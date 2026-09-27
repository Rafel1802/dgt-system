<?php

namespace App\Models\Kpi;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class KpiAssignment extends Model
{
    protected $table = 'kpi_assignments';

    protected $fillable = [
        'user_id',
        'squad_id',
        'kpi_period_id',
        'assigned_by',
        'template_id',
        'target_deliverables',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function squad(): BelongsTo
    {
        return $this->belongsTo(KpiSquad::class, 'squad_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(KpiPeriod::class, 'kpi_period_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(KpiTemplate::class, 'template_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(KpiAssignmentItem::class, 'assignment_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(KpiReview::class, 'kpi_assignment_id');
    }
}
