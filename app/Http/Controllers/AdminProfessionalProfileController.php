<?php

namespace App\Http\Controllers;

use App\Models\ProfessionalProfile;
use App\Models\Seller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Facades\Image;

class AdminProfessionalProfileController extends Controller
{
    protected function uploadAndCompressImage($file, $subfolder)
    {
        $uploadDir = public_path("uploads/{$subfolder}");
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $filename = time() . '_' . uniqid() . '.webp';
        $image = Image::make($file->getRealPath())->orientate();
        $image->resize(1500, 1500, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        })->encode('webp', 80);
        $image->save("{$uploadDir}/{$filename}");

        return "{$subfolder}/{$filename}";
    }

    protected function formatDate($day, $month)
    {
        if (!$day || !$month) return null;
        try {
            return Carbon::createFromFormat('d-F', "{$day}-{$month}")->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function fillProfessionalProfile(Request $request, $sellerId)
    {
        $seller = Seller::with('subcategory')->findOrFail($sellerId);

        $rules = [
            'gender'                => 'required|in:male,female',
            'dob_day'               => 'nullable|integer|min:1|max:31',
            'dob_month'             => 'nullable|string',
            'about'                 => 'nullable|string|max:1000',
            'business_email'        => 'nullable|email',
            'mobile_number'         => 'required|string',
            'country'               => 'required|string',
            'state'                 => 'required|string',
            'city'                  => 'required|string',
            'business_name'         => 'required|string|max:255',
            'experience_years'      => 'nullable|integer|min:0',
            'bank_name'             => 'nullable|string|max:255',
            'business_bank_name'    => 'nullable|string|max:255',
            'business_bank_account' => 'nullable|string|max:20',
            'verification_number'   => $seller->subcategory && $seller->subcategory->auto_verify == 1
                                        ? 'required|string|unique:professional_profiles,verification_number'
                                        : 'nullable|string|unique:professional_profiles,verification_number',
            'profile_image'         => 'nullable|image|max:2048',
            'certificate_file'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Format date
        $data['date_of_birth'] = $this->formatDate($request->dob_day, $request->dob_month);

        // WhatsApp link
        if (!empty($data['mobile_number'])) {
            $raw = preg_replace('/\D/', '', $data['mobile_number']);
            $data['whatsapp_phone_link'] = "https://wa.me/{$raw}";
        }

        // Handle file uploads
        if ($request->hasFile('profile_image')) {
            $data['profile_image'] = $this->uploadAndCompressImage($request->file('profile_image'), 'profile_images');
        }

        if ($request->hasFile('certificate_file')) {
            $data['certificate_file'] = $this->uploadAndCompressImage($request->file('certificate_file'), 'certificates');
        }

        $data['seller_id'] = $sellerId;

        $profile = ProfessionalProfile::updateOrCreate(
            ['seller_id' => $sellerId],
            $data
        );

        // Mark seller profile as updated
        $seller->update(['profile_updated' => 1]);

        return response()->json([
            'success' => true,
            'message' => 'Professional profile filled successfully by admin',
            'profile' => $profile,
        ]);
    }
}
