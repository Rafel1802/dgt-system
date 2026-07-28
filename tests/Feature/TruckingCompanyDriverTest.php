<?php

namespace Tests\Feature;

use App\Models\TruckingCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TruckingCompanyDriverTest extends TestCase
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

    public function test_a_driver_can_be_added_from_the_trucking_profile_page(): void
    {
        $company = TruckingCompany::create(['company_name' => 'FWD Logistics', 'is_active' => true]);

        $response = $this->actingAs($this->user)->post(route('crm.logistics.trucking.drivers.store', $company), [
            'name'  => 'Sok Dara',
            'phone' => '012-345-678',
        ]);

        $response->assertRedirect(route('crm.logistics.trucking.show', $company));
        $this->assertDatabaseHas('trucking_company_drivers', [
            'trucking_company_id' => $company->id,
            'name'                => 'Sok Dara',
            'phone'               => '012-345-678',
        ]);
    }

    public function test_multiple_drivers_can_be_added_one_at_a_time(): void
    {
        $company = TruckingCompany::create(['company_name' => 'FWD Logistics', 'is_active' => true]);

        $this->actingAs($this->user)->post(route('crm.logistics.trucking.drivers.store', $company), ['name' => 'Sok Dara']);
        $this->actingAs($this->user)->post(route('crm.logistics.trucking.drivers.store', $company), ['name' => 'Chan Vuthy']);

        $this->assertEquals(2, $company->drivers()->count());
    }

    public function test_driver_name_is_required(): void
    {
        $company = TruckingCompany::create(['company_name' => 'FWD Logistics', 'is_active' => true]);

        $response = $this->actingAs($this->user)->post(route('crm.logistics.trucking.drivers.store', $company), ['phone' => '012-345-678']);

        $response->assertSessionHasErrors('name');
        $this->assertEquals(0, $company->drivers()->count());
    }

    public function test_a_driver_can_be_removed_from_the_trucking_profile_page(): void
    {
        $company = TruckingCompany::create(['company_name' => 'FWD Logistics', 'is_active' => true]);
        $driver = $company->drivers()->create(['name' => 'Sok Dara', 'phone' => '012-345-678']);

        $response = $this->actingAs($this->user)->delete(route('crm.logistics.trucking.drivers.destroy', [$company, $driver]));

        $response->assertRedirect(route('crm.logistics.trucking.show', $company));
        $this->assertDatabaseMissing('trucking_company_drivers', ['id' => $driver->id]);
    }

    public function test_a_driver_cannot_be_removed_via_a_mismatched_company(): void
    {
        $company = TruckingCompany::create(['company_name' => 'FWD Logistics', 'is_active' => true]);
        $otherCompany = TruckingCompany::create(['company_name' => 'Other Co', 'is_active' => true]);
        $driver = $company->drivers()->create(['name' => 'Sok Dara']);

        $response = $this->actingAs($this->user)->delete(route('crm.logistics.trucking.drivers.destroy', [$otherCompany, $driver]));

        $response->assertNotFound();
        $this->assertDatabaseHas('trucking_company_drivers', ['id' => $driver->id]);
    }

    public function test_show_page_lists_drivers_and_has_an_add_driver_field(): void
    {
        $company = TruckingCompany::create(['company_name' => 'FWD Logistics', 'is_active' => true]);
        $company->drivers()->create(['name' => 'Sok Dara', 'phone' => '012-345-678']);

        $response = $this->actingAs($this->user)->get(route('crm.logistics.trucking.show', $company));

        $response->assertOk();
        $response->assertSee('Sok Dara');
        $response->assertSee('012-345-678');
        $response->assertSee(route('crm.logistics.trucking.drivers.store', $company), false);
    }

    public function test_create_page_has_no_driver_fields(): void
    {
        $response = $this->actingAs($this->user)->get(route('crm.logistics.trucking.create'));

        $response->assertOk();
        $response->assertDontSee('Add Driver');
    }

    public function test_edit_page_has_no_driver_fields(): void
    {
        $company = TruckingCompany::create(['company_name' => 'FWD Logistics', 'is_active' => true]);
        $company->drivers()->create(['name' => 'Sok Dara']);

        $response = $this->actingAs($this->user)->get(route('crm.logistics.trucking.edit', $company));

        $response->assertOk();
        $response->assertDontSee('Add Driver');
        $response->assertDontSee('Sok Dara');
    }
}
