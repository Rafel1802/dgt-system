<?php

namespace Database\Seeders;

use App\Models\Kpi\KpiAssignment;
use App\Models\Kpi\KpiAssignmentItem;
use App\Models\Kpi\KpiAuditLog;
use App\Models\Kpi\KpiPeriod;
use App\Models\Kpi\KpiReview;
use App\Models\Kpi\KpiReviewItem;
use App\Models\Kpi\KpiSkill;
use App\Models\Kpi\KpiSquad;
use App\Models\Kpi\KpiSupervisorReport;
use App\Models\Kpi\KpiTask;
use App\Models\Kpi\KpiTaskSubmission;
use App\Models\Kpi\KpiTemplate;
use App\Models\Kpi\KpiTemplateItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class DigitalKpiSeeder extends Seeder
{
    public function run(): void
    {
        // Find key users
        $superadmin = User::where('username', 'superadmin')->first();
        $somalika   = User::where('username', 'somalika')->first();
        $dara       = User::where('username', 'dara')->first();
        $kim        = User::where('username', 'kim')->first();

        $supervisorId = $somalika?->id ?? ($superadmin?->id ?? 1);
        $daraId = $dara?->id ?? 12;
        $kimId = $kim?->id ?? 13;

        // 1. Squads
        $squad1 = KpiSquad::updateOrCreate(
            ['code' => 'SQUAD-1'],
            [
                'name' => 'Digital Media Squad 1',
                'lead_id' => $daraId,
                'description' => 'Digital Video Production, Quality Control, and Motion Graphics Unit led by Mr. Dara.'
            ]
        );

        $squad2 = KpiSquad::updateOrCreate(
            ['code' => 'SQUAD-2'],
            [
                'name' => 'Digital Media Squad 2',
                'lead_id' => $kimId,
                'description' => 'Creative Graphic Design, Branding, and Social Media Visuals Unit led by Mr. KimOun.'
            ]
        );

        // 2. Period
        $period = KpiPeriod::updateOrCreate(
            ['name' => 'September 2026'],
            [
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-30',
                'status' => 'Open',
                'description' => 'Monthly KPI evaluation cycle for September 2026.',
            ]
        );

        // 3. Core Skills
        $skillVideo = KpiSkill::updateOrCreate(
            ['name' => 'Video Production & Editing'],
            ['category' => 'Creative', 'description' => 'Video editing, pacing, color grading, sound design and rendering.']
        );

        $skillGraphic = KpiSkill::updateOrCreate(
            ['name' => 'Graphic & Visual Design'],
            ['category' => 'Creative', 'description' => 'Visual identity, banner layout, typography, 3D clay styling, and vectors.']
        );

        $skillQC = KpiSkill::updateOrCreate(
            ['name' => 'QC & Brand Compliance'],
            ['category' => 'Quality', 'description' => 'Flawless brand compliance, error-free output, and technical precision.']
        );

        // 4. Templates
        $tpl1 = KpiTemplate::updateOrCreate(
            ['name' => 'Squad 1 Video & QC Standard Template'],
            [
                'role_type' => 'QC & Video Lead',
                'skill_id' => $skillVideo->id,
                'description' => 'Evaluates deliverables volume, quality control accuracy, and delivery velocity.'
            ]
        );

        $tplItem1_1 = KpiTemplateItem::firstOrCreate([
            'template_id' => $tpl1->id,
            'name' => 'Monthly Deliverables Volume',
        ], [
            'pillar' => 'productivity',
            'weight' => 35.0,
            'target_value' => 20.0,
            'unit' => 'videos',
        ]);

        $tplItem1_2 = KpiTemplateItem::firstOrCreate([
            'template_id' => $tpl1->id,
            'name' => 'Quality & First-Time Approval Rate',
        ], [
            'pillar' => 'quality',
            'weight' => 35.0,
            'target_value' => 95.0,
            'unit' => '%',
        ]);

        $tplItem1_3 = KpiTemplateItem::firstOrCreate([
            'template_id' => $tpl1->id,
            'name' => 'Turnaround Time (TAT)',
        ], [
            'pillar' => 'deadline',
            'weight' => 30.0,
            'target_value' => 90.0,
            'unit' => '% on-time',
        ]);

        $tpl2 = KpiTemplate::updateOrCreate(
            ['name' => 'Squad 2 Graphic & Creative Template'],
            [
                'role_type' => 'Graphic Lead',
                'skill_id' => $skillGraphic->id,
                'description' => 'Evaluates visual creativity, artwork turnaround, and brand consistency.'
            ]
        );

        $tplItem2_1 = KpiTemplateItem::firstOrCreate([
            'template_id' => $tpl2->id,
            'name' => 'Creative Deliverables Volume',
        ], [
            'pillar' => 'productivity',
            'weight' => 40.0,
            'target_value' => 25.0,
            'unit' => 'graphics',
        ]);

        $tplItem2_2 = KpiTemplateItem::firstOrCreate([
            'template_id' => $tpl2->id,
            'name' => 'Visual Quality & Brand Aesthetics',
        ], [
            'pillar' => 'quality',
            'weight' => 30.0,
            'target_value' => 95.0,
            'unit' => '%',
        ]);

        $tplItem2_3 = KpiTemplateItem::firstOrCreate([
            'template_id' => $tpl2->id,
            'name' => 'Design Delivery Speed & Turnaround',
        ], [
            'pillar' => 'deadline',
            'weight' => 30.0,
            'target_value' => 90.0,
            'unit' => '% on-time',
        ]);

        // 5. Monthly KPI Assignments for Dara and Kim
        // Dara Assignment
        $assignDara = KpiAssignment::updateOrCreate(
            [
                'user_id' => $daraId,
                'kpi_period_id' => $period->id,
            ],
            [
                'squad_id' => $squad1->id,
                'assigned_by' => $supervisorId,
                'template_id' => $tpl1->id,
                'target_deliverables' => 20,
                'status' => 'Evaluated',
            ]
        );

        $assignDaraItem1 = KpiAssignmentItem::firstOrCreate([
            'assignment_id' => $assignDara->id,
            'name' => 'Monthly Deliverables Volume',
        ], [
            'template_item_id' => $tplItem1_1->id,
            'pillar' => 'productivity',
            'weight' => 35.0,
            'target_value' => 20.0,
            'unit' => 'videos',
        ]);

        $assignDaraItem2 = KpiAssignmentItem::firstOrCreate([
            'assignment_id' => $assignDara->id,
            'name' => 'Quality & First-Time Approval Rate',
        ], [
            'template_item_id' => $tplItem1_2->id,
            'pillar' => 'quality',
            'weight' => 35.0,
            'target_value' => 95.0,
            'unit' => '%',
        ]);

        $assignDaraItem3 = KpiAssignmentItem::firstOrCreate([
            'assignment_id' => $assignDara->id,
            'name' => 'Turnaround Time (TAT)',
        ], [
            'template_item_id' => $tplItem1_3->id,
            'pillar' => 'deadline',
            'weight' => 30.0,
            'target_value' => 90.0,
            'unit' => '% on-time',
        ]);

        // Kim Assignment
        $assignKim = KpiAssignment::updateOrCreate(
            [
                'user_id' => $kimId,
                'kpi_period_id' => $period->id,
            ],
            [
                'squad_id' => $squad2->id,
                'assigned_by' => $supervisorId,
                'template_id' => $tpl2->id,
                'target_deliverables' => 25,
                'status' => 'Finalized',
            ]
        );

        $assignKimItem1 = KpiAssignmentItem::firstOrCreate([
            'assignment_id' => $assignKim->id,
            'name' => 'Creative Deliverables Volume',
        ], [
            'template_item_id' => $tplItem2_1->id,
            'pillar' => 'productivity',
            'weight' => 40.0,
            'target_value' => 25.0,
            'unit' => 'graphics',
        ]);

        $assignKimItem2 = KpiAssignmentItem::firstOrCreate([
            'assignment_id' => $assignKim->id,
            'name' => 'Visual Quality & Brand Aesthetics',
        ], [
            'template_item_id' => $tplItem2_2->id,
            'pillar' => 'quality',
            'weight' => 30.0,
            'target_value' => 95.0,
            'unit' => '%',
        ]);

        $assignKimItem3 = KpiAssignmentItem::firstOrCreate([
            'assignment_id' => $assignKim->id,
            'name' => 'Design Delivery Speed & Turnaround',
        ], [
            'template_item_id' => $tplItem2_3->id,
            'pillar' => 'deadline',
            'weight' => 30.0,
            'target_value' => 90.0,
            'unit' => '% on-time',
        ]);

        // 6. Deliverables / Tasks for Dara (Squad 1)
        $daraTasks = [
            ['title' => 'Master Promo 4K Highlight Reel', 'priority' => 'High', 'status' => 'Approved', 'quality' => 95.0],
            ['title' => 'Podcast Episode 44 Multicam Edit', 'priority' => 'High', 'status' => 'Approved', 'quality' => 94.0],
            ['title' => 'TikTok Vertical Teaser Campaign #1', 'priority' => 'Medium', 'status' => 'Approved', 'quality' => 92.0],
            ['title' => 'YouTube Longform Color Grade & QC', 'priority' => 'Urgent', 'status' => 'Approved', 'quality' => 98.0],
            ['title' => 'Product Feature Motion Graphic Intro', 'priority' => 'High', 'status' => 'Approved', 'quality' => 93.0],
            ['title' => 'Livestream Replay High-Bitrate Export', 'priority' => 'Medium', 'status' => 'Approved', 'quality' => 95.0],
        ];

        foreach ($daraTasks as $idx => $t) {
            $task = KpiTask::firstOrCreate([
                'title' => $t['title'],
                'assignee_id' => $daraId,
                'kpi_period_id' => $period->id,
            ], [
                'squad_id' => $squad1->id,
                'creator_id' => $supervisorId,
                'priority' => $t['priority'],
                'status' => $t['status'],
                'due_date' => now()->addDays($idx + 1),
                'completed_at' => now()->subDays(2),
                'quality_score' => $t['quality'],
                'feedback' => 'Approved with high praise for color balance and timing.',
            ]);

            KpiTaskSubmission::firstOrCreate([
                'task_id' => $task->id,
            ], [
                'user_id' => $daraId,
                'evidence_url' => 'https://drive.google.com/drive/folders/squad1_dara_deliverables',
                'notes' => 'Complete high bitrate render and full QC review performed.',
                'submitted_at' => now()->subDays(2),
            ]);
        }

        // 7. Deliverables / Tasks for Kim (Squad 2)
        $kimTasks = [
            ['title' => '3D Clay Brand Visual Assets System', 'priority' => 'Urgent', 'status' => 'Approved', 'quality' => 98.0],
            ['title' => 'Social Carousel Graphic Package (10 slides)', 'priority' => 'High', 'status' => 'Approved', 'quality' => 96.0],
            ['title' => 'Hero Banner Campaign September Release', 'priority' => 'High', 'status' => 'Approved', 'quality' => 97.0],
            ['title' => 'Typography & Iconography Guidelines 2026', 'priority' => 'Medium', 'status' => 'Approved', 'quality' => 95.0],
            ['title' => 'Story Poster Series for Product Launch', 'priority' => 'High', 'status' => 'Approved', 'quality' => 96.0],
            ['title' => 'Vector Badge & Achievement Collection', 'priority' => 'Medium', 'status' => 'Approved', 'quality' => 95.0],
        ];

        foreach ($kimTasks as $idx => $t) {
            $task = KpiTask::firstOrCreate([
                'title' => $t['title'],
                'assignee_id' => $kimId,
                'kpi_period_id' => $period->id,
            ], [
                'squad_id' => $squad2->id,
                'creator_id' => $supervisorId,
                'priority' => $t['priority'],
                'status' => $t['status'],
                'due_date' => now()->addDays($idx + 1),
                'completed_at' => now()->subDays(1),
                'quality_score' => $t['quality'],
                'feedback' => 'Exceptional 3D clay aesthetic with clean typography.',
            ]);

            KpiTaskSubmission::firstOrCreate([
                'task_id' => $task->id,
            ], [
                'user_id' => $kimId,
                'evidence_url' => 'https://drive.google.com/drive/folders/squad2_kim_creative_assets',
                'notes' => 'Exported in high resolution PNG, SVG, and Figma design tokens.',
                'submitted_at' => now()->subDays(1),
            ]);
        }

        // 8. Reviews
        // Review for Dara
        $revDara = KpiReview::updateOrCreate(
            [
                'user_id' => $daraId,
                'kpi_period_id' => $period->id,
            ],
            [
                'kpi_assignment_id' => $assignDara->id,
                'reviewer_id' => $supervisorId,
                'productivity_score' => 92.0,
                'quality_score' => 96.0,
                'deadline_score' => 94.0,
                'teamwork_score' => 95.0,
                'overall_kpi' => 94.25,
                'performance_band' => 'Exceeds Expectations',
                'status' => 'Approved',
                'manager_notes' => 'Outstanding video production output with rapid turnaround times and zero QC defects.',
                'reviewed_at' => now()->subDay(),
            ]
        );

        KpiReviewItem::firstOrCreate([
            'review_id' => $revDara->id,
            'assignment_item_id' => $assignDaraItem1->id,
        ], [
            'score' => 92.0,
            'weight' => 35.0,
            'weighted_score' => 32.2,
            'comments' => 'Delivered 18/20 target videos efficiently.',
        ]);

        KpiReviewItem::firstOrCreate([
            'review_id' => $revDara->id,
            'assignment_item_id' => $assignDaraItem2->id,
        ], [
            'score' => 96.0,
            'weight' => 35.0,
            'weighted_score' => 33.6,
            'comments' => 'Exceptional QC pass rate.',
        ]);

        KpiReviewItem::firstOrCreate([
            'review_id' => $revDara->id,
            'assignment_item_id' => $assignDaraItem3->id,
        ], [
            'score' => 94.0,
            'weight' => 30.0,
            'weighted_score' => 28.2,
            'comments' => 'Average turnaround 3.3h.',
        ]);

        // Review for Kim
        $revKim = KpiReview::updateOrCreate(
            [
                'user_id' => $kimId,
                'kpi_period_id' => $period->id,
            ],
            [
                'kpi_assignment_id' => $assignKim->id,
                'reviewer_id' => $supervisorId,
                'productivity_score' => 96.0,
                'quality_score' => 98.0,
                'deadline_score' => 95.0,
                'teamwork_score' => 97.0,
                'overall_kpi' => 96.50,
                'performance_band' => 'Outstanding',
                'status' => 'Finalized',
                'manager_notes' => 'Exceptional visual design quality, introduced new 3D clay design tokens, stellar leadership.',
                'reviewed_at' => now()->subDay(),
            ]
        );

        KpiReviewItem::firstOrCreate([
            'review_id' => $revKim->id,
            'assignment_item_id' => $assignKimItem1->id,
        ], [
            'score' => 96.0,
            'weight' => 40.0,
            'weighted_score' => 38.4,
            'comments' => 'Delivered 24/25 graphics packages.',
        ]);

        KpiReviewItem::firstOrCreate([
            'review_id' => $revKim->id,
            'assignment_item_id' => $assignKimItem2->id,
        ], [
            'score' => 98.0,
            'weight' => 30.0,
            'weighted_score' => 29.4,
            'comments' => 'Aesthetic quality exceeded expectations.',
        ]);

        KpiReviewItem::firstOrCreate([
            'review_id' => $revKim->id,
            'assignment_item_id' => $assignKimItem3->id,
        ], [
            'score' => 95.0,
            'weight' => 30.0,
            'weighted_score' => 28.5,
            'comments' => 'Consistently delivered under 3.5h TAT.',
        ]);

        // 9. Supervisor Reports
        KpiSupervisorReport::updateOrCreate(
            [
                'squad_id' => $squad1->id,
                'kpi_period_id' => $period->id,
            ],
            [
                'submitted_by' => $daraId,
                'reviewed_by' => $supervisorId,
                'overall_team_kpi' => 94.25,
                'team_productivity' => 92.0,
                'team_quality' => 96.0,
                'team_deadline' => 94.0,
                'status' => 'Approved',
                'summary' => 'Squad 1 completed all high-priority 4K video exports, maintained rigorous QC standards, and reduced average TAT to 3.3h.',
                'strengths' => 'Strong motion graphics capability and consistent quality standards.',
                'improvements' => 'Optimize export rendering queue during multi-project deadlines.',
                'supervisor_notes' => 'Approved with commendation. High performance throughout September cycle.',
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
                'overall_team_kpi' => 96.50,
                'team_productivity' => 96.0,
                'team_quality' => 98.0,
                'team_deadline' => 95.0,
                'status' => 'Finalized',
                'summary' => 'Squad 2 produced 24 creative design packages, successfully deployed the 3D clay visual aesthetic system, and exceeded customer engagement targets.',
                'strengths' => 'Extraordinary visual aesthetics and leadership in team brand consistency.',
                'improvements' => 'Expand template library for seasonal campaigns.',
                'supervisor_notes' => 'Finalized. Squad 2 achieved Outstanding performance for September 2026.',
                'submitted_at' => now()->subDays(2),
                'reviewed_at' => now()->subDay(),
            ]
        );

        // 10. Audit Log
        KpiAuditLog::create([
            'user_id' => $supervisorId,
            'action' => 'kpi_system_initialized',
            'entity_type' => KpiSquad::class,
            'entity_id' => $squad1->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'DigitalKpiSeeder',
            'new_values' => ['message' => 'StaffKPI system successfully seeded and integrated into Digital System.'],
        ]);
    }
}
