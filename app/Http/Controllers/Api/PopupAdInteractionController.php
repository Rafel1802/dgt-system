<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PopupAd;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PopupAdInteractionController extends Controller
{
    public function check(Request $request)
    {
        $user = $request->user();

        $activeAds = PopupAd::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('start_time')
                      ->orWhere('start_time', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('end_time')
                      ->orWhere('end_time', '>=', now());
            })
            ->with(['users' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }])
            ->orderBy('id', 'desc')
            ->get();

        $activeAd = $activeAds->first(function ($ad) {
            // Check user interaction (already eager loaded)
            $pivot = $ad->users->first()?->pivot;

            // If user clicked it, they should never see it again
            if ($pivot && $pivot->is_clicked) {
                return false;
            }

            // If they have seen it, check if interval has passed
            if ($pivot && $pivot->last_shown_at) {
                $lastShown = Carbon::parse($pivot->last_shown_at);
                $interval = max(1, (int) ($ad->interval_minutes ?: 5));
                if (now()->lessThan($lastShown->copy()->addMinutes($interval))) {
                    return false; // Interval not passed yet
                }
            }

            return true;
        });

        if ($activeAd) {
            return response()->json([
                'ad' => [
                    'id' => $activeAd->id,
                    'title' => $activeAd->title,
                    'image_url' => $activeAd->image_path ? asset('storage/' . $activeAd->image_path) : null,
                    'body_text' => $activeAd->body_text,
                    'button_text' => $activeAd->button_text,
                    'button_link' => $activeAd->button_link,
                    'notification_text' => $activeAd->notification_text,
                    'notification_icon' => $activeAd->notification_icon,
                    'interval_minutes' => (int) ($activeAd->interval_minutes ?: 5),
                ]
            ]);
        }

        return response()->json(['ad' => null]);
    }

    public function markShown(Request $request)
    {
        $request->validate(['ad_id' => 'required|exists:popup_ads,id']);
        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => 'unauthenticated'], 401);
        }
        
        $pivot = null;
        if (method_exists($user, 'popupAds')) {
            $pivot = $user->popupAds()->where('popup_ad_id', $request->ad_id)->first()?->pivot;
        } else {
            $pivot = \Illuminate\Support\Facades\DB::table('popup_ad_user')
                ->where('user_id', $user->id)
                ->where('popup_ad_id', $request->ad_id)
                ->first();
        }

        // If it's the very first time being shown to this user, send the system notification
        if (!$pivot) {
            $ad = PopupAd::find($request->ad_id);
            if ($ad && $ad->notification_text) {
                try {
                    \App\Support\InstantNotifier::send($user, new \App\Notifications\GenericDatabaseNotification([
                        'icon' => $ad->notification_icon ?? '📢',
                        'title' => $ad->title,
                        'body' => $ad->notification_text,
                        'url' => $ad->button_link ?? '#',
                        'module' => 'announcement',
                    ]));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Failed sending instant notification for popup ad: ' . $e->getMessage());
                }
            }
        }

        if (method_exists($user, 'popupAds')) {
            $user->popupAds()->syncWithoutDetaching([
                $request->ad_id => [
                    'last_shown_at' => now(),
                ]
            ]);
        } else {
            \Illuminate\Support\Facades\DB::table('popup_ad_user')->updateOrInsert(
                ['popup_ad_id' => $request->ad_id, 'user_id' => $user->id],
                ['last_shown_at' => now(), 'updated_at' => now()]
            );
        }

        return response()->json(['status' => 'success']);
    }

    public function markClicked(Request $request)
    {
        $request->validate(['ad_id' => 'required|exists:popup_ads,id']);
        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => 'unauthenticated'], 401);
        }
        
        if (method_exists($user, 'popupAds')) {
            $user->popupAds()->syncWithoutDetaching([
                $request->ad_id => [
                    'is_clicked' => true,
                    'last_shown_at' => now(),
                ]
            ]);
        } else {
            \Illuminate\Support\Facades\DB::table('popup_ad_user')->updateOrInsert(
                ['popup_ad_id' => $request->ad_id, 'user_id' => $user->id],
                ['is_clicked' => true, 'last_shown_at' => now(), 'updated_at' => now()]
            );
        }

        return response()->json(['status' => 'success']);
    }
}
