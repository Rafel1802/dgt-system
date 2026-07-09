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

    public function test_create_case_for_creates_a_new_case_once_the_previous_one_is_resolved(): void
    {
        $customer = $this->makeCustomer();
        $record = EbayCustomerRecord::create([
            'tab_type'    => EbayCustomerRecord::TAB_TECHNICAL,
            'username'    => 'buyer2',
            'customer_id' => $customer->id,
        ]);

        $first = TechSupportCase::firstOrFail();
        $this->service->changeStatus($first, TechSupportCase::STATUS_RESOLVED, $this->admin);

        $second = $this->service->createCaseFor($record);
        $this->assertNotNull($second);
        $this->assertEquals(2, TechSupportCase::count());
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

    public function test_request_call_without_an_assigned_technician_does_not_error(): void
    {
        $customer = $this->makeCustomer();
        $case = TechSupportCase::create([
            'source_type' => EbayCustomerRecord::class,
            'source_id'   => 999,
            'customer_id' => $customer->id,
            'status'      => TechSupportCase::STATUS_NEW,
        ]);

        $callRequest = $this->service->requestCall($case, $this->admin);

        $this->assertDatabaseHas('call_requests', ['id' => $callRequest->id]);
        $this->assertEquals(0, User::role('tech-support')->get()->sum(fn ($u) => $u->notifications()->count()));
    }
}
