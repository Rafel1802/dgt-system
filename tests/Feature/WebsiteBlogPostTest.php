<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteIntegration;
use App\Models\WebsitePost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteBlogPostTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_post_cms_page_requires_authentication()
    {
        $response = $this->get('/websites/posts');
        $response->assertRedirect('/login');
    }

    public function test_authorized_user_can_access_blog_post_cms()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);

        $user = User::factory()->create([
            'id'       => 999,
            'username' => 'dara',
            'name'     => 'Dara Dhevil',
            'email'    => 'dara@kiuq.com',
            'is_active'=> true,
        ]);
        $user->assignRole('super-admin');

        $response = $this->actingAs($user)->get('/websites/posts');
        $response->assertStatus(200);
        $response->assertSee('Blog Post CMS');
        $response->assertSee('Smart Import');
        $response->assertSee('Auto-Cut (160)');
        $response->assertSee('Auto-Cut (60)');
        $response->assertSee('autoCutSeoGrammar');
    }

    public function test_store_heavy_compact_excavators_guide_blog_post_as_draft()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);

        $user = User::factory()->create([
            'id'       => 999,
            'username' => 'dara',
            'name'     => 'Dara Dhevil',
            'email'    => 'dara@kiuq.com',
            'is_active'=> true,
        ]);
        $user->assignRole('super-admin');

        $website = Website::create([
            'name'     => 'TYPHON Machinery Store',
            'url'      => 'https://typhonmachinery.com',
            'category' => 'Heavy Machinery',
            'status'   => Website::STATUS_LIVE ?? 'live',
        ]);

        $rawBody = <<<RAW
Executive Summary & Market Sizing: Stepping Up from 2.7-Ton to 4-Ton Units

The 2.7-Ton to 4-Ton excavator is often the first machine a contractor buys, and for good reason. Our machine handle irrigation trenching, light landscaping, and narrow-access work with excellent efficiency.

Technical Spotlight: TYPHON KUVUO 4.0 (SKU: TYPH-KUVUO)

Powertrain: Kubota D1703 Diesel

https://img.miniexcavator.org/ebay/Website-Team/high-end-mini_excavator/Pillar_blog_1/Kubota_diesel_engine_poster_1920b28d_2K.webp

Digging Performance

https://youtu.be/IbtRQUmLtSs?si=8hnjRpUJtwf3LH4V

Standard Equipment

https://img.miniexcavator.org/ebay/Website-Team/high-end-mini_excavator/Pillar_blog_1/Mini_excavator_commercial_advert_57071a1b_2K.webp

Technical Spotlight: TYPHON KUVUO 2.7 KONSTRUCTZ Series (SKU: TYPH-KUVUO27)

https://img.miniexcavator.org/ebay/Website-Team/high-end-mini_excavator/Pillar_blog_1/Mini_excavator_maneuvering_in_ba_fb91385b_2K.webp

Powertrain: Kubota D1105 Diesel

https://img.miniexcavator.org/ebay/Website-Team/high-end-mini_excavator/Pillar_blog_1/Kubota_diesel_engine_industrial__04b36133_2K.webp

Protected Engineering: Inverted Boom Cylinders vs. Exposed Top-Mounted Designs

