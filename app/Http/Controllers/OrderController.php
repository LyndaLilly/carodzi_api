<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * Store a new order (buyer → seller linkage)
     */
    public function store(Request $request)
    {
        // 🔹 Debug logs — check which guard is used and who is authenticated
        Log::info('Incoming Order Request', [
            'headers' => $request->headers->all(),
            'bearer_token' => $request->bearerToken(),
            'buyer_guard_user' => auth()->guard('buyer')->user(),
            'default_guard_user' => auth()->user(),
        ]);

        // 🔹 Authenticate buyer via Sanctum buyer guard
        $buyer = auth()->guard('buyer')->user();

        if (!$buyer) {
            Log::warning('Unauthorized order attempt', [
                'token' => $request->bearerToken(),
            ]);

            return response()->json(['error' => 'Unauthorized buyer.'], 401);
        }

        // 🔹 Validate incoming order data
        $request->validate([
            'buyer_fullname' => 'required|string|max:255',
            'buyer_email' => 'required|email',
            'buyer_phone' => 'required|string|max:20',
            'buyer_delivery_location' => 'required|string|max:255',
            'total_price' => 'required|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:productupload,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // ✅ Create main order record and link buyer
            $order = Order::create([
                'buyer_id' => $buyer->id,
                'buyer_fullname' => $request->buyer_fullname,
                'buyer_email' => $request->buyer_email,
                'buyer_phone' => $request->buyer_phone,
                'buyer_delivery_location' => $request->buyer_delivery_location,
                'total_amount' => $request->total_price,
                'status' => 'pending',
                'payment_method' => $request->payment_method ?? 'contact_seller',
            ]);

            // ✅ Create associated order items
            foreach ($request->items as $item) {
                $product = ProductUpload::find($item['product_id']);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'seller_id' => $product->seller_id, // link seller
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ]);
            }

            DB::commit();

            Log::info('Order placed successfully', [
                'buyer_id' => $buyer->id,
                'order_id' => $order->id,
            ]);

            return response()->json([
                'message' => 'Order placed successfully!',
                'order_id' => $order->id,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Order creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to place order.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Admin: View all orders
     */
    public function index()
    {
        $orders = Order::with(['items.product', 'items.seller'])
            ->latest()
            ->get();

        return response()->json($orders);
    }

    /**
     * Show single order with items
     */
    public function show($id)
    {
        $order = Order::with(['items.product', 'items.seller'])
            ->findOrFail($id);

        return response()->json($order);
    }
}
