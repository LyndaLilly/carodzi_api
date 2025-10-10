<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SellerNotificationController extends Controller
{
    /**
     * Return notifications for the logged-in seller
     */
    public function index(Request $request)
    {
        $seller = $request->user(); // assuming seller is authenticated via Sanctum
        $notifications = $seller->notifications()->orderBy('created_at', 'desc')->get();

        return response()->json($notifications);
    }
}
