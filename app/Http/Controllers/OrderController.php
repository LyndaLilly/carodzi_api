<?php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ProductUpload;
use App\Notifications\NewOrderNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Store a single order (one product per order)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'delivery_fullname'       => 'required|string|max:255',
            'delivery_email'          => 'required|email',
            'delivery_phone'          => 'required|string|max:20',
            'buyer_delivery_location' => 'required|string|max:255',
            'product_id'              => 'required|integer|exists:productupload,id',
            'quantity'                => 'required|integer|min:1',
            'price'                   => 'required|numeric|min:0',
            'total_price'             => 'required|numeric|min:0',
            'payment_method'          => 'nullable|string|max:50',
        ]);

        try {
            DB::beginTransaction();

            // ✅ Get product info
            $product = ProductUpload::findOrFail($request->product_id);

            // ✅ Create a single order linked directly to the product
            $order = Order::create([
                'buyer_id'                => auth()->id() ?? null, // optional if buyers can order while logged in
                'delivery_fullname'       => $request->delivery_fullname,
                'delivery_email'          => $request->delivery_email,
                'delivery_phone'          => $request->delivery_phone,
                'buyer_delivery_location' => $request->buyer_delivery_location,
                'product_id'              => $request->product_id,
                'seller_id'               => $product->seller_id,
                'quantity'                => $request->quantity,
                'price'                   => $request->price,
                'total_amount'            => $request->total_price,
                'payment_method'          => $request->payment_method ?? 'contact_seller',
                'payment_status'          => 'pending',
            ]);

            $seller = $product->seller;
            \Log::info('Seller for notification:', ['seller' => $seller]);

            // --- Notify the seller via Laravel Notification ---
            if ($seller) {
                $seller->notify(new NewOrderNotification($product->name));
            }

            \Log::info('✅ Order created successfully', $order->toArray());

            DB::commit();

            return response()->json([
                'message'  => 'Order placed successfully!',
                'order_id' => $order->id,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('❌ Order creation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'error'   => 'Failed to place order.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Admin: View all orders
     */
    public function index()
    {
        $orders = Order::with(['product', 'seller'])
            ->latest()
            ->get();

        return response()->json($orders);
    }

    /**
     * Show a single order with its product and seller
     */
    public function show($id)
    {
        $order = Order::with(['product', 'seller'])
            ->findOrFail($id);

        return response()->json($order);
    }

    public function buyerOrders()
    {
        try {
            $buyerId = auth()->id();

            if (! $buyerId) {
                \Log::warning('⚠️ Buyer not authenticated while trying to fetch orders');
                return response()->json([
                    'error' => 'Unauthorized. Please log in as a buyer.',
                ], 401);
            }

            \Log::info('🔍 Fetching orders for buyer ID:', ['buyer_id' => $buyerId]);

            $orders = Order::with(['product', 'seller'])
                ->where('buyer_id', $buyerId)
                ->latest()
                ->get();

            \Log::info('✅ Orders fetched successfully', ['total_orders' => $orders->count()]);

            return response()->json($orders);
        } catch (\Throwable $e) {
            // 🔥 Log the detailed error
            \Log::error('❌ Failed to fetch buyer orders', [
                'error_message' => $e->getMessage(),
                'file'          => $e->getFile(),
                'line'          => $e->getLine(),
                'trace'         => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error'   => 'Something went wrong while fetching your orders.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

}
