<?php

namespace Tests\Feature;

use App\Models\SocialMediaAnalytic;
use App\Models\SocialMediaClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialMediaAnalyticsMultiClassTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_pdf_can_be_assigned_to_multiple_classes(): void
    {
        Storage::fake('local');
        config(['app.url' => 'http://localhost']);

        $user = User::factory()->create(['is_active' => true]);
        Role::create(['name' => 'social_qc', 'guard_name' => 'web']);
        $user->assignRole('social_qc');

        $classes = collect(['MachineryAsia', 'MachineryOrg', 'MiniExcavator'])
            ->map(fn ($name) => SocialMediaClass::create([
                'name' => $name,
                'created_by' => $user->id,
            ]));

        $this->actingAs($user)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post('http://localhost' . route('social-media.analytics.store', [], false), [
                'class_ids' => $classes->pluck('id')->all(),
                'date_from' => '2026-06-22',
                'date_to' => '2026-06-28',
                'file' => UploadedFile::fake()->create('weekly-analytics.pdf', 100, 'application/pdf'),
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $analytic = SocialMediaAnalytic::with('classes')->sole();
        $this->assertEqualsCanonicalizing($classes->pluck('id')->all(), $analytic->classes->pluck('id')->all());
        $this->assertSame('weekly-analytics.pdf', $analytic->original_name);
        Storage::disk('local')->assertExists($analytic->file_path);
    }

    public function test_reports_index_detects_overlapping_analytics(): void
    {
        Storage::fake('local');
        config(['app.url' => 'http://localhost']);

        $user = User::factory()->create(['is_active' => true]);
        Role::firstOrCreate(['name' => 'social_qc', 'guard_name' => 'web']);
        $user->assignRole('social_qc');

        $class = SocialMediaClass::create([
            'name' => 'TestClass',
            'created_by' => $user->id,
        ]);

        // Upload an analytic file that overlaps on the very first day (2026-06-22) of its range
        // when report dates are 2026-06-01 to 2026-06-22
        $file = UploadedFile::fake()->create('weekly-analytics.pdf', 100, 'application/pdf');
        $path = $file->storeAs('social-analytics/shared', 'test-analytic.pdf');

        $analytic = SocialMediaAnalytic::create([
            'date_from' => '2026-06-22',
            'date_to' => '2026-06-28',
            'file_path' => $path,
            'original_name' => 'weekly-analytics.pdf',
            'uploaded_by' => $user->id,
        ]);
        $analytic->classes()->attach($class->id);

        // Fetch report index with date_from=2026-06-01 and date_to=2026-06-22
        $response = $this->actingAs($user)
            ->get('http://localhost' . route('social-media.reports.index', [
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-22',
                'class_id' => $class->id,
            ], false));

        $response->assertOk();
        $response->assertViewHas('hasAnalytics', true);
        
        $available = $response->viewData('availableAnalytics');
        $this->assertCount(1, $available);
        $this->assertEquals($analytic->id, $available->first()->id);
    }

    public function test_reports_export_single_analytic_shortcut(): void
    {
        Storage::fake('local');
        config(['app.url' => 'http://localhost']);

        $user = User::factory()->create(['is_active' => true]);
        Role::firstOrCreate(['name' => 'social_qc', 'guard_name' => 'web']);
        $user->assignRole('social_qc');

        $class = SocialMediaClass::create([
            'name' => 'TestClass',
            'created_by' => $user->id,
        ]);

        $file = UploadedFile::fake()->create('weekly-analytics.pdf', 100, 'application/pdf');
        $path = $file->storeAs('social-analytics/shared', 'test-analytic.pdf');

        $analytic = SocialMediaAnalytic::create([
            'date_from' => '2026-06-22',
            'date_to' => '2026-06-28',
            'file_path' => $path,
            'original_name' => 'weekly-analytics.pdf',
            'uploaded_by' => $user->id,
        ]);
        $analytic->classes()->attach($class->id);

        $response = $this->actingAs($user)
            ->post('http://localhost' . route('social-media.reports.export.zip', [
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-22',
                'class_id' => $class->id,
                'include_analytics' => '1',
            ], false));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_reports_export_multiple_files_zip(): void
    {
        Storage::fake('local');
        config(['app.url' => 'http://localhost']);

        $user = User::factory()->create(['is_active' => true]);
        Role::firstOrCreate(['name' => 'social_qc', 'guard_name' => 'web']);
        $user->assignRole('social_qc');

        $class = SocialMediaClass::create([
            'name' => 'TestClass',
            'created_by' => $user->id,
        ]);

        $file = UploadedFile::fake()->create('weekly-analytics.pdf', 100, 'application/pdf');
        $path = $file->storeAs('social-analytics/shared', 'test-analytic.pdf');

        $analytic = SocialMediaAnalytic::create([
            'date_from' => '2026-06-22',
            'date_to' => '2026-06-28',
            'file_path' => $path,
            'original_name' => 'weekly-analytics.pdf',
            'uploaded_by' => $user->id,
        ]);
        $analytic->classes()->attach($class->id);

        $response = $this->actingAs($user)
            ->post('http://localhost' . route('social-media.reports.export.zip', [
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-22',
                'class_id' => $class->id,
                'include_csv' => '1',
                'include_analytics' => '1',
            ], false));

        $response->assertOk();
        $this->assertSame('application/zip', $response->headers->get('Content-Type'));
    }
}
