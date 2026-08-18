<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProductImportTest extends TestCase
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

    public function test_can_import_products_from_csv_with_new_column_structure(): void
    {
        // 1. Ensure categories table starts empty
        ProductCategory::truncate();
        Product::truncate();

        // 2. Mock a CSV file with "Image,Product Name,SKU,Price" columns
        $csvContent = "Image,Product Name,SKU,Price\n"
            . "https://example.com/forklift.png,TYPHON VIGOR 3.0 Blue Electric Forklift 3 Ton,SKU-FORK-3T,13648.95\n"
            . "https://example.com/excavator.png,TYPHON TERROR 1.0 Mini Excavator,SKU-EXCA-1T,12598.95\n";

        $file = UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        // 3. Request preview first to trigger normalization and session saving
        $response = $this->actingAs($this->user)->post(route('crm.products.import'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('crm.products.index'));
        $response->assertSessionHas('product_import_rows');

        // Verify categories were auto-created during normalization
        $this->assertDatabaseHas('product_categories', ['name' => 'Forklift']);
        $this->assertDatabaseHas('product_categories', ['name' => 'Mni Excavator']);

        // Verify session data has mapped values
        $rows = session('product_import_rows');
        $this->assertCount(2, $rows);
        $this->assertEquals('TYPHON VIGOR 3.0 Blue Electric Forklift 3 Ton', $rows[0]['name']);
        $this->assertEquals('SKU-FORK-3T', $rows[0]['sku']);
        $this->assertEquals('Forklift', $rows[0]['category_name']);
        $this->assertEquals('TYPHON', $rows[0]['brand']);
        $this->assertEquals(13648.95, $rows[0]['price']);
        $this->assertEquals('https://example.com/forklift.png', $rows[0]['product_image']);

        // 4. Confirm the import
        $confirmResponse = $this->actingAs($this->user)->post(route('crm.products.import'), [
            'confirm_import' => '1',
        ]);

        $confirmResponse->assertRedirect(route('crm.products.index'));

        // 5. Verify database records
        $this->assertEquals(2, Product::count());

        $forklift = Product::where('sku', 'SKU-FORK-3T')->first();
        $this->assertNotNull($forklift);
        $this->assertEquals('TYPHON VIGOR 3.0 Blue Electric Forklift 3 Ton', $forklift->name);
        $this->assertEquals('TYPHON', $forklift->brand);
        $this->assertEquals(13648.95, $forklift->price);
        $this->assertEquals('https://example.com/forklift.png', $forklift->image);
        $this->assertEquals('Forklift', $forklift->categoryName);
    }
}
