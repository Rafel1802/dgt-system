<?php

namespace App\Models\ProductionReport;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionReportRevision extends Model
{
    public $timestamps = false;

    protected $table = 'production_report_revisions';

    protected $fillable = [
        'production_report_id',
        'user_id',
        'revision_number',
        'action',
        'previous_status',
        'new_status',
        'snapshot_json',
        'notes',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'snapshot_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ProductionReport::class, 'production_report_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}

