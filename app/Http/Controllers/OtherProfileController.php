<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\OtherProfile;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OtherProfileController extends Controller
{

    protected function uploadFile($file, $subfolder)
    {
        $uploadDir = public_path("uploads/{$subfolder}");

        if (! file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move($uploadDir, $filename);

        return "{$subfolder}/{$filename}";
    }

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

        $data = $request->except(['profile_image', 'certificate_file']);

        if ($request->hasFile('profile_image')) {
            $data['profile_image'] = $this->uploadFile($request->file('profile_image'), 'profile_images');
        }

        if ($request->hasFile('certificate_file')) {
            $data['certificate_file'] = $this->uploadFile($request->file('certificate_file'), 'certificates');
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

        if ($request->hasFile('profile_image')) {
            // Delete old file if exists
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
