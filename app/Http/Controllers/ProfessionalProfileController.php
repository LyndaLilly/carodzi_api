<?php

namespace App\Http\Controllers;

use App\Models\ProfessionalProfile;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

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

    // ✅ Convert date to MySQL format safely
    protected function formatDate($date)
    {
        try {
            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function storeProfessional(Request $request)
    {
        $rules = [
            'seller_id'             => 'required|exists:sellers,id',
            'gender'                => 'required|in:male,female',
            'date_of_birth'         => 'nullable|string',
            'about'                 => 'required|string|max:1000',
            'business_email'        => 'nullable|email',
            'mobile_number'         => 'required|string',
            'country'               => 'required|string',
            'state'                 => 'required|string',
            'city'                  => 'required|string',
            'business_name'         => 'required|string|max:255',
            'experience_years'      => 'required|integer|min:0',
            'bank_name'             => 'required|string|max:255',
            'business_bank_name'    => 'required|string|max:255',
            'business_bank_account' => 'required|string|max:20',
            'verification_number'   => 'nullable|string|unique:professional_profiles',
            'profile_image'         => 'nullable|image|max:2048',
            'certificate_file'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ];

        $seller = Seller::with('subcategory')->find($request->seller_id);
        if ($seller && $seller->subcategory && $seller->subcategory->auto_verify == 1) {
            $rules['verification_number'] = 'required|string|unique:professional_profiles';
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // ✅ Date fix
        if (!empty($data['date_of_birth'])) {
            $data['date_of_birth'] = $this->formatDate($data['date_of_birth']);
        }


        // ✅ File uploads
        if ($request->hasFile('profile_image')) {
            $data['profile_image'] = $this->uploadFile($request->file('profile_image'), 'profile_images');
        }
        if ($request->hasFile('certificate_file')) {
            $data['certificate_file'] = $this->uploadFile($request->file('certificate_file'), 'certificates');
        }

        $profile = ProfessionalProfile::create($data);
        Seller::where('id', $request->seller_id)->update(['profile_updated' => 1]);

        return response()->json([
            'success' => true,
            'message' => 'Professional profile created successfully',
            'profile' => $profile,
        ]);
    }

    public function updateProfessionalProfile(Request $request)
    {
        $sellerId = $request->user()->id;
        $profile  = ProfessionalProfile::where('seller_id', $sellerId)->firstOrFail();

        $rules = [
            'gender'                => 'sometimes|required|in:male,female',
            'date_of_birth'         => 'nullable|string',
            'about'                 => 'sometimes|required|string|max:1000',
            'business_email'        => 'nullable|email|unique:professional_profiles,business_email,' . $profile->id,
            'mobile_number'         => 'sometimes|required|string',
            'country'               => 'sometimes|required|string',
            'state'                 => 'sometimes|required|string',
            'city'                  => 'sometimes|required|string',
            'business_name'         => 'sometimes|required|string|max:255',
            'experience_years'      => 'sometimes|required|integer|min:0',
            'bank_name'             => 'sometimes|required|string|max:255',
            'business_bank_name'    => 'sometimes|required|string|max:255',
            'business_bank_account' => 'sometimes|required|string|max:20',
            'verification_number'   => 'nullable|string|unique:professional_profiles,verification_number,' . $profile->id,
            'profile_image'         => 'nullable|image|max:2048',
            'certificate_file'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ];

        $seller = $request->user()->load('subcategory');
        if ($seller && $seller->subcategory && $seller->subcategory->auto_verify == 1) {
            $rules['verification_number'] = 'sometimes|required|string|unique:professional_profiles,verification_number,' . $profile->id;
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        // ✅ Fix date
        if (!empty($validated['date_of_birth'])) {
            $validated['date_of_birth'] = $this->formatDate($validated['date_of_birth']);
        }

        // ✅ File updates
        if ($request->hasFile('profile_image')) {
            if ($profile->profile_image && file_exists(public_path("uploads/{$profile->profile_image}"))) {
                unlink(public_path("uploads/{$profile->profile_image}"));
            }
            $validated['profile_image'] = $this->uploadFile($request->file('profile_image'), 'profile_images');
        }

        if ($request->hasFile('certificate_file')) {
            if ($profile->certificate_file && file_exists(public_path("uploads/{$profile->certificate_file}"))) {
                unlink(public_path("uploads/{$profile->certificate_file}"));
            }
            $validated['certificate_file'] = $this->uploadFile($request->file('certificate_file'), 'certificates');
        }

        $profile->update($validated);
        Seller::where('id', $sellerId)->update(['profile_updated' => 1]);

        return response()->json([
            'success' => true,
            'message' => 'Professional profile updated successfully',
            'profile' => $profile->fresh(),
        ]);
    }

    public function showProfessionalProfile(Request $request)
    {
        $sellerId = $request->user()->id;
        $profile  = ProfessionalProfile::where('seller_id', $sellerId)->first();

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Professional profile not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'profile' => $profile,
        ]);
    }
}
