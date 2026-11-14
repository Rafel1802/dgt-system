<?php

namespace Tests\Feature;

use App\Models\ProductionReport\ProductionReport;
use App\Models\ProductionReport\ProductionReportMedia;
use App\Models\ProductionReport\ProductionReportTeamMapping;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ProductionReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $somalika;
    protected User $dara;
    protected User $kim;
    protected User $memberA1;
    protected User $memberA2;
    protected User $memberB1;
    protected User $unmappedUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->somalika = User::factory()->create([
            'name' => 'Ms. Somalika',
            'username' => 'somalika',
            'email' => 'somalika@kiuq.local',
            'team_role' => 'Production Supervisor',
            'is_active' => true,
        ]);

        $this->dara = User::factory()->create([
            'name' => 'Mr. Dara',
            'username' => 'dara',
            'email' => 'dara@kiuq.local',
            'team' => 'A',
            'team_role' => 'Head of Production Team A',
            'is_active' => true,
        ]);

        $this->kim = User::factory()->create([
            'name' => 'Mr. Kim',
            'username' => 'kim',
            'email' => 'kim@kiuq.local',
            'team' => 'B',
            'team_role' => 'Head of Production Team B',
            'is_active' => true,
        ]);

        $adminDigitalRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin-digital', 'guard_name' => 'web']);
        $digitalTeamRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'digital-team', 'guard_name' => 'web']);
        $this->somalika->assignRole($adminDigitalRole);
        $this->dara->assignRole([$adminDigitalRole, $digitalTeamRole]);
        $this->kim->assignRole([$adminDigitalRole, $digitalTeamRole]);

        $this->memberA1 = User::factory()->create([
            'name' => 'Samnang (Team A)',
            'username' => 'nang',
            'email' => 'nang@kiuq.local',
            'team' => 'A',
            'team_role' => 'Video & Web Specialist',
            'is_active' => true,
        ]);

        $this->memberA2 = User::factory()->create([
            'name' => 'Vouchky (Team A)',
            'username' => 'vouchky',
            'email' => 'vouchky@kiuq.local',
            'team' => 'A',
            'team_role' => 'Video Content Specialist',
            'is_active' => true,
        ]);

        $this->memberB1 = User::factory()->create([
            'name' => 'Pich (Team B)',
            'username' => 'pich',
            'email' => 'pich@kiuq.local',
            'team' => 'B',
            'team_role' => 'Senior Graphic Designer',
            'is_active' => true,
        ]);

        $this->unmappedUser = User::factory()->create([
            'name' => 'External CRM Staff',
            'username' => 'crmstaff',
            'email' => 'crm@kiuq.local',
            'team' => null,
            'is_active' => true,
        ]);
    }

    public function test_member_submits_daily_report_on_time_and_late(): void
    {
        // 1. On-time submission at 3:00 PM Cambodia time (before 5:00 PM deadline)
        Carbon::setTestNow(Carbon::parse('2026-11-10 15:00:00', 'Asia/Phnom_Penh'));

        $poster = UploadedFile::fake()->image('promo-poster.png', 800, 600);

        $response = $this->actingAs($this->memberA1)->post(route('production-reports.daily.store'), [
            'reporting_date' => '2026-11-10',
            'project_category' => 'Video Production',
            'tasks_completed' => "- Edited 2 product promo videos\n- Designed 1 thumbnail poster",
            'work_in_progress' => 'Rendering 4K master reel',
            'pending_tasks' => 'Voiceover sync',
            'blockers' => 'None',
            'achievements' => 'Fast turnaround on campaign video',
            'deliverable_links' => "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
            'items' => [
                [
                    'task_title' => 'Product Promo Video',
                    'task_category' => 'Video',
                    'quantity' => 2,
                    'unit' => 'videos',
                    'status' => 'completed',
                    'completion_date' => '2026-11-10',
                ],
            ],
            'attachments' => [$poster],
            'action' => 'submit',
        ]);

        $report = ProductionReport::where('user_id', $this->memberA1->id)
            ->whereDate('reporting_date', '2026-11-10')
            ->firstOrFail();

        $response->assertRedirect(route('production-reports.show', $report->id));
        $this->assertSame(ProductionReport::STATUS_SUBMITTED, $report->status);
        $this->assertFalse($report->is_late);
        $this->assertSame('A', $report->team_code);
        $this->assertCount(1, $report->items);
        $this->assertCount(1, $report->media);
        $this->assertCount(1, $report->revisions);

        // 2. Late submission at 6:15 PM Cambodia time (after 5:00 PM deadline)
        Carbon::setTestNow(Carbon::parse('2026-11-11 18:15:00', 'Asia/Phnom_Penh'));

        $this->actingAs($this->memberA1)->post(route('production-reports.daily.store'), [
            'reporting_date' => '2026-11-11',
            'project_category' => 'Video Production',
            'tasks_completed' => 'Completed late evening edit',
            'action' => 'submit',
        ]);

        $lateReport = ProductionReport::where('user_id', $this->memberA1->id)
            ->whereDate('reporting_date', '2026-11-11')
            ->firstOrFail();

        $this->assertSame(ProductionReport::STATUS_SUBMITTED_LATE, $lateReport->status);
        $this->assertTrue($lateReport->is_late);

        Carbon::setTestNow();
    }

    public function test_member_cannot_view_another_members_daily_report_or_media(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 14:00:00', 'Asia/Phnom_Penh'));

        $poster = UploadedFile::fake()->image('private-design.png', 400, 400);

        $this->actingAs($this->memberA1)->post(route('production-reports.daily.store'), [
            'reporting_date' => '2026-11-10',
            'tasks_completed' => 'Member A1 private work',
            'attachments' => [$poster],
            'action' => 'submit',
        ]);

        $reportA1 = ProductionReport::where('user_id', $this->memberA1->id)->firstOrFail();
        $mediaA1 = $reportA1->media()->firstOrFail();

        // Another member in the same team (memberA2) or other team (memberB1) cannot view reportA1 or its media
        $this->actingAs($this->memberA2)
            ->get(route('production-reports.show', $reportA1->id))
            ->assertStatus(403);

        $this->actingAs($this->memberB1)
            ->get(route('production-reports.show', $reportA1->id))
            ->assertStatus(403);

        $this->actingAs($this->memberA2)
            ->get(route('production-reports.media.view', $mediaA1->id))
            ->assertStatus(403);

        $this->actingAs($this->memberA2)
            ->get(route('production-reports.export', [$reportA1->id, 'pdf']))
            ->assertStatus(403);

        // Unmapped user cannot access module at all
        $this->actingAs($this->unmappedUser)
            ->get(route('production-reports.index'))
            ->assertStatus(403);

        Carbon::setTestNow();
    }

    public function test_dara_and_kim_strict_team_isolation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 14:00:00', 'Asia/Phnom_Penh'));

        // Member A1 (Dara's Team A) submits daily report
        $this->actingAs($this->memberA1)->post(route('production-reports.daily.store'), [
            'reporting_date' => '2026-11-10',
            'tasks_completed' => 'Team A video task',
            'attachments' => [UploadedFile::fake()->image('team-a-poster.png')],
            'action' => 'submit',
        ]);
        $reportTeamA = ProductionReport::where('user_id', $this->memberA1->id)->firstOrFail();
        $mediaTeamA = $reportTeamA->media()->firstOrFail();

        // Member B1 (Kim's Team B) submits daily report
        $this->actingAs($this->memberB1)->post(route('production-reports.daily.store'), [
            'reporting_date' => '2026-11-10',
            'tasks_completed' => 'Team B graphic task',
            'attachments' => [UploadedFile::fake()->image('team-b-poster.png')],
            'action' => 'submit',
        ]);
        $reportTeamB = ProductionReport::where('user_id', $this->memberB1->id)->firstOrFail();
        $mediaTeamB = $reportTeamB->media()->firstOrFail();

        // 1. Dara CAN view Team A report & media
        $this->actingAs($this->dara)
            ->get(route('production-reports.show', $reportTeamA->id))
            ->assertStatus(200);
        $this->actingAs($this->dara)
            ->get(route('production-reports.media.view', $mediaTeamA->id))
            ->assertStatus(200);

        // 2. Dara CANNOT view Kim's Team B report, media, weekly compiler, or export
        $this->actingAs($this->dara)
            ->get(route('production-reports.show', $reportTeamB->id))
            ->assertStatus(403);
        $this->actingAs($this->dara)
            ->get(route('production-reports.media.view', $mediaTeamB->id))
            ->assertStatus(403);
        $this->actingAs($this->dara)
            ->get(route('production-reports.export', [$reportTeamB->id, 'pdf']))
            ->assertStatus(403);
        $this->actingAs($this->dara)
            ->get(route('production-reports.weekly.create', ['team' => 'B']))
            ->assertStatus(403);
        $this->actingAs($this->dara)
            ->post(route('production-reports.weekly.store'), [
                'team_code' => 'B',
                'reporting_start_date' => '2026-11-09',
                'reporting_end_date' => '2026-11-13',
                'performance_rating' => 5,
                'action' => 'submit',
            ])
            ->assertStatus(403);

        // 3. Kim CAN view Team B report & media
        $this->actingAs($this->kim)
            ->get(route('production-reports.show', $reportTeamB->id))
            ->assertStatus(200);
        $this->actingAs($this->kim)
            ->get(route('production-reports.media.view', $mediaTeamB->id))
            ->assertStatus(200);

        // 4. Kim CANNOT view Dara's Team A report, media, weekly compiler, or export
        $this->actingAs($this->kim)
            ->get(route('production-reports.show', $reportTeamA->id))
            ->assertStatus(403);
        $this->actingAs($this->kim)
            ->get(route('production-reports.media.view', $mediaTeamA->id))
            ->assertStatus(403);
        $this->actingAs($this->kim)
            ->get(route('production-reports.export', [$reportTeamA->id, 'pptx']))
            ->assertStatus(403);
        $this->actingAs($this->kim)
            ->get(route('production-reports.weekly.create', ['team' => 'A']))
            ->assertStatus(403);

        Carbon::setTestNow();
    }

    public function test_weekly_report_auto_compiles_team_daily_reports_and_requires_performance_rating(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-13 15:00:00', 'Asia/Phnom_Penh')); // Friday before Saturday 8 AM deadline

        // Member A1 submits daily report with measurable items and poster
        $this->actingAs($this->memberA1)->post(route('production-reports.daily.store'), [
            'reporting_date' => '2026-11-10',
            'tasks_completed' => 'Created 3 promotional posters',
            'achievements' => 'High engagement visual campaign',
            'items' => [
                ['task_title' => 'Promo Posters', 'task_category' => 'Poster', 'quantity' => 3, 'unit' => 'posters', 'status' => 'completed'],
            ],
            'attachments' => [UploadedFile::fake()->image('best-poster.png')],
            'action' => 'submit',
        ]);

        $dailyA1 = ProductionReport::where('user_id', $this->memberA1->id)->firstOrFail();
        $posterId = $dailyA1->media()->firstOrFail()->id;

        // Submitting weekly report WITHOUT rating (1..5) must fail validation
        $this->actingAs($this->dara)->post(route('production-reports.weekly.store'), [
            'team_code' => 'A',
            'reporting_start_date' => '2026-11-09',
            'reporting_end_date' => '2026-11-13',
            'action' => 'submit',
        ])->assertSessionHasErrors('performance_rating');

        // Submitting weekly report WITH rating = 5 succeeds and compiles member summary table & highlights
        $response = $this->actingAs($this->dara)->post(route('production-reports.weekly.store'), [
            'team_code' => 'A',
            'reporting_start_date' => '2026-11-09',
            'reporting_end_date' => '2026-11-13',
            'performance_rating' => 5,
            'head_comments' => 'Outstanding team collaboration and output quality.',
            'next_priorities' => 'Launch Q4 holiday video series.',
            'selected_poster_ids' => [$posterId],
            'action' => 'submit',
        ]);

        $weeklyReport = ProductionReport::where('report_type', ProductionReport::TYPE_WEEKLY)
            ->where('team_code', 'A')
            ->firstOrFail();

        $response->assertRedirect(route('production-reports.show', $weeklyReport->id));
        $this->assertSame(5, $weeklyReport->performance_rating);
        $this->assertSame('Excellent', $weeklyReport->performance_rating_label);
        $this->assertSame(ProductionReport::STATUS_SUBMITTED, $weeklyReport->status);
        $this->assertFalse($weeklyReport->is_late);

        $compiledSummary = $weeklyReport->compiled_summary_json;
        $this->assertNotEmpty($compiledSummary['member_summary_table']);
        $this->assertSame(3, $compiledSummary['total_completed_tasks'] > 0 ? (int) $compiledSummary['total_measurable_quantity'] : 0);
        // Member A2 did not submit any daily reports in the period, so missing reports are recorded accurately
        $this->assertNotEmpty($compiledSummary['missing_reports']);
        $this->assertCount(1, $weeklyReport->media);

        Carbon::setTestNow();
    }

    public function test_monthly_report_auto_compiles_member_daily_reports(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-28 14:00:00', 'Asia/Phnom_Penh'));

        $this->actingAs($this->memberA1)->post(route('production-reports.daily.store'), [
            'reporting_date' => '2026-11-05',
            'tasks_completed' => 'Edited Nov 5 video package',
            'achievements' => 'Zero QC revisions needed',
            'action' => 'submit',
        ]);

        $this->actingAs($this->memberA1)->post(route('production-reports.daily.store'), [
            'reporting_date' => '2026-11-12',
            'tasks_completed' => 'Designed Nov 12 banner set',
            'action' => 'submit',
        ]);

        // 1. Submitting without a mandatory presentation document (PDF, PPT, DOCX) must fail validation
        $failResponse = $this->actingAs($this->memberA1)->post(route('production-reports.monthly.store'), [
            'reporting_year' => 2026,
            'reporting_month' => 11,
            'next_priorities' => 'Increase motion graphics output in December',
            'action' => 'submit',
        ]);
        $failResponse->assertSessionHasErrors(['attachments']);

        // 2. Submitting with attached presentation document (e.g. .pptx) succeeds
        $doc = UploadedFile::fake()->create('monthly-performance-nov2026.pptx', 120, 'application/vnd.openxmlformats-officedocument.presentationml.presentation');
        $response = $this->actingAs($this->memberA1)->post(route('production-reports.monthly.store'), [
            'reporting_year' => 2026,
            'reporting_month' => 11,
            'next_priorities' => 'Increase motion graphics output in December',
            'attachments' => [$doc],
            'action' => 'submit',
        ]);

        $monthly = ProductionReport::where('user_id', $this->memberA1->id)
            ->where('report_type', ProductionReport::TYPE_MONTHLY)
            ->where('reporting_year', 2026)
            ->where('reporting_month', 11)
            ->firstOrFail();

        $response->assertRedirect(route('production-reports.show', $monthly->id));
        $this->assertSame(ProductionReport::STATUS_SUBMITTED, $monthly->status);
        $this->assertStringContainsString('Edited Nov 5 video package', (string) $monthly->tasks_completed);
        $this->assertStringContainsString('Designed Nov 12 banner set', (string) $monthly->tasks_completed);
        $this->assertSame(2, $monthly->compiled_summary_json['submitted_days_count']);
        $this->assertGreaterThan(0, $monthly->compiled_summary_json['missing_days_count']);

        Carbon::setTestNow();
    }

    public function test_somalika_supervisor_can_view_all_reports_and_never_submit_any_report(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 14:00:00', 'Asia/Phnom_Penh'));

        // Team A and Team B members submit daily reports
        $this->actingAs($this->memberA1)->post(route('production-reports.daily.store'), [
            'reporting_date' => '2026-11-10',
            'tasks_completed' => 'Team A daily work',
            'action' => 'submit',
        ]);
        $repA = ProductionReport::where('user_id', $this->memberA1->id)->firstOrFail();

        $this->actingAs($this->memberB1)->post(route('production-reports.daily.store'), [
            'reporting_date' => '2026-11-10',
            'tasks_completed' => 'Team B daily work',
            'action' => 'submit',
        ]);
        $repB = ProductionReport::where('user_id', $this->memberB1->id)->firstOrFail();

        // Somalika can view Dashboard (without Submit buttons), Team A report, and Team B report
        $dashResponse = $this->actingAs($this->somalika)->get(route('production-reports.index'));
        $dashResponse->assertStatus(200);
        $dashResponse->assertSee('Supervisor View-Only');
        $dashResponse->assertDontSee('+ Submit Daily Report');

        $this->actingAs($this->somalika)->get(route('production-reports.show', $repA->id))->assertStatus(200);
        $this->actingAs($this->somalika)->get(route('production-reports.show', $repB->id))->assertStatus(200);

        // Somalika CANNOT access daily/weekly/monthly creation forms or submit POST endpoints
        $this->actingAs($this->somalika)->get(route('production-reports.daily.create'))->assertStatus(403);
        $this->actingAs($this->somalika)->post(route('production-reports.daily.store'), [
            'reporting_date' => '2026-11-10',
            'tasks_completed' => 'Attempted supervisor submission',
            'action' => 'submit',
        ])->assertStatus(403);

        $this->actingAs($this->somalika)->get(route('production-reports.weekly.create'))->assertStatus(403);
        $this->actingAs($this->somalika)->post(route('production-reports.weekly.store'), [
            'team_code' => 'A',
            'reporting_start_date' => '2026-11-09',
            'reporting_end_date' => '2026-11-13',
            'performance_rating' => 5,
            'action' => 'submit',
        ])->assertStatus(403);

        $this->actingAs($this->somalika)->get(route('production-reports.monthly.create'))->assertStatus(403);
        $this->actingAs($this->somalika)->post(route('production-reports.monthly.store'), [
            'reporting_year' => 2026,
            'reporting_month' => 11,
            'action' => 'submit',
        ])->assertStatus(403);

        Carbon::setTestNow();
    }

    public function test_pptx_pdf_and_csv_exports_generate_valid_files_in_private_storage(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-13 15:00:00', 'Asia/Phnom_Penh'));

        $this->actingAs($this->memberA1)->post(route('production-reports.daily.store'), [
            'reporting_date' => '2026-11-10',
            'tasks_completed' => 'Designed campaign poster',
            'attachments' => [UploadedFile::fake()->image('campaign.png', 600, 400)],
            'action' => 'submit',
        ]);
        $daily = ProductionReport::where('user_id', $this->memberA1->id)->firstOrFail();
        $posterId = $daily->media()->firstOrFail()->id;

        $this->actingAs($this->dara)->post(route('production-reports.weekly.store'), [
            'team_code' => 'A',
            'reporting_start_date' => '2026-11-09',
            'reporting_end_date' => '2026-11-13',
            'performance_rating' => 4,
            'head_comments' => 'Strong weekly delivery',
            'selected_poster_ids' => [$posterId],
            'action' => 'submit',
        ]);

        $weekly = ProductionReport::where('report_type', ProductionReport::TYPE_WEEKLY)->firstOrFail();

        // 1. Export PPTX (12 slides + embedded image)
        $pptxRes = $this->actingAs($this->somalika)->get(route('production-reports.export', [$weekly->id, 'pptx']));
        $pptxRes->assertStatus(200);
        $weekly->refresh();
        $this->assertNotNull($weekly->pptx_path);
        $this->assertTrue(Storage::disk('local')->exists($weekly->pptx_path));

        $absPptx = Storage::disk('local')->path($weekly->pptx_path);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($absPptx) === true);
        // Verify all 12 slides exist inside the .pptx archive
        for ($s = 1; $s <= 12; $s++) {
            $this->assertNotFalse($zip->locateName("ppt/slides/slide{$s}.xml"), "Slide {$s} missing in PPTX");
        }
        $zip->close();

        // 2. Export PDF
        $pdfRes = $this->actingAs($this->somalika)->get(route('production-reports.export', [$weekly->id, 'pdf']));
        $pdfRes->assertStatus(200);
        $weekly->refresh();
        $this->assertNotNull($weekly->pdf_path);
        $this->assertTrue(Storage::disk('local')->exists($weekly->pdf_path));

        // 3. Export CSV
        $csvRes = $this->actingAs($this->somalika)->get(route('production-reports.export', [$weekly->id, 'csv']));
        $csvRes->assertStatus(200);
        $weekly->refresh();
        $this->assertNotNull($weekly->csv_path);
        $this->assertTrue(Storage::disk('local')->exists($weekly->csv_path));

        // 4. Scheduled command runs cleanly
        $exitCode = Artisan::call('production-reports:process-schedule', ['--task' => 'all']);
        $this->assertSame(0, $exitCode);

        Carbon::setTestNow();
    }

    public function test_submitted_report_is_locked_until_returned_for_correction_and_preserves_revision_history(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 14:00:00', 'Asia/Phnom_Penh'));

        $this->actingAs($this->memberA1)->post(route('production-reports.daily.store'), [
            'reporting_date' => '2026-11-10',
            'tasks_completed' => 'Initial submitted tasks',
            'action' => 'submit',
        ]);

        $report = ProductionReport::where('user_id', $this->memberA1->id)->firstOrFail();
        $this->assertSame(ProductionReport::STATUS_SUBMITTED, $report->status);

        // Attempting to overwrite an already submitted report directly fails validation
        $this->actingAs($this->memberA1)->post(route('production-reports.daily.store'), [
            'report_id' => $report->id,
            'reporting_date' => '2026-11-10',
            'tasks_completed' => 'Silent overwrite attempt',
            'action' => 'submit',
        ])->assertSessionHasErrors('reporting_date');

        // Head Dara returns the report for correction
        $this->actingAs($this->dara)->post(route('production-reports.return', $report->id), [
            'returned_reason' => 'Please attach the final poster file and clarify task quantities.',
        ])->assertRedirect(route('production-reports.show', $report->id));

        $report->refresh();
        $this->assertSame(ProductionReport::STATUS_RETURNED, $report->status);
        $this->assertTrue($report->isEditableByAuthor());

        // Now Member A1 can update and resubmit, creating revision #3
        $this->actingAs($this->memberA1)->post(route('production-reports.daily.store'), [
            'report_id' => $report->id,
            'reporting_date' => '2026-11-10',
            'tasks_completed' => 'Corrected tasks with full poster quantities',
            'action' => 'submit',
        ])->assertRedirect(route('production-reports.show', $report->id));

        $report->refresh();
        $this->assertSame(ProductionReport::STATUS_SUBMITTED, $report->status);
        $this->assertSame(3, $report->revisions()->count());

        // Supervisor Somalika approves the report
        $this->actingAs($this->somalika)->post(route('production-reports.approve', $report->id), [
            'comments' => 'Verified and approved.',
        ])->assertRedirect(route('production-reports.show', $report->id));

        $report->refresh();
        $this->assertSame(ProductionReport::STATUS_APPROVED, $report->status);
        $this->assertSame(4, $report->revisions()->count());

        Carbon::setTestNow();
    }

    public function test_calendar_media_archive_and_team_mapping_security(): void
    {
        $calRes = $this->actingAs($this->somalika)->get(route('production-reports.calendar', ['month' => 11, 'year' => 2026]));
        $calRes->assertStatus(200);
        $calRes->assertSee('Submission Calendar — November 2026');
        $calRes->assertSee('pr-cal-grid-header', false);
        $calRes->assertSee('Sun');
        $calRes->assertSee('Mon');
        $calRes->assertSee('Sat');
        $calRes->assertSee('pr-cal-day-cell', false);
        $calRes->assertSee('On-Time Compliance');
        $calRes->assertSee('Total Reports');

        $this->actingAs($this->somalika)->get(route('production-reports.media'))->assertStatus(200);
        $this->actingAs($this->somalika)->get(route('production-reports.archive'))->assertStatus(200);
        $this->actingAs($this->somalika)->get(route('production-reports.settings'))->assertStatus(200);

        // Dara cannot assign a user to Kim's Team B
        $this->actingAs($this->dara)->post(route('production-reports.settings.team-mapping'), [
            'user_id' => $this->memberA1->id,
            'team_code' => 'B',
            'role_in_reporting' => 'member',
        ])->assertStatus(403);

        // Member cannot access settings screen
        $this->actingAs($this->memberA1)->get(route('production-reports.settings'))->assertStatus(403);
    }

    public function test_members_only_see_their_own_menus_while_heads_and_supervisor_see_all(): void
    {
        // Members: dashboard + my reports + submit daily/monthly only
        $this->actingAs($this->memberA1)->get(route('production-reports.index'))->assertStatus(200)
            ->assertDontSee('@include', false)
            ->assertDontSee('@once', false)
            ->assertSee('.pr-hero {', false)
            ->assertSee('class="pr-root', false)
            ->assertSee('Submit Daily')
            ->assertSee('My Reports')
            ->assertDontSee('Weekly Team Reports')
            ->assertDontSee('Report Calendar')
            ->assertDontSee('Media &amp; Attachments', false)
            ->assertDontSee('Report Archive')
            ->assertDontSee('Team &amp; Deadlines', false);

        foreach (['weekly.index', 'calendar', 'media', 'archive', 'archive.export-csv'] as $routeName) {
            $this->actingAs($this->memberA1)->get(route('production-reports.' . $routeName))->assertStatus(403);
        }

        // Dara, Kim and Somalika see every menu
        foreach ([$this->dara, $this->kim, $this->somalika] as $leader) {
            $this->actingAs($leader)->get(route('production-reports.index'))->assertStatus(200)
                ->assertSee('Weekly Team Reports')
                ->assertSee('Report Calendar')
                ->assertSee('Report Archive');

            foreach (['weekly.index', 'calendar', 'media', 'archive'] as $routeName) {
                $this->actingAs($leader)->get(route('production-reports.' . $routeName))->assertStatus(200);
            }
        }
    }

    public function test_frozen_users_cannot_use_reports_and_are_hidden_from_team_lists(): void
    {
        $this->memberA2->update(['is_active' => false]);

        $perm = app(\App\Services\ProductionReport\ProductionReportPermissionService::class);
        $this->assertNull($perm->resolveRole($this->memberA2->fresh()));
        $this->assertFalse($perm->canSubmitDaily($this->memberA2->fresh()));
        $this->assertFalse($perm->getTeamMembers('A')->contains('id', $this->memberA2->id));

        // Their previously submitted reports are not shown to leadership
        $report = ProductionReport::create([
            'report_code' => 'PR-FROZEN-0001',
            'report_type' => ProductionReport::TYPE_DAILY,
            'user_id' => $this->memberA2->id,
            'team_code' => 'A',
            'reporting_date' => '2026-11-10',
            'reporting_start_date' => '2026-11-10',
            'reporting_end_date' => '2026-11-10',
            'reporting_month' => 11,
            'reporting_year' => 2026,
            'title' => 'Frozen member daily',
            'status' => ProductionReport::STATUS_SUBMITTED,
        ]);

        $visible = $perm->scopeReportsQuery(ProductionReport::query(), $this->somalika)->pluck('id');
        $this->assertFalse($visible->contains($report->id));
    }

    public function test_daily_reminder_banner_alerts_15_minutes_before_deadline(): void
    {
        // 4:40 PM Cambodia = 20 minutes before 5:00 PM -> not yet
        Carbon::setTestNow(Carbon::parse('2026-11-10 16:40:00', 'Asia/Phnom_Penh'));
        $this->actingAs($this->memberA1)->getJson(route('production-reports.reminder-status'))
            ->assertOk()->assertJson(['show' => false]);

        // 4:46 PM = 14 minutes left -> alert
        Carbon::setTestNow(Carbon::parse('2026-11-10 16:46:00', 'Asia/Phnom_Penh'));
        $this->actingAs($this->memberA1)->getJson(route('production-reports.reminder-status'))
            ->assertOk()->assertJson(['show' => true, 'overdue' => false, 'lead_minutes' => 15]);

        // Supervisor never gets submission reminders
        $this->actingAs($this->somalika)->getJson(route('production-reports.reminder-status'))
            ->assertOk()->assertJson(['show' => false]);

        // Sunday is a day off
        Carbon::setTestNow(Carbon::parse('2026-11-15 16:50:00', 'Asia/Phnom_Penh'));
        $this->actingAs($this->memberA1)->getJson(route('production-reports.reminder-status'))
            ->assertOk()->assertJson(['show' => false]);

        Carbon::setTestNow();
    }

    public function test_exact_production_rosters_team_a_six_members_team_b_four_members(): void
    {
        $perm = app(\App\Services\ProductionReport\ProductionReportPermissionService::class);

        // Team A: 6 members
        $teamAUsernames = \App\Services\ProductionReport\ProductionReportPermissionService::ROSTER['A'];
        $this->assertCount(6, $teamAUsernames);
        $this->assertSame(['nang', 'vouchky', 'chhay', 'heang', 'nalin', 'sreypich'], $teamAUsernames);

        // Team B: 4 members
        $teamBUsernames = \App\Services\ProductionReport\ProductionReportPermissionService::ROSTER['B'];
        $this->assertCount(4, $teamBUsernames);
        $this->assertSame(['pich', 'sor', 'lin', 'sarak'], $teamBUsernames);

        // Lyza deactivated mapping is recognized as excluded
        $lyza = User::factory()->create([
            'username' => 'lyza',
            'name' => 'Lyza',
            'team' => 'A',
            'is_active' => true,
        ]);
        ProductionReportTeamMapping::create([
            'user_id' => $lyza->id,
            'team_code' => 'A',
            'team_name' => 'Team A',
            'role_in_reporting' => ProductionReportTeamMapping::ROLE_MEMBER,
            'is_active' => false,
        ]);

        $this->assertTrue($perm->isExcludedFromRoster($lyza));
        $this->assertTrue($perm->isExcludedFromRoster('lyza'));
        $this->assertNull($perm->resolveRole($lyza));
    }

    public function test_member_date_range_export_and_copy_as_text(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-12 14:00:00', 'Asia/Phnom_Penh'));

        $this->actingAs($this->memberA1)->post(route('production-reports.daily.store'), [
            'reporting_date' => '2026-11-10',
            'tasks_completed' => 'Rendered product showcase video',
            'achievements' => 'Exported in full 4K',
            'action' => 'submit',
        ]);

        $this->actingAs($this->memberA1)->post(route('production-reports.daily.store'), [
            'reporting_date' => '2026-11-11',
            'tasks_completed' => 'Updated website product thumbnails',
            'achievements' => 'Optimized 25 image assets',
            'action' => 'submit',
        ]);

        // 1. Export Range as CSV
        $csvResponse = $this->actingAs($this->memberA1)->get(route('production-reports.export-range', [
            'format' => 'csv',
            'from' => '2026-11-01',
            'to' => '2026-11-12',
        ]));
        $csvResponse->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csvResponse->headers->get('content-type'));

        // 2. Export Range as PDF
        $pdfResponse = $this->actingAs($this->memberA1)->get(route('production-reports.export-range', [
            'format' => 'pdf',
            'from' => '2026-11-01',
            'to' => '2026-11-12',
        ]));
        $pdfResponse->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfResponse->headers->get('content-type'));

        // 3. 1-Click Clipboard Text Summary
        $textResponse = $this->actingAs($this->memberA1)->getJson(route('production-reports.summary-text', [
            'from' => '2026-11-01',
            'to' => '2026-11-12',
        ]));
        $textResponse->assertOk();
        $textResponse->assertJson(['success' => true, 'count' => 2]);
        $text = $textResponse->json('text');
        $this->assertStringContainsString('Rendered product showcase video', $text);
        $this->assertStringContainsString('Updated website product thumbnails', $text);
        $this->assertStringContainsString('Exported in full 4K', $text);

        Carbon::setTestNow();
    }

    public function test_head_compiles_and_submits_team_monthly_report_to_supervisor(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-28 15:00:00', 'Asia/Phnom_Penh'));

        // Member A1 submits a daily report
        $this->actingAs($this->memberA1)->post(route('production-reports.daily.store'), [
            'reporting_date' => '2026-11-12',
            'tasks_completed' => 'Delivered 3 product promo animations',
            'action' => 'submit',
        ]);

        // Member A1 submits their monthly report with attached document
        $doc = UploadedFile::fake()->create('nang-monthly-report.pdf', 300, 'application/pdf');
        $this->actingAs($this->memberA1)->post(route('production-reports.monthly.store'), [
            'reporting_year' => 2026,
            'reporting_month' => 11,
            'attachments' => [$doc],
            'action' => 'submit',
        ]);

        // Dara (Head of Team A) opens Team Monthly compilation page
        $compilePage = $this->actingAs($this->dara)->get(route('production-reports.monthly.team.create', [
            'year' => 2026,
            'month' => 11,
        ]));
        $compilePage->assertOk();
        $compilePage->assertSee('Team A Monthly Production Report');

        // Dara cannot submit without a rating (1-5) and presentation document
        $failResponse = $this->actingAs($this->dara)->post(route('production-reports.monthly.team.store'), [
            'reporting_year' => 2026,
            'reporting_month' => 11,
            'title' => 'Team A Monthly Report - Nov 2026',
            'executive_summary' => 'Team output was outstanding this month.',
            'tasks_completed' => 'Delivered 3 promo animations across campaigns.',
            'next_priorities' => 'Ramp up Q1 video assets.',
            'action' => 'submit',
        ]);
        $failResponse->assertSessionHasErrors(['performance_rating']);

        // Dara submits Team Monthly report with 5-star rating and attached team PPTX
        $teamPptx = UploadedFile::fake()->create('Team-A-Monthly-Review.pptx', 500, 'application/vnd.openxmlformats-officedocument.presentationml.presentation');
        $submitResponse = $this->actingAs($this->dara)->post(route('production-reports.monthly.team.store'), [
            'reporting_year' => 2026,
            'reporting_month' => 11,
            'title' => 'Team A Monthly Report - Nov 2026',
            'executive_summary' => 'Team output was outstanding this month.',
            'tasks_completed' => 'Delivered 3 promo animations across campaigns.',
            'next_priorities' => 'Ramp up Q1 video assets.',
            'performance_rating' => 5,
            'health_status' => 'on_track',
            'attachments' => [$teamPptx],
            'action' => 'submit',
        ]);

        $teamMonthly = ProductionReport::where('report_type', ProductionReport::TYPE_MONTHLY)
            ->where('project_category', ProductionReport::TEAM_MONTHLY_CATEGORY)
            ->where('team_code', 'A')
            ->where('reporting_year', 2026)
            ->where('reporting_month', 11)
            ->firstOrFail();

        $submitResponse->assertRedirect(route('production-reports.show', $teamMonthly->id));
        $this->assertSame(ProductionReport::STATUS_SUBMITTED, $teamMonthly->status);
        $this->assertSame(5, $teamMonthly->performance_rating);
        $this->assertSame('Excellent', $teamMonthly->performance_rating_label);

        // Whole team combined export
        $batchResponse = $this->actingAs($this->dara)->post(route('production-reports.team.export-combined'), [
            'team' => 'A',
            'year' => 2026,
            'month' => 11,
            'format' => 'pdf',
        ]);
        $batchResponse->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $batchResponse->headers->get('content-type'));

        Carbon::setTestNow();
    }

    public function test_dara_and_kim_can_add_and_remove_members_in_their_teams(): void
    {
        $perm = app(\App\Services\ProductionReport\ProductionReportPermissionService::class);

        // Verify they have role admin-digital
        $this->assertTrue($this->dara->hasRole('admin-digital'));
        $this->assertTrue($this->kim->hasRole('admin-digital'));

        // 1. Dara adds a new member into Team A
        $newMember = User::factory()->create([
            'name' => 'New Animator',
            'username' => 'animator_a',
            'team' => 'A',
            'is_active' => true,
        ]);

        $addResponse = $this->actingAs($this->dara)->post(route('production-reports.settings.team-mapping'), [
            'user_id' => $newMember->id,
            'team_code' => 'A',
            'role_in_reporting' => 'member',
            'position_title' => '2D Animator',
        ]);
        $addResponse->assertRedirect(route('production-reports.settings'));

        $mappingA = ProductionReportTeamMapping::where('user_id', $newMember->id)->firstOrFail();
        $this->assertSame('A', $mappingA->team_code);
        $this->assertTrue($mappingA->is_active);
        $this->assertTrue($perm->getTeamMembers('A')->contains('id', $newMember->id));

        // 2. Dara cannot remove a member from Team B
        $mappingB = ProductionReportTeamMapping::where('user_id', $this->memberB1->id)->first();
        if (!$mappingB) {
            $mappingB = ProductionReportTeamMapping::create([
                'user_id' => $this->memberB1->id,
                'team_code' => 'B',
                'team_name' => 'Team B',
                'role_in_reporting' => 'member',
                'is_active' => true,
            ]);
        }
        $forbiddenResponse = $this->actingAs($this->dara)->post(route('production-reports.settings.team-mapping.remove', $mappingB->id));
        $forbiddenResponse->assertForbidden();

        // 3. Dara removes the new member from Team A
        $removeResponse = $this->actingAs($this->dara)->post(route('production-reports.settings.team-mapping.remove', $mappingA->id));
        $removeResponse->assertRedirect(route('production-reports.settings'));
        $this->assertFalse($mappingA->fresh()->is_active);
        $this->assertFalse($perm->getTeamMembers('A')->contains('id', $newMember->id));

        // 4. Kim can remove member from Team B
        $kimRemove = $this->actingAs($this->kim)->post(route('production-reports.settings.team-mapping.remove', $mappingB->id));
        $kimRemove->assertRedirect(route('production-reports.settings'));
        $this->assertFalse($mappingB->fresh()->is_active);
    }

    public function test_dashboard_removes_duplicate_buttons_and_usernames_and_daily_tracker_tab_works(): void
    {
        $res = $this->actingAs($this->dara)->get(route('production-reports.index'));
        $res->assertStatus(200);
        $html = $res->getContent();

        // Only one Compile Team A Weekly button in the page (no duplicate in tracker header)
        $this->assertSame(1, substr_count($html, '★ Compile Team A Weekly'));
        $this->assertSame(1, substr_count($html, '★ Compile Team A Monthly'));
        $this->assertStringNotContainsString('(@' . $this->memberA1->username . ')', $html);

        // Daily Tracker route works for Head and Supervisor
        $trackerRes = $this->actingAs($this->dara)->get(route('production-reports.daily.tracker'));
        $trackerRes->assertStatus(200);
        $trackerRes->assertSee('Daily Production Report Tracker');
        $trackerRes->assertSee($this->memberA1->name);
        $trackerRes->assertDontSee('(@' . $this->memberA1->username . ')');

        $supTrackerRes = $this->actingAs($this->somalika)->get(route('production-reports.daily.tracker', ['team' => 'ALL']));
        $supTrackerRes->assertStatus(200);
        $supTrackerRes->assertSee('All Teams (Team A &amp; B)', false);
    }
}
