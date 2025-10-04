<?php
namespace App\Http\Controllers;

use App\Models\ProductUpload;
use App\Models\ProductUploadImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ProductUploadController extends Controller
{
    public function storeProduct(Request $request)
    {
        try {
            Log::info('Product Upload Request', $request->all());

            // Fetch seller type
            $seller = \App\Models\Seller::findOrFail($request->seller_id);

            // Common rules for everyone
            $rules = [
                'seller_id'      => 'required|exists:sellers,id',
                'name'           => 'required|string|max:255',
                'category_id'    => 'required|exists:product_categories,id',
                'subcategory_id' => 'required|exists:product_subcategories,id',
                'location'       => 'required|string',
                'description'    => 'required|string',
                'images'         => 'required|array|min:1|max:3',
                'images.*'       => 'image|max:2048',
                'is_active'      => 'required|boolean',
            ];

            // Product seller (not professional)
            if ($seller->is_professional == 0) {
                $rules = array_merge($rules, [
                    'price'            => 'required|numeric',
                    'brand'            => 'nullable|string',
                    'model'            => 'nullable|string',
                    'condition'        => 'nullable|string',
                    'internal_storage' => 'nullable|string',
                    'ram'              => 'nullable|string',
                    'address'          => 'nullable|string',
                ]);
            }

            // Service provider (professional)
            if ($seller->is_professional == 1) {
                $rules = array_merge($rules, [
                    'specialization' => 'required|string',
                    'qualification'  => 'required|string',
                    'availability'   => 'required|string',
                    'rate'           => 'required|numeric', // replaces price
                ]);
            }

            // Run validation
            $validated = $request->validate($rules);

            // Save record
            $product = ProductUpload::create($validated);

            // Save images
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $subfolder = 'products';
                    $uploadDir = public_path("uploads/{$subfolder}");

                    if (! file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                    $image->move($uploadDir, $filename);

                    ProductUploadImage::create([
                        'productupload_id' => $product->id,
                        'image_path'       => "{$subfolder}/{$filename}",
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'product' => $product->load('images'),
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error uploading product/service', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getAllProducts($sellerId)
    {
        $products = ProductUpload::with('images')
            ->where('seller_id', $sellerId)
            ->get()
            ->map(function ($product) {
                $product->is_professional = $product->seller ? $product->seller->is_professional : 0;
                return $product;
            });

        return response()->json([
            'success'  => true,
            'products' => $products,
        ]);
    }

    public function getPopularProducts()
    {
        Log::info('getPopularProducts endpoint hit');

        try {
            $products = ProductUpload::with('images', 'seller')
                ->whereHas('seller', function ($q) {
                    $q->where('is_professional', 0); // normal sellers
                })
                ->latest()
                ->take(8)
                ->get();

            Log::info('Popular products fetched', ['count' => $products->count()]);

            return response()->json([
                'success'  => true,
                'products' => $products,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching popular products', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching products',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getPopularServices()
    {
        $services = ProductUpload::with(['images', 'seller.subcategory'])
            ->whereHas('seller', function ($q) {
                $q->where('is_professional', 1);
            })
            ->latest()
            ->take(8)
            ->get();

        // ✅ Compute verified status for each service
        $services->transform(function ($service) {
            $seller               = $service->seller;
            $requiresVerification = $seller && $seller->subcategory && $seller->subcategory->auto_verify == 1;
            $service->is_verified = ($seller && $seller->status == 1 && $requiresVerification);
            return $service;
        });

        return response()->json([
            'success'  => true,
            'services' => $services,
        ]);
    }

    public function getSingleProduct($id)
    {
        $product = ProductUpload::with([
            'images',
            'seller.profile',
            'seller.professionalProfile',
            'seller.subcategory', // ✅ add this line so we can check auto_verify
            'category',
        ])->findOrFail($id);

        // Determine if seller is professional
        $product->is_professional = $product->seller ? $product->seller->is_professional : 0;

        // Handle seller profile image and verification
        if ($product->seller) {
            if ($product->seller->is_professional) {
                $product->seller->profile_image = $product->seller->professionalProfile->profile_image ?? null;
            } else {
                $product->seller->profile_image = $product->seller->profile->profile_image ?? null;
            }

            // ✅ Updated Verification Logic:
            // Verified if:
            // (1) seller->status == 1  OR
            // (2) seller->subcategory->auto_verify == 1  OR
            // (3) category->auto_verify == 1
            $autoVerifySubcategory = $product->seller->subcategory
                ? $product->seller->subcategory->auto_verify == 1
                : false;

            $autoVerifyCategory = $product->category
                ? $product->category->auto_verify == 1
                : false;

            $isVerified = $product->seller->status == 1 || $autoVerifySubcategory || $autoVerifyCategory;

            // Attach verification info directly to the seller
            $product->seller->is_verified = $isVerified;
        }

        return response()->json([
            'success' => true,
            'product' => $product,
        ]);
    }

    public function getRecommended($id)
    {
        try {
            Log::info("🔎 Fetching recommended products for product ID: {$id}");

            // Try to fetch the main product
            $product = ProductUpload::findOrFail($id);
            Log::info("✅ Found product", ['id' => $product->id, 'subcategory_id' => $product->subcategory_id]);

            // Fetch recommended products from the same subcategory
            $recommended = ProductUpload::with('images', 'seller')
                ->where('subcategory_id', $product->subcategory_id)
                ->where('id', '!=', $id)
                ->inRandomOrder()
                ->take(6)
                ->get();

            Log::info("✅ Recommended products fetched", ['count' => $recommended->count()]);

            return response()->json([
                'success'     => true,
                'recommended' => $recommended,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error("❌ Product not found for recommended lookup", ['id' => $id]);
            return response()->json([
                'success' => false,
                'message' => "Product with ID {$id} not found",
            ], 404);
        } catch (\Exception $e) {
            Log::error("❌ Error fetching recommended products", [
                'id'      => $id,
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recommended products',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function updateProduct(Request $request)
    {
        try {
            $productId = $request->input('product_id');
            $product   = ProductUpload::findOrFail($productId);
            $seller    = $product->seller;

            // Validation rules
            $rules = [
                'name'           => 'sometimes|string',
                'category_id'    => 'sometimes|exists:product_categories,id',
                'subcategory_id' => 'sometimes|exists:product_subcategories,id',
                'location'       => 'sometimes|string',
                'description'    => 'sometimes|string',
                'is_active'      => 'sometimes|boolean',
                'images.*'       => 'image|max:2048',
                'removed_images' => 'sometimes|array',
            ];

            if ($seller && $seller->is_professional == 0) {
                $rules = array_merge($rules, [
                    'price'            => 'sometimes|numeric',
                    'brand'            => 'sometimes|string|nullable',
                    'model'            => 'sometimes|string|nullable',
                    'condition'        => 'sometimes|string|nullable',
                    'internal_storage' => 'sometimes|string|nullable',
                    'ram'              => 'sometimes|string|nullable',
                    'address'          => 'sometimes|string|nullable',
                ]);
            }

            if ($seller && $seller->is_professional == 1) {
                $rules = array_merge($rules, [
                    'specialization' => 'sometimes|string',
                    'qualification'  => 'sometimes|string',
                    'availability'   => 'sometimes|string',
                    'rate'           => 'sometimes|numeric',
                ]);
            }

            $validated = $request->validate($rules);

            // Apply partial updates
            $product->fill($validated);
            $product->save();

            // Handle removed images
            if ($request->filled('removed_images')) {
                $removedIds = $request->input('removed_images');
                ProductUploadImage::whereIn('id', $removedIds)->delete();
            }

            // Count existing images after removal
            $existingCount = $product->images()->count();
            $newFilesCount = $request->hasFile('images') ? count($request->file('images')) : 0;

            if ($existingCount + $newFilesCount > 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maximum 3 images allowed. Please remove some images first.',
                ], 422);
            }

            // Handle new images if uploaded

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $subfolder = 'products';
                    $uploadDir = public_path("uploads/{$subfolder}");

                    if (! file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                    $image->move($uploadDir, $filename);

                    ProductUploadImage::create([
                        'productupload_id' => $product->id,
                        'image_path'       => "{$subfolder}/{$filename}",
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'product' => $product->load('images'),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteProduct(Request $request)
    {
        $productId = $request->input('product_id');
        $product   = ProductUpload::findOrFail($productId);

        foreach ($product->images as $image) {
            $imagePath = public_path("uploads/{$image->image_path}");
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
            $image->delete();
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ]);
    }

    public function getAllProductsForBuyers()
    {
        $products = ProductUpload::with(['images', 'seller.subcategory'])->latest()->get();

        $products->transform(function ($product) {
            $seller = $product->seller;

            $requiresVerification = $seller
            && $seller->subcategory
            && $seller->subcategory->auto_verify == 1;

            // ✅ Add dynamic field
            $product->is_verified = ($seller && $seller->status == 1 && $requiresVerification);

            return $product;
        });

        return response()->json([
            'success'  => true,
            'products' => $products,
        ]);
    }

}
