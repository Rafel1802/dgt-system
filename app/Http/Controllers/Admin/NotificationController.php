<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * This controller — not Board\NotificationController — is the one that
 * actually answers GET /notifications: routes/web.php registers both under
 * the identical literal URI, and Laravel's route collection keys routes by
 * method+URI, so the later registration (this one, line ~654) silently
 * overwrites the earlier one in the lookup table. Any future notification
 * scoping/module work must be verified against THIS controller specifically.
 */
class NotificationController extends Controller
{
    /**
     * Scope a notifications() / unreadNotifications() relation query to only
     * the modules this user is allowed to see — see User::notificationModules().
     * Rows with no 'module' key (saved before this scoping existed) are
     * always shown.
     */
    private function scopeToUserModules($query, $modules)
    {
        return $query->where(function (Builder $q) use ($modules) {
            $q->whereNull('data->module')->orWhereIn('data->module', $modules);
        });
    }

    public function index(): JsonResponse
    {
        $user = auth()->user();
        $modules = $user->notificationModules();

        // Keep only the latest 100 notifications to prevent DB bloat
        // Add a secondary sort on `id` to guarantee a deterministic order 
        // when multiple notifications share the exact same `created_at` timestamp.
        // We MUST scope this to the user's modules as well, otherwise deleting
        // global notifications causes the scoped query below to slide older
        // notifications into its top 100, which the frontend incorrectly sees as "new".
        $excessIds = $this->scopeToUserModules($user->notifications()->orderBy('id'), $modules)
            ->skip(100)->take(50)->pluck('id');
        if ($excessIds->isNotEmpty()) {
            $user->notifications()->whereIn('id', $excessIds)->delete();
        }

        // Use the raw notification UUID as `id` — must match InstantNotifier /
        // Pusher payload ids so the frontend dedupe (localStorage shown-ids)
        // treats live push + poll refresh as the same card, not two.
        $notifications = $this->scopeToUserModules($user->notifications()->orderBy('id'), $modules)
            ->take(100)->get()->map(fn($n) => [
                'id'         => (string) $n->id,
                'data'       => $n->data,
                'read_at'    => $n->read_at,
                'time_ago'   => $n->created_at->format('M j, Y, g:i A'),
                'created_at' => $n->created_at,
            ]);

        $counts = $this->scopeToUserModules($user->unreadNotifications(), $modules)
            ->toBase()
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.module')) as module, count(*) as aggregate")
            ->groupBy('module')
            ->pluck('aggregate', 'module');

        $unreadByModule = [
            'boards' => $counts['kanban'] ?? 0,
            'smm' => $counts['social-media'] ?? 0,
            'websites' => $counts['websites'] ?? 0,
        ];

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $counts->sum(),
            'unread_by_module' => $unreadByModule,
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

    /** Mark all notifications as read — scoped the same way as index(). */
    public function markAllAsRead(): JsonResponse
    {
        $user = auth()->user();
        $this->scopeToUserModules($user->unreadNotifications(), $user->notificationModules())
            ->update(['read_at' => now()]);
            
        return response()->json(['success' => true]);
    }
}
