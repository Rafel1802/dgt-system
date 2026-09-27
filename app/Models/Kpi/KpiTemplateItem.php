<?php

namespace App\Models\Kpi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiTemplateItem extends Model
{
    protected $table = 'kpi_template_items';

    protected $fillable = [
        'template_id',
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(KpiTemplate::class, 'template_id');
    }
}
