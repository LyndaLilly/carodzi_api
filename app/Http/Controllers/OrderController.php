<?php
namespace App\Http\Controllers;

use App\Models\DirectInquiry;
use App\Models\Order;
use App\Models\ProductReview;
use App\Models\ProductUpload;
use App\Notifications\NewOrderNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{

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
                'status'                  => 'pending',
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

    public function index()
    {
        $orders = Order::with(['product', 'seller'])
            ->latest()
            ->get();

        return response()->json($orders);
    }

    public function show($id)
    {
        $order = Order::with(['product', 'seller'])
            ->findOrFail($id);

        return response()->json($order);
    }

    private function getImageUrl($path)
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return asset('public/uploads/' . $path);
    }

    private function transformOrder($order)
    {
        $product    = $order->product;
        $firstImage = $product?->images?->first()?->image_path ?? $product?->images?->first()?->image_url;
        $image      = $this->getImageUrl($firstImage);

        // ✅ Get review if it exists for this buyer and order
        $review = ProductReview::where('buyer_id', $order->buyer_id)
            ->where('order_id', $order->id)
            ->where('productupload_id', $product->id)
            ->first();

        return [
            'order_id'       => $order->id,
            'product_id'     => $product->id,
            'name'           => $product->name,
            'price'          => $product->price,
            'total_amount'   => $order->total_amount,
            'payment_status' => $order->payment_status,
            'status'         => $order->status,
            'created_at'     => $order->created_at,
            'image'          => $image,
            'seller'         => $order->seller?->business_name,
            'review'         => $review ? [
                'rating'  => $review->rating,
                'comment' => $review->review,
            ] : null,
        ];
    }

    public function buyerOrders()
    {
        $buyerId = auth()->id();
        if (! $buyerId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $orders = Order::with(['product.images', 'seller'])
            ->where('buyer_id', $buyerId)
            ->latest()
            ->get();

        $data = $orders->map(fn($order) => $this->transformOrder($order));

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

public function sellerOrdersSummary()
{
    try {
        $sellerId = auth()->id();

        if (! $sellerId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // --- Online Orders Counts ---
        $onlineOrdersCounts = Order::where('seller_id', $sellerId)
            ->selectRaw("
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                COUNT(*) as total
            ")
            ->first();

        // --- Direct Inquiries Counts ---
        $directInquiryCounts = DirectInquiry::where('seller_id', $sellerId)
            ->selectRaw("
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                COUNT(*) as total
            ")
            ->first();

        // --- Totals ---
        $totalOrders = $onlineOrdersCounts->total + $directInquiryCounts->total;
        $totalCompleted = $onlineOrdersCounts->completed + $directInquiryCounts->completed;
        $totalPending = $onlineOrdersCounts->pending + $directInquiryCounts->pending;

        // --- Total Revenue from online orders only ---
        $totalRevenue = Order::where('seller_id', $sellerId)
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        return response()->json([
            'success' => true,
            'online_orders' => [
                'pending'   => (int) $onlineOrdersCounts->pending,
                'completed' => (int) $onlineOrdersCounts->completed,
                'total'     => (int) $onlineOrdersCounts->total,
            ],
            'direct_inquiries' => [
                'pending'   => (int) $directInquiryCounts->pending,
                'completed' => (int) $directInquiryCounts->completed,
                'total'     => (int) $directInquiryCounts->total,
            ],
            'totals' => [
                'total_orders'    => (int) $totalOrders,
                'total_completed' => (int) $totalCompleted,
                'total_pending'   => (int) $totalPending,
                'total_revenue'   => (float) $totalRevenue,
            ],
        ]);

    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'error'   => 'Something went wrong',
            'details' => $e->getMessage(),
        ], 500);
    }
}

    public function sellerWeeklyRevenue()
    {
        try {
            $sellerId = auth()->id();
            if (! $sellerId) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Get the last 7 days' revenue
            $weeklyRevenue = Order::where('seller_id', $sellerId)
                ->where('payment_status', 'paid')
                ->where('created_at', '>=', now()->subDays(6)) // past 7 days including today
                ->selectRaw('DATE(created_at) as date, SUM(total_amount) as revenue')
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get();

            // Fill missing days (so chart always shows 7 days)
            $days = collect();
            for ($i = 6; $i >= 0; $i--) {
                $date    = now()->subDays($i)->format('Y-m-d');
                $dayName = now()->subDays($i)->format('D');
                $revenue = $weeklyRevenue->firstWhere('date', $date)->revenue ?? 0;

                $days->push([
                    'name'    => $dayName,
                    'date'    => $date,
                    'revenue' => (float) $revenue,
                ]);
            }

            return response()->json([
                'success' => true,
                'data'    => $days,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Failed to fetch weekly revenue',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function sellerOrders(Request $request)
    {
        \Log::info('🛒 SellerOrders API hit', [
            'auth_user' => $request->user(),
            'token'     => $request->bearerToken(),
        ]);

        $seller = $request->user();

        if (! $seller) {
            \Log::warning('🚫 Unauthorized access to SellerOrders');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // ✅ Load buyer (with their profile) and product info
        $orders = Order::where('seller_id', $seller->id)
            ->with([
                'buyer.profile', // Load buyers and their profiles
                'product',       // Load product info
            ])
            ->latest()
            ->get();

        \Log::info('✅ SellerOrders fetched successfully', [
            'seller_id'    => $seller->id,
            'orders_count' => $orders->count(),
        ]);

        return response()->json([
            'success' => true,
            'orders'  => $orders,
        ]);
    }

}
