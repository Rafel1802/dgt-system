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
                'canva_link' => 'https://canva.com/design/test',
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

    public function test_qc_admin_digital_and_super_admin_can_update_analytics_date_and_classes(): void
    {
        Storage::fake('local');
        config(['app.url' => 'http://localhost']);

        $qcUser = User::factory()->create(['is_active' => true]);
        $digitalAdminUser = User::factory()->create(['is_active' => true]);
        $superAdminUser = User::factory()->create(['is_active' => true]);
        $targetUser = User::factory()->create(['is_active' => true, 'name' => 'Target User']);

        Role::firstOrCreate(['name' => 'social_qc', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin-digital', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $qcUser->assignRole('social_qc');
        $digitalAdminUser->assignRole('admin-digital');
        $superAdminUser->assignRole('super-admin');

        $classA = SocialMediaClass::create(['name' => 'ClassA', 'created_by' => $qcUser->id]);
        $classB = SocialMediaClass::create(['name' => 'ClassB', 'created_by' => $qcUser->id]);
        $classC = SocialMediaClass::create(['name' => 'ClassC', 'created_by' => $qcUser->id]);

        $file = UploadedFile::fake()->create('initial-analytics.pdf', 100, 'application/pdf');
        $path = $file->storeAs('social-analytics/shared', 'initial-analytic.pdf');

        $analytic = SocialMediaAnalytic::create([
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-07',
            'file_path' => $path,
            'original_name' => 'initial-analytics.pdf',
            'uploaded_by' => $qcUser->id,
            'canva_link' => 'https://canva.com/design/init',
        ]);
        $analytic->classes()->attach([$classA->id]);

        // 1. QC updates dates, classes, and uploader
        $response = $this->actingAs($qcUser)
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->put('http://localhost' . route('social-media.analytics.update', $analytic, false), [
                'class_ids' => [$classA->id, $classB->id],
                'date_from' => '2026-06-08',
                'date_to' => '2026-06-14',
                'uploaded_by' => $targetUser->id,
                'canva_link' => 'https://canva.com/design/updated-qc',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $analytic->refresh();
        $this->assertSame('2026-06-08', $analytic->date_from->format('Y-m-d'));
        $this->assertSame('2026-06-14', $analytic->date_to->format('Y-m-d'));
        $this->assertSame($targetUser->id, $analytic->uploaded_by);
        $this->assertSame('https://canva.com/design/updated-qc', $analytic->canva_link);
        $this->assertEqualsCanonicalizing([$classA->id, $classB->id], $analytic->classes->pluck('id')->all());

        // 2. Admin Digital updates classes and date range
        $response = $this->actingAs($digitalAdminUser)
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->put('http://localhost' . route('social-media.analytics.update', $analytic, false), [
                'class_ids' => [$classC->id],
                'date_from' => '2026-06-15',
                'date_to' => '2026-06-21',
                'uploaded_by' => $digitalAdminUser->id,
            ]);

        $response->assertOk()->assertJsonPath('success', true);
        $analytic->refresh();
        $this->assertSame('2026-06-15', $analytic->date_from->format('Y-m-d'));
        $this->assertSame('2026-06-21', $analytic->date_to->format('Y-m-d'));
        $this->assertEqualsCanonicalizing([$classC->id], $analytic->classes->pluck('id')->all());

        // 3. Super Admin updates and optionally replaces PDF file
        $newPdf = UploadedFile::fake()->create('replacement.pdf', 120, 'application/pdf');
        $response = $this->actingAs($superAdminUser)
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->put('http://localhost' . route('social-media.analytics.update', $analytic, false), [
                'class_ids' => [$classA->id, $classC->id],
                'date_from' => '2026-06-22',
                'date_to' => '2026-06-28',
                'file' => $newPdf,
            ]);

        $response->assertOk()->assertJsonPath('success', true);
        $analytic->refresh();
        $this->assertSame('replacement.pdf', $analytic->original_name);
        Storage::disk('local')->assertExists($analytic->file_path);
        $this->assertEqualsCanonicalizing([$classA->id, $classC->id], $analytic->classes->pluck('id')->all());
    }

    public function test_digital_team_user_can_also_update_analytics(): void
    {
        Storage::fake('local');
        config(['app.url' => 'http://localhost']);

        $user = User::factory()->create(['is_active' => true]);
        Role::firstOrCreate(['name' => 'digital-team', 'guard_name' => 'web']);
        $user->assignRole('digital-team');

        $class = SocialMediaClass::create(['name' => 'ClassA', 'created_by' => $user->id]);

        $analytic = SocialMediaAnalytic::create([
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-07',
            'file_path' => 'social-analytics/shared/sample.pdf',
            'original_name' => 'sample.pdf',
            'uploaded_by' => $user->id,
        ]);
        $analytic->classes()->attach([$class->id]);

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->put('http://localhost' . route('social-media.analytics.update', $analytic, false), [
                'class_ids' => [$class->id],
                'date_from' => '2026-06-08',
                'date_to' => '2026-06-14',
            ]);

        $response->assertOk()->assertJsonPath('success', true);
        $analytic->refresh();
        $this->assertSame('2026-06-08', $analytic->date_from->format('Y-m-d'));
        $this->assertSame('2026-06-14', $analytic->date_to->format('Y-m-d'));
    }

    public function test_analytics_update_validates_date_and_classes(): void
    {
        Storage::fake('local');
        config(['app.url' => 'http://localhost']);

        $user = User::factory()->create(['is_active' => true]);
        Role::firstOrCreate(['name' => 'social_qc', 'guard_name' => 'web']);
        $user->assignRole('social_qc');

        $class = SocialMediaClass::create(['name' => 'ClassA', 'created_by' => $user->id]);

        $analytic = SocialMediaAnalytic::create([
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-07',
            'file_path' => 'social-analytics/shared/sample.pdf',
            'original_name' => 'sample.pdf',
            'uploaded_by' => $user->id,
        ]);
        $analytic->classes()->attach([$class->id]);

        // Date to before date from
        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->put('http://localhost' . route('social-media.analytics.update', $analytic, false), [
                'class_ids' => [$class->id],
                'date_from' => '2026-06-14',
                'date_to' => '2026-06-07',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date_to']);

        // Empty classes
        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->put('http://localhost' . route('social-media.analytics.update', $analytic, false), [
                'class_ids' => [],
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-07',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['class_ids']);
    }
}
