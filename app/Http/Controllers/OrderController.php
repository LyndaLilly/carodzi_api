<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Buyer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductUpload;
use App\Models\Seller;
use App\Notifications\AdminOrderCreated;
use App\Notifications\SellerOrderAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class OrderController extends Controller
{
    /**
     * 🛒 Store order (Paystack or other)
     */
    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:productupload,id',
            'items.*.quantity' => 'required|integer|min:1',
            'delivery_address' => 'required|string',
            'delivery_location' => 'nullable|string',
            'delivery_fee' => 'nullable|numeric',
            'payment_method' => 'required|in:paystack,crypto,other',
            'payment_reference' => 'nullable|string',
            'crypto_proof' => 'nullable|string',
        ]);

        $buyer = Auth::user();
        if (! $buyer || ! $buyer instanceof Buyer) {
            return response()->json(['message' => 'Unauthorized or invalid buyer'], 401);
        }

        $totalAmount = 0;
        $deliveryFee = $request->delivery_fee ?? 0;

        // 🧮 Calculate total and prepare items
        $itemsData = [];
        foreach ($request->items as $item) {
            $product = ProductUpload::findOrFail($item['product_id']);
            $seller = Seller::find($product->seller_id);

            $subtotal = $product->price * $item['quantity'];
            $totalAmount += $subtotal;

            $itemsData[] = [
                'product_id' => $product->id,
                'seller_id' => $seller ? $seller->id : null,
                'product_name' => $product->name,
                'product_price' => $product->price,
                'quantity' => $item['quantity'],
                'subtotal' => $subtotal,
            ];
        }

        $grandTotal = $totalAmount + $deliveryFee;

        // 📝 Create main order record
        $order = Order::create([
            'buyer_id' => $buyer->id,
            'delivery_address' => $request->delivery_address,
            'delivery_location' => $request->delivery_location,
            'delivery_fee' => $deliveryFee,
            'payment_method' => $request->payment_method,
            'payment_reference' => $request->payment_reference,
            'crypto_proof' => $request->crypto_proof,
            'total_amount' => $grandTotal,
        ]);

        // 🧾 Create order items
        foreach ($itemsData as $itemData) {
            $order->items()->create($itemData);
        }

        // 📨 Notify admins
        $admins = Admin::where('status', true)->get();
        Notification::send($admins, new AdminOrderCreated($order));

        // 🔔 Notify all sellers involved
        $sellerIds = collect($itemsData)->pluck('seller_id')->unique()->filter();
        $sellers = Seller::whereIn('id', $sellerIds)->get();
        Notification::send($sellers, new SellerOrderAlert($order));

        return response()->json([
            'message' => 'Order placed successfully!',
            'order' => $order->load('items.product', 'items.seller'),
        ]);
    }

    /**
     * 💰 Store crypto order
     */
    public function storeCryptoOrder(Request $request)
    {
        Log::info('Incoming Crypto Order Request:', $request->all());

        try {
            $request->validate([
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:productupload,id',
                'items.*.quantity' => 'required|integer|min:1',
                'delivery_address' => 'required|string',
                'delivery_location' => 'nullable|string',
                'delivery_fee' => 'nullable|numeric',
                'crypto_proof' => 'required|string',
            ]);

            $buyer = Auth::user();
            if (! $buyer || ! $buyer instanceof Buyer) {
                return response()->json(['message' => 'Unauthorized or invalid buyer'], 401);
            }

            $totalAmount = 0;
            $deliveryFee = $request->delivery_fee ?? 0;
            $itemsData = [];

            foreach ($request->items as $item) {
                $product = ProductUpload::findOrFail($item['product_id']);
                $seller = Seller::find($product->seller_id);

                $subtotal = $product->price * $item['quantity'];
                $totalAmount += $subtotal;

                $itemsData[] = [
                    'product_id' => $product->id,
                    'seller_id' => $seller ? $seller->id : null,
                    'product_name' => $product->name,
                    'product_price' => $product->price,
                    'quantity' => $item['quantity'],
                    'subtotal' => $subtotal,
                ];
            }

            $grandTotal = $totalAmount + $deliveryFee;

            $order = Order::create([
                'buyer_id' => $buyer->id,
                'delivery_address' => $request->delivery_address,
                'delivery_location' => $request->delivery_location,
                'delivery_fee' => $deliveryFee,
                'payment_method' => 'crypto',
                'payment_status' => 'pending',
                'crypto_proof' => $request->crypto_proof,
                'total_amount' => $grandTotal,
            ]);

            foreach ($itemsData as $itemData) {
                $order->items()->create($itemData);
            }

            $admins = Admin::where('status', true)->get();
            Notification::send($admins, new AdminOrderCreated($order));

            $sellerIds = collect($itemsData)->pluck('seller_id')->unique()->filter();
            $sellers = Seller::whereIn('id', $sellerIds)->get();
            Notification::send($sellers, new SellerOrderAlert($order));

            return response()->json([
                'message' => 'Crypto order submitted successfully! Awaiting admin confirmation.',
                'order' => $order->load('items.product', 'items.seller'),
            ]);

        } catch (\Throwable $e) {
            Log::error('❌ Crypto order failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while processing the crypto order.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 📋 Buyer orders
     */
    public function index()
    {
        $buyer = Auth::user();
        $orders = Order::where('buyer_id', $buyer->id)
            ->with(['items.product', 'items.seller'])
            ->latest()
            ->get();

        return response()->json($orders);
    }

    /**
     * 🔍 View single order
     */
    public function show($id)
    {
        $buyer = Auth::user();
        $order = Order::where('buyer_id', $buyer->id)
            ->with(['items.product', 'items.seller'])
            ->findOrFail($id);

        return response()->json($order);
    }
}