[🚜 TYPHON KUVUO 2.7 | KONSTRUCTZ SERIES 2.7 Ton MINI EXCAVATOR](https://youtu.be/U0S3ldZQIFE?si=TanuxQ4maTtcLUEJ)

Summary Comparison Table

| Specification | TYPHON KUVUO 4.0 (TYPH-KUVUO) | TYPHON KUVUO 2.7 KONSTRUCTZ (TYPH-KUVUO27) |
| --- | --- | --- |
| Operating Class | 4-Ton (~8,800 lbs) | 2.7-Ton (5,300 lbs) |
| Engine | EPA-certified Kubota D1703 Diesel | Kubota D1105 3-Cylinder Direct-Injection Diesel |
| Horsepower | 25 HP @ 2,200 RPM | 19 HP @ 2,200 RPM / up to 25 HP @ 3,000 RPM |
| Max Digging Depth | 3,106 mm (approx. 10.2 ft) | 7.8 ft |
| Hydraulic System | Load-sensing variable-displacement piston pump | Axial piston variable pump |

Explore the [TYPHON KUVUO 4.0 Mini Excavator (SKU: TYPH-KUVUO)](https://typhonmachinery.com/store/) for maximum digging force.
Explore the [TYPHON KUVUO 2.7 Mini Excavator, KONSTRUCTZ Series (SKU: TYPH-KUVUO27)](https://typhonmachinery.com/store/TYPHON-KUVUO-2-7) for access agility.
RAW;

        $contentHtml = <<<HTML
<p>The heavy compact excavator class sits in the most demanding zone of modern construction.</p>
<figure class="cms-rendered-figure"><img src="https://img.miniexcavator.org/ebay/Website-Team/high-end-mini_excavator/Pillar_blog_1/Kubota_diesel_engine_poster_1920b28d_2K.webp" alt="Kubota Engine"></figure>
<div class="cms-youtube-wrapper"><iframe src="https://www.youtube-nocookie.com/embed/IbtRQUmLtSs?rel=0" allowfullscreen></iframe></div>
<div class="cms-youtube-wrapper"><iframe src="https://www.youtube-nocookie.com/embed/U0S3ldZQIFE?rel=0" allowfullscreen></iframe></div>
<table class="cms-rendered-table">
  <thead><tr><th>Specification</th><th>TYPHON KUVUO 4.0</th><th>TYPHON KUVUO 2.7</th></tr></thead>
  <tbody>
    <tr><td>Engine</td><td>Kubota D1703</td><td>Kubota D1105</td></tr>
  </tbody>
</table>
<p>Explore the <a href="https://typhonmachinery.com/store/">TYPHON KUVUO 4.0</a></p>
HTML;

        $payload = [
            'website_id'         => $website->id,
            'title'              => 'The Ultimate Guide to Heavy Compact Excavators: Sizing, Hydraulicss, and Performance in the 2.7-Ton to 4-Ton Class',
            'meta_title'         => 'Heavy Compact Excavators Guide: 2.7-Ton to 4-Ton Class | TYPHON',
            'meta_description'   => 'Learn how to size, specify, and evaluate 2.7-ton to 4-ton compact excavators. Compare the TYPHON KUVUO 4.0 and KUVUO 2.7 on Kubota power, hydraulics, and protected engineering.',
            'status'             => 'draft',
            'featured_image_url' => 'https://img.miniexcavator.org/ebay/Website-Team/high-end-mini_excavator/Pillar_blog_1/Excavator_digging_on_constructio_9ad2f5ca_2K.webp',
            'raw_content'        => $rawBody,
            'content_html'       => $contentHtml,
            'category'           => 'Excavators Guide',
        ];

        $response = $this->actingAs($user)->postJson(route('websites.posts.store'), $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'post' => [
                'title'  => 'The Ultimate Guide to Heavy Compact Excavators: Sizing, Hydraulicss, and Performance in the 2.7-Ton to 4-Ton Class',
                'status' => 'draft',
            ],
        ]);

        $this->assertDatabaseHas('website_posts', [
            'website_id' => $website->id,
            'title'      => 'The Ultimate Guide to Heavy Compact Excavators: Sizing, Hydraulicss, and Performance in the 2.7-Ton to 4-Ton Class',
            'status'     => 'draft',
        ]);

        $createdPost = WebsitePost::where('website_id', $website->id)->first();
        $this->assertEquals('Heavy Compact Excavators Guide: 2.7-Ton to 4-Ton Class', $createdPost->meta_title);
        $this->assertLessThanOrEqual(60, mb_strlen($createdPost->meta_title));
        $this->assertLessThanOrEqual(160, mb_strlen($createdPost->meta_description));
        $this->assertStringContainsString('2.7-ton to 4-ton compact excavators', $createdPost->meta_description);
        $this->assertStringContainsString('https://img.miniexcavator.org/', $createdPost->featured_image_url);

        // Verify latest-10 endpoint includes this post
        $latestResp = $this->actingAs($user)->getJson(route('websites.posts.latest10', ['website_id' => $website->id]));
        $latestResp->assertStatus(200);
        $latestResp->assertJsonFragment(['title' => 'The Ultimate Guide to Heavy Compact Excavators: Sizing, Hydraulicss, and Performance in the 2.7-Ton to 4-Ton Class']);

        // Verify show endpoint returns it
        $showResp = $this->actingAs($user)->getJson(route('websites.posts.show', $createdPost->id));
        $showResp->assertStatus(200);
        $showResp->assertJsonPath('post.id', $createdPost->id);
        $showResp->assertJsonPath('post.status', 'draft');
    }

    public function test_happymachinery_profile_and_rich_fields_stored_and_rendered()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);

        $user = User::factory()->create([
            'id'       => 998,
            'username' => 'dara_test2',
            'name'     => 'Dara Test',
            'email'    => 'dara2@kiuq.com',
            'is_active'=> true,
        ]);
        $user->assignRole('super-admin');

        $hm = Website::create([
            'name'     => 'Happy Machinery',
            'url'      => 'https://happymachinery.com',
            'category' => 'Compact Loaders & Machinery',
            'status'   => 'live',
        ]);

        // Access index page to verify profile resolution
        $response = $this->actingAs($user)->get('/websites/posts?website_id=' . $hm->id);
        $response->assertStatus(200);
        $response->assertSee('happymachinery.com/blog/');

        // Store post with rich Happy Machinery fields
        $payload = [
            'website_id'         => $hm->id,
            'title'              => 'Ultimate Mini Skid Steer Operations & Safety Guide',
            'slug'               => 'mini-skid-steer-operations-safety-guide',
            'content_html'       => '<p>Complete operations walkthrough for stand-on loaders.</p>',
            'raw_content'        => 'Complete operations walkthrough for stand-on loaders.',
            'status'             => 'draft',
            'author_name'        => 'Happy Machinery Editorial',
            'category'           => 'Jobsite Productivity',
            'playlist'           => 'Mini Skid Steer Operations & Safety Guide',
            'featured_image_url' => 'https://happymachinery.com/images/skid-steer-cover.webp',
            'cover_alt'          => 'Stand-on mini skid steer moving mulch on residential site',
            'video_url'          => 'https://www.youtube.com/watch?v=HM123456789',
            'product_sku'        => 'HM-SKID900 Stand-On Loader',
            'product_url'        => 'https://happymachinery.com/equipment',
            'meta_title'         => 'Mini Skid Steer Operations & Safety Guide | Happy Machinery',
            'meta_description'   => 'Learn essential safety checks and attachments for mini skid steers.',
            'focus_keyword'      => 'mini skid steer operations',
        ];

        $storeResp = $this->actingAs($user)->postJson(route('websites.posts.store'), $payload);
        $storeResp->assertStatus(200);
        $storeResp->assertJson(['success' => true]);

        $created = WebsitePost::where('website_id', $hm->id)->first();
        $this->assertNotNull($created);
        $this->assertEquals('Happy Machinery Editorial', $created->author_name);
        $this->assertEquals('Jobsite Productivity', $created->category);
        $this->assertEquals('Mini Skid Steer Operations & Safety Guide', $created->playlist);

        // Verify show endpoint returns rich fields
        $showResp = $this->actingAs($user)->getJson(route('websites.posts.show', $created->id));
        $showResp->assertStatus(200);
        $showResp->assertJsonPath('post.cover_alt', 'Stand-on mini skid steer moving mulch on residential site');
        $showResp->assertJsonPath('post.video_url', 'https://www.youtube.com/watch?v=HM123456789');
        $showResp->assertJsonPath('post.product_sku', 'HM-SKID900 Stand-On Loader');
        $showResp->assertJsonPath('post.focus_keyword', 'mini skid steer operations');
    }

    public function test_posting_works_across_all_websites_fleet()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);

        $user = User::factory()->create([
            'id'       => 997,
            'username' => 'dara_all_sites',
            'name'     => 'Dara All Sites',
            'email'    => 'dara_all@kiuq.com',
            'is_active'=> true,
        ]);
        $user->assignRole('super-admin');

        $sitesData = [
            ['name' => 'StoneKrusher', 'url' => 'https://stonekrusher.com', 'category' => 'Aggregate & Mining'],
            ['name' => 'American Loader', 'url' => 'https://americanloader.com', 'category' => 'Track Loaders'],
            ['name' => 'Heavy Equipment Wiki', 'url' => 'https://heavyequipment.wiki', 'category' => 'Encyclopedia'],
            ['name' => 'GroundRipper', 'url' => 'https://groundripper.com', 'category' => 'Utility Trenching'],
            ['name' => 'SkidSteers Online', 'url' => 'https://skidsteers.online', 'category' => 'Skid Steer Parts'],
        ];

        foreach ($sitesData as $sData) {
            $site = Website::create($sData);

            $payload = [
                'website_id'   => $site->id,
                'title'        => "Engineering Guide for {$site->name}",
                'slug'         => "engineering-guide-{$site->id}",
                'content_html' => "<p>Professional guide content for {$site->name}.</p>",
                'raw_content'  => "Professional guide content for {$site->name}.",
                'status'       => 'published',
                'author_name'  => "{$site->name} Team",
                'category'     => $site->category,
            ];

            $resp = $this->actingAs($user)->postJson(route('websites.posts.store'), $payload);
            $resp->assertStatus(200);
            $resp->assertJson(['success' => true]);

            $this->assertDatabaseHas('website_posts', [
                'website_id' => $site->id,
                'slug'       => "engineering-guide-{$site->id}",
                'status'     => 'published',
            ]);

            // Verify public connector API can retrieve posts for this site by ID
            $apiResp = $this->getJson("/api/websites/{$site->id}/posts");
            $apiResp->assertStatus(200);
            $apiResp->assertJsonFragment(['slug' => "engineering-guide-{$site->id}"]);

            // Verify RSS feed works for this site
            $rssResp = $this->get("/api/websites/{$site->id}/feed.xml");
            $rssResp->assertStatus(200);
            $this->assertStringContainsString("engineering-guide-{$site->id}", $rssResp->getContent());
        }

        // Test querying API by domain name
        $domainResp = $this->getJson("/api/websites/stonekrusher.com/posts");
        $domainResp->assertStatus(200);
        $domainResp->assertJsonStructure(['success', 'website', 'posts']);
    }

    public function test_website_dropdown_contains_class_and_handler_avatar_and_supports_draft_publish_and_schedule()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);

        $handler = User::factory()->create([
            'id'       => 994,
            'name'     => 'Lyza Sok',
            'email'    => 'lyza@kiuq.com',
            'is_active'=> true,
        ]);

        $admin = User::factory()->create([
            'id'       => 993,
            'name'     => 'Dara Dhevil',
            'email'    => 'dara_test@kiuq.com',
            'is_active'=> true,
        ]);
        $admin->assignRole('super-admin');

        $site = Website::create([
            'name'       => 'American Compactors',
            'url'        => 'https://americancompactors.com/',
            'category'   => '1st Class',
            'handled_by' => $handler->id,
            'status'     => Website::STATUS_LIVE ?? 'live',
        ]);

        // 1. Verify CMS index page delivers rich website dropdown data with Class and Handler profile
        $pageResp = $this->actingAs($admin)->get('/websites/posts');
        $pageResp->assertStatus(200);
        $pageResp->assertSee('American Compactors');
        $pageResp->assertSee('1st Class');
        $pageResp->assertSee('Lyza Sok');

        // 2. Test saving as DRAFT
        $draftResp = $this->actingAs($admin)->postJson(route('websites.posts.store'), [
            'website_id'   => $site->id,
            'title'        => 'Compactor Soil Compaction Guide',
            'slug'         => 'compactor-soil-compaction-guide',
            'content_html' => '<p>Draft guide</p>',
            'status'       => 'draft',
        ]);
        $draftResp->assertStatus(200);
        $draftResp->assertJson(['success' => true, 'post' => ['status' => 'draft']]);
        $this->assertDatabaseHas('website_posts', ['slug' => 'compactor-soil-compaction-guide', 'status' => 'draft']);

        // 3. Test saving as SCHEDULED
        $scheduledTime = now()->addDays(3)->format('Y-m-d H:i:s');
        $schedResp = $this->actingAs($admin)->postJson(route('websites.posts.store'), [
            'website_id'   => $site->id,
            'title'        => 'Advanced Asphalt Rolling Techniques',
            'slug'         => 'advanced-asphalt-rolling-techniques',
            'content_html' => '<p>Scheduled post</p>',
            'status'       => 'scheduled',
            'scheduled_at' => $scheduledTime,
        ]);
        $schedResp->assertStatus(200);
        $schedResp->assertJson(['success' => true, 'post' => ['status' => 'scheduled']]);
        $this->assertDatabaseHas('website_posts', ['slug' => 'advanced-asphalt-rolling-techniques', 'status' => 'scheduled']);

        // 4. Test saving as PUBLISHED
        $pubResp = $this->actingAs($admin)->postJson(route('websites.posts.store'), [
            'website_id'   => $site->id,
            'title'        => 'Vibratory Plate vs Roller Comparison',
            'slug'         => 'vibratory-plate-vs-roller-comparison',
            'content_html' => '<p>Live published post</p>',
            'status'       => 'published',
        ]);
        $pubResp->assertStatus(200);
        $pubResp->assertJson(['success' => true, 'post' => ['status' => 'published']]);
        $this->assertDatabaseHas('website_posts', ['slug' => 'vibratory-plate-vs-roller-comparison', 'status' => 'published']);
    }

    public function test_front_page_shows_latest_10_blog_posts_hub_and_new_blog_button()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);

        $admin = User::factory()->create([
            'id'       => 992,
            'name'     => 'Dara Admin',
            'email'    => 'dara_hub@kiuq.com',
            'is_active'=> true,
        ]);
        $admin->assignRole('super-admin');

        $site = Website::create([
            'name'     => 'Stone Krusher Fleet',
            'url'      => 'https://stonekrusher.com',
            'category' => 'Crushers',
            'status'   => 'live',
        ]);

        WebsitePost::create([
            'website_id'   => $site->id,
            'external_id'  => 'post-test-1',
            'title'        => 'First Crushing Guide',
            'slug'         => 'first-crushing-guide',
            'canonical_url'=> 'https://stonekrusher.com/blog/first-crushing-guide',
            'status'       => 'published',
            'published_at' => now(),
            'content_html' => '<p>Guide 1</p>',
        ]);

        $response = $this->actingAs($admin)->get('/websites/posts');

        $response->assertStatus(200);
        $response->assertSee('New Blog');
        $response->assertSee('Latest Blog Posts Across Integrated Fleet');
        $response->assertSee('Total Posts');
        $response->assertSee('Published Live');
        $response->assertSee('Drafts');
        $response->assertSee('Scheduled');
        $response->assertSee('First Crushing Guide');
        $response->assertSee('All Blogs');
    }

    public function test_concurrent_multi_user_posting_creates_unique_slugs_and_prevents_bugs_or_lag()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);

        $user1 = User::factory()->create([
            'id'       => 990,
            'name'     => 'Editor One',
            'email'    => 'editor1@kiuq.com',
            'is_active'=> true,
        ]);
        $user1->assignRole('super-admin');

        $user2 = User::factory()->create([
            'id'       => 991,
            'name'     => 'Editor Two',
            'email'    => 'editor2@kiuq.com',
            'is_active'=> true,
        ]);
        $user2->assignRole('super-admin');

        $site = Website::create([
            'name'     => 'American Excavators',
            'url'      => 'https://americanexcavators.com',
            'category' => '1st Class',
            'status'   => 'live',
        ]);

        // User 1 posts article with title "Heavy Excavator Guide 2026"
        $resp1 = $this->actingAs($user1)->postJson(route('websites.posts.store'), [
            'website_id'   => $site->id,
            'title'        => 'Heavy Excavator Guide 2026',
            'slug'         => 'heavy-excavator-guide-2026',
            'content_html' => '<p>Content from user 1</p>',
            'status'       => 'published',
        ]);

        $resp1->assertStatus(200);
        $resp1->assertJson(['success' => true]);
        $this->assertEquals('heavy-excavator-guide-2026', $resp1->json('post.slug'));

        // User 2 posts at the same time with identical requested slug on the same website
        $resp2 = $this->actingAs($user2)->postJson(route('websites.posts.store'), [
            'website_id'   => $site->id,
            'title'        => 'Heavy Excavator Guide 2026',
            'slug'         => 'heavy-excavator-guide-2026',
            'content_html' => '<p>Content from user 2 at same time</p>',
            'status'       => 'published',
        ]);

        $resp2->assertStatus(200);
        $resp2->assertJson(['success' => true]);
        // Unique slug generator must suffix with -2 to prevent collisions or crash
        $this->assertEquals('heavy-excavator-guide-2026-2', $resp2->json('post.slug'));

        // Both posts must exist in database without corruption
        $this->assertDatabaseHas('website_posts', ['id' => $resp1->json('post.id'), 'slug' => 'heavy-excavator-guide-2026']);
        $this->assertDatabaseHas('website_posts', ['id' => $resp2->json('post.id'), 'slug' => 'heavy-excavator-guide-2026-2']);
    }

    public function test_website_profiles_have_strictly_one_author_and_no_handler_name()
    {
        $user = User::factory()->create([
            'name'     => 'Mr. Dara (Head)',
            'email'    => 'dara@kiuq.com',
        ]);

        $site = Website::create([
            'name'        => 'americancompactors.com',
            'url'         => 'https://americancompactors.com',
            'category'    => '1st Class',
            'status'      => 'live',
            'handled_by'  => $user->id, // Handler should NOT leak into website author
        ]);

        $controller = app(\App\Http\Controllers\WebsiteBlogPostController::class);
        $profile = $controller->resolveWebsiteProfile($site);

        $this->assertIsArray($profile['authors']);
        $this->assertCount(1, $profile['authors'], 'Each website profile must have strictly ONE author');
        $this->assertEquals($profile['default_author'], $profile['authors'][0]);
        $this->assertStringNotContainsString('Dara', $profile['authors'][0]);
        $this->assertStringNotContainsString('Sor', $profile['authors'][0]);
        $this->assertStringNotContainsString('KiuQ', $profile['authors'][0]);
    }

    public function test_cms_studio_editor_has_independent_two_column_scrolling_classes()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);

        $user = User::factory()->create([
            'id'       => 999,
            'username' => 'dara',
            'name'     => 'Dara Dhevil',
            'email'    => 'dara@kiuq.com',
            'is_active'=> true,
        ]);
        $user->assignRole('super-admin');

        $response = $this->actingAs($user)->get('/websites/posts');
        $response->assertStatus(200);
        $response->assertSee('cms-studio-screen');
        $response->assertSee('cms-studio-col-left');
        $response->assertSee('cms-studio-col-right');
        $response->assertSee('cms-visual-editor');
        $response->assertSee('setEditorMode');
        $response->assertSee('syncVisualCanvasFromRaw');
    }

    public function test_statuses_are_strictly_normalized_to_published_draft_and_scheduled()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);

        $user = User::factory()->create([
            'id'       => 999,
            'username' => 'dara',
            'name'     => 'Dara Dhevil',
            'email'    => 'dara@kiuq.com',
            'is_active'=> true,
        ]);
        $user->assignRole('super-admin');

        $website = Website::create([
            'name'     => 'Status Test Machinery',
            'url'      => 'https://statustest.com',
            'category' => 'Machinery',
            'status'   => 'live',
        ]);

        // 1. Model normalization helper
        $this->assertEquals(WebsitePost::STATUS_PUBLISHED, WebsitePost::normalizeStatus('publish'));
        $this->assertEquals(WebsitePost::STATUS_PUBLISHED, WebsitePost::normalizeStatus('PUBLISH'));
        $this->assertEquals(WebsitePost::STATUS_PUBLISHED, WebsitePost::normalizeStatus('published'));
        $this->assertEquals(WebsitePost::STATUS_PUBLISHED, WebsitePost::normalizeStatus('public'));
        $this->assertEquals(WebsitePost::STATUS_SCHEDULED, WebsitePost::normalizeStatus('scheduled'));
        $this->assertEquals(WebsitePost::STATUS_SCHEDULED, WebsitePost::normalizeStatus('future'));
        $this->assertEquals(WebsitePost::STATUS_DRAFT, WebsitePost::normalizeStatus('draft'));
        $this->assertEquals(WebsitePost::STATUS_DRAFT, WebsitePost::normalizeStatus('pending'));
        $this->assertEquals(WebsitePost::STATUS_DRAFT, WebsitePost::normalizeStatus(null));

        // 2. Creating posts with legacy WordPress status 'publish' automatically normalizes to 'published'
        $postLegacyWp = WebsitePost::create([
            'website_id'    => $website->id,
            'external_id'   => 'wp-101',
            'title'         => 'Legacy WP Post',
            'slug'          => 'legacy-wp-post',
            'canonical_url' => 'https://statustest.com/blog/legacy-wp-post',
            'status'        => 'publish', // Raw WordPress value
            'published_at'  => now(),
        ]);
        $this->assertEquals('published', $postLegacyWp->status);

        $postPublic = WebsitePost::create([
            'website_id'    => $website->id,
            'external_id'   => 'wp-102',
            'title'         => 'Public Post',
            'slug'          => 'public-post',
            'canonical_url' => 'https://statustest.com/blog/public-post',
            'status'        => 'public',
            'published_at'  => now(),
        ]);
        $this->assertEquals('published', $postPublic->status);

        $postFuture = WebsitePost::create([
            'website_id'    => $website->id,
            'external_id'   => 'wp-103',
            'title'         => 'Future Post',
            'slug'          => 'future-post',
            'canonical_url' => 'https://statustest.com/blog/future-post',
            'status'        => 'future',
            'scheduled_at'  => now()->addDays(2),
        ]);
        $this->assertEquals('scheduled', $postFuture->status);

        // 3. Service getLatest10Posts returns normalized statuses
        $service = app(\App\Services\WebsiteBlogPostService::class);
        $posts = $service->getLatest10Posts($website->id);

        foreach ($posts as $p) {
            $this->assertContains($p['status'], ['published', 'draft', 'scheduled'], 'Every post status must strictly be published, draft, or scheduled');
            $this->assertNotEquals('publish', $p['status'], 'Status must never be raw unnormalized "publish"');
        }

        // 4. View contains status normalization functions
        $response = $this->actingAs($user)->get('/websites/posts');
        $response->assertStatus(200);
        $response->assertSee('normalizeStatus');
        $response->assertSee('statusLabel');
        $response->assertSee('statusBadgeClass');
        $response->assertSee('statusDotClass');
    }

    public function test_draft_post_is_returned_when_filtering_by_draft_even_if_outside_top_10()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);

        $user = User::factory()->create([
            'id'       => 996,
            'username' => 'dara_filter_tester',
            'name'     => 'Dara Filter Tester',
            'email'    => 'filter_tester@kiuq.com',
            'is_active'=> true,
        ]);
        $user->assignRole('super-admin');

        $website = Website::create([
            'name'     => 'Filter Fleet Store',
            'url'      => 'https://fleetfiltertest.com',
            'category' => 'Industrial',
            'status'   => Website::STATUS_LIVE ?? 'live',
        ]);

        // Create 1 draft post first (lower ID)
        $draftPost = WebsitePost::create([
            'website_id'    => $website->id,
            'external_id'   => 'draft-item-1',
            'title'         => 'Important Secret Draft Article',
            'slug'          => 'important-secret-draft-article',
            'canonical_url' => 'https://fleetfiltertest.com/blog/important-secret-draft-article',
            'status'        => 'draft',
        ]);

        // Create 12 published posts after it (higher IDs that would push it out of a naive take(10))
        for ($i = 1; $i <= 12; $i++) {
            WebsitePost::create([
                'website_id'    => $website->id,
                'external_id'   => "pub-item-{$i}",
                'title'         => "Published Article #{$i}",
                'slug'          => "published-article-{$i}",
                'canonical_url' => "https://fleetfiltertest.com/blog/published-article-{$i}",
                'status'        => 'published',
                'published_at'  => now(),
            ]);
        }

        // Test 1: Fetch with status=draft MUST find the 1 draft
        $draftResponse = $this->actingAs($user)->getJson(route('websites.posts.latest10', [
            'website_id' => $website->id,
            'status'     => 'draft',
        ]));
        $draftResponse->assertStatus(200);
        $draftResponse->assertJsonPath('total', 1);
        $draftResponse->assertJsonFragment(['title' => 'Important Secret Draft Article']);

        // Test 2: Fetch with limit=20 returns all 13 items
        $all20Response = $this->actingAs($user)->getJson(route('websites.posts.latest10', [
            'website_id' => $website->id,
            'limit'      => '20',
        ]));
        $all20Response->assertStatus(200);
        $all20Response->assertJsonPath('total', 13);

        // Test 3: Fetch with limit=all returns all 13 items
        $allLimitResponse = $this->actingAs($user)->getJson(route('websites.posts.latest10', [
            'website_id' => $website->id,
            'limit'      => 'all',
        ]));
        $allLimitResponse->assertStatus(200);
        $allLimitResponse->assertJsonPath('total', 13);

        // Test 4: View contains "New Blog" button, Tag Keywords, and Per-Page limit selector
        $pageResponse = $this->actingAs($user)->get('/websites/posts');
        $pageResponse->assertStatus(200);
        $pageResponse->assertSee('New Blog');
        $pageResponse->assertDontSee('+ + Add More');
        $pageResponse->assertSee('Meta Title');
        $pageResponse->assertSee('Meta Description');
        $pageResponse->assertSee('Tag Keywords');
        $pageResponse->assertSee('changeFeedLimit');
        $pageResponse->assertSee('setLatestStatusFilter');
        $pageResponse->assertSee("feedLimit === 'all' ? 'ALL' : 'LATEST ' + feedLimit", false);
        $pageResponse->assertSee("['10', '20', '30', '50', 'all']", false);
        $pageResponse->assertSee("@click=\"setLatestStatusFilter('draft')\"", false);

        // Test 5: Post with NULL status must be treated as draft and returned by draft filter
        WebsitePost::create([
            'website_id'    => $website->id,
            'external_id'   => 'null-status-draft-item',
            'title'         => 'Draft Post With Null Status',
            'slug'          => 'draft-post-with-null-status',
            'canonical_url' => 'https://fleetfiltertest.com/blog/draft-post-with-null-status',
            'status'        => null,
        ]);

        $nullDraftResponse = $this->actingAs($user)->getJson(route('websites.posts.latest10', [
            'website_id' => $website->id,
            'status'     => 'draft',
        ]));
        $nullDraftResponse->assertStatus(200);
        $nullDraftResponse->assertJsonPath('total', 2);
        $nullDraftResponse->assertJsonFragment(['title' => 'Draft Post With Null Status']);
    }

    public function test_show_endpoint_and_editor_never_returns_empty_paragraphs_for_draft_or_new()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);

        $user = User::factory()->create([
            'id'       => 9999,
            'email'    => 'content-tester@kiuq.com',
            'is_active'=> true,
        ]);
        $user->assignRole('super-admin');

        $website = Website::create([
            'name'     => 'Roller Fleet Test',
            'url'      => 'https://rollerfleettest.com',
            'category' => 'Road Rollers',
            'status'   => 'live',
        ]);

        // Post with null content_html and null raw_content, but with excerpt
        $draftWithExcerpt = WebsitePost::create([
            'website_id'    => $website->id,
            'external_id'   => 'draft-excerpt-209',
            'title'         => 'Road roller uneven compaction',
            'slug'          => 'why-road-roller-leaves-uneven-compaction',
            'canonical_url' => 'https://rollerfleettest.com/blog/why-road-roller-leaves-uneven-compaction',
            'status'        => 'draft',
            'excerpt'       => 'Fix uneven road roller compaction by troubleshooting travel speed and lift thickness.',
            'content_html'  => null,
            'raw_content'   => null,
        ]);

        // 1. Show endpoint MUST synthesize non-empty paragraphs from excerpt
        $res = $this->actingAs($user)->getJson("/websites/posts/{$draftWithExcerpt->id}");
        $res->assertStatus(200);
        $res->assertJsonPath('success', true);
        $data = $res->json('post');
        $this->assertNotEmpty($data['content_html']);
        $this->assertNotEmpty($data['raw_content']);
        $this->assertStringContainsString('Fix uneven road roller compaction', $data['raw_content']);
        $this->assertStringContainsString('Overview & Practical Applications', $data['raw_content']);

        // 2. Post with totally blank content and no excerpt must also synthesize starter paragraphs
        $blankPost = WebsitePost::create([
            'website_id'    => $website->id,
            'external_id'   => 'blank-post-test',
            'title'         => 'Brand New Equipment Model',
            'slug'          => 'brand-new-equipment-model',
            'canonical_url' => 'https://rollerfleettest.com/blog/brand-new-equipment-model',
            'status'        => 'draft',
            'excerpt'       => null,
            'content_html'  => null,
            'raw_content'   => null,
        ]);

        $resBlank = $this->actingAs($user)->getJson("/websites/posts/{$blankPost->id}");
        $resBlank->assertStatus(200);
        $blankData = $resBlank->json('post');
        $this->assertNotEmpty($blankData['content_html']);
        $this->assertNotEmpty($blankData['raw_content']);
        $this->assertStringContainsString('Brand New Equipment Model', $blankData['raw_content']);

        // 3. Page view has getDefaultStarterContent for "add new"
        $page = $this->actingAs($user)->get('/websites/posts');
        $page->assertStatus(200);
        $page->assertSee('getDefaultStarterContent');
        $page->assertSee('Key Operational Overview & Specifications', false);
    }

    public function test_batch_store_multiple_blog_posts_in_one_click()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);

        $user = User::factory()->create([
            'id'       => 9998,
            'username' => 'batch_editor',
            'name'     => 'Multi Author',
            'email'    => 'batch@kiuq.com',
            'is_active'=> true,
        ]);
        $user->assignRole('super-admin');

        $siteA = Website::create([
            'name'     => 'American Excavators',
            'url'      => 'https://americanexcavator.net',
            'category' => 'Excavation',
            'status'   => 'live',
        ]);

        $siteB = Website::create([
            'name'     => 'US Compactors Fleet',
            'url'      => 'https://uscompactors.com',
            'category' => 'Compaction',
            'status'   => 'live',
        ]);

        $payload = [
            'posts' => [
                [
                    'website_id'   => $siteA->id,
                    'title'        => 'First Batch Article For Excavators',
                    'slug'         => 'first-batch-article-for-excavators',
                    'content_html' => '<p>Article 1 full content for Excavators fleet.</p>',
                    'raw_content'  => 'Article 1 full content for Excavators fleet.',
                    'status'       => 'published',
                    'author_name'  => 'Excavator Team',
                    'category'     => 'Excavators',
                ],
                [
                    'website_id'   => $siteB->id,
                    'title'        => 'Second Batch Article For Compactors',
                    'slug'         => 'second-batch-article-for-compactors',
                    'content_html' => '<p>Article 2 full content for Compactors fleet.</p>',
                    'raw_content'  => 'Article 2 full content for Compactors fleet.',
                    'status'       => 'draft',
                    'author_name'  => 'Compactor Team',
                    'category'     => 'Compactors',
                ],
            ]
        ];

        $res = $this->actingAs($user)->postJson('/websites/posts/batch', $payload);
        $res->assertStatus(200);
        $res->assertJsonPath('success', true);
        $res->assertJsonCount(2, 'results');

        $this->assertDatabaseHas('website_posts', [
            'website_id' => $siteA->id,
            'title'      => 'First Batch Article For Excavators',
            'status'     => 'published',
        ]);

        $this->assertDatabaseHas('website_posts', [
            'website_id' => $siteB->id,
            'title'      => 'Second Batch Article For Compactors',
            'status'     => 'draft',
        ]);
    }

    public function test_single_article_studio_two_column_scrollable_ui_elements_rendered()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);

        $user = User::factory()->create([
            'id'       => 999,
            'username' => 'dara',
            'name'     => 'Dara Dhevil',
            'email'    => 'dara@kiuq.com',
            'is_active'=> true,
        ]);
        $user->assignRole('super-admin');

        $response = $this->actingAs($user)->get('/websites/posts?new=1');
        $response->assertStatus(200);

        // Verify Two-Column Studio Containers
        $response->assertSee('cms-studio-screen');
        $response->assertSee('cms-studio-workspace');
        $response->assertSee('cms-studio-col-left');
        $response->assertSee('cms-studio-col-right');
        $response->assertSee('cms-visual-editor');

        // Studio Action Buttons
        $response->assertSee('Preview');
        $response->assertSee('Save as Draft');
        $response->assertSee('Publish Article');

        // SEO Meta & Tag Keywords Fields
        $response->assertSee('Meta Title');
        $response->assertSee('Meta Description');
        $response->assertSee('Tag Keywords');

        // Verify "+ + Add More" has been removed from studio header
        $response->assertDontSee('+ + Add More');
    }

    public function test_preview_staging_opens_authentic_blog_view_in_new_tab(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);

        $user = User::factory()->create([
            'username' => 'previewuser',
            'name'     => 'Preview Tester',
            'email'    => 'preview@test.com',
            'is_active'=> true,
        ]);
        $user->assignRole('super-admin');

        $website = Website::create([
            'name'        => 'Preview Test Site',
            'url'         => 'https://preview-site.com',
            'category'    => '1st Class',
            'handler_id'  => $user->id,
            'is_archived' => false,
        ]);

        $response = $this->actingAs($user)->post('/websites/posts/preview-staging', [
            'website_id'         => $website->id,
            'title'              => 'Live Staging Article Preview Title',
            'slug'               => 'live-staging-article-preview-title',
            'content_html'       => '<p>This is live staging article preview text rendered in new tab.</p>',
            'featured_image_url' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff',
            'category'           => 'Technology',
            'author_name'        => 'Preview Tester',
        ]);

        $response->assertStatus(200);
        $response->assertSee('Live Staging Article Preview Title');
        $response->assertSee('This is live staging article preview text rendered in new tab.');
        $response->assertSee('LIVE BLOG PREVIEW');
        $response->assertSee('Preview Test Site');
        $response->assertSee('Close Preview ✕');
    }

    public function test_preview_generates_shareable_link_valid_for_10_minutes_and_expires(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);

        $user = User::factory()->create([
            'username' => 'tokenpreviewuser',
            'name'     => 'Token Tester',
            'email'    => 'token@test.com',
            'is_active'=> true,
        ]);
        $user->assignRole('super-admin');

        $website = Website::create([
            'name'        => 'Ten Minute Preview Site',
            'url'         => 'https://tenmin-site.com',
            'category'    => '1st Class',
            'handler_id'  => $user->id,
            'is_archived' => false,
        ]);

        // 1. Generate 10-minute preview token via AJAX
        $postResponse = $this->actingAs($user)->postJson('/websites/posts/preview-token', [
            'website_id'         => $website->id,
            'title'              => '10 Minute Preview Title',
            'slug'               => '10-minute-preview-title',
            'content_html'       => '<p>Special content visible for 10 minutes before expiring.</p>',
            'featured_image_url' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff',
            'category'           => 'Technology',
            'author_name'        => 'Token Tester',
        ]);

        $postResponse->assertStatus(200);
        $postResponse->assertJsonStructure(['success', 'token', 'preview_url', 'expires_at', 'expires_in']);
        $token = $postResponse->json('token');
        $this->assertNotEmpty($token);

        // 2. Open public preview link without login (unauthenticated visitor / share link)
        $previewResponse = $this->get('/blog/preview/' . $token);
        $previewResponse->assertStatus(200);
        $previewResponse->assertSee('10 Minute Preview Title');
        $previewResponse->assertSee('Special content visible for 10 minutes before expiring.');
        $previewResponse->assertSee('Expires in:');
        $previewResponse->assertSee('Copy 10m Link');
        $previewResponse->assertSee('Ten Minute Preview Site');

        // 3. Expire token and verify 410 expired page
        \Illuminate\Support\Facades\Cache::forget("blog_preview_{$token}");
        $expiredResponse = $this->get('/blog/preview/' . $token);
        $expiredResponse->assertStatus(410);
        $expiredResponse->assertSee('Preview Link Expired');
        $expiredResponse->assertSee('10-Minute Limit Reached');
    }

    public function test_blog_post_can_store_and_post_specification_table_across_all_websites()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);

        $user = User::factory()->create([
            'id'       => 999,
            'username' => 'dara',
            'name'     => 'Dara Dhevil',
            'email'    => 'dara@kiuq.com',
            'is_active'=> true,
        ]);
        $user->assignRole('super-admin');

        $website1 = Website::create([
            'name'     => 'American Compactors',
            'url'      => 'https://americancompactors.com',
            'category' => 'Heavy Machinery',
            'status'   => Website::STATUS_LIVE ?? 'live',
        ]);

        $website2 = Website::create([
            'name'     => 'Happy Machinery',
            'url'      => 'https://happymachinery.com',
            'category' => 'Marketplace',
            'status'   => Website::STATUS_LIVE ?? 'live',
        ]);

        $tableHtml = '<div class="cms-table-container overflow-x-auto my-6"><table class="cms-rendered-table w-full text-xs" border="1" cellpadding="8" cellspacing="0"><thead><tr><th>Specification</th><th>TYPHON KUVUO 4.0</th><th>TYPHON KUVUO 2.7</th></tr></thead><tbody><tr><td>Operating Class</td><td>4-Ton (~8,800 lbs)</td><td>2.7-Ton (5,300 lbs)</td></tr><tr><td>Engine</td><td>Kubota D1703</td><td>Kubota D1105</td></tr></tbody></table></div>';
        $fullContentHtml = "<p>Here is the comparison table for both models:</p>\n\n" . $tableHtml;

        $payload = [
            'posts' => [
                [
                    'website_id'         => $website1->id,
                    'title'              => 'Heavy Compact Excavators Guide: 2.7-Ton to 4-Ton Class',
                    'slug'               => 'heavy-compact-excavators-guide-table-spec',
                    'content_html'       => $fullContentHtml,
                    'raw_content'        => "| Specification | TYPHON KUVUO 4.0 | TYPHON KUVUO 2.7 |\n| --- | --- | --- |\n| Operating Class | 4-Ton (~8,800 lbs) | 2.7-Ton (5,300 lbs) |\n| Engine | Kubota D1703 | Kubota D1105 |",
                    'status'             => 'draft',
                    'author_name'        => 'Editorial Team',
                    'category'           => 'Machinery Guide',
                    'meta_title'         => 'Heavy Compact Excavators Guide: 2.7-Ton to 4-Ton Class',
                    'meta_description'   => 'Compare TYPHON KUVUO 4.0 and 2.7 mini excavators specifications.',
                    'tag_keywords'       => 'mini excavator, compact excavator, specifications',
                ],
                [
                    'website_id'         => $website2->id,
                    'title'              => 'Excavator Comparison Specs Guide',
                    'slug'               => 'excavator-comparison-specs-guide',
                    'content_html'       => $fullContentHtml,
                    'raw_content'        => "| Specification | TYPHON KUVUO 4.0 | TYPHON KUVUO 2.7 |\n| --- | --- | --- |\n| Operating Class | 4-Ton (~8,800 lbs) | 2.7-Ton (5,300 lbs) |",
                    'status'             => 'draft',
                    'author_name'        => 'Happy Machinery Team',
                    'category'           => 'Equipment',
                    'meta_title'         => 'Excavator Comparison Specs Guide',
                    'meta_description'   => 'Full equipment specifications table comparison.',
                    'tag_keywords'       => 'excavator, specs table',
                ],
            ]
        ];

        $response = $this->actingAs($user)->postJson('/websites/posts/batch', $payload);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('website_posts', [
            'website_id' => $website1->id,
            'title'      => 'Heavy Compact Excavators Guide: 2.7-Ton to 4-Ton Class',
            'slug'       => 'heavy-compact-excavators-guide-table-spec',
        ]);

        $post1 = WebsitePost::where('website_id', $website1->id)->where('slug', 'heavy-compact-excavators-guide-table-spec')->first();
        $this->assertNotNull($post1);
        $this->assertStringContainsString('<table', $post1->content_html);
        $this->assertStringContainsString('Specification', $post1->content_html);
        $this->assertStringContainsString('TYPHON KUVUO 4.0', $post1->content_html);
        $this->assertStringContainsString('TYPHON KUVUO 2.7', $post1->content_html);
        $this->assertStringContainsString('Kubota D1703', $post1->content_html);

        $post2 = WebsitePost::where('website_id', $website2->id)->where('slug', 'excavator-comparison-specs-guide')->first();
        $this->assertNotNull($post2);
        $this->assertStringContainsString('<table', $post2->content_html);
        $this->assertStringContainsString('Specification', $post2->content_html);
    }

    public function test_blog_post_headings_are_cleanly_formatted_and_styled_for_all_websites()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);

        $user = User::factory()->create([
            'id'       => 999,
            'username' => 'dara',
            'name'     => 'Dara Dhevil',
            'email'    => 'dara@kiuq.com',
            'is_active'=> true,
        ]);
        $user->assignRole('super-admin');

        $website = Website::create([
            'name'     => 'TYPHON Machinery Store',
            'url'      => 'https://typhonmachinery.com',
            'category' => 'Heavy Machinery',
            'status'   => Website::STATUS_LIVE ?? 'live',
        ]);

        $content = <<<HTML
