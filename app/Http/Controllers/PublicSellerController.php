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
            'products.images',
            'subcategory'
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

        // Attach computed is_verified field
        $sellers->transform(function ($seller) {
            $autoVerify = $seller->subcategory && $seller->subcategory->auto_verify == 1;
            // if subcategory allows auto_verify, treat seller as verified if status == 1
            $seller->is_verified = ($seller->status == 1 && $autoVerify);
            return $seller;
        });

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
            'products.images',
            'subcategory'
        ])
            ->where('profile_updated', 1)
            ->findOrFail($id);

        // Attach computed is_verified field
        $autoVerify = $seller->subcategory && $seller->subcategory->auto_verify == 1;
        $seller->is_verified = ($seller->status == 1 && $autoVerify);

        return response()->json([
            'success' => true,
            'seller'  => $seller,
        ]);
    }

    // Fetch all sellers for homepage
    public function homepageSellers()
    {
        $sellers = Seller::with([
            'profile',
            'professionalProfile',
            'products.images',
            'subcategory'
        ])
            ->where('profile_updated', 1)
            ->get();

        // Attach computed is_verified field
        $sellers->transform(function ($seller) {
            $autoVerify = $seller->subcategory && $seller->subcategory->auto_verify == 1;
            $seller->is_verified = ($seller->status == 1 && $autoVerify);
            return $seller;
        });

        return response()->json([
            'success' => true,
            'sellers' => $sellers,
        ]);
    }
}
