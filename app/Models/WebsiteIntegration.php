<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WebsiteIntegration extends Model
{
    use HasFactory;

    protected $table = 'website_integrations';

    const CONNECTOR_WP_PLUGIN    = 'wordpress_plugin';
    const CONNECTOR_WP_REST      = 'wordpress_rest';
    const CONNECTOR_LARAVEL      = 'custom_laravel';
    const CONNECTOR_PHP          = 'custom_php';
    const CONNECTOR_API          = 'custom_api';
    const CONNECTOR_NONE         = 'none';

    const CMS_STATUS_CONNECTED   = 'connected';
    const CMS_STATUS_DISCONNECTED = 'disconnected';
    const CMS_STATUS_ERROR       = 'error';
    const CMS_STATUS_UNVERIFIED  = 'unverified';

    const ANALYTICS_STATUS_CONNECTED     = 'connected';
    const ANALYTICS_STATUS_DISCONNECTED  = 'disconnected';
    const ANALYTICS_STATUS_ERROR        = 'error';
    const ANALYTICS_STATUS_NOT_CONFIGURED = 'not_configured';

    protected $fillable = [
        'website_id',
        'connector_type',
        'api_key',
        'api_secret',
        'webhook_secret',
        'endpoint_url',
        'is_verified',
        'verification_token',
        'verified_at',
        'verification_method',
        'cms_status',
        'analytics_status',
        'ga4_property_id',
        'ga4_credentials_json',
        'tech_stack',
        'hostinger_account',
        'last_sync_at',
        'last_sync_status',
        'last_sync_error',
        'last_ping_at',
        'sync_frequency_minutes',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'api_secret',
        'webhook_secret',
        'ga4_credentials_json',
    ];

    protected function casts(): array
    {
        return [
            'is_verified'             => 'boolean',
            'is_active'               => 'boolean',
            'verified_at'             => 'datetime',
            'last_sync_at'            => 'datetime',
            'last_ping_at'            => 'datetime',
            'api_secret'              => 'encrypted',
            'webhook_secret'          => 'encrypted',
            'ga4_credentials_json'    => 'encrypted',
            'tech_stack'              => 'array',
            'sync_frequency_minutes'  => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(WebsiteSyncLog::class, 'website_id', 'website_id')
            ->orderByDesc('created_at');
    }

    // ─── Helpers & Credential Management ──────────────────────────────────────

    /**
     * Generate fresh API credentials.
     */
    public static function createDefaultCredentials(): array
    {
        return [
            'api_key'            => 'dgt_live_' . Str::random(32),
            'api_secret'         => 'dgt_sec_' . Str::random(48),
            'webhook_secret'     => 'dgt_wh_' . Str::random(32),
            'verification_token' => 'dgt_verify_' . Str::random(24),
        ];
    }

    /**
     * Rotate credentials and return newly generated plaintext keys for display.
     */
    public function rotateCredentials(): array
    {
        $newCredentials = self::createDefaultCredentials();

        $this->update([
            'api_key'            => $newCredentials['api_key'],
            'api_secret'         => $newCredentials['api_secret'],
            'webhook_secret'     => $newCredentials['webhook_secret'],
            'verification_token' => $newCredentials['verification_token'],
        ]);

        return $newCredentials;
    }

    /**
     * Verify HMAC SHA-256 signature for incoming connector request.
     */
    public function verifySignature(string $signature, string $timestamp, string $payload): bool
    {
        // Reject stale requests (drift > 300 seconds)
        if (abs(time() - (int)$timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $this->api_secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Masked API Key for safe UI display (e.g. dgt_live_abcd...ef12).
     */
    public function getMaskedApiKeyAttribute(): string
    {
        if (empty($this->api_key)) {
            return 'None';
        }
        $len = strlen($this->api_key);
        if ($len <= 16) {
            return $this->api_key;
        }
        return substr($this->api_key, 0, 12) . '••••••••' . substr($this->api_key, -4);
    }

    public function isCmsConnected(): bool
    {
        return $this->cms_status === self::CMS_STATUS_CONNECTED && $this->is_active;
    }

    public function isAnalyticsConnected(): bool
    {
        return $this->analytics_status === self::ANALYTICS_STATUS_CONNECTED && $this->is_active;
    }
}
