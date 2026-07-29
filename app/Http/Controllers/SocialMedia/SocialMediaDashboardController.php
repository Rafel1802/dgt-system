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
        $canManageClasses = $user->hasAnyRole(['super-admin', 'admin-digital', 'social_qc']);
        $isQc             = $user->hasAnyRole(['super-admin', 'admin-digital', 'social_admin', 'social_qc', 'boss']);

        // Build class query based on role
        $classQuery = SocialMediaClass::with(['activeItems', 'assignedUsers', 'posts' => function ($query) use ($user, $isQc) {
            if (!$isQc) {
                $query->where('user_id', $user->id);
            }
        }])->withCount('activeItems as items_count');

        $canSeeAllClasses = $user->hasAnyRole(['super-admin', 'admin-digital', 'social_qc', 'boss', 'digital-team']);
        // Remove the restriction so everyone sees all classes (unassigned users will see them in view-only mode)

        $classes = $classQuery->orderBy('name')->get();

        // Compute summary stats per class
        $classesWithStats = $classes->map(function (SocialMediaClass $class) {
            // Posts are already eager-loaded and filtered based on the role
            $posts = $class->posts;

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
            'classesWithStats', 'globalStats', 'canManageClasses', 'isQc', 'user'
        ));
    }
}
