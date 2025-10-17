<?php
namespace App\Http\Controllers;

use App\Models\SellerCategory;
use App\Models\SellerSubcategory;
use Illuminate\Http\Request;

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
}
