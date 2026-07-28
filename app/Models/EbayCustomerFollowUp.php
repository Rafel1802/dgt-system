<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EbayCustomerFollowUp extends Model
{
    protected $fillable = [
        'ebay_customer_record_id', 'user_id', 'notes', 'contacted_at',
    ];

    protected $casts = [
        'contacted_at' => 'datetime',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(EbayCustomerRecord::class, 'ebay_customer_record_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
