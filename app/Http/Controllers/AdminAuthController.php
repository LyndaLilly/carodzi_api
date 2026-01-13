<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminAuthController extends Controller
{
    // ------------------------
    // REGISTER ADMIN
    // ------------------------
    public function register(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email|unique:admins,email',
            'password' => 'required|min:8|confirmed', // expects password_confirmation
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Create admin
        $admin = Admin::create([
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => 'admin',
            'status'   => true,
        ]);

        // ✅ Make sure admin is saved
        if (!$admin || !$admin->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create admin',
            ], 500);
        }

        // Create personal access token
        $token = $admin->createToken('admin_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'admin'  => [
                'id'    => $admin->id,
                'email' => $admin->email,
                'role'  => $admin->role,
            ],
            'token' => $token,
        ], 201);
    }

    // ------------------------
    // LOGIN ADMIN
    // ------------------------
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $admin = Admin::where('email', $request->email)->first();

        if (!$admin) {
            return response()->json([
                'errors' => ['login' => 'Email does not exist'],
            ], 404);
        }

        if (!Hash::check($request->password, $admin->password)) {
            return response()->json([
                'errors' => ['password' => 'Incorrect password'],
            ], 401);
        }

        // ✅ Make sure the admin exists and has an ID
        if (!$admin->id) {
            return response()->json([
                'errors' => ['login' => 'Admin not properly created'],
            ], 500);
        }

        $token = $admin->createToken('admin_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'admin'  => [
                'id'    => $admin->id,
                'email' => $admin->email,
                'role'  => $admin->role,
            ],
            'token' => $token,
        ]);
    }

    // ------------------------
    // LOGOUT ADMIN
    // ------------------------
    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully.',
        ]);
    }
}
