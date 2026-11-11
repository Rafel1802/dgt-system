<?php

namespace Tests\Feature;

use App\Events\NewInquiryReceived;
use App\Models\Role;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteInquiry;
use App\Models\WebsiteInquiryActivity;
use App\Models\WebsiteIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebsiteInquiryTest extends TestCase
{
    use RefreshDatabase;

    protected Website $website;
    protected WebsiteIntegration $integration;
    protected string $apiKey;
    protected string $apiSecret;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin-digital']);

        $this->apiKey = 'dgt_test_key_' . Str::random(16);
        $this->apiSecret = 'dgt_test_sec_' . Str::random(32);

        $this->website = Website::create([
            'name'        => 'Test Machinery Co',
            'url'         => 'https://testmachinery.example.com',
            'status'      => Website::STATUS_LIVE,
            'is_archived' => false,
        ]);

        $this->integration = WebsiteIntegration::create([
            'website_id'      => $this->website->id,
            'connector_type'  => WebsiteIntegration::CONNECTOR_WP_PLUGIN,
            'tech_stack'      => 'WordPress + Elementor',
            'api_key'         => $this->apiKey,
            'api_secret'      => $this->apiSecret,
            'is_active'       => true,
            'is_verified'     => true,
            'cms_status'      => WebsiteIntegration::CMS_STATUS_CONNECTED,
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Helper to sign request with HMAC SHA-256.
     */
    protected function signPayload(array $payload): array
    {
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(8));
        $jsonPayload = json_encode($payload);
        $signature = hash_hmac('sha256', $timestamp . '.' . $jsonPayload, $this->apiSecret);

        return [
            'headers' => [
                'X-DGT-Key'       => $this->apiKey,
                'X-DGT-Signature' => $signature,
                'X-DGT-Timestamp' => $timestamp,
                'X-DGT-Nonce'     => $nonce,
                'Accept'          => 'application/json',
                'Content-Type'    => 'application/json',
            ],
            'json' => $jsonPayload,
        ];
    }

    public function test_unauthenticated_inquiry_request_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/inquiries', [
            'customer_name' => 'Anonymous Hacker',
        ]);

        $response->assertStatus(401);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/inquiries', [
            'customer_name' => 'John Doe',
        ], [
            'X-DGT-Key'       => $this->apiKey,
            'X-DGT-Signature' => 'invalid_sha256_signature',
            'X-DGT-Timestamp' => (string) time(),
        ]);

        $response->assertStatus(401);
    }

    public function test_valid_hmac_submission_creates_inquiry_and_broadcasts(): void
    {
        Event::fake([NewInquiryReceived::class]);

        $payload = [
            'customer_name'    => 'Robert Vance',
            'customer_email'   => 'robert@vancerefrigeration.com',
            'customer_phone'   => '+1-555-0192',
            'customer_company' => 'Vance Refrigeration',
            'subject'          => 'Quote for 5 Commercial Compressors',
            'message'          => 'Please send pricing and delivery timeline for model XYZ.',
            'product_service'  => 'Commercial Compressors',
            'inquiry_type'     => 'quote_request',
            'form_name'        => 'Request a Quote',
            'source_url'       => 'https://testmachinery.example.com/products/compressor-xyz',
            'idempotency_key'  => 'vance_req_001',
        ];

        $signed = $this->signPayload($payload);

        $response = $this->postJson('/api/v1/inquiries', $payload, $signed['headers']);

        $response->assertStatus(201)
            ->assertJson([
                'success'      => true,
                'inquiry_type' => 'quote_request',
                'status'       => 'new',
            ]);

        $inquiryId = $response->json('inquiry_id');
        $this->assertNotNull($inquiryId);

        $this->assertDatabaseHas('website_inquiries', [
            'id'               => $inquiryId,
            'website_id'       => $this->website->id,
            'customer_name'    => 'Robert Vance',
            'customer_email'   => 'robert@vancerefrigeration.com',
            'customer_phone'   => '+1-555-0192',
            'customer_company' => 'Vance Refrigeration',
            'inquiry_type'     => 'quote_request',
            'status'           => 'new',
            'idempotency_key'  => 'vance_req_001',
        ]);

        $this->assertDatabaseHas('website_inquiry_activities', [
            'inquiry_id'    => $inquiryId,
            'activity_type' => 'created',
        ]);

        Event::assertDispatched(NewInquiryReceived::class, function ($event) use ($inquiryId) {
            return $event->inquiry->id === $inquiryId;
        });
    }

    public function test_idempotent_inquiry_prevents_duplicate_records(): void
    {
        $payload = [
            'customer_name'   => 'Alice Walker',
            'customer_email'  => 'alice@example.com',
            'message'         => 'First submission attempt',
            'idempotency_key' => 'idemp_key_unique_123',
        ];

        $signed1 = $this->signPayload($payload);
        $res1 = $this->postJson('/api/v1/inquiries', $payload, $signed1['headers']);
        $res1->assertStatus(201);
        $firstId = $res1->json('inquiry_id');

        // Send second request with identical idempotency key (e.g. network retry)
        $signed2 = $this->signPayload($payload);
        $res2 = $this->postJson('/api/v1/inquiries', $payload, $signed2['headers']);
        $res2->assertStatus(201);
        $secondId = $res2->json('inquiry_id');

        $this->assertEquals($firstId, $secondId, 'Idempotent request must return the existing inquiry record');
        $this->assertEquals(1, WebsiteInquiry::where('website_id', $this->website->id)->where('idempotency_key', 'idemp_key_unique_123')->count());
    }

    public function test_contact_form_7_payload_normalization(): void
    {
        $cf7Payload = [
            'form_name'  => 'Contact Us CF7',
            'form_id'    => 'cf7_42',
            'source_url' => 'https://testmachinery.example.com/contact',
            'fields'     => [
                'your-name'    => 'Sarah Connor',
                'your-email'   => 'sarah@resistance.org',
                'your-phone'   => '0812345678',
                'your-subject' => 'Equipment Inquiry',
                'your-message' => 'Need industrial blast shields specification.',
                'budget'       => '$25,000',
            ],
            'idempotency_key' => 'cf7_norm_test_1',
        ];

        $signed = $this->signPayload($cf7Payload);
        $res = $this->postJson('/api/v1/connector/inquiries', $cf7Payload, $signed['headers']);
        $res->assertStatus(201);

        $inquiry = WebsiteInquiry::find($res->json('inquiry_id'));
        $this->assertEquals('Sarah Connor', $inquiry->customer_name);
        $this->assertEquals('sarah@resistance.org', $inquiry->customer_email);
        $this->assertEquals('0812345678', $inquiry->customer_phone);
        $this->assertEquals('Need industrial blast shields specification.', $inquiry->message);
        $this->assertArrayHasKey('budget', $inquiry->custom_fields);
        $this->assertEquals('$25,000', $inquiry->custom_fields['budget']);
    }

    public function test_spam_honeypot_triggers_spam_status(): void
    {
        $spamPayload = [
            'customer_name'  => 'Bot Spammer',
            'customer_email' => 'spammer@botnet.ru',
            'message'        => 'Visit casino online now!',
            'website_hp'     => 'http://bad-bot-filled-honeypot.com', // Honeypot filled by bot
            'idempotency_key' => 'spam_test_1',
        ];

        $signed = $this->signPayload($spamPayload);
        $res = $this->postJson('/api/v1/inquiries', $spamPayload, $signed['headers']);
        $res->assertStatus(201);

        $inquiry = WebsiteInquiry::find($res->json('inquiry_id'));
        $this->assertEquals(WebsiteInquiry::STATUS_SPAM, $inquiry->status);
    }

    public function test_web_dashboard_index_and_csv_export(): void
    {
        // Create an authorized user
        $user = User::factory()->create([
            'email' => 'admin_test_' . Str::random(8) . '@example.com',
        ]);
        $user->assignRole('super-admin');

        // Create inquiry record
        $inquiry = WebsiteInquiry::create([
            'website_id'      => $this->website->id,
            'customer_name'   => 'Dashboard Customer',
            'customer_email'  => 'customer@example.com',
            'subject'         => 'Test Dashboard Inquiries',
            'message'         => 'Looking at inquiries dashboard.',
            'status'          => WebsiteInquiry::STATUS_NEW,
            'inquiry_type'    => WebsiteInquiry::TYPE_CONTACT,
            'submitted_at'    => now(),
        ]);

        $this->actingAs($user);

        // 1. Visit Inquiries Dashboard
        $response = $this->get('/websites/inquiries');
        $response->assertStatus(200);
        $response->assertSee('Centralized Website Inquiries');
        $response->assertSee('Dashboard Customer');

        // 2. View Single Inquiry Detail (AJAX)
        $detailRes = $this->getJson("/websites/inquiries/{$inquiry->id}");
        $detailRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'inquiry' => [
                    'id'            => $inquiry->id,
                    'customer_name' => 'Dashboard Customer',
                ],
            ]);

        // 3. Update Status
        $statusRes = $this->postJson("/websites/inquiries/{$inquiry->id}/status", [
            'status' => WebsiteInquiry::STATUS_IN_PROGRESS,
            'note'   => 'Contacted customer by phone.',
        ]);
        $statusRes->assertStatus(200);
        $this->assertEquals(WebsiteInquiry::STATUS_IN_PROGRESS, $inquiry->fresh()->status);

        // 4. Add Internal Note
        $noteRes = $this->postJson("/websites/inquiries/{$inquiry->id}/notes", [
            'note' => 'Customer requested a follow-up call on Friday.',
        ]);
        $noteRes->assertStatus(200);
        $this->assertDatabaseHas('website_inquiry_activities', [
            'inquiry_id'    => $inquiry->id,
            'activity_type' => 'note_added',
            'description'   => 'Customer requested a follow-up call on Friday.',
        ]);

        // 5. CSV Export
        $csvRes = $this->get('/websites/inquiries/export/csv');
        $csvRes->assertStatus(200);
        $csvRes->assertHeader('content-type', 'text/csv; charset=UTF-8');

        // Cleanup
        $user->delete();
    }
}
