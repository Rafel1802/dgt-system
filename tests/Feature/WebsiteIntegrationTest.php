<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteIntegration;
use App\Models\WebsitePost;
use App\Models\WebsitePostAnalyticsSnapshot;
use App\Services\Security\SafeUrlValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WebsiteIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Website $website;
    private WebsiteIntegration $integration;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'admin-digital']);

        $this->user = User::factory()->create([
            'is_active' => true,
        ]);
        $this->user->assignRole('admin-digital');

        $this->website = Website::create([
            'name'       => 'Test Excavator Site',
            'url'        => 'https://example-excavators.com',
            'category'   => '1st Class',
            'handled_by' => $this->user->id,
            'status'     => Website::STATUS_LIVE,
        ]);

        $keys = WebsiteIntegration::createDefaultCredentials();

        $this->integration = WebsiteIntegration::create([
            'website_id'             => $this->website->id,
            'connector_type'         => WebsiteIntegration::CONNECTOR_WP_PLUGIN,
            'api_key'                => $keys['api_key'],
            'api_secret'             => $keys['api_secret'],
            'webhook_secret'         => $keys['webhook_secret'],
            'verification_token'     => $keys['verification_token'],
            'hostinger_account'      => 'Hostinger Account 1',
            'cms_status'             => WebsiteIntegration::CMS_STATUS_DISCONNECTED,
            'analytics_status'       => WebsiteIntegration::ANALYTICS_STATUS_NOT_CONFIGURED,
            'is_active'              => true,
            'sync_frequency_minutes' => 60,
        ]);
    }

    public function test_handshake_succeeds_with_valid_hmac_signature(): void
    {
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.', $this->integration->api_secret);

        $response = $this->withHeaders([
            'X-DGT-Key'       => $this->integration->api_key,
            'X-DGT-Timestamp' => $timestamp,
            'X-DGT-Signature' => $signature,
        ])->getJson('/api/v1/connector/handshake');

        $response->assertStatus(200)
            ->assertJson([
                'success'      => true,
                'website_id'   => $this->website->id,
                'website_name' => 'Test Excavator Site',
                'api_version'  => 'v1',
            ]);

        $this->integration->refresh();
        $this->assertEquals(WebsiteIntegration::CMS_STATUS_CONNECTED, $this->integration->cms_status);
        $this->assertNotNull($this->integration->last_ping_at);
    }

    public function test_handshake_fails_with_invalid_signature(): void
    {
        $timestamp = (string) time();

        $response = $this->withHeaders([
            'X-DGT-Key'       => $this->integration->api_key,
            'X-DGT-Timestamp' => $timestamp,
            'X-DGT-Signature' => 'invalid_forged_signature_hex',
        ])->getJson('/api/v1/connector/handshake');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error'   => 'invalid_signature',
            ]);
    }

    public function test_handshake_fails_with_expired_timestamp(): void
    {
        $timestamp = (string) (time() - 600); // 10 minutes in the past
        $signature = hash_hmac('sha256', $timestamp . '.', $this->integration->api_secret);

        $response = $this->withHeaders([
            'X-DGT-Key'       => $this->integration->api_key,
            'X-DGT-Timestamp' => $timestamp,
            'X-DGT-Signature' => $signature,
        ])->getJson('/api/v1/connector/handshake');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error'   => 'timestamp_expired',
            ]);
    }

    public function test_replay_protection_blocks_duplicate_nonce(): void
    {
        $timestamp = (string) time();
        $nonce = 'unique_nonce_' . uniqid();
        $signature = hash_hmac('sha256', $timestamp . '.', $this->integration->api_secret);

        $headers = [
            'X-DGT-Key'       => $this->integration->api_key,
            'X-DGT-Timestamp' => $timestamp,
            'X-DGT-Nonce'     => $nonce,
            'X-DGT-Signature' => $signature,
        ];

        // First attempt succeeds
        $firstResponse = $this->withHeaders($headers)->getJson('/api/v1/connector/handshake');
        $firstResponse->assertStatus(200);

        // Immediate second attempt with same nonce is blocked
        $secondResponse = $this->withHeaders($headers)->getJson('/api/v1/connector/handshake');
        $secondResponse->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error'   => 'replay_detected',
            ]);
    }

    public function test_website_id_mismatch_is_rejected(): void
    {
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.', $this->integration->api_secret);

        // Attacker claims to be website 99999
        $response = $this->withHeaders([
            'X-DGT-Key'       => $this->integration->api_key,
            'X-DGT-Timestamp' => $timestamp,
            'X-DGT-Signature' => $signature,
        ])->getJson('/api/v1/connector/handshake?website_id=99999');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error'   => 'website_mismatch',
            ]);
    }

    public function test_blog_posts_idempotent_sync_prevents_duplicate_posts_and_inflated_views(): void
    {
        $postsPayload = [
            'posts' => [
                [
                    'external_id'      => '101',
                    'title'            => 'Top 10 Excavator Attachments',
                    'slug'             => 'top-10-excavator-attachments',
                    'canonical_url'    => 'https://example-excavators.com/blog/attachments',
                    'author_name'      => 'Jane Specialist',
                    'status'           => 'published',
                    'published_at'     => '2026-10-20 10:00:00',
                    'cumulative_views' => 1250,
                    'period_views'     => 450,
                    'analytics_source' => 'wp_postviews',
                ],
                [
                    'external_id'      => '102',
                    'title'            => 'Daily Maintenance Checklist',
                    'slug'             => 'daily-maintenance-checklist',
                    'canonical_url'    => 'https://example-excavators.com/blog/maintenance-checklist',
                    'author_name'      => 'Bob Engineer',
                    'status'           => 'published',
                    'published_at'     => '2026-10-22 14:30:00',
                    'cumulative_views' => 890,
                    'period_views'     => 310,
                    'analytics_source' => 'wp_postviews',
                ],
            ],
            'sync_source' => 'test_suite',
        ];

        $rawJson = json_encode($postsPayload);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $rawJson, $this->integration->api_secret);

        // 1. First sync run
        $firstResponse = $this->withHeaders([
            'X-DGT-Key'       => $this->integration->api_key,
            'X-DGT-Timestamp' => $timestamp,
            'X-DGT-Signature' => $signature,
        ])->postJson('/api/v1/connector/sync/posts', $postsPayload);

        $firstResponse->assertStatus(200)
            ->assertJson([
                'success'      => true,
                'items_synced' => 2,
            ]);

        $this->assertEquals(2, WebsitePost::where('website_id', $this->website->id)->count());
        $post1 = WebsitePost::where('website_id', $this->website->id)->where('external_id', '101')->first();
        $this->assertEquals(1250, $post1->cumulative_views);

        // 2. Second sync run with identical payload (idempotent)
        $secondResponse = $this->withHeaders([
            'X-DGT-Key'       => $this->integration->api_key,
            'X-DGT-Timestamp' => $timestamp,
            'X-DGT-Signature' => $signature,
        ])->postJson('/api/v1/connector/sync/posts', $postsPayload);

        $secondResponse->assertStatus(200)
            ->assertJson([
                'success'       => true,
                'items_synced'  => 0,
                'items_skipped' => 2,
            ]);

        // Verify total posts did NOT duplicate and views did NOT inflate
        $this->assertEquals(2, WebsitePost::where('website_id', $this->website->id)->count());
        $post1->refresh();
        $this->assertEquals(1250, $post1->cumulative_views);
    }

    public function test_daily_analytics_snapshots_are_recorded(): void
    {
        $postsPayload = [
            'posts' => [
                [
                    'external_id'      => '201',
                    'title'            => 'Hydraulic Oil Best Practices',
                    'canonical_url'    => 'https://example-excavators.com/blog/hydraulic-oil',
                    'cumulative_views' => 500,
                    'analytics_source' => 'custom_counter',
                    'daily_snapshots'  => [
                        [
                            'date'             => '2026-10-24',
                            'views'            => 120,
                            'page_views'       => 120,
                            'unique_visitors'  => 85,
                        ],
                        [
                            'date'             => '2026-10-25',
                            'views'            => 140,
                            'page_views'       => 140,
                            'unique_visitors'  => 95,
                        ],
                    ],
                ],
            ],
        ];

        $rawJson = json_encode($postsPayload);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $rawJson, $this->integration->api_secret);

        $response = $this->withHeaders([
            'X-DGT-Key'       => $this->integration->api_key,
            'X-DGT-Timestamp' => $timestamp,
            'X-DGT-Signature' => $signature,
        ])->postJson('/api/v1/connector/sync/posts', $postsPayload);

        $response->assertStatus(200);

        $this->assertEquals(2, WebsitePostAnalyticsSnapshot::where('website_id', $this->website->id)->count());
    }

    public function test_ssrf_validator_blocks_private_and_cloud_metadata_ips(): void
    {
        // 127.0.0.1
        $this->assertTrue(SafeUrlValidator::isPrivateIp('127.0.0.1'));
        // 10.0.0.1
        $this->assertTrue(SafeUrlValidator::isPrivateIp('10.0.0.1'));
        // 192.168.1.100
        $this->assertTrue(SafeUrlValidator::isPrivateIp('192.168.1.100'));
        // AWS/GCP Cloud Metadata 169.254.169.254
        $this->assertTrue(SafeUrlValidator::isPrivateIp('169.254.169.254'));
        // Public IP
        $this->assertFalse(SafeUrlValidator::isPrivateIp('8.8.8.8'));
    }

    public function test_credential_rotation_creates_new_keys_and_revokes_old(): void
    {
        $oldKey = $this->integration->api_key;
        $oldSecret = $this->integration->api_secret;

        $newKeys = $this->integration->rotateCredentials();

        $this->integration->refresh();
        $this->assertNotEquals($oldKey, $this->integration->api_key);
        $this->assertEquals($newKeys['api_key'], $this->integration->api_key);

        // Old key should fail
        $timestamp = (string) time();
        $oldSignature = hash_hmac('sha256', $timestamp . '.', $oldSecret);

        $response = $this->withHeaders([
            'X-DGT-Key'       => $oldKey,
            'X-DGT-Timestamp' => $timestamp,
            'X-DGT-Signature' => $oldSignature,
        ])->getJson('/api/v1/connector/handshake');

        $response->assertStatus(401);
    }

    public function test_web_dashboard_routes_accessible_to_authorized_users(): void
    {
        $dara = User::factory()->create([
            'username'  => 'dara',
            'name'      => 'Mr. Dara',
            'is_active' => true,
        ]);

        $this->actingAs($dara);

        $response1 = $this->get('/websites/integrations');
        $response1->assertStatus(200);

        $response2 = $this->get('/websites/blogs');
        $response2->assertStatus(200);

        $csvResponse = $this->get('/websites/blogs/export/csv');
        $csvResponse->assertStatus(200);
        $this->assertStringContainsString('text/csv', $csvResponse->headers->get('content-type'));
    }

    public function test_central_connectors_forbidden_for_non_dara_and_non_superadmin_users(): void
    {
        $regularUser = User::factory()->create([
            'username'  => 'standard_user',
            'name'      => 'Standard User',
            'is_active' => true,
        ]);
        $regularUser->assignRole('admin-digital');

        $this->actingAs($regularUser);

        $response = $this->get('/websites/integrations');
        $response->assertStatus(403);

        $unsyncResponse = $this->postJson("/websites/{$this->website->id}/integration/unsync");
        $unsyncResponse->assertStatus(403);
    }

    public function test_unsync_disconnects_website_and_clears_posts(): void
    {
        $dara = User::factory()->create([
            'username'  => 'dara',
            'name'      => 'Mr. Dara',
            'is_active' => true,
        ]);

        // Create sample posts
        WebsitePost::create([
            'website_id'       => $this->website->id,
            'external_id'      => '999',
            'title'            => 'Temporary Post for Unsync Test',
            'slug'             => 'temporary-unsync-post',
            'canonical_url'    => 'https://example-excavators.com/temporary-unsync-post',
            'status'           => 'published',
            'cumulative_views' => 10,
        ]);

        $this->assertEquals(1, WebsitePost::where('website_id', $this->website->id)->count());

        $this->actingAs($dara);

        $response = $this->postJson("/websites/{$this->website->id}/integration/unsync", [
            'clear_posts' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success'       => true,
                'website_id'    => $this->website->id,
                'posts_deleted' => 1,
            ]);

        $this->integration->refresh();
        $this->assertEquals(WebsiteIntegration::CMS_STATUS_DISCONNECTED, $this->integration->cms_status);
        $this->assertFalse($this->integration->is_active);
        $this->assertEquals('unsynced', $this->integration->last_sync_status);

        // Verify posts were cleared
        $this->assertEquals(0, WebsitePost::where('website_id', $this->website->id)->count());
    }
}
