<?php
namespace App\Http\Controllers;

use App\Models\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProductReviewController extends Controller
{
    // 🟢 Create review (product + service)
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'productupload_id' => 'required|exists:productupload,id',
                'order_id'         => 'required|exists:orders,id',
                'rating'           => 'required|integer|min:1|max:5', // Product rating
                'service_rating'   => 'nullable|integer|min:1|max:5', // Service rating (optional)
                'review'           => 'nullable|string',
            ]);

            $buyerId = Auth::id() ?? $request->input('buyer_id');

            // Check if buyer already reviewed this product
            $existingReview = ProductReview::where('buyer_id', $buyerId)
                ->where('productupload_id', $validated['productupload_id'])
                ->first();

            if ($existingReview) {
                // 🟡 Update existing review
                $existingReview->update([
                    'rating'         => $validated['rating'],
                    'service_rating' => $validated['service_rating'] ?? $existingReview->service_rating,
                    'review'         => $validated['review'] ?? $existingReview->review,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Review updated successfully!',
                    'review'  => $existingReview,
                ], 200);
            }

            // 🟢 Create new review
            $review = ProductReview::create([
                'productupload_id' => $validated['productupload_id'],
                'buyer_id'         => $buyerId,
                'order_id'         => $validated['order_id'],
                'rating'           => $validated['rating'],
                'service_rating'   => $validated['service_rating'] ?? null,
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
                'message' => 'Failed to create/update review',
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
            'success'        => true,
            'average_rating' => round($average ?? 0, 1),
        ]);
    }

    // 🟠 Average rating for a seller (based on all product reviews)
    public function getSellerAverageRating($sellerId)
    {
        try {
            $average = ProductReview::whereHas('product', function ($query) use ($sellerId) {
                $query->where('seller_id', $sellerId); // ✅ correct column
            })
                ->where('is_visible', true)
                ->avg('rating');

            return response()->json([
                'success'        => true,
                'average_rating' => round($average ?? 0, 1),
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Failed to fetch seller average rating', [
                'error'     => $e->getMessage(),
                'seller_id' => $sellerId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate seller rating',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // 🟣 Get average service rating for a seller
    public function getSellerServiceAverage($sellerId)
    {
        try {
            $averageService = ProductReview::whereHas('product', function ($query) use ($sellerId) {
                $query->where('seller_id', $sellerId); // ✅ make sure your productupload table has seller_id
            })
                ->whereNotNull('service_rating')
                ->where('is_visible', true)
                ->avg('service_rating');

            return response()->json([
                'success'                => true,
                'average_service_rating' => round($averageService ?? 0, 1),
            ], 200);

        } catch (\Exception $e) {
            \Log::error('❌ Failed to fetch seller service average rating', [
                'error'     => $e->getMessage(),
                'seller_id' => $sellerId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate service rating',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

}