<p>KUVUO 4.0 Mini Excavator (SKU: TYPH-KUVUO) runs a load-sensing variable-displacement piston pump.</p>
<h2>Fundamentals of Hydraulic Displacement in Compact Machinery</h2>
<h3>Fixed vs. Variable Displacement</h3>
<p>Displacement is the volume of fluid a pump moves with each revolution of its input shaft.</p>
<h3>Mini Excavator Piston Pump vs. Gear Pump</h3>
<p>Displacement matters, but how that displacement is produced and controlled is just as critical.</p>
HTML;

        $payload = [
            'website_id'   => $website->id,
            'title'        => 'Fundamentals of Hydraulic Displacement in Compact Machinery',
            'slug'         => 'hydraulic-displacement-compact-machinery',
            'content_html' => $content,
            'raw_content'  => $content,
            'status'       => 'published',
        ];

        $response = $this->actingAs($user)->postJson('/websites/posts', $payload);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $post = WebsitePost::where('website_id', $website->id)->where('slug', 'hydraulic-displacement-compact-machinery')->first();
        $this->assertNotNull($post);

        // Verify H2 has universal inline styling
        $this->assertStringContainsString('<h2', $post->content_html);
        $this->assertStringContainsString('font-size: 1.5rem', $post->content_html);
        $this->assertStringContainsString('font-weight: 800', $post->content_html);
        $this->assertStringContainsString('border-bottom: 2px solid #e2e8f0', $post->content_html);
        $this->assertStringContainsString('Fundamentals of Hydraulic Displacement in Compact Machinery', $post->content_html);

        // Verify H3 has universal inline styling
        $this->assertStringContainsString('<h3', $post->content_html);
        $this->assertStringContainsString('font-size: 1.25rem', $post->content_html);
        $this->assertStringContainsString('font-weight: 700', $post->content_html);
        $this->assertStringContainsString('Fixed vs. Variable Displacement', $post->content_html);
        $this->assertStringContainsString('Mini Excavator Piston Pump vs. Gear Pump', $post->content_html);
    }

    public function test_standalone_paragraph_bold_titles_are_promoted_to_styled_headings()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);

        $user = User::factory()->create([
            'id'       => 999,
            'username' => 'dara',
            'name'     => 'Dara Dhevil',
            'email'    => 'dara@kiuq.com',
            'is_active'=> true,
        ]);
        $user->assignRole('super-admin');

        $website = Website::create([
            'name'     => 'American Compactors',
            'url'      => 'https://americancompactors.com',
            'category' => 'Heavy Equipment',
            'status'   => Website::STATUS_LIVE ?? 'live',
        ]);

        $unformattedContent = <<<HTML
