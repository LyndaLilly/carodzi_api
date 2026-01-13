<?php
namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminAuthController extends Controller
{
    public function register(Request $request)
    {
        Log::info('Admin registration attempt', [
            'email' => $request->email,
        ]);

        try {
            $request->validate([
                'email'    => 'required|email|unique:admins,email',
                'password' => 'required|string|min:8|confirmed',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Admin registration validation failed', [
                'errors' => $e->errors(),
                'input'  => $request->except('password', 'password_confirmation'),
            ]);

            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        }

        try {
            $admin = Admin::create([
                'email'    => $request->email,
                'password' => Hash::make($request->password),
                'role'     => 'admin', // default role
                'status'   => true,
            ]);

            Log::info('Admin registered successfully', [
                'admin_id' => $admin->id,
                'email'    => $admin->email,
            ]);

            return response()->json([
                'message' => 'Admin registered successfully',
                'admin'   => $admin,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Admin registration failed', [
                'error' => $e->getMessage(),
                'input' => $request->except('password', 'password_confirmation'),
            ]);

            return response()->json([
                'message' => 'Registration failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        Log::info('Admin login attempt', ['email' => $credentials['email']]);

        $admin = Admin::where('email', $credentials['email'])->first();

        if (! $admin || ! Hash::check($credentials['password'], $admin->password)) {
            Log::error('Invalid admin login', ['email' => $credentials['email']]);
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Only allow role = admin (skip super_admin checks)
        if ($admin->role !== 'admin') {
            Log::warning('Non-admin attempted login', ['email' => $credentials['email'], 'role' => $admin->role]);
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $token = $admin->createToken('admin-token')->plainTextToken;

        Log::info('Admin logged in successfully', ['email' => $credentials['email']]);

        return response()->json([
            'message' => 'Admin logged in successfully',
            'token'   => $token,
            'admin'   => $admin,
        ]);
    }

    public function logout(Request $request)
    {

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

}
