<?php
namespace App\Http\Controllers;

use App\Models\Seller;
use App\Models\SellerVerificationPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SellerVerificationController extends Controller
{
    public function initiatePayment(Request $request)
    {
        $request->validate([
            'seller_id' => 'required|exists:sellers,id',
        ]);

        $seller    = Seller::find($request->seller_id);
        $amount    = 5000 * 100;
        $reference = 'ALEBAZ-SVP-' . Str::upper(Str::random(12));

        // Call Paystack
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('PAYSTACK_SECRET_KEY'),
            'Content-Type'  => 'application/json',
        ])->post(env('PAYSTACK_BASE_URL') . '/transaction/initialize', [
            'email'     => $seller->email,
            'amount'    => $amount,
            'reference' => $reference,
            'currency'  => 'NGN',
            'metadata'  => [
                'seller_id' => $seller->id,
            ],
        ]);

        $result = $response->json();

        if ($result['status'] === true) {
            return response()->json([
                'authorization_url' => $result['data']['authorization_url'],
                'reference'         => $reference,
            ]);
        }

        return response()->json(['message' => 'Unable to initiate payment'], 500);
    }

    public function verifyPayment(Request $request)
    {

        try {
            $request->validate([
                'reference' => 'required|string',
            ]);

            $reference = $request->reference;

            // Verify payment with Paystack
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('PAYSTACK_SECRET_KEY'),
            ])->get(env('PAYSTACK_BASE_URL') . "/transaction/verify/{$reference}");

            $result = $response->json();

            Log::info('PAYSTACK RESPONSE RAW', [
                'status' => $response->status(),
                'body'   => $result,
            ]);

            // Only save if payment was successful
            if (
                isset($result['status']) &&
                $result['status'] === true &&
                $result['data']['status'] === 'success'
            ) {
                Log::info('PAYMENT VERIFIED SUCCESSFULLY');

                // Save payment record now
                $payment = SellerVerificationPayment::create([
                    'seller_id'  => $result['data']['metadata']['seller_id'],
                    'reference'  => $result['data']['reference'],
                    'amount'     => $result['data']['amount'] / 100, // convert kobo to Naira
                    'status'     => 'success',
                    'paid_at'    => now(),
                    'starts_at'  => now(),
                    'ends_at'    => now()->addYear(),
                    'expires_at' => now()->addYear(),
                ]);

                $seller = $payment->seller;

                if ($seller) {
                    $seller->verified = true;
                    $seller->save();
                    Log::info('SELLER VERIFIED', [
                        'seller_id' => $seller->id,
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Seller verified successfully',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Payment was not successful',
            ], 400);

        } catch (\Throwable $e) {
            Log::critical('VERIFY PAYMENT CRASHED', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error during verification',
            ], 500);
        }
    }
}
