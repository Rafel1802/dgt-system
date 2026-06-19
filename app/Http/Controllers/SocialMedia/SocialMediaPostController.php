<?php

namespace App\Http\Controllers\SocialMedia;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaClass;
use App\Models\SocialMediaPost;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SocialMediaPostController extends Controller
{
    /**
     * Show the Google Sheets-style table for a class on a given date.
     */
    public function show(Request $request, SocialMediaClass $class)
    {
        $user    = auth()->user();
        $isAdmin = $user->hasAnyRole(['super-admin', 'admin-digital']);
        $isQc    = $user->hasAnyRole(['super-admin', 'admin-digital', 'social_qc']);

        // Gate: social_user must be assigned to this class
        if (!$class->isVisibleTo($user)) {
            abort(403, 'You are not assigned to this class.');
        }

        $postDate = $request->input('date', now()->toDateString());

        try {
            $postDate = Carbon::parse($postDate)->toDateString();
        } catch (\Exception $e) {
            $postDate = now()->toDateString();
        }

        // Load all active items for this class
        $items = $class->activeItems()->get();

        // Determine which users to show posts for
        // Admin/QC see all users' posts; social_user sees only their own
        $viewUserId = null;
        if (!$isQc) {
            $viewUserId = $user->id;
        } elseif ($request->filled('user_id')) {
            $viewUserId = (int) $request->input('user_id');
        }

        // Load posts for this class + date (optionally filtered by user)
        $postsQuery = SocialMediaPost::with(['user', 'checker'])
            ->where('social_media_class_id', $class->id)
            ->where('post_date', $postDate);

        if ($viewUserId) {
            $postsQuery->where('user_id', $viewUserId);
        }

        $posts = $postsQuery->get()->keyBy(function ($p) use ($viewUserId) {
            return $viewUserId ? ($p->social_media_item_id . '_' . $p->user_id) : $p->social_media_item_id;
        });

        // Assigned users for filter dropdown (admin/QC only)
        $assignedUsers = $isQc ? $class->assignedUsers()->orderBy('name')->get() : collect();

        return view('social-media.table', compact(
            'class', 'items', 'posts', 'postDate', 'isAdmin', 'isQc', 'user',
            'assignedUsers', 'viewUserId'
        ));
    }

    /**
     * AJAX: Upsert a post row (create or update URL).
     * Used when user inputs a link or changes date.
     */
    public function storeOrUpdate(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'social_media_class_id' => 'required|exists:social_media_classes,id',
            'social_media_item_id'  => 'required|exists:social_media_items,id',
            'post_date'             => 'required|date',
            'post_url'              => 'nullable|url|max:2048',
            'optional_text'         => 'nullable|string|max:500',
        ]);

        $class = SocialMediaClass::findOrFail($validated['social_media_class_id']);

        if (!$class->isVisibleTo($user)) {
            return response()->json(['error' => 'You are not assigned to this class.'], 403);
        }

        $post = SocialMediaPost::where([
            'social_media_class_id' => $validated['social_media_class_id'],
            'social_media_item_id'  => $validated['social_media_item_id'],
            'user_id'               => $user->id,
            'post_date'             => $validated['post_date'],
        ])->first();

        if ($post && $post->is_checked) {
            return response()->json(['error' => 'This row is locked after QC approval.'], 403);
        }

        $post = SocialMediaPost::updateOrCreate(
            [
                'social_media_class_id' => $validated['social_media_class_id'],
                'social_media_item_id'  => $validated['social_media_item_id'],
                'user_id'               => $user->id,
                'post_date'             => $validated['post_date'],
            ],
            [
                'post_url'      => $validated['post_url'] ?? null,
                'optional_text' => $validated['optional_text'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'post_id' => $post->id,
            'post_url' => $post->post_url,
        ]);
    }

    /**
     * AJAX: Toggle is_completed.
     * User must have a post_url before marking complete.
     */
    public function markCompleted(Request $request, SocialMediaPost $post)
    {
        $user = auth()->user();

        if ($post->is_checked) {
            return response()->json(['error' => 'Locked after QC approval.'], 403);
        }

        if ($post->user_id !== $user->id && !$user->hasAnyRole(['super-admin', 'admin-digital'])) {
            return response()->json(['error' => 'You can only update your own posts.'], 403);
        }

        $validated = $request->validate(['is_completed' => 'required|boolean']);

        // Must have URL to mark complete
        if ($validated['is_completed'] && empty($post->post_url)) {
            return response()->json(['error' => 'Please enter a post link before marking complete.'], 422);
        }

        $post->update([
            'is_completed' => $validated['is_completed'],
            'completed_at' => $validated['is_completed'] ? now() : null,
        ]);

        return response()->json([
            'success'      => true,
            'is_completed' => $post->is_completed,
            'completed_at' => $post->completed_at?->format('d M Y H:i'),
        ]);
    }

    /**
     * AJAX: QC check/uncheck.
     * Only admin/QC roles can use this.
     */
    public function markChecked(Request $request, SocialMediaPost $post)
    {
        if (!$post->is_completed) {
            return response()->json(['error' => 'Cannot QC a post that is not yet completed.'], 422);
        }

        $validated = $request->validate(['is_checked' => 'required|boolean']);

        $post->update([
            'is_checked' => $validated['is_checked'],
            'checked_by' => $validated['is_checked'] ? auth()->id() : null,
            'checked_at' => $validated['is_checked'] ? now() : null,
        ]);

        return response()->json([
            'success'          => true,
            'is_checked'       => $post->is_checked,
            'qc_status_label'  => $post->fresh()->qc_status_label,
            'checker_name'     => $post->checker?->name ?? '',
            'checked_at'       => $post->checked_at?->format('d M Y H:i') ?? '',
        ]);
    }

    /**
     * Admin unlock a QC-checked post.
     */
    public function unlock(SocialMediaPost $post)
    {
        $post->update([
            'is_checked' => false,
            'checked_by' => null,
            'checked_at' => null,
        ]);

        return back()->with('success', 'Post unlocked for editing.');
    }
}
