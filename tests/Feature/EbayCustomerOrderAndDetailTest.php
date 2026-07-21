<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\EbayCustomerFollowUp;
use App\Models\EbayCustomerOrder;
use App\Models\EbayCustomerRecord;
use App\Models\EbayStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EbayCustomerOrderAndDetailTest extends TestCase
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

    public function test_creating_a_new_order_record_with_products_creates_order_and_items(): void
    {
        $store = EbayStore::create(['store_name' => 'Test Store', 'is_active' => true]);

        $this->actingAs($this->user)->post(route('crm.ebay.customers.store'), [
            'tab_type' => EbayCustomerRecord::TAB_NEW_ORDER,
            'username' => 'buyer_1',
            'order_id' => 'ORD-100',
            'order_date' => now()->toDateString(),
            'order_store_id' => $store->id,
            'products' => [
                ['name' => 'Widget A', 'price' => '19.99'],
                ['name' => 'Widget B', 'price' => '5.00'],
            ],
        ]);

        $record = EbayCustomerRecord::firstOrFail();
        $this->assertEquals('ORD-100', $record->order_id);
        $this->assertEquals($store->id, $record->ebay_store_id);

        $order = EbayCustomerOrder::where('ebay_customer_record_id', $record->id)->firstOrFail();
        $this->assertEquals('ORD-100', $order->order_id);
        $this->assertEquals(2, $order->items()->count());
        $this->assertDatabaseHas('ebay_customer_order_items', ['product_name' => 'Widget A', 'price' => 19.99]);
    }

    public function test_show_page_renders_detail_history_and_purchase_sections(): void
    {
        $record = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_URGENT,
            'buyer_name' => 'Jane Client',
            'username' => 'jane_c',
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.ebay.customers.show', $record));

        $response->assertOk();
        $response->assertSee('Jane Client');
        $response->assertSee('Purchase History');
        $response->assertSee('Follow-Up Notes');
        $response->assertSee('Handled-by History');
        $response->assertSee('Status History');
    }

    public function test_logging_a_follow_up_creates_a_note(): void
    {
        $record = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_URGENT,
            'username' => 'buyer_2',
        ]);

        $response = $this->actingAs($this->user)->postJson(
            route('crm.ebay.customers.follow-up', $record),
            ['notes' => 'Called the customer, resolved the issue.']
        );

        $response->assertOk();
        $this->assertDatabaseHas('ebay_customer_follow_ups', [
            'ebay_customer_record_id' => $record->id,
            'notes' => 'Called the customer, resolved the issue.',
            'user_id' => $this->user->id,
        ]);
        $this->assertEquals(1, EbayCustomerFollowUp::count());
    }

    public function test_adding_a_new_order_to_an_existing_customer_regardless_of_current_status(): void
    {
        $record = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_RESOLVED,
            'username' => 'repeat_buyer',
            'order_id' => 'ORD-OLD',
        ]);

        $store = EbayStore::create(['store_name' => 'Test Store', 'is_active' => true]);

        $response = $this->actingAs($this->user)->postJson(
            route('crm.ebay.customers.orders.store', $record),
            [
                'order_id' => 'ORD-200',
                'order_date' => now()->toDateString(),
                'order_store_id' => $store->id,
                'products' => [['name' => 'Replacement Part', 'price' => 12.5]],
            ]
        );

        $response->assertOk();
        $record->refresh();
        $this->assertEquals('ORD-200', $record->order_id);
        $this->assertEquals(1, $record->orders()->count());
    }

    public function test_creating_a_record_for_an_existing_username_redirects_to_the_existing_record_instead_of_duplicating(): void
    {
        $this->actingAs($this->user)->post(route('crm.ebay.customers.store'), [
            'tab_type' => EbayCustomerRecord::TAB_URGENT,
            'username' => 'mesa_buyer',
            'email'    => 'mesa@example.com',
            'phone'    => '15678627664',
        ]);

        $existing = EbayCustomerRecord::firstOrFail();

        $response = $this->actingAs($this->user)->post(route('crm.ebay.customers.store'), [
            'tab_type' => EbayCustomerRecord::TAB_CANCELATION,
            'username' => 'mesa_buyer',
            'email'    => 'mesa@example.com',
            'phone'    => '15678627664',
        ]);

        $response->assertRedirect(route('crm.ebay.customers.edit', $existing));
        $this->assertEquals(1, EbayCustomerRecord::count());
    }

    public function test_resolved_is_a_valid_status_and_can_be_set_via_update(): void
    {
        $this->assertArrayHasKey(EbayCustomerRecord::TAB_RESOLVED, EbayCustomerRecord::tabs());

        $record = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_TECHNICAL,
            'username' => 'buyer_3',
        ]);

        $this->actingAs($this->user)->put(route('crm.ebay.customers.update', $record), [
            'tab_type' => EbayCustomerRecord::TAB_RESOLVED,
            'username' => 'buyer_3',
        ]);

        $this->assertEquals(EbayCustomerRecord::TAB_RESOLVED, $record->fresh()->tab_type);
        $this->assertDatabaseHas('ebay_customer_status_history', [
            'ebay_customer_record_id' => $record->id,
            'status' => EbayCustomerRecord::TAB_RESOLVED,
        ]);
    }

    public function test_switching_status_to_new_order_via_edit_requires_order_and_products(): void
    {
        $record = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_TECHNICAL,
            'username' => 'buyer_switch',
        ]);

        // Missing order_id/products entirely — should fail validation, not silently switch status.
        $response = $this->actingAs($this->user)->from(route('crm.ebay.customers.edit', $record))
            ->put(route('crm.ebay.customers.update', $record), [
                'tab_type' => EbayCustomerRecord::TAB_NEW_ORDER,
                'username' => 'buyer_switch',
            ]);

        $response->assertSessionHasErrors(['order_id', 'products']);
        $this->assertEquals(EbayCustomerRecord::TAB_TECHNICAL, $record->fresh()->tab_type);

        // With order_id + a priced product, the switch succeeds and an order is logged.
        $this->actingAs($this->user)->put(route('crm.ebay.customers.update', $record), [
            'tab_type' => EbayCustomerRecord::TAB_NEW_ORDER,
            'username' => 'buyer_switch',
            'order_id' => 'ORD-SWITCH-1',
            'order_date' => now()->toDateString(),
            'products' => [['name' => 'Part X', 'price' => '9.99']],
        ]);

        $record->refresh();
        $this->assertEquals(EbayCustomerRecord::TAB_NEW_ORDER, $record->tab_type);
        $this->assertEquals('ORD-SWITCH-1', $record->order_id);
        $this->assertEquals(1, $record->orders()->count());
        $this->assertDatabaseHas('ebay_customer_order_items', ['product_name' => 'Part X', 'price' => 9.99]);
    }

    public function test_editing_an_already_new_order_record_without_status_change_does_not_require_order_fields(): void
    {
        $record = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_NEW_ORDER,
            'username' => 'buyer_stable',
            'order_id' => 'ORD-EXISTING',
        ]);

        $response = $this->actingAs($this->user)->put(route('crm.ebay.customers.update', $record), [
            'tab_type' => EbayCustomerRecord::TAB_NEW_ORDER,
            'username' => 'buyer_stable',
            'informations' => 'Just adding a note, not a new order.',
        ]);

        $response->assertRedirect(route('crm.ebay.customers.index', ['tab_type' => EbayCustomerRecord::TAB_NEW_ORDER]));
        $this->assertEquals('Just adding a note, not a new order.', $record->fresh()->informations);
        $this->assertEquals(0, $record->orders()->count());
    }

    public function test_ebay_record_creation_reuses_the_matched_customer_regardless_of_which_contact_field_matched(): void
    {
        $this->actingAs($this->user)->post(route('crm.ebay.customers.store'), [
            'tab_type' => EbayCustomerRecord::TAB_URGENT,
            'username' => 'dupe_buyer_1',
            'email'    => 'same@example.com',
        ]);

        $customer = Customer::where('email', 'same@example.com')->firstOrFail();

        // A quick-create combobox call (a different entry point, e.g. Website CRM)
        // with the same email must reuse the customer, not spawn a second one.
        $response = $this->actingAs($this->user)->postJson(route('crm.customers.quick-create'), [
            'name'  => 'Same Person Different Form',
            'email' => 'same@example.com',
        ]);

        $response->assertCreated();
        $response->assertJson(['id' => $customer->id]);
        $this->assertEquals(1, Customer::where('email', 'same@example.com')->count());
    }

    public function test_quick_create_reuses_an_existing_customer_matched_by_phone(): void
    {
        $existing = Customer::create(['name' => 'Phone Match', 'phone' => '555-010-0100', 'status' => 'lead', 'source' => 'website', 'created_by' => $this->user->id]);

        $response = $this->actingAs($this->user)->postJson(route('crm.customers.quick-create'), [
            'name'  => 'Different Name Same Phone',
            'phone' => '555-010-0100',
        ]);

        $response->assertCreated();
        $response->assertJson(['id' => $existing->id]);
        $this->assertEquals(1, Customer::where('phone', $existing->phone)->count());
    }

    public function test_edit_page_no_longer_shows_handler_or_status_history(): void
    {
        $record = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_URGENT,
            'username' => 'buyer_4',
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.ebay.customers.edit', $record));

        $response->assertOk();
        $response->assertDontSee('Handled-by History');
        $response->assertDontSee('Status History');
    }
}
