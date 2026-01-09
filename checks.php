<?php

namespace App\Http\Controllers;

use App\Helpers\ExpoPush;
use App\Models\DirectInquiry;
use App\Models\Order;
use App\Models\ProductReview;
use App\Models\ProductUpload;
use App\Notifications\NewOrderNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class OrderController extends Controller
{
    /**
     * Store a new order (offline / bitcoin only).
     * Paystack orders are created after payment verification.
     */
   

    /**
     * Initialize Paystack payment
     * Metadata is sent with transaction; order is created after payment success.
     */
   

    /**
     * Paystack callback - create order after successful payment
     */
   

    /**
     * Upload bitcoin proof
     */
 
    /**
     * Helper function to notify seller via push and Laravel Notification
     */
    

    // --- Other methods like index(), show(), buyerOrders(), sellerOrdersSummary(), etc.
    // can remain the same as in your current controller.
}
