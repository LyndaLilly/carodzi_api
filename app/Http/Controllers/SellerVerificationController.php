<?php
namespace App\Http\Controllers;

use App\Models\SellerVerificationPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SellerVerificationController extends Controller
{
    // 🔹 Initiate a verification payment
    public function initiatePayment(Request $request)
    {
        try {
            $seller = $request->user();

            $amount    = 5000 * 100;
            $reference = 'ALEBAZ-SVP-' . Str::upper(Str::random(12));

            // Save pending payment record
            $payment = SellerVerificationPayment::create([
                'seller_id' => $seller->id,
                'reference' => $reference,
                'amount'    => 5000,
                'status'    => 'pending',
            ]);

            // Call Paystack
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('PAYSTACK_SECRET_KEY'),
                'Content-Type'  => 'application/json',
            ])->post(env('PAYSTACK_BASE_URL') . '/transaction/initialize', [
                'email'        => $seller->email,
                'amount'       => $amount,
                'reference'    => $reference,
                'currency'     => 'NGN',
                'callback_url' => route('seller.verification.callback'),
                'metadata'     => [
                    'seller_id'  => $seller->id,
                    'payment_id' => $payment->id,
                ],
            ]);

            $result = $response->json();

            if (! $result) {
                return response()->json(['message' => 'No response from Paystack'], 500);
            }

            if ($result['status'] === true) {
                return response()->json([
                    'authorization_url' => $result['data']['authorization_url'],
                    'reference'         => $reference,
                    'success'           => true,
                ]);
            }

            return response()->json([
                'message' => $result['message'] ?? 'Unable to initiate payment',
            ], 500);

        } catch (\Exception $e) {
            // Log error for debugging
            \Log::error('Seller verification payment error: ' . $e->getMessage(), [
                'stack' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    // 🔹 Paystack callback
    public function handleCallback(Request $request)
    {
        $reference = $request->query('reference');

        if (! $reference) {
            return redirect()->route('seller.dashboard')
                ->with('error', 'Invalid payment reference');
        }

        // Verify transaction with Paystack
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('PAYSTACK_SECRET_KEY'),
        ])->get(env('PAYSTACK_BASE_URL') . "/transaction/verify/{$reference}");

        $result = $response->json();

        if ($result['status'] === true && $result['data']['status'] === 'success') {
            // Update payment record
            $payment = SellerVerificationPayment::where('reference', $reference)->firstOrFail();

            $payment->update([
                'status'     => 'success',
                'paid_at'    => now(),
                'starts_at'  => now(),
                'ends_at'    => now()->addYear(), // active for 1 year
                'expires_at' => now()->addYear(),
            ]);

            // Update seller verified status
            $seller           = $payment->seller;
            $seller->verified = true;
            $seller->save();

            return redirect()->route('seller.dashboard')
                ->with('success', 'Verification payment successful!');
        }

        return redirect()->route('seller.dashboard')
            ->with('error', 'Payment failed or not verified');
    }
}
