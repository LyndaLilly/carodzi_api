<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use Illuminate\Http\Request;

class PublicSellerController extends Controller
{
    // ✅ List sellers
    public function index(Request $request)
    {
        $query = Seller::query()
            ->with(['profile', 'professionalProfile'])
            ->where('profile_updated', 1);

        // --- Professional sellers ---
        if ($request->has('type') && $request->type === 'professional') {
            $query->where('is_professional', 1);

            if ($request->has('verified')) {
                if ($request->verified == '1') {
                    $query->where('status', 1);
                } elseif ($request->verified == '0') {
                    $query->where('status', 0);
                }
            }
        }
        // --- Other sellers ---
        elseif ($request->has('type') && $request->type === 'other') {
            $query->where('is_professional', 0);
        }

        $sellers = $query->get();

        foreach ($sellers as $seller) {
            $this->normalizeSeller($seller);
        }

        return response()->json([
            'status'  => 'success',
            'sellers' => $sellers,
        ]);
    }

    // ✅ Single seller profile with ACTIVE products only
    public function show($id)
    {
        $seller = Seller::with([
            'profile',
            'professionalProfile',
            'products' => function ($q) {
                $q->where('is_active', 1)->with('images');
            }
        ])
            ->where('profile_updated', 1)
            ->findOrFail($id);

        $this->normalizeSeller($seller);

        return response()->json([
            'status'  => 'success',
            'seller'  => $seller,
        ]);
    }

    // ✅ Helper to normalize file URLs and add verification status
    private function normalizeSeller($seller)
    {
        if ($seller->profile && $seller->profile->profile_image) {
            $seller->profile->profile_image = url('uploads/' . $seller->profile->profile_image);
        }
        if ($seller->professionalProfile && $seller->professionalProfile->profile_image) {
            $seller->professionalProfile->profile_image = url('uploads/' . $seller->professionalProfile->profile_image);
        }

        $seller->verification_status = $seller->is_professional
            ? ($seller->status == 1 ? 'verified' : 'unverified')
            : 'n/a';

        // Normalize product images
        if ($seller->relationLoaded('products')) {
            foreach ($seller->products as $product) {
                foreach ($product->images as $image) {
                    $image->image_path = url('uploads/' . $image->image_path);
                }
            }
        }
    }
}
