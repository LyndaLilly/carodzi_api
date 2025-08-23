<?php
namespace App\Http\Controllers;

use App\Models\Seller;
use App\Models\SellerProfile;
use Illuminate\Http\Request;

class SellerProfileController extends Controller
{
    /**
     * Show seller profile
     */
    public function show(Request $request)
    {
        $sellerId = $request->user()->id;
        $profile  = SellerProfile::where('seller_id', $sellerId)->first();

        if (! $profile) {
            return response()->json([
                'gender'              => '',
                'date_of_birth'       => '',
                'email'               => '',
                'phone_number'        => '',
                'country'             => '',
                'whatsapp_phone_link' => '',
                'state'               => '',
                'city'                => '',
                'business_name'       => '',
                'category_id'         => null,
                'product_service_id'  => null,
                'profile_image'       => null,
                'profession'          => null,
            ]);
        }

        $profileImageUrl = $profile->profile_image
        ? asset('storage/profile_images/' . $profile->profile_image)
        : null;

        return response()->json(array_merge($profile->toArray(), [
            'profile_image_url' => $profileImageUrl,
        ]));
    }

    public function store(Request $request)
    {
        $sellerId = $request->user()->id;

        $rules = [
            'gender'              => 'required|in:male,female',
            'date_of_birth'       => 'required|date',
            'email'               => 'required|email|unique:seller_profiles,email',
            'phone_number'        => 'nullable|string',
            'country'             => 'nullable|string',
            'whatsapp_phone_link' => 'nullable|url',
            'state'               => 'nullable|string',
            'city'                => 'nullable|string',
            'business_name'       => 'nullable|string',
            'category_id'         => 'nullable|exists:sellers_category,id',
            'product_service_id'  => 'nullable|exists:sellers_subcategory,id',
            'profile_image'       => 'nullable|file|image|max:2048',
            'profession'          => 'nullable|string|max:255',
            'is_professional'     => 'boolean',
        ];

        $validatedData = $request->validate($rules);

        // handle image upload
        if ($request->hasFile('profile_image')) {
            $image     = $request->file('profile_image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->storeAs('profile_images', $imageName, 'public');
            $validatedData['profile_image'] = $imageName;
        }

        $validatedData['seller_id'] = $sellerId;
        $profile                    = SellerProfile::create($validatedData);

        Seller::where('id', $sellerId)->update([
            'profile_updated' => 1,
            'is_professional' => $request->boolean('is_professional'),
        ]);

        $updatedSeller = Seller::find($sellerId);

        return response()->json([
            'message'           => 'Seller profile created successfully',
            'profile'           => $profile,
            'profile_image_url' => $profile->profile_image
            ? asset('storage/profile_images/' . $profile->profile_image)
            : null,
            'seller'            => $updatedSeller,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $sellerId = auth()->id();

        $validated = $request->validate([
            'gender'              => 'nullable|string',
            'date_of_birth'       => 'nullable|date',
            'email'               => 'nullable|email|unique:seller_profiles,email,' . $sellerId . ',seller_id',
            'phone_number'        => 'nullable|string',
            'country'             => 'nullable|string',
            'state'               => 'nullable|string',
            'city'                => 'nullable|string',
            'business_name'       => 'nullable|string',
            'category_id'         => 'nullable|integer',
            'product_service_id'  => 'nullable|integer',
            'profile_image'       => 'nullable|image',
            'whatsapp_phone_link' => 'nullable|string',
            'profession'          => 'nullable|string|max:255',
            'is_professional'     => 'boolean',
        ]);

        $profile = SellerProfile::where('seller_id', $sellerId)->first();

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profile not found',
            ], 404);
        }

        if ($request->hasFile('profile_image')) {
            $image     = $request->file('profile_image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->storeAs('profile_images', $imageName, 'public');
            $validated['profile_image'] = $imageName;
        }

        $profile->update($validated);

        Seller::where('id', $sellerId)->update([
            'profile_updated' => 1,
            'is_professional' => $request->boolean('is_professional'),
        ]);

        $updatedSeller = Seller::find($sellerId);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data'    => $profile,
            'seller'  => $updatedSeller,
        ]);
    }

}
