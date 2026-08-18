<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\ShipmentCustomer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ShipmentDuplicateDetectionTest extends TestCase
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

    /** Helper: create a ShipmentCustomer tied to a fresh shipment. */
    private function makeCustomer(array $attrs): ShipmentCustomer
    {
        $shipment = Shipment::create([
            'shipment_code' => 'SHP-' . fake()->unique()->bothify('??##'),
            'status'        => Shipment::STATUS_PENDING,
        ]);

        return ShipmentCustomer::create(array_merge([
            'shipment_id'      => $shipment->id,
            'shipping_address' => '123 Test St',
            'handled_by'       => $this->user->id,
        ], $attrs));
    }

    public function test_duplicate_tracking_number_is_flagged(): void
    {
        // Pre-existing record (e.g. from an earlier import, now delivered)
        $this->makeCustomer([
            'recipient_name'  => 'Alice Johnson',
            'recipient_phone' => '+1 (555) 111-2222',
            'tracking_number' => '2026081501TYPH',
            'status'          => ShipmentCustomer::STATUS_DELIVERED,
        ]);

        // New pending record with the SAME tracking number
        $this->makeCustomer([
            'recipient_name'  => 'Alice Johnson',
            'recipient_phone' => '+1 (555) 111-2222',
            'tracking_number' => '2026081501TYPH',
            'status'          => ShipmentCustomer::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.logistics.processTrucking'));

        $response->assertOk();
        $response->assertSee('Duplicate');
    }

    public function test_same_name_and_phone_is_flagged(): void
    {
        $this->makeCustomer([
            'recipient_name'  => 'Bob Smith',
            'recipient_phone' => '+1 (555) 333-4444',
            'status'          => ShipmentCustomer::STATUS_IN_TRANSIT,
        ]);

        $this->makeCustomer([
            'recipient_name'  => 'Bob Smith',
            'recipient_phone' => '+1 (555) 333-4444',
            'status'          => ShipmentCustomer::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.logistics.processTrucking'));

        $response->assertOk();
        $response->assertSee('Duplicate');
    }

    public function test_different_names_and_phones_are_not_flagged(): void
    {
        $this->makeCustomer([
            'recipient_name'  => 'Carol White',
            'recipient_phone' => '+1 (555) 555-6666',
            'tracking_number' => '2026081502TYPH',
            'status'          => ShipmentCustomer::STATUS_PENDING,
        ]);

        $this->makeCustomer([
            'recipient_name'  => 'Dave Brown',
            'recipient_phone' => '+1 (555) 777-8888',
            'tracking_number' => '2026081503TYPH',
            'status'          => ShipmentCustomer::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.logistics.processTrucking'));

        $response->assertOk();
        $response->assertDontSee('bg-red-50 text-red-700');
        $response->assertDontSee('bg-amber-50 text-amber-700');
        $response->assertDontSee('bg-slate-100 text-slate-500');
    }

    public function test_phone_only_match_shows_possible_duplicate(): void
    {
        // Pre-existing record
        $this->makeCustomer([
            'recipient_name'  => 'Eve Green',
            'recipient_phone' => '+1 (555) 999-0000',
            'status'          => ShipmentCustomer::STATUS_DELIVERED,
        ]);

        // Different name, same phone — possible duplicate (maybe household member)
        $this->makeCustomer([
            'recipient_name'  => 'Frank Green',
            'recipient_phone' => '+1 (555) 999-0000',
            'status'          => ShipmentCustomer::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.logistics.processTrucking'));

        $response->assertOk();
        $response->assertSee('Possible Duplicate');
    }

    public function test_duplicate_badge_visible_on_loaded_page_too(): void
    {
        $this->makeCustomer([
            'recipient_name'  => 'Grace Hall',
            'recipient_phone' => '+1 (555) 222-3333',
            'tracking_number' => '2026081504TYPH',
            'status'          => ShipmentCustomer::STATUS_PENDING,
        ]);

        $this->makeCustomer([
            'recipient_name'  => 'Grace Hall',
            'recipient_phone' => '+1 (555) 222-3333',
            'tracking_number' => '2026081504TYPH',
            'status'          => ShipmentCustomer::STATUS_IN_TRANSIT,
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.logistics.loaded'));

        $response->assertOk();
        $response->assertSee('Duplicate');
    }

    public function test_within_page_same_tracking_detected(): void
    {
        $this->makeCustomer([
            'recipient_name'  => 'Hank Lee',
            'recipient_phone' => '+1 (555) 444-5555',
            'tracking_number' => '2026081599DUPE',
            'status'          => ShipmentCustomer::STATUS_PENDING,
        ]);

        $this->makeCustomer([
            'recipient_name'  => 'Hank Lee',
            'recipient_phone' => '+1 (555) 444-5555',
            'tracking_number' => '2026081599DUPE',
            'status'          => ShipmentCustomer::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.logistics.processTrucking'));

        $response->assertOk();
        $response->assertSee('Duplicate');
    }
}
