<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ProfessionalProfile;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfessionalProfileController extends Controller
{
    public function storeProfessional(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'seller_id'             => 'required|exists:sellers,id',
            'gender'                => 'nullable|in:male,female',
            'date_of_birth'         => 'nullable|date',
            'profile_image'         => 'nullable|image|max:2048',
            'about'                 => 'nullable|string',
            'business_email'        => 'nullable|email|unique:professional_profiles',
            'mobile_number'         => 'nullable|string',
            'whatsapp_phone_link'   => 'nullable|string',
            'country'               => 'nullable|string',
            'state'                 => 'nullable|string',
            'city'                  => 'nullable|string',
            'business_name'         => 'nullable|string',
            'verification_number'   => 'required|string|unique:professional_profiles',
            'school_name'           => 'nullable|string',
            'graduation_year'       => 'nullable|digits:4|integer',
            'experience_years'      => 'nullable|integer',
            'certificate_file'      => 'nullable|file|mimes:pdf,jpg,png|max:5120',
            'bank_name'             => 'nullable|string',
            'business_bank_name'    => 'nullable|string',
            'business_bank_account' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();

        if ($request->hasFile('profile_image')) {
            $data['profile_image'] = $request->file('profile_image')->store('profile_images', 'public');
        }

        if ($request->hasFile('certificate_file')) {
            $data['certificate_file'] = $request->file('certificate_file')->store('certificates', 'public');
        }

        $profile = ProfessionalProfile::create($data);

        // Update sellers table
        Seller::where('id', $request->seller_id)->update(['profile_updated' => 1]);

        return response()->json([
            'message' => 'Professional profile created successfully',
            'profile' => $profile,
        ]);
    }

    public function updateProfessionalProfile(Request $request)
    {
        $sellerId = $request->user()->id;
        $profile  = ProfessionalProfile::where('seller_id', $sellerId)->firstOrFail();

        $validated = $request->validate([
            'gender'                => 'nullable|in:male,female',
            'date_of_birth'         => 'nullable|date',
            'about'                 => 'nullable|string',
            'business_email'        => 'nullable|email|unique:professional_profiles,business_email,' . $profile->id,
            'mobile_number'         => 'nullable|string',
            'whatsapp_phone_link'   => 'nullable|string',
            'country'               => 'nullable|string',
            'state'                 => 'nullable|string',
            'city'                  => 'nullable|string',
            'business_name'         => 'nullable|string',
            'verification_number'   => 'required|string|unique:professional_profiles,verification_number,' . $profile->id,
            'school_name'           => 'nullable|string',
            'graduation_year'       => 'nullable|digits:4|integer',
            'experience_years'      => 'nullable|integer',
            'bank_name'             => 'nullable|string',
            'business_bank_name'    => 'nullable|string',
            'business_bank_account' => 'nullable|string',
        ]);

        // Handle profile image
        if ($request->hasFile('profile_image')) {
            $image                      = $request->file('profile_image');
            $imageName                  = time() . '_' . $image->getClientOriginalName();
            $imagePath                  = $image->storeAs('profile_images', $imageName, 'public');
            $validated['profile_image'] = $imagePath;
        }

        // Handle certificate file
        if ($request->hasFile('certificate_file')) {
            $file                          = $request->file('certificate_file');
            $fileName                      = time() . '_' . $file->getClientOriginalName();
            $filePath                      = $file->storeAs('certificates', $fileName, 'public');
            $validated['certificate_file'] = $filePath;
        }

        // Update profile
        $profile->update($validated);

        // Mark seller profile as updated
        Seller::where('id', $sellerId)->update(['profile_updated' => 1]);

        return response()->json([
            'success' => true,
            'message' => 'Professional profile updated successfully',
            'profile' => $profile,
            'seller'  => Seller::find($sellerId),
        ]);
    }

    public function showProfessionalProfile(Request $request)
    {
        $sellerId = $request->user()->id;
        
        $profile = ProfessionalProfile::where('seller_id', $sellerId)->first();

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Professional profile not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'profile' => $profile,
            'seller'  => Seller::find($sellerId),
        ]);
    }

}
