<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class TechSupportCase extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The sidebar's "new case" badge count (layouts/app.blade.php) caches
     * this for 5 minutes for performance, since it renders on every page
     * load — invalidate it on any change that could move a case in or out
     * of the New status, so the badge doesn't linger stale (e.g. showing
     * "1" for up to 5 minutes after the last New case was already picked
     * up) rather than clearing the moment it's no longer true.
     */
    protected static function booted(): void
    {
        $forget = fn () => Cache::forget('tech_case_count_new');

        static::created($forget);
        static::updated($forget);
        static::deleted($forget);
    }

    const STATUS_NEW         = 'new_case';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_RED         = 'red_case';
    const STATUS_RESOLVED    = 'resolved';

    protected $fillable = [
        'source_type', 'source_id', 'customer_id', 'order_id',
        'status', 'occurrence_count', 'assigned_to', 'created_by',
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

    /** "1st", "2nd", "3rd", "4th", ... — shared by the reopen-case log/notification text and the occurrence badge below. */
    public static function ordinal(int $n): string
    {
        if ($n % 100 >= 11 && $n % 100 <= 13) {
            return "{$n}th";
        }

        return $n . match ($n % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }

    /** e.g. "(2nd)" once a customer has reported the same technical issue more than once, null otherwise — for a compact badge next to the case/customer's status wherever it's shown. */
    public function getOccurrenceLabelAttribute(): ?string
    {
        return $this->occurrence_count > 1 ? '(' . self::ordinal($this->occurrence_count) . ')' : null;
    }

    /** Most recent call request raised on this case, null if none — for a "Call Requested"/"Call Completed" badge on the case row. Reads off an eager-loaded callRequests() to avoid an extra query per row. */
    public function getLatestCallRequestAttribute(): ?CallRequest
    {
        return $this->callRequests->sortByDesc('created_at')->first();
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
