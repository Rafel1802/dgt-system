<?php

namespace Tests\Feature;

use App\Models\Website;
use App\Models\WebsitePost;
use App\Services\WebsiteBlogPostService;
use Tests\TestCase;

class WebsiteBlogPostThemeTest extends TestCase
{
    protected WebsiteBlogPostService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WebsiteBlogPostService::class);
    }

    public function test_groundripper_and_konstructz_resolves_brand_gold()
    {
        $theme = $this->service->resolveWebsiteTheme(null, null, 'groundripper.com');
        $this->assertEquals('#8c6f38', $theme['primary']);
        $this->assertEquals('#8c6f38', $theme['link_color']);
        $this->assertEquals('#fbfaf8', $theme['cta_bg']);

        $themeKonstructz = $this->service->resolveWebsiteTheme(null, null, 'konstructz.com');
        $this->assertEquals('#8c6f38', $themeKonstructz['primary']);
        $this->assertEquals('#8c6f38', $themeKonstructz['link_color']);
    }

    public function test_kuvuo_resolves_deep_forest_green()
    {
        $theme = $this->service->resolveWebsiteTheme(null, null, 'kuvuo.net');
        $this->assertEquals('#12372a', $theme['primary']);
        $this->assertEquals('#12372a', $theme['link_color']);
    }

    public function test_typhon_fleet_resolves_signature_orange()
    {
        $theme = $this->service->resolveWebsiteTheme(null, null, 'typhonnow.com');
        $this->assertEquals('#f26522', $theme['primary']);
        $this->assertEquals('#ea580c', $theme['link_color']);
    }

    public function test_machine2u_resolves_emerald_green()
    {
        $theme = $this->service->resolveWebsiteTheme(null, null, 'machine2u.com');
        $this->assertEquals('#10b981', $theme['primary']);
        $this->assertEquals('#059669', $theme['link_color']);
    }

    public function test_cta_card_renders_with_groundripper_brand_gold()
    {
        $web = new Website(['name' => 'GroundRipper', 'url' => 'https://groundripper.com']);
        $html = '<p>Check our [scissor lift](https://groundripper.com/products/scissor-lift) models.</p>';
        $styled = $this->service->ensureContentCleanAndStyled($html, null, $web, 'Scissor Lift', 'https://groundripper.com/products/scissor-lift');

        $this->assertStringContainsString('color: #8c6f38;', $styled);
        $this->assertStringContainsString('border-left: 5px solid #8c6f38;', $styled);
        $this->assertStringContainsString('background: #8c6f38;', $styled);
        $this->assertStringNotContainsString('#2563eb', $styled);
    }
}
