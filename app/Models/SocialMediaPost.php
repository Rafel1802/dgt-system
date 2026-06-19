<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialMediaPost extends Model
{
    protected $fillable = [
        'social_media_class_id',
        'social_media_item_id',
        'user_id',
        'post_date',
        'post_url',
        'optional_text',
        'is_completed',
        'completed_at',
        'is_checked',
        'checked_by',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'post_date'    => 'date',
            'completed_at' => 'datetime',
            'checked_at'   => 'datetime',
            'is_completed' => 'boolean',
            'is_checked'   => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function socialMediaClass(): BelongsTo
    {
        return $this->belongsTo(SocialMediaClass::class, 'social_media_class_id');
    }

    public function socialMediaItem(): BelongsTo
    {
        return $this->belongsTo(SocialMediaItem::class, 'social_media_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    public function scopePending($query)
    {
        return $query->where('is_completed', false);
    }

    public function scopeChecked($query)
    {
        return $query->where('is_checked', true);
    }

    public function scopeQcPending($query)
    {
        return $query->where('is_completed', true)->where('is_checked', false);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Row is locked once QC has checked it */
    public function isLocked(): bool
    {
        return $this->is_checked;
    }

    /** Can the given user edit this post row? */
    public function canEditBy(User $user): bool
    {
        if ($this->is_checked) {
            // Only admin-level can unlock
            return $user->hasAnyRole(['super-admin', 'admin-digital']);
        }
        // Owner or admin
        return $this->user_id === $user->id
            || $user->hasAnyRole(['super-admin', 'admin-digital']);
    }

    public function getQcStatusLabelAttribute(): string
    {
        if ($this->is_checked) return 'Checked';
        if ($this->is_completed) return 'QC Pending';
        return 'Not Submitted';
    }

    public function getQcStatusColorAttribute(): string
    {
        return match($this->qc_status_label) {
            'Checked'       => 'blue',
            'QC Pending'    => 'orange',
            default         => 'slate',
        };
    }
}
