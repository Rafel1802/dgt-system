<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteFollowUp;
use App\Services\GoogleBlogsSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebsiteFollowUpTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Website $website;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true]);
        $this->website = Website::create([
            'name' => 'americanloader.com',
            'url' => 'https://americanloader.com',
            'status' => 'Live',
        ]);
    }

    public function test_store_followup_with_skip_sheet_sync_saves_as_skipped(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('websites.followups.store'), [
            'type' => 'blog_post',
            'created_at' => '2026-09-16',
            'skip_sheet_sync' => '1',
            'items' => [
                [
                    'website_id' => $this->website->id,
                    'blog_sheet_class' => '4',
                    'url' => 'https://americanloader.com/blog/how-to-choose-storage',
                ]
            ]
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('website_follow_ups', [
            'website_id' => $this->website->id,
            'blog_sheet_class' => '4',
            'google_sheet_status' => 'skipped',
            'url' => 'https://americanloader.com/blog/how-to-choose-storage',
        ]);
    }

    public function test_store_followup_returns_needs_confirmation_when_google_sheet_has_existing_link(): void
    {
        config([
            'services.google_blogs.apps_script_url' => 'https://script.google.com/test',
            'services.google_blogs.api_secret' => 'test-secret',
        ]);

        // Mock Apps Script response returning conflict
        Http::fake([
            'https://script.google.com/test' => Http::response([
                'success' => false,
                'needs_confirmation' => true,
                'existing_public_link' => 'https://americanloader.com/blog/old-post',
                'message' => 'This row already has a Public Link. Are you sure you want to replace it?',
            ], 200),
        ]);

        $response = $this->actingAs($this->user)->postJson(route('websites.followups.store'), [
            'type' => 'blog_post',
            'created_at' => '2026-09-16',
            'items' => [
                [
                    'website_id' => $this->website->id,
                    'blog_sheet_class' => '4',
                    'url' => 'https://americanloader.com/blog/how-to-choose-storage',
                ]
            ]
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => false,
            'needs_confirmation' => true,
        ]);
        $this->assertStringContainsString('already has a Public Link', $response->json('message'));
        $this->assertStringContainsString('Sep Blogs', $response->json('message'));
    }

    public function test_store_followup_with_force_overwrite_succeeds(): void
    {
        config([
            'services.google_blogs.apps_script_url' => 'https://script.google.com/test',
            'services.google_blogs.api_secret' => 'test-secret',
        ]);

        // Mock Apps Script response succeeding with force_overwrite
        Http::fake([
            'https://script.google.com/test' => function (\Illuminate\Http\Client\Request $request) {
                $payload = $request->data();
                $this->assertTrue((bool)$payload['force_overwrite']);
                return Http::response([
                    'success' => true,
                    'message' => 'Blog successfully synchronized.',
                    'sheet' => 'Sep Blogs',
                    'row' => 15,
                ], 200);
            },
        ]);

        $response = $this->actingAs($this->user)->postJson(route('websites.followups.store'), [
            'type' => 'blog_post',
            'created_at' => '2026-09-16',
            'force_overwrite' => '1',
            'items' => [
                [
                    'website_id' => $this->website->id,
                    'blog_sheet_class' => '4',
                    'url' => 'https://americanloader.com/blog/how-to-choose-storage',
                ]
            ]
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('website_follow_ups', [
            'website_id' => $this->website->id,
            'blog_sheet_class' => '4',
            'google_sheet_status' => 'synced',
            'url' => 'https://americanloader.com/blog/how-to-choose-storage',
        ]);
    }

    public function test_store_followup_returns_clear_error_when_doc_link_is_missing(): void
    {
        config([
            'services.google_blogs.apps_script_url' => 'https://script.google.com/test',
            'services.google_blogs.api_secret' => 'test-secret',
        ]);

        Http::fake([
            'https://script.google.com/test' => Http::response([
                'success' => false,
                'message' => 'This row does not have a Doc Link yet. The scheduled row for "americanloader.com" on 09/16/2026 in Class 4 (tab "Sep Blogs") is missing a Doc Link (\'Link\'). Please add the Doc Link in Google Sheet before importing.',
            ], 200),
        ]);

        $response = $this->actingAs($this->user)->postJson(route('websites.followups.store'), [
            'type' => 'blog_post',
            'created_at' => '2026-09-16',
            'items' => [
                [
                    'website_id' => $this->website->id,
                    'blog_sheet_class' => '4',
                    'url' => 'https://americanloader.com/blog/how-to-choose-storage',
                ]
            ]
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => false,
            'needs_confirmation' => false,
        ]);
        $this->assertStringContainsString('This row does not have a Doc Link yet', $response->json('message'));
        $this->assertStringContainsString('missing a Doc Link', $response->json('message'));
    }

    public function test_store_followup_sends_correct_sheet_tab_to_apps_script(): void
    {
        config([
            'services.google_blogs.apps_script_url' => 'https://script.google.com/test',
            'services.google_blogs.api_secret' => 'test-secret',
        ]);

        Http::fake([
            'https://script.google.com/test' => function (\Illuminate\Http\Client\Request $request) {
                $payload = $request->data();
                $this->assertEquals('Sep Blogs', $payload['sheet_tab'] ?? null);
                $this->assertEquals('4', $payload['class'] ?? null);
                return Http::response([
                    'success' => true,
                    'message' => 'Blog successfully synchronized into row 35 with existing Doc Link.',
                    'sheet' => 'Sep Blogs',
                    'row' => 35,
                ], 200);
            },
        ]);

        $response = $this->actingAs($this->user)->postJson(route('websites.followups.store'), [
            'type' => 'blog_post',
            'created_at' => '2026-09-16',
            'items' => [
                [
                    'website_id' => $this->website->id,
                    'blog_sheet_class' => '4',
                    'url' => 'https://americanloader.com/blog/how-to-choose-storage',
                ]
            ]
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }
}
