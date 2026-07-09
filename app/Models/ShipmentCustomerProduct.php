<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentCustomerProduct extends Model
{
    protected $fillable = [
        'shipment_customer_id', 'product_id', 'product_name', 'sku', 'price', 'quantity',
    ];

    protected $casts = [
        'price'    => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function shipmentCustomer(): BelongsTo
    {
        return $this->belongsTo(ShipmentCustomer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
