<?php

namespace App\Models;

use App\Enums\EbayLeadStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class EbayOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ebay_offer_id', 'customer_id', 'product_id', 'created_by',
        'ebay_order_id', 'ebay_username',
        'sale_amount', 'currency', 'payment_status', 'payment_date',
        'status', 'notes',
    ];

    protected $casts = [
        'status'       => EbayLeadStatus::class,
        'sale_amount'  => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(EbayOffer::class, 'ebay_offer_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function logistic(): HasOne
    {
        return $this->hasOne(Logistic::class);
    }

    public function scopeConfirmed($query): mixed
    {
        return $query->where('status', EbayLeadStatus::OrderConfirmed->value);
    }
}
