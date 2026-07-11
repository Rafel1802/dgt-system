<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EbayCustomerOrder extends Model
{
    protected $fillable = [
        'ebay_customer_record_id', 'order_id', 'ebay_store_id', 'ordered_at', 'created_by',
    ];

    protected $casts = [
        'ordered_at' => 'date',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(EbayCustomerRecord::class, 'ebay_customer_record_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(EbayStore::class, 'ebay_store_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(EbayCustomerOrderItem::class);
    }
}
