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
        $seller = $request->user('sanctum'); // 🔹 explicitly use Sanctum guard
        if (!$seller) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

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

        return response()->json($notifications);
    }

    /**
     * Optional: mark notification as read
     */
    public function markAsRead(Request $request, $id)
    {
        $seller = $request->user('sanctum');
        if (!$seller) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $notification = $seller->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
            return response()->json(['message' => 'Notification marked as read']);
        }

        return response()->json(['error' => 'Notification not found'], 404);
    }
}
