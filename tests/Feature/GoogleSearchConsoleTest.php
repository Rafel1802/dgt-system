<?php

namespace Tests\Feature;

use App\Models\GoogleSearchConsole\GoogleSearchConsoleAccount;
use App\Models\GoogleSearchConsole\GoogleSearchConsoleDailyMetric;
use App\Models\GoogleSearchConsole\GoogleSearchConsoleSearchQuery;
use App\Models\GoogleSearchConsole\GoogleSearchConsoleSite;
use App\Models\User;
use App\Models\Website;
use App\Services\GoogleSearchConsole\GoogleSearchConsoleAnalyticsService;
use App\Services\GoogleSearchConsole\XlsxExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoogleSearchConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $memberSamnang;
    protected User $memberVouchky;
    protected User $memberPich;
    protected Website $websiteA;
    protected Website $websiteB;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'admin-digital']);

        // 1. Create test users
        $this->adminUser = User::factory()->create([
            'name'     => 'Super Admin',
            'username' => 'superadmin',
            'email'    => 'superadmin@dgt.local',
        ]);
        $this->adminUser->assignRole('super-admin');

        $this->memberSamnang = User::factory()->create([
            'name'     => 'Mr. Samnang',
            'username' => 'samnang',
            'email'    => 'samnang@kiuq.com',
        ]);

        $this->memberVouchky = User::factory()->create([
            'name'     => 'Ms. Vouchky',
            'username' => 'vouchky',
            'email'    => 'vouchky@kiuq.com',
        ]);

        $this->memberPich = User::factory()->create([
            'name'     => 'Mr. Pich',
            'username' => 'pich',
            'email'    => 'pich@kiuq.com',
        ]);

        // 2. Setup Websites as described in Phase 9 specification:
        // Website A is assigned to Samnang and Vouchky
        // Website B is assigned to Pich and Samnang
        $this->websiteA = Website::create([
            'name'        => 'Website A (Test)',
            'url'         => 'https://website-a-test.com',
            'handled_by'  => $this->memberSamnang->id,
            'status'      => 'Live',
            'is_archived' => false,
        ]);
        $this->websiteA->assignees()->attach($this->memberVouchky->id);

        $this->websiteB = Website::create([
            'name'        => 'Website B (Test)',
            'url'         => 'https://website-b-test.com',
            'handled_by'  => $this->memberPich->id,
            'status'      => 'Live',
            'is_archived' => false,
        ]);
        $this->websiteB->assignees()->attach($this->memberSamnang->id);

        // 3. Map websites to GSC properties
        \App\Models\GoogleSearchConsole\GoogleSearchConsoleSite::create([
            'website_id'      => $this->websiteA->id,
            'site_url'        => 'https://website-a-test.com',
            'is_verified'     => true,
            'is_sync_enabled' => true,
        ]);
        \App\Models\GoogleSearchConsole\GoogleSearchConsoleSite::create([
            'website_id'      => $this->websiteB->id,
            'site_url'        => 'https://website-b-test.com',
            'is_verified'     => true,
            'is_sync_enabled' => true,
        ]);
    }

    /**
     * Test Phase 2 & 5: GSC Route Access & Permissions
     */
    public function test_guest_is_redirected_to_login()
    {
        $response = $this->get(route('websites.search-console.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_access_dashboard()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('websites.search-console.index'));

        $response->assertStatus(200);
        $response->assertSee('Website SEO Analytics');
        $response->assertSee('Google Search Console');
    }

    public function test_properties_view_renders_cleanly()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('websites.search-console.index', ['view' => 'properties']));

        $response->assertStatus(200);
        $response->assertSee('Bulk Property Auto-Mapper');
        $response->assertSee('Google Search Console Property Management');
    }


    /**
     * Test Phase 9: Team Member & Website Scoping Without Double-Counting
     */
    public function test_team_member_filtering_and_double_counting_prevention()
    {
        $analytics = app(GoogleSearchConsoleAnalyticsService::class);

        // 1. When Samnang is selected -> Should include Website A and Website B
        $samnangSites = $analytics->resolveWebsiteIds($this->adminUser, null, [$this->memberSamnang->id]);
        $this->assertContains($this->websiteA->id, $samnangSites);
        $this->assertContains($this->websiteB->id, $samnangSites);

        // 2. When Vouchky is selected -> Should include Website A only
        $vouchkySites = $analytics->resolveWebsiteIds($this->adminUser, null, [$this->memberVouchky->id]);
        $this->assertContains($this->websiteA->id, $vouchkySites);
        $this->assertNotContains($this->websiteB->id, $vouchkySites);

        // 3. When Pich is selected -> Should include Website B only
        $pichSites = $analytics->resolveWebsiteIds($this->adminUser, null, [$this->memberPich->id]);
        $this->assertNotContains($this->websiteA->id, $pichSites);
        $this->assertContains($this->websiteB->id, $pichSites);

        // 4. When Samnang AND Vouchky are both selected -> Website A must be present exactly once (no duplicates)
        $bothSites = $analytics->resolveWebsiteIds($this->adminUser, null, [$this->memberSamnang->id, $this->memberVouchky->id]);
        $this->assertCount(count(array_unique($bothSites)), $bothSites, 'Website list must contain unique IDs without double-counting');
        $this->assertContains($this->websiteA->id, $bothSites);
        $this->assertContains($this->websiteB->id, $bothSites);
    }

    /**
     * Test Phase 8: Mathematical Accuracy of Weighted Average Position and Combined CTR
     */
    public function test_mathematical_accuracy_of_ctr_and_weighted_position()
    {
        $analytics = app(GoogleSearchConsoleAnalyticsService::class);

        $date = '2026-10-01';
        $prevDate = '2026-09-01';

        // Insert controlled sample data:
        // Website A: 100 clicks, 1,000 impressions, position 10.0
        GoogleSearchConsoleDailyMetric::create([
            'website_id'  => $this->websiteA->id,
            'site_url'    => 'https://website-a-test.com',
            'data_date'   => $date,
            'clicks'      => 100,
            'impressions' => 1000,
            'ctr'         => 0.10,
            'position'    => 10.0,
            'search_type' => 'web',
        ]);

        // Website B: 300 clicks, 9,000 impressions, position 20.0
        GoogleSearchConsoleDailyMetric::create([
            'website_id'  => $this->websiteB->id,
            'site_url'    => 'https://website-b-test.com',
            'data_date'   => $date,
            'clicks'      => 300,
            'impressions' => 9000,
            'ctr'         => 0.0333,
            'position'    => 20.0,
            'search_type' => 'web',
        ]);

        // Expected Combined Calculations:
        // Total Clicks = 100 + 300 = 400
        // Total Impressions = 1,000 + 9,000 = 10,000
        // Correct CTR = (400 / 10,000) * 100 = 4.00%
        // Correct Weighted Avg Position = (10 * 1,000 + 20 * 9,000) / 10,000 = (10,000 + 180,000) / 10,000 = 19.0
        // Note: Simple unweighted average of positions (10 + 20)/2 = 15.0 would be mathematically FALSE!

        $summary = $analytics->getKpiSummary(
            [$this->websiteA->id, $this->websiteB->id],
            $date,
            $date,
            $prevDate,
            $prevDate
        );

        $this->assertEquals(400, $summary['clicks']['current']);
        $this->assertEquals(10000, $summary['impressions']['current']);
        $this->assertEquals(4.00, $summary['ctr']['current']);
        $this->assertEquals(19.0, $summary['position']['current'], 'Position must be impression-weighted, not a naive average');
    }

    /**
     * Test Phase 10: PDF Export Generation
     */
    public function test_pdf_export_endpoint_returns_pdf()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('websites.search-console.export.pdf', [
                'range' => '7d',
                'websites' => $this->websiteA->id,
            ]));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    /**
     * Test Phase 10: Multi-Sheet Excel XLSX Export Generation
     */
    public function test_excel_export_endpoint_returns_xlsx()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('websites.search-console.export.excel', [
                'range' => '7d',
                'websites' => $this->websiteA->id,
            ]));

        $response->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));
    }

    /**
     * Test Phase 10: CSV Export Streaming
     */
    public function test_csv_export_endpoint_returns_csv()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('websites.search-console.export.csv', [
                'report' => 'keywords',
                'range' => '7d',
                'websites' => $this->websiteA->id,
            ]));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    /**
     * Test Phase 3: OAuth State Validation & CSRF Protection
     */
    public function test_oauth_callback_rejects_state_mismatch()
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['gsc_oauth_state' => 'valid_secret_state_123'])
            ->get(route('google.search-console.callback', [
                'state' => 'tampered_state_999',
                'code' => 'fake_code',
            ]));

        $response->assertRedirect(route('websites.search-console.index'));
        $response->assertSessionHas('error');
    }

    /**
     * Test Phase 4: Property Mapping Storage & Retrieval
     */
    public function test_property_mapping_storage()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('websites.search-console.mappings.save'), [
                'website_id' => $this->websiteA->id,
                'site_url'   => 'sc-domain:website-a-test.com',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('google_search_console_sites', [
            'website_id' => $this->websiteA->id,
            'site_url'   => 'sc-domain:website-a-test.com',
        ]);
    }

    /**
     * Test Phase 6: Keywords Searching and Sorting
     */
    public function test_search_keywords_filtering_and_sorting()
    {
        $analytics = app(GoogleSearchConsoleAnalyticsService::class);
        $date = '2026-10-01';

        GoogleSearchConsoleSearchQuery::create([
            'website_id'  => $this->websiteA->id,
            'site_url'    => 'https://website-a-test.com',
            'data_date'   => $date,
            'query'       => 'mini excavator 3 ton',
            'query_hash'  => sha1('mini excavator 3 ton'),
            'clicks'      => 45,
            'impressions' => 1200,
            'ctr'         => 0.0375,
            'position'    => 4.2,
        ]);

        GoogleSearchConsoleSearchQuery::create([
            'website_id'  => $this->websiteA->id,
            'site_url'    => 'https://website-a-test.com',
            'data_date'   => $date,
            'query'       => 'skid steer brush cutter',
            'query_hash'  => sha1('skid steer brush cutter'),
            'clicks'      => 80,
            'impressions' => 2000,
            'ctr'         => 0.0400,
            'position'    => 3.1,
        ]);

        // Filter by keyword substring 'brush'
        $filtered = $analytics->getSearchKeywords([$this->websiteA->id], $date, $date, 'brush');
        $this->assertEquals(1, $filtered['total']);
        $this->assertEquals('skid steer brush cutter', $filtered['data'][0]['query']);

        // Sort by clicks descending
        $sorted = $analytics->getSearchKeywords([$this->websiteA->id], $date, $date, null, 'clicks', 'desc');
        $this->assertEquals(2, $sorted['total']);
        $this->assertEquals('skid steer brush cutter', $sorted['data'][0]['query']);
        $this->assertEquals('mini excavator 3 ton', $sorted['data'][1]['query']);
    }

    /**
     * Test Phase 6: Inspect Single Keyword Daily Performance
     */
    public function test_keyword_details_endpoint()
    {
        $date = now()->subDays(2)->toDateString();

        GoogleSearchConsoleSearchQuery::create([
            'website_id'  => $this->websiteA->id,
            'site_url'    => 'https://website-a-test.com',
            'data_date'   => $date,
            'query'       => 'trencher teeth replacement',
            'query_hash'  => sha1('trencher teeth replacement'),
            'clicks'      => 12,
            'impressions' => 340,
            'ctr'         => 0.0353,
            'position'    => 5.6,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('websites.search-console.keyword-details', [
                'keyword'   => 'trencher teeth replacement',
                'range'     => '7d',
                'websites'  => $this->websiteA->id,
            ]));

        $response->assertStatus(200);
        $response->assertJson([
            'keyword' => 'trencher teeth replacement',
            'summary' => [
                'clicks'      => 12,
                'impressions' => 340,
            ],
        ]);
    }

    /**
     * Test Phase 12: Security - Non-Admin User is Forbidden from Managing Mappings
     */
    public function test_non_admin_cannot_access_property_mapping_endpoint()
    {
        $regularUser = User::factory()->create([
            'name'     => 'Regular Viewer',
            'username' => 'viewer',
            'email'    => 'viewer@kiuq.com',
        ]);

        $response = $this->actingAs($regularUser)
            ->postJson(route('websites.search-console.mappings.save'), [
                'website_id' => $this->websiteA->id,
                'site_url'   => 'sc-domain:unauthorized.com',
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test Phase 11: Artisan Command for Missing Website
     */
    public function test_artisan_sync_command_with_unmapped_website()
    {
        $this->artisan('gsc:sync', ['--website' => 999999])
            ->assertExitCode(1);
    }

    /**
     * Test Auto Sync Check Endpoint
     */
    public function test_auto_sync_check_endpoint()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('websites.search-console.auto-sync-check'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['synced', 'reason']);
    }

    /**
     * Test Multi-Website Filtering and Website Breakdown with Class and Handler
     */
    public function test_multi_website_filtering_and_breakdown()
    {
        $this->websiteA->update(['category' => '1st Class']);
        $this->websiteB->update(['category' => '2nd Class']);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('websites.search-console.data', [
                'range'    => '28d',
                'websites' => "{$this->websiteA->id},{$this->websiteB->id}",
            ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'kpi_summary',
            'daily_trend',
            'keywords',
            'top_pages',
            'countries',
            'devices',
            'website_breakdown',
        ]);

        $breakdown = $response->json('website_breakdown');
        $this->assertCount(2, $breakdown);

        $siteA = collect($breakdown)->firstWhere('website_id', $this->websiteA->id);
        $this->assertNotNull($siteA);
        $this->assertEquals('1st Class', $siteA['class']);
        $this->assertEquals($this->memberSamnang->name, $siteA['handler_name']);

        $siteB = collect($breakdown)->firstWhere('website_id', $this->websiteB->id);
        $this->assertNotNull($siteB);
        $this->assertEquals('2nd Class', $siteB['class']);
        $this->assertEquals($this->memberPich->name, $siteB['handler_name']);
    }

    /**
     * Test Multi-Website PDF, Excel, and CSV Exports
     */
    public function test_multi_website_exports()
    {
        $this->websiteA->update(['category' => '1st Class']);
        $this->websiteB->update(['category' => '2nd Class']);

        $filterParams = [
            'range'    => '28d',
            'websites' => "{$this->websiteA->id},{$this->websiteB->id}",
        ];

        // 1. PDF Export
        $pdfResponse = $this->actingAs($this->adminUser)
            ->get(route('websites.search-console.export.pdf', $filterParams));
        $pdfResponse->assertStatus(200);
        $pdfResponse->assertHeader('content-type', 'application/pdf');

        // 2. Excel Export
        $excelResponse = $this->actingAs($this->adminUser)
            ->get(route('websites.search-console.export.excel', $filterParams));
        $excelResponse->assertStatus(200);

        // 3. CSV Export with Breakdown
        $csvResponse = $this->actingAs($this->adminUser)
            ->get(route('websites.search-console.export.csv', array_merge($filterParams, ['report' => 'breakdown'])));
        $csvResponse->assertStatus(200);
        $csvResponse->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    /**
     * Test Security: Non-Dara user cannot disconnect Google Search Console account
     */
    public function test_non_dara_user_cannot_disconnect_gsc()
    {
        GoogleSearchConsoleAccount::create([
            'email'         => 'soporadara@kiuq.com',
            'access_token'  => 'valid_token',
            'refresh_token' => 'valid_refresh',
            'expires_at'    => now()->addHour(),
        ]);

        // Attempt disconnect as non-Dara admin user (web form redirect)
        $response = $this->actingAs($this->adminUser)
            ->post(route('google.search-console.disconnect'));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('google_search_console_accounts', [
            'email' => 'soporadara@kiuq.com',
        ]);

        // Attempt as JSON request: should return 403 Forbidden
        $jsonResponse = $this->actingAs($this->adminUser)
            ->postJson(route('google.search-console.disconnect'));
        $jsonResponse->assertStatus(403);
    }

    /**
     * Test Security: User Dara CAN disconnect Google Search Console account
     */
    public function test_dara_user_can_disconnect_gsc()
    {
        $account = GoogleSearchConsoleAccount::create([
            'email'         => 'soporadara@kiuq.com',
            'access_token'  => 'valid_token',
            'refresh_token' => 'valid_refresh',
            'expires_at'    => now()->addHour(),
            'is_active'     => true,
        ]);

        $daraUser = User::factory()->create([
            'name'     => 'Mr. Dara',
            'username' => 'dara',
            'email'    => 'soporadara@kiuq.com',
        ]);
        $daraUser->assignRole('super-admin');

        $response = $this->actingAs($daraUser)
            ->post(route('google.search-console.disconnect'));

        $response->assertRedirect(route('websites.search-console.index'));
        $this->assertDatabaseHas('google_search_console_accounts', [
            'email'     => 'soporadara@kiuq.com',
            'is_active' => false,
        ]);
    }

    /**
     * Test Sitemaps submission requires website to be mapped first
     */
    public function test_sitemap_submission_requires_active_mapping()
    {
        // Unmapped website
        $unmappedWebsite = Website::create([
            'name'   => 'Unmapped Site',
            'url'    => 'https://unmapped-site.com',
            'domain' => 'unmapped-site.com',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('websites.search-console.sitemaps.submit'), [
                'website_id'  => $unmappedWebsite->id,
                'sitemap_url' => 'https://unmapped-site.com/sitemap.xml',
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
    }

    /**
     * Test Export CSV with Growth report includes period-over-period comparison columns
     */
    public function test_export_csv_with_growth_report_contains_comparison_columns()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('websites.search-console.export.csv', [
                'range'  => '28d',
                'report' => 'growth',
            ]));

        $response->assertStatus(200);
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('Website Name', $content);
        $this->assertStringContainsString('Clicks (Current)', $content);
        $this->assertStringContainsString('Clicks (Previous)', $content);
        $this->assertStringContainsString('Clicks Growth (%)', $content);
        $this->assertStringContainsString('Impressions (Current)', $content);
        $this->assertStringContainsString('Impressions (Previous)', $content);
        $this->assertStringContainsString('Impressions Growth (%)', $content);
    }

    /**
     * Test Export PDF with custom date range and class filter
     */
    public function test_export_pdf_with_custom_date_range_and_class_filter()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('websites.search-console.export.pdf', [
                'range'      => 'custom',
                'start_date' => '2026-10-01',
                'end_date'   => '2026-10-15',
                'class'      => 'General',
            ]));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    /**
     * Test Export Excel with date range and growth sheets
     */
    public function test_export_excel_with_date_range_and_growth_sheets()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('websites.search-console.export.excel', [
                'range' => 'this_week',
            ]));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /**
     * Test Analytics Service correctly calculates custom date ranges and comparison periods
     */
    public function test_analytics_service_resolves_presets_and_custom_ranges_accurately()
    {
        $service = app(GoogleSearchConsoleAnalyticsService::class);

        // Test custom 10-day range
        $custom = $service->resolveDateRange('custom', '2026-10-11', '2026-10-20');
        $this->assertEquals('2026-10-11', $custom['start']);
        $this->assertEquals('2026-10-20', $custom['end']);
        $this->assertEquals('2026-10-01', $custom['prev_start']);
        $this->assertEquals('2026-10-10', $custom['prev_end']);
        $this->assertEquals('custom', $custom['preset']);

        // Test this_week preset
        $thisWeek = $service->resolveDateRange('this_week');
        $this->assertEquals('this_week', $thisWeek['preset']);
        $this->assertNotNull($thisWeek['prev_start']);
        $this->assertNotNull($thisWeek['prev_end']);

        // Test last_week preset
        $lastWeek = $service->resolveDateRange('last_week');
        $this->assertEquals('last_week', $lastWeek['preset']);
        $this->assertNotNull($lastWeek['prev_start']);
        $this->assertNotNull($lastWeek['prev_end']);

        // Test 7d preset even when lingering custom dates from another selection are present
        $presetWithLingering = $service->resolveDateRange('7d', '2026-09-01', '2026-09-28');
        $this->assertEquals('7d', $presetWithLingering['preset']);
        $this->assertEquals(7, $presetWithLingering['days']);
        $this->assertStringContainsString('Last 7 Days', $presetWithLingering['label']);
    }

    /**
     * Test index page HTML renders with valid JavaScript
     */
    public function test_index_page_renders_with_valid_javascript()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('websites.search-console.index'));

        $response->assertStatus(200);
        $html = $response->getContent();

        preg_match_all('/<script>(.*?)<\/script>/s', $html, $matches);
        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $idx => $script) {
            $tmpFile = storage_path("app/test_render_script_{$idx}.js");
            file_put_contents($tmpFile, $script);
            $output = [];
            $returnCode = 0;
            // First check syntax
            exec("node -c " . escapeshellarg($tmpFile) . " 2>&1", $output, $returnCode);
            $this->assertEquals(0, $returnCode, "JavaScript Syntax Error in script tag #{$idx}:\n" . implode("\n", $output));

            // Then test executing gscDashboard if defined
            $evalCode = "global.localStorage = { removeItem: () => {}, getItem: () => null, setItem: () => {} }; global.window = { addEventListener: () => {}, removeEventListener: () => {} }; global.document = { addEventListener: () => {}, querySelector: () => null, getElementById: () => null }; global.MutationObserver = class { observe() {} disconnect() {} }; const fs = require('fs'); const src = fs.readFileSync('" . addslashes($tmpFile) . "', 'utf8'); eval(src); if (typeof gscDashboard === 'function') { const d = gscDashboard(); if (typeof d.openManageModal !== 'function') throw new Error('openManageModal missing'); }";
            $outputEval = [];
            $returnCodeEval = 0;
            exec("node -e " . escapeshellarg($evalCode) . " 2>&1", $outputEval, $returnCodeEval);
            @unlink($tmpFile);

            $this->assertEquals(0, $returnCodeEval, "JavaScript Runtime Execution Error in script tag #{$idx}:\n" . implode("\n", $outputEval));
        }
    }

    /**
     * Test live URL test endpoint validates URL format and returns inspection details
     */
    public function test_live_url_endpoint_validates_and_inspects_url()
    {
        $responseInvalid = $this->actingAs($this->adminUser)
            ->postJson(route('websites.search-console.test-live-url'), [
                'url' => 'not-a-valid-url',
            ]);

        $responseInvalid->assertStatus(422);
        $responseInvalid->assertJson(['success' => false]);

        \Illuminate\Support\Facades\Http::fake([
            'https://example.com/test-page' => \Illuminate\Support\Facades\Http::response('<html><head><title>Test Page</title><meta name="robots" content="index, follow"><link rel="canonical" href="https://example.com/test-page"></head><body>Hello</body></html>', 200),
            'https://example.com/robots.txt' => \Illuminate\Support\Facades\Http::response("User-agent: *\nAllow: /", 200),
        ]);

        $responseValid = $this->actingAs($this->adminUser)
            ->postJson(route('websites.search-console.test-live-url'), [
                'url' => 'https://example.com/test-page',
            ]);

        $responseValid->assertStatus(200);
        $responseValid->assertJson([
            'success'   => true,
            'live_test' => [
                'reachable'    => true,
                'status_code'  => 200,
                'verdict'      => 'PASS',
                'is_indexable' => true,
                'title'        => 'Test Page',
            ],
        ]);
    }

    /**
     * Test properties view renders properly
     */
     public function test_properties_view_renders()
     {
         $response = $this->actingAs($this->adminUser)
             ->get(route('websites.search-console.index', ['view' => 'properties']));

         $response->assertStatus(200);
         $content = $response->getContent();
         $this->assertStringContainsString("activeView === 'properties'", $content);
         $this->assertStringContainsString("Smart Bulk Property Auto-Mapper", $content);
         $this->assertStringContainsString("Google Search Console Property Management", $content);
     }

    /**
     * Test sitemap submission on mapped site returns clean json response with message
     */
    public function test_mapped_website_sitemap_submission_response()
    {
        // Mapped website with site URL
        $mappedWebsite = Website::create([
            'name'   => 'Mapped Site Test',
            'url'    => 'https://mapped-site-test.com',
            'domain' => 'mapped-site-test.com',
            'status' => 'active',
        ]);

        \App\Models\GoogleSearchConsole\GoogleSearchConsoleSite::create([
            'website_id'      => $mappedWebsite->id,
            'site_url'        => 'sc-domain:mapped-site-test.com',
            'permission_level'=> 'siteFullUser',
            'is_sync_enabled' => true,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('websites.search-console.sitemaps.submit'), [
                'website_id'  => $mappedWebsite->id,
                'sitemap_url' => 'https://mapped-site-test.com/sitemap.xml',
            ]);

        // Should return a structured JSON response (either 200 or 422 if account disconnected, but always structured with success and message)
        $this->assertContains($response->status(), [200, 422]);
        $response->assertJsonStructure(['success', 'message']);
    }

    /**
     * Test data endpoint updates date range and KPI metrics when filtering by range preset and custom dates
     */
    public function test_data_endpoint_updates_date_range_and_kpis_on_filter()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson(route('websites.search-console.data', [
                'range'      => '7d',
                'start_date' => '2026-09-01', // lingering dates from previous selection should NOT override '7d'
                'end_date'   => '2026-09-28',
            ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'date_range' => ['start', 'end', 'prev_start', 'prev_end', 'days', 'preset', 'label'],
            'kpi_summary' => ['clicks', 'impressions', 'ctr', 'position'],
        ]);

        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertEquals('7d', $data['date_range']['preset']);
        $this->assertEquals(7, $data['date_range']['days']);

        // Now test custom date range
        $customResponse = $this->actingAs($this->adminUser)
            ->getJson(route('websites.search-console.data', [
                'range'      => 'custom',
                'start_date' => '2026-10-10',
                'end_date'   => '2026-10-15',
            ]));

        $customResponse->assertStatus(200);
        $customData = $customResponse->json();
        $this->assertEquals('custom', $customData['date_range']['preset']);
        $this->assertEquals('2026-10-10', $customData['date_range']['start']);
        $this->assertEquals('2026-10-15', $customData['date_range']['end']);
        $this->assertEquals(6, $customData['date_range']['days']);
    }

    /**
     * Test export strictly filters by class and never includes other classes or unrelated members
     */
    public function test_export_filters_strictly_by_class_and_does_not_include_other_classes()
    {
        // Website in 5th Class
        $site5th = Website::create([
            'name'       => 'Class 5 Excavators',
            'url'        => 'https://class5excavators.com',
            'category'   => '5th Class',
            'status'     => 'Live',
            'handled_by' => $this->memberSamnang->id,
        ]);
        GoogleSearchConsoleSite::create([
            'website_id'      => $site5th->id,
            'site_url'        => 'sc-domain:class5excavators.com',
            'permission_level'=> 'siteFullUser',
            'is_sync_enabled' => true,
        ]);
        GoogleSearchConsoleDailyMetric::create([
            'website_id'  => $site5th->id,
            'site_url'    => 'sc-domain:class5excavators.com',
            'data_date'   => now()->subDays(5)->format('Y-m-d'),
            'clicks'      => 120,
            'impressions' => 1500,
            'ctr'         => 8.0,
            'position'    => 3.2,
            'search_type' => 'web',
        ]);

        // Website in 1st Class
        $site1st = Website::create([
            'name'       => 'Class 1 Compactors',
            'url'        => 'https://class1compactors.com',
            'category'   => '1st Class',
            'status'     => 'Live',
            'handled_by' => $this->memberPich->id,
        ]);
        GoogleSearchConsoleSite::create([
            'website_id'      => $site1st->id,
            'site_url'        => 'sc-domain:class1compactors.com',
            'permission_level'=> 'siteFullUser',
            'is_sync_enabled' => true,
        ]);
        GoogleSearchConsoleDailyMetric::create([
            'website_id'  => $site1st->id,
            'site_url'    => 'sc-domain:class1compactors.com',
            'data_date'   => now()->subDays(5)->format('Y-m-d'),
            'clicks'      => 45,
            'impressions' => 600,
            'ctr'         => 7.5,
            'position'    => 4.1,
            'search_type' => 'web',
        ]);

        // 1. Export CSV with 5th Class filter
        $response5th = $this->actingAs($this->adminUser)
            ->get(route('websites.search-console.export.csv', [
                'range'  => '28d',
                'class'  => '5th Class',
                'report' => 'breakdown',
            ]));

        $response5th->assertStatus(200);
        ob_start();
        $response5th->sendContent();
        $content5th = ob_get_clean();

        // Must contain 5th Class website
        $this->assertStringContainsString('Class 5 Excavators', $content5th);
        $this->assertStringContainsString('class5excavators.com', $content5th);
        // Must NEVER contain 1st Class website
        $this->assertStringNotContainsString('Class 1 Compactors', $content5th);
        $this->assertStringNotContainsString('class1compactors.com', $content5th);

        // 2. Export CSV with 1st Class filter
        $response1st = $this->actingAs($this->adminUser)
            ->get(route('websites.search-console.export.csv', [
                'range'  => '28d',
                'class'  => '1st Class',
                'report' => 'breakdown',
            ]));

        $response1st->assertStatus(200);
        ob_start();
        $response1st->sendContent();
        $content1st = ob_get_clean();

        // Must contain 1st Class website
        $this->assertStringContainsString('Class 1 Compactors', $content1st);
        $this->assertStringContainsString('class1compactors.com', $content1st);
        // Must NEVER contain 5th Class website
        $this->assertStringNotContainsString('Class 5 Excavators', $content1st);
        $this->assertStringNotContainsString('class5excavators.com', $content1st);

        // 3. Export CSV with Member filter (only Pich)
        $responseMember = $this->actingAs($this->adminUser)
            ->get(route('websites.search-console.export.csv', [
                'range'   => '28d',
                'members' => $this->memberPich->id,
                'report'  => 'breakdown',
            ]));
        $responseMember->assertStatus(200);
        ob_start();
        $responseMember->sendContent();
        $contentMember = ob_get_clean();

        // Must contain Pich's website
        $this->assertStringContainsString('Class 1 Compactors', $contentMember);
        // Must NOT contain Samnang's 5th Class Excavators
        $this->assertStringNotContainsString('Class 5 Excavators', $contentMember);

        // 4. Export PDF with 5th Class filter
        $pdfResponse = $this->actingAs($this->adminUser)
            ->get(route('websites.search-console.export.pdf', [
                'range' => '28d',
                'class' => '5th Class',
            ]));
        $pdfResponse->assertStatus(200);
        $pdfResponse->assertHeader('content-type', 'application/pdf');
    }

    /**
     * Test index status auto-syncs directly without manual filling or modals
     */
    public function test_index_status_auto_syncs_directly_without_manual_fill_modal()
    {
        $site = Website::create([
            'name'       => 'Thunder Dumpsters',
            'url'        => 'https://thunderdump.com',
            'category'   => '1st Class',
            'status'     => 'Live',
            'handled_by' => $this->memberPich->id,
        ]);

        $mapped = GoogleSearchConsoleSite::create([
            'website_id'       => $site->id,
            'site_url'         => 'sc-domain:thunderdump.com',
            'permission_level' => 'siteFullUser',
            'is_sync_enabled'  => true,
        ]);

        \App\Models\GoogleSearchConsole\GoogleSearchConsoleSitemap::create([
            'website_id'      => $site->id,
            'site_url'        => 'sc-domain:thunderdump.com',
            'sitemap_url'     => 'https://thunderdump.com/sitemap.xml',
            'submitted_count' => 260,
            'indexed_count'   => 47,
            'status'          => 'success',
        ]);

        // Request index status with auto-sync (refresh = 1)
        $res = $this->actingAs($this->adminUser)
            ->getJson(route('websites.search-console.index-status', [
                'website_id' => $site->id,
                'refresh'    => 1,
            ]));

        $res->assertStatus(200);
        $data = $res->json();

        $this->assertTrue($data['success']);
        $this->assertEquals(47, $data['summary']['total_indexed']);
        $this->assertGreaterThan(0, $data['summary']['total_not_indexed']);
        $this->assertNotEmpty($data['summary']['gsc_reasons']);
        $this->assertNotNull($data['summary']['gsc_last_updated']);

        // Verify mapped site record in DB was automatically persisted without manual form submission
        $freshMapped = $mapped->fresh();
        $this->assertEquals(47, $freshMapped->gsc_indexed_pages);
        $this->assertNotNull($freshMapped->gsc_indexing_updated_at);

        // Verify blade view has auto-sync button and no manual edit modal
        $viewRes = $this->actingAs($this->adminUser)
            ->get(route('websites.search-console.index'));

        $viewRes->assertStatus(200);
        $viewRes->assertSee('Auto-Sync from GSC');
        $viewRes->assertDontSee('Sync / Edit GSC Numbers');
        $viewRes->assertDontSee('showCoverageSnapshotModal');
    }

    /**
     * Test PDF exports across all 4 subpages: Rankings, Compare, Sitemaps
     */
    public function test_subpage_pdf_exports()
    {
        // 1. Rankings PDF
        $resRankings = $this->actingAs($this->adminUser)
            ->get(route('websites.search-console.export.pdf', [
                'view'  => 'rankings',
                'range' => '28d',
            ]));
        $resRankings->assertStatus(200);
        $resRankings->assertHeader('content-type', 'application/pdf');

        // 2. Compare PDF
        $resCompare = $this->actingAs($this->adminUser)
            ->get(route('websites.search-console.export.pdf', [
                'view'   => 'compare',
                'site_a' => $this->websiteA->id,
                'site_b' => $this->websiteB->id,
                'range'  => '28d',
            ]));
        $resCompare->assertStatus(200);
        $resCompare->assertHeader('content-type', 'application/pdf');

        // 3. Sitemaps PDF
        $resSitemaps = $this->actingAs($this->adminUser)
            ->get(route('websites.search-console.export.pdf', [
                'view'       => 'sitemaps',
                'website_id' => $this->websiteA->id,
            ]));
        $resSitemaps->assertStatus(200);
        $resSitemaps->assertHeader('content-type', 'application/pdf');
    }

    /**
     * Test Excel XLSX exports across all 4 subpages: Rankings, Compare, Sitemaps
     */
    public function test_subpage_excel_exports()
    {
        // 1. Rankings Excel
        $resRankings = $this->actingAs($this->adminUser)
            ->get(route('websites.search-console.export.excel', [
                'view'  => 'rankings',
                'range' => '28d',
            ]));
        $resRankings->assertStatus(200);
        $resRankings->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // 2. Compare Excel
        $resCompare = $this->actingAs($this->adminUser)
            ->get(route('websites.search-console.export.excel', [
                'view'   => 'compare',
                'site_a' => $this->websiteA->id,
                'site_b' => $this->websiteB->id,
                'range'  => '28d',
            ]));
        $resCompare->assertStatus(200);
        $resCompare->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // 3. Sitemaps Excel
        $resSitemaps = $this->actingAs($this->adminUser)
            ->get(route('websites.search-console.export.excel', [
                'view'       => 'sitemaps',
                'website_id' => $this->websiteA->id,
            ]));
        $resSitemaps->assertStatus(200);
        $resSitemaps->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /**
     * Test CSV exports for Compare and Sitemaps
     */
    public function test_subpage_csv_exports()
    {
        // 1. Compare CSV
        $resCompare = $this->actingAs($this->adminUser)
            ->get(route('websites.search-console.export.csv', [
                'view'   => 'compare',
                'site_a' => $this->websiteA->id,
                'site_b' => $this->websiteB->id,
                'range'  => '28d',
            ]));
        $resCompare->assertStatus(200);
        $resCompare->assertHeader('content-type', 'text/csv; charset=UTF-8');
        ob_start();
        $resCompare->sendContent();
        $compareCsv = ob_get_clean();
        $this->assertStringContainsString('HEAD-TO-HEAD SUMMARY', $compareCsv);
        $this->assertStringContainsString('Website A (Test)', $compareCsv);
        $this->assertStringContainsString('Website B (Test)', $compareCsv);

        // 2. Sitemaps CSV
        $resSitemaps = $this->actingAs($this->adminUser)
            ->get(route('websites.search-console.export.csv', [
                'view'       => 'sitemaps',
                'website_id' => $this->websiteA->id,
            ]));
        $resSitemaps->assertStatus(200);
        $resSitemaps->assertHeader('content-type', 'text/csv; charset=UTF-8');
        ob_start();
        $resSitemaps->sendContent();
        $sitemapsCsv = ob_get_clean();
        $this->assertStringContainsString('INDEXATION OVERVIEW', $sitemapsCsv);
        $this->assertStringContainsString('Website A (Test)', $sitemapsCsv);
    }
}




