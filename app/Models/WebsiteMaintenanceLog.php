<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteMaintenanceLog extends Model
{
    protected $with = ['user'];

    protected $fillable = [
        'website_id', 'user_id', 'action', 'note',
        'old_status', 'new_status', 'old_progress', 'new_progress',
        'attachment_path', 'attachment_name', 'attachments',
    ];

    protected $casts = [
        'old_progress' => 'integer',
        'new_progress' => 'integer',
        'attachments'  => 'array',
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
