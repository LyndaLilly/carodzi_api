<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\OtherProfile;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OtherProfileController extends Controller
{
    public function storeOther(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'seller_id'             => 'required|exists:sellers,id',
            'gender'                => 'required|in:male,female',
            'date_of_birth'         => 'required|date',
            'profile_image'         => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'certificate_file'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',
            'about'                 => 'required|string|min:10|max:1000',
            'business_email'        => 'required|email|unique:other_profiles',
            'mobile_number'         => 'required|string|min:10|max:20',
            'whatsapp_phone_link'   => 'nullable|url',
            'country'               => 'required|string',
            'state'                 => 'required|string',
            'city'                  => 'required|string',
            'business_name'         => 'required|string|max:255',
            'bank_name'             => 'nullable|string|max:255',
            'business_bank_name'    => 'nullable|string|max:255',
            'business_bank_account' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();

        if ($request->hasFile('profile_image')) {
            $data['profile_image'] = $request->file('profile_image')->store('profile_images', 'public');
        }

        $profile = OtherProfile::create($data);

        Seller::where('id', $request->seller_id)->update(['profile_updated' => 1]);

        return response()->json([
            'message' => 'Profile created successfully',
            'profile' => $profile,
        ]);
    }

    public function updateOtherProfile(Request $request)
    {
        $sellerId = $request->user()->id;
        $profile  = OtherProfile::where('seller_id', $sellerId)->firstOrFail();

        // Validate only fields that are present in the request
        $validated = $request->validate([
            'gender'                => 'sometimes|in:male,female',
            'date_of_birth'         => 'sometimes|date',
            'profile_image'         => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'certificate_file'      => 'sometimes|nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',
            'about'                 => 'sometimes|string|min:10|max:1000',
            'business_email'        => 'sometimes|email|unique:other_profiles,business_email,' . $profile->id,
            'mobile_number'         => 'sometimes|string|min:10|max:20',
            'whatsapp_phone_link'   => 'sometimes|nullable|url',
            'country'               => 'sometimes|string',
            'state'                 => 'sometimes|string',
            'city'                  => 'sometimes|string',
            'business_name'         => 'sometimes|string|max:255',
            'bank_name'             => 'sometimes|nullable|string|max:255',
            'business_bank_name'    => 'sometimes|nullable|string|max:255',
            'business_bank_account' => 'sometimes|nullable|string|max:50',
        ]);

        // Handle file uploads, keep old if not sent
        if ($request->hasFile('profile_image')) {
            $validated['profile_image'] = $request->file('profile_image')->store('profile_images', 'public');
        } else {
            unset($validated['profile_image']); // Keep existing file
        }

        // Update profile with only the validated fields
        $profile->update($validated);

        Seller::where('id', $sellerId)->update(['profile_updated' => 1]);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'profile' => $profile,
            'seller'  => Seller::find($sellerId),
        ]);
    }

    public function showOtherProfile(Request $request)
    {
        $sellerId = $request->user()->id;

        $profile = OtherProfile::where('seller_id', $sellerId)->first();

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profile not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'profile' => $profile,
            'seller'  => Seller::find($sellerId),
        ]);
    }
}
