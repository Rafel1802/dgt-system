<?php

namespace App\Models\GoogleSearchConsole;

use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoogleSearchConsoleSite extends Model
{
    use HasFactory;

    protected $table = 'google_search_console_sites';

    protected $fillable = [
        'account_id',
        'website_id',
        'site_url',
        'permission_level',
        'is_verified',
        'is_sync_enabled',
        'last_synced_at',
        'last_data_date',
        'sync_status',
        'sync_error',
        'gsc_indexed_pages',
        'gsc_not_indexed_pages',
        'gsc_indexing_updated_at',
        'gsc_indexing_reasons',
    ];

    protected function casts(): array
    {
        return [
            'is_verified'             => 'boolean',
            'is_sync_enabled'         => 'boolean',
            'last_synced_at'          => 'datetime',
            'last_data_date'          => 'date',
            'gsc_indexed_pages'       => 'integer',
            'gsc_not_indexed_pages'   => 'integer',
            'gsc_indexing_updated_at' => 'date',
            'gsc_indexing_reasons'    => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(GoogleSearchConsoleAccount::class, 'account_id');
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class, 'website_id');
    }

    public function dailyMetrics(): HasMany
    {
        return $this->hasMany(GoogleSearchConsoleDailyMetric::class, 'website_id', 'website_id');
    }

    public function searchQueries(): HasMany
    {
        return $this->hasMany(GoogleSearchConsoleSearchQuery::class, 'website_id', 'website_id');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(GoogleSearchConsolePage::class, 'website_id', 'website_id');
    }

    public function countries(): HasMany
    {
        return $this->hasMany(GoogleSearchConsoleCountry::class, 'website_id', 'website_id');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(GoogleSearchConsoleDevice::class, 'website_id', 'website_id');
    }

    public function sitemaps(): HasMany
    {
        return $this->hasMany(GoogleSearchConsoleSitemap::class, 'website_id', 'website_id');
    }

    public function urlInspections(): HasMany
    {
        return $this->hasMany(GoogleSearchConsoleUrlInspection::class, 'website_id', 'website_id');
    }

    /**
     * Check if property is domain property (e.g. sc-domain:example.com)
     */
    public function isDomainProperty(): bool
    {
        return str_starts_with($this->site_url, 'sc-domain:');
    }

    /**
     * Displayable clean name for property
     */
    public function getDisplayNameAttribute(): string
    {
        if (str_starts_with($this->site_url, 'sc-domain:')) {
            return substr($this->site_url, 10) . ' (Domain)';
        }
        return $this->site_url;
    }
}
