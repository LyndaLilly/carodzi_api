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

    /**
 * Mark a single notification as read
 */
public function markAsRead(Request $request, $id)
{
    $seller = $request->user('sanctum');
    if (!$seller) {
        Log::warning("Seller not authenticated while marking notification as read.");
        return response()->json(['error' => 'Unauthenticated'], 401);
    }

    try {
        $notification = $seller->notifications()->where('id', $id)->first();

        if (!$notification) {
            return response()->json(['error' => 'Notification not found'], 404);
        }

        $notification->markAsRead();

        Log::info("Notification ID {$id} marked as read for seller ID {$seller->id}");

        return response()->json(['message' => 'Notification marked as read']);
    } catch (\Exception $e) {
        Log::error("Error marking notification as read: ".$e->getMessage());
        return response()->json(['error' => 'Failed to mark notification'], 500);
    }
}

/**
 * Optional: mark all notifications as read
 */
public function markAllAsRead(Request $request)
{
    $seller = $request->user('sanctum');
    if (!$seller) {
        Log::warning("Seller not authenticated while marking all notifications as read.");
        return response()->json(['error' => 'Unauthenticated'], 401);
    }

    try {
        $seller->unreadNotifications->markAsRead();
        Log::info("All notifications marked as read for seller ID {$seller->id}");
        return response()->json(['message' => 'All notifications marked as read']);
    } catch (\Exception $e) {
        Log::error("Error marking all notifications as read: ".$e->getMessage());
        return response()->json(['error' => 'Failed to mark notifications'], 500);
    }
}

}
