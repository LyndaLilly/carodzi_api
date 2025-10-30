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
            \Log::info('Direct inquiry received', $request->all());

            $validated = $request->validate([
                'seller_id'      => 'required|exists:sellers,id',
                'product_id'     => 'nullable|exists:product_uploads,id',
                'contact_method' => 'required|string|max:50',
                'buyer_name'     => 'nullable|string|max:255',
                'buyer_email'    => 'nullable|email|max:255',
                'message'        => 'nullable|string',
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
}
