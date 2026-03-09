<?php
namespace App\Http\Controllers;

use App\Models\ChatIntent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatIntentController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'seller_id'  => ['required', 'integer'],
            'qty'        => ['nullable', 'integer', 'min:1'],
            'message'    => ['nullable', 'string'],
        ]);

        $token = Str::random(40);

        $intent = ChatIntent::create([
            'token'      => $token,
            'product_id' => $data['product_id'],
            'seller_id'  => $data['seller_id'],
            'qty'        => $data['qty'] ?? 1,
            'message'    => $data['message'] ?? null,
            'expires_at' => now()->addMinutes(10),
        ]);

        return response()->json([
            'success'    => true,
            'intent'     => $intent->token,
            'expires_at' => $intent->expires_at,
        ]);
    }

    public function resolve(Request $request)
    {
        $data = $request->validate([
            'intent' => ['required', 'string'],
        ]);

        $intent = ChatIntent::where('token', $data['intent'])->first();

        if (! $intent) {
            return response()->json([
                'success' => false,
                'message' => 'Intent not found',
            ], 404);
        }

        if ($intent->used_at) {
            return response()->json([
                'success' => false,
                'message' => 'Intent already used',
            ], 409);
        }

        if (now()->greaterThan($intent->expires_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Intent expired',
            ], 410);
        }

        // mark used (so it cannot be reused)
        $intent->used_at = now();
        $intent->save();

        return response()->json([
            'success' => true,
            'data'    => [
                'product_id' => $intent->product_id,
                'seller_id'  => $intent->seller_id,
                'qty'        => $intent->qty,
                'message'    => $intent->message,
            ],
        ]);
    }
}
