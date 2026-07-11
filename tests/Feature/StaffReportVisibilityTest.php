<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class StaffReportVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_sales_crm_index_redirects_to_their_own_report(): void
    {
        $salesRep = $this->makeUser('sales-crm');

        $response = $this->actingAs($salesRep)->get(route('crm.reports.index'));

        $response->assertRedirect(route('crm.reports.show', $salesRep));
    }

    public function test_sales_crm_staff_page_redirects_to_their_own_report(): void
    {
        $salesRep = $this->makeUser('sales-crm');

        $response = $this->actingAs($salesRep)->get(route('crm.reports.staff'));

        $response->assertRedirect(route('crm.reports.show', $salesRep));
    }

    public function test_admin_can_view_the_staff_report_page(): void
    {
        $admin = $this->makeUser('admin-crm');

        $this->actingAs($admin)->get(route('crm.reports.staff'))->assertOk();
    }

    public function test_sales_crm_can_view_their_own_report(): void
    {
        $salesRep = $this->makeUser('sales-crm');

        $this->actingAs($salesRep)->get(route('crm.reports.show', $salesRep))->assertOk();
    }

    public function test_sales_crm_cannot_view_another_staff_members_report(): void
    {
        $salesRep = $this->makeUser('sales-crm');
        $otherRep = $this->makeUser('sales-crm');

        $this->actingAs($salesRep)->get(route('crm.reports.show', $otherRep))->assertForbidden();
    }

    public function test_sales_crm_cannot_export_another_staff_members_report(): void
    {
        $salesRep = $this->makeUser('sales-crm');
        $otherRep = $this->makeUser('sales-crm');

        $this->actingAs($salesRep)->get(route('crm.reports.export', $otherRep))->assertForbidden();
    }

    public function test_tech_support_can_view_their_own_report_but_not_someone_elses(): void
    {
        $tech = $this->makeUser('tech-support');
        $otherTech = $this->makeUser('tech-support');

        $this->actingAs($tech)->get(route('crm.reports.show', $tech))->assertOk();
        $this->actingAs($tech)->get(route('crm.reports.show', $otherTech))->assertForbidden();
    }

    public function test_admin_can_view_the_team_index_and_any_staff_report(): void
    {
        $admin = $this->makeUser('admin-crm');
        $salesRep = $this->makeUser('sales-crm');

        $this->actingAs($admin)->get(route('crm.reports.index'))->assertOk();
        $this->actingAs($admin)->get(route('crm.reports.show', $salesRep))->assertOk();
    }

    public function test_boss_can_view_the_team_index_and_any_staff_report(): void
    {
        $boss = $this->makeUser('boss');
        $salesRep = $this->makeUser('sales-crm');

        $this->actingAs($boss)->get(route('crm.reports.index'))->assertOk();
        $this->actingAs($boss)->get(route('crm.reports.show', $salesRep))->assertOk();
    }

    public function test_super_admin_can_view_the_team_index_and_any_staff_report(): void
    {
        $superAdmin = $this->makeUser('super-admin');
        $salesRep = $this->makeUser('sales-crm');

        $this->actingAs($superAdmin)->get(route('crm.reports.index'))->assertOk();
        $this->actingAs($superAdmin)->get(route('crm.reports.show', $salesRep))->assertOk();
    }
}
