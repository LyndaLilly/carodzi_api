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
        Log::info("Fetching sellers for subcategory ID: {$subId}"); // log the incoming subcategory ID

        try {
            $sellers = DB::table('sellers')
                ->where('sub_category_id', $subId) // match your DB column name
                ->select('id', 'firstname', 'lastname')
                ->get();

            Log::info("Sellers fetched:", ['count' => $sellers->count(), 'sellers' => $sellers]);

            return response()->json([
                'sellers' => $sellers,
            ]);
        } catch (\Exception $e) {
            Log::error("Error fetching sellers for subcategory ID {$subId}: " . $e->getMessage());

            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getAllSellers()
    {
        \Log::info('Admin fetching all sellers with profiles');

        try {
            $sellers = \App\Models\Seller::with([
                'profile',             // OtherProfile
                'professionalProfile', // ProfessionalProfile
                'subcategory',
                'products.images',
            ])->get();

            // Add computed fields for profile image & business name
            $sellers->transform(function ($seller) {
                if ($seller->is_professional && $seller->professionalProfile) {
                    $seller->profile_image       = $seller->professionalProfile->profile_image ?? null;
                    $seller->business_name       = $seller->professionalProfile->business_name ?? null;
                    $seller->verification_number = $seller->professionalProfile->verification_number ?? null; // 👈 Add this
                } elseif ($seller->profile) {
                    $seller->profile_image       = $seller->profile->profile_image ?? null;
                    $seller->business_name       = $seller->profile->business_name ?? null;
                    $seller->verification_number = null; // no verification number
                } else {
                    $seller->profile_image       = null;
                    $seller->business_name       = null;
                    $seller->verification_number = null;
                }

                // Optional: attach is_verified field
                $autoVerify          = optional($seller->subcategory)->auto_verify == 1;
                $seller->is_verified = ($seller->status == 1 && $autoVerify);

                return $seller;
            });

            return response()->json([
                'success' => true,
                'sellers' => $sellers,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Error fetching sellers: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sellers',
            ], 500);
        }
    }

    public function viewSeller($sellerId)
    {
        try {
            $seller = \App\Models\Seller::with([
                'profile',             // other_profiles
                'professionalProfile', // professional_profiles
                'subcategory.category',
                'products.images',
            ])->findOrFail($sellerId);

            // Decide which profile to use
            if ($seller->is_professional && $seller->professionalProfile) {
                $profile                     = $seller->professionalProfile;
                $seller->verification_number = $profile->verification_number ?? null; // 👈 Add this
            } else {
                $profile                     = $seller->profile;
                $seller->verification_number = null;
            }

            $seller->profile_image = $profile->profile_image ?? null;
            $seller->business_name = $profile->business_name ?? null;

            // Computed verified flag
            $autoVerify          = optional($seller->subcategory)->auto_verify == 1;
            $seller->is_verified = ($seller->status == 1 && $autoVerify);

            return response()->json([
                'success' => true,
                'seller'  => $seller,
            ]);

        } catch (\Throwable $e) {
            \Log::error("Error viewing seller {$sellerId}: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Seller not found',
            ], 404);
        }
    }

    public function updateSeller(Request $request, $sellerId)
    {
        try {
            $seller = \App\Models\Seller::findOrFail($sellerId);

            // Validate basic fields (add more as needed)
            $validated = $request->validate([
                'firstname'       => 'sometimes|string|max:255',
                'lastname'        => 'sometimes|string|max:255',
                'status'          => 'sometimes|boolean',
                'is_professional' => 'sometimes|boolean',
                'profile_image'   => 'nullable|image|max:2048',
                // other seller-specific fields can be added
            ]);

            // Handle profile image update
            if ($request->hasFile('profile_image')) {

                // Determine which profile table to update
                if ($seller->is_professional && $seller->professionalProfile) {
                    $profile = $seller->professionalProfile;
                } else {
                    $profile = $seller->profile;
                }

                if ($profile && $profile->profile_image && file_exists(public_path("uploads/{$profile->profile_image}"))) {
                    unlink(public_path("uploads/{$profile->profile_image}"));
                }

                $filename = time() . '_' . uniqid() . '.webp';
                $image    = \Intervention\Image\Facades\Image::make($request->file('profile_image')->getRealPath())
                    ->orientate()
                    ->resize(1500, 1500, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    })
                    ->encode('webp', 80);

                $uploadDir = public_path('uploads/profile_images');
                if (! file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $image->save("{$uploadDir}/{$filename}");

                if ($profile) {
                    $profile->update(['profile_image' => "profile_images/{$filename}"]);
                }
            }

            $seller->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Seller updated successfully',
                'seller'  => $seller->fresh()->load(['profile', 'professionalProfile', 'subcategory']),
            ]);
        } catch (\Throwable $e) {
            \Log::error("Error updating seller: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to update seller',
            ], 500);
        }
    }

    public function deleteSeller($sellerId)
    {
        try {
            $seller = \App\Models\Seller::findOrFail($sellerId);

            // Delete profile images if exist
            $profiles = [$seller->profile, $seller->professionalProfile];
            foreach ($profiles as $profile) {
                if ($profile && $profile->profile_image && file_exists(public_path("uploads/{$profile->profile_image}"))) {
                    unlink(public_path("uploads/{$profile->profile_image}"));
                }
            }

            $seller->delete();

            return response()->json([
                'success' => true,
                'message' => 'Seller deleted successfully',
            ]);
        } catch (\Throwable $e) {
            \Log::error("Error deleting seller: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete seller',
            ], 500);
        }
    }

    public function updateSellerCategory(Request $request, $sellerId)
    {
        try {
            $request->validate([
                'category_id'     => 'required|exists:sellers_category,id',
                'sub_category_id' => 'required|exists:sellers_subcategory,id',
                'is_professional' => 'required|boolean',
            ]);

            // 🔥 Find seller by ID (admin updating any seller)
            $seller = \App\Models\Seller::findOrFail($sellerId);

            // Get subcategory
            $subCategory = \App\Models\SellerSubcategory::findOrFail($request->sub_category_id);

            // Reset status when category changes
            $seller->status = 0;

            $seller->category_id     = $request->category_id;
            $seller->sub_category_id = $request->sub_category_id;
            $seller->is_professional = $request->is_professional;

            $seller->save();

            $seller->load(['profile', 'professionalProfile', 'subcategory']);

            return response()->json([
                'success' => true,
                'message' => 'Seller category updated successfully.',
                'seller'  => $seller,
            ]);

        } catch (\Throwable $e) {
            \Log::error("Admin category update error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update seller category',
            ], 500);
        }
    }

    public function getDashboardStats()
    {
        try {
            $totalSellers  = DB::table('sellers')->count();
            $totalBuyers   = DB::table('buyers')->count();
            $totalProducts = DB::table('productupload')->count();
            $totalOrders   = DB::table('orders')->count();

            return response()->json([
                'success' => true,
                'stats'   => [
                    'total_sellers'  => $totalSellers,
                    'total_buyers'   => $totalBuyers,
                    'total_products' => $totalProducts,
                    'total_orders'   => $totalOrders,
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error("Error fetching dashboard stats: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard stats',
            ], 500);
        }
    }

    use App\Models\ProductUpload;
use Illuminate\Support\Facades\DB;

public function getAllProducts()
{
    try {
        $products = ProductUpload::with([
            'seller:id,firstname,lastname',
            'seller.profile:id,seller_id,business_name',
            'seller.professionalProfile:id,seller_id,business_name',
            'images:id,product_id,image'
        ])
        ->select('id', 'seller_id', 'name')
        ->get()
        ->map(function ($product) {

            // Get first image only
            $image = optional($product->images->first())->image;

            // Determine business name
            $seller = $product->seller;

            $businessName = null;

            if ($seller->professionalProfile) {
                $businessName = $seller->professionalProfile->business_name;
            } elseif ($seller->profile) {
                $businessName = $seller->profile->business_name;
            }

            return [
                'id'            => $product->id,
                'name'          => $product->name,
                'image'         => $image,
                'firstname'     => $seller->firstname,
                'lastname'      => $seller->lastname,
                'business_name' => $businessName,
            ];
        });

        return response()->json([
            'success'  => true,
            'products' => $products
        ]);

    } catch (\Throwable $e) {
        \Log::error("Error fetching admin products: " . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch products'
        ], 500);
    }
}


}
