<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ProfessionalProfile;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfessionalProfileController extends Controller
{
    protected function uploadFile($file, $subfolder)
    {
        $uploadDir = public_path("uploads/{$subfolder}");

        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move($uploadDir, $filename);

        return "{$subfolder}/{$filename}";
    }

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
            'certificate_file'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'bank_name'             => 'nullable|string',
            'business_bank_name'    => 'nullable|string',
            'business_bank_account' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->except(['profile_image', 'certificate_file']);

        if ($request->hasFile('profile_image')) {
            $data['profile_image'] = $this->uploadFile($request->file('profile_image'), 'profile_images');
        }

        if ($request->hasFile('certificate_file')) {
            $data['certificate_file'] = $this->uploadFile($request->file('certificate_file'), 'certificates');
        }

        $profile = ProfessionalProfile::create($data);

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
            'profile_image'         => 'sometimes|nullable|image|max:2048',
            'certificate_file'      => 'sometimes|nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($request->hasFile('profile_image')) {
            if ($profile->profile_image && file_exists(public_path("uploads/{$profile->profile_image}"))) {
                unlink(public_path("uploads/{$profile->profile_image}"));
            }
            $validated['profile_image'] = $this->uploadFile($request->file('profile_image'), 'profile_images');
        } else {
            unset($validated['profile_image']);
        }

        if ($request->hasFile('certificate_file')) {
            if ($profile->certificate_file && file_exists(public_path("uploads/{$profile->certificate_file}"))) {
                unlink(public_path("uploads/{$profile->certificate_file}"));
            }
            $validated['certificate_file'] = $this->uploadFile($request->file('certificate_file'), 'certificates');
        } else {
            unset($validated['certificate_file']);
        }

        $profile->update($validated);

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

        if (!$profile) {
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
