<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsitePostAnalyticsSnapshot extends Model
{
    use HasFactory;

    protected $table = 'website_post_analytics_snapshots';

    protected $fillable = [
        'website_id',
        'post_id',
        'snapshot_date',
        'views',
        'page_views',
        'unique_visitors',
        'source',
        'raw_metrics',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date'    => 'date',
            'views'            => 'integer',
            'page_views'       => 'integer',
            'unique_visitors'  => 'integer',
            'raw_metrics'      => 'array',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(WebsitePost::class, 'post_id');
    }
}
