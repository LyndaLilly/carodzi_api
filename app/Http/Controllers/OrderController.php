<?php
namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Buyer;
use App\Models\Order;
use App\Models\ProductUpload;
use App\Models\Seller;
use App\Notifications\AdminOrderCreated;
use App\Notifications\SellerOrderAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class OrderController extends Controller
{
    /**
     * 🛒 Store a newly created order.
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id'        => 'required|exists:productupload,id',
            'quantity'          => 'required|integer|min:1',
            'delivery_address'  => 'required|string',
            'delivery_location' => 'nullable|string',
            'delivery_fee'      => 'nullable|numeric',
            'payment_method'    => 'required|in:paystack,crypto,other',
            'payment_reference' => 'nullable|string',
            'crypto_proof'      => 'nullable|string',
        ]);

        // ✅ Authenticated buyer (assuming Sanctum or similar)
        $buyer = Auth::user();

        if (! $buyer || ! $buyer instanceof Buyer) {
            return response()->json(['message' => 'Unauthorized or invalid buyer'], 401);
        }

        // 🧩 Get product and seller
        $product = ProductUpload::findOrFail($request->product_id);
        $seller  = Seller::find($product->seller_id);

        // 💰 Calculate total
        $productPrice = $product->price;
        $deliveryFee  = $request->delivery_fee ?? 0;
        $totalAmount  = ($productPrice * $request->quantity) + $deliveryFee;

        // 📝 Create Order
        $order = Order::create([
            'buyer_id'          => $buyer->id,
            'seller_id'         => $seller ? $seller->id : null,
            'product_id'        => $product->id,
            'product_name'      => $product->name,
            'product_price'     => $productPrice,
            'quantity'          => $request->quantity,
            'delivery_address'  => $request->delivery_address,
            'delivery_location' => $request->delivery_location,
            'delivery_fee'      => $deliveryFee,
            'payment_method'    => $request->payment_method,
            'payment_reference' => $request->payment_reference,
            'crypto_proof'      => $request->crypto_proof,
            'total_amount'      => $totalAmount,
        ]);

        // 📨 Notify Admin (email)
        $admins = Admin::where('status', true)->get();
        Notification::send($admins, new AdminOrderCreated($order));

        // 🔔 Notify Seller (dashboard notification)
        if ($seller) {
            $seller->notify(new SellerOrderAlert($order));
        }

        return response()->json([
            'message' => 'Order placed successfully!',
            'order'   => $order,
        ]);
    }

    public function index()
    {
        $buyer = Auth::user();

        $orders = Order::where('buyer_id', $buyer->id)
            ->with(['product', 'seller'])
            ->latest()
            ->get();

        return response()->json($orders);
    }

    public function show($id)
    {
        $buyer = Auth::user();

        $order = Order::where('buyer_id', $buyer->id)
            ->with(['product', 'seller'])
            ->findOrFail($id);

        return response()->json($order);
    }

}