<p>KUVUO 4.0 Mini Excavator runs a load-sensing pump.</p>
<p><strong>Fundamentals of Hydraulic Displacement in Compact Machinery</strong></p>
<p><strong>Fixed vs. Variable Displacement</strong></p>
<p>Displacement is the volume of fluid a pump moves with each revolution.</p>
HTML;

        $payload = [
            'website_id'   => $website->id,
            'title'        => 'Hydraulic Displacement Guide',
            'slug'         => 'hydraulic-displacement-guide-bold',
            'content_html' => $unformattedContent,
            'raw_content'  => $unformattedContent,
            'status'       => 'published',
        ];

        $response = $this->actingAs($user)->postJson('/websites/posts', $payload);
        $response->assertStatus(200);

        $post = WebsitePost::where('website_id', $website->id)->where('slug', 'hydraulic-displacement-guide-bold')->first();
        $this->assertNotNull($post);

        // Must be promoted to styled headings rather than left as plain unstyled <p><strong>
        $this->assertStringContainsString('<h2', $post->content_html);
        $this->assertStringContainsString('border-bottom: 2px solid #e2e8f0', $post->content_html);
        $this->assertStringContainsString('Fundamentals of Hydraulic Displacement in Compact Machinery', $post->content_html);

        $this->assertStringContainsString('<h3', $post->content_html);
        $this->assertStringContainsString('Fixed vs. Variable Displacement', $post->content_html);
    }

    public function test_published_post_has_public_status_and_persisted_canonical_url()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);

        $user = User::factory()->create([
            'id'       => 999,
            'username' => 'dara',
            'name'     => 'Dara Dhevil',
            'email'    => 'dara@kiuq.com',
            'is_active'=> true,
        ]);
        $user->assignRole('super-admin');

        $website = Website::create([
            'name'     => 'TYPHON Machinery Store',
            'url'      => 'https://typhonmachinery.com',
            'category' => 'Heavy Machinery',
            'status'   => Website::STATUS_LIVE ?? 'live',
        ]);

        $payload = [
            'website_id'   => $website->id,
            'title'        => 'TYPHON KUVUO 4.0 Operating Guide',
            'slug'         => 'typhon-kuvuo-4-operating-guide',
            'content_html' => '<p>The KUVUO 4.0 delivers unmatched breakout force on commercial jobsites.</p>',
            'raw_content'  => 'The KUVUO 4.0 delivers unmatched breakout force on commercial jobsites.',
            'status'       => 'published',
            'author_name'  => 'TYPHON Machinery',
            'category'     => 'Heavy Compact Excavators',
            'product_sku'  => 'TYPH-KUVUO',
            'product_url'  => 'https://typhonmachinery.com/store/kuvuo-40',
        ];

        $response = $this->actingAs($user)->postJson('/websites/posts', $payload);
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertTrue($data['success']);
        $this->assertNotNull($data['post']);
        $this->assertEquals('published', $data['post']['status']);
        $this->assertNotEmpty($data['remote_url']);
        $this->assertStringContainsString('typhonmachinery.com', $data['remote_url']);

        $post = WebsitePost::where('website_id', $website->id)->where('slug', 'typhon-kuvuo-4-operating-guide')->first();
        $this->assertNotNull($post);
        $this->assertEquals('published', $post->status);
        $this->assertStringContainsString('typhon-kuvuo-4-operating-guide', $post->canonical_url);

        // Verify tailored equipment CTA card was injected
        $this->assertStringContainsString('cms-equipment-cta', $post->content_html);
        $this->assertStringContainsString('TYPH-KUVUO', $post->content_html);
        $this->assertStringContainsString('https://typhonmachinery.com/store/kuvuo-40', $post->content_html);
    }

    public function test_batch_store_persists_public_status_and_returns_live_urls()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);

        $user = User::factory()->create([
            'id'       => 999,
            'username' => 'dara',
            'name'     => 'Dara Dhevil',
            'email'    => 'dara@kiuq.com',
            'is_active'=> true,
        ]);
        $user->assignRole('super-admin');

        $website = Website::create([
            'name'     => 'Happy Machinery',
            'url'      => 'https://happymachinery.com',
            'category' => 'Compact Loaders',
            'status'   => Website::STATUS_LIVE ?? 'live',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://happymachinery.com/*' => \Illuminate\Support\Facades\Http::response(['status' => 'success', 'value' => []], 200),
        ]);

        $batchPayload = [
            'posts' => [
                [
                    'website_id'   => $website->id,
                    'title'        => 'Mini Skid Steer Operations 101',
                    'slug'         => 'mini-skid-steer-operations-101',
                    'content_html' => '<p>Mastering mini skid steer control and daily maintenance.</p>',
                    'raw_content'  => 'Mastering mini skid steer control and daily maintenance.',
                    'status'       => 'published',
                    'author_name'  => 'Happy Machinery',
                    'category'     => 'Compact Loaders',
                ]
            ]
        ];

        $response = $this->actingAs($user)->postJson('/websites/posts/batch', $batchPayload);
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['posts']);
        $this->assertEquals('published', $data['posts'][0]['status']);
        $this->assertStringContainsString('happymachinery.com/blog/mini-skid-steer-operations-101', $data['posts'][0]['remote_url']);
    }

    public function test_seo_meta_description_and_title_auto_cut_following_english_grammar()
    {
        $service = app(\App\Services\WebsiteBlogPostService::class);

        // 1. Meta Description over 160 chars (176 chars like user screenshot)
        // Notice the dangling "and" if cut carelessly
        $longDesc = 'Learn how to size, specify, and evaluate 2.7-ton to 4-ton compact excavators. Compare the TYPHON mini excavators for durability, hydraulic performance, warranty, and operator safety.';
        $this->assertGreaterThan(160, mb_strlen($longDesc));

        $cutDesc = $service->autoCutSeoGrammar($longDesc, 160, false);
        $this->assertLessThanOrEqual(160, mb_strlen($cutDesc));
        $this->assertStringEndsWith('.', $cutDesc);
        // Must never end with broken words or dangling conjunctions
        $this->assertStringNotContainsString(', and.', $cutDesc);
        $this->assertStringNotContainsString(' and.', $cutDesc);
        $this->assertStringNotContainsString(' warranty, and.', $cutDesc);

        // 2. Dangling preposition removal
        $descWithDanglingPrep = 'Discover the absolute best compact mini excavators on the American commercial machinery market today with';
        $cutPrep = $service->autoCutSeoGrammar($descWithDanglingPrep, 70, false);
        $this->assertLessThanOrEqual(70, mb_strlen($cutPrep));
        $this->assertStringEndsWith('.', $cutPrep);
        $this->assertStringNotContainsString('with.', $cutPrep);

        // 3. Meta Title over 60 chars
        $longTitle = 'The Ultimate Guide to Heavy Compact Excavators: Buying, Sizing, and Operating mini diggers in 2026';
        $this->assertGreaterThan(60, mb_strlen($longTitle));

        $cutTitle = $service->autoCutSeoGrammar($longTitle, 60, true);
        $this->assertLessThanOrEqual(60, mb_strlen($cutTitle));
        // Titles should not end in a period or colon or comma
        $this->assertDoesNotMatchRegularExpression('/[\.,:;\-]$/', $cutTitle);

        // 4. End-to-end post store auto-cutting
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);
        $user = User::factory()->create([
            'id'       => 999,
            'username' => 'dara',
            'name'     => 'Dara Dhevil',
            'email'    => 'dara@kiuq.com',
            'is_active'=> true,
        ]);
        $user->assignRole('super-admin');

        $website = Website::firstOrCreate(
            ['name' => 'SEO Grammar Auto-Cut Test Site'],
            ['url' => 'https://seogrammartest.com', 'category' => 'Machinery', 'status' => 'live']
        );

        $payload = [
            'website_id'       => $website->id,
            'title'            => 'Clean Cut SEO Grammar Post Title',
            'meta_title'       => 'The Complete Guide to Choosing Between 2.7-Ton and 4-Ton Compact Excavators for Modern Landscaping Work',
            'meta_description' => 'Learn how to size, specify, and evaluate 2.7-ton to 4-ton compact excavators. Compare the TYPHON mini excavators for durability, hydraulic performance, warranty, and operator safety.',
            'raw_content'      => 'Article content for grammar auto-cut test.',
            'content_html'     => '<p>Article content for grammar auto-cut test.</p>',
            'status'           => 'draft',
        ];

        $response = $this->actingAs($user)->postJson(route('websites.posts.store'), $payload);
        $response->assertStatus(200);

        $post = WebsitePost::where('website_id', $website->id)->latest('id')->first();
        $this->assertNotNull($post);
        $this->assertLessThanOrEqual(60, mb_strlen($post->meta_title));
        $this->assertLessThanOrEqual(160, mb_strlen($post->meta_description));
        $this->assertStringEndsWith('.', $post->meta_description);
        $this->assertDoesNotMatchRegularExpression('/[,:;\-]$/', $post->meta_title);
    }

    public function test_outbound_post_dispatch_with_remote_connector_and_sync_failure_handling()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);
        $user = User::factory()->create([
            'id'       => 1000,
            'username' => 'dara_sync',
            'name'     => 'Dara Sync',
            'email'    => 'dara_sync@kiuq.com',
            'is_active'=> true,
        ]);
        $user->assignRole('super-admin');

        $website = Website::create([
            'name'     => 'StoneKrusher Sync Site',
            'url'      => 'https://stonekrusher.com',
            'category' => 'Mining',
            'status'   => 'live',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://stonekrusher.com/api/dgt-sync.php' => \Illuminate\Support\Facades\Http::response([
                'success' => true,
                'message' => 'Post saved successfully',
            ], 200),
        ]);

        $payload = [
            'website_id'   => $website->id,
            'title'        => 'Jaw Crusher Maintenance Guide',
            'content_html' => '<p>Comprehensive maintenance tips for primary jaw crushers.</p>',
            'status'       => 'published',
        ];

        $response = $this->actingAs($user)->postJson(route('websites.posts.store'), $payload);
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertTrue($data['remote_synced']);
        $this->assertStringContainsString('stonekrusher.com', $data['remote_url']);

        // Now test fallback when remote website is offline or no sync path exists
        $unconnectedWebsite = Website::create([
            'name'     => 'Unconnected Custom Site',
            'url'      => 'https://unconnected-site-xyz.com',
            'category' => 'Tools',
            'status'   => 'live',
        ]);

        $payload2 = [
            'website_id'   => $unconnectedWebsite->id,
            'title'        => 'Unconnected Post Title',
            'content_html' => '<p>Article saved locally in DGT Central.</p>',
            'status'       => 'published',
        ];

        $response2 = $this->actingAs($user)->postJson(route('websites.posts.store'), $payload2);
        $response2->assertStatus(200);
        $data2 = $response2->json();
        $this->assertTrue($data2['success']);
        $this->assertFalse($data2['remote_synced']);
        $this->assertStringContainsString('Central System', $data2['remote_message']);
    }

    public function test_asiamachinery_does_not_collide_with_machinery_org_and_detects_root_permalink()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);

        $user = User::factory()->create([
            'username' => 'dara_collision_test',
            'is_active'=> true,
        ]);
        $user->assignRole('super-admin');

        $service = app(\App\Services\WebsiteBlogPostService::class);

        $asiaWebsite = Website::create([
            'name'     => 'Asia Machinery',
            'url'      => 'https://asiamachinery.org',
            'category' => 'Machinery',
            'status'   => 'live',
        ]);

        // Create an existing post with root permalink
        WebsitePost::create([
            'website_id'    => $asiaWebsite->id,
            'external_id'   => 'wp-3471',
            'title'         => 'Existing Post',
            'slug'          => 'existing-post',
            'canonical_url' => 'https://asiamachinery.org/existing-post/',
            'status'        => 'published',
        ]);

        // Verify determineWebsitePostUrl detects root permalink
        $detectedUrl = $service->determineWebsitePostUrl($asiaWebsite, 'compact-dumper');
        $this->assertEquals('https://asiamachinery.org/compact-dumper/', $detectedUrl);

        // Verify that publishing a post to asiamachinery.org does NOT send it to machinery.org/api/dgt-sync.php
        \Illuminate\Support\Facades\Http::fake([
            'https://machinery.org/*' => \Illuminate\Support\Facades\Http::response(['should_not_be_called' => true], 500),
            'https://asiamachinery.org/*' => \Illuminate\Support\Facades\Http::response(['not_found' => true], 404),
        ]);

        $payload = [
            'website_id'   => $asiaWebsite->id,
            'title'        => 'Compact Material Transport Dumper',
            'content_html' => '<p>Dumpers for residential jobs.</p>',
            'status'       => 'published',
        ];

        $response = $this->actingAs($user)->postJson(route('websites.posts.store'), $payload);
        $response->assertStatus(200);

        // Assert HTTP fake for machinery.org was NEVER called
        \Illuminate\Support\Facades\Http::assertNotSent(function ($request) {
            $host = parse_url($request->url(), PHP_URL_HOST);
            return $host === 'machinery.org' || str_ends_with((string)$host, '.machinery.org');
        });
    }

    public function test_dispatch_supports_any_website_with_connector_or_rest_api()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);

        $user = User::factory()->create([
            'username' => 'dara_universal_test',
            'is_active'=> true,
        ]);
        $user->assignRole('super-admin');

        $service = app(\App\Services\WebsiteBlogPostService::class);

        // 1. Any website with DGT Connector configured
        $connectorSite = Website::create([
            'name'     => 'Universal Fleet Site',
            'url'      => 'https://universal-connector.test',
            'category' => 'Equipment',
            'status'   => 'live',
        ]);

        WebsiteIntegration::create([
            'website_id'     => $connectorSite->id,
            'connector_type' => 'wordpress_plugin',
            'api_key'        => 'key_test_123',
            'api_secret'     => 'secret_test_456',
            'endpoint_url'   => null, // test fallback to website->url
            'is_active'      => true,
            'cms_status'     => 'connected',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://universal-connector.test/wp-json/dgt/v1/posts' => \Illuminate\Support\Facades\Http::response([
                'success' => true,
                'id'      => 9999,
                'url'     => 'https://universal-connector.test/new-universal-post/',
                'status'  => 'publish',
            ], 200),
            'https://brand-new-custom.test/api/dgt-sync.php' => \Illuminate\Support\Facades\Http::response([
                'success' => true,
                'message' => 'Post saved successfully',
            ], 200),
        ]);

        $post1 = WebsitePost::create([
            'website_id'    => $connectorSite->id,
            'external_id'   => 'ext-1',
            'title'         => 'Universal Connector Post',
            'slug'          => 'universal-connector-post',
            'canonical_url' => 'https://universal-connector.test/universal-connector-post',
            'content_html'  => '<p>Content</p>',
            'status'        => 'published',
        ]);

        $res1 = $service->dispatchPostToRemoteWebsite($connectorSite, $post1);
        $this->assertTrue($res1['synced']);
        $this->assertEquals('https://universal-connector.test/new-universal-post/', $res1['url']);

        // 2. Any brand new custom website with auto-discovered /api/dgt-sync.php
        $customSite = Website::create([
            'name'     => 'Brand New Custom Site',
            'url'      => 'https://brand-new-custom.test',
            'category' => 'Custom',
            'status'   => 'live',
        ]);

        $post2 = WebsitePost::create([
            'website_id'    => $customSite->id,
            'external_id'   => 'ext-2',
            'title'         => 'Auto Discovered Post',
            'slug'          => 'auto-discovered-post',
            'canonical_url' => 'https://brand-new-custom.test/blog/auto-discovered-post',
            'content_html'  => '<p>Custom framework content</p>',
            'status'        => 'published',
        ]);

        $res2 = $service->dispatchPostToRemoteWebsite($customSite, $post2);
        $this->assertTrue($res2['synced']);
        $this->assertStringContainsString('Auto-Discovered', $res2['message']);
        $this->assertEquals('https://brand-new-custom.test/blog/auto-discovered-post', $res2['url']);
    }

    public function test_destroy_removes_post_locally_and_dispatches_remote_deletion()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);

        $user = User::factory()->create([
            'email'    => 'admin_delete_test@dgt.test',
            'username' => 'admin_delete_test',
            'is_active'=> true,
        ]);
        $user->assignRole('super-admin');

        $site = Website::create([
            'name'     => 'Delete Test Fleet Site',
            'url'      => 'https://delete-remote.test',
            'status'   => 'live',
        ]);

        WebsiteIntegration::create([
            'website_id'     => $site->id,
            'connector_type' => 'wordpress_plugin',
            'api_key'        => 'key_test_123',
            'api_secret'     => 'secret_test_456',
            'endpoint_url'   => 'https://delete-remote.test/wp-json/dgt/v1/posts',
            'is_active'      => true,
            'cms_status'     => 'connected',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://delete-remote.test/wp-json/dgt/v1/posts/delete' => \Illuminate\Support\Facades\Http::response([
                'success' => true,
                'message' => 'Post permanently deleted from WordPress site and caches purged.',
            ], 200),
        ]);

        $post = WebsitePost::create([
            'website_id'    => $site->id,
            'external_id'   => 'del-ext-1',
            'title'         => 'Post To Be Deleted',
            'slug'          => 'post-to-be-deleted',
            'status'        => 'published',
            'canonical_url' => 'https://delete-remote.test/post-to-be-deleted/',
        ]);

        $this->assertDatabaseHas('website_posts', ['id' => $post->id]);

        $response = $this->actingAs($user)->deleteJson(route('websites.posts.destroy', $post->id));

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseMissing('website_posts', ['id' => $post->id]);

        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            return str_contains($request->url(), '/wp-json/dgt/v1/posts/delete');
        });
    }

    public function test_leading_duplicate_hero_image_is_stripped_when_featured_image_url_is_present(): void
    {
        $service = app(\App\Services\WebsiteBlogPostService::class);
        $featuredUrl = 'https://example.com/wp-content/uploads/2026/10/skid-steer-hero.jpg';

        // 1. Content starting with <figure> containing the same featured image
        $htmlWithFigure = '<figure class="cms-rendered-figure"><img src="https://example.com/wp-content/uploads/2026/10/skid-steer-hero.jpg" alt="Skid Steer Hero"><figcaption>Hero</figcaption></figure><p>First body paragraph explaining skid steers.</p>';
        $cleaned = $service->ensureContentCleanAndStyled($htmlWithFigure, null, null, null, null, $featuredUrl);

        $this->assertStringNotContainsString('skid-steer-hero.jpg', $cleaned, 'Leading duplicate image must be stripped');
        $this->assertStringContainsString('First body paragraph explaining skid steers.', $cleaned);

        // 2. Content with <p><img></p>
        $htmlWithP = '<p><img src="https://example.com/wp-content/uploads/2026/10/skid-steer-hero.jpg" /></p><p>Second body paragraph.</p>';
        $cleanedP = $service->ensureContentCleanAndStyled($htmlWithP, null, null, null, null, $featuredUrl);

        $this->assertStringNotContainsString('skid-steer-hero.jpg', $cleanedP);
        $this->assertStringContainsString('Second body paragraph.', $cleanedP);

        // 3. Different image in content is NOT stripped
        $htmlDifferent = '<figure><img src="https://example.com/uploads/other-image.jpg"></figure><p>Content.</p>';
        $cleanedDiff = $service->ensureContentCleanAndStyled($htmlDifferent, null, null, null, null, $featuredUrl);
        $this->assertStringContainsString('other-image.jpg', $cleanedDiff, 'Non-duplicate images must be preserved');
    }
}





