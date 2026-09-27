<?php

namespace App\Models\Kpi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiTemplate extends Model
{
    protected $table = 'kpi_templates';

    protected $fillable = [
        'name',
        'role_type',
        'skill_id',
        'description',
    ];

    public function skill(): BelongsTo
    {
        return $this->belongsTo(KpiSkill::class, 'skill_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(KpiTemplateItem::class, 'template_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(KpiAssignment::class, 'template_id');
    }
}
