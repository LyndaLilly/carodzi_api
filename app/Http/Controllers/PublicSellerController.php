<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use Illuminate\Http\Request;

class PublicSellerController extends Controller
{
    // List sellers with active products
    public function index(Request $request)
    {
        $query = Seller::with([
            'profile',
            'professionalProfile',
            'products.images'
        ])->where('profile_updated', 1);

        // Professional sellers filter
        if ($request->type === 'professional') {
            $query->where('is_professional', 1);

            if ($request->has('verified')) {
                $query->where('status', $request->verified == '1' ? 1 : 0);
            }
        }
        // Other sellers filter
        elseif ($request->type === 'other') {
            $query->where('is_professional', 0);
        }

        $sellers = $query->get();

        return response()->json([
            'success' => true,
            'sellers' => $sellers,
        ]);
    }

    // Single seller with active products
    public function show($id)
    {
        $seller = Seller::with([
            'profile',
            'professionalProfile',
            'products.images'
        ])
        ->where('profile_updated', 1)
        ->findOrFail($id);

        return response()->json([
            'success' => true,
            'seller' => $seller,
        ]);
    }

}
