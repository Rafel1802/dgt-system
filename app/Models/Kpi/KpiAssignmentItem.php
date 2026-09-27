<?php

namespace App\Models\Kpi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiAssignmentItem extends Model
{
    protected $table = 'kpi_assignment_items';

    protected $fillable = [
        'assignment_id',
        'template_item_id',
        'name',
        'pillar',
        'weight',
        'target_value',
        'unit',
    ];

    protected $casts = [
        'weight' => 'float',
        'target_value' => 'float',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(KpiAssignment::class, 'assignment_id');
    }

    public function templateItem(): BelongsTo
    {
        return $this->belongsTo(KpiTemplateItem::class, 'template_item_id');
    }
}
