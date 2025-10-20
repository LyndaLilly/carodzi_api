<?php
namespace App\Http\Controllers;

use App\Models\Promote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PromoteController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'plan'                  => 'required|string|in:basic,standard,premium',
            'payment_method'        => 'required|in:paystack,crypto',
            'transaction_reference' => 'nullable|string',
            'crypto_hash'           => 'required_if:payment_method,crypto|string|max:255',
        ]);

        $seller = $request->user();

        // ✅ 1. Prevent duplicate active promotions
        $existingPromotion = Promote::where('seller_id', $seller->id)
            ->where('is_active', true)
            ->where('end_date', '>', now())
            ->first();

        if ($existingPromotion) {
            return response()->json([
                'status'  => 'error',
                'message' => 'You already have an active promotion until ' .
                $existingPromotion->end_date->format('M d, Y') . '.',
            ], 403);
        }

        // ✅ 2. Fetch plan details
        $plans = config('promote.plans');
        $plan  = $request->plan;

        if (! isset($plans[$plan])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid plan selected.',
            ], 422);
        }

        $planDetails = $plans[$plan];
        $startDate   = now();
        $endDate     = $startDate->copy()->addDays($planDetails['duration']);

        // ✅ 3. Create promotion record
        $promote = Promote::create([
            'seller_id'             => $seller->id,
            'plan'                  => $plan,
            'duration'              => $planDetails['duration'],
            'start_date'            => $startDate,
            'end_date'              => $endDate,
            'is_active'             => $request->payment_method === 'paystack',
            'is_approved'           => $request->payment_method === 'paystack',
            'payment_method'        => $request->payment_method,
            'transaction_reference' => $request->transaction_reference,
            'crypto_hash'           => $request->crypto_hash,
            'amount'                => $planDetails['price'],
        ]);

        return response()->json([
            'status'    => 'success',
            'message'   => 'Promotion submitted successfully.',
            'promotion' => $promote,
        ], 201);
    }

    public function approve($id)
    {
        $promotion = Promote::findOrFail($id);

        if ($promotion->is_approved) {
            return response()->json([
                'status'  => 'info',
                'message' => 'Promotion already approved.',
            ]);
        }

        $promotion->update([
            'is_approved' => true,
            'is_active'   => true,
            'approved_at' => now(),
        ]);

        return response()->json([
            'status'    => 'success',
            'message'   => 'Promotion approved successfully.',
            'promotion' => $promotion,
        ]);
    }

    public function featured()
    {
        $featuredPromotions = Promote::where('is_active', true)
            ->where('is_approved', true)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->with(['seller.profile'])
            ->get();

        $featuredSellers = $featuredPromotions->map(function ($promo) {
            $seller = $promo->seller;

            // ✅ Calculate seller’s average rating from ProductReview model
            $averageRating = \App\Models\ProductReview::whereHas('product', function ($query) use ($seller) {
                $query->where('seller_id', $seller->id);
            })
                ->where('is_visible', true)
                ->avg('rating');

            $profile = $seller->profile;

            return [
                'id'             => $seller->id,
                'business_name'  => $profile->business_name ?? ($seller->firstname . ' ' . $seller->lastname),
                'logo'           => $profile->profile_image ? 'profile_images/' . basename($profile->profile_image) : null,
                'tagline'        => $profile->tagline ?? null,
                'is_verified'    => $seller->verified,
                'average_rating' => round($averageRating ?? 0, 1),
            ];
        });

        return response()->json([
            'success' => true,
            'sellers' => $featuredSellers,
        ]);
    }

    public function expirePromotions()
    {
        $expired = Promote::where('is_active', true)
            ->where('end_date', '<', now())
            ->update(['is_active' => false, 'expired_at' => now()]);

        return response()->json([
            'message' => "{$expired} promotions expired successfully.",
        ]);
    }

    public function initializePaystackPayment(Request $request)
    {
        $request->validate([
            'plan' => 'required|string|in:basic,standard,premium',
        ]);

        $seller = $request->user();

        // ✅ Prevent duplicate active promotions before payment
        $existingPromotion = Promote::where('seller_id', $seller->id)
            ->where('is_active', true)
            ->where('end_date', '>', now())
            ->first();

        if ($existingPromotion) {
            return response()->json([
                'status'  => false,
                'message' => 'You already have an active promotion until ' .
                $existingPromotion->end_date->format('M d, Y') . '.',
            ], 403);
        }

        // ✅ Fetch plan details
        $plans = config('promote.plans');
        $plan  = $request->plan;

        if (! isset($plans[$plan])) {
            return response()->json(['error' => 'Invalid plan selected'], 422);
        }

        $planDetails = $plans[$plan];
        $amount      = $planDetails['price'] * 100;

        $response = Http::withToken(config('services.paystack.secret_key'))
            ->post(config('services.paystack.base_url') . '/transaction/initialize', [
                'email'        => $seller->email,
                'amount'       => $amount,
                'callback_url' => url('/api/paystack/callback'),
                'metadata'     => [
                    'seller_id' => $seller->id,
                    'plan'      => $plan,
                ],
            ]);

        if (! $response->successful()) {
            return response()->json(['error' => 'Failed to connect to Paystack'], 500);
        }

        $data = $response->json();

        return response()->json([
            'status'            => true,
            'message'           => 'Payment initialized successfully',
            'authorization_url' => $data['data']['authorization_url'],
            'reference'         => $data['data']['reference'],
        ]);
    }

    public function handlePaystackCallback(Request $request)
    {
        $reference = $request->query('reference');

        if (! $reference) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Missing transaction reference.',
            ], 400);
        }

        // Verify transaction with Paystack
        $response = Http::withToken(config('services.paystack.secret_key'))
            ->get(config('services.paystack.base_url') . '/transaction/verify/' . $reference);

        $data = $response->json();

        if (! $data['status']) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Verification failed.',
                'data'    => $data,
            ], 400);
        }

        $paymentData = $data['data'];

        // Find promotion by transaction_reference
        $promotion = Promote::where('transaction_reference', $reference)->first();

        if (! $promotion) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Promotion not found for this transaction reference.',
            ], 404);
        }

        // Mark promotion as active & approved
        $promotion->update([
            'is_active'   => true,
            'is_approved' => true,
            'approved_at' => now(),
        ]);

        return response()->json([
            'status'    => 'success',
            'message'   => 'Payment verified successfully, promotion activated.',
            'promotion' => $promotion,
        ]);
    }

    public function handlePaystackWebhook(Request $request)
    {
        $payload = $request->all();

        if (isset($payload['event']) && $payload['event'] === 'charge.success') {
            $reference = $payload['data']['reference'];

            $promotion = Promote::where('transaction_reference', $reference)->first();

            if ($promotion && ! $promotion->is_active) {
                $promotion->update([
                    'is_active'   => true,
                    'is_approved' => true,
                    'approved_at' => now(),
                ]);
            }
        }

        return response()->json(['received' => true]);
    }

    public function getCryptoPrice()
    {
        try {
            $response = Http::get('https://api.coingecko.com/api/v3/simple/price', [
                'ids'           => 'ethereum',
                'vs_currencies' => 'ngn',
            ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Unable to fetch ETH price'], 500);
            }

            $priceData     = $response->json();
            $ethPriceInNgn = $priceData['ethereum']['ngn'] ?? null;

            return response()->json([
                'success'    => true,
                'eth_to_ngn' => $ethPriceInNgn,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error fetching ETH price'], 500);
        }
    }

    public function checkActive(Request $request)
{
    $seller = $request->user();

    $existingPromotion = Promote::where('seller_id', $seller->id)
        ->where('is_active', true)
        ->where('end_date', '>', now())
        ->first();

    return response()->json([
        'has_active' => (bool) $existingPromotion,
        'message' => $existingPromotion
            ? 'Active promotion until ' . $existingPromotion->end_date->format('M d, Y')
            : null,
    ]);
}


}
