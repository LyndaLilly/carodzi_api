<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\BuyerProfileController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\DirectInquiryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OtherProfileController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\ProductUploadController;
use App\Http\Controllers\ProfessionalProfileController;
use App\Http\Controllers\PromoteController;
use App\Http\Controllers\PublicSellerController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\SellerNotificationController;
use App\Http\Controllers\WishController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

// Seller routes (requires auth)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/promote', [PromoteController::class, 'store']);
});

Route::post('/promotions/paystack/initialize', [PromoteController::class, 'initializePaystackPayment'])->middleware('auth:sanctum');

Route::get('/paystack/callback', [PromoteController::class, 'handlePaystackCallback']);
Route::post('/paystack/webhook', [PromoteController::class, 'handlePaystackWebhook']);
Route::get('/crypto/price', [PromoteController::class, 'getCryptoPrice']);
Route::middleware('auth:sanctum')->get('/seller/promote/check', [PromoteController::class, 'checkActive']);

// Admin route to approve crypto promotion (protect with admin middleware)
Route::post('/promote/{id}/approve', [PromoteController::class, 'approve']);
Route::get('/promotions/expire', [PromoteController::class, 'expirePromotions']);

// 🟢 Public route to get featured sellers
Route::get('/featured-sellers', [PromoteController::class, 'featured']);

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

Route::middleware('auth:sanctum')->group(function () {
    // Update product (expects product_id in request body + _method=PATCH if using FormData)
    Route::patch('/products/update', [ProductUploadController::class, 'updateProduct']);

    // Delete product (expects product_id in request body)
    Route::post('/products/delete', [ProductUploadController::class, 'deleteProduct']);
});

Route::get('/products', [ProductUploadController::class, 'getAllProductsForBuyers']);
Route::get('/products/most-viewed', [ProductUploadController::class, 'getMostViewedProducts']);
Route::get('/services/most-viewed', [ProductUploadController::class, 'getMostViewedServices']);
Route::get('/most-viewed', [ProductUploadController::class, 'getMostViewedAll']);

Route::get('/products/{id}/view', [ProductUploadController::class, 'recordProductView']);

Route::get('/products/{id}/recommended', [ProductUploadController::class, 'getRecommended']);
Route::get('/products/{id}', [ProductUploadController::class, 'getSingleProduct']);

Route::get('/search', [ProductUploadController::class, 'search']);
Route::get('/subcategory/{id}', [ProductCategoryController::class, 'showSubcategory']);

Route::get('/public-sellers', [PublicSellerController::class, 'index']);     // list
Route::get('/public-sellers/{id}', [PublicSellerController::class, 'show']); // single
Route::get('/homepage-sellers', [PublicSellerController::class, 'homepageSellers']);
Route::get('/public-seller-search', [PublicSellerController::class, 'search']);
Route::get('/sellers/{id}/view', [SellerController::class, 'viewSeller']);

Route::middleware('auth:sanctum')->get('/seller/notifications', [SellerNotificationController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/seller/notifications/{id}/read', [SellerNotificationController::class, 'markAsRead']);
    Route::post('/seller/notifications/read-all', [SellerNotificationController::class, 'markAllAsRead']);
});

// 🟢 Product Reviews
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/reviews', [ProductReviewController::class, 'store']);
});

Route::get('/reviews/product/{productId}', [ProductReviewController::class, 'getProductReviews']);
Route::get('/reviews/product/{productId}/average', [ProductReviewController::class, 'getAverageRating']);
Route::get('/reviews/seller/{sellerId}/average', [ProductReviewController::class, 'getSellerAverageRating']);
Route::get('/reviews/seller/{sellerId}/service-average', [ProductReviewController::class, 'getSellerServiceAverage']);

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

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::put('/cart-update/{id}', [CartController::class, 'update']);
    Route::delete('/cart-delete/{id}', [CartController::class, 'destroy']);
    Route::delete('/cart-clear', [CartController::class, 'clear']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/wish', [WishController::class, 'index']);
    Route::post('/wish', [WishController::class, 'store']);
    Route::put('/wish-update/{id}', [WishController::class, 'update']);
    Route::delete('/wish-delete/{id}', [WishController::class, 'destroy']);
    Route::delete('/wish-clear', [WishController::class, 'clear']);
});

// 🧾 Orders
Route::middleware('auth:buyer')->group(function () {
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::get('/buyer/orders', [OrderController::class, 'buyerOrders']);
      Route::post('/orders/{order}/verify', [OrderController::class, 'verifyPayment']);
});

Route::post('/order/paystack/init', [OrderController::class, 'paystackInit'])->middleware('auth:sanctum');
Route::get('/order/paystack/callback', [OrderController::class, 'orderPaystackCallback']);


Route::middleware('auth:sanctum')->get('/buyer/orders/{id}', [OrderController::class, 'buyerSingleOrder']);


Route::middleware('auth:sanctum')->group(function () {
    Route::post('/orders/{order}/bitcoin-proof', [OrderController::class, 'uploadBitcoinProof']);
});

Route::middleware('auth:sanctum')->get('/seller/orders/summary', [OrderController::class, 'sellerOrdersSummary']);

Route::middleware('auth:sanctum')->get('/seller/weekly-revenue', [OrderController::class, 'sellerWeeklyRevenue']);

// 📨 Direct Inquiry Routes
Route::post('/direct-inquiry', [DirectInquiryController::class, 'store']);

// Get all inquiries for a seller
Route::get('/seller/{sellerId}/inquiries', [DirectInquiryController::class, 'sellerInquiries']);

// Get all inquiries for a buyer
Route::get('/buyer/{buyerId}/inquiries', [DirectInquiryController::class, 'buyerInquiries']);

Route::middleware('auth:sanctum')->group(function () {
    Route::put('/seller/inquiries/update-status/{id}', [DirectInquiryController::class, 'updateStatus']);
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
    Route::get('/orders', [OrderController::class, 'index']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/seller/orders', [OrderController::class, 'sellerOrders']);
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
