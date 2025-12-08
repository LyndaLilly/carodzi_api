<?php
namespace App\Http\Controllers;

use App\Models\ProductCategory;
use App\Models\ProductSubcategory;
use App\Models\ProductUpload;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{

    public function index()
    {
        $categories = ProductCategory::with('subcategories')->get();

        return response()->json(['categories' => $categories]);
    }

    public function storeCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:product_categories,name',
        ]);

        $category = ProductCategory::create([
            'name' => $request->name,
        ]);

        return response()->json([
            'message'  => 'Product category created successfully',
            'category' => $category,
        ], 201);
    }

    public function updateCategory(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|unique:product_categories,name,' . $id,
        ]);

        $category = ProductCategory::findOrFail($id);
        $category->update(['name' => $request->name]);

        return response()->json(['message' => 'Product category updated successfully', 'category' => $category]);
    }

    public function deleteCategory($id)
    {
        $category = ProductCategory::findOrFail($id);

        // Delete related subcategories automatically due to foreign key cascade
        $category->delete();

        return response()->json(['message' => 'Product category deleted successfully']);
    }

    public function storeSubcategory(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|unique:product_subcategories,name',
            'category_id' => 'required|exists:product_categories,id',
        ]);

        $subcategory = ProductSubcategory::create([
            'name'        => $request->name,
            'category_id' => $request->category_id,
        ]);

        return response()->json([
            'message'     => 'Product subcategory created successfully',
            'subcategory' => $subcategory,
        ], 201);
    }

    public function updateSubcategory(Request $request, $id)
    {
        $request->validate([
            'name'        => 'required|string|unique:product_subcategories,name,' . $id,
            'category_id' => 'required|exists:product_categories,id',
        ]);

        $subcategory = ProductSubcategory::findOrFail($id);
        $subcategory->update([
            'name'        => $request->name,
            'category_id' => $request->category_id,
        ]);

        return response()->json(['message' => 'Product subcategory updated successfully', 'subcategory' => $subcategory]);
    }

    public function deleteSubcategory($id)
    {
        $subcategory = ProductSubcategory::findOrFail($id);
        $subcategory->delete();

        return response()->json(['message' => 'Product subcategory deleted successfully']);
    }

    public function subcategoriesByCategory($categoryId)
    {
        $subcategories = ProductSubcategory::where('category_id', $categoryId)->get();

        return response()->json(['subcategories' => $subcategories]);
    }

    public function showSubcategory($id)
    {
        $subcategory = ProductSubcategory::findOrFail($id);

        // Get products linked to this subcategory
        $products = ProductUpload::where('subcategory_id', $id)
            ->with('images') // eager load product images if you want
            ->get();

        return response()->json([
            'subcategory' => $subcategory,
            'products'    => $products,
        ]);
    }

    public function getCategoryWithSubcategoriesAndProducts($categoryId)
    {
        $category = ProductCategory::with([
            'subcategories' => function ($query) {
                $query->select('id', 'name', 'category_id');
            },
            'subcategories.products',
        ])
            ->where('id', $categoryId)
            ->first();

        if (! $category) {
            return response()->json([
                'message' => 'Category not found',
            ], 404);
        }

        return response()->json([
            'category'      => $category,
            'subcategories' => $category->subcategories,
        ]);
    }

}
