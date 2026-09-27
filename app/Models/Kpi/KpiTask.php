<?php

namespace App\Models\Kpi;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KpiTask extends Model
{
    use SoftDeletes;

    protected $table = 'kpi_tasks';

    protected $fillable = [
        'title',
        'description',
        'squad_id',
        'assignee_id',
        'creator_id',
        'kpi_period_id',
        'priority',
        'status',
        'due_date',
        'completed_at',
        'quality_score',
        'feedback',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'quality_score' => 'float',
    ];

    public function squad(): BelongsTo
    {
        return $this->belongsTo(KpiSquad::class, 'squad_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(KpiPeriod::class, 'kpi_period_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(KpiTaskSubmission::class, 'task_id');
    }
}
