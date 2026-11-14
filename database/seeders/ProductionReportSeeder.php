<?php

namespace Database\Seeders;

use App\Models\ProductionReport\ProductionReport;
use App\Models\ProductionReport\ProductionReportTeamMapping;
use App\Models\User;
use App\Services\ProductionReport\ProductionReportPermissionService;
use App\Services\ProductionReport\ProductionReportService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class ProductionReportSeeder extends Seeder
{
    public function run(): void
    {
        $adminDigitalRole = Role::firstOrCreate(['name' => 'admin-digital', 'guard_name' => 'web']);
        $digitalTeamRole = Role::firstOrCreate(['name' => 'digital-team', 'guard_name' => 'web']);

        $defaultPassword = Hash::make('Password@123!');

        // 1. Supervisor: Somalika (View-only for all production teams)
        $somalika = User::updateOrCreate(
            ['username' => 'somalika'],
            [
                'name' => 'Somalika (Supervisor)',
                'email' => 'somalika@dgt.local',
                'password' => $defaultPassword,
                'team' => null,
                'team_role' => 'Production Supervisor',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $somalika->syncRoles([$adminDigitalRole]);

        ProductionReportTeamMapping::updateOrCreate(
            ['user_id' => $somalika->id],
            [
                'role_in_reporting' => ProductionReportTeamMapping::ROLE_SUPERVISOR,
                'team_code' => 'ALL',
                'team_name' => 'All Production Teams',
                'position_title' => 'Production Supervisor',
                'can_submit_daily' => false,
                'can_submit_weekly' => false,
                'can_submit_monthly' => false,
                'is_active' => true,
            ]
        );

        // 2. Head of Production Team A: Dara
        $dara = User::updateOrCreate(
            ['username' => 'dara'],
            [
                'name' => 'Dara (Head - Team A)',
                'email' => 'soporadara@kiuq.com',
                'password' => $defaultPassword,
                'team' => 'Team A',
                'team_role' => 'Head of Production (Team A)',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $dara->syncRoles([$adminDigitalRole, $digitalTeamRole]);

        ProductionReportTeamMapping::updateOrCreate(
            ['user_id' => $dara->id],
            [
                'role_in_reporting' => ProductionReportTeamMapping::ROLE_HEAD,
                'team_code' => ProductionReportPermissionService::TEAM_A,
                'team_name' => 'Digital Media Production Team A',
                'position_title' => 'Head of Production (Team A)',
                'can_submit_daily' => true,
                'can_submit_weekly' => true,
                'can_submit_monthly' => true,
                'is_active' => true,
            ]
        );

        // 3. Head of Production Team B: Kim
        $kim = User::updateOrCreate(
            ['username' => 'kim'],
            [
                'name' => 'KimOun (Head - Team B)',
                'email' => 'kim@dgt.local',
                'password' => $defaultPassword,
                'team' => 'Digital Media',
                'team_role' => 'Head of Production (Team B)',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $kim->syncRoles([$adminDigitalRole, $digitalTeamRole]);

        ProductionReportTeamMapping::updateOrCreate(
            ['user_id' => $kim->id],
            [
                'role_in_reporting' => ProductionReportTeamMapping::ROLE_HEAD,
                'team_code' => ProductionReportPermissionService::TEAM_B,
                'team_name' => 'Digital Media Production Team B',
                'position_title' => 'Head of Production (Team B)',
                'can_submit_daily' => true,
                'can_submit_weekly' => true,
                'can_submit_monthly' => true,
                'is_active' => true,
            ]
        );

        // 4. Team A Members (Assigned to Dara)
        $teamAMembers = [
            ['username' => 'nang', 'name' => 'Samnang', 'role' => 'Video & Web Specialist'],
            ['username' => 'vouchky', 'name' => 'Vouchky', 'role' => 'Video Content Specialist'],
            ['username' => 'chhay', 'name' => 'Chhay', 'role' => 'Motion Graphic Artist'],
            ['username' => 'nalin', 'name' => 'Nalin', 'role' => 'Video Content Creator'],
            ['username' => 'sreypich', 'name' => 'Sreypich', 'role' => 'Motion Designer / QC'],
            ['username' => 'heang', 'name' => 'SokHeang', 'role' => 'Media QC & Editor'],
        ];

        $teamAUserModels = [];
        foreach ($teamAMembers as $memberData) {
            $member = User::updateOrCreate(
                ['username' => $memberData['username']],
                [
                    'name' => $memberData['name'],
                    'email' => $memberData['username'] . '@dgt.local',
                    'password' => $defaultPassword,
                    'team' => 'Team A',
                    'team_role' => $memberData['role'],
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
            $member->syncRoles([$digitalTeamRole]);

            ProductionReportTeamMapping::updateOrCreate(
                ['user_id' => $member->id],
                [
                    'role_in_reporting' => ProductionReportTeamMapping::ROLE_MEMBER,
                    'team_code' => ProductionReportPermissionService::TEAM_A,
                    'team_name' => 'Digital Media Production Team A',
                    'position_title' => $memberData['role'],
                    'can_submit_daily' => true,
                    'can_submit_weekly' => false,
                    'can_submit_monthly' => true,
                    'is_active' => true,
                ]
            );
            $teamAUserModels[] = $member;
        }

        // 5. Team B Members (Assigned to Kim)
        $teamBMembers = [
            ['username' => 'pich', 'name' => 'Pich', 'role' => 'Senior Graphic Designer'],
            ['username' => 'sor', 'name' => 'Sor', 'role' => 'Visual & Brand Designer'],
            ['username' => 'lin', 'name' => 'Lin', 'role' => 'Creative Content Designer'],
            ['username' => 'sarak', 'name' => 'Sarak', 'role' => 'Creative Design Specialist'],
        ];

        $teamBUserModels = [];
        foreach ($teamBMembers as $memberData) {
            $member = User::updateOrCreate(
                ['username' => $memberData['username']],
                [
                    'name' => $memberData['name'],
                    'email' => $memberData['username'] . '@dgt.local',
                    'password' => $defaultPassword,
                    'team' => 'Digital Media',
                    'team_role' => $memberData['role'],
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
            $member->syncRoles([$digitalTeamRole]);

            ProductionReportTeamMapping::updateOrCreate(
                ['user_id' => $member->id],
                [
                    'role_in_reporting' => ProductionReportTeamMapping::ROLE_MEMBER,
                    'team_code' => ProductionReportPermissionService::TEAM_B,
                    'team_name' => 'Digital Media Production Team B',
                    'position_title' => $memberData['role'],
                    'can_submit_daily' => true,
                    'can_submit_weekly' => false,
                    'can_submit_monthly' => true,
                    'is_active' => true,
                ]
            );
            $teamBUserModels[] = $member;
        }

        // 6. Seed Sample Daily, Weekly, and Monthly Reports (only if none exist yet)
        if (ProductionReport::count() > 0) {
            return;
        }

        $now = Carbon::now('Asia/Phnom_Penh');
        $todayStr = $now->toDateString();
        $yesterdayStr = $now->copy()->subDay()->toDateString();

        $reportService = app(ProductionReportService::class);

        foreach ([$yesterdayStr, $todayStr] as $dateIndex => $reportDate) {
            // Seed Team A daily reports
            foreach (array_slice($teamAUserModels, 0, 4) as $idx => $member) {
                $reportService->saveOrSubmitDailyReport(
                    $member,
                    [
                        'reporting_date' => $reportDate,
                        'project_category' => 'Video Production & Motion Graphics',
                        'tasks_completed' => "Completed 2 vertical product promo reels and 1 animated lower-third title package for campaign #" . ($dateIndex + 1) . ".\nPassed internal audio-sync QC check.",
                        'work_in_progress' => 'Color grading on 4K showroom walkthrough video (75% complete).',
                        'pending_tasks' => 'Export final localized subtitles in Khmer and English.',
                        'blockers' => $idx === 1 ? 'Waiting for final voiceover audio track from marketing.' : 'None — all assets available.',
                        'achievements' => 'Delivered 2 high-retention vertical reels ahead of 5:00 PM deadline with zero QC revisions.',
                        'deliverable_links' => ['https://drive.google.com/drive/folders/1sQiq4-jtjsRmEBfJr6ofZkHXuHZuLhTi'],
                        'items' => [
                            [
                                'task_title' => 'Edited vertical product promo reels (TikTok / Reels)',
                                'task_category' => 'Video Production',
                                'quantity' => 2,
                                'unit' => 'videos',
                                'status' => 'completed',
                            ],
                            [
                                'task_title' => 'Lower-third animated title package',
                                'task_category' => 'Motion Graphics',
                                'quantity' => 1,
                                'unit' => 'graphics',
                                'status' => 'completed',
                            ],
                        ],
                    ],
                    [],
                    true
                );
            }

            // Seed Team B daily reports (Kim's Team)
            foreach (array_slice($teamBUserModels, 0, 4) as $idx => $member) {
                $reportService->saveOrSubmitDailyReport(
                    $member,
                    [
                        'reporting_date' => $reportDate,
                        'project_category' => 'Creative Graphic Design & Branding',
                        'tasks_completed' => "Designed 3 social media promotional posters and 1 product carousel set.\nExported print-ready and web-optimized assets.",
                        'work_in_progress' => '3D clay visual key-art for upcoming seasonal campaign.',
                        'pending_tasks' => 'Resize banner variations for website hero slider.',
                        'blockers' => 'None.',
                        'achievements' => 'Approved on first review by Creative Lead with 100% brand consistency.',
                        'deliverable_links' => ['https://drive.google.com/drive/folders/1sQiq4-jtjsRmEBfJr6ofZkHXuHZuLhTi'],
                        'items' => [
                            [
                                'task_title' => 'Social media campaign posters (1080x1350)',
                                'task_category' => 'Poster Design',
                                'quantity' => 3,
                                'unit' => 'posters',
                                'status' => 'completed',
                            ],
                            [
                                'task_title' => 'Multi-slide product feature carousel',
                                'task_category' => 'Graphic Design',
                                'quantity' => 1,
                                'unit' => 'carousels',
                                'status' => 'completed',
                            ],
                        ],
                    ],
                    [],
                    true
                );
            }
        }

        // 7. Compile and Submit Weekly Team Reports for Team A (Dara) and Team B (Kim)
        $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $weekEnd = $now->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $reportService->saveOrSubmitWeeklyReport(
            $dara,
            [
                'team_code' => ProductionReportPermissionService::TEAM_A,
                'reporting_start_date' => $weekStart,
                'reporting_end_date' => $weekEnd,
                'executive_summary' => 'Team A achieved strong video production velocity this week, delivering 16 vertical reels and 8 motion graphic packages with zero QC compliance defects.',
                'achievements' => "1. Completed 16 vertical product promo reels across TikTok and Reels.\n2. Standardized motion lower-third templates for faster editing turnaround.\n3. Maintained 100% first-pass audio/visual quality control.",
                'next_priorities' => "1. Finalize 4K showroom walkthrough series.\n2. Deliver bilingual subtitle packages for Q4 hero videos.",
                'performance_rating' => 5,
                'head_comments' => 'Outstanding teamwork and deadline discipline across all video editors and motion designers.',
            ],
            [],
            true
        );

        $reportService->saveOrSubmitWeeklyReport(
            $kim,
            [
                'team_code' => ProductionReportPermissionService::TEAM_B,
                'reporting_start_date' => $weekStart,
                'reporting_end_date' => $weekEnd,
                'executive_summary' => 'Team B delivered 24 high-impact promotional posters and 8 multi-slide carousels this week, elevating visual consistency across all digital channels.',
                'achievements' => "1. Launched 24 campaign posters with new 3D visual styling.\n2. Completed 8 product feature carousels ahead of schedule.\n3. Updated brand asset library for social and web banners.",
                'next_priorities' => "1. Complete seasonal campaign hero banners.\n2. Deliver eBay and website promotional graphics.",
                'performance_rating' => 5,
                'head_comments' => 'Exceptional visual polish and creative execution by all Team B designers.',
            ],
            [],
            true
        );

        // 8. Compile and Submit Sample Monthly Reports for Samnang (Team A) & Pich (Team B)
        if (!empty($teamAUserModels)) {
            $reportService->saveOrSubmitMonthlyReport(
                $teamAUserModels[0],
                [
                    'reporting_year' => (int) $now->year,
                    'reporting_month' => (int) $now->month,
                    'executive_summary' => 'Completed all assigned daily video editing and motion graphic deliverables for the month with 100% on-time submission.',
                    'next_priorities' => 'Expand 4K product showcase series and optimize vertical reel hooks.',
                ],
                [],
                true
            );
        }

        if (!empty($teamBUserModels)) {
            $reportService->saveOrSubmitMonthlyReport(
                $teamBUserModels[0],
                [
                    'reporting_year' => (int) $now->year,
                    'reporting_month' => (int) $now->month,
                    'executive_summary' => 'Delivered all monthly social media poster campaigns and brand graphic packages on schedule.',
                    'next_priorities' => 'Lead visual direction for next month seasonal product launch.',
                ],
                [],
                true
            );
        }
    }
}
