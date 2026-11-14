<?php

namespace App\Models\ProductionReport;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionReport extends Model
{
    use SoftDeletes;

    protected $table = 'production_reports';

    public const TYPE_DAILY = 'daily';
    public const TYPE_WEEKLY = 'weekly';
    public const TYPE_MONTHLY = 'monthly';

    /** project_category used for the Head's combined Team Monthly report (a monthly report with team scope). */
    public const TEAM_MONTHLY_CATEGORY = 'Team Monthly Production';

    public const STATUS_NOT_STARTED = 'not_started';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_SUBMITTED_LATE = 'submitted_late';
    public const STATUS_RETURNED = 'returned_for_correction';
    public const STATUS_APPROVED = 'approved';

    public const RATING_LABELS = [
        1 => 'Needs Improvement',
        2 => 'Below Expectations',
        3 => 'Meets Expectations',
        4 => 'Strong Performance',
        5 => 'Excellent',
    ];

    protected $fillable = [
        'report_code',
        'report_type',
        'user_id',
        'team_id',
        'team_code',
        'team_name',
        'reporting_date',
        'reporting_start_date',
        'reporting_end_date',
        'reporting_month',
        'reporting_year',
        'project_category',
        'title',
        'executive_summary',
        'tasks_completed',
        'work_in_progress',
        'pending_tasks',
        'blockers',
        'achievements',
        'deliverable_links',
        'next_priorities',
        'notes',
        'performance_rating',
        'performance_rating_label',
        'head_comments',
        'supervisor_comments',
        'member_ratings_json',
        'compiled_summary_json',
        'status',
        'is_late',
        'deadline_at',
        'submitted_at',
        'submitted_by',
        'reviewed_by',
        'reviewed_at',
        'returned_reason',
        'pptx_path',
        'pdf_path',
        'csv_path',
        'last_exported_at',
    ];

    protected function casts(): array
    {
        return [
            'reporting_date' => 'date',
            'reporting_start_date' => 'date',
            'reporting_end_date' => 'date',
            'reporting_month' => 'integer',
            'reporting_year' => 'integer',
            'deliverable_links' => 'array',
            'member_ratings_json' => 'array',
            'compiled_summary_json' => 'array',
            'performance_rating' => 'integer',
            'is_late' => 'boolean',
            'deadline_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'last_exported_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionReportItem::class, 'production_report_id')->orderBy('sort_order')->orderBy('id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductionReportMedia::class, 'production_report_id')->orderBy('highlight_order')->orderBy('id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ProductionReportRevision::class, 'production_report_id')->orderByDesc('revision_number')->orderByDesc('id');
    }

    public function isSubmittedOrApproved(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUBMITTED,
            self::STATUS_SUBMITTED_LATE,
            self::STATUS_APPROVED,
        ], true);
    }

    public function isEditableByAuthor(): bool
    {
        return in_array($this->status, [
            self::STATUS_NOT_STARTED,
            self::STATUS_DRAFT,
            self::STATUS_RETURNED,
        ], true);
    }

    public function getStatusBadgeLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_NOT_STARTED => 'Not Started',
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SUBMITTED => 'Submitted',
            self::STATUS_SUBMITTED_LATE => 'Submitted Late',
            self::STATUS_RETURNED => 'Returned for Correction',
            self::STATUS_APPROVED => 'Approved',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'emerald',
            self::STATUS_SUBMITTED => 'indigo',
            self::STATUS_SUBMITTED_LATE => 'amber',
            self::STATUS_RETURNED => 'rose',
            self::STATUS_DRAFT => 'sky',
            default => 'slate',
        };
    }

    public function getRatingDisplayAttribute(): string
    {
        if (!$this->performance_rating) {
            return 'Not Rated';
        }
        $label = $this->performance_rating_label ?: (self::RATING_LABELS[$this->performance_rating] ?? '');
        return "{$this->performance_rating}/5 — {$label}";
    }

    public function isTeamMonthly(): bool
    {
        return $this->report_type === self::TYPE_MONTHLY && $this->project_category === self::TEAM_MONTHLY_CATEGORY;
    }

    public function getPeriodLabelAttribute(): string
    {
        if ($this->report_type === self::TYPE_DAILY && $this->reporting_date) {
            return $this->reporting_date->format('D, M d, Y');
        }
        if ($this->report_type === self::TYPE_MONTHLY && $this->reporting_month && $this->reporting_year) {
            return \Carbon\Carbon::createFromDate($this->reporting_year, $this->reporting_month, 1)->format('F Y');
        }
        if ($this->reporting_start_date && $this->reporting_end_date) {
            return $this->reporting_start_date->format('M d, Y') . ' – ' . $this->reporting_end_date->format('M d, Y');
        }
        return (string) $this->reporting_year;
    }

    public function getStorageFolderAttribute(): string
    {
        $year = $this->reporting_year ?: now('Asia/Phnom_Penh')->year;
        $teamFolder = $this->team_code ? 'Team-' . strtoupper($this->team_code) : 'Team-Unassigned';
        return "production-reports/{$year}/{$teamFolder}/{$this->report_type}/{$this->id}";
    }
}

