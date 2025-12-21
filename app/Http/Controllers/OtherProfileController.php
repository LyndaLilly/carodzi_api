<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\OtherProfile;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Facades\Image;

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

    public function storeOther(Request $request)
    {
        $rules = [
            'seller_id'             => 'required|exists:sellers,id',
            'gender'                => 'required|in:male,female',
            'date_of_birth'         => 'nullable|string',
            'about'                 => 'nullable|string|max:1000',
            'business_email'        => 'nullable|email',
            'mobile_number'         => 'required|string',
            'country'               => 'required|string',
            'state'                 => 'required|string',
            'city'                  => 'required|string',
            'business_name'         => 'required|string|max:255',
            'date_of_establishment' => 'nullable|date',
            'bank_name'             => 'nullable|string|max:255',
            'business_bank_name'    => 'nullable|string|max:255',
            'business_bank_account' => 'nullable|string|max:20',
            'profile_image'         => 'nullable|image|max:2048',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if (! empty($data['date_of_birth'])) {
            $data['date_of_birth'] = $data['date_of_birth'];
        }

        // Generate WhatsApp link
        if (! empty($data['mobile_number'])) {
            $raw                         = preg_replace('/\D/', '', $data['mobile_number']);
            $data['whatsapp_phone_link'] = "https://wa.me/{$raw}";
        }

        if ($request->hasFile('profile_image')) {
            $data['profile_image'] = $this->uploadAndCompressImage($request->file('profile_image'), 'profile_images');
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

        $rules = [
            'gender'                => 'sometimes|required|in:male,female',
            'date_of_birth'         =>  'nullable|string',
            'about'                 => 'nullable|string|max:1000',
            'business_email'        => 'nullable|email|unique:other_profiles,business_email,' . $profile->id,
            'mobile_number'         => 'sometimes|required|string',
            'country'               => 'sometimes|required|string',
            'state'                 => 'sometimes|required|string',
            'city'                  => 'sometimes|required|string',
            'business_name'         => 'sometimes|required|string|max:255',
            'date_of_establishment' => 'nullable|date',
            'bank_name'             => 'nullable|string|max:255',
            'business_bank_name'    => 'nullable|string|max:255',
            'business_bank_account' => 'nullable|string|max:20',
            'profile_image'         => 'nullable|image|max:2048',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        if (! empty($data['date_of_birth'])) {
            $data['date_of_birth'] = $data['date_of_birth'];
        }

        // Generate WhatsApp link
        if (! empty($validated['mobile_number'])) {
            $raw                              = preg_replace('/\D/', '', $validated['mobile_number']);
            $validated['whatsapp_phone_link'] = "https://wa.me/{$raw}";
        }

        if ($request->hasFile('profile_image')) {
            // Delete old file if exists
            if ($profile->profile_image && file_exists(public_path("uploads/{$profile->profile_image}"))) {
                unlink(public_path("uploads/{$profile->profile_image}"));
            }
            $validated['profile_image'] = $this->uploadAndCompressImage($request->file('profile_image'), 'profile_images');
        } else {
            unset($validated['profile_image']);
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
