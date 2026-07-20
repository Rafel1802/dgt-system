<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShipmentCustomer extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id', 'customer_id',
        'recipient_name', 'recipient_phone', 'recipient_email', 'shipping_address',
        'status', 'handled_by', 'notes', 'tracking_number',
    ];

    const STATUS_PENDING     = 'pending';
    const STATUS_IN_TRANSIT  = 'in_transit';
    const STATUS_IN_DELIVERY = 'in_delivery';
    const STATUS_DELIVERED   = 'delivered';
    const STATUS_PROBLEM     = 'problem';

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING     => 'Pending',
            self::STATUS_IN_TRANSIT  => 'Loaded',
            self::STATUS_IN_DELIVERY => 'In Delivery',
            self::STATUS_DELIVERED   => 'Delivered',
            self::STATUS_PROBLEM     => 'Problem / Delay',
        ];
    }

    public function statusLabel(): string
    {
        return self::statuses()[$this->status] ?? ucfirst($this->status);
    }

    public function statusColor(): string
    {
        return self::colorForStatus($this->status);
    }

    public static function colorForStatus(string $status): string
    {
        return match($status) {
            self::STATUS_PENDING     => '#94a3b8',
            self::STATUS_IN_TRANSIT  => '#3b82f6',
            self::STATUS_IN_DELIVERY => '#06b6d4',
            self::STATUS_DELIVERED   => '#22c55e',
            self::STATUS_PROBLEM     => '#ef4444',
            default                  => '#94a3b8',
        };
    }

    public function setRecipientPhoneAttribute(?string $value): void
    {
        $this->attributes['recipient_phone'] = \App\Support\PhoneNumberFormatter::format($value);
    }

    // ── Relationships ───────────────────────────────────────────────────────

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by')->withTrashed();
    }

    public function products(): HasMany
    {
        return $this->hasMany(ShipmentCustomerProduct::class);
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeSearch($query, string $term): mixed
    {
        return $query->where(function ($q) use ($term) {
            $q->where('recipient_name', 'like', "%{$term}%")
              ->orWhere('recipient_phone', 'like', "%{$term}%")
              ->orWhere('tracking_number', 'like', "%{$term}%")
              ->orWhereHas('shipment', fn ($sq) => $sq->where('shipment_code', 'like', "%{$term}%"));
        });
    }
}
