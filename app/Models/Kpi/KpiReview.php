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
}
