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
        ])->where('profile_updated', 1);

        // Filter professional sellers
        if ($request->type === 'professional') {
            $query->where('is_professional', 1);

            if ($request->has('verified')) {
                $query->where('status', $request->verified == '1' ? 1 : 0);
            }
        }
        // Filter normal sellers
        elseif ($request->type === 'other') {
            $query->where('is_professional', 0);
        }

        $sellers = $query->get()->map(function ($seller) {
            // Add a uniform profile_image field for frontend
            if ($seller->is_professional) {
                $seller->profile_image = $seller->professionalProfile->profile_image ?? null;
            } else {
                $seller->profile_image = $seller->profile->profile_image ?? null;
            }

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
        $seller = Seller::with(['profile', 'professionalProfile', 'products.images'])
            ->where('profile_updated', 1)
            ->findOrFail($id);

        // Uniform profile_image field
        if ($seller->is_professional) {
            $seller->profile_image = $seller->professionalProfile->profile_image ?? null;
        } else {
            $seller->profile_image = $seller->profile->profile_image ?? null;
        }

        return response()->json([
            'success' => true,
            'seller'  => $seller,
        ]);
    }
}
