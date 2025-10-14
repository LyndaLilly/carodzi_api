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

            $seller = \App\Models\Seller::findOrFail($request->seller_id);

            $rules = [
                'seller_id'      => 'required|exists:sellers,id',
                'name'           => 'required|string|max:255',
                'category_id'    => 'required|exists:product_categories,id',
                'subcategory_id' => 'required|exists:product_subcategories,id',
                'location'       => 'required|string',
                'description'    => 'required|string',
                'currency'       => 'required|string|max:10',
                'images'         => 'required|array|min:1|max:3',
                'images.*'       => 'image|max:2048',
                'is_active'      => 'required|boolean',
            ];

            if ($seller->is_professional == 0) {
                $rules = array_merge($rules, [
                    'price'            => 'required|numeric',
                    'brand'            => 'nullable|string',
                    'model'            => 'nullable|string',
                    'condition'        => 'nullable|string',
                    'internal_storage' => 'nullable|string',
                    'ram'              => 'nullable|string',
                    'address'          => 'required|string',
                ]);
            }

            if ($seller->is_professional == 1) {
                $rules = array_merge($rules, [
                    'specialization' => 'nullable|string',
                    'availability'   => 'required|string',
                    'rate'           => 'required|string|max:50', // ✅ changed to string
                ]);
            }

            $validated = $request->validate($rules);

            $product = ProductUpload::create($validated);

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

    public function updateProduct(Request $request)
    {
        try {
            $productId = $request->input('product_id');
            $product   = ProductUpload::findOrFail($productId);
            $seller    = $product->seller;

            $rules = [
                'name'           => 'sometimes|string|max:255',
                'category_id'    => 'sometimes|exists:product_categories,id',
                'subcategory_id' => 'sometimes|exists:product_subcategories,id',
                'location'       => 'sometimes|string',
                'description'    => 'sometimes|string',
                'currency'       => 'sometimes|string|max:10',
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
                    'specialization' => 'sometimes|string|nullable',
                    'availability'   => 'sometimes|string',
                    'rate'           => 'sometimes|string|max:50', // ✅ updated from numeric
                ]);
            }

            $validated = $request->validate($rules);

            $product->fill($validated);
            $product->save();

            // 🗑 Handle image removals
            if ($request->filled('removed_images')) {
                $removedIds = $request->input('removed_images');
                ProductUploadImage::whereIn('id', $removedIds)->delete();
            }

            // ✅ Check total image count
            $existingCount = $product->images()->count();
            $newFilesCount = $request->hasFile('images') ? count($request->file('images')) : 0;

            if ($existingCount + $newFilesCount > 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maximum 3 images allowed. Please remove some images first.',
                ], 422);
            }

            // 🖼 Add new images
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
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
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
            'seller.subcategory',
            'category',
            'reviews.buyer', // 👈 include reviews + buyer info
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

            $autoVerifySubcategory = $product->seller->subcategory
                ? $product->seller->subcategory->auto_verify == 1
                : false;

            $autoVerifyCategory = $product->category
                ? $product->category->auto_verify == 1
                : false;

            $isVerified = $product->seller->status == 1 || $autoVerifySubcategory || $autoVerifyCategory;

            $product->seller->is_verified = $isVerified;
        }

        // ✅ Add computed fields
        $product->average_rating = $product->reviews->avg('rating');
        $product->review_count   = $product->reviews->count();

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

    public function search(Request $request)
    {
        $query = $request->input('query');

        if (! $query) {
            return response()->json([
                'success' => false,
                'message' => 'Search query is required',
            ], 400);
        }

        $results = ProductUpload::with(['images', 'category', 'subcategory', 'seller.profile'])
            ->where(function ($q) use ($query) {
                // ✅ Product name
                $q->where('name', 'like', "%{$query}%");

                // ✅ Category name
                $q->orWhereHas('category', function ($cat) use ($query) {
                    $cat->where('name', 'like', "%{$query}%");
                });

                // ✅ Subcategory name
                $q->orWhereHas('subcategory', function ($sub) use ($query) {
                    $sub->where('name', 'like', "%{$query}%");
                });

                // ✅ Seller business name
                $q->orWhereHas('seller.profile', function ($prof) use ($query) {
                    $prof->where('business_name', 'like', "%{$query}%");
                });

                // ✅ Seller first and last name (main seller table)
                $q->orWhereHas('seller', function ($seller) use ($query) {
                    $seller->where('firstname', 'like', "%{$query}%")
                        ->orWhere('lastname', 'like', "%{$query}%");
                });
            })
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'count'   => $results->count(),
            'results' => $results,
        ]);
    }

}
