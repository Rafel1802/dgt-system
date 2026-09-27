<?php

namespace App\Models\Kpi;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiSupervisorReport extends Model
{
    protected $table = 'kpi_supervisor_reports';

    protected $fillable = [
        'squad_id',
        'kpi_period_id',
        'submitted_by',
        'reviewed_by',
        'overall_team_kpi',
        'team_productivity',
        'team_quality',
        'team_deadline',
        'status',
        'summary',
        'strengths',
        'improvements',
        'supervisor_notes',
        'submitted_at',
        'reviewed_at',
    ];

    protected $casts = [
        'overall_team_kpi' => 'float',
        'team_productivity' => 'float',
        'team_quality' => 'float',
        'team_deadline' => 'float',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function squad(): BelongsTo
    {
        return $this->belongsTo(KpiSquad::class, 'squad_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(KpiPeriod::class, 'kpi_period_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
