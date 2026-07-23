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

        // Use the raw notification UUID as `id` — must match InstantNotifier /
        // Pusher payload ids so the frontend dedupe (localStorage shown-ids)
        // treats live push + poll refresh as the same card, not two.
        $notifications = $this->scopeToUserModules($user->notifications(), $modules)
            ->take(30)->get()->map(fn($n) => [
                'id'         => (string) $n->id,
                'data'       => $n->data,
                'read_at'    => $n->read_at,
                'time_ago'   => $n->created_at->format('M j, Y, g:i A'),
                'created_at' => $n->created_at,
            ]);

        // Every ActivityLog row with module='kanban' is board/Kanban activity
        // (see CardController::logCardActivity()) — this whole query used to
        // run unconditionally for every user, with no scoping at all, which
        // is what let board activity from other teams show up in everyone's
        // bell regardless of role. Only fetch it for users whose role set
        // actually grants them the 'digital' module.
        $activities = in_array('digital', $modules, true)
            ? \App\Models\ActivityLog::with('user')
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
                ])
            : collect();

        $merged = $notifications->concat($activities)->sortByDesc('created_at')->take(30)->values();

        return response()->json([
            'notifications' => $merged,
            'unread_count' => $this->scopeToUserModules($user->unreadNotifications(), $modules)->count(),
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
        $this->scopeToUserModules($user->unreadNotifications(), $user->notificationModules())->get()->markAsRead();
        return response()->json(['success' => true]);
    }
}
