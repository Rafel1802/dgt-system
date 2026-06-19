<?php

namespace App\Models;

use App\Enums\LeadTemperature;
use App\Enums\WebsiteLeadStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadFollowUp extends Model
{
    protected $fillable = [
        'lead_id', 'user_id', 'notes', 'next_action',
        'follow_up_date', 'temperature', 'status_changed_to', 'contacted_at',
    ];

    protected $casts = [
        'temperature'      => LeadTemperature::class,
        'status_changed_to'=> WebsiteLeadStatus::class,
        'follow_up_date'   => 'date',
        'contacted_at'     => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
