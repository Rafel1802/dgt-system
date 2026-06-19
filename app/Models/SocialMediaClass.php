<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialMediaClass extends Model
{
    protected $fillable = ['name', 'description', 'icon', 'status', 'created_by'];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SocialMediaItem::class, 'social_media_class_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function activeItems(): HasMany
    {
        return $this->hasMany(SocialMediaItem::class, 'social_media_class_id')
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(SocialMediaPost::class, 'social_media_class_id');
    }

    /** Users assigned to this class */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'social_media_class_user')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isAssignedTo(User $user): bool
    {
        return $this->assignedUsers()->where('user_id', $user->id)->exists();
    }

    /**
     * Can the given user view this class?
     * Admins/QC see all; social_user sees only assigned ones.
     */
    public function isVisibleTo(User $user): bool
    {
        if ($user->hasAnyRole(['super-admin', 'admin-digital', 'social_qc'])) {
            return true;
        }
        return $this->isAssignedTo($user);
    }

    // ─── Summary Stats ────────────────────────────────────────────────────────

    public function summaryFor(?User $user = null): array
    {
        $query = $this->posts();

        if ($user && !$user->hasAnyRole(['super-admin', 'admin-digital', 'social_qc'])) {
            $query = $query->where('user_id', $user->id);
        }

        $posts = $query->get();

        return [
            'total_items'  => $this->activeItems()->count(),
            'total_posts'  => $posts->count(),
            'completed'    => $posts->where('is_completed', true)->count(),
            'pending'      => $posts->where('is_completed', false)->count(),
            'qc_checked'   => $posts->where('is_checked', true)->count(),
            'qc_pending'   => $posts->where('is_completed', true)->where('is_checked', false)->count(),
        ];
    }
}
