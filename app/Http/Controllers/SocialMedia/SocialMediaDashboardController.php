<?php

namespace App\Http\Controllers\SocialMedia;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaClass;
use App\Models\SocialMediaPost;

class SocialMediaDashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $canManageClasses = $user->hasAnyRole(['super-admin', 'admin-digital', 'social_qc']);

        $classes = SocialMediaClass::with(['activeItems'])
            ->withCount('activeItems as items_count')
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return view('social-media.dashboard', compact('classes', 'canManageClasses', 'user'));
    }
}
