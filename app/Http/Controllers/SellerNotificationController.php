<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SellerNotificationController extends Controller
{
    /**
     * Return notifications for the logged-in seller
     */
    public function index(Request $request)
    {
        $seller = $request->user('sanctum'); // explicitly use Sanctum guard
        if (!$seller) {
            Log::warning('Seller not authenticated while fetching notifications.');
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        try {
            $notifications = $seller->notifications()
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($notif) {
                    return [
                        'id' => $notif->id,
                        'type' => $notif->type,
                        'data' => is_string($notif->data) ? json_decode($notif->data, true) : $notif->data,
                        'read_at' => $notif->read_at,
                        'created_at' => $notif->created_at,
                    ];
                });

            Log::info("Fetched ".count($notifications)." notifications for seller ID {$seller->id}");

            return response()->json($notifications);
        } catch (\Exception $e) {
            Log::error('Error fetching seller notifications: '.$e->getMessage());
            return response()->json(['error' => 'Failed to fetch notifications'], 500);
        }
    }
}
