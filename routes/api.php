<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\OtherProfileController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductUploadController;
use App\Http\Controllers\ProfessionalProfileController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\BuyerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BuyerProfileController;

// Seller routes
Route::post('/sellers/register', [SellerController::class, 'registerSeller']);
Route::post('/sellers/verify-email', [SellerController::class, 'verifyEmail']);
Route::post('/sellers/resend-verification', [SellerController::class, 'resendVerificationEmail']);
Route::post('/sellers/forgot-password', [SellerController::class, 'requestPasswordReset']);
Route::post('/sellers/verify-reset-code', [SellerController::class, 'verifyPasswordResetCode']);
Route::post('/sellers/resend-reset-code', [SellerController::class, 'resendPasswordResetCode']);
Route::post('/sellers/reset-password', [SellerController::class, 'resetPassword']);
Route::post('/sellers/login', [SellerController::class, 'SellerLogin']);
Route::middleware('auth:sanctum')->post('/seller/logout', [SellerController::class, 'sellerLogout']);

Route::middleware('auth:sanctum')->get('/seller/me', [SellerController::class, 'me']);
Route::middleware('auth:sanctum')->post('/seller/product-upload', [ProductUploadController::class, 'storeProduct']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/seller/professional-profile', [ProfessionalProfileController::class, 'storeProfessional']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::patch('/seller/professional-update', [ProfessionalProfileController::class, 'updateProfessionalProfile']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/seller/other-profile', [OtherProfileController::class, 'storeOther']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::patch('/seller/other-update', [OtherProfileController::class, 'updateOtherProfile']);
});

Route::middleware('auth:sanctum')->get('/seller/professional-profile', [ProfessionalProfileController::class, 'showProfessionalProfile']);

Route::middleware('auth:sanctum')->get('/seller/other-profile', [OtherProfileController::class, 'showOtherProfile']);

Route::middleware('auth:sanctum')->post('/seller/update-category', [SellerController::class, 'updateSellerCategory']);

Route::middleware('auth:sanctum')->post('/seller/update-product-category', [SellerController::class, 'updateProductCategory']);

Route::get('/products/popular', [ProductUploadController::class, 'getPopularProducts']);
Route::get('/services/popular', [ProductUploadController::class, 'getPopularServices']);


// Get all products for a seller
Route::get('/products/seller/{sellerId}', [ProductUploadController::class, 'getAllProducts']);

// Get single product
Route::get('/products/{id}', [ProductUploadController::class, 'getSingleProduct']);
Route::get('/products/{id}/recommended', [ProductController::class, 'getRecommended']);

Route::middleware('auth:sanctum')->group(function () {
    // Update product (expects product_id in request body + _method=PATCH if using FormData)
    Route::patch('/products/update', [ProductUploadController::class, 'updateProduct']);

    // Delete product (expects product_id in request body)
    Route::post('/products/delete', [ProductUploadController::class, 'deleteProduct']);
});



Route::get('/products', [ProductUploadController::class, 'getAllProductsForBuyers']);
Route::get('/subcategory/{id}', [ProductCategoryController::class, 'showSubcategory']);



//Buyers

Route::post('/buyers/register', [BuyerController::class, 'registerBuyer']);
Route::post('/buyers/verify-email', [BuyerController::class, 'verifyEmail']);
Route::post('/buyers/resend-verification', [BuyerController::class, 'resendVerificationEmail']);
Route::post('/buyers/forgot-password', [BuyerController::class, 'requestPasswordReset']);
Route::post('/buyers/verify-reset-code', [BuyerController::class, 'verifyPasswordResetCode']);
Route::post('/buyers/resend-reset-code', [BuyerController::class, 'resendPasswordResetCode']);
Route::post('/buyers/reset-password', [BuyerController::class, 'resetPassword']);
Route::post('/buyers/login', [BuyerController::class, 'BuyerLogin']);
Route::middleware('auth:sanctum')->post('/buyers/logout', [BuyerController::class, 'buyerLogout']);
Route::middleware('auth:sanctum')->get('/buyer/me', [BuyerController::class, 'me']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/buyer/profile-fill', [BuyerProfileController::class, 'profileFill']);
   Route::patch('/buyer/profile', [BuyerProfileController::class, 'update']);
    Route::get('/buyer/profile', [BuyerProfileController::class, 'show']);
});





//Admins Routes

Route::middleware('auth:admin')->get('/admin/me', function (Request $request) {
    $admin = $request->user();

    if (! $admin) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    return response()->json([
        'type' => 'admin',
        'user' => $admin,
    ]);
});

Route::post('/admin/login', [AdminAuthController::class, 'login']);
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->middleware('auth:admin');
Route::post('/admin/register', [AdminAuthController::class, 'register'])
    ->middleware('auth:admin', 'is.superadmin');

Route::middleware(['auth:admin', 'is.superadmin'])->group(function () {
    Route::get('/admin/dashboard', function () {
        return view('admin.dashboard');
    });

    Route::post('/admin/create-admin', [AdminController::class, 'createAdmin']);
});

Route::middleware(['auth:admin'])->group(function () {
    Route::post('/admin/seller-category', [AdminController::class, 'createSellerCategory']);
    Route::post('/admin/seller-subcategory', [AdminController::class, 'createSellerSubcategory']);
});

Route::get('/admin/seller-categories', [AdminController::class, 'getSellerCategories']);
Route::get('/admin/seller-subcategories/{categoryId}', [AdminController::class, 'getSubcategoriesByCategory']);

Route::middleware('auth:admin')->group(function () {
    Route::post('/admin/product-category', [ProductCategoryController::class, 'storeCategory']);
    Route::put('/admin/product-category/{id}', [ProductCategoryController::class, 'updateCategory']);
    Route::delete('/admin/product-category/{id}', [ProductCategoryController::class, 'deleteCategory']);

    Route::post('/admin/product-subcategory', [ProductCategoryController::class, 'storeSubcategory']);
    Route::put('/admin/product-subcategory/{id}', [ProductCategoryController::class, 'updateSubcategory']);
    Route::delete('/admin/product-subcategory/{id}', [ProductCategoryController::class, 'deleteSubcategory']);
});

Route::get('/admin/product-categories', [ProductCategoryController::class, 'index']);

Route::get('/admin/product-subcategories/{categoryId}', [ProductCategoryController::class, 'subcategoriesByCategory']);

