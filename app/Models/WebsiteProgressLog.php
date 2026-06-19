<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteProgressLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['website_id', 'user_id', 'percent', 'note', 'created_at'];

    protected $casts = [
        'percent'    => 'integer',
        'created_at' => 'datetime',
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
