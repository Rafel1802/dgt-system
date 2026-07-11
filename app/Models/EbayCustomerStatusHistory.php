<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EbayCustomerStatusHistory extends Model
{
    protected $table = 'ebay_customer_status_history';

    protected $fillable = [
        'ebay_customer_record_id', 'status', 'changed_by', 'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(EbayCustomerRecord::class, 'ebay_customer_record_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by')->withTrashed();
    }
}
