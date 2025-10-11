<?php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ProductUpload;
use App\Notifications\NewOrderNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    private function getImageUrl($path)
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return asset('public/uploads/' . $path); // ✅ include public in URL
    }

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
                return response()->json([
                    'error' => 'Unauthorized. Please log in as a buyer.',
                ], 401);
            }

            $orders = Order::with(['product.images', 'seller'])
                ->where('buyer_id', $buyerId)
                ->latest()
                ->get()
                ->map(function ($order) {
                    // ✅ Safely handle product and images
                    if ($order->product && $order->product->images) {
                        foreach ($order->product->images as $image) {
                            // Use the helper function to generate full URL
                            $image->image_url = $this->getImageUrl($image->image_url);
                        }
                    }

                    // Optionally include first image for convenience
                    $firstImage                 = $order->product?->images->first()?->image_url;
                    $order->product_first_image = $this->getImageUrl($firstImage);

                    return $order;
                });

            return response()->json($orders);

        } catch (\Throwable $e) {
            return response()->json([
                'error'   => 'Something went wrong while fetching your orders.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

}
