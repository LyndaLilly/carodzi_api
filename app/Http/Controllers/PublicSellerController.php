<?php
namespace App\Http\Controllers;

use App\Models\Seller;
use Illuminate\Http\Request;

class PublicSellerController extends Controller
{
   public function index(Request $request)
{
    \Log::info('📥 Incoming request to PublicSellerController@index', [
        'sub_category_id' => $request->sub_category_id,
        'type' => $request->type,
        'verified' => $request->verified,
    ]);

    try {
        $query = Seller::with([
            'profile',
            'professionalProfile',
            'products.images',
            'subcategory',
        ])->where('profile_updated', 1);

        if ($request->has('sub_category_id')) {
            $query->where('sub_category_id', $request->sub_category_id);
        }

        if ($request->type === 'professional') {
            $query->where('is_professional', 1);

            if ($request->has('verified')) {
                $query->where('status', $request->verified == '1' ? 1 : 0);
            }
        } elseif ($request->type === 'other') {
            $query->where('is_professional', 0);
        }

        $sellers = $query->get();
        \Log::info('✅ Sellers fetched', ['count' => $sellers->count()]);

        $sellers->transform(function ($seller) {
            $autoVerify          = optional($seller->subcategory)->auto_verify == 1;
            $seller->is_verified = ($seller->status == 1 && $autoVerify);
            return $seller;
        });

        return response()->json([
            'success' => true,
            'sellers' => $sellers,
        ]);
    } catch (\Throwable $e) {
        \Log::error('❌ Error in PublicSellerController@index', [
            'message' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'An error occurred',
        ], 500);
    }
}


    // Single seller with active products
    public function show($id)
    {
        $seller = Seller::with([
            'profile',
            'professionalProfile',
            'products.images',
            'subcategory',
        ])
            ->where('profile_updated', 1)
            ->findOrFail($id);

        // Attach computed is_verified field
        $autoVerify          = $seller->subcategory && $seller->subcategory->auto_verify == 1;
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
            'subcategory',
        ])
            ->where('profile_updated', 1)
            ->get();

        // Attach computed is_verified field
        $sellers->transform(function ($seller) {
            $autoVerify          = $seller->subcategory && $seller->subcategory->auto_verify == 1;
            $seller->is_verified = ($autoVerify && $seller->status == 1);
            return $seller;
        });

        return response()->json([
            'success' => true,
            'sellers' => $sellers,
        ]);
    }
}
