<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BlogReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure permission exists
        Permission::firstOrCreate(['name' => 'view-blog-reports', 'guard_name' => 'web']);

        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->givePermissionTo('view-blog-reports');
    }

    public function test_csv_download_with_records_json_streams_csv(): void
    {
        $mockRecords = [
            [
                'class' => '1',
                'doc_link' => 'http://example.com/doc1',
                'public_link' => 'http://example.com/pub1',
                'dated' => '08/12',
                'website_link' => 'http://newdiggerforsale.com',
            ],
            [
                'class' => '2',
                'doc_link' => 'http://example.com/doc2',
                'public_link' => 'http://example.com/pub2',
                'dated' => '08/13',
                'website_link' => 'http://machinery.org',
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('blog-reports.csv'), [
            'month_label' => 'Blog Reports - September',
            'records_json' => json_encode($mockRecords),
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        
        $content = $response->streamedContent();
        
        // Assert UTF-8 BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        
        // Assert CSV Headers
        $this->assertStringContainsString('No.,Class,Dated,"Website Link","Google Doc Link","Public Link"', $content);
        
        // Assert Rows
        $this->assertStringContainsString('1,1,08/12,http://newdiggerforsale.com,http://example.com/doc1,http://example.com/pub1', $content);
        $this->assertStringContainsString('2,2,08/13,http://machinery.org,http://example.com/doc2,http://example.com/pub2', $content);
    }

    public function test_csv_download_with_sheet_url_and_fallback(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://docs.google.com/spreadsheets/d/test-sheet-id/*' => \Illuminate\Support\Facades\Http::response(
                "Class,Doc Link,Public Link,Dated,Website link\n1,http://doc.test,http://pub.test,08/12,http://site.test\n",
                200
            ),
        ]);

        $response = $this->actingAs($this->user)->post(route('blog-reports.csv'), [
            'sheet_url' => 'https://docs.google.com/spreadsheets/d/test-sheet-id/edit#gid=0',
            'month_label' => 'Blog Reports - September',
        ]);

        if ($response->isRedirect()) {
            $this->fail('Redirected with error: ' . session('error'));
        }
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('http://site.test', $content);
    }

    public function test_preview_renders_hidden_csv_form_inputs(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://docs.google.com/spreadsheets/d/test-sheet-id/*' => \Illuminate\Support\Facades\Http::response(
                "Class,Doc Link,Public Link,Dated,Website link\n1,http://doc.test,http://pub.test,08/12,http://site.test\n",
                200
            ),
        ]);

        $response = $this->actingAs($this->user)->post(route('blog-reports.preview'), [
            'sheet_url' => 'https://docs.google.com/spreadsheets/d/test-sheet-id/edit#gid=0',
            'month_label' => 'Blog Reports - September',
            'class_filter' => '1',
        ]);

        $response->assertOk();
        $response->assertSee('name="sheet_url"', false);
        $response->assertSee('name="records_json"', false);
        $response->assertSee('name="class_filter"', false);
        $response->assertSee('Download CSV', false);
    }

    public function test_csv_validation_fails_without_records_or_sheet_url(): void
    {
        $response = $this->actingAs($this->user)->post(route('blog-reports.csv'), [
            'month_label' => 'Blog Reports - September',
        ]);

        $response->assertSessionHasErrors(['sheet_url']);
    }
}
