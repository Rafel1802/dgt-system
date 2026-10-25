<?php

namespace App\Models\GoogleSearchConsole;

use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class GoogleSearchConsoleSitemap extends Model
{
    use HasFactory;

    protected $table = 'google_search_console_sitemaps';

    protected $fillable = [
        'website_id',
        'site_url',
        'sitemap_url',
        'type',
        'is_pending',
        'is_sitemaps_index',
        'last_submitted_at',
        'last_downloaded_at',
        'submitted_count',
        'indexed_count',
        'errors_count',
        'warnings_count',
        'status',
        'error_message',
        'submitted_by',
    ];

    protected function casts(): array
    {
        return [
            'is_pending'         => 'boolean',
            'is_sitemaps_index'  => 'boolean',
            'last_submitted_at'  => 'datetime',
            'last_downloaded_at' => 'datetime',
            'submitted_count'    => 'integer',
            'indexed_count'      => 'integer',
            'errors_count'       => 'integer',
            'warnings_count'     => 'integer',
        ];
    }

    /**
     * Self-healing helper: ensure table exists before querying
     */
    public static function ensureTableExists(): void
    {
        if (!Schema::hasTable('google_search_console_sitemaps')) {
            Schema::create('google_search_console_sitemaps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
                $table->string('site_url')->index();
                $table->string('sitemap_url', 1000);
                $table->string('type', 50)->default('SITEMAP');
                $table->boolean('is_pending')->default(false);
                $table->boolean('is_sitemaps_index')->default(false);
                $table->timestamp('last_submitted_at')->nullable();
                $table->timestamp('last_downloaded_at')->nullable();
                $table->unsignedInteger('submitted_count')->default(0);
                $table->unsignedInteger('indexed_count')->default(0);
                $table->unsignedInteger('errors_count')->default(0);
                $table->unsignedInteger('warnings_count')->default(0);
                $table->string('status', 30)->default('success')->index();
                $table->text('error_message')->nullable();
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['website_id', 'status'], 'gsc_sitemaps_site_status_idx');
            });
        }
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class, 'website_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
