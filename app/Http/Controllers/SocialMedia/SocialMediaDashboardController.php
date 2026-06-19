<?php

namespace App\Http\Controllers\SocialMedia;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaClass;
use App\Models\SocialMediaPost;

class SocialMediaDashboardController extends Controller
{
    public function index()
    {
        $user    = auth()->user();
        $isAdmin = $user->hasAnyRole(['super-admin', 'admin-digital']);
        $isQc    = $user->hasAnyRole(['super-admin', 'admin-digital', 'social_qc']);

        // Build class query based on role
        $classQuery = SocialMediaClass::with(['activeItems', 'assignedUsers'])
            ->withCount('activeItems as items_count');

        if (!$isQc) {
            // social_user: only assigned classes
            $classQuery->whereHas('assignedUsers', fn ($q) => $q->where('user_id', $user->id));
        }

        $classes = $classQuery->orderBy('name')->get();

        // Compute summary stats per class
        $classesWithStats = $classes->map(function (SocialMediaClass $class) use ($user, $isQc) {
            $postsQuery = $class->posts();

            if (!$isQc) {
                $postsQuery->where('user_id', $user->id);
            }

            $posts = $postsQuery->get();

            return [
                'model'       => $class,
                'total_items' => $class->items_count,
                'total_posts' => $posts->count(),
                'completed'   => $posts->where('is_completed', true)->count(),
                'pending'     => $posts->where('is_completed', false)->count(),
                'qc_checked'  => $posts->where('is_checked', true)->count(),
                'qc_pending'  => $posts->where('is_completed', true)->where('is_checked', false)->count(),
            ];
        });

        // Global KPI
        $globalStats = [
            'total_classes' => $classes->count(),
            'total_items'   => $classes->sum('items_count'),
            'total_posts'   => $classesWithStats->sum('total_posts'),
            'completed'     => $classesWithStats->sum('completed'),
            'pending'       => $classesWithStats->sum('pending'),
            'qc_checked'    => $classesWithStats->sum('qc_checked'),
            'qc_pending'    => $classesWithStats->sum('qc_pending'),
        ];

        return view('social-media.dashboard', compact(
            'classesWithStats', 'globalStats', 'isAdmin', 'isQc', 'user'
        ));
    }
}
