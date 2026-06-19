<?php

namespace App\Http\Controllers\Board;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get the authenticated user's notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $limit = $request->query('limit', 15);
        $user = auth()->user();

        $unreadCount = $user->unreadNotifications()->count();
        $notifications = $user->notifications()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function ($n) {
                return [
                    'id'         => $n->id,
                    'data'       => $n->data,
                    'read_at'    => $n->read_at ? $n->read_at->toIso8601String() : null,
                    'created_at' => $n->created_at ? $n->created_at->toIso8601String() : null,
                    'time_ago'   => $n->created_at ? $n->created_at->format('M j, Y, g:i A') : 'just now',
                ];
            });

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => $unreadCount,
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(string $id): JsonResponse
    {
        $notification = auth()->user()->notifications()->find($id);

        if ($notification) {
            $notification->markAsRead();
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'error' => 'Notification not found'], 404);
    }

    /**
     * Mark all unread notifications as read.
     */
    public function markAllAsRead(): JsonResponse
    {
        auth()->user()->unreadNotifications->markAsRead();
        return response()->json(['success' => true]);
    }
}
