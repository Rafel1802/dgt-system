<?php

namespace App\Models\Kpi;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiReview extends Model
{
    protected $table = 'kpi_reviews';

    protected $fillable = [
        'kpi_assignment_id',
        'user_id',
        'reviewer_id',
        'kpi_period_id',
        'squad_id',
        'productivity_score',
        'quality_score',
        'deadline_score',
        'teamwork_score',
        'overall_kpi',
        'performance_band',
        'status',
        'manager_notes',
        'supervisor_notes',
        'uploaded_pdf_path',
        'google_drive_url',
        'google_drive_file_id',
        'google_drive_synced_at',
        'evaluation_date',
        'reviewed_at',
    ];

    protected $casts = [
        'productivity_score' => 'float',
        'quality_score' => 'float',
        'deadline_score' => 'float',
        'teamwork_score' => 'float',
        'overall_kpi' => 'float',
        'reviewed_at' => 'datetime',
        'evaluation_date' => 'date',
        'google_drive_synced_at' => 'datetime',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(KpiAssignment::class, 'kpi_assignment_id');
    }

    public function squad(): BelongsTo
    {
        return $this->belongsTo(KpiSquad::class, 'squad_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(KpiPeriod::class, 'kpi_period_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(KpiReviewItem::class, 'review_id');
    }

    /**
     * Rating on 5-point scale (highest is 5.0).
     * If legacy score was stored > 5 (e.g. 95%), converts to 5-point scale: 95 / 20 = 4.75 -> 4.8.
     */
    public function getPointsScoreAttribute(): float
    {
        $raw = (float)$this->overall_kpi;
        if ($raw <= 0) return 0.0;
        if ($raw > 5.0) {
            return round(min(5.0, $raw / 20.0), 1);
        }
        return round(min(5.0, $raw), 1);
    }

    public function getPointsFormattedAttribute(): string
    {
        return number_format($this->points_score, 1) . ' / 5.0';
    }

    public function getQualityPointsAttribute(): float
    {
        $raw = (float)$this->quality_score;
        if ($raw <= 0) return 0.0;
        return $raw > 5.0 ? round(min(5.0, $raw / 20.0), 1) : round(min(5.0, $raw), 1);
    }

    public function getProductivityPointsAttribute(): float
    {
        $raw = (float)$this->productivity_score;
        if ($raw <= 0) return 0.0;
        return $raw > 5.0 ? round(min(5.0, $raw / 20.0), 1) : round(min(5.0, $raw), 1);
    }

    public function getDeadlinePointsAttribute(): float
    {
        $raw = (float)$this->deadline_score;
        if ($raw <= 0) return 0.0;
        return $raw > 5.0 ? round(min(5.0, $raw / 20.0), 1) : round(min(5.0, $raw), 1);
    }

    public function getTeamworkPointsAttribute(): float
    {
        $raw = (float)$this->teamwork_score;
        if ($raw <= 0) return 0.0;
        return $raw > 5.0 ? round(min(5.0, $raw / 20.0), 1) : round(min(5.0, $raw), 1);
    }

    public function getStarsCountAttribute(): int
    {
        return (int)round($this->points_score);
    }
}
