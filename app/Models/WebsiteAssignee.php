<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteAssignee extends Model
{
    use HasFactory;

    protected $table = 'website_assignees';

    protected $fillable = [
        'website_id',
        'user_id',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class, 'website_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
