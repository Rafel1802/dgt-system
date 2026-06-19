<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $user = auth()->user();
        
        $notifications = $user->notifications()->take(30)->get()->map(fn($n) => [
            'id'       => 'notif_' . $n->id,
            'data'     => $n->data,
            'read_at'  => $n->read_at,
            'time_ago' => $n->created_at->format('M j, Y, g:i A'),
            'created_at' => $n->created_at,
        ]);

        $activities = \App\Models\ActivityLog::with('user')
            ->where('module', 'kanban')
            ->latest('created_at')
            ->take(30)
            ->get()
            ->map(fn($act) => [
                'id'       => 'act_' . $act->id,
                'data'     => [
                    'actor_name'   => $act->user?->name ?? 'System',
                    'actor_avatar' => $act->user?->avatar_url,
                    'action'       => strip_tags($act->description),
                ],
                'read_at'  => now(), // Activities don't have unread state
                'time_ago' => $act->created_at->format('M j, Y, g:i A'),
                'created_at' => $act->created_at,
            ]);

        $merged = $notifications->concat($activities)->sortByDesc('created_at')->take(30)->values();

        return response()->json([
            'notifications' => $merged,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /** Mark a notification as read */
    public function markAsRead($id): JsonResponse
    {
        if (str_starts_with($id, 'act_')) return response()->json(['success' => true]);
        
        $id = str_replace('notif_', '', $id);
        $notification = auth()->user()->notifications()->find($id);
        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json(['success' => true]);
    }

    /** Mark all notifications as read */
    public function markAllAsRead(): JsonResponse
    {
        auth()->user()->unreadNotifications->markAsRead();
        return response()->json(['success' => true]);
    }
}
