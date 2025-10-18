<?php
namespace App\Http\Controllers;

use App\Models\Promote;
use Illuminate\Http\Request;

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

        // Fetch plan details from config
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

    /**
     * Fetch featured sellers
     * GET /api/featured-sellers
     */
    public function featured()
    {
        $featuredSellers = Promote::where('is_active', true)
            ->where('is_approved', true)
            ->where('end_date', '>=', now())
            ->with(['seller.profile'])
            ->get()
            ->map(function ($promo) {
                $seller = $promo->seller;
                return [
                    'id'             => $seller->id,
                    'business_name'  => $seller->profile->business_name ?? ($seller->firstname . ' ' . $seller->lastname),
                    'logo'           => $seller->profile->profile_image ?? null,
                    'tagline'        => $seller->profile->tagline ?? null,
                    'is_verified'    => $seller->verified,
                    'average_rating' => $seller->profile->average_rating ?? 0,
                ];
            });

        return response()->json([
            'success' => true,
            'sellers' => $featuredSellers,
        ]);
    }

    /**
     * Optional: expire promotions (can be scheduled)
     */
    public function expirePromotions()
    {
        $expired = Promote::where('is_active', true)
            ->where('end_date', '<', now())
            ->update(['is_active' => false, 'expired_at' => now()]);

        return response()->json([
            'message' => "{$expired} promotions expired successfully.",
        ]);
    }
}
