<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsApiResponses;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    use FormatsApiResponses;

    /**
     * Scope a notifications() / unreadNotifications() relation query to only
     * the modules this user is allowed to see — CRM staff shouldn't see
     * digital/board notifications and vice versa (see
     * User::notificationModules()). Rows with no 'module' key (saved before
     * this scoping existed) are always shown. Untyped param/return since
     * this accepts both a MorphMany relation and a plain query builder.
     */
    private function scopeToUserModules($query, Request $request)
    {
        $modules = $request->user()->notificationModules();

        return $query->where(function (Builder $q) use ($modules) {
            $q->whereNull('data->module')->orWhereIn('data->module', $modules);
        });
    }

    public function index(Request $request): JsonResponse
    {
        $limit = min((int) $request->integer('limit', 30), 100);
        $query = $request->boolean('unread')
            ? $request->user()->unreadNotifications()
            : $request->user()->notifications();
        $this->scopeToUserModules($query, $request);

        return response()->json([
            'notifications' => $query->latest()->limit($limit)->get()->map(fn ($notification) => $this->format($notification))->values(),
            'unread_count' => $this->scopeToUserModules($request->user()->unreadNotifications(), $request)->count(),
        ]);
    }

    public function unread(Request $request): JsonResponse
    {
        return $this->index($request->merge(['unread' => true]));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $this->scopeToUserModules($request->user()->unreadNotifications(), $request)->count(),
            'latest_id' => optional($this->scopeToUserModules($request->user()->unreadNotifications(), $request)->latest()->first())->id,
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
        $this->scopeToUserModules($request->user()->unreadNotifications(), $request)->get()->markAsRead();

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    public function format(DatabaseNotification $notification): array
    {
        return $this->normalizeNotification($notification);
    }
}
