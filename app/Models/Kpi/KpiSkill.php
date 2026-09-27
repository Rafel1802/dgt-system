<?php

namespace App\Models\Kpi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiSkill extends Model
{
    protected $table = 'kpi_skills';

    protected $fillable = [
        'name',
        'category',
        'description',
    ];

    public function templates(): HasMany
    {
        return $this->hasMany(KpiTemplate::class, 'skill_id');
    }
}
