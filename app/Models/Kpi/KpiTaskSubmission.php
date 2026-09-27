<?php

namespace App\Models\Kpi;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiTaskSubmission extends Model
{
    protected $table = 'kpi_task_submissions';

    protected $fillable = [
        'task_id',
        'user_id',
        'evidence_url',
        'notes',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(KpiTask::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
