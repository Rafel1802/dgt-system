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

        // Build class query based on role, using withCount for DB-level aggregation instead of loading models
        $classQuery = SocialMediaClass::with(['activeItems', 'assignedUsers'])
            ->withCount('activeItems as items_count')
            ->withCount([
                'posts as total_posts' => function ($query) use ($user, $isQc) {
                    if (!$isQc) $query->where('user_id', $user->id);
                },
                'posts as completed' => function ($query) use ($user, $isQc) {
                    if (!$isQc) $query->where('user_id', $user->id);
                    $query->where('is_completed', true);
                },
                'posts as pending' => function ($query) use ($user, $isQc) {
                    if (!$isQc) $query->where('user_id', $user->id);
                    $query->where('is_completed', false);
                },
                'posts as qc_checked' => function ($query) use ($user, $isQc) {
                    if (!$isQc) $query->where('user_id', $user->id);
                    $query->where('is_checked', true);
                },
                'posts as qc_pending' => function ($query) use ($user, $isQc) {
                    if (!$isQc) $query->where('user_id', $user->id);
                    $query->where('is_completed', true)->where('is_checked', false);
                }
            ]);

        $canSeeAllClasses = $user->hasAnyRole(['super-admin', 'admin-digital', 'social_qc', 'boss', 'digital-team']);

        $classes = $classQuery->orderBy('position')->orderBy('name')->get();

        // Compute summary stats per class directly from counts
        $classesWithStats = $classes->map(function (SocialMediaClass $class) {
            return [
                'model'       => $class,
                'total_items' => $class->items_count,
                'total_posts' => $class->total_posts ?? 0,
                'completed'   => $class->completed ?? 0,
                'pending'     => $class->pending ?? 0,
                'qc_checked'  => $class->qc_checked ?? 0,
                'qc_pending'  => $class->qc_pending ?? 0,
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
