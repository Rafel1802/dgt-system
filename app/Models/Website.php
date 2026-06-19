<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Website extends Model
{
    use HasFactory, SoftDeletes;

    // Status constants
    const STATUS_DRAFT        = 'Draft';
    const STATUS_IN_PROGRESS  = 'In Progress';
    const STATUS_QC_REVIEW    = 'QC Review';
    const STATUS_COMPLETED    = 'Completed';
    const STATUS_LIVE         = 'Live';
    const STATUS_NEEDS_UPDATE = 'Needs Update';
    const STATUS_ERROR        = 'Error/Bug';

    const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_IN_PROGRESS,
        self::STATUS_QC_REVIEW,
        self::STATUS_COMPLETED,
        self::STATUS_LIVE,
        self::STATUS_NEEDS_UPDATE,
        self::STATUS_ERROR,
    ];

    const PROGRESS_STEPS = [10, 25, 50, 75, 100];

    protected $fillable = [
        'name',
        'url',
        'logo_path',
        'category',
        'handled_by',
        'start_date',
        'deadline',
        'completed_at',
        'live_at',
        'status',
        'progress_percent',
        'notes',
        'created_by',
        'updated_by',
        'is_archived',
    ];

    protected $casts = [
        'start_date'       => 'date',
        'deadline'         => 'date',
        'completed_at'     => 'datetime',
        'live_at'          => 'datetime',
        'progress_percent' => 'integer',
        'is_archived'      => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function progressLogs(): HasMany
    {
        return $this->hasMany(WebsiteProgressLog::class)->orderByDesc('created_at');
    }

    public function qcChecks(): HasMany
    {
        return $this->hasMany(WebsiteQcCheck::class);
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(WebsiteMaintenanceLog::class)->orderByDesc('created_at');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function getLogoSrcAttribute(): string
    {
        if (! $this->logo_path) {
            return '';
        }
        return str_starts_with($this->logo_path, 'http')
            ? $this->logo_path
            : asset('storage/' . $this->logo_path);
    }

    public function getCleanDomainAttribute(): string
    {
        return str_replace(['http://', 'https://'], '', rtrim($this->url, '/'));
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT        => 'slate',
            self::STATUS_IN_PROGRESS  => 'blue',
            self::STATUS_QC_REVIEW    => 'amber',
            self::STATUS_COMPLETED    => 'emerald',
            self::STATUS_LIVE         => 'green',
            self::STATUS_NEEDS_UPDATE => 'orange',
            self::STATUS_ERROR        => 'rose',
            default                   => 'slate',
        };
    }

    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    public function isInProgress(): bool
    {
        return in_array($this->status, [
            self::STATUS_IN_PROGRESS,
            self::STATUS_QC_REVIEW,
            self::STATUS_NEEDS_UPDATE,
            self::STATUS_ERROR,
        ]);
    }

    public function isOverdue(): bool
    {
        return $this->deadline && $this->deadline->isPast() && ! $this->isLive();
    }
}
