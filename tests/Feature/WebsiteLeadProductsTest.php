<?php

namespace Tests\Feature;

use App\Enums\InquirySource;
use App\Enums\WebsiteLeadStatus;
use App\Models\Lead;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class WebsiteLeadProductsTest extends TestCase
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

    protected function makeProduct(string $name, ?string $sku = null, float $price = 1000): Product
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

    protected function makeLead(string $status = 'new_lead'): Lead
    {
        return Lead::create([
            'handled_by'  => $this->user->id,
            'client_name' => 'Test Client',
            'source'      => InquirySource::Website->value,
            'status'      => $status,
            'received_at' => now(),
        ]);
    }

    public function test_marking_successful_via_quick_status_without_products_fails(): void
    {
        $lead = $this->makeLead();

        $response = $this->actingAs($this->user)->patchJson(route('crm.website.status', $lead), [
            'status' => WebsiteLeadStatus::Successful->value,
        ]);

        $response->assertStatus(422);
        $this->assertNotEquals(WebsiteLeadStatus::Successful, $lead->fresh()->status);
    }

    public function test_marking_successful_via_quick_status_with_a_catalog_product_succeeds(): void
    {
        $lead = $this->makeLead();
        $product = $this->makeProduct('Excavator X1', 'SKU-X1', 15000);

        $response = $this->actingAs($this->user)->patchJson(route('crm.website.status', $lead), [
            'status'     => WebsiteLeadStatus::Successful->value,
            'order_date' => now()->toDateString(),
            'products' => [
                ['product_id' => $product->id, 'price' => 14500, 'quantity' => 1],
            ],
        ]);

        $response->assertOk();
        $lead->refresh();
        $this->assertEquals(WebsiteLeadStatus::Successful, $lead->status);
        $this->assertCount(1, $lead->orders);
        $this->assertCount(1, $lead->products);
        $this->assertEquals('Excavator X1', $lead->products[0]->product_name);
        $this->assertEquals('SKU-X1', $lead->products[0]->sku);
        $this->assertEquals(14500, $lead->products[0]->price);
    }

    public function test_marking_successful_with_a_manually_typed_product_name_succeeds(): void
    {
        $lead = $this->makeLead();

        $response = $this->actingAs($this->user)->patchJson(route('crm.website.status', $lead), [
            'status'     => WebsiteLeadStatus::Successful->value,
            'order_date' => now()->toDateString(),
            'products' => [
                ['product_name' => 'Custom Bucket Attachment', 'price' => 300, 'quantity' => 1],
            ],
        ]);

        $response->assertOk();
        $lead->refresh();
        $this->assertEquals(WebsiteLeadStatus::Successful, $lead->status);
        $this->assertCount(1, $lead->products);
        $this->assertNull($lead->products[0]->product_id);
        $this->assertEquals('Custom Bucket Attachment', $lead->products[0]->product_name);
    }

    public function test_non_successful_status_changes_never_require_products(): void
    {
        $lead = $this->makeLead();

        $response = $this->actingAs($this->user)->patchJson(route('crm.website.status', $lead), [
            'status' => WebsiteLeadStatus::Contacted->value,
        ]);

        $response->assertOk();
        $this->assertEquals(WebsiteLeadStatus::Contacted, $lead->fresh()->status);
    }

    public function test_marking_successful_again_on_an_already_successful_lead_adds_a_new_order_instead_of_replacing(): void
    {
        $lead = $this->makeLead(WebsiteLeadStatus::Successful->value);

        $firstProduct = $this->makeProduct('First Sale', 'F-1', 100);
        $this->actingAs($this->user)->patchJson(route('crm.website.status', $lead), [
            'status'     => WebsiteLeadStatus::Successful->value,
            'order_date' => now()->toDateString(),
            'products' => [['product_id' => $firstProduct->id, 'price' => 100, 'quantity' => 1]],
        ])->assertOk();

        $secondProduct = $this->makeProduct('Second Sale', 'S-1', 200);
        $this->actingAs($this->user)->patchJson(route('crm.website.status', $lead), [
            'status'     => WebsiteLeadStatus::Successful->value,
            'order_date' => now()->toDateString(),
            'products' => [['product_id' => $secondProduct->id, 'price' => 200, 'quantity' => 1]],
        ])->assertOk();

        $lead->refresh();
        $this->assertCount(2, $lead->orders);
        $this->assertCount(2, $lead->products);
        $this->assertEqualsCanonicalizing(['First Sale', 'Second Sale'], $lead->products->pluck('product_name')->all());
    }

    public function test_store_order_logs_a_new_order_on_an_existing_lead(): void
    {
        $lead = $this->makeLead(WebsiteLeadStatus::Successful->value);
        $product = $this->makeProduct('Repeat Purchase', 'RP-1', 750);

        $response = $this->actingAs($this->user)->postJson(route('crm.website.orders.store', $lead), [
            'order_date' => now()->toDateString(),
            'products' => [['product_id' => $product->id, 'price' => 700, 'quantity' => 1]],
        ]);

        $response->assertOk();
        $lead->refresh();
        $this->assertCount(1, $lead->orders);
        $this->assertEquals(700, $lead->products[0]->price);
    }

    public function test_store_order_allows_a_manually_typed_product_with_no_catalog_match(): void
    {
        $lead = $this->makeLead(WebsiteLeadStatus::Successful->value);

        $response = $this->actingAs($this->user)->postJson(route('crm.website.orders.store', $lead), [
            'order_date' => now()->toDateString(),
            'products' => [['product_name' => 'One-off Custom Part', 'price' => 45]],
        ]);

        $response->assertOk();
        $lead->refresh();
        $this->assertCount(1, $lead->products);
        $this->assertNull($lead->products[0]->product_id);
        $this->assertEquals('One-off Custom Part', $lead->products[0]->product_name);
    }

    public function test_store_order_requires_at_least_one_product(): void
    {
        $lead = $this->makeLead(WebsiteLeadStatus::Successful->value);

        $response = $this->actingAs($this->user)->postJson(route('crm.website.orders.store', $lead), [
            'order_date' => now()->toDateString(),
            'products' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_store_order_does_not_delete_previous_orders(): void
    {
        $lead = $this->makeLead(WebsiteLeadStatus::Successful->value);
        $productA = $this->makeProduct('Order A Item');
        $productB = $this->makeProduct('Order B Item');

        $this->actingAs($this->user)->postJson(route('crm.website.orders.store', $lead), [
            'order_date' => now()->toDateString(),
            'products' => [['product_id' => $productA->id, 'price' => 100]],
        ])->assertOk();
        $this->actingAs($this->user)->postJson(route('crm.website.orders.store', $lead), [
            'order_date' => now()->toDateString(),
            'products' => [['product_id' => $productB->id, 'price' => 200]],
        ])->assertOk();

        $lead->refresh();
        $this->assertCount(2, $lead->orders);
        $this->assertCount(2, $lead->products);
    }

    /**
     * The Edit Lead form used to embed a "Products Sold" fieldset that
     * re-synced products on every save (wipe-and-replace). Under the
     * additive order-history model that would silently create a duplicate
     * order on every unrelated field edit, so it was removed — editing a
     * lead's own fields no longer touches products at all.
     */
    public function test_editing_lead_details_no_longer_touches_products(): void
    {
        $lead = $this->makeLead();

        $response = $this->actingAs($this->user)->put(route('crm.website.update', $lead), [
            'client_name' => 'Test Client',
            'source'      => InquirySource::Website->value,
            'status'      => WebsiteLeadStatus::Successful->value,
        ]);

        $response->assertRedirect(route('crm.website.show', $lead));
        $lead->refresh();
        $this->assertEquals(WebsiteLeadStatus::Successful, $lead->status);
        $this->assertCount(0, $lead->products);
    }

    public function test_show_page_renders_order_history_when_orders_exist(): void
    {
        $lead = $this->makeLead();
        $product = $this->makeProduct('Excavator X1', 'SKU-X1', 15000);
        $this->actingAs($this->user)->patchJson(route('crm.website.status', $lead), [
            'status'     => WebsiteLeadStatus::Successful->value,
            'order_date' => now()->toDateString(),
            'products' => [['product_id' => $product->id, 'price' => 14500, 'quantity' => 1]],
        ])->assertOk();

        $this->actingAs($this->user)->get(route('crm.website.show', $lead))
            ->assertOk()->assertSee('Order History')->assertSee('Excavator X1');
    }
}
