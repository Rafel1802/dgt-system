<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpBan extends Model
{
    protected $fillable = [
        'ip_address',
        'device_token',
        'reason',
        'banned_by',
        'is_active',
        'banned_at',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'banned_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function bannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by')->withTrashed();
    }

    /**
     * Check if this ban is currently effective.
     */
    public function isEffective(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        // Permanent ban OR not yet expired
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function scopeActive($query): mixed
    {
        return $query->where('is_active', true)
                     ->where(function ($q) {
                         $q->whereNull('expires_at')
                           ->orWhere('expires_at', '>', now());
                     });
    }
}
