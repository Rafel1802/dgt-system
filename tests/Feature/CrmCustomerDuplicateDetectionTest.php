<?php

namespace Tests\Feature;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CrmCustomerDuplicateDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);
    }

    private function makeUser(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin-crm');

        return $user;
    }

    public function test_same_name_and_email_together_is_hard_blocked(): void
    {
        $user = $this->makeUser();
        $existing = Customer::create([
            'name' => 'Jane Doe', 'email' => 'jane@example.com', 'status' => CustomerStatus::Lead->value,
            'source' => CustomerSource::Website->value, 'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->post(route('crm.customers.store'), [
            'name' => 'Jane Doe', 'email' => 'jane@example.com',
            'status' => CustomerStatus::Lead->value, 'first_purchase_date' => now()->toDateString(),
        ]);

        $response->assertRedirect(route('crm.customers.show', $existing));
        $this->assertSame(1, Customer::where('email', 'jane@example.com')->count());
    }

    public function test_same_email_under_a_different_name_is_hard_blocked(): void
    {
        $user = $this->makeUser();
        $existing = Customer::create([
            'name' => 'Jane Doe', 'email' => 'jane@example.com', 'status' => CustomerStatus::Lead->value,
            'source' => CustomerSource::Website->value, 'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->post(route('crm.customers.store'), [
            'name' => 'Someone Else', 'email' => 'jane@example.com',
            'status' => CustomerStatus::Lead->value, 'first_purchase_date' => now()->toDateString(),
        ]);

        $response->assertRedirect(route('crm.customers.show', $existing));
        $this->assertSame(1, Customer::count());
    }

    public function test_phone_only_match_is_a_soft_warning_not_a_block(): void
    {
        $user = $this->makeUser();
        Customer::create([
            'name' => 'Phone Owner', 'phone' => '(207) 213-9077', 'status' => CustomerStatus::Lead->value,
            'source' => CustomerSource::Website->value, 'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->post(route('crm.customers.store'), [
            'name' => 'Completely Different Person', 'phone' => '2072139077',
            'status' => CustomerStatus::Lead->value, 'first_purchase_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('phoneDuplicateWarning');
        $this->assertSame(1, Customer::count(), 'The new customer should not have been created yet.');
    }

    public function test_confirming_the_phone_duplicate_warning_creates_the_customer(): void
    {
        $user = $this->makeUser();
        Customer::create([
            'name' => 'Phone Owner', 'phone' => '(207) 213-9077', 'status' => CustomerStatus::Lead->value,
            'source' => CustomerSource::Website->value, 'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->post(route('crm.customers.store'), [
            'name' => 'Completely Different Person', 'phone' => '2072139077',
            'status' => CustomerStatus::Lead->value, 'first_purchase_date' => now()->toDateString(),
            'confirm_duplicate' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('phoneDuplicateWarning');
        $this->assertSame(2, Customer::count());
        $this->assertDatabaseHas('customers', ['name' => 'Completely Different Person']);
    }

    public function test_a_brand_new_customer_with_no_matches_is_created_normally(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->post(route('crm.customers.store'), [
            'name' => 'Fresh Customer', 'email' => 'fresh@example.com', 'phone' => '2071234567',
            'status' => CustomerStatus::Lead->value, 'first_purchase_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('phoneDuplicateWarning');
        $this->assertDatabaseHas('customers', ['name' => 'Fresh Customer']);
    }
}
