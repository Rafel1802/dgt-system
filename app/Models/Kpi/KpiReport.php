<?php

namespace App\Models\Kpi;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiReport extends Model
{
    protected $table = 'kpi_reports';

    protected $fillable = [
        'kpi_period_id',
        'squad_id',
        'type',
        'file_name',
        'file_path',
        'google_drive_file_id',
        'google_drive_link',
        'created_by',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(KpiPeriod::class, 'kpi_period_id');
    }

    public function squad(): BelongsTo
    {
        return $this->belongsTo(KpiSquad::class, 'squad_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
