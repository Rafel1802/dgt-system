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
        $clearCache = fn () => \Illuminate\Support\Facades\Cache::forget('logistic_pending_returns_count');
        static::created($clearCache);
        static::deleted($clearCache);

        static::updated(function (self $return) use ($clearCache) {
            if ($return->wasChanged('status')) {
                $clearCache();
            }

            if ($return->wasChanged('status')) {
                if ($return->customer) {
                    \App\Services\UniversalStatusSyncService::syncLogisticStatus($return->customer, $return->status);
                }
                
                // Sync to Tech Support Case if it's marked as received
                if ($return->status === self::STATUS_RECEIVED && $return->tech_support_case_id) {
                    if ($case = $return->techSupportCase) {
                        $case->update(['status' => \App\Models\TechSupportCase::STATUS_RETURN_RECEIVED]);
                        
                        \App\Models\TechSupportCaseLog::create([
                            'tech_support_case_id' => $case->id,
                            'user_id' => auth()->id() ?? 1,
                            'type' => 'status_update',
                            'note' => 'Machine Return marked as Received by Logistics',
                            'previous_status' => $case->getOriginal('status'),
                        ]);
                    }
                }
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
