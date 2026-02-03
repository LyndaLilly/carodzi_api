<?php
namespace App\Http\Controllers;

use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    /**
     * Initialize Paystack Payment
     */
    public function initializePayment(Request $request)
    {
        $request->validate([
            'seller_id' => 'required|exists:sellers,id',
        ]);

        $seller      = Seller::findOrFail($request->seller_id);
        $reference   = 'SUBS_' . time() . '_' . uniqid();
        $callbackUrl = route('subscription.verify', ['seller_id' => $seller->id]);

        try {
            $response = Http::withToken(env('PAYSTACK_SECRET_KEY'))
                ->post('https://api.paystack.co/transaction/initialize', [
                    'email'        => $seller->email,
                    'amount'       => 5000, // ₦50 in kobo
                    'reference'    => $reference,
                    'callback_url' => $callbackUrl,
                ]);

            $data = $response->json();

            if ($data['status']) {
                // Save reference in DB before payment
                \App\Models\Subscription::updateOrCreate(
                    ['seller_id' => $seller->id],
                    [
                        'plan'               => 'yearly',
                        'starts_at'          => now(),
                        'expires_at'         => now()->addYear(),
                        'is_active'          => false, // not active until verified
                        'paystack_reference' => $reference,
                    ]
                );

                return response()->json([
                    'success'           => true,
                    'authorization_url' => $data['data']['authorization_url'],
                    'reference'         => $data['data']['reference'],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $data['message'] ?? 'Failed to initialize payment',
            ], 400);
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

            if ($data['status'] && $data['data']['status'] === 'success') {
                // Activate subscription
                $subscription = \App\Models\Subscription::where('seller_id', $seller->id)
                    ->where('paystack_reference', $reference)
                    ->first();

                if ($subscription) {
                    $subscription->update([
                        'is_active'  => true,
                        'starts_at'  => now(),
                        'expires_at' => now()->addYear(),
                    ]);
                }

                return response()->json([
                    'success'      => true,
                    'message'      => 'Payment successful, subscription activated.',
                    'subscription' => $subscription,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Payment not successful.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed: ' . $e->getMessage(),
            ], 500);
        }
    }

}
