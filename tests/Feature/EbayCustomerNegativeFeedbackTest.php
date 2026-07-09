<?php

namespace Tests\Feature;

use App\Models\EbayCustomerRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EbayCustomerNegativeFeedbackTest extends TestCase
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

    public function test_negative_feedback_causes_and_resolution_can_be_saved(): void
    {
        $record = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_NEGATIVES,
            'buyer_name' => 'Nancy Drew',
        ]);

        $this->actingAs($this->user)->put(
            route('crm.ebay.customers.update', $record),
            [
                'tab_type' => $record->tab_type,
                'username' => 'nancy_d',
                'buyer_name' => 'Nancy Drew',
                'negative_feedback_causes' => ['Logistic issues'],
                'negative_feedback_resolved' => '1',
            ]
        )->assertRedirect(route('crm.ebay.customers.index', ['tab_type' => $record->tab_type]));

        $record->refresh();
        $this->assertEquals(['Logistic issues'], $record->negative_feedback_causes);
        $this->assertTrue($record->negative_feedback_resolved);
        $this->assertNotNull($record->negative_feedback_resolved_at);
    }

    public function test_creating_a_record_logs_its_initial_status(): void
    {
        $response = $this->actingAs($this->user)->post(route('crm.ebay.customers.store'), [
            'tab_type' => EbayCustomerRecord::TAB_NEW_ORDER,
            'username' => 'nancy_d',
            'order_id' => 'ORD-1',
            'products' => [['name' => 'Widget', 'price' => '9.99']],
        ]);
        $response->assertSessionDoesntHaveErrors();

        $record = EbayCustomerRecord::where('username', 'nancy_d')->firstOrFail();

        $this->assertDatabaseHas('ebay_customer_status_history', [
            'ebay_customer_record_id' => $record->id,
            'status' => EbayCustomerRecord::TAB_NEW_ORDER,
        ]);
    }

    public function test_changing_status_is_recorded_in_status_history(): void
    {
        $record = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_NEW_ORDER,
            'username' => 'nancy_d',
        ]);

        $response = $this->actingAs($this->user)->put(
            route('crm.ebay.customers.update', $record),
            ['tab_type' => EbayCustomerRecord::TAB_CANCELATION, 'username' => 'nancy_d']
        );
        $response->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('ebay_customer_status_history', [
            'ebay_customer_record_id' => $record->id,
            'status' => EbayCustomerRecord::TAB_CANCELATION,
        ]);
        $this->assertEquals(EbayCustomerRecord::TAB_CANCELATION, $record->fresh()->tab_type);
    }

    public function test_invalid_cause_is_rejected(): void
    {
        $record = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_NEGATIVES,
            'buyer_name' => 'Nancy Drew',
        ]);

        $this->actingAs($this->user)->put(
            route('crm.ebay.customers.update', $record),
            ['tab_type' => $record->tab_type, 'username' => 'nancy_d', 'buyer_name' => 'Nancy Drew', 'negative_feedback_causes' => ['Not A Real Cause']]
        )->assertSessionHasErrors('negative_feedback_causes.0');
    }
}
