<?php
namespace App\Http\Controllers;
use App\Models\ProductUpload;
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
        \Log::info('📩 Incoming Review Request:', $request->all());

        // Try to find the product/service
        $upload = ProductUpload::find($request->input('productupload_id'));

        if (!$upload) {
            \Log::error('❌ ProductUpload not found for ID: ' . $request->input('productupload_id'));
            return response()->json([
                'success' => false,
                'message' => 'Product or Service not found',
            ], 404);
        }

        \Log::info('✅ Found Upload:', [
            'id' => $upload->id,
            'is_professional' => $upload->is_professional,
        ]);

        // Determine if this is a service
        $isService = $upload->is_professional == 1;

        // Validation rules
        $rules = [
            'productupload_id' => 'required|exists:productupload,id', // ✅ corrected table name
            'rating'           => 'required|integer|min:1|max:5',
            'review'           => 'nullable|string',
        ];

        if (! $isService) {
            $rules['order_id'] = 'required|exists:orders,id';
        } else {
            $rules['order_id'] = 'nullable';
        }

        $validated = $request->validate($rules);

        $buyerId = Auth::id();
        \Log::info('👤 Authenticated Buyer ID:', ['buyer_id' => $buyerId]);

        if (!$buyerId) {
            \Log::error('❌ No authenticated buyer found');
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: No buyer ID found',
            ], 401);
        }

        // Create review
        $review = ProductReview::create([
            'productupload_id' => $validated['productupload_id'],
            'order_id'         => $validated['order_id'] ?? null,
            'buyer_id'         => $buyerId,
            'rating'           => $validated['rating'],
            'review'           => $validated['review'] ?? null,
            'is_approved'      => true,
            'is_visible'       => true,
        ]);

        \Log::info('✅ Review Created Successfully:', $review->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Review added successfully!',
            'review'  => $review,
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        \Log::warning('⚠️ Validation failed:', $e->errors());
        return response()->json([
            'success' => false,
            'errors'  => $e->errors(),
        ], 422);

    } catch (\Exception $e) {
        \Log::error('💥 Unexpected error in ReviewController@store:', [
            'message' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Internal Server Error: ' . $e->getMessage(),
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
