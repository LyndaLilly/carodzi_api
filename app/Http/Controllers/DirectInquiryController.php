<?php
namespace App\Http\Controllers;

use App\Models\DirectInquiry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            $request->validate([
                'status' => 'required|in:pending,in_progress,completed,not_completed',
            ]);

            $inquiry = DirectInquiry::find($id);

            if (! $inquiry) {
                \Log::warning("Inquiry not found", ['id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Inquiry not found.',
                ], 404);
            }

            // Seller
            $seller = Auth::guard('seller')->user();
            if (! $seller) {
                \Log::warning("No seller authenticated");
                return response()->json([
                    'success' => false,
                    'message' => 'No seller authenticated.',
                ], 403);
            }

            if ($seller->id !== $inquiry->seller_id) {
                \Log::warning("Seller mismatch", ['seller_id' => $seller->id, 'inquiry_seller_id' => $inquiry->seller_id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You cannot update this inquiry.',
                ], 403);
            }

            if ((int) $seller->is_professional !== 1) {
                \Log::info("Seller is not professional", ['seller_id' => $seller->id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Only professional sellers can update inquiry status.',
                ], 403);
            }

            // Update status
            $inquiry->status       = $request->status;
            $inquiry->completed_at = $request->status === 'completed' ? now() : null;
            $inquiry->save();

            \Log::info("Inquiry updated successfully", ['inquiry_id' => $inquiry->id, 'status' => $inquiry->status]);

            // Notify buyer
            if ($request->status === 'completed' && $inquiry->buyer) {
                try {
                    $inquiry->buyer->notify(new DirectInquiryCompleted($inquiry));
                    \Log::info("Buyer notified", ['buyer_id' => $inquiry->buyer->id]);
                } catch (\Exception $e) {
                    \Log::error('Failed to send inquiry completion notification', [
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
