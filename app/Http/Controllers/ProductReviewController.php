<?php

namespace App\Http\Controllers;

use App\Models\ProductReview;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ProductReviewController extends Controller
{
    // 🟢 Create review
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'productupload_id' => 'required|exists:productupload,id',
                'order_id'         => 'required|exists:orders,id|unique:product_reviews,order_id',
                'rating'           => 'required|integer|min:1|max:5',
                'review'           => 'nullable|string',
            ]);

            // Get buyer ID — from auth or payload
            $buyerId = Auth::id() ?? $request->input('buyer_id');

            $review = ProductReview::create([
                'productupload_id' => $validated['productupload_id'],
                'buyer_id'         => $buyerId,
                'order_id'         => $validated['order_id'],
                'rating'           => $validated['rating'],
                'review'           => $validated['review'] ?? null,
                'is_approved'      => true,
                'is_visible'       => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Review added successfully!',
                'review'  => $review,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('❌ Review creation failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create review',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // 🟣 Get reviews for a product
    public function getProductReviews($productId)
    {
        $reviews = ProductReview::with('buyer')
            ->where('productupload_id', $productId)
            ->where('is_visible', true)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'reviews' => $reviews,
        ]);
    }

    // 🟡 Average rating for a product
    public function getAverageRating($productId)
    {
        $average = ProductReview::where('productupload_id', $productId)
            ->where('is_visible', true)
            ->avg('rating');

        return response()->json([
            'success' => true,
            'average_rating' => round($average ?? 0, 1),
        ]);
    }
}
