<?php
namespace App\Http\Controllers;

use App\Models\Seller;
use Illuminate\Http\Request;
use Yabacon\Paystack;

class SubscriptionController extends Controller
{
    protected $paystack;

    public function __construct()
    {
        $this->paystack = new Paystack(env('PAYSTACK_SECRET_KEY'));
    }

    public function initializePayment(Request $request)
    {
        $request->validate([
            'seller_id' => 'required|exists:sellers,id',
        ]);

        $seller = Seller::findOrFail($request->seller_id);

        $reference = 'SUBS_' . time() . '_' . uniqid();

        $paymentData = [
            'amount'       => 5000000, // 50,000 NGN in kobo
            'email'        => $seller->email,
            'reference'    => $reference,
            'callback_url' => route('subscription.verify', ['seller_id' => $seller->id]),
        ];

        try {
            $tran = $this->paystack->transaction->initialize($paymentData);

            return response()->json([
                'success'           => true,
                'authorization_url' => $tran->data->authorization_url,
                'reference'         => $tran->data->reference,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment initialization failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function verifyPayment(Request $request)
    {
        $seller_id = $request->query('seller_id'); // From Paystack callback
        $reference = $request->query('reference'); // From Paystack callback

        $seller = \App\Models\Seller::findOrFail($seller_id);

        try {
            // Verify transaction with Paystack
            $tran = $this->paystack->transaction->verify([
                'reference' => $reference,
            ]);

            // Check if payment was successful
            if ($tran->data->status === 'success') {
                // Create or update yearly subscription
                $subscription = \App\Models\Subscription::updateOrCreate(
                    ['seller_id' => $seller->id],
                    [
                        'plan'       => 'yearly',
                        'starts_at'  => now(),
                        'expires_at' => now()->addYear(),
                        'is_active'  => true,
                    ]
                );

                return response()->json([
                    'success'      => true,
                    'message'      => 'Payment successful, subscription activated.',
                    'subscription' => $subscription,
                ]);
            }

            // Payment failed
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
