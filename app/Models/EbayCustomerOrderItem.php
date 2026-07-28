<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EbayCustomerOrderItem extends Model
{
    protected $fillable = [
        'ebay_customer_order_id', 'product_name', 'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(EbayCustomerOrder::class, 'ebay_customer_order_id');
    }
}
