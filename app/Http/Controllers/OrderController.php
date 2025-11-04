<?php
namespace App\Http\Controllers;

use App\Models\DirectInquiry;
use App\Models\Order;
use App\Models\ProductReview;
use App\Models\ProductUpload;
use App\Notifications\NewOrderNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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
                'buyer_id'                => auth()->id() ?? null,
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
            $totalOrders    = $onlineOrdersCounts->total + $directInquiryCounts->total;
            $totalCompleted = $onlineOrdersCounts->completed + $directInquiryCounts->completed;
            $totalPending   = $onlineOrdersCounts->pending + $directInquiryCounts->pending;

            // --- Total Revenue from online orders only ---
            $totalRevenue = Order::where('seller_id', $sellerId)
                ->where('payment_status', 'paid')
                ->sum('total_amount');

            return response()->json([
                'success'          => true,
                'online_orders'    => [
                    'pending'   => (int) $onlineOrdersCounts->pending,
                    'completed' => (int) $onlineOrdersCounts->completed,
                    'total'     => (int) $onlineOrdersCounts->total,
                ],
                'direct_inquiries' => [
                    'pending'   => (int) $directInquiryCounts->pending,
                    'completed' => (int) $directInquiryCounts->completed,
                    'total'     => (int) $directInquiryCounts->total,
                ],
                'totals'           => [
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

    public function verifyPayment(Request $request, Order $order)
    {
        // Validate input
        $request->validate([
            'reference' => 'required|string',
        ]);

        // Ensure authenticated buyer is the owner of this order
        $buyerId = auth()->id();
        if (! $buyerId || $order->buyer_id !== $buyerId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            // Read keys and base url from config/services.php
            $secretKey = config('services.paystack.secret_key');
            $baseUrl   = config('services.paystack.base_url', 'https://api.paystack.co');

            // Call Paystack verify endpoint
            $reference = $request->input('reference');

            $response = Http::withToken($secretKey)
                ->get($baseUrl . "/transaction/verify/{$reference}");

            if (! $response->successful()) {
                // Log for debugging
                \Log::error('Paystack verify HTTP error', [
                    'order_id' => $order->id,
                    'status'   => $response->status(),
                    'body'     => $response->body(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to communicate with Paystack',
                    'status'  => $response->status(),
                ], 502);
            }

            $data = $response->json();

            // Paystack returns a "status" top-level boolean and nested data
            if (! isset($data['status']) || $data['status'] !== true) {
                // verification failed (invalid reference, etc.)
                \Log::warning('Paystack verification failed', ['order_id' => $order->id, 'response' => $data]);

                return response()->json([
                    'success'  => false,
                    'message'  => 'Payment not verified',
                    'paystack' => $data,
                ], 400);
            }

            $tx = $data['data'] ?? null;

            // Confirm the transaction success and amount/order match if you store amounts
            if ($tx && isset($tx['status']) && $tx['status'] === 'success') {
                                                                                     // optional: validate amount matches order->total_amount (remember amount returned by Paystack is in kobo)
                $paystackAmount = isset($tx['amount']) ? $tx['amount'] / 100 : null; // to Naira
                if ($paystackAmount !== null && (float) $paystackAmount != (float) $order->total_amount) {
                    \Log::warning('Paystack amount mismatch', [
                        'order_id'        => $order->id,
                        'order_amount'    => $order->total_amount,
                        'paystack_amount' => $paystackAmount,
                    ]);
                    // You may still accept but flag it. For now we continue to mark paid.
                }

                // Mark order as paid
                $order->update([
                    'payment_status' => 'paid',
                    'status'         => 'completed',
                    'payment_method' => 'paystack',
                    // optionally: store paystack reference somewhere if you have a field for it
                    //'payment_reference' => $reference,
                ]);

                return response()->json([
                    'success'  => true,
                    'message'  => 'Payment verified and order updated',
                    'order_id' => $order->id,
                ]);
            }

            // Transaction not successful
            return response()->json([
                'success'  => false,
                'message'  => 'Transaction not successful',
                'paystack' => $tx,
            ], 400);

        } catch (\Throwable $e) {
            \Log::error('Paystack verification error', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error during verification',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function uploadBitcoinProof(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);

        // ensure only the buyer can upload
        if (auth()->id() !== $order->buyer_id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'bitcoin_proof' => 'required|string|max:255', // this will be the hash
        ]);

        try {
            $order->update([
                'bitcoin_proof'  => $request->bitcoin_proof, // hash string
                'payment_method' => 'bitcoin',
                'payment_status' => 'pending',
                'status'         => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bitcoin payment proof uploaded successfully. Awaiting manual approval.',
                'order'   => $order,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload Bitcoin proof',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function buyerSingleOrder($id)
    {
        $buyerId = auth()->id();
        if (! $buyerId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $order = Order::with([
            'product.images', // get all product images
            'seller',         // get seller details
        ])
            ->where('buyer_id', $buyerId)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'order'   => [
                'id'                      => $order->id,
                'product'                 => [
                    'id'          => $order->product->id,
                    'name'        => $order->product->name,
                    'description' => $order->product->description,
                    'price'       => $order->product->price,
                    'images'      => $order->product->images->map(fn($img) => asset('public/uploads/' . $img->image_path)),
                ],
                'quantity'                => $order->quantity,
                'total_amount'            => $order->total_amount,
                'payment_status'          => $order->payment_status,
                'payment_method'          => $order->payment_method,
                'status'                  => $order->status,
                'buyer_delivery_location' => $order->buyer_delivery_location,
                'delivery_fullname'       => $order->delivery_fullname,
                'delivery_email'          => $order->delivery_email,
                'delivery_phone'          => $order->delivery_phone,
                'created_at'              => $order->created_at->format('Y-m-d H:i'),
                'seller'                  => [
                    'id'            => $order->seller->id,
                    'business_name' => $order->seller->business_name,
                    'email'         => $order->seller->email,
                ],
            ],
        ]);
    }

}
