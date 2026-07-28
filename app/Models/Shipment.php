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
        'trucking_company_id', 'driver_id', 'created_by', 'assigned_to',
        'estimated_arrival', 'actual_arrival', 'notes',
    ];

    protected $casts = [
        'estimated_arrival' => 'date',
        'actual_arrival'    => 'date',
    ];

    // Possible statuses
    const STATUS_PENDING     = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETE    = 'complete';
    const STATUS_PROBLEM     = 'problem';

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING     => 'Pending',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETE    => 'Complete',
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
            self::STATUS_COMPLETE    => '#22c55e',
            self::STATUS_PROBLEM     => '#ef4444',
            default                  => '#94a3b8',
        };
    }

    // ── Relationships ───────────────────────────────────────────────────────

    public function truckingCompany(): BelongsTo
    {
        return $this->belongsTo(TruckingCompany::class)->withTrashed();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(TruckingCompanyDriver::class, 'driver_id');
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

    /** Per-status customer counts, e.g. ['delivered' => 1, 'problem' => 2] — only non-empty statuses included. */
    public function customerStatusCounts(): array
    {
        return $this->shipmentCustomers()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
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
