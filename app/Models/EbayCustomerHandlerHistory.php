<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EbayCustomerHandlerHistory extends Model
{
    protected $table = 'ebay_customer_handler_history';

    protected $fillable = [
        'ebay_customer_record_id', 'user_id', 'started_at', 'ended_at', 'confirmed_at',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'ended_at'     => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(EbayCustomerRecord::class, 'ebay_customer_record_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** The current (not-yet-ended) assignment for a user, still awaiting their confirmation. */
    public function scopePendingConfirmation($query)
    {
        return $query->whereNull('ended_at')->whereNull('confirmed_at');
    }
}
