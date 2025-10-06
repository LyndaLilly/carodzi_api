<?php
namespace App\Http\Controllers;

use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function index()
    {
        $buyer = Auth::user(); // Assuming buyer is authenticated

        if (! $buyer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // Eager load product with its images
        $cartItems = Cart::with(['product.images'])
            ->where('buyer_id', $buyer->id)
            ->get();

        // Transform data to include first image, name, price, quantity
        $data = $cartItems->map(function ($cart) {
            $product = $cart->product;

            return [
                'cart_id'    => $cart->id,
                'product_id' => $product->id,
                'name'       => $product->name,
                'price'      => $product->price,
                'quantity'   => $cart->quantity,
                'total'      => $product->price * $cart->quantity,
                'image'      => $product->images->first()?->image_path
                    ? asset('public/uploads/' . $product->images->first()->image_path)
                    : null, // fallback if no image
            ];
        });

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

        // Check if product already exists in cart
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

        return response()->json([
            'success' => true,
            'message' => 'Product added to cart successfully.',
            'data'    => $cart,
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

        $cart = Cart::where('buyer_id', $buyer->id)->findOrFail($id);
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
