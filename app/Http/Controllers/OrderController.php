<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ProductUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Store a single order (one product per order)
     */
    public function store(Request $request)
    {
        $request->validate([
            'buyer_fullname' => 'required|string|max:255',
            'buyer_email' => 'required|email',
            'buyer_phone' => 'required|string|max:20',
            'buyer_delivery_location' => 'required|string|max:255',
            'product_id' => 'required|integer|exists:productupload,id',
            'quantity' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'total_price' => 'required|numeric|min:0',
            'payment_method' => 'nullable|string|max:50',
        ]);

        try {
            DB::beginTransaction();

            // ✅ Get product info
            $product = ProductUpload::findOrFail($request->product_id);

            // ✅ Create a single order linked directly to the product
            $order = Order::create([
                'buyer_fullname' => $request->buyer_fullname,
                'buyer_email' => $request->buyer_email,
                'buyer_phone' => $request->buyer_phone,
                'buyer_delivery_location' => $request->buyer_delivery_location,
                'product_id' => $request->product_id,
                'seller_id' => $product->seller_id,
                'quantity' => $request->quantity,
                'price' => $request->price,
                'total_amount' => $request->total_price,
                'payment_method' => $request->payment_method ?? 'contact_seller',
                'status' => 'pending',
            ]);

             \Log::info('Order validated successfully', $validated);

            DB::commit();

            return response()->json([
                'message' => 'Order placed successfully!',
                'order_id' => $order->id,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
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
}
