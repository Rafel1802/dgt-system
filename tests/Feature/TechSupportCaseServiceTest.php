<?php

namespace Tests\Feature;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\EbayCustomerRecord;
use App\Models\TechSupportCase;
use App\Models\User;
use App\Services\TechSupportCaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TechSupportCaseServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected TechSupportCaseService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super-admin');
        $this->actingAs($this->admin);

        $this->service = app(TechSupportCaseService::class);
    }

    private function makeCustomer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'name'       => 'Test Customer',
            'status'     => CustomerStatus::Lead->value,
            'source'     => CustomerSource::Ebay->value,
            'created_by' => $this->admin->id,
        ], $overrides));
    }

    public function test_create_case_for_is_idempotent_while_a_case_is_open(): void
    {
        $customer = $this->makeCustomer();
        $record = EbayCustomerRecord::create([
            'tab_type'    => EbayCustomerRecord::TAB_TECHNICAL,
            'username'    => 'buyer1',
            'customer_id' => $customer->id,
        ]);

        // The model's own booted() hook already created one on TAB_TECHNICAL creation.
        $this->assertEquals(1, TechSupportCase::count());

        $again = $this->service->createCaseFor($record);
        $this->assertNull($again);
        $this->assertEquals(1, TechSupportCase::count());
    }

    public function test_create_case_for_reopens_the_same_case_once_the_previous_one_is_resolved(): void
    {
        $customer = $this->makeCustomer();
        $record = EbayCustomerRecord::create([
            'tab_type'    => EbayCustomerRecord::TAB_TECHNICAL,
            'username'    => 'buyer2',
            'customer_id' => $customer->id,
        ]);

        $first = TechSupportCase::firstOrFail();
        $this->service->changeStatus($first, TechSupportCase::STATUS_RESOLVED, $this->admin);

        // A repeat technical issue reopens the same case row instead of
        // creating a second one — no duplicate customer row on the list.
        $reopened = $this->service->createCaseFor($record);
        $this->assertNotNull($reopened);
        $this->assertEquals($first->id, $reopened->id);
        $this->assertEquals(1, TechSupportCase::count());
        $this->assertEquals(2, $reopened->occurrence_count);
        $this->assertEquals(TechSupportCase::STATUS_NEW, $reopened->status);
        $this->assertNull($reopened->resolved_at);

        $this->assertDatabaseHas('tech_support_case_logs', [
            'tech_support_case_id' => $first->id,
            'type'                  => 'reopened',
        ]);
    }

    public function test_sync_to_ebay_updates_the_record_directly_when_it_is_the_case_source(): void
    {
        $customer = $this->makeCustomer();
        $record = EbayCustomerRecord::create([
            'tab_type'    => EbayCustomerRecord::TAB_TECHNICAL,
            'username'    => 'buyer3',
            'customer_id' => $customer->id,
        ]);

        $case = TechSupportCase::firstOrFail();
        $this->service->syncToEbay($case);

        $this->assertTrue($record->fresh()->tech_resolved);
        $this->assertEquals(EbayCustomerRecord::TAB_RESOLVED, $record->fresh()->tab_type);
        $this->assertNotNull($case->fresh()->ebay_synced_at);
    }

    public function test_reopening_a_resolved_case_reverts_the_ebay_record_to_technical_issues(): void
    {
        $customer = $this->makeCustomer();
        $record = EbayCustomerRecord::create([
            'tab_type'    => EbayCustomerRecord::TAB_TECHNICAL,
            'username'    => 'buyer4',
            'customer_id' => $customer->id,
        ]);

        $case = TechSupportCase::firstOrFail();
        $this->service->changeStatus($case, TechSupportCase::STATUS_RESOLVED, $this->admin);

        $this->assertEquals(EbayCustomerRecord::TAB_RESOLVED, $record->fresh()->tab_type);
        $this->assertTrue($record->fresh()->tech_resolved);

        // Reopen the case (e.g. the issue came back).
        $this->service->changeStatus($case, TechSupportCase::STATUS_IN_PROGRESS, $this->admin);

        $record->refresh();
        $this->assertEquals(EbayCustomerRecord::TAB_TECHNICAL, $record->tab_type);
        $this->assertFalse($record->tech_resolved);
        $this->assertNull($record->tech_resolved_at);
        $this->assertNull($case->fresh()->ebay_synced_at);
    }

    public function test_reopening_to_red_case_also_reverts_the_ebay_record(): void
    {
        $customer = $this->makeCustomer();
        $record = EbayCustomerRecord::create([
            'tab_type'    => EbayCustomerRecord::TAB_TECHNICAL,
            'username'    => 'buyer5',
            'customer_id' => $customer->id,
        ]);

        $case = TechSupportCase::firstOrFail();
        $this->service->changeStatus($case, TechSupportCase::STATUS_RESOLVED, $this->admin);
        $this->service->changeStatus($case, TechSupportCase::STATUS_RED, $this->admin);

        $this->assertEquals(EbayCustomerRecord::TAB_TECHNICAL, $record->fresh()->tab_type);
        $this->assertFalse($record->fresh()->tech_resolved);
    }

    public function test_reopening_a_case_does_not_spawn_a_duplicate_case(): void
    {
        $customer = $this->makeCustomer();
        EbayCustomerRecord::create([
            'tab_type'    => EbayCustomerRecord::TAB_TECHNICAL,
            'username'    => 'buyer6',
            'customer_id' => $customer->id,
        ]);

        $case = TechSupportCase::firstOrFail();
        $this->service->changeStatus($case, TechSupportCase::STATUS_RESOLVED, $this->admin);
        $this->service->changeStatus($case, TechSupportCase::STATUS_IN_PROGRESS, $this->admin);

        $this->assertEquals(1, TechSupportCase::count());
    }

    public function test_reopening_does_not_touch_an_ebay_record_manually_moved_off_resolved(): void
    {
        $customer = $this->makeCustomer();
        $record = EbayCustomerRecord::create([
            'tab_type'    => EbayCustomerRecord::TAB_TECHNICAL,
            'username'    => 'buyer7',
            'customer_id' => $customer->id,
        ]);

        $case = TechSupportCase::firstOrFail();
        $this->service->changeStatus($case, TechSupportCase::STATUS_RESOLVED, $this->admin);

        // Staff manually re-categorize the record to something else before the case reopens.
        $record->update(['tab_type' => EbayCustomerRecord::TAB_URGENT]);

        $this->service->changeStatus($case, TechSupportCase::STATUS_IN_PROGRESS, $this->admin);

        $this->assertEquals(EbayCustomerRecord::TAB_URGENT, $record->fresh()->tab_type);
    }

    public function test_request_call_without_an_assigned_technician_does_not_error(): void
    {
        $customer = $this->makeCustomer();
        $case = TechSupportCase::create([
            'source_type' => EbayCustomerRecord::class,
            'source_id'   => 999,
            'customer_id' => $customer->id,
            'status'      => TechSupportCase::STATUS_NEW,
        ]);

        $callRequest = $this->service->requestCall($case, $this->admin, 'Please call back about the return.');

        $this->assertDatabaseHas('call_requests', ['id' => $callRequest->id]);
        $this->assertEquals(0, User::role('tech-support')->get()->sum(fn ($u) => $u->notifications()->count()));
    }
}
