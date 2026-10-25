<?php

namespace App\Models\GoogleSearchConsole;

use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class GoogleSearchConsoleUrlInspection extends Model
{
    use HasFactory;

    protected $table = 'google_search_console_url_inspections';

    protected $fillable = [
        'website_id',
        'site_url',
        'url',
        'url_hash',
        'verdict',
        'coverage_state',
        'is_indexed',
        'indexing_state',
        'page_fetch_state',
        'robots_txt_state',
        'crawled_as',
        'last_crawl_time',
        'google_canonical',
        'user_canonical',
        'sitemap_url',
        'mobile_verdict',
        'issues',
        'raw_data',
        'inspected_by',
        'inspected_at',
    ];

    protected function casts(): array
    {
        return [
            'is_indexed'       => 'boolean',
            'last_crawl_time'  => 'datetime',
            'inspected_at'     => 'datetime',
            'issues'           => 'array',
            'raw_data'         => 'array',
        ];
    }

    /**
     * Self-healing helper: ensure table exists before querying
     */
    public static function ensureTableExists(): void
    {
        if (!Schema::hasTable('google_search_console_url_inspections')) {
            Schema::create('google_search_console_url_inspections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
                $table->string('site_url')->index();
                $table->text('url');
                $table->char('url_hash', 40)->index();
                $table->string('verdict', 30)->default('PASS')->index();
                $table->string('coverage_state', 255)->default('Submitted and indexed')->index();
                $table->boolean('is_indexed')->default(true)->index();
                $table->string('indexing_state', 100)->nullable();
                $table->string('page_fetch_state', 100)->nullable();
                $table->string('robots_txt_state', 50)->nullable();
                $table->string('crawled_as', 50)->nullable();
                $table->timestamp('last_crawl_time')->nullable();
                $table->text('google_canonical')->nullable();
                $table->text('user_canonical')->nullable();
                $table->text('sitemap_url')->nullable();
                $table->string('mobile_verdict', 30)->nullable();
                $table->json('issues')->nullable();
                $table->json('raw_data')->nullable();
                $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('inspected_at')->nullable()->index();
                $table->timestamps();

                $table->unique(['website_id', 'url_hash'], 'gsc_url_inspections_site_hash_unique');
                $table->index(['website_id', 'is_indexed'], 'gsc_url_inspections_indexed_idx');
            });
        }
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class, 'website_id');
    }

    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }
}
