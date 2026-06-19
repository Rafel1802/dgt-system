<?php

namespace App\Models;

use App\Enums\DealStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deal extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id', 'assigned_to', 'created_by',
        'title', 'description', 'stage', 'value', 'currency',
        'probability', 'expected_close_date', 'closed_at',
        'lost_reason', 'product_interests', 'position',
    ];

    protected $casts = [
        'stage'               => DealStage::class,
        'value'               => 'decimal:2',
        'product_interests'   => 'array',
        'expected_close_date' => 'date',
        'closed_at'           => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function getWeightedValueAttribute(): float
    {
        return round($this->value * ($this->probability / 100), 2);
    }

    public function isActive(): bool
    {
        return ! in_array($this->stage, [DealStage::Won, DealStage::Lost]);
    }
}
