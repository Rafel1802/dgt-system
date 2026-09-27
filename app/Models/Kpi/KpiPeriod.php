<?php

namespace App\Models\Kpi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiPeriod extends Model
{
    protected $table = 'kpi_periods';

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'status',
        'description',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(KpiAssignment::class, 'kpi_period_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(KpiTask::class, 'kpi_period_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(KpiReview::class, 'kpi_period_id');
    }

    public function supervisorReports(): HasMany
    {
        return $this->hasMany(KpiSupervisorReport::class, 'kpi_period_id');
    }
}
