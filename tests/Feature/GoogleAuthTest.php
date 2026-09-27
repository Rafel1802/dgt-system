<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
    }

    public function test_google_callback_rejects_empty_payload()
    {
        $response = $this->postJson(route('auth.google.callback'), []);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'No Google credential or access token received.',
            ]);
    }

    public function test_home_route_renders_oauth_redirect_view()
    {
        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $response->assertSee('KIUQ SYSTEM');
        $response->assertSee('BroadcastChannel');
        $response->assertSee('kiuq_google_auth');
    }

    public function test_privacy_policy_page_renders_successfully()
    {
        $response = $this->get(route('privacy-policy'));

        $response->assertStatus(200);
        $response->assertSee('Privacy Policy');
        $response->assertSee('KIUQ.COM');
        $response->assertSee('Google API Services User Data Policy');
    }

    public function test_terms_of_service_page_renders_successfully()
    {
        $response = $this->get(route('terms-of-service'));

        $response->assertStatus(200);
        $response->assertSee('Terms of Service');
        $response->assertSee('KIUQ.COM');
    }

    public function test_login_page_renders_footer_with_kiuq_copyright()
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
        $response->assertSee('KIUQ.COM');
        $response->assertSee(route('privacy-policy'));
        $response->assertSee(route('terms-of-service'));
    }
}
