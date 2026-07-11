<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CallReportShare extends Model
{
    protected $fillable = [
        'token', 'filters', 'created_by',
    ];

    protected $casts = [
        'filters' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public static function createForFilters(array $filters, ?int $createdBy): self
    {
        return static::create([
            'token'      => Str::random(40),
            'filters'    => array_filter($filters, fn ($v) => $v !== null && $v !== ''),
            'created_by' => $createdBy,
        ]);
    }
}
