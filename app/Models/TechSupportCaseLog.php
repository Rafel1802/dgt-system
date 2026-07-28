<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class TechSupportCaseLog extends Model
{
    const TYPE_FOLLOW_UP      = 'follow_up';
    const TYPE_CALL_COMPLETED = 'call_completed';
    const TYPE_REOPENED       = 'reopened';

    protected $fillable = [
        'tech_support_case_id', 'user_id', 'type', 'note',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(TechSupportCase::class, 'tech_support_case_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
