<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsitePost extends Model
{
    use HasFactory;

    protected $table = 'website_posts';

    const STATUS_PUBLISHED = 'published';
    const STATUS_DRAFT     = 'draft';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_ARCHIVED  = 'archived';

    const SOURCE_DGT_TRACKER        = 'dgt_tracker';
    const SOURCE_ELEMENTOR          = 'elementor_views';
    const SOURCE_NATIVE_DB          = 'native_database';
    const SOURCE_WP_POSTVIEWS       = 'wp_postviews';
    const SOURCE_JETPACK            = 'jetpack';
    const SOURCE_POST_VIEWS_COUNTER = 'post_views_counter';
    const SOURCE_GA4                = 'ga4';
    const SOURCE_CUSTOM_COUNTER     = 'custom_counter';
    const SOURCE_NONE               = 'none';

    protected $fillable = [
        'website_id',
        'external_id',
        'title',
        'slug',
        'canonical_url',
        'author_name',
        'status',
        'published_at',
        'scheduled_at',
        'modified_at',
        'featured_image_url',
        'excerpt',
        'content_html',
        'raw_content',
        'meta_title',
        'meta_description',
        'tag_keywords',
        'focus_keyword',
        'category',
        'playlist',
        'cumulative_views',
        'period_views',
        'page_views',
        'unique_visitors',
        'analytics_source',
        'metrics_data',
        'data_completeness',
        'sync_hash',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at'       => 'datetime',
            'scheduled_at'       => 'datetime',
            'modified_at'        => 'datetime',
            'last_synced_at'     => 'datetime',
            'cumulative_views'   => 'integer',
            'period_views'       => 'integer',
            'page_views'         => 'integer',
            'unique_visitors'    => 'integer',
            'metrics_data'       => 'array',
        ];
    }

    public function getContentHtmlAttribute(?string $value): ?string
    {
        return !empty($value) ? $value : ($this->metrics_data['cms_extra']['content_html'] ?? null);
    }

    public function getMetaTitleAttribute(?string $value): ?string
    {
        return !empty($value) ? $value : ($this->metrics_data['cms_extra']['meta_title'] ?? null);
    }

    public function getMetaDescriptionAttribute(?string $value): ?string
    {
        return !empty($value) ? $value : ($this->metrics_data['cms_extra']['meta_description'] ?? null);
    }

    public function getCategoryAttribute(?string $value): ?string
    {
        return !empty($value) ? $value : ($this->metrics_data['cms_extra']['category'] ?? null);
    }

    public function getPlaylistAttribute(?string $value): ?string
    {
        return !empty($value) ? $value : ($this->metrics_data['cms_extra']['playlist'] ?? null);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(WebsitePostAnalyticsSnapshot::class, 'post_id')
            ->orderByDesc('snapshot_date');
    }

    /**
     * Normalize any incoming or legacy status string to strictly one of:
     * 'published', 'scheduled', or 'draft'.
     */
    public static function normalizeStatus(?string $status): string
    {
        $s = strtolower(trim((string) $status));
        if (in_array($s, [self::STATUS_PUBLISHED, 'publish', 'public'], true)) {
            return self::STATUS_PUBLISHED;
        }
        if (in_array($s, [self::STATUS_SCHEDULED, 'future'], true)) {
            return self::STATUS_SCHEDULED;
        }
        return self::STATUS_DRAFT;
    }

    public function setStatusAttribute($value): void
    {
        $this->attributes['status'] = self::normalizeStatus($value);
    }

    public function getStatusAttribute(?string $value): string
    {
        return self::normalizeStatus($value);
    }

    public function scopePublished($query)
    {
        return $query->whereIn('status', [self::STATUS_PUBLISHED, 'publish', 'public']);
    }

    public function scopeScheduled($query)
    {
        return $query->whereIn('status', [self::STATUS_SCHEDULED, 'future']);
    }

    public function scopeDraft($query)
    {
        return $query->whereNotIn('status', [self::STATUS_PUBLISHED, 'publish', 'public', self::STATUS_SCHEDULED, 'future']);
    }

    public function scopeTopViewed($query)
    {
        return $query->orderByDesc('cumulative_views');
    }

    /**
     * Compute a SHA-256 fingerprint of the post content and metrics
     * to prevent needless DB updates on repeat syncs.
     */
    public static function computeSyncHash(array $data): string
    {
        return hash('sha256', implode('|', [
            $data['title'] ?? '',
            $data['canonical_url'] ?? '',
            $data['status'] ?? '',
            $data['published_at'] ?? '',
            $data['cumulative_views'] ?? 0,
            $data['period_views'] ?? 0,
            $data['page_views'] ?? 0,
            $data['analytics_source'] ?? '',
        ]));
    }
}
