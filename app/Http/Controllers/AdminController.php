<?php
namespace App\Http\Controllers;

use App\Models\SellerCategory;
use App\Models\SellerSubcategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    // GET /sellers/by-subcategory/{subId}
public function getSellersBySubcategory($subId)
{
    $sellers = DB::table('sellers') // replace 'sellers' with your actual table if different
        ->where('sub_category_id', $subId)
        ->select('id', 'firstname', 'lastname') // only fetch firstname and lastname
        ->get();

    return response()->json([
        'sellers' => $sellers,
    ]);
}


}
