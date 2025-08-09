<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SellerController;
use App\Models\Seller;


Route::get('/hello', function () {
    return response()->json(['message' => 'Hello from API']);
});

Route::post('/sellers/register', [SellerController::class, 'registerSeller']);
Route::post('/sellers/verify-email', [SellerController::class, 'verifyEmail']);
Route::post('/sellers/resend-verification', [SellerController::class, 'resendVerificationEmail']);
Route::post('/sellers/forgot-password', [SellerController::class, 'requestPasswordReset']);
Route::post('/sellers/verify-reset-code', [SellerController::class, 'verifyPasswordResetCode']);
Route::post('/sellers/resend-reset-code', [SellerController::class, 'resendPasswordResetCode']);
Route::post('/sellers/reset-password', [SellerController::class, 'resetPassword']);
Route::post('/sellers/login', [SellerController::class, 'SellerLogin']);
