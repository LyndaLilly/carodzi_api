<?php
namespace App\Http\Controllers;

use App\Models\Seller;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public function initializePayment(Request $request)
    {
        $request->validate([
            'seller_id' => 'required|exists:sellers,id',
            'plan'      => 'required|string|in:monthly,yearly',
        ]);

        $seller = Seller::findOrFail($request->seller_id);

        // Check for existing active subscription
        $existing = Subscription::where('seller_id', $seller->id)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active subscription until ' . $existing->expires_at->format('M d, Y'),
            ], 403);
        }

        $reference   = 'SUBS_' . time() . '_' . uniqid();
        $callbackUrl = route('subscription.verify', ['seller_id' => $seller->id]);
        $amount = $request->plan === 'yearly' ? 5000 * 100 : 500 * 100; 

        try {
            $response = Http::withToken(env('PAYSTACK_SECRET_KEY'))
                ->post('https://api.paystack.co/transaction/initialize', [
                    'email'        => $seller->email,
                    'amount'       => $amount,
                    'reference'    => $reference,
                    'callback_url' => $callbackUrl,
                    'metadata'     => ['seller_id' => $seller->id, 'plan' => $request->plan],
                ]);

            $data = $response->json();

            if (! $data['status']) {
                return response()->json([
                    'success' => false,
                    'message' => $data['message'] ?? 'Failed to initialize payment',
                ], 400);
            }

            // Save subscription record (inactive until payment verified)
            Subscription::updateOrCreate(
                ['seller_id' => $seller->id],
                [
                    'plan'               => $request->plan,
                    'starts_at'          => now(),
                    'expires_at'         => $request->plan === 'yearly' ? now()->addYear() : now()->addMonth(),
                    'is_active'          => false,
                    'paystack_reference' => $reference,
                ]
            );

            return response()->json([
                'success'           => true,
                'authorization_url' => $data['data']['authorization_url'],
                'reference'         => $data['data']['reference'],
            ]);
        } catch (\Exception $e) {
            Log::error('Paystack initialization error', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Payment initialization failed. Check logs.',
            ], 500);
        }
    }

    public function verifyPayment(Request $request)
    {
        $seller_id = $request->query('seller_id');
        $reference = $request->query('reference');

        $seller = Seller::findOrFail($seller_id);

        try {
            $response = Http::withToken(env('PAYSTACK_SECRET_KEY'))
                ->get("https://api.paystack.co/transaction/verify/{$reference}");

            $data = $response->json();

            if (! $data['status'] || $data['data']['status'] !== 'success') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not successful.',
                ], 400);
            }

            // Activate subscription
            $subscription = Subscription::where('seller_id', $seller->id)
                ->where('paystack_reference', $reference)
                ->first();

            if ($subscription) {
                $subscription->update([
                    'is_active'  => true,
                    'starts_at'  => now(),
                    'expires_at' => $subscription->plan === 'yearly' ? now()->addYear() : now()->addMonth(),
                ]);
            }

            return response()->json([
                'success'      => true,
                'message'      => 'Payment successful, subscription activated.',
                'subscription' => $subscription,
            ]);
        } catch (\Exception $e) {
            Log::error('Paystack verification error', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed.',
            ], 500);
        }
    }

    public function checkActive(Request $request)
    {
        $seller = $request->user();

        $active = Subscription::where('seller_id', $seller->id)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();

        return response()->json([
            'has_active' => (bool) $active,
            'plan'       => $active?->plan,
            'expires_at' => $active?->expires_at,
            'message'    => $active ? 'Active subscription until ' . $active->expires_at->format('M d, Y') : null,
        ]);
    }


    public function expireSubscriptions()
    {
        $expired = Subscription::where('is_active', true)
            ->where('expires_at', '<', now())
            ->update(['is_active' => false, 'expires_at' => now()]);

        return response()->json([
            'message' => "{$expired} subscriptions expired successfully.",
        ]);
    }
}
