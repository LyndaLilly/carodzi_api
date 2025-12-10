<?php
namespace App\Http\Controllers;

use App\Models\Buyer;
use App\Models\BuyerProfile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BuyerProfileController extends Controller
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

    protected function formatDate($date)
    {
        try {
            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function profileFill(Request $request)
    {
        $rules = [
            'buyer_id'      => 'required|exists:buyers,id',
            'gender'        => 'required|in:male,female',
            'date_of_birth' => 'nullable|string',
            'profile_image' => 'nullable|image|max:2048',
            'about'         => 'nullable|string',
            'email'         => 'nullable|email',
            'mobile_number' => 'required|string',
            'country'       => 'required|string',
            'state'         => 'required|string',
            'city'          => 'required|string',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated(); // ✅ Use this consistently

        // Format date if present
        if (! empty($validated['date_of_birth'])) {
            $validated['date_of_birth'] = $validated['date_of_birth'];
        }

        // Generate WhatsApp link
        if (! empty($validated['mobile_number'])) {
            $raw                              = preg_replace('/\D/', '', $validated['mobile_number']);
            $validated['whatsapp_phone_link'] = "https://wa.me/{$raw}";
        }

        // ✅ Handle image upload correctly
        if ($request->hasFile('profile_image')) {
            $validated['profile_image'] = $this->uploadFile($request->file('profile_image'), 'buyer_image');
        }

        // ✅ Create the record including buyer_id
        $profile = BuyerProfile::create($validated);

        // ✅ Mark buyer as profile updated
        Buyer::where('id', $request->buyer_id)->update(['profile_updated' => 1]);

        return response()->json([
            'success' => true,
            'message' => 'Profile created successfully',
            'profile' => $profile,
        ]);
    }

    public function update(Request $request)
    {
        $buyerId = $request->user()->id;
        $profile = BuyerProfile::where('buyer_id', $buyerId)->firstOrFail();

        $rules = [
            'gender'        => 'sometimes|nullable|in:male,female',
            'date_of_birth' => $profile->date_of_birth
                ? 'prohibited'
                : 'nullable|string',
            'profile_image' => 'nullable|image|max:2048',
            'about'         => 'sometimes|nullable|string',
            'email'         => 'nullable|email|unique:buyer_profiles,email,' . $profile->id,
            'mobile_number' => 'sometimes|required|string',
            'country'       => 'sometimes|required|string',
            'state'         => 'sometimes|required|string',
            'city'          => 'sometimes|required|string',
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

        // Handle profile image upload
        if ($request->hasFile('profile_image')) {
            if ($profile->profile_image && file_exists(public_path("uploads/{$profile->profile_image}"))) {
                unlink(public_path("uploads/{$profile->profile_image}"));
            }
            $validated['profile_image'] = $this->uploadFile($request->file('profile_image'), 'buyer_image');
        } else {
            unset($validated['profile_image']);
        }

        // Update DB
        $profile->update($validated);
        Buyer::where('id', $buyerId)->update(['profile_updated' => 1]);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'profile' => $profile->fresh(),
        ]);
    }

    public function show(Request $request)
    {
        $buyerId = $request->user()->id;
        $profile = BuyerProfile::where('buyer_id', $buyerId)->first();

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profile not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'profile' => $profile,
        ]);
    }
}
