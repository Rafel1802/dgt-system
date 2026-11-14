<?php

namespace App\Console\Commands;

use App\Models\ProductionReport\ProductionReport;
use App\Models\ProductionReport\ProductionReportDeadline;
use App\Models\User;
use App\Services\ProductionReport\ProductionReportPermissionService;
use App\Services\ProductionReport\ProductionReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ProcessProductionReportScheduleCommand extends Command
{
    protected $signature = 'production-reports:process-schedule
                            {--task=all : Specific schedule task: all, daily-reminder, daily-overdue, weekly-draft, weekly-reminder, weekly-overdue, monthly-draft, monthly-reminder, monthly-overdue}';

    protected $description = 'Process Production Report scheduled reminders, automatic weekly/monthly draft compilations, and overdue alerts (Asia/Phnom_Penh).';

    public function __construct(
        private readonly ProductionReportPermissionService $permissionService,
        private readonly ProductionReportService $reportService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->permissionService->syncDefaultMappingsIfNeeded();
        $task = strtolower((string) $this->option('task'));
        $now = $this->reportService->nowCambodia();

        if (in_array($task, ['all', 'daily-reminder'], true)) {
            $this->processDailyReminders($now);
        }

        if (in_array($task, ['all', 'daily-overdue'], true)) {
            $this->processDailyOverdue($now);
        }

        if (in_array($task, ['all', 'weekly-draft'], true)) {
            $this->processWeeklyDraftPreparation($now);
        }

        if (in_array($task, ['all', 'weekly-reminder'], true)) {
            $this->processWeeklyFinalReminder($now);
        }

        if (in_array($task, ['all', 'weekly-overdue'], true)) {
            $this->processWeeklyOverdue($now);
        }

        if (in_array($task, ['all', 'monthly-draft'], true)) {
            $this->processMonthlyDraftPreparation($now);
        }

        if (in_array($task, ['all', 'monthly-reminder'], true)) {
            $this->processMonthlyReminders($now);
        }

        if (in_array($task, ['all', 'monthly-overdue'], true)) {
            $this->processMonthlyOverdue($now);
        }

        return self::SUCCESS;
    }

    /**
     * 1. Daily 4:45 PM Cambodia reminder (15 minutes before the 5:00 PM deadline) to every active
     *    production member (and Head, when allowed to submit dailies) who hasn't submitted today's report.
     *    Frozen / deactivated accounts are never notified.
     */
    protected function processDailyReminders(Carbon $now): void
    {
        $todayStr = $now->toDateString();
        $members = $this->permissionService->getTeamMembers('A')
            ->concat($this->permissionService->getTeamMembers('B'));

        foreach (['A', 'B'] as $teamCode) {
            $head = $this->permissionService->getTeamHead($teamCode);
            if ($head && $head->is_active && $this->permissionService->canSubmitDaily($head)) {
                $members->push($head);
            }
        }

        $members = $members->filter(fn (User $u) => (bool) $u->is_active)->unique('id');

        $remindedCount = 0;
        foreach ($members as $member) {
            $submitted = ProductionReport::where('user_id', $member->id)
                ->where('report_type', ProductionReport::TYPE_DAILY)
                ->whereDate('reporting_date', $todayStr)
                ->whereIn('status', [
                    ProductionReport::STATUS_SUBMITTED,
                    ProductionReport::STATUS_SUBMITTED_LATE,
                    ProductionReport::STATUS_APPROVED,
                ])
                ->exists();

            if ($submitted) {
                continue;
            }

            $dedupeKey = "pr_daily_reminder_{$todayStr}_u{$member->id}";
            if (Cache::has($dedupeKey)) {
                continue;
            }

            $this->reportService->sendSafeNotification($member, [
                'module' => 'digital',
                'title' => '⏰ Daily Report due in 15 minutes (5:00 PM)',
                'message' => "Only 15 minutes left! Please submit your Daily Production Report for {$now->format('M d, Y')} before 5:00 PM Cambodia time.",
                'actor_name' => 'Production Report Scheduler',
                'link' => route('production-reports.daily.create', ['date' => $todayStr]),
            ]);

            Cache::put($dedupeKey, true, now()->addHours(18));
            $remindedCount++;
        }

        $this->info("Daily reminders processed: {$remindedCount} member(s) notified.");
    }

    /**
     * 2. Daily after 5:00 PM Cambodia overdue check.
     */
    protected function processDailyOverdue(Carbon $now): void
    {
        $todayStr = $now->toDateString();
        foreach (['A', 'B'] as $teamCode) {
            $members = $this->permissionService->getTeamMembers($teamCode);
            $missingNames = [];

            foreach ($members as $member) {
                $report = ProductionReport::where('user_id', $member->id)
                    ->where('report_type', ProductionReport::TYPE_DAILY)
                    ->whereDate('reporting_date', $todayStr)
                    ->first();

                if (!$report || !$report->isSubmittedOrApproved()) {
                    $missingNames[] = $member->name;
                    if ($report && $report->status === ProductionReport::STATUS_DRAFT) {
                        $report->update(['is_late' => true]);
                    }
                }
            }

            if (!empty($missingNames)) {
                $dedupeKey = "pr_daily_overdue_{$todayStr}_team_{$teamCode}";
                if (!Cache::has($dedupeKey)) {
                    $head = $this->permissionService->getTeamHead($teamCode);
                    $somalika = User::whereRaw('LOWER(username) = ?', ['somalika'])->first();
                    $msg = "Team {$teamCode} has " . count($missingNames) . " overdue/missing daily report(s) for {$now->format('M d, Y')} (past 5:00 PM deadline): " . implode(', ', $missingNames);

                    if ($head) {
                        $this->reportService->sendSafeNotification($head, [
                            'module' => 'digital',
                            'title' => "Team {$teamCode} Overdue Daily Reports Alert",
                            'message' => $msg,
                            'actor_name' => 'Production Report Scheduler',
                            'link' => route('production-reports.index'),
                        ]);
                    }
                    if ($somalika) {
                        $this->reportService->sendSafeNotification($somalika, [
                            'module' => 'digital',
                            'title' => "Team {$teamCode} Overdue Daily Reports Alert",
                            'message' => $msg,
                            'actor_name' => 'Production Report Scheduler',
                            'link' => route('production-reports.index'),
                        ]);
                    }

                    Cache::put($dedupeKey, true, now()->addHours(18));
                }
            }
        }

        $this->info('Daily overdue check completed.');
    }

    /**
     * 3. Friday Afternoon: Auto-prepare weekly team report drafts for Dara (Team A) and Kim (Team B).
     */
    protected function processWeeklyDraftPreparation(Carbon $now): void
    {
        $period = $this->reportService->getDefaultWeeklyPeriod($now);
        $startDate = $period['start_date'];
        $endDate = $period['end_date'];

        foreach (['A', 'B'] as $teamCode) {
            $head = $this->permissionService->getTeamHead($teamCode);
            if (!$head) {
                continue;
            }

            $existing = ProductionReport::where('report_type', ProductionReport::TYPE_WEEKLY)
                ->where('team_code', $teamCode)
                ->whereDate('reporting_start_date', $startDate)
                ->whereDate('reporting_end_date', $endDate)
                ->first();

            if (!$existing) {
                $draft = $this->reportService->saveOrSubmitWeeklyReport($head, [
                    'team_code' => $teamCode,
                    'reporting_start_date' => $startDate,
                    'reporting_end_date' => $endDate,
                    'revision_notes' => 'Auto-compiled Friday afternoon weekly team report draft.',
                ], [], false);

                $this->reportService->sendSafeNotification($head, [
                    'module' => 'digital',
                    'title' => "Weekly Team {$teamCode} Report Draft Prepared",
                    'message' => "Your Team {$teamCode} Weekly Production Report draft ({$draft->period_label}) has been auto-compiled from your team's daily reports. Please review, rate team performance (1–5), select best posters/videos, and submit before Saturday 8:00 AM.",
                    'actor_name' => 'Production Report Scheduler',
                    'link' => route('production-reports.weekly.create', ['team' => $teamCode, 'start_date' => $startDate, 'end_date' => $endDate]),
                ]);
            }
        }

        $this->info('Weekly draft preparation completed for Team A and Team B.');
    }

    /**
     * 4. Saturday 7:00 AM Cambodia: Final reminder to Dara and Kim before 8:00 AM deadline.
     */
    protected function processWeeklyFinalReminder(Carbon $now): void
    {
        $period = $this->reportService->getDefaultWeeklyPeriod($now->copy()->subDay());
        $startDate = $period['start_date'];
        $endDate = $period['end_date'];

        foreach (['A', 'B'] as $teamCode) {
            $head = $this->permissionService->getTeamHead($teamCode);
            if (!$head) {
                continue;
            }

            $submitted = ProductionReport::where('report_type', ProductionReport::TYPE_WEEKLY)
                ->where('team_code', $teamCode)
                ->whereDate('reporting_start_date', $startDate)
                ->whereIn('status', [
                    ProductionReport::STATUS_SUBMITTED,
                    ProductionReport::STATUS_SUBMITTED_LATE,
                    ProductionReport::STATUS_APPROVED,
                ])
                ->exists();

            if ($submitted) {
                continue;
            }

            $dedupeKey = "pr_weekly_final_rem_{$startDate}_team_{$teamCode}";
            if (Cache::has($dedupeKey)) {
                continue;
            }

            $this->reportService->sendSafeNotification($head, [
                'module' => 'digital',
                'title' => "URGENT: Team {$teamCode} Weekly Report Due at 8:00 AM",
                'message' => "Final reminder: Team {$teamCode} Weekly Production Report ({$startDate} to {$endDate}) is due today (Saturday) before 8:00 AM Cambodia time.",
                'actor_name' => 'Production Report Scheduler',
                'link' => route('production-reports.weekly.create', ['team' => $teamCode, 'start_date' => $startDate, 'end_date' => $endDate]),
            ]);

            Cache::put($dedupeKey, true, now()->addHours(24));
        }

        $this->info('Weekly Saturday 7:00 AM final reminders processed.');
    }

    /**
     * 5. Saturday after 8:00 AM Cambodia: Mark unsubmitted weekly reports overdue and alert Supervisor Somalika.
     */
    protected function processWeeklyOverdue(Carbon $now): void
    {
        $period = $this->reportService->getDefaultWeeklyPeriod($now->copy()->subDay());
        $startDate = $period['start_date'];
        $endDate = $period['end_date'];

        foreach (['A', 'B'] as $teamCode) {
            $report = ProductionReport::where('report_type', ProductionReport::TYPE_WEEKLY)
                ->where('team_code', $teamCode)
                ->whereDate('reporting_start_date', $startDate)
                ->first();

            if ($report && $report->isSubmittedOrApproved()) {
                continue;
            }

            if ($report) {
                $report->update(['is_late' => true]);
            }

            $dedupeKey = "pr_weekly_overdue_{$startDate}_team_{$teamCode}";
            if (!Cache::has($dedupeKey)) {
                $head = $this->permissionService->getTeamHead($teamCode);
                $somalika = User::whereRaw('LOWER(username) = ?', ['somalika'])->first();
                $msg = "Team {$teamCode} Weekly Production Report ({$startDate} – {$endDate}) is overdue past the Saturday 8:00 AM deadline.";

                if ($head) {
                    $this->reportService->sendSafeNotification($head, [
                        'module' => 'digital',
                        'title' => "OVERDUE: Team {$teamCode} Weekly Report",
                        'message' => $msg,
                        'actor_name' => 'Production Report Scheduler',
                        'link' => route('production-reports.weekly.create', ['team' => $teamCode, 'start_date' => $startDate, 'end_date' => $endDate]),
                    ]);
                }
                if ($somalika) {
                    $this->reportService->sendSafeNotification($somalika, [
                        'module' => 'digital',
                        'title' => "OVERDUE: Team {$teamCode} Weekly Report",
                        'message' => $msg,
                        'actor_name' => 'Production Report Scheduler',
                        'link' => route('production-reports.index'),
                    ]);
                }

                Cache::put($dedupeKey, true, now()->addHours(24));
            }
        }

        $this->info('Weekly overdue check completed.');
    }

    /**
     * 6. Monthly draft preparation from daily reports for each production member.
     */
    protected function processMonthlyDraftPreparation(Carbon $now): void
    {
        $year = (int) $now->year;
        $month = (int) $now->month;

        $members = $this->permissionService->getTeamMembers('A')
            ->concat($this->permissionService->getTeamMembers('B'))
            ->unique('id');

        foreach ($members as $member) {
            $existing = ProductionReport::where('user_id', $member->id)
                ->where('report_type', ProductionReport::TYPE_MONTHLY)
                ->where('reporting_year', $year)
                ->where('reporting_month', $month)
                ->first();

            if (!$existing) {
                $draft = $this->reportService->saveOrSubmitMonthlyReport($member, [
                    'reporting_year' => $year,
                    'reporting_month' => $month,
                    'revision_notes' => 'Auto-compiled monthly production report draft from daily submissions.',
                ], [], false);

                $this->reportService->sendSafeNotification($member, [
                    'module' => 'digital',
                    'title' => 'Monthly Production Report Draft Ready',
                    'message' => "Your Monthly Production Report draft for {$draft->period_label} has been auto-compiled from your daily reports. Please review, select your best posters/videos, add next month's goals, and submit.",
                    'actor_name' => 'Production Report Scheduler',
                    'link' => route('production-reports.monthly.create', ['year' => $year, 'month' => $month]),
                ]);
            }
        }

        $this->info('Monthly draft preparation completed.');
    }

    /**
     * 7. Monthly deadline reminder.
     */
    protected function processMonthlyReminders(Carbon $now): void
    {
        $year = (int) $now->year;
        $month = (int) $now->month;

        $members = $this->permissionService->getTeamMembers('A')
            ->concat($this->permissionService->getTeamMembers('B'))
            ->unique('id');

        foreach ($members as $member) {
            $submitted = ProductionReport::where('user_id', $member->id)
                ->where('report_type', ProductionReport::TYPE_MONTHLY)
                ->where('reporting_year', $year)
                ->where('reporting_month', $month)
                ->whereIn('status', [
                    ProductionReport::STATUS_SUBMITTED,
                    ProductionReport::STATUS_SUBMITTED_LATE,
                    ProductionReport::STATUS_APPROVED,
                ])
                ->exists();

            if ($submitted) {
                continue;
            }

            $dedupeKey = "pr_monthly_rem_{$year}_{$month}_u{$member->id}";
            if (!Cache::has($dedupeKey)) {
                $this->reportService->sendSafeNotification($member, [
                    'module' => 'digital',
                    'title' => 'Monthly Production Report Deadline Reminder',
                    'message' => "Reminder: Please finalize and submit your Monthly Production Report for {$now->format('F Y')}.",
                    'actor_name' => 'Production Report Scheduler',
                    'link' => route('production-reports.monthly.create', ['year' => $year, 'month' => $month]),
                ]);
                Cache::put($dedupeKey, true, now()->addHours(24));
            }
        }

        $this->info('Monthly deadline reminders processed.');
    }

    /**
     * 8. Monthly overdue check.
     */
    protected function processMonthlyOverdue(Carbon $now): void
    {
        $year = (int) $now->year;
        $month = (int) $now->month;
        $deadline = $this->reportService->calculateMonthlyDeadline($year, $month);

        if ($now->lte($deadline)) {
            return;
        }

        $members = $this->permissionService->getTeamMembers('A')
            ->concat($this->permissionService->getTeamMembers('B'))
            ->unique('id');

        foreach ($members as $member) {
            $report = ProductionReport::where('user_id', $member->id)
                ->where('report_type', ProductionReport::TYPE_MONTHLY)
                ->where('reporting_year', $year)
                ->where('reporting_month', $month)
                ->first();

            if ($report && !$report->isSubmittedOrApproved()) {
                $report->update(['is_late' => true]);
            }
        }

        $this->info('Monthly overdue check completed.');
    }
}

