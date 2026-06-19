<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteQcCheck extends Model
{
    // Default QC checklist items (key => label)
    const DEFAULT_CHECKLIST = [
        'domain_works'         => 'Domain works correctly',
        'logo_correct'         => 'Logo is correct & displays properly',
        'homepage_checked'     => 'Homepage checked',
        'product_pages'        => 'Product pages checked',
        'mobile_responsive'    => 'Mobile responsive',
        'seo_meta'             => 'SEO title & meta description checked',
        'contact_form'         => 'Contact form works',
        'speed_checked'        => 'Page speed acceptable',
        'no_broken_links'      => 'No broken links',
    ];

    protected $fillable = [
        'website_id', 'checklist_key', 'checklist_label',
        'is_checked', 'checked_by', 'checked_at',
    ];

    protected $casts = [
        'is_checked' => 'boolean',
        'checked_at' => 'datetime',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    /**
     * Seed the default QC checklist for a website (idempotent).
     */
    public static function seedForWebsite(int $websiteId): void
    {
        foreach (self::DEFAULT_CHECKLIST as $key => $label) {
            self::firstOrCreate(
                ['website_id' => $websiteId, 'checklist_key' => $key],
                ['checklist_label' => $label, 'is_checked' => false]
            );
        }
    }
}
