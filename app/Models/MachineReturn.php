<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MachineReturn extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tech_support_case_id', 'customer_id', 'status', 'handled_by', 'notes',
    ];

    protected static function booted(): void
    {
        static::updated(function (self $return) {
            if ($return->wasChanged('status') && $return->customer) {
                \App\Services\UniversalStatusSyncService::syncLogisticStatus($return->customer, $return->status);
            }
        });
    }

    public const STATUS_PENDING = 'pending';
    public const STATUS_PICKUP_ARRANGED = 'pickup_arranged';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_RECEIVED = 'received';

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PICKUP_ARRANGED => 'Pickup Arranged',
            self::STATUS_IN_TRANSIT => 'In Transit',
            self::STATUS_RECEIVED => 'Received',
        ];
    }

    public function statusColor(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => '#f59e0b', // amber
            self::STATUS_PICKUP_ARRANGED => '#3b82f6', // blue
            self::STATUS_IN_TRANSIT => '#0ea5e9', // sky
            self::STATUS_RECEIVED => '#10b981', // emerald
            default => '#94a3b8',
        };
    }

    public function statusLabel(): string
    {
        return self::statuses()[$this->status] ?? ucfirst($this->status);
    }

    public function techSupportCase()
    {
        return $this->belongsTo(TechSupportCase::class)->withTrashed();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by')->withTrashed();
    }

    public function logs()
    {
        return $this->hasMany(MachineReturnLog::class)->orderByDesc('created_at');
    }
}
