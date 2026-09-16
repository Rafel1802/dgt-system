<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PinnedNotification extends Model
{
    protected $fillable = [
        'notification_id',
        'actor_name',
        'actor_avatar',
        'title',
        'message',
        'link',
        'board_name',
        'card_title',
        'card_id',
        'data',
        'pinned_by',
        'pinned_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'data'       => 'array',
        'pinned_at'  => 'datetime',
        'expires_at' => 'datetime',
        'is_active'  => 'boolean',
    ];

    public function pinner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pinned_by');
    }

    /**
     * Scope query to only active and non-expired pinned notifications.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Check if this pinned notification has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
