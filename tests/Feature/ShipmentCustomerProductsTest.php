<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentCustomer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ShipmentCustomerProductsTest extends TestCase
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

    protected function makeProduct(string $name, ?string $sku = null, float $price = 100): Product
    {
        return Product::create([
            'name'       => $name,
            'sku'        => $sku,
            'category'   => 'excavator',
            'price'      => $price,
            'is_active'  => true,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_adding_a_customer_with_products_creates_line_items(): void
    {
        $shipment = Shipment::create(['status' => Shipment::STATUS_PENDING]);
        $productA = $this->makeProduct('Excavator X1', 'SKU-X1', 15000);
        $productB = $this->makeProduct('Bucket Attachment', 'SKU-BA', 500);

        $this->actingAs($this->user)->post(route('crm.logistics.shipments.customers.add', $shipment), [
            'recipient_name'  => 'New Customer',
            'shipping_address'=> 'Phnom Penh',
            'products'        => [
                ['product_id' => $productA->id, 'price' => 14500, 'quantity' => 1],
                ['product_id' => $productB->id, 'price' => 450, 'quantity' => 2],
            ],
        ]);

        $customer = ShipmentCustomer::firstOrFail();
        $this->assertCount(2, $customer->products);
        $this->assertEquals('Excavator X1', $customer->products[0]->product_name);
        $this->assertEquals('SKU-X1', $customer->products[0]->sku);
        $this->assertEquals(14500, $customer->products[0]->price);
        $this->assertEquals(2, $customer->products[1]->quantity);
    }

    public function test_adding_a_customer_without_any_products_is_allowed(): void
    {
        $shipment = Shipment::create(['status' => Shipment::STATUS_PENDING]);

        $response = $this->actingAs($this->user)->post(route('crm.logistics.shipments.customers.add', $shipment), [
            'recipient_name'  => 'No Products Customer',
            'shipping_address'=> 'Siem Reap',
        ]);

        $response->assertRedirect(route('crm.logistics.shipments.show', $shipment));
        $customer = ShipmentCustomer::firstOrFail();
        $this->assertCount(0, $customer->products);
    }

    public function test_updating_a_customer_replaces_its_product_line_items(): void
    {
        $shipment = Shipment::create(['status' => Shipment::STATUS_PENDING]);
        $customer = $shipment->shipmentCustomers()->create([
            'recipient_name' => 'A', 'status' => ShipmentCustomer::STATUS_PENDING, 'shipping_address' => '',
        ]);
        $oldProduct = $this->makeProduct('Old Product', 'OLD-1');
        $customer->products()->create([
            'product_id' => $oldProduct->id, 'product_name' => 'Old Product', 'sku' => 'OLD-1',
            'price' => 100, 'quantity' => 1,
        ]);

        $newProduct = $this->makeProduct('New Product', 'NEW-1', 250);

        $this->actingAs($this->user)->put(route('crm.logistics.shipments.customers.update', [$shipment, $customer]), [
            'recipient_name' => 'A', 'status' => ShipmentCustomer::STATUS_PENDING,
            'products' => [
                ['product_id' => $newProduct->id, 'price' => 250, 'quantity' => 3],
            ],
        ]);

        $customer->refresh();
        $this->assertCount(1, $customer->products);
        $this->assertEquals('New Product', $customer->products[0]->product_name);
        $this->assertEquals(3, $customer->products[0]->quantity);
    }

    public function test_tracking_number_is_optional_and_saved_when_provided(): void
    {
        $shipment = Shipment::create(['status' => Shipment::STATUS_PENDING]);
        $customer = $shipment->shipmentCustomers()->create([
            'recipient_name' => 'A', 'status' => ShipmentCustomer::STATUS_PENDING, 'shipping_address' => '',
        ]);

        $response = $this->actingAs($this->user)->put(route('crm.logistics.shipments.customers.update', [$shipment, $customer]), [
            'recipient_name' => 'A', 'status' => ShipmentCustomer::STATUS_DELIVERED,
            'tracking_number' => 'TRK-12345',
        ]);

        $response->assertRedirect(route('crm.logistics.shipments.show', $shipment));
        $this->assertEquals('TRK-12345', $customer->fresh()->tracking_number);
    }

    public function test_marking_delivered_without_a_tracking_number_still_succeeds(): void
    {
        $shipment = Shipment::create(['status' => Shipment::STATUS_PENDING]);
        $customer = $shipment->shipmentCustomers()->create([
            'recipient_name' => 'A', 'status' => ShipmentCustomer::STATUS_PENDING, 'shipping_address' => '',
        ]);

        $response = $this->actingAs($this->user)->put(route('crm.logistics.shipments.customers.update', [$shipment, $customer]), [
            'recipient_name' => 'A', 'status' => ShipmentCustomer::STATUS_DELIVERED,
        ]);

        $response->assertRedirect(route('crm.logistics.shipments.show', $shipment));
        $this->assertNull($customer->fresh()->tracking_number);
    }

    public function test_show_page_renders_with_products_and_tracking_number_present(): void
    {
        $shipment = Shipment::create(['status' => Shipment::STATUS_PENDING]);
        $customer = $shipment->shipmentCustomers()->create([
            'recipient_name' => 'A', 'status' => ShipmentCustomer::STATUS_DELIVERED,
            'shipping_address' => '', 'tracking_number' => 'TRK-99999',
        ]);
        $product = $this->makeProduct('Excavator X1', 'SKU-X1', 15000);
        $customer->products()->create([
            'product_id' => $product->id, 'product_name' => 'Excavator X1', 'sku' => 'SKU-X1',
            'price' => 15000, 'quantity' => 1,
        ]);

        $this->actingAs($this->user)->get(route('crm.logistics.shipments.show', $shipment))
            ->assertOk()
            ->assertSee('Excavator X1')
            ->assertSee('TRK-99999');
    }
}
