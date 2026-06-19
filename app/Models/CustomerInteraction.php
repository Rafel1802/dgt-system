<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerInteraction extends Model
{
    protected $fillable = [
        'customer_id', 'user_id', 'type', 'subject',
        'content', 'outcome', 'interacted_at', 'duration_minutes',
    ];

    protected $casts = [
        'interacted_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function getTypeIconAttribute(): string
    {
        return match($this->type) {
            'call'     => '📞',
            'email'    => '📧',
            'meeting'  => '🤝',
            'note'     => '📝',
            'whatsapp' => '💬',
            'demo'     => '🖥️',
            default    => '📋',
        };
    }

    public function getOutcomeColorAttribute(): string
    {
        return match($this->outcome) {
            'positive' => 'text-emerald-600',
            'negative' => 'text-rose-600',
            default    => 'text-slate-500',
        };
    }
}
