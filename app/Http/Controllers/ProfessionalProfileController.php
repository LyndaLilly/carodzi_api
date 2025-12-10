<?php
namespace App\Http\Controllers;

use App\Models\ProfessionalProfile;
use App\Models\Seller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Facades\Image;

class ProfessionalProfileController extends Controller
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

    protected function uploadAndCompressImage($file, $subfolder)
    {
        $uploadDir = public_path("uploads/{$subfolder}");
        if (! file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $filename = time() . '_' . uniqid() . '.webp';
        $image    = Image::make($file->getRealPath())->orientate();

        $maxWidth  = 1500;
        $maxHeight = 1500;

        $image->resize($maxWidth, $maxHeight, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });

        $image->encode('webp', 80);

        $image->save("{$uploadDir}/{$filename}");

        $finalPath = "{$uploadDir}/{$filename}";

        \Log::info("Image Compressed:", [
            "original_size_kb" => round($file->getSize() / 1024, 2),
            "new_size_kb"      => round(filesize($finalPath) / 1024, 2),
            "saved_as"         => $filename,
        ]);

        return "{$subfolder}/{$filename}";
    }

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
        // 🔍 Log the full incoming request before validation
        \Log::info('Professional Profile Request Received:', [
            'all_input'            => $request->all(),
            'has_profile_image'    => $request->hasFile('profile_image'),
            'has_certificate_file' => $request->hasFile('certificate_file'),
        ]);

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
            'bank_name'             => 'nullable|string|max:255',
            'business_bank_name'    => 'nullable|string|max:255',
            'business_bank_account' => 'nullable|string|max:20',
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

            // ❗ Log validation failure
            \Log::error('Professional Profile Validation Failed:', [
                'errors' => $validator->errors(),
                'input'  => $request->all(),
            ]);

            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if (! empty($data['date_of_birth'])) {
            $data['date_of_birth'] = $data['date_of_birth'];
        }

        // WhatsApp Link
        if (! empty($data['mobile_number'])) {
            $raw                         = preg_replace('/\D/', '', $data['mobile_number']);
            $data['whatsapp_phone_link'] = "https://wa.me/{$raw}";
        }

        // Logs for file upload
        if ($request->hasFile('profile_image')) {
            \Log::info('Uploading profile_image:', [
                'mime'          => $request->file('profile_image')->getMimeType(),
                'size'          => $request->file('profile_image')->getSize(),
                'original_name' => $request->file('profile_image')->getClientOriginalName(),
            ]);

            $data['profile_image'] = $this->uploadAndCompressImage($request->file('profile_image'), 'profile_images');

            // $data['profile_image'] = $this->uploadFile($request->file('profile_image'), 'profile_images');
        }

        if ($request->hasFile('certificate_file')) {
            \Log::info('Uploading certificate_file:', [
                'mime'          => $request->file('certificate_file')->getMimeType(),
                'size'          => $request->file('certificate_file')->getSize(),
                'original_name' => $request->file('certificate_file')->getClientOriginalName(),
            ]);

            $data['certificate_file'] = $this->uploadAndCompressImage($request->file('certificate_file'), 'certificates');
        }

        $profile = ProfessionalProfile::create($data);
        Seller::where('id', $request->seller_id)->update(['profile_updated' => 1]);

        // 🔵 Final log
        \Log::info('Professional Profile Created Successfully:', [
            'profile' => $profile,
        ]);

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
            'about'                 => 'sometimes|required|string|max:50',
            'business_email'        => 'nullable|email|unique:professional_profiles,business_email,' . $profile->id,
            'mobile_number'         => 'sometimes|required|string',
            'country'               => 'sometimes|required|string',
            'state'                 => 'sometimes|required|string',
            'city'                  => 'sometimes|required|string',
            'business_name'         => 'sometimes|required|string|max:255',
            'experience_years'      => 'sometimes|nullable|integer|min:0',
            'bank_name'             => 'nullable|string|max:255',
            'business_bank_name'    => 'nullable|string|max:255',
            'business_bank_account' => 'nullable|string|max:20',
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

        if (! empty($data['date_of_birth'])) {
            $data['date_of_birth'] = $data['date_of_birth'];
        }

        if (! empty($validated['mobile_number'])) {
            $raw                              = preg_replace('/\D/', '', $validated['mobile_number']);
            $validated['whatsapp_phone_link'] = "https://wa.me/{$raw}";
        }

        // File updates
        if ($request->hasFile('profile_image')) {
            if ($profile->profile_image && file_exists(public_path("uploads/{$profile->profile_image}"))) {
                unlink(public_path("uploads/{$profile->profile_image}"));
            }

            $validated['profile_image'] = $this->uploadAndCompressImage($request->file('profile_image'), 'profile_images');

        }

        if ($request->hasFile('certificate_file')) {
            if ($profile->certificate_file && file_exists(public_path("uploads/{$profile->certificate_file}"))) {
                unlink(public_path("uploads/{$profile->certificate_file}"));
            }
            $validated['certificate_file'] = $this->uploadAndCompressImage($request->file('certificate_file'), 'certificates');
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

        if (! $profile) {
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
