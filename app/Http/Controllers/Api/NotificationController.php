<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsApiResponses;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    use FormatsApiResponses;

    public function index(Request $request): JsonResponse
    {
        $limit = min((int) $request->integer('limit', 30), 100);
        $query = $request->boolean('unread')
            ? $request->user()->unreadNotifications()
            : $request->user()->notifications();

        return response()->json([
            'notifications' => $query->latest()->limit($limit)->get()->map(fn ($notification) => $this->format($notification))->values(),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function unread(Request $request): JsonResponse
    {
        return $this->index($request->merge(['unread' => true]));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'latest_id' => optional($request->user()->unreadNotifications()->latest()->first())->id,
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->find($id);

        if (! $notification) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        $notification->markAsRead();

        return response()->json(['message' => 'Notification marked as read.', 'notification' => $this->format($notification->fresh())]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    public function format(DatabaseNotification $notification): array
    {
        return $this->normalizeNotification($notification);
    }
}
