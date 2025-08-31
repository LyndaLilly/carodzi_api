<?php
namespace App\Http\Controllers;

use App\Models\BuyerProfile;
use Illuminate\Http\Request;

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

    public function profileFill(Request $request)
    {
        $buyerId = $request->user()->id;

        $validated = $request->validate([
            'gender'              => 'required|in:male,female',
            'date_of_birth'       => 'required|date',
            'profile_image'       => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'about'               => 'nullable|string',
            'email'               => 'required|email|unique:buyer_profiles,email',
            'mobile_number'       => 'required|string|min:10|max:20',
            'whatsapp_phone_link' => 'nullable|url',
            'country'             => 'required|string',
            'state'               => 'required|string',
            'city'                => 'required|string',
        ]);

        $validated['buyer_id'] = $buyerId;

        if ($request->hasFile('profile_image')) {
            $validated['profile_image'] = $this->uploadFile($request->file('profile_image'), 'buyer_image');
        }

        $profile = BuyerProfile::create($validated);

        $request->user()->update(['profile_updated' => 1]);

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

        $validated = $request->validate([
            'gender'              => 'sometimes|nullable|in:male,female',
            'date_of_birth'       => 'sometimes|nullable|date',
            'profile_image'       => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'about'               => 'sometimes|nullable|string',
            'email'               => 'sometimes|nullable|email|unique:buyer_profiles,email,' . $profile->id,
            'mobile_number'       => 'sometimes|nullable|string|min:10|max:20',
            'whatsapp_phone_link' => 'sometimes|nullable|url',
            'country'             => 'sometimes|nullable|string',
            'state'               => 'sometimes|nullable|string',
            'city'                => 'sometimes|nullable|string',
        ]);

        // Handle profile image upload
        if ($request->hasFile('profile_image')) {
            if ($profile->profile_image && file_exists(public_path("uploads/{$profile->profile_image}"))) {
                unlink(public_path("uploads/{$profile->profile_image}"));
            }
            $validated['profile_image'] = $this->uploadFile($request->file('profile_image'), 'buyer_image');
        } else {
            unset($validated['profile_image']); // don’t overwrite if not present
        }

        // Update DB
        $profile->update($validated);
        $request->user()->update(['profile_updated' => 1]);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'profile' => $profile,
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
