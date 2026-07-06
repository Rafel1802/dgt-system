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

    // ── Status constants ───────────────────────────────────────────────────────
    const STATUS_BUILD_WEBSITE         = 'Build Website';
    const STATUS_BUILD_PROGRESS        = 'Build Progress';
    const STATUS_QC_CHECKING           = 'QC Checking';
    const STATUS_SUPERVISOR_CHECKING   = 'Supervisor Checking';
    const STATUS_QC_ERROR              = 'QC Error';
    const STATUS_SUPERVISOR_ERROR      = 'Supervisor Error';
    const STATUS_LIVE                  = 'Live';
    const STATUS_MAINTENANCE           = 'Maintenance';
    const STATUS_MAINTENANCE_PROGRESS  = 'Maintenance Progress';
    const STATUS_MAINTENANCE_QC_CHECKING = 'Maintenance QC Checking';
    const STATUS_MAINTENANCE_SUPERVISOR_CHECKING = 'Maintenance Supervisor Checking';
    const STATUS_MAINTENANCE_QC_ERROR  = 'Maintenance QC Error';
    const STATUS_MAINTENANCE_SUPERVISOR_ERROR = 'Maintenance Supervisor Error';
    const STATUS_COMPLETED             = 'Completed';

    const STATUSES = [
        self::STATUS_BUILD_WEBSITE,
        self::STATUS_BUILD_PROGRESS,
        self::STATUS_QC_CHECKING,
        self::STATUS_SUPERVISOR_CHECKING,
        self::STATUS_QC_ERROR,
        self::STATUS_SUPERVISOR_ERROR,
        self::STATUS_LIVE,
        self::STATUS_MAINTENANCE,
        self::STATUS_MAINTENANCE_PROGRESS,
        self::STATUS_MAINTENANCE_QC_CHECKING,
        self::STATUS_MAINTENANCE_SUPERVISOR_CHECKING,
        self::STATUS_MAINTENANCE_QC_ERROR,
        self::STATUS_MAINTENANCE_SUPERVISOR_ERROR,
        self::STATUS_COMPLETED,
    ];

    const PROGRESS_STEPS = [0, 10, 25, 50, 75, 100];

    const FOLLOW_UP_TYPES = [
        'blog_post'     => 'Blog Post',
        'indexed_page'  => 'Indexed Page',
        'website_page'  => 'Website Page',
        'other'         => 'Other',
    ];

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
        'maintenance_percent',
        'qc_approved_by',
        'qc_approved_at',
        'maintenance_started_at',
        'maintenance_completed_at',
        'notes',
        'error_note',
        'error_link',
        'error_attachment_path',
        'error_attachment_name',
        'error_flagged_at',
        'error_flagged_by',
        'error_progress_percent',
        'created_by',
        'updated_by',
        'is_archived',
    ];

    protected $casts = [
        'start_date'               => 'date',
        'deadline'                 => 'date',
        'completed_at'             => 'datetime',
        'live_at'                  => 'datetime',
        'qc_approved_at'           => 'datetime',
        'maintenance_started_at'   => 'datetime',
        'maintenance_completed_at' => 'datetime',
        'error_flagged_at'         => 'datetime',
        'progress_percent'         => 'integer',
        'maintenance_percent'      => 'integer',
        'error_progress_percent'   => 'integer',
        'is_archived'              => 'boolean',
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

    public function qcApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qc_approved_by');
    }

    public function progressLogs(): HasMany
    {
        return $this->hasMany(WebsiteProgressLog::class)
            ->where('type', 'build')
            ->orderByDesc('created_at');
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(WebsiteProgressLog::class)
            ->where('type', 'maintenance')
            ->orderByDesc('created_at');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(WebsiteMaintenanceLog::class)->orderByDesc('created_at');
    }

    public function qcChecks(): HasMany
    {
        return $this->hasMany(WebsiteQcCheck::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(WebsiteFollowUp::class)->orderByDesc('created_at');
    }

    // ── Status helpers ────────────────────────────────────────────────────────

    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    public function isBuilding(): bool
    {
        return in_array($this->status, [
            self::STATUS_BUILD_WEBSITE,
            self::STATUS_BUILD_PROGRESS,
            self::STATUS_QC_CHECKING,
            self::STATUS_SUPERVISOR_CHECKING,
            self::STATUS_QC_ERROR,
            self::STATUS_SUPERVISOR_ERROR,
        ]);
    }

    public function isMaintenance(): bool
    {
        return in_array($this->status, [
            self::STATUS_MAINTENANCE,
            self::STATUS_MAINTENANCE_PROGRESS,
            self::STATUS_MAINTENANCE_QC_CHECKING,
            self::STATUS_MAINTENANCE_SUPERVISOR_CHECKING,
            self::STATUS_MAINTENANCE_QC_ERROR,
            self::STATUS_MAINTENANCE_SUPERVISOR_ERROR,
        ]);
    }

    public function isQcError(): bool
    {
        return in_array($this->status, [
            self::STATUS_QC_ERROR,
            self::STATUS_MAINTENANCE_QC_ERROR,
        ]);
    }

    public function isSupervisorError(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUPERVISOR_ERROR,
            self::STATUS_MAINTENANCE_SUPERVISOR_ERROR,
        ]);
    }

    public function isLiveOrMaintenance(): bool
    {
        return in_array($this->status, [
            self::STATUS_LIVE,
            self::STATUS_MAINTENANCE,
            self::STATUS_MAINTENANCE_PROGRESS,
            self::STATUS_MAINTENANCE_QC_CHECKING,
            self::STATUS_MAINTENANCE_SUPERVISOR_CHECKING,
            self::STATUS_MAINTENANCE_QC_ERROR,
            self::STATUS_MAINTENANCE_SUPERVISOR_ERROR,
        ]);
    }

    public function isInProgress(): bool
    {
        return $this->isBuilding();
    }

    public function isOverdue(): bool
    {
        return $this->deadline && $this->deadline->isPast() && !$this->isLive();
    }

    // ── Display helpers ───────────────────────────────────────────────────────

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
            self::STATUS_BUILD_WEBSITE        => 'slate',
            self::STATUS_BUILD_PROGRESS       => 'blue',
            self::STATUS_QC_CHECKING          => 'amber',
            self::STATUS_SUPERVISOR_CHECKING  => 'cyan',
            self::STATUS_QC_ERROR             => 'red',
            self::STATUS_SUPERVISOR_ERROR     => 'red',
            self::STATUS_LIVE                 => 'emerald',
            self::STATUS_MAINTENANCE          => 'orange',
            self::STATUS_MAINTENANCE_PROGRESS => 'orange',
            self::STATUS_MAINTENANCE_QC_CHECKING => 'amber',
            self::STATUS_MAINTENANCE_SUPERVISOR_CHECKING => 'cyan',
            self::STATUS_MAINTENANCE_QC_ERROR => 'red',
            self::STATUS_MAINTENANCE_SUPERVISOR_ERROR => 'red',
            self::STATUS_COMPLETED            => 'violet',
            default                           => 'slate',
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_BUILD_WEBSITE        => 'bg-slate-100 text-slate-700',
            self::STATUS_BUILD_PROGRESS       => 'bg-blue-100 text-blue-700',
            self::STATUS_QC_CHECKING          => 'bg-amber-100 text-amber-700',
            self::STATUS_SUPERVISOR_CHECKING  => 'bg-cyan-100 text-cyan-700',
            self::STATUS_QC_ERROR             => 'bg-red-100 text-red-700',
            self::STATUS_SUPERVISOR_ERROR     => 'bg-red-100 text-red-700',
            self::STATUS_LIVE                 => 'bg-emerald-100 text-emerald-700',
            self::STATUS_MAINTENANCE          => 'bg-orange-100 text-orange-700',
            self::STATUS_MAINTENANCE_PROGRESS => 'bg-orange-100 text-orange-700',
            self::STATUS_MAINTENANCE_QC_CHECKING => 'bg-amber-100 text-amber-700',
            self::STATUS_MAINTENANCE_SUPERVISOR_CHECKING => 'bg-cyan-100 text-cyan-700',
            self::STATUS_MAINTENANCE_QC_ERROR => 'bg-red-100 text-red-700',
            self::STATUS_MAINTENANCE_SUPERVISOR_ERROR => 'bg-red-100 text-red-700',
            self::STATUS_COMPLETED            => 'bg-violet-100 text-violet-700',
            default                           => 'bg-slate-100 text-slate-600',
        };
    }
}
