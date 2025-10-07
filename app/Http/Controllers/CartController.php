<?php
namespace App\Http\Controllers;

use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
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
     * Transform a cart item to include product details and first image
     */
    private function transformCartItem($cart)
    {
        $product = $cart->product;

        $image = $product->images->first()?->image_path;
        $image = $this->getImageUrl($image);

        $isProfessional = $product->seller ? $product->seller->is_professional : 0;

        return [
            'cart_id'         => $cart->id,
            'product_id'      => $product->id,
            'name'            => $product->name,
            'price'           => $product->price,
            'quantity'        => $cart->quantity,
            'total'           => $product->price * $cart->quantity,
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

        $cartItems = Cart::with(['product.images', 'product.seller'])
            ->where('buyer_id', $buyer->id)
            ->get();

        $data = $cartItems->map(fn($cart) => $this->transformCartItem($cart));

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

        $existing = Cart::where('buyer_id', $buyer->id)
            ->where('product_id', $validated['product_id'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Product already added to cart.',
            ], 200);
        }

        $cart = Cart::create([
            'buyer_id'   => $buyer->id,
            'product_id' => $validated['product_id'],
            'quantity'   => $validated['quantity'] ?? 1,
        ]);

        // Reload the cart item with product & images
        $cart->load(['product.images', 'product.seller']);
        $cartData = $this->transformCartItem($cart);

        return response()->json([
            'success' => true,
            'message' => 'Product added to cart successfully.',
            'data'    => $cartData,
        ]);
    }

    public function update(Request $request, $id)
    {
        $buyer     = Auth::user();
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = Cart::where('buyer_id', $buyer->id)->findOrFail($id);
        $cart->update(['quantity' => $validated['quantity']]);

        return response()->json([
            'success' => true,
            'message' => 'Cart updated successfully.',
        ]);
    }

    public function destroy($id)
    {
        $buyer = Auth::user();
        $cart  = Cart::where('buyer_id', $buyer->id)->findOrFail($id);
        $cart->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item removed from cart.',
        ]);
    }

    public function clear()
    {
        $buyer = Auth::user();
        Cart::where('buyer_id', $buyer->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cart cleared successfully.',
        ]);
    }
}
