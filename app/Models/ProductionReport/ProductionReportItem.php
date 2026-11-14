<?php

namespace App\Models\ProductionReport;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionReportItem extends Model
{
    protected $table = 'production_report_items';

    protected $fillable = [
        'production_report_id',
        'user_id',
        'task_title',
        'task_description',
        'task_category',
        'quantity',
        'unit',
        'status',
        'completion_date',
        'external_link',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'completion_date' => 'date',
            'sort_order' => 'integer',
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

