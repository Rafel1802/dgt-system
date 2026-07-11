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
        'name', 'phone', 'email', 'inquiry_type', 'answered_by', 'occurred_at', 'details', 'created_by',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * Shared search + date-range + answered-by filtering, used by both the
     * authenticated Call Reports page and the public share-link view so the
     * two never drift apart.
     */
    public function scopeFiltered($query, array $filters): mixed
    {
        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%");
            });
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('occurred_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('occurred_at', '<=', $filters['date_to']);
        }
        if (! empty($filters['answered_by'])) {
            $query->where('answered_by', $filters['answered_by']);
        }

        return $query;
    }
}
