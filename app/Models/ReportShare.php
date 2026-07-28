<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReportShare extends Model
{
    protected $fillable = [
        'token', 'report_type', 'user_id', 'filters', 'created_by',
    ];

    protected $casts = [
        'filters' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public static function createForStaff(int $userId, array $filters, ?int $createdBy): self
    {
        return static::create([
            'token'       => Str::random(40),
            'report_type' => 'staff',
            'user_id'     => $userId,
            'filters'     => array_filter($filters, fn ($v) => $v !== null && $v !== ''),
            'created_by'  => $createdBy,
        ]);
    }

    public static function createForTeam(array $filters, ?int $createdBy): self
    {
        return static::create([
            'token'       => Str::random(40),
            'report_type' => 'team',
            'filters'     => array_filter($filters, fn ($v) => $v !== null && $v !== ''),
            'created_by'  => $createdBy,
        ]);
    }
}
