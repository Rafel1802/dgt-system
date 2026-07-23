<?php

namespace App\Models;

use App\Enums\CustomerQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerWorkflowLog extends Model
{
    protected $fillable = [
        'customer_id', 'moved_by', 'feedback_category', 'from_queue', 'to_queue', 'reason',
    ];

    protected $casts = [
        'from_queue' => CustomerQueue::class,
        'to_queue'   => CustomerQueue::class,
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function mover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by')->withTrashed();
    }
}
