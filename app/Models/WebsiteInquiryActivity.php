<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteInquiryActivity extends Model
{
    use HasFactory;

    protected $table = 'website_inquiry_activities';

    const TYPE_CREATED        = 'created';
    const TYPE_STATUS_CHANGED = 'status_changed';
    const TYPE_ASSIGNED       = 'assigned';
    const TYPE_NOTE_ADDED     = 'note_added';
    const TYPE_REPLIED        = 'replied';
    const TYPE_RETRIED        = 'retried';
    const TYPE_VIEWED         = 'viewed';

    protected $fillable = [
        'inquiry_id',
        'user_id',
        'activity_type',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(WebsiteInquiry::class, 'inquiry_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
