<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallReport extends Model
{
    use HasFactory;

    const INQUIRY_TYPES = ['Inquiry', 'Technical', 'Wrong dial', 'Delivery status', 'Return missed', 'Followed up'];

    protected $fillable = [
        'name', 'phone', 'email', 'inquiry_type', 'answered_by', 'occurred_at', 'created_by',
    ];

    protected $casts = [
        'occurred_at' => 'date',
    ];

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
