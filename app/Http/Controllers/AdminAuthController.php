<?php
namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminAuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'firstname' => 'required|string|max:255',
            'lastname'  => 'required|string|max:255',
            'email'     => 'required|email|unique:admins,email',
            'password'  => 'required|string|min:8|confirmed',
            'role'      => 'required|in:super_admin,admin',
        ]);

        $admin = Admin::create([
            'firstname' => $request->firstname,
            'lastname'  => $request->lastname,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'role'      => $request->role,
            'status'    => true,
        ]);

        return response()->json([
            'message' => 'Admin registered successfully',
            'admin'   => $admin,
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        Log::info('Admin login attempt', ['email' => $credentials['email']]);

        // Find admin by email
        $admin = Admin::where('email', $credentials['email'])->first();

        if (! $admin) {
            Log::error('Admin not found', ['email' => $credentials['email']]);
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Verify password
        if (! Hash::check($credentials['password'], $admin->password)) {
            Log::error('Invalid password', ['email' => $credentials['email']]);
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Create token (if using Sanctum or Passport)
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
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out']);
    }
}
