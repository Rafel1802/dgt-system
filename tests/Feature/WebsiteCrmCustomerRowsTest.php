<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class WebsiteCrmCustomerRowsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);

        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->assignRole('super-admin');
    }

    public function test_website_sourced_customers_without_a_lead_appear_on_the_leads_page(): void
    {
        Customer::create([
            'name' => 'Chrisjen Avasarala',
            'email' => 'chrisjen@example.com',
            'status' => 'lead',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.website.index'));

        $response->assertOk();
        $response->assertSee('Chrisjen Avasarala');
    }

    public function test_ebay_sourced_customers_do_not_appear_on_the_leads_page(): void
    {
        Customer::create([
            'name' => 'Amos Burton',
            'email' => 'amos@example.com',
            'source' => 'ebay',
            'status' => 'lead',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.website.index'));

        $response->assertOk();
        $response->assertDontSee('Amos Burton');
    }

    public function test_customer_only_rows_hidden_while_filtering(): void
    {
        Customer::create([
            'name' => 'Chrisjen Avasarala',
            'email' => 'chrisjen@example.com',
            'status' => 'lead',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.website.index', ['search' => 'zzz-no-match']));

        $response->assertOk();
        $response->assertDontSee('Chrisjen Avasarala');
    }
}
