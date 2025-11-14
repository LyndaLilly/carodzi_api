<?php
namespace App\Http\Controllers;

use App\Mail\BuyerEmailResetPasswordCode;
use App\Mail\BuyerPasswordResetSuccessMail;
use App\Mail\EmailVerifyBuyer;
use App\Models\Buyer;
use App\Models\BuyerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class BuyerController extends Controller
{
    public function registerBuyer(Request $request)
    {
        Log::info('Incoming buyer registration request.', $request->all());

        $validator = Validator::make($request->all(), [
            'firstname' => 'required|string|max:255',
            'lastname'  => 'required|string|max:255',
            'email'     => 'required|email|unique:buyers,email',
            'password'  => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/',
            ],
            'role'      => 'required|in:buyer,buyer',
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

        Log::info('Validation passed. Proceeding with buyer creation.');

        Log::info('Password received from frontend (before hashing): ' . $request->password);

        $verificationCode = rand(100000, 999999);
        Log::info('Generated verification code for buyer: ' . $verificationCode);

        try {
            $buyer = Buyer::create([
                'firstname'         => $request->firstname,
                'lastname'          => $request->lastname,
                'email'             => $request->email,
                'password'          => Hash::make($request->password),
                'role'              => $request->role,
                'verification_code' => $verificationCode,
                'verified'          => false,
                'profile_updated'   => false,
            ]);

            Log::info('Buyer successfully created in database.', ['buyer_id' => $buyer->id, 'email' => $buyer->email]);

        } catch (\Exception $e) {
            Log::error('Failed to create buyer in database.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to register buyer due to DB error.',
            ], 500);
        }

        try {
            Mail::to($buyer->email)->send(new EmailVerifyBuyer($buyer->firstname, $verificationCode));
            Log::info('Verification email sent to buyer.', ['email' => $buyer->email]);

            $emailMessage = 'Verification code sent to your email.';
        } catch (\Exception $e) {
            Log::error('Failed to send verification email.', [
                'email'   => $buyer->email,
                'message' => $e->getMessage(),
            ]);

            $emailMessage = 'Registration successful, but failed to send verification email.';
        }

        return response()->json([
            'status'  => 'success',
            'message' => $emailMessage,
            'data'    => [
                'email' => $buyer->email,
                'role'  => $buyer->role,
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
            'email' => 'required|email|exists:buyers,email',
            'code'  => 'required|digits:6',
        ]);

        $buyer = Buyer::where('email', $request->email)->first();

        if ($buyer->verified) {
            Log::info('Email already verified.', ['email' => $request->email]);

            return response()->json([
                'status'  => 'info',
                'message' => 'Email already verified.',
            ]);
        }

        if ($buyer->verification_code == $request->code) {
            $buyer->verified                  = true;
            $buyer->verification_code         = null;
            $buyer->email_verified_at         = now(); // ✅ Set verified time
            $buyer->verification_code_sent_at = now();
            $buyer->save();

            Log::info('Email verified successfully.', ['email' => $request->email]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Email verified successfully.',
            ]);
        } else {
            Log::warning('Invalid verification code.', [
                'email'          => $request->email,
                'submitted_code' => $request->code,
                'actual_code'    => $buyer->verification_code,
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
            'email' => 'required|email|exists:buyers,email',
        ]);

        $buyer = Buyer::where('email', $request->email)->first();

        if ($buyer->verified) {
            Log::info('Resend skipped. Email already verified.', ['email' => $request->email]);

            return response()->json([
                'status'  => 'info',
                'message' => 'Email is already verified.',
            ]);
        }

        $newCode                  = rand(100000, 999999);
        $buyer->verification_code = $newCode;
        $buyer->save();

        Log::info('Generated new verification code.', [
            'email' => $buyer->email,
            'code'  => $newCode,
        ]);

        try {
            Mail::to($buyer->email)->send(new EmailVerifyBuyer($buyer->firstname, $newCode));
            Log::info('Resent verification email.', ['email' => $buyer->email]);
        } catch (\Exception $e) {
            Log::error('Failed to resend verification email.', [
                'email'   => $buyer->email,
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
            'email' => 'required|email|exists:buyers,email',
        ]);

        $buyer     = Buyer::where('email', $request->email)->first();
        $resetCode = rand(100000, 999999);

        $buyer->password_reset_code    = $resetCode;
        $buyer->password_reset_sent_at = now();
        $buyer->save();

        $emailSent = true;

        try {
            Mail::to($buyer->email)->send(new BuyerEmailResetPasswordCode($buyer->firstname, $resetCode));
            Log::info('Resent verification email.', ['email' => $buyer->email]);
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
            'email' => 'required|email|exists:buyers,email',
            'code'  => 'required|digits:6',
        ]);

        $buyer = Buyer::where('email', $request->email)->first();

        // Check if code matches
        if ($buyer->password_reset_code != $request->code) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid reset code.',
            ], 400);
        }

        // ✅ Check if code is expired (older than 30 minutes)
        if (! $buyer->password_reset_sent_at || now()->diffInMinutes($buyer->password_reset_sent_at) > 30) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Reset code has expired. Please request a new one.',
            ], 410); // 410 Gone
        }
        $buyer->password_reset_code        = null; // Clear code
        $buyer->password_reset_verified_at = now();
        $buyer->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Reset code verified. You can now reset your password.',
        ]);
    }

    public function resendPasswordResetCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:buyers,email',
        ]);

        $buyer = Buyer::where('email', $request->email)->first();

        if (! $buyer) {
            return response()->json([
                'status'  => 'error',
                'message' => 'buyer not found.',
            ], 404);
        }

        $newResetCode = rand(100000, 999999);
        $buyer->password_reset_code    = $newResetCode;
        $buyer->password_reset_sent_at = now();
        $buyer->save();

        $emailSent = true;

        try {
            Mail::to($buyer->email)->send(new BuyerEmailResetPasswordCode($buyer->firstname, $newResetCode, 'resent'));
            Log::info('Resent verification email.', ['email' => $buyer->email]);
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
            'email'    => 'required|email|exists:buyers,email',
            'password' => [
                'required',
                'confirmed',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/',
            ],
        ]);

        $buyer = Buyer::where('email', $request->email)->first();

        if (! $buyer->password_reset_verified_at) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Reset code not verified.',
            ], 403);
        }

        $buyer->password                   = Hash::make($request->password);
        $buyer->password_reset_verified_at = null; 
        $buyer->save();

        try {
            $buyerName = trim(($buyer->firstname));

            Mail::to($buyer->email)->send(new BuyerPasswordResetSuccessMail($buyerName));
        } catch (\Exception $e) {
            \Log::error("Password reset confirmation email failed: " . $e->getMessage());
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Password reset successful. You can now log in.',
        ]);
    }

    public function BuyerLogin(Request $request)
    {
        Log::info('Login attempt for: ' . $request->email);

        $buyer = Buyer::where('email', $request->email)->first();

        if (! $buyer) {
            Log::warning('buyer not found');
            return response()->json([
                'errors' => [
                    'email' => 'Email does not exist',
                ],
            ], 404);
        }

        if (! Hash::check($request->password, $buyer->password)) {
            Log::warning('Password mismatch', [
                'input_password' => $request->password,
                'hashed'         => $buyer->password,
            ]);
            return response()->json([
                'errors' => [
                    'password' => 'Incorrect password',
                ],
            ], 401);
        }

        if (! $buyer->verified) {
            Log::warning('buyer not verified', ['email' => $request->email]);
            return response()->json([
                'message' => 'Please verify your email before logging in.',
            ], 403);
        }

        $token = $buyer->createToken('buyer_token')->plainTextToken;

        return response()->json([
            'buyer' => $buyer,
            'token' => $token,
        ]);
    }

    public function buyerLogout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Logged out successfully.',
        ]);
    }

    public function me(Request $request)
    {
        $buyer   = $request->user();
        $profile = BuyerProfile::where('buyer_id', $buyer->id)->first();

        return response()->json([
            'buyer'   => $buyer,
            'profile' => $profile,
        ]);
    }

}
