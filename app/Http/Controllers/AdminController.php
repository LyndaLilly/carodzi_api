<?php
namespace App\Http\Controllers;

use App\Models\SellerCategory;
use App\Models\SellerSubcategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; 

class AdminController extends Controller
{
    public function createSellerCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:sellers_category,name',
        ]);

        $category = SellerCategory::create([
            'name' => $request->name,
        ]);

        return response()->json([
            'message'  => 'Seller category created successfully',
            'category' => $category,
        ], 201);
    }

    public function createSellerSubcategory(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|unique:sellers_subcategory,name',
            'category_id' => 'required|exists:sellers_category,id',
            'auto_verify' => 'nullable|boolean',
        ]);

        $subcategory = SellerSubcategory::create([
            'name'        => $request->name,
            'category_id' => $request->category_id,
            'auto_verify' => $request->auto_verify ?? 0, // default 0
        ]);

        return response()->json([
            'message'     => 'Seller subcategory created successfully',
            'subcategory' => $subcategory,
        ], 201);
    }

    public function getSellerCategories()
    {
        // eager load subcategories including auto_verify flag
        $categories = SellerCategory::with('subcategories')->get();

        return response()->json([
            'categories' => $categories,
        ]);
    }

    public function getSubcategoriesByCategory($categoryId)
    {
        $subcategories = DB::table('sellers_subcategory')
            ->where('category_id', $categoryId)
            ->select('id', 'name', 'auto_verify') // 👈 include auto_verify
            ->get();

        return response()->json([
            'subcategories' => $subcategories,
        ]);
    }

    public function getAllSubcategories()
    {
        $subcategories = DB::table('sellers_subcategory as s')
            ->join('sellers_category as c', 's.category_id', '=', 'c.id')
            ->select('s.id', 's.name', 's.auto_verify', 's.category_id', 'c.name as category_name')
            ->get();

        return response()->json([
            'subcategories' => $subcategories,
        ]);
    }



public function getSellersBySubcategory($subId)
{
    Log::info("Fetching sellers for subcategory ID: {$subId}");

    try {
        $sellers = Seller::with(['profile', 'professionalProfile'])
            ->where('sub_category_id', $subId)
            ->select('id', 'firstname', 'lastname', 'is_professional')
            ->get();

        // Combine profile images
        $sellers = $sellers->map(function($seller) {
            $seller->profile_image = $seller->is_professional
                ? optional($seller->professionalProfile)->profile_image
                : optional($seller->profile)->profile_image;

            unset($seller->profile);
            unset($seller->professionalProfile);

            return $seller;
        });

        Log::info("Sellers fetched:", ['count' => $sellers->count()]);

        return response()->json([
            'sellers' => $sellers
        ]);
    } catch (\Exception $e) {
        Log::error("Error fetching sellers for subcategory ID {$subId}: " . $e->getMessage());

        return response()->json([
            'error' => $e->getMessage()
        ], 500);
    }
}




}
