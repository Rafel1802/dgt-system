<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_reports_redirects_to_dashboard(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->get('/blog-reports');
        $response->assertRedirect(route('dashboard'));

        $previewResponse = $this->actingAs($user)->get('/blog-reports/preview');
        $previewResponse->assertRedirect(route('dashboard'));
    }
}
