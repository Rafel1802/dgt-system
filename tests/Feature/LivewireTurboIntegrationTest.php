<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireTurboIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);

        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->assignRole('super-admin');
    }

    public function test_internal_test_page_renders_with_livewire_component_and_no_duplicate_assets(): void
    {
        $response = $this->actingAs($this->user)->get(route('internal.livewire-test'));

        $response->assertOk();
        $response->assertSee('Count: 0');

        // Livewire's JS must be the manually-bundled app.js copy, not the auto-injected
        // <body> script (which Turbo would re-execute on every navigation).
        $response->assertDontSee('livewire.js', false);
        $response->assertDontSee('livewire.min.js', false);

        // Turbo must still be present (self-hosted under /js, not unpkg CDN).
        $response->assertSee('turbo.es2017-esm.js', false);
        $response->assertDontSee('unpkg.com/@hotwired/turbo', false);

        // Sidebar/topbar shell must still be present alongside the Livewire component.
        $response->assertSee('id="sidebar"', false);
    }

    public function test_livewire_component_increments_server_side(): void
    {
        $this->actingAs($this->user);

        Livewire::test('test-ping')
            ->assertSet('count', 0)
            ->call('increment')
            ->assertSet('count', 1);
    }
}
