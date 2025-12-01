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
            'type'            => $request->type,
            'verified'        => $request->verified,
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
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
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

    public function search(Request $request)
    {
        $searchTerm     = strtolower($request->query('q', ''));
        $verifiedFilter = $request->query('verified', 'all');         // all | verified | not_verified
        $type           = strtolower($request->query('type', 'all')); // service | seller | all

        if (empty($searchTerm)) {
            return response()->json(['results' => []]);
        }

        $query = Seller::with(['profile', 'subcategory.category'])
            ->where('profile_updated', 1)
            ->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(firstname) LIKE ?', ["%{$searchTerm}%"])
                    ->orWhereRaw('LOWER(lastname) LIKE ?', ["%{$searchTerm}%"])
                    ->orWhereHas('subcategory', function ($sq) use ($searchTerm) {
                        $sq->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"]);
                    })
                    ->orWhereHas('subcategory.category', function ($cq) use ($searchTerm) {
                        $cq->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"]);
                    })
                    ->orWhereHas('professionalProfile', function ($pq) use ($searchTerm) {
                        $pq->whereRaw('LOWER(business_name) LIKE ?', ["%{$searchTerm}%"]);
                    });
            });

        // ✅ Filter by type
        if ($type === 'service') {
            $query->whereHas('subcategory.category', function ($cat) {
                $cat->whereRaw('LOWER(name) = ?', ['service']);
            });
        } elseif ($type === 'seller') {
            $query->whereHas('subcategory.category', function ($cat) {
                $cat->whereRaw('LOWER(name) != ?', ['service']);
            });
        }

        // ✅ Verified filter
        if ($verifiedFilter === 'verified') {
            $query->whereHas('subcategory', fn($q) => $q->where('auto_verify', 1))
                ->where('status', 1);
        } elseif ($verifiedFilter === 'not_verified') {
            $query->whereHas('subcategory', fn($q) => $q->where('auto_verify', 1))
                ->where('status', 0);
        }

        $sellers = $query->orderBy('firstname')->take(20)->get();

        $results = $sellers->map(function ($seller) {
            $autoVerify          = optional($seller->subcategory)->auto_verify == 1;
            $seller->is_verified = ($seller->status == 1 && $autoVerify);

            return [
                'id'                  => $seller->id,
                'firstname'           => $seller->firstname,
                'lastname'            => $seller->lastname,
                'subcategory'         => optional($seller->subcategory)->name,
                'category'            => optional(optional($seller->subcategory)->category)->name,
                'is_verified'         => $seller->is_verified,
                'profile'             => $seller->profile,
                'professionalProfile' => $seller->professionalProfile,
            ];
        });

        return response()->json(['results' => $results]);
    }

    public function mostViewedSellers()
    {
        try {
            $sellers = Seller::with([
                'profile',
                'professionalProfile',
                'products.images',
                'subcategory',
            ])
                ->where('profile_updated', 1)
                ->where('views', '>=', 5)
                ->get();

            // Normalize data: combine profile_image and business_name
            $sellers->transform(function ($seller) {
                $autoVerify          = $seller->subcategory && $seller->subcategory->auto_verify == 1;
                $seller->is_verified = ($autoVerify && $seller->status == 1);

                // Determine profile image
                if ($seller->is_professional && $seller->professionalProfile) {
                    $seller->profile_image = $seller->professionalProfile->profile_image ?? null;
                    $seller->business_name = $seller->professionalProfile->business_name ?? null;
                } else if ($seller->profile) {
                    $seller->profile_image = $seller->profile->profile_image ?? null;
                    $seller->business_name = null; // normal sellers usually have no business name
                } else {
                    $seller->profile_image = null;
                    $seller->business_name = null;
                }

                return $seller;
            });

            return response()->json([
                'success' => true,
                'sellers' => $sellers,
            ]);

        } catch (\Throwable $e) {
            \Log::error('❌ Error fetching most viewed sellers', [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred',
            ], 500);
        }
    }

}
