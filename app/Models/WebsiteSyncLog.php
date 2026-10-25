<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteSyncLog extends Model
{
    use HasFactory;

    protected $table = 'website_sync_logs';

    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED  = 'failed';
    const STATUS_PARTIAL = 'partial';

    protected $fillable = [
        'website_id',
        'sync_type',
        'status',
        'items_synced',
        'items_updated',
        'items_failed',
        'duration_ms',
        'error_message',
        'request_ip',
        'payload_summary',
        'triggered_by',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'items_synced'    => 'integer',
            'items_updated'   => 'integer',
            'items_failed'    => 'integer',
            'duration_ms'     => 'integer',
            'payload_summary' => 'array',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
