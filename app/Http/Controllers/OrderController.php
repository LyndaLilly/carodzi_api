<?php
namespace App\Http\Controllers;

use App\Helpers\ExpoPush;
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

        $paymentMethod = $request->payment_method ?? 'contact_seller';

        if ($paymentMethod === 'paystack') {
            // For Paystack, order creation will happen after payment
            return response()->json([
                'success' => true,
                'message' => 'Ready to initiate Paystack payment',
            ]);
        }

        try {
            DB::beginTransaction();

            $product = ProductUpload::findOrFail($request->product_id);

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
                'payment_method'          => $paymentMethod,
                'payment_status'          => 'pending',
                'status'                  => 'pending',
            ]);

            $this->notifySeller($product, $order);

            DB::commit();

            return response()->json([
                'message'  => 'Order placed successfully!',
                'order_id' => $order->id,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'error'   => 'Failed to place order.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function paystackInit(Request $request)
    {
        $request->validate([
            'delivery_fullname'       => 'required|string|max:255',
            'delivery_email'          => 'required|email',
            'delivery_phone'          => 'required|string|max:20',
            'buyer_delivery_location' => 'required|string|max:255',
            'product_id'              => 'required|integer|exists:productupload,id',
            'quantity'                => 'required|integer|min:1',
            'price'                   => 'required|numeric|min:0',
            'total_price'             => 'required|numeric|min:0',
        ]);

        $product = ProductUpload::findOrFail($request->product_id);

        try {
            $secretKey = config('services.paystack.secret_key');
            $baseUrl   = config('services.paystack.base_url', 'https://api.paystack.co');

            $reference = 'TEMP-' . time() . '-' . rand(1000, 9999);

            $payload = [
                'email'        => $request->delivery_email,
                'amount'       => $request->total_price * 100,
                'reference'    => $reference,
                'callback_url' => url('/api/order/paystack/callback'),
                'metadata'     => [
                    'buyer_id'                => auth()->id(), // ✅ ADD THIS
                    'delivery_fullname'       => $request->delivery_fullname,
                    'delivery_email'          => $request->delivery_email,
                    'delivery_phone'          => $request->delivery_phone,
                    'buyer_delivery_location' => $request->buyer_delivery_location,
                    'product_id'              => $request->product_id,
                    'quantity'                => $request->quantity,
                    'price'                   => $request->price,
                    'total_price'             => $request->total_price,
                ],
            ];

            $response = Http::withToken($secretKey)->post($baseUrl . '/transaction/initialize', $payload);
            $data     = $response->json();

            return response()->json([
                'success'           => true,
                'authorization_url' => $data['data']['authorization_url'],
                'reference'         => $data['data']['reference'],
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize Paystack transaction',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function orderPaystackCallback(Request $request)
    {
        $reference = $request->query('reference');

        if (! $reference) {
            return response()->json([
                'success' => false,
                'message' => 'Missing payment reference',
            ], 400);
        }

        // 1️⃣ VERIFY WITH PAYSTACK
        $secretKey = config('services.paystack.secret_key');
        $baseUrl   = config('services.paystack.base_url', 'https://api.paystack.co');

        $response = Http::withToken($secretKey)
            ->get("{$baseUrl}/transaction/verify/{$reference}");

        $data = $response->json();

        if (
            ! isset($data['status']) ||
            ! $data['status'] ||
            $data['data']['status'] !== 'success'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed',
            ], 400);
        }

        // 2️⃣ 👉 PUT DUPLICATE CHECK RIGHT HERE 👈
        if (Order::where('payment_reference', $reference)->exists()) {
            return response()->json([
                'success' => true,
                'message' => 'Order already processed',
            ]);
        }

        // 3️⃣ EXTRACT METADATA
        $tx   = $data['data'];
        $meta = $tx['metadata'];

        $product = ProductUpload::findOrFail($meta['product_id']);

        // 4️⃣ CREATE ORDER (ONLY ONCE)
        $order = Order::create([
            'buyer_id'                => $meta['buyer_id'],
            'delivery_fullname'       => $meta['delivery_fullname'],
            'delivery_email'          => $meta['delivery_email'],
            'delivery_phone'          => $meta['delivery_phone'],
            'buyer_delivery_location' => $meta['buyer_delivery_location'],
            'product_id'              => $meta['product_id'],
            'seller_id'               => $product->seller_id,
            'quantity'                => $meta['quantity'],
            'price'                   => $meta['price'],
            'total_amount'            => $meta['total_price'],
            'payment_method'          => 'paystack',
            'payment_status'          => 'paid',
            'status'                  => 'completed',
            'payment_reference'       => $reference,
        ]);

        $this->notifySeller($product, $order);

        return response()->json([
            'success'  => true,
            'message'  => 'Payment verified and order created',
            'order_id' => $order->id,
        ]);
    }

    public function uploadBitcoinProof(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);

        if (auth()->id() !== $order->buyer_id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'bitcoin_proof' => 'required|string|max:255',
        ]);

        try {
            $order->update([
                'bitcoin_proof'  => $request->bitcoin_proof,
                'payment_method' => 'bitcoin',
                'payment_status' => 'pending',
                'status'         => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bitcoin payment proof uploaded successfully. Awaiting approval.',
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

    private function notifySeller($product, $order)
    {
        $seller = $product->seller;
        if (! $seller) {
            return;
        }

        $seller->notify(new NewOrderNotification($product->name));

        if ($seller->expo_push_token) {
            try {
                ExpoPush::send(
                    $seller->expo_push_token,
                    'New Order Received',
                    "You have a new order for {$product->name}",
                    ['order_id' => $order->id]
                );
            } catch (\Throwable $e) {
                \Log::error('Failed to send push notification', ['error' => $e->getMessage()]);
            }
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

            if ($tx && isset($tx['status']) && $tx['status'] === 'success') {
                // Mark order as paid and completed
                $order->update([
                    'payment_status'    => 'paid',
                    'status'            => 'completed',
                    'payment_method'    => 'paystack',
                    'payment_reference' => $reference,
                ]);

                // --- Notify the seller via push ---
                $seller = $order->product->seller;
                if ($seller && $seller->expo_push_token) {
                    \Log::info('Attempting push after Paystack payment', [
                        'seller_id'  => $seller->id,
                        'expo_token' => $seller->expo_push_token,
                        'order_id'   => $order->id,
                    ]);

                    try {
                        $response = ExpoPush::send(
                            $seller->expo_push_token,
                            'New Order Received',
                            "You have a new order for {$order->product->name}",
                            ['order_id' => $order->id]
                        );

                        \Log::info('✅ Push sent after payment', ['response' => $response->body()]);
                    } catch (\Throwable $e) {
                        \Log::error('❌ Failed to send push after payment', ['error' => $e->getMessage()]);
                    }
                }

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

    public function buyerSingleOrder($id)
    {
        $buyerId = auth()->id();
        if (! $buyerId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $order = Order::with([
            'product.images',
            'seller.profile',
            'seller.professionalProfile',
        ])
            ->where('buyer_id', $buyerId)
            ->findOrFail($id);

        // Pick correct profile (professional or other)
        $seller  = $order->seller;
        $profile = $seller->is_professional ? $seller->professionalProfile : $seller->profile;

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
                    'id'             => $seller->id,
                    'business_name'  => $profile->business_name ?? null,
                    'email'          => $seller->email,
                    'business_email' => $profile->business_email ?? null,
                    'phone'          => $profile->phone ?? null,
                    'address'        => $profile->address ?? null,
                ],
            ],
        ]);
    }

}
