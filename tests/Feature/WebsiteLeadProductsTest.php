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

    public function test_marking_successful_via_quick_status_with_products_succeeds(): void
    {
        $lead = $this->makeLead();
        $product = $this->makeProduct('Excavator X1', 'SKU-X1', 15000);

        $response = $this->actingAs($this->user)->patchJson(route('crm.website.status', $lead), [
            'status'   => WebsiteLeadStatus::Successful->value,
            'products' => [
                ['product_id' => $product->id, 'price' => 14500, 'quantity' => 1],
            ],
        ]);

        $response->assertOk();
        $lead->refresh();
        $this->assertEquals(WebsiteLeadStatus::Successful, $lead->status);
        $this->assertCount(1, $lead->products);
        $this->assertEquals('Excavator X1', $lead->products[0]->product_name);
        $this->assertEquals('SKU-X1', $lead->products[0]->sku);
        $this->assertEquals(14500, $lead->products[0]->price);
    }

    public function test_marking_successful_via_edit_form_without_products_fails(): void
    {
        $lead = $this->makeLead();

        $response = $this->actingAs($this->user)->put(route('crm.website.update', $lead), [
            'client_name' => 'Test Client',
            'source'      => InquirySource::Website->value,
            'status'      => WebsiteLeadStatus::Successful->value,
        ]);

        $response->assertRedirect(route('crm.website.edit', $lead));
        $response->assertSessionHasErrors('products');
        $this->assertNotEquals(WebsiteLeadStatus::Successful, $lead->fresh()->status);
    }

    public function test_marking_successful_via_edit_form_with_products_succeeds(): void
    {
        $lead = $this->makeLead();
        $product = $this->makeProduct('Forklift F2', 'SKU-F2', 8000);

        $response = $this->actingAs($this->user)->put(route('crm.website.update', $lead), [
            'client_name' => 'Test Client',
            'source'      => InquirySource::Website->value,
            'status'      => WebsiteLeadStatus::Successful->value,
            'products'    => [
                ['product_id' => $product->id, 'price' => 7800, 'quantity' => 2],
            ],
        ]);

        $response->assertRedirect(route('crm.website.show', $lead));
        $lead->refresh();
        $this->assertEquals(WebsiteLeadStatus::Successful, $lead->status);
        $this->assertCount(1, $lead->products);
        $this->assertEquals(2, $lead->products[0]->quantity);
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

    public function test_editing_an_already_successful_lead_replaces_its_product_line_items(): void
    {
        $lead = $this->makeLead(WebsiteLeadStatus::Successful->value);
        $oldProduct = $this->makeProduct('Old Product', 'OLD-1');
        $lead->products()->create([
            'product_id' => $oldProduct->id, 'product_name' => 'Old Product', 'sku' => 'OLD-1',
            'price' => 100, 'quantity' => 1,
        ]);

        $newProduct = $this->makeProduct('New Product', 'NEW-1', 500);

        $this->actingAs($this->user)->put(route('crm.website.update', $lead), [
            'client_name' => 'Test Client',
            'source'      => InquirySource::Website->value,
            'status'      => WebsiteLeadStatus::Successful->value,
            'products'    => [
                ['product_id' => $newProduct->id, 'price' => 500, 'quantity' => 1],
            ],
        ]);

        $lead->refresh();
        $this->assertCount(1, $lead->products);
        $this->assertEquals('New Product', $lead->products[0]->product_name);
    }

    public function test_edit_and_show_pages_render_with_products_present(): void
    {
        $lead = $this->makeLead(WebsiteLeadStatus::Successful->value);
        $product = $this->makeProduct('Excavator X1', 'SKU-X1', 15000);
        $lead->products()->create([
            'product_id' => $product->id, 'product_name' => 'Excavator X1', 'sku' => 'SKU-X1',
            'price' => 14500, 'quantity' => 1,
        ]);

        $this->actingAs($this->user)->get(route('crm.website.edit', $lead))
            ->assertOk()->assertSee('Excavator X1');

        $this->actingAs($this->user)->get(route('crm.website.show', $lead))
            ->assertOk()->assertSee('Products Sold');
    }
}
