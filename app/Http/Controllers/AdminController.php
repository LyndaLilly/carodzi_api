<?php
namespace App\Http\Controllers;

use App\Models\SellerCategory;
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
        ]);

        $subcategory = \DB::table('sellers_subcategory')->insertGetId([
            'name'        => $request->name,
            'category_id' => $request->category_id,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return response()->json([
            'message'        => 'Seller subcategory created successfully',
            'subcategory_id' => $subcategory,
        ], 201);
    }

    public function getSellerCategories()
    {
        $categories = SellerCategory::with('subcategories')->get();

        return response()->json([
            'categories' => $categories,
        ]);
    }

    public function getSubcategoriesByCategory($categoryId)
    {
        $subcategories = \DB::table('sellers_subcategory')
            ->where('category_id', $categoryId)
            ->get();

        return response()->json([
            'subcategories' => $subcategories,
        ]);
    }
}
