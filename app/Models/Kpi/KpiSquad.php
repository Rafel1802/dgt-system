<?php

namespace App\Models\Kpi;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KpiSquad extends Model
{
    use SoftDeletes;

    protected $table = 'kpi_squads';

    protected $fillable = [
        'name',
        'code',
        'lead_id',
        'description',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(KpiAssignment::class, 'squad_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(KpiTask::class, 'squad_id');
    }

    public function supervisorReports(): HasMany
    {
        return $this->hasMany(KpiSupervisorReport::class, 'squad_id');
    }
}
