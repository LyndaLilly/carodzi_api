<?php
namespace App\Http\Controllers;

use App\Models\Wish;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WishController extends Controller
{
    /**
     * Helper to get full image URL, same as ProductUploadController
     */
    private function getImageUrl($path)
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return asset('public/uploads/' . $path); // ✅ include public in URL
    }

    /**
     * Transform a Wish item to include product details and first image
     */
    private function transformWishItem($wish)
    {
        $product = $wish->product;

        $image = $product->images->first()?->image_path;
        $image = $this->getImageUrl($image);

        $isProfessional = $product->seller ? $product->seller->is_professional : 0;

        return [
            'wish_id'         => $wish->id,
            'product_id'      => $product->id,
            'name'            => $product->name,
            'price'           => $product->price,
            'quantity'        => $wish->quantity,
            'total'           => $product->price * $wish->quantity,
            'image'           => $image,
            'is_professional' => $isProfessional,
        ];
    }

    public function index()
    {
        $buyer = Auth::user();
        if (! $buyer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $wishItems = Wish::with(['product.images', 'product.seller'])
            ->where('buyer_id', $buyer->id)
            ->get();

        $data = $wishItems->map(fn($wish) => $this->transformWishItem($wish));

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    public function store(Request $request)
    {
        $buyer = Auth::user();

        $validated = $request->validate([
            'product_id' => 'required|exists:productupload,id',
            'quantity'   => 'nullable|integer|min:1',
        ]);

        $existing = Wish::where('buyer_id', $buyer->id)
            ->where('product_id', $validated['product_id'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Product already added to wish.',
            ], 200);
        }

        $wish = Wish::create([
            'buyer_id'   => $buyer->id,
            'product_id' => $validated['product_id'],
            'quantity'   => $validated['quantity'] ?? 1,
        ]);

        // Reload the wish item with product & images
        $wish->load(['product.images', 'product.seller']);
        $wishData = $this->transformWishItem($wish);

        return response()->json([
            'success' => true,
            'message' => 'Product added to wish successfully.',
            'data'    => $wishData,
        ]);
    }

    public function update(Request $request, $id)
    {
        $buyer     = Auth::user();
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $wish = Wish::where('buyer_id', $buyer->id)->findOrFail($id);
        $wish->update(['quantity' => $validated['quantity']]);

        return response()->json([
            'success' => true,
            'message' => 'Wish updated successfully.',
        ]);
    }

    public function destroy($id)
    {
        $buyer = Auth::user();
        $wish  = Wish::where('buyer_id', $buyer->id)->findOrFail($id);
        $wish->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item removed from wish.',
        ]);
    }

    public function clear()
    {
        $buyer = Auth::user();
        Wish::where('buyer_id', $buyer->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Wish cleared successfully.',
        ]);
    }
}
