<?php

namespace Tests\Feature;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\CustomerWorkflowLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CrmNormalStaffPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);
    }

    private function makeUser(string $role, array $attrs = []): User
    {
        $user = User::factory()->create(array_merge(['is_active' => true], $attrs));
        $user->assignRole($role);

        return $user;
    }

    private function makeCustomer(?int $assignedTo = null): Customer
    {
        return Customer::create([
            'name'                 => 'Normal Staff Target',
            'status'               => CustomerStatus::Lead->value,
            'source'               => CustomerSource::Website->value,
            'created_by'           => $assignedTo ?? User::factory()->create()->id,
            'assigned_to'          => $assignedTo,
            'first_purchase_date'  => null,
            'notes'                => 'original notes',
        ]);
    }

    // ── Field whitelist for Normal Staff (own assigned customer) ─────────

    public function test_plain_sales_crm_can_change_status_and_notes_on_own_assigned_customer(): void
    {
        $user = $this->makeUser('sales-crm');
        $customer = $this->makeCustomer($user->id);

        $response = $this->actingAs($user)->put(route('crm.customers.update', $customer), [
            'name'   => 'Someone Else Entirely',
            'status' => CustomerStatus::Active->value,
            'notes'  => 'updated notes',
            'assigned_to' => User::factory()->create()->id,
        ]);

        $response->assertRedirect(route('crm.customers.show', $customer));
        $customer->refresh();

        $this->assertSame(CustomerStatus::Active, $customer->status);
        $this->assertSame('updated notes', $customer->notes);
        // Name and assignment are outside the whitelist — must stay untouched.
        $this->assertSame('Normal Staff Target', $customer->name);
        $this->assertSame($user->id, $customer->assigned_to);
    }

    public function test_plain_ebay_team_cannot_update_a_customer_assigned_to_someone_else(): void
    {
        $user = $this->makeUser('ebay-team');
        $otherRep = User::factory()->create();
        $customer = $this->makeCustomer($otherRep->id);

        $this->actingAs($user)->put(route('crm.customers.update', $customer), [
            'name'   => 'Should Not Change',
            'status' => CustomerStatus::Active->value,
        ])->assertForbidden();
    }

    public function test_crm_supervisor_via_sales_crm_role_has_full_edit_on_any_customer(): void
    {
        $user = $this->makeUser('sales-crm', ['crm_role' => 'supervisor']);
        $otherRep = User::factory()->create();
        $customer = $this->makeCustomer($otherRep->id);

        $response = $this->actingAs($user)->put(route('crm.customers.update', $customer), [
            'name'   => 'Supervisor Edited This',
            'status' => CustomerStatus::Active->value,
            'assigned_to' => $user->id,
        ]);

        $response->assertRedirect(route('crm.customers.show', $customer));
        $customer->refresh();
        $this->assertSame('Supervisor Edited This', $customer->name);
        $this->assertSame($user->id, $customer->assigned_to);
    }

    public function test_admin_crm_has_full_edit_on_any_customer(): void
    {
        $user = $this->makeUser('admin-crm');
        $customer = $this->makeCustomer(null);

        $response = $this->actingAs($user)->put(route('crm.customers.update', $customer), [
            'name'   => 'Admin Edited This',
            'status' => CustomerStatus::Active->value,
        ]);

        $response->assertRedirect(route('crm.customers.show', $customer));
        $this->assertSame('Admin Edited This', $customer->fresh()->name);
    }

    // ── Purchase Date required on create ──────────────────────────────────

    public function test_create_customer_requires_a_purchase_date(): void
    {
        $user = $this->makeUser('admin-crm');

        $response = $this->actingAs($user)->post(route('crm.customers.store'), [
            'name'   => 'No Purchase Date',
            'status' => CustomerStatus::Lead->value,
        ]);

        $response->assertSessionHasErrors('first_purchase_date');
        $this->assertDatabaseMissing('customers', ['name' => 'No Purchase Date']);
    }

    public function test_create_customer_succeeds_with_a_purchase_date(): void
    {
        $user = $this->makeUser('admin-crm');

        $response = $this->actingAs($user)->post(route('crm.customers.store'), [
            'name'                 => 'Has Purchase Date',
            'status'               => CustomerStatus::Lead->value,
            'first_purchase_date'  => now()->subDay()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('customers', ['name' => 'Has Purchase Date']);
    }

    public function test_updating_a_customer_that_already_has_a_purchase_date_requires_keeping_one(): void
    {
        $user = $this->makeUser('admin-crm');
        $customer = Customer::create([
            'name' => 'Already Has Date', 'status' => CustomerStatus::Active->value,
            'source' => CustomerSource::Website->value, 'created_by' => $user->id,
            'first_purchase_date' => now()->subWeek(),
        ]);

        $response = $this->actingAs($user)->put(route('crm.customers.update', $customer), [
            'name'   => 'Already Has Date',
            'status' => CustomerStatus::Active->value,
            'first_purchase_date' => '',
        ]);

        $response->assertSessionHasErrors('first_purchase_date');
    }

    // ── Workflow routing (Admin/Supervisor only) ──────────────────────────

    public function test_plain_sales_crm_cannot_route_a_customer(): void
    {
        $user = $this->makeUser('sales-crm');
        $customer = $this->makeCustomer($user->id);

        $this->actingAs($user)->post(route('crm.customers.route', $customer), [
            'feedback_category' => 'Technical Issue',
            'to_queue'          => 'technical',
        ])->assertForbidden();
    }

    public function test_admin_crm_can_route_a_customer_and_history_is_logged(): void
    {
        $user = $this->makeUser('admin-crm');
        $customer = $this->makeCustomer(null);

        $response = $this->actingAs($user)->post(route('crm.customers.route', $customer), [
            'feedback_category' => 'Technical Issue',
            'to_queue'          => 'technical',
            'reason'            => 'Customer reported a defect',
        ]);

        $response->assertRedirect(route('crm.customers.show', $customer));
        $customer->refresh();
        $this->assertSame(\App\Enums\CustomerQueue::Technical, $customer->current_queue);

        $this->assertDatabaseHas('customer_workflow_logs', [
            'customer_id' => $customer->id,
            'moved_by'    => $user->id,
            'to_queue'    => 'technical',
        ]);
    }

    // ── Delete stays gated exactly as before (regression) ─────────────────

    public function test_plain_sales_crm_still_cannot_delete_a_customer(): void
    {
        $user = $this->makeUser('sales-crm');
        $customer = $this->makeCustomer($user->id);

        $this->actingAs($user)->delete(route('crm.customers.destroy', $customer))->assertForbidden();
        $this->assertModelExists($customer);
    }
}
