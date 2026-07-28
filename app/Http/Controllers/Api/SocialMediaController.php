<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsApiResponses;
use App\Http\Controllers\Controller;
use App\Models\SocialMediaAnalytic;
use App\Models\SocialMediaClass;
use App\Models\SocialMediaPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialMediaController extends Controller
{
    use FormatsApiResponses;

    public function dashboard(Request $request): JsonResponse
    {
        $classes = SocialMediaClass::with(['items', 'assignedUsers:id,name,avatar'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'classes' => $classes,
            'stats' => [
                'classes' => $classes->count(),
                'items' => $classes->sum(fn ($class) => $class->items->count()),
                'posts_today' => SocialMediaPost::whereDate('created_at', today())->count(),
                'analytics' => SocialMediaAnalytic::count(),
            ],
            'recent_posts' => SocialMediaPost::with(['socialMediaClass:id,name', 'socialMediaItem:id,name', 'user:id,name,avatar'])
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }
}
