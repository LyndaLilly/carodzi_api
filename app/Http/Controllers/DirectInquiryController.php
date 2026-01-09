<?php
namespace App\Http\Controllers;

use App\Models\DirectInquiry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class DirectInquiryController extends Controller
{
    /**
     * Store a new direct inquiry
     */
    public function store(Request $request)
    {
        try {
            \Log::info('[DirectInquiryController] Direct inquiry received', $request->all());

            $validated = $request->validate([
                'seller_id'      => 'required|exists:sellers,id',
                'product_id'     => 'nullable|exists:productupload,id',
                'contact_method' => 'required|string|max:50',
                'buyer_name'     => 'nullable|string|max:255',
                'buyer_email'    => 'nullable|email|max:255',
                'message'        => 'nullable|string',
                'price'          => 'nullable|numeric|min:0',
            ]);

            $buyerId = Auth::guard('buyer')->check() ? Auth::guard('buyer')->id() : null;
            \Log::info('Buyer authenticated ID:', ['buyer_id' => $buyerId]);

            $inquiry = DirectInquiry::create([
                'seller_id'      => $validated['seller_id'],
                'buyer_id'       => $buyerId,
                'product_id'     => $validated['product_id'] ?? null,
                'contact_method' => $validated['contact_method'],
                'buyer_name'     => $validated['buyer_name'] ?? null,
                'buyer_email'    => $validated['buyer_email'] ?? null,
                'message'        => $validated['message'] ?? null,
                'price'          => $validated['price'] ?? null,
            ]);

            \Log::info('Inquiry saved successfully', ['id' => $inquiry->id]);

            $seller = $inquiry->seller;
            if ($seller) {
                // In-app notification
                $seller->notify(new \App\Notifications\NewDirectInquiryNotification($inquiry));

                // Push notification via ExpoPush
                if ($seller->expo_push_token) {
                    try {
                        $contactMethod = $inquiry->contact_method ?? 'interaction';
                        $productName   = $inquiry->product?->name ?? 'your product';

                        $message = "A buyer clicked '{$contactMethod}' for {$productName}.";

                        \App\Helpers\ExpoPush::send(
                            $seller->expo_push_token,
                            'Buyer Interest',
                            $message,
                            ['product_id' => $inquiry->product_id]

                        );
                    } catch (\Throwable $e) {
                        \Log::error('Failed to send push for inquiry', ['error' => $e->getMessage()]);
                    }
                }
            }

            \Log::info('Inquiry saved successfully', ['id' => $inquiry->id]);

            return response()->json([
                'success' => true,
                'message' => 'Your inquiry has been sent successfully!',
                'data'    => $inquiry,
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Direct inquiry error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all inquiries for a seller
     */
    public function sellerInquiries($sellerId)
    {
        $inquiries = DirectInquiry::where('seller_id', $sellerId)
            ->with('buyer', 'product')
            ->latest()
            ->get();

        return response()->json($inquiries);
    }

    /**
     * Get all inquiries made by a buyer
     */
    public function buyerInquiries($buyerId)
    {
        $inquiries = DirectInquiry::where('buyer_id', $buyerId)
            ->with('seller', 'product')
            ->latest()
            ->get();

        return response()->json($inquiries);
    }

    /**
     * Update the status of a direct inquiry (for professionals only)
     */
    public function updateStatus(Request $request, $id)
    {
        \Log::info("updateStatus called", ['id' => $id, 'request' => $request->all()]);

        try {
            // Validate status input
            $request->validate([
                'status' => 'required|in:pending,in_progress,completed,not_completed',
            ]);

            // Find the inquiry
            $inquiry = DirectInquiry::find($id);
            if (! $inquiry) {
                \Log::warning("Inquiry not found", ['id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Inquiry not found.',
                ], 404);
            }

            // Authenticate seller
            $seller = Auth::guard('seller')->user();
            if (! $seller) {
                \Log::warning("No seller authenticated");
                return response()->json([
                    'success' => false,
                    'message' => 'No seller authenticated.',
                ], 403);
            }

            // Ensure the seller owns this inquiry
            if ($seller->id !== $inquiry->seller_id) {
                \Log::warning("Seller mismatch", ['seller_id' => $seller->id, 'inquiry_seller_id' => $inquiry->seller_id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You cannot update this inquiry.',
                ], 403);
            }

            // Update status
            $inquiry->status       = $request->status;
            $inquiry->completed_at = $request->status === 'completed' ? now() : null;
            $inquiry->save();

            \Log::info("Inquiry updated successfully", ['inquiry_id' => $inquiry->id, 'status' => $inquiry->status]);

            // Send email to buyer if completed
            if ($request->status === 'completed' && $inquiry->buyer_email) {
                try {

                    Mail::send('emails.inquiry.direct_inquiry_completed', ['inquiry' => $inquiry], function ($message) use ($inquiry) {
                        $message->to($inquiry->buyer_email)
                            ->subject("Your Inquiry has been Completed by {$inquiry->seller->business_name}")
                            ->from(config('mail.from.address'), config('mail.from.name'));
                    });

                    \Log::info("Inquiry email sent to buyer", ['buyer_email' => $inquiry->buyer_email]);
                } catch (\Exception $e) {
                    \Log::error('Failed to send inquiry completion email', [
                        'error'      => $e->getMessage(),
                        'inquiry_id' => $inquiry->id,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Service status updated successfully.',
                'data'    => $inquiry,
            ]);

        } catch (\Exception $e) {
            \Log::error('Direct inquiry status update failed', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage(),
            ], 500);
        }
    }

}
