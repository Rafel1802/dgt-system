<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'shipment_code', 'status',
        'trucking_company_id', 'created_by', 'assigned_to',
        'estimated_arrival', 'actual_arrival', 'notes',
    ];

    protected $casts = [
        'estimated_arrival' => 'date',
        'actual_arrival'    => 'date',
    ];

    // Possible statuses
    const STATUS_PENDING     = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_DELIVERED   = 'delivered';
    const STATUS_PROBLEM     = 'problem';

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING     => 'Pending',
            self::STATUS_IN_PROGRESS => 'In Progress',
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
        return match($this->status) {
            self::STATUS_PENDING     => '#94a3b8',
            self::STATUS_IN_PROGRESS => '#3b82f6',
            self::STATUS_DELIVERED   => '#22c55e',
            self::STATUS_PROBLEM     => '#ef4444',
            default                  => '#94a3b8',
        };
    }

    // ── Relationships ───────────────────────────────────────────────────────

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

    public function shipmentCustomers(): HasMany
    {
        return $this->hasMany(ShipmentCustomer::class);
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeSearch($query, string $term): mixed
    {
        return $query->where(function ($q) use ($term) {
            $q->where('shipment_code', 'like', "%{$term}%")
              ->orWhereHas('truckingCompany', fn($tq) => $tq->where('company_name', 'like', "%{$term}%"))
              ->orWhereHas('shipmentCustomers', fn($sq) => $sq->where('recipient_name', 'like', "%{$term}%"));
        });
    }
}
