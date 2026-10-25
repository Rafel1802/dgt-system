<?php

namespace App\Models\GoogleSearchConsole;

use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleSearchConsoleSyncLog extends Model
{
    use HasFactory;

    protected $table = 'google_search_console_sync_logs';

    protected $fillable = [
        'website_id',
        'site_url',
        'started_at',
        'finished_at',
        'status',
        'records_synced',
        'start_date',
        'end_date',
        'message',
        'triggered_by',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at'     => 'datetime',
            'finished_at'    => 'datetime',
            'start_date'     => 'date',
            'end_date'       => 'date',
            'records_synced' => 'integer',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class, 'website_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
