<?php

namespace App\Models;

use App\Enums\LogisticStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Logistic extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id', 'product_id', 'ebay_order_id', 'lead_id',
        'created_by', 'assigned_to', 'trucking_company_id',
        'order_id', 'product_description',
        'shipping_address', 'recipient_name', 'recipient_phone',
        'truck_company', 'driver_name', 'driver_phone',
        'shipping_budget', 'final_shipping_cost', 'currency',
        'pickup_datetime', 'estimated_arrival', 'actual_arrival',
        'tracking_number', 'status',
        'delivery_proof', 'notes',
    ];

    protected $casts = [
        'status'            => LogisticStatus::class,
        'shipping_budget'   => 'decimal:2',
        'final_shipping_cost'=> 'decimal:2',
        'pickup_datetime'   => 'datetime',
        'estimated_arrival' => 'date',
        'actual_arrival'    => 'date',
    ];

    public function setRecipientPhoneAttribute(?string $value): void
    {
        $this->attributes['recipient_phone'] = \App\Support\PhoneNumberFormatter::format($value);
    }

    public function setDriverPhoneAttribute(?string $value): void
    {
        $this->attributes['driver_phone'] = \App\Support\PhoneNumberFormatter::format($value);
    }

    // ── Relationships ───────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function ebayOrder(): BelongsTo
    {
        return $this->belongsTo(EbayOrder::class)->withTrashed();
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withTrashed();
    }

    public function truckingCompany(): BelongsTo
    {
        return $this->belongsTo(TruckingCompany::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function updates(): HasMany
    {
        return $this->hasMany(LogisticUpdate::class)->orderByDesc('occurred_at');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeInTransit($query): mixed
    {
        return $query->where('status', LogisticStatus::InTransit->value);
    }

    public function scopeNeedsTruck($query): mixed
    {
        return $query->where('status', LogisticStatus::TruckSearching->value);
    }

    public function scopeSearch($query, string $term): mixed
    {
        return $query->where(function ($q) use ($term) {
            $q->where('order_id', 'like', "%{$term}%")
              ->orWhere('tracking_number', 'like', "%{$term}%")
              ->orWhere('recipient_name', 'like', "%{$term}%")
              ->orWhere('recipient_phone', 'like', "%{$term}%");
        });
    }

    // ── Accessors ────────────────────────────────────────────────────────────

    public function getDeliveryProofUrlAttribute(): ?string
    {
        return $this->delivery_proof ? asset('storage/' . $this->delivery_proof) : null;
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->estimated_arrival
            && $this->estimated_arrival->isPast()
            && ! $this->status?->isTerminal();
    }
}
