<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteMaintenanceLog extends Model
{
    protected $fillable = [
        'website_id', 'user_id', 'action', 'note',
        'old_status', 'new_status', 'old_progress', 'new_progress',
    ];

    protected $casts = [
        'old_progress' => 'integer',
        'new_progress' => 'integer',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
