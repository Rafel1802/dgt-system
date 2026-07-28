<?php

namespace App\Models;

use App\Enums\LogisticStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticUpdate extends Model
{
    protected $fillable = [
        'logistic_id', 'user_id', 'status', 'notes', 'attachment', 'occurred_at',
    ];

    protected $casts = [
        'status'      => LogisticStatus::class,
        'occurred_at' => 'datetime',
    ];

    public function logistic(): BelongsTo
    {
        return $this->belongsTo(Logistic::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment ? asset('storage/' . $this->attachment) : null;
    }
}
