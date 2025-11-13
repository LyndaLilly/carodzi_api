<?php
namespace App\Http\Controllers;

use App\Mail\EmailVerificationCodeMail;
use App\Mail\PasswordResetCodeMail;
use App\Mail\PasswordResetSuccessMail;
use App\Models\Seller;
use App\Models\SellerProfileView;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class SellerController extends Controller
{
    public function registerSeller(Request $request)
    {
        Log::info('Incoming seller registration request.', $request->all());

        $validator = Validator::make($request->all(), [
            'firstname' => 'required|string|max:255',
            'lastname'  => 'required|string|max:255',
            'email'     => 'required|email|unique:sellers,email',
            'password'  => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/',
            ],
            'role'      => 'required|in:buyer,seller',
        ], [
            'password.regex' => 'Password must be at least 8 characters and include at least one lowercase letter, one uppercase letter, one number, and one special character.',
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed during registration.', $validator->errors()->toArray());

            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        Log::info('Password received from frontend: ' . $request->password);

        $verificationCode = rand(100000, 999999);

        $seller = Seller::create([
            'firstname'               => $request->firstname,
            'lastname'                => $request->lastname,
            'email'                   => $request->email,
            'password'                => Hash::make($request->password),
            'role'                    => $request->role,
            'verification_code'       => $verificationCode,
            'verified'                => false,
            'profile_updated'         => false,
            'is_professional'         => false,
            'status'                  => false,
            'is_subscribed'           => true,
            'subscription_type'       => 'trial',
            'subscription_expires_at' => Carbon::now()->addDays(30),
        ]);

        try {
            Mail::to($seller->email)->send(new EmailVerificationCodeMail($seller->firstname, $verificationCode));
            Log::info('Verification email sent to seller.', ['email' => $seller->email]);

            $emailMessage = 'Verification code sent to your email.';
        } catch (\Exception $e) {
            Log::error('Failed to send verification email.', [
                'email'   => $seller->email,
                'message' => $e->getMessage(),
            ]);

            $emailMessage = 'Registration successful, but failed to send verification email.';
        }

        return response()->json([
            'status'  => 'success',
            'message' => $emailMessage,
            'data'    => [
                'email' => $seller->email,
                'role'  => $seller->role,
            ],
        ], 201);
    }

    public function verifyEmail(Request $request)
    {
        Log::info('Attempting email verification.', [
            'email' => $request->email,
            'code'  => $request->code,
        ]);

        $request->validate([
            'email' => 'required|email|exists:sellers,email',
            'code'  => 'required|digits:6',
        ]);

        $seller = Seller::where('email', $request->email)->first();

        if ($seller->verified) {
            Log::info('Email already verified.', ['email' => $request->email]);

            return response()->json([
                'status'  => 'info',
                'message' => 'Email already verified.',
            ]);
        }

        if ($seller->verification_code == $request->code) {
            $seller->verified                  = true;
            $seller->verification_code         = null;
            $seller->email_verified_at         = now(); // ✅ Set verified time
            $seller->verification_code_sent_at = now();
            $seller->save();

            Log::info('Email verified successfully.', ['email' => $request->email]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Email verified successfully.',
            ]);
        } else {
            Log::warning('Invalid verification code.', [
                'email'          => $request->email,
                'submitted_code' => $request->code,
                'actual_code'    => $seller->verification_code,
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid verification code.',
            ], 400);
        }
    }

    public function resendVerificationEmail(Request $request)
    {
        Log::info('Resend verification code request.', ['email' => $request->email]);

        $request->validate([
            'email' => 'required|email|exists:sellers,email',
        ]);

        $seller = Seller::where('email', $request->email)->first();

        if ($seller->verified) {
            Log::info('Resend skipped. Email already verified.', ['email' => $request->email]);

            return response()->json([
                'status'  => 'info',
                'message' => 'Email is already verified.',
            ]);
        }

        $newCode                   = rand(100000, 999999);
        $seller->verification_code = $newCode;
        $seller->save();

        Log::info('Generated new verification code.', [
            'email' => $seller->email,
            'code'  => $newCode,
        ]);

        try {
            Mail::to($seller->email)->send(new EmailVerificationCodeMail($seller->firstname, $newCode));
            Log::info('Resent verification email.', ['email' => $seller->email]);
        } catch (\Exception $e) {
            Log::error('Failed to resend verification email.', [
                'email'   => $seller->email,
                'message' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'New verification code sent. Check your email.',
        ]);
    }

    public function requestPasswordReset(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:sellers,email',
        ]);

        $seller    = Seller::where('email', $request->email)->first();
        $resetCode = rand(100000, 999999);

        $seller->password_reset_code    = $resetCode;
        $seller->password_reset_sent_at = now();
        $seller->save();

        $emailSent = true;

        try {
            Mail::to($seller->email)->send(new PasswordResetCodeMail($resetCode, $seller->firstname . ' ' . $seller->lastname));
        } catch (\Exception $e) {
            \Log::error("Email failed: " . $e->getMessage());
            $emailSent = false;
        } catch (\Exception $e) {
            \Log::error("Email failed: " . $e->getMessage());
            $emailSent = false;
        }

        return response()->json([
            'status'       => 'success',
            'message'      => 'Password reset code saved successfully.',
            'email_status' => $emailSent ? 'sent' : 'failed',
        ]);
    }

    public function verifyPasswordResetCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:sellers,email',
            'code'  => 'required|digits:6',
        ]);

        $seller = Seller::where('email', $request->email)->first();

        // Check if code matches
        if ($seller->password_reset_code != $request->code) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid reset code.',
            ], 400);
        }

        if (! $seller->password_reset_sent_at || now()->diffInMinutes($seller->password_reset_sent_at) > 30) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Reset code has expired. Please request a new one.',
            ], 410);
        }

        $seller->password_reset_code        = null;
        $seller->password_reset_verified_at = now();
        $seller->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Reset code verified. You can now reset your password.',
        ]);
    }

    public function resendPasswordResetCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:sellers,email',
        ]);

        $seller = Seller::where('email', $request->email)->first();

        $newResetCode                   = rand(100000, 999999);
        $seller->password_reset_code    = $newResetCode;
        $seller->password_reset_sent_at = now();
        $seller->save();

        $emailSent = true;

        try {
            Mail::to($seller->email)->send(new PasswordResetCodeMail($newResetCode, $seller->firstname . ' ' . $seller->lastname, 'resent'));
        } catch (\Exception $e) {
            \Log::error("Failed to resend password reset code: " . $e->getMessage());
            $emailSent = false;
        }

        return response()->json([
            'status'       => 'success',
            'message'      => $emailSent ? 'Reset code resent successfully.' : 'Code updated, but email failed to send.',
            'email_status' => $emailSent ? 'sent' : 'failed',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email|exists:sellers,email',
            'password' => [
                'required',
                'confirmed',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/',
            ],
        ]);

        $seller = Seller::where('email', $request->email)->first();

        if (! $seller->password_reset_verified_at) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Reset code not verified.',
            ], 403);
        }

        // ✅ Update password
        $seller->password                   = Hash::make($request->password);
        $seller->password_reset_verified_at = null;
        $seller->save();

        try {
            $sellerName = trim(($seller->firstname ?? '') . ' ' . ($seller->lastname ?? ''));

            Mail::to($seller->email)->send(new PasswordResetSuccessMail($sellerName));
        } catch (\Exception $e) {
            \Log::error("Password reset confirmation email failed: " . $e->getMessage());
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Password reset successful. You can now log in.',
        ]);
    }

    public function SellerLogin(Request $request)
    {
        Log::info('Login attempt for: ' . $request->email);

        $seller = Seller::where('email', $request->email)->first();

        if (! $seller) {
            Log::warning('Seller not found');
            return response()->json([
                'errors' => [
                    'email' => 'Email does not exist',
                ],
            ], 404);
        }

        if (! Hash::check($request->password, $seller->password)) {
            Log::warning('Password mismatch', [
                'input_password' => $request->password,
                'hashed'         => $seller->password,
            ]);
            return response()->json([
                'errors' => [
                    'password' => 'Incorrect password',
                ],
            ], 401);
        }

        if (! $seller->verified) {
            Log::warning('Seller not verified', ['email' => $request->email]);
            return response()->json([
                'message' => 'Please verify your email before logging in.',
            ], 403);
        }

        $token = $seller->createToken('seller_token')->plainTextToken;

        return response()->json([
            'seller' => $seller,
            'token'  => $token,
        ]);
    }

    public function sellerLogout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Logged out successfully.',
        ]);
    }

    public function me(Request $request)
    {
        $seller = $request->user()->load([
            'profile',
            'professionalProfile',
            'subcategory',
        ]);

        // Normalize file URLs
        if ($seller->profile && $seller->profile->profile_image) {
            $seller->profile->profile_image = url('storage/' . $seller->profile->profile_image);
        }

        if ($seller->professionalProfile) {
            if ($seller->professionalProfile->profile_image) {
                $seller->professionalProfile->profile_image = url('storage/' . $seller->professionalProfile->profile_image);
            }
            if ($seller->professionalProfile->certificate_file) {
                $seller->professionalProfile->certificate_file = url('storage/' . $seller->professionalProfile->certificate_file);
            }
        }

        return response()->json([
            'seller'  => $seller,
            'profile' => $seller->is_professional
                ? $seller->professionalProfile
                : $seller->profile,
        ]);
    }

    public function updateSellerCategory(Request $request)
    {
        $request->validate([
            'category_id'     => 'required|exists:sellers_category,id',
            'sub_category_id' => 'required|exists:sellers_subcategory,id',
            'is_professional' => 'required|boolean',
        ]);

        $seller = $request->user();

        // Get the subcategory
        $subCategory = \App\Models\SellerSubcategory::findOrFail($request->sub_category_id);

        $seller->status = 0;

        $seller->category_id     = $request->category_id;
        $seller->sub_category_id = $request->sub_category_id;
        $seller->is_professional = $request->is_professional;
        $seller->save();

        $seller->load(['profile', 'professionalProfile']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Category updated successfully.',
            'seller'  => $seller,
            'profile' => $seller->is_professional
                ? $seller->professionalProfile
                : $seller->profile,
        ]);
    }

    public function updateProductCategory(Request $request)
    {
        $request->validate([
            'product_id'      => 'required|exists:product_categories,id',
            'sub_product_id'  => 'required|exists:product_subcategories,id',
            'is_professional' => 'required|boolean',
        ]);

        $seller                  = $request->user();
        $seller->product_id      = $request->product_id;
        $seller->sub_product_id  = $request->sub_product_id;
        $seller->is_professional = $request->is_professional;
        $seller->save();

        // Load profile & professionalProfile
        $seller->load(['profile', 'professionalProfile']);

        if ($seller->profile && $seller->profile->profile_image) {
            $seller->profile->profile_image = url('storage/' . $seller->profile->profile_image);
        }

        if ($seller->professionalProfile) {
            if ($seller->professionalProfile->profile_image) {
                $seller->professionalProfile->profile_image = url('storage/' . $seller->professionalProfile->profile_image);
            }
            if ($seller->professionalProfile->certificate) {
                $seller->professionalProfile->certificate = url('storage/' . $seller->professionalProfile->certificate);
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Product category updated successfully.',
            'seller'  => $seller,
            'profile' => $seller->is_professional
                ? $seller->professionalProfile
                : $seller->profile,
        ]);
    }

    public function viewSeller($id)
    {
        try {
            $seller = Seller::findOrFail($id);
            $ip     = request()->ip();

            // Prevent multiple rapid views from same IP or viewer within 1 hour
            $alreadyViewed = SellerProfileView::where('seller_id', $seller->id)
                ->where(function ($q) use ($ip) {
                    $q->where('ip_address', $ip);
                    if (Auth::check()) {
                        $q->orWhere('viewer_id', Auth::id());
                    }
                })
                ->where('created_at', '>', now()->subHours(1))
                ->exists();

            if (! $alreadyViewed) {
                SellerProfileView::create([
                    'seller_id'  => $seller->id,
                    'viewer_id'  => Auth::id(),
                    'ip_address' => $ip,
                ]);

                // Increment total views in sellers table
                $seller->increment('views');
            }

            // Load relationships (profile, etc.)
            $seller->load(['profile', 'professionalProfile', 'subcategory', 'category']);

            // Normalize images
            if ($seller->profile && $seller->profile->profile_image) {
                $seller->profile->profile_image = url('storage/' . $seller->profile->profile_image);
            }

            if ($seller->professionalProfile && $seller->professionalProfile->profile_image) {
                $seller->professionalProfile->profile_image = url('storage/' . $seller->professionalProfile->profile_image);
            }

            // ✅ Include recent profile views (last 3)
            $recentViews = SellerProfileView::where('seller_id', $seller->id)
                ->latest()
                ->take(3)
                ->get(['id', 'ip_address', 'created_at', 'viewer_id']);

            return response()->json([
                'status'       => 'success',
                'message'      => 'Seller viewed successfully.',
                'seller'       => $seller,
                'recent_views' => $recentViews,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Seller not found.',
            ], 404);
        }
    }

}
