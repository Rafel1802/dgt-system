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

    public function test_store_followup_handles_google_apps_script_connection_timeout_gracefully(): void
    {
        config([
            'services.google_blogs.apps_script_url' => 'https://script.google.com/test',
            'services.google_blogs.api_secret' => 'test-secret',
        ]);

        Http::fake([
            'https://script.google.com/test' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timed out after 12000 milliseconds');
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
        $response->assertJson(['success' => false]);
        $this->assertStringContainsString('Connection timed out', $response->json('message'));
    }

    public function test_store_followup_auto_detects_november_month_and_syncs_to_nov_blogs_tab(): void
    {
        config([
            'services.google_blogs.apps_script_url' => 'https://script.google.com/test',
            'services.google_blogs.api_secret' => 'test-secret',
        ]);

        Http::fake([
            'https://script.google.com/test' => function (\Illuminate\Http\Client\Request $request) {
                $payload = $request->data();
                $this->assertEquals('Nov Blogs', $payload['sheet_tab'] ?? null);
                $this->assertEquals('4', $payload['class'] ?? null);
                $this->assertEquals('11/05/2026', $payload['date'] ?? null);
                return Http::response([
                    'success' => true,
                    'message' => 'Blog successfully synchronized into row 14.',
                    'sheet' => 'Nov Blogs',
                    'row' => 14,
                ], 200);
            },
        ]);

        $response = $this->actingAs($this->user)->postJson(route('websites.followups.store'), [
            'type' => 'blog_post',
            'created_at' => '2026-11-05',
            'items' => [
                [
                    'website_id' => $this->website->id,
                    'blog_sheet_class' => '4',
                    'url' => 'https://americanloader.com/blog/november-post',
                ]
            ]
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('website_follow_ups', [
            'website_id' => $this->website->id,
            'blog_sheet_class' => '4',
            'google_sheet_status' => 'synced',
            'url' => 'https://americanloader.com/blog/november-post',
        ]);
    }

    public function test_store_followup_without_created_at_defaults_to_current_month(): void
    {
        config([
            'services.google_blogs.apps_script_url' => 'https://script.google.com/test',
            'services.google_blogs.api_secret' => 'test-secret',
        ]);

        $expectedSheet = now(config('app.timezone', 'Asia/Phnom_Penh'))->format('M') . ' Blogs';

        Http::fake([
            'https://script.google.com/test' => function (\Illuminate\Http\Client\Request $request) use ($expectedSheet) {
                $payload = $request->data();
                $this->assertEquals($expectedSheet, $payload['sheet_tab'] ?? null);
                return Http::response([
                    'success' => true,
                    'message' => 'Blog successfully synchronized into row 10.',
                    'sheet' => $expectedSheet,
                    'row' => 10,
                ], 200);
            },
        ]);

        $response = $this->actingAs($this->user)->postJson(route('websites.followups.store'), [
            'type' => 'blog_post',
            'items' => [
                [
                    'website_id' => $this->website->id,
                    'blog_sheet_class' => '4',
                    'url' => 'https://americanloader.com/blog/auto-date-post',
                ]
            ]
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_store_followup_supports_class_8_and_syncs_to_sheet(): void
    {
        config([
            'services.google_blogs.apps_script_url' => 'https://script.google.com/test',
            'services.google_blogs.api_secret' => 'test-secret',
        ]);

        $this->assertArrayHasKey('8', GoogleBlogsSheetService::CLASS_BLOCKS);

        Http::fake([
            'https://script.google.com/test' => function (\Illuminate\Http\Client\Request $request) {
                $payload = $request->data();
                $this->assertEquals('8', $payload['class'] ?? null);
                $this->assertEquals('Oct Blogs', $payload['sheet_tab'] ?? null);
                $this->assertEquals('10/08/2026', $payload['date'] ?? null);
                return Http::response([
                    'success' => true,
                    'message' => 'Blog successfully synchronized.',
                    'sheet' => 'Oct Blogs',
                    'row' => 7,
                ], 200);
            },
        ]);

        $response = $this->actingAs($this->user)->postJson(route('websites.followups.store'), [
            'type' => 'blog_post',
            'created_at' => '2026-10-08',
            'items' => [
                [
                    'website_id' => $this->website->id,
                    'blog_sheet_class' => '8',
                    'url' => 'https://americanloader.com/blog/class-8-post',
                ]
            ]
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('website_follow_ups', [
            'website_id' => $this->website->id,
            'blog_sheet_class' => '8',
            'google_sheet_status' => 'synced',
        ]);
    }

    public function test_extract_class_from_category_detects_all_formats(): void
    {
        $this->assertEquals('1', GoogleBlogsSheetService::extractClassFromCategory('1st Class'));
        $this->assertEquals('2', GoogleBlogsSheetService::extractClassFromCategory('2nd Class'));
        $this->assertEquals('3', GoogleBlogsSheetService::extractClassFromCategory('3rd Class'));
        $this->assertEquals('4', GoogleBlogsSheetService::extractClassFromCategory('4th Class'));
        $this->assertEquals('5', GoogleBlogsSheetService::extractClassFromCategory('5th Class'));
        $this->assertEquals('6', GoogleBlogsSheetService::extractClassFromCategory('6th Class'));
        $this->assertEquals('7', GoogleBlogsSheetService::extractClassFromCategory('7th Class'));
        $this->assertEquals('8', GoogleBlogsSheetService::extractClassFromCategory('8th Class'));

        // Alternate formats
        $this->assertEquals('5', GoogleBlogsSheetService::extractClassFromCategory('Class 5'));
        $this->assertEquals('5', GoogleBlogsSheetService::extractClassFromCategory('Fifth Class'));
        $this->assertEquals('1', GoogleBlogsSheetService::extractClassFromCategory('First Class'));
        $this->assertEquals('2', GoogleBlogsSheetService::extractClassFromCategory('Second Class'));

        // Invalid / empty
        $this->assertNull(GoogleBlogsSheetService::extractClassFromCategory('Uncategorized'));
        $this->assertNull(GoogleBlogsSheetService::extractClassFromCategory(''));
        $this->assertNull(GoogleBlogsSheetService::extractClassFromCategory(null));
    }

    public function test_store_followup_auto_detects_blog_sheet_class_from_website_category(): void
    {
        config([
            'services.google_blogs.apps_script_url' => 'https://script.google.com/test',
            'services.google_blogs.api_secret' => 'test-secret',
        ]);

        $kuvuoWebsite = Website::create([
            'name' => 'kuvuo.com',
            'url' => 'https://kuvuo.com',
            'category' => '5th Class',
            'status' => 'Live',
        ]);

        Http::fake([
            'https://script.google.com/test' => function (\Illuminate\Http\Client\Request $request) {
                $payload = $request->data();
                $this->assertEquals('5', $payload['class'] ?? null);
                $this->assertEquals('Oct Blogs', $payload['sheet_tab'] ?? null);
                return Http::response([
                    'success' => true,
                    'message' => 'Blog successfully synchronized.',
                    'sheet' => 'Oct Blogs',
                    'row' => 12,
                ], 200);
            },
        ]);

        // Submit without specifying blog_sheet_class (or empty string)
        $response = $this->actingAs($this->user)->postJson(route('websites.followups.store'), [
            'type' => 'blog_post',
            'created_at' => '2026-10-08',
            'items' => [
                [
                    'website_id' => $kuvuoWebsite->id,
                    'blog_sheet_class' => '',
                    'url' => 'https://kuvuo.com/blog/stone-crusher-guide',
                ]
            ]
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('website_follow_ups', [
            'website_id' => $kuvuoWebsite->id,
            'blog_sheet_class' => '5',
            'google_sheet_status' => 'synced',
        ]);
    }

    public function test_can_view_all_website_reports_helper_identifies_dara_kim_and_supervisors(): void
    {
        $dara = User::factory()->create(['name' => 'Mr. Dara (Head)', 'username' => 'dara']);
        $kim = User::factory()->create(['name' => 'Mr. KimOun (Head)', 'username' => 'kim']);
        $supervisor = User::factory()->create(['name' => 'Ms. Somalika', 'username' => 'somalika', 'team_role' => 'Supervisor']);
        $regularMember = User::factory()->create(['name' => 'Ms. Sokheang', 'username' => 'sokheang']);

        $this->assertTrue($dara->canViewAllWebsiteReports());
        $this->assertTrue($kim->canViewAllWebsiteReports());
        $this->assertTrue($supervisor->canViewAllWebsiteReports());
        $this->assertFalse($regularMember->canViewAllWebsiteReports());
    }

    public function test_regular_member_is_forced_to_own_account_when_exporting_website_report(): void
    {
        $regularMember = User::factory()->create(['name' => 'Ms. Sokheang', 'username' => 'sokheang']);
        $otherUser = User::factory()->create(['name' => 'Mr. Hour', 'username' => 'hour']);

        // Attach regular member to website members so they have website access
        \App\Models\WebsiteMember::create([
            'user_id' => $regularMember->id,
            'role' => 'Developer',
        ]);

        $response = $this->actingAs($regularMember)->get(route('websites.export', [
            'tab' => 'follow-up',
            'format' => 'preview',
            'member_id' => $otherUser->id,
        ]));

        $response->assertOk();
        // Regular member cannot view otherUser's report; memberId is forced to their own ID
        $response->assertViewHas('memberId', $regularMember->id);
    }

    public function test_dara_can_export_for_any_member(): void
    {
        $dara = User::factory()->create(['name' => 'Mr. Dara', 'username' => 'dara']);
        $otherUser = User::factory()->create(['name' => 'Ms. Sokheang', 'username' => 'sokheang']);

        \App\Models\WebsiteMember::create([
            'user_id' => $dara->id,
            'role' => 'QC',
        ]);

        $response = $this->actingAs($dara)->get(route('websites.export', [
            'tab' => 'follow-up',
            'format' => 'preview',
            'member_id' => $otherUser->id,
        ]));

        $response->assertOk();
        // Dara can view otherUser's report
        $response->assertViewHas('memberId', $otherUser->id);
    }

    public function test_store_multi_website_followups_saves_all_instantly(): void
    {
        $site2 = Website::create(['name' => 'machinery.com', 'url' => 'https://machinery.com', 'status' => 'Live']);
        $site3 = Website::create(['name' => 'compactors.com', 'url' => 'https://compactors.com', 'status' => 'Live']);

        $response = $this->actingAs($this->user)->postJson(route('websites.followups.store'), [
            'type' => 'blog_post',
            'created_at' => '2026-10-08',
            'force_overwrite' => '1',
            'items' => [
                [
                    'website_id' => $this->website->id,
                    'blog_sheet_class' => '1',
                    'url' => 'https://americanloader.com/blog/article-1',
                ],
                [
                    'website_id' => $site2->id,
                    'blog_sheet_class' => '2',
                    'url' => 'https://machinery.com/blog/article-2',
                ],
                [
                    'website_id' => $site3->id,
                    'blog_sheet_class' => '3',
                    'url' => 'https://compactors.com/blog/article-3',
                ],
            ]
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'count' => 3]);

        $this->assertDatabaseHas('website_follow_ups', [
            'website_id' => $this->website->id,
            'url' => 'https://americanloader.com/blog/article-1',
        ]);
        $this->assertDatabaseHas('website_follow_ups', [
            'website_id' => $site2->id,
            'url' => 'https://machinery.com/blog/article-2',
        ]);
        $this->assertDatabaseHas('website_follow_ups', [
            'website_id' => $site3->id,
            'url' => 'https://compactors.com/blog/article-3',
        ]);
    }

    public function test_store_followup_with_async_sheet_flag_saves_immediately(): void
    {
        config([
            'services.google_blogs.apps_script_url' => 'https://script.google.com/test',
            'services.google_blogs.api_secret' => 'test-secret',
        ]);

        Http::fake([
            'https://script.google.com/test' => Http::response([
                'success' => true,
                'message' => 'Blog successfully synchronized.',
                'sheet' => 'Oct Blogs',
                'row' => 10,
            ], 200),
        ]);

        $response = $this->actingAs($this->user)->postJson(route('websites.followups.store'), [
            'type' => 'blog_post',
            'created_at' => '2026-10-08',
            'async_sheet' => '1',
            'items' => [
                [
                    'website_id' => $this->website->id,
                    'blog_sheet_class' => '1',
                    'url' => 'https://americanloader.com/blog/async-test',
                ],
            ]
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'count' => 1]);

        $this->assertDatabaseHas('website_follow_ups', [
            'website_id' => $this->website->id,
            'url' => 'https://americanloader.com/blog/async-test',
            'google_sheet_status' => 'synced',
        ]);
    }
}

