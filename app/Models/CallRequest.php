<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CallRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_type', 'source_id', 'name', 'phone', 'note', 'fulfillment_note',
        'requested_by', 'fulfilled', 'fulfilled_at', 'fulfilled_by',
    ];

    protected $casts = [
        'fulfilled'    => 'boolean',
        'fulfilled_at' => 'datetime',
    ];

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = \App\Support\PhoneNumberFormatter::format($value);
    }

    public function source(): MorphTo
    {
        return $this->morphTo('source', 'source_type', 'source_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    public function fulfilledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fulfilled_by')->withTrashed();
    }

    public function scopePending($query): mixed
    {
        return $query->where('fulfilled', false);
    }
}
