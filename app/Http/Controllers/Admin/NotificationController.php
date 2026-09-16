<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PinnedNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $excessIds = $this->scopeToUserModules($user->notifications()->latest('created_at')->orderByDesc('id'), $modules)
            ->skip(100)->take(50)->pluck('id');
        if ($excessIds->isNotEmpty()) {
            $user->notifications()->whereIn('id', $excessIds)->delete();
        }

        // Active pinned notifications (global for all users, with auto-expiration check)
        $activePinned = PinnedNotification::active()->with('pinner')->latest('pinned_at')->get();
        $pinnedList = $activePinned->map(function ($p) {
            $data = is_array($p->data) ? $p->data : (json_decode($p->data ?? '[]', true) ?: []);
            return [
                'id'                     => 'pinned_' . $p->id,
                'pinned_id'              => $p->id,
                'source_notification_id' => $p->notification_id,
                'is_pinned'              => true,
                'expires_at'             => $p->expires_at?->toISOString(),
                'expires_at_human'       => $p->expires_at ? $p->expires_at->diffForHumans() : null,
                'pinned_by_name'         => $p->pinner?->name ?? 'Admin',
                'data'                   => array_merge($data, [
                    'actor_name'   => $p->actor_name ?: ($p->pinner?->name ?? 'System Announcement'),
                    'actor_avatar' => $p->actor_avatar ?: ($p->pinner?->avatar_url ?? null),
                    'description'  => $p->message,
                    'message'      => $p->message,
                    'title'        => $p->title,
                    'link'         => $p->link,
                    'board_name'   => $p->board_name,
                    'card_title'   => $p->card_title,
                    'card_id'      => $p->card_id,
                    'is_pinned'    => true,
                ]),
                'read_at'                => null,
                'time_ago'               => $p->pinned_at->format('M j, Y, g:i A'),
                'created_at'             => $p->pinned_at->toISOString(),
            ];
        });

        $pinnedSourceIds = $activePinned->pluck('notification_id')->filter()->toArray();

        // Use the raw notification UUID as `id` — must match InstantNotifier /
        // Pusher payload ids so the frontend dedupe (localStorage shown-ids)
        // treats live push + poll refresh as the same card, not two.
        $userNotifications = $this->scopeToUserModules($user->notifications()->latest('created_at')->orderByDesc('id'), $modules)
            ->take(100)->get()
            ->filter(fn($n) => !in_array((string) $n->id, $pinnedSourceIds, true))
            ->map(fn($n) => [
                'id'         => (string) $n->id,
                'data'       => $n->data,
                'read_at'    => $n->read_at,
                'time_ago'   => $n->created_at->format('M j, Y, g:i A'),
                'created_at' => $n->created_at,
            ]);

        // Prepend pinned notifications to the top for all users
        $allNotifications = $pinnedList->concat($userNotifications)->values();

        // Instead of a heavy JSON_EXTRACT group by on the entire unread set (which can cause massive table scans),
        // we'll just check if they have unread notifications, and if so, fetch a limited set to tally.
        $unreadQuery = $this->scopeToUserModules($user->unreadNotifications(), $modules);
        $totalUnread = $unreadQuery->count() + $pinnedList->count();
        
        $unreadByModule = [
            'Kanban' => 0,
            'SMM' => 0,
            'Websites' => 0,
        ];

        if ($totalUnread > 0) {
            // Only pull the latest 50 unread to tally, saving the DB from scanning thousands of rows
            $recentUnread = $this->scopeToUserModules($user->unreadNotifications()->latest('created_at'), $modules)
                ->take(50)
                ->pluck('data');
                
            $counts = collect($recentUnread)->map(function ($data) {
                return is_string($data) ? json_decode($data, true) : $data;
            })->countBy(function ($data) {
                return $data['module'] ?? 'kanban';
            });
            
            $unreadByModule = [
                'Kanban' => $counts->get('kanban', 0) + ($totalUnread > 50 ? $totalUnread - 50 : 0), // pad the remainder to kanban
                'SMM' => $counts->get('social-media', 0),
                'Websites' => $counts->get('websites', 0),
            ];
        }

        return response()->json([
            'notifications'    => $allNotifications,
            'pinned_ids'       => $pinnedSourceIds,
            'can_pin'          => $user->canPinNotifications(),
            'unread_count'     => $totalUnread,
            'unread_by_module' => $unreadByModule,
        ]);
    }

    /** Pin a notification to the top for all users with optional scheduled expiration */
    public function pin(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user->canPinNotifications()) {
            return response()->json(['error' => 'Unauthorized to pin notifications.'], 403);
        }

        $validated = $request->validate([
            'notification_id'   => ['nullable', 'string'],
            'title'             => ['nullable', 'string', 'max:255'],
            'message'           => ['nullable', 'string', 'max:2000'],
            'link'              => ['nullable', 'string', 'max:1000'],
            'duration'          => ['nullable', 'string'], // 'never', '1h', '12h', '24h', '3d', '7d', 'custom'
            'custom_expires'    => ['nullable', 'date'],
            'custom_expires_at' => ['nullable', 'date'],
            'actor_name'        => ['nullable', 'string', 'max:150'],
            'actor_avatar'      => ['nullable', 'string', 'max:1000'],
            'data'              => ['nullable', 'array'],
        ]);

        $expiresAt = null;
        $duration = $validated['duration'] ?? 'never';
        switch ($duration) {
            case '1h':
                $expiresAt = now()->addHour();
                break;
            case '12h':
                $expiresAt = now()->addHours(12);
                break;
            case '24h':
            case '1d':
                $expiresAt = now()->addDay();
                break;
            case '3d':
                $expiresAt = now()->addDays(3);
                break;
            case '7d':
            case '1w':
                $expiresAt = now()->addDays(7);
                break;
            case 'custom':
                $customDate = $validated['custom_expires_at'] ?? ($validated['custom_expires'] ?? null);
                if (!empty($customDate)) {
                    $expiresAt = Carbon::parse($customDate);
                }
                break;
            default:
                $expiresAt = null;
                break;
        }

        $notificationId = $validated['notification_id'] ?? null;
        $actorName = $validated['actor_name'] ?? $user->name;
        $actorAvatar = $validated['actor_avatar'] ?? $user->avatar_url;
        $boardName = null;
        $cardTitle = null;
        $cardId = null;
        $link = $validated['link'] ?? null;
        $title = $validated['title'] ?? null;
        $message = $validated['message'] ?? '';
        $data = $validated['data'] ?? [];

        if ($notificationId) {
            $cleanId = str_replace(['notif_', 'pinned_'], '', $notificationId);
            $source = DB::table('notifications')->where('id', $cleanId)->first();
            if ($source) {
                $sourceData = json_decode($source->data, true) ?: [];
                $data = array_merge($sourceData, $data);
                $actorName = $sourceData['actor_name'] ?? $actorName;
                $actorAvatar = $sourceData['actor_avatar'] ?? $actorAvatar;
                $boardName = $sourceData['board_name'] ?? null;
                $cardTitle = $sourceData['card_title'] ?? null;
                $cardId = $sourceData['card_id'] ?? null;
                $link = $link ?: ($sourceData['link'] ?? null);
                if (empty($message)) {
                    $message = $sourceData['description'] ?? ($sourceData['message'] ?? 'Important update');
                }
            }
        }

        if (empty($message)) {
            return response()->json(['error' => 'Notification content cannot be empty.'], 422);
        }

        $pinned = PinnedNotification::create([
            'notification_id' => $notificationId ? str_replace(['notif_', 'pinned_'], '', $notificationId) : null,
            'actor_name'      => $actorName,
            'actor_avatar'    => $actorAvatar,
            'title'           => $title,
            'message'         => $message,
            'link'            => $link,
            'board_name'      => $boardName,
            'card_title'      => $cardTitle,
            'card_id'         => $cardId,
            'data'            => $data,
            'pinned_by'       => $user->id,
            'pinned_at'       => now(),
            'expires_at'      => $expiresAt,
            'is_active'       => true,
        ]);

        return response()->json([
            'success'   => true,
            'message'   => 'Notification pinned to top for all users.',
            'pinned_id' => $pinned->id,
        ]);
    }

    /** Unpin a notification */
    public function unpin($id): JsonResponse
    {
        $user = auth()->user();
        if (!$user->canPinNotifications()) {
            return response()->json(['error' => 'Unauthorized to unpin notifications.'], 403);
        }

        $cleanId = str_replace(['pinned_', 'notif_'], '', (string) $id);
        
        $pinned = PinnedNotification::where('id', $cleanId)
            ->orWhere('notification_id', $cleanId)
            ->first();

        if ($pinned) {
            $pinned->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification unpinned successfully.',
        ]);
    }

    /** Mark a notification as read */
    public function markAsRead($id): JsonResponse
    {
        if (str_starts_with($id, 'act_') || str_starts_with($id, 'pinned_')) {
            return response()->json(['success' => true]);
        }

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
