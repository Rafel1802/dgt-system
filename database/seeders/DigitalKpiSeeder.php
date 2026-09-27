<?php

namespace Database\Seeders;

use App\Models\Kpi\KpiAuditLog;
use App\Models\Kpi\KpiPeriod;
use App\Models\Kpi\KpiReview;
use App\Models\Kpi\KpiSkill;
use App\Models\Kpi\KpiSquad;
use App\Models\Kpi\KpiSupervisorReport;
use App\Models\User;
use Illuminate\Database\Seeder;

class DigitalKpiSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Key Users
        $superadmin = User::where('username', 'superadmin')->first();
        $somalika   = User::where('username', 'somalika')->first();
        $dara       = User::where('username', 'dara')->first();
        $kim        = User::where('username', 'kim')->first();

        $supervisorId = $somalika?->id ?? ($superadmin?->id ?? 1);
        $daraId = $dara?->id ?? 12;
        $kimId = $kim?->id ?? 13;

        // Staff users
        $lyza      = User::where('username', 'lyza')->first();
        $sreypich  = User::where('username', 'sreypich')->first();
        $lin       = User::where('username', 'lin')->first();
        $heang     = User::where('username', 'heang')->first();
        $nalin     = User::where('username', 'Nalin')->first();

        // 2. Squads
        $squad1 = KpiSquad::updateOrCreate(
            ['code' => 'SQUAD-1'],
            [
                'name' => 'Digital Media Squad 1',
                'lead_id' => $daraId,
                'description' => 'Video Production, Motion Graphics, and Quality Control Unit led by Mr. Dara.'
            ]
        );

        $squad2 = KpiSquad::updateOrCreate(
            ['code' => 'SQUAD-2'],
            [
                'name' => 'Digital Media Squad 2',
                'lead_id' => $kimId,
                'description' => 'Creative Graphic Design, Branding, and Visual Assets Unit led by Mr. KimOun.'
            ]
        );

        // 3. Attach Staff to Squads (Staff below Dara and Kim)
        // Dara's Team (Squad 1)
        if ($lyza) {
            $squad1->members()->syncWithoutDetaching([
                $lyza->id => ['role_title' => 'Video Editor', 'joined_date' => '2026-01-15']
            ]);
        }
        if ($sreypich) {
            $squad1->members()->syncWithoutDetaching([
                $sreypich->id => ['role_title' => 'Motion Designer / QC Assistant', 'joined_date' => '2026-02-01']
            ]);
        }
        if ($lin) {
            $squad1->members()->syncWithoutDetaching([
                $lin->id => ['role_title' => 'Video Content Creator', 'joined_date' => '2026-03-10']
            ]);
        }

        // Kim's Team (Squad 2)
        if ($heang) {
            $squad2->members()->syncWithoutDetaching([
                $heang->id => ['role_title' => 'Senior Graphic Designer', 'joined_date' => '2026-01-10']
            ]);
        }
        if ($nalin) {
            $squad2->members()->syncWithoutDetaching([
                $nalin->id => ['role_title' => 'Visual & Branding Artist', 'joined_date' => '2026-02-15']
            ]);
        }

        // 4. Period (September 2026)
        $period = KpiPeriod::updateOrCreate(
            ['name' => 'September 2026'],
            [
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-30',
                'status' => 'Open',
                'description' => 'Monthly KPI Evaluation Cycle for September 2026.',
            ]
        );

        // 5. Monthly Staff Evaluations for September 2026
        // Dara gives KPI to Ms. Lyza
        if ($lyza) {
            KpiReview::updateOrCreate(
                [
                    'user_id' => $lyza->id,
                    'kpi_period_id' => $period->id,
                ],
                [
                    'squad_id' => $squad1->id,
                    'reviewer_id' => $daraId,
                    'productivity_score' => 94.0,
                    'quality_score' => 96.0,
                    'deadline_score' => 92.0,
                    'teamwork_score' => 95.0,
                    'overall_kpi' => 94.40,
                    'performance_band' => 'Exceeds Expectations',
                    'status' => 'Approved',
                    'manager_notes' => 'Exceptional video editing velocity on TikTok vertical campaigns and YouTube reels. Great pacing and clean transitions.',
                    'supervisor_notes' => 'Commended by Ms. Somalika for high first-pass QC approval rate.',
                    'reviewed_at' => now()->subDays(2),
                ]
            );
        }

        // Dara gives KPI to Ms. Sreypich
        if ($sreypich) {
            KpiReview::updateOrCreate(
                [
                    'user_id' => $sreypich->id,
                    'kpi_period_id' => $period->id,
                ],
                [
                    'squad_id' => $squad1->id,
                    'reviewer_id' => $daraId,
                    'productivity_score' => 91.0,
                    'quality_score' => 95.0,
                    'deadline_score' => 90.0,
                    'teamwork_score' => 95.0,
                    'overall_kpi' => 92.60,
                    'performance_band' => 'Exceeds Expectations',
                    'status' => 'Approved',
                    'manager_notes' => 'Very thorough motion graphics work and quality checks on longform video edits. Zero compliance defects.',
                    'supervisor_notes' => 'Solid technical precision and reliable output.',
                    'reviewed_at' => now()->subDays(2),
                ]
            );
        }

        // Kim gives KPI to Ms. SokHeang
        if ($heang) {
            KpiReview::updateOrCreate(
                [
                    'user_id' => $heang->id,
                    'kpi_period_id' => $period->id,
                ],
                [
                    'squad_id' => $squad2->id,
                    'reviewer_id' => $kimId,
                    'productivity_score' => 96.0,
                    'quality_score' => 98.0,
                    'deadline_score' => 95.0,
                    'teamwork_score' => 97.0,
                    'overall_kpi' => 96.60,
                    'performance_band' => 'Outstanding',
                    'status' => 'Finalized',
                    'manager_notes' => 'Outstanding creativity deploying the 3D clay visual assets and social carousel packages. Fast turnaround and stellar aesthetic.',
                    'supervisor_notes' => 'Top performing graphic designer this month. Commendable visual polish.',
                    'reviewed_at' => now()->subDays(2),
                ]
            );
        }

        // Kim gives KPI to Ms. Nalin
        if ($nalin) {
            KpiReview::updateOrCreate(
                [
                    'user_id' => $nalin->id,
                    'kpi_period_id' => $period->id,
                ],
                [
                    'squad_id' => $squad2->id,
                    'reviewer_id' => $kimId,
                    'productivity_score' => 93.0,
                    'quality_score' => 95.0,
                    'deadline_score' => 92.0,
                    'teamwork_score' => 94.0,
                    'overall_kpi' => 93.60,
                    'performance_band' => 'Exceeds Expectations',
                    'status' => 'Approved',
                    'manager_notes' => 'Consistent brand compliance across all banner sets and high quality vector badges.',
                    'supervisor_notes' => 'Approved with commendation for typography and color palette consistency.',
                    'reviewed_at' => now()->subDays(2),
                ]
            );
        }

        // 6. Squad Monthly Summary Reports
        KpiSupervisorReport::updateOrCreate(
            [
                'squad_id' => $squad1->id,
                'kpi_period_id' => $period->id,
            ],
            [
                'submitted_by' => $daraId,
                'reviewed_by' => $supervisorId,
                'overall_team_kpi' => 93.50,
                'team_productivity' => 92.5,
                'team_quality' => 95.5,
                'team_deadline' => 91.0,
                'status' => 'Approved',
                'summary' => 'Squad 1 completed all high-priority video production edits, maintained flawless QC compliance, and staff achieved strong output.',
                'strengths' => 'Rapid turnaround on TikTok vertical formats and strong collaboration.',
                'improvements' => 'Optimize export queues during multi-project deadlines.',
                'supervisor_notes' => 'Approved. Excellent leadership by Mr. Dara.',
                'submitted_at' => now()->subDays(2),
                'reviewed_at' => now()->subDay(),
            ]
        );

        KpiSupervisorReport::updateOrCreate(
            [
                'squad_id' => $squad2->id,
                'kpi_period_id' => $period->id,
            ],
            [
                'submitted_by' => $kimId,
                'reviewed_by' => $supervisorId,
                'overall_team_kpi' => 95.10,
                'team_productivity' => 94.5,
                'team_quality' => 96.5,
                'team_deadline' => 93.5,
                'status' => 'Finalized',
                'summary' => 'Squad 2 produced high quality creative packages, deployed the 3D clay styling, and staff exceeded design milestones.',
                'strengths' => 'Exceptional visual polish and brand identity craftsmanship.',
                'improvements' => 'Expand seasonal template library.',
                'supervisor_notes' => 'Finalized. Outstanding creative leadership by Mr. KimOun.',
                'submitted_at' => now()->subDays(2),
                'reviewed_at' => now()->subDay(),
            ]
        );

        // 7. Audit Log
        KpiAuditLog::create([
            'user_id' => $supervisorId,
            'action' => 'kpi_staff_system_seeded',
            'entity_type' => KpiSquad::class,
            'entity_id' => $squad1->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'DigitalKpiSeeder',
            'new_values' => ['message' => 'Staff members assigned to Squads 1 & 2 with September evaluations.'],
        ]);
    }
}
