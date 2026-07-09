<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TechSupportCase extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_NEW         = 'new_case';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_RED         = 'red_case';
    const STATUS_RESOLVED    = 'resolved';

    protected $fillable = [
        'source_type', 'source_id', 'customer_id', 'order_id',
        'status', 'assigned_to', 'created_by',
        'acknowledged_at', 'resolved_at', 'ebay_synced_at',
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
        'resolved_at'     => 'datetime',
        'ebay_synced_at'  => 'datetime',
    ];

    public static function statuses(): array
    {
        return [
            self::STATUS_NEW         => 'New Case',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_RED         => 'Red Case (Potential Return)',
            self::STATUS_RESOLVED    => 'Resolved',
        ];
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            self::STATUS_NEW         => '#6366f1', // indigo
            self::STATUS_IN_PROGRESS => '#f59e0b', // amber
            self::STATUS_RED         => '#dc2626', // red
            self::STATUS_RESOLVED    => '#10b981', // emerald
            default => '#94a3b8',
        };
    }

    // ── Relationships ───────────────────────────────────────────────────────

    public function source(): MorphTo
    {
        return $this->morphTo('source', 'source_type', 'source_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(TechSupportCaseLog::class)->orderByDesc('created_at');
    }

    public function callRequests(): MorphMany
    {
        return $this->morphMany(CallRequest::class, 'source', 'source_type', 'source_id');
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeStatus($query, string $status): mixed
    {
        return $query->where('status', $status);
    }

    public function scopeSearch($query, string $term): mixed
    {
        return $query->where(function ($q) use ($term) {
            $q->where('order_id', 'like', "%{$term}%")
              ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%"));
        });
    }
}
