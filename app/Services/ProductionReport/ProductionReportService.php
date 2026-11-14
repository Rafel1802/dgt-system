<?php

namespace App\Services\ProductionReport;

use App\Models\ProductionReport\ProductionReport;
use App\Models\ProductionReport\ProductionReportDeadline;
use App\Models\ProductionReport\ProductionReportItem;
use App\Models\ProductionReport\ProductionReportMedia;
use App\Models\ProductionReport\ProductionReportRevision;
use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductionReportService
{
    public const TIMEZONE = 'Asia/Phnom_Penh';

    public const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp', 'pdf', 'mp4', 'mov', 'webm', 'ppt', 'pptx', 'doc', 'docx', 'xlsx', 'csv',
    ];

    /** A Monthly (member or team) report must carry at least one of these documents. */
    public const DOCUMENT_EXTENSIONS = ['pdf', 'ppt', 'pptx', 'doc', 'docx'];

    public const ALLOWED_MIME_PREFIXES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
        'application/pdf',
        'video/mp4', 'video/quicktime', 'video/webm',
        'application/vnd.openxmlformats-officedocument',
        'text/csv', 'text/plain',
    ];

    public function __construct(
        private readonly ProductionReportPermissionService $permissionService
    ) {}

    public function nowCambodia(): Carbon
    {
        return Carbon::now(self::TIMEZONE);
    }

    /**
     * Daily deadline: Before 5:00 PM Cambodia time on the reporting date.
     */
    public function calculateDailyDeadline(string|Carbon $reportingDate): Carbon
    {
        $cfg = ProductionReportDeadline::current();
        $tz = $cfg->timezone ?: self::TIMEZONE;
        $time = $cfg->daily_deadline_time ?: '17:00';
        $dateStr = $reportingDate instanceof Carbon ? $reportingDate->toDateString() : Carbon::parse($reportingDate, $tz)->toDateString();

        return Carbon::parse("{$dateStr} {$time}:00", $tz);
    }

    /**
     * Weekly deadline: Every Saturday before 8:00 AM Cambodia time following the reporting week.
     */
    public function calculateWeeklyDeadline(string|Carbon $periodStart, string|Carbon $periodEnd): Carbon
    {
        $cfg = ProductionReportDeadline::current();
        $tz = $cfg->timezone ?: self::TIMEZONE;
        $time = $cfg->weekly_deadline_time ?: '08:00';
        $end = $periodEnd instanceof Carbon ? $periodEnd->copy()->setTimezone($tz) : Carbon::parse($periodEnd, $tz);

        // If period ends on Friday, Saturday is the next day; if Saturday, same day; otherwise next Saturday
        $saturday = $end->isSaturday() ? $end->copy() : $end->copy()->next(Carbon::SATURDAY);
        $dateStr = $saturday->toDateString();

        return Carbon::parse("{$dateStr} {$time}:00", $tz);
    }

    /**
     * Monthly deadline: Configurable (default last day of the reporting month at 5:00 PM Cambodia time).
     */
    public function calculateMonthlyDeadline(int $year, int $month): Carbon
    {
        $cfg = ProductionReportDeadline::current();
        $tz = $cfg->timezone ?: self::TIMEZONE;
        $time = $cfg->monthly_deadline_time ?: '17:00';
        $mode = $cfg->monthly_deadline_mode ?: 'last_day_of_month';

        $base = Carbon::createFromDate($year, $month, 1, $tz);
        if ($mode === 'day_of_next_month') {
            $day = max(1, min(28, (int) ($cfg->monthly_deadline_day ?: 5)));
            $target = $base->copy()->addMonth()->day($day);
        } elseif ($mode === 'specific_day') {
            $day = max(1, min($base->daysInMonth, (int) ($cfg->monthly_deadline_day ?: 28)));
            $target = $base->copy()->day($day);
        } else {
            $target = $base->copy()->endOfMonth();
        }

        return Carbon::parse($target->toDateString() . " {$time}:00", $tz);
    }

    /**
     * Resolve default weekly period (Monday to Friday of current week, or previous week if early Monday).
     */
    public function getDefaultWeeklyPeriod(?Carbon $reference = null): array
    {
        $now = ($reference ?: $this->nowCambodia())->copy()->setTimezone(self::TIMEZONE);
        $monday = $now->copy()->startOfWeek(Carbon::MONDAY);
        $friday = $monday->copy()->addDays(4);

        return [
            'start_date' => $monday->toDateString(),
            'end_date' => $friday->toDateString(),
            'deadline' => $this->calculateWeeklyDeadline($monday, $friday),
        ];
    }

    /**
     * Determine whether a submission at $submittedAt is on time or late against $deadline.
     */
    public function evaluateSubmissionTiming(Carbon $deadline, ?Carbon $submittedAt = null): array
    {
        $actual = ($submittedAt ?: $this->nowCambodia())->copy()->setTimezone(self::TIMEZONE);
        $deadlineKh = $deadline->copy()->setTimezone(self::TIMEZONE);

        $isLate = $actual->gt($deadlineKh);

        return [
            'status' => $isLate ? ProductionReport::STATUS_SUBMITTED_LATE : ProductionReport::STATUS_SUBMITTED,
            'is_late' => $isLate,
            'submitted_at' => $actual,
            'deadline_at' => $deadlineKh,
        ];
    }

    /**
     * Create or update a Daily Report (Draft or Submit).
     */
    public function saveOrSubmitDailyReport(
        User $user,
        array $data,
        array $uploadedFiles = [],
        bool $submit = false,
        ?ProductionReport $existingReport = null
    ): ProductionReport {
        if (!$this->permissionService->canSubmitDaily($user)) {
            abort(403, 'You are not authorized to submit daily production reports.');
        }

        $tz = self::TIMEZONE;
        $reportingDate = Carbon::parse($data['reporting_date'] ?? $this->nowCambodia()->toDateString(), $tz)->toDateString();
        $teamCode = $this->permissionService->getAssignedTeamCode($user) ?: 'A';
        $teamName = $this->permissionService->getTeamName($teamCode);

        $report = $existingReport ?: ProductionReport::withTrashed()
            ->where('user_id', $user->id)
            ->where('report_type', ProductionReport::TYPE_DAILY)
            ->whereDate('reporting_date', $reportingDate)
            ->first();

        if ($report && $report->trashed()) {
            $report->restore();
        }

        if ($report) {
            if ((int) $report->user_id !== (int) $user->id) {
                abort(403, 'You cannot modify another member\'s daily report.');
            }
            if (!$report->isEditableByAuthor()) {
                throw ValidationException::withMessages([
                    'reporting_date' => 'This daily report has already been submitted and locked. Please request a reopen or correction if changes are needed.',
                ]);
            }
        }

        $deadline = $this->calculateDailyDeadline($reportingDate);
        $previousStatus = $report?->status;

        return DB::transaction(function () use ($user, $data, $uploadedFiles, $submit, $report, $reportingDate, $teamCode, $teamName, $deadline, $previousStatus) {
            $newStatus = ProductionReport::STATUS_DRAFT;
            $isLate = false;
            $submittedAt = $report?->submitted_at;
            $submittedBy = $report?->submitted_by;

            if ($submit) {
                $timing = $this->evaluateSubmissionTiming($deadline);
                $newStatus = $timing['status'];
                $isLate = $timing['is_late'];
                $submittedAt = $timing['submitted_at'];
                $submittedBy = $user->id;
            }

            $links = $this->normalizeLinks($data['deliverable_links'] ?? []);
            $dateFormatted = Carbon::parse($reportingDate)->format('M d, Y');

            $attributes = [
                'report_type' => ProductionReport::TYPE_DAILY,
                'user_id' => $user->id,
                'team_code' => $teamCode,
                'team_name' => $teamName,
                'reporting_date' => $reportingDate,
                'reporting_start_date' => $reportingDate,
                'reporting_end_date' => $reportingDate,
                'reporting_month' => (int) Carbon::parse($reportingDate)->month,
                'reporting_year' => (int) Carbon::parse($reportingDate)->year,
                'project_category' => $data['project_category'] ?? 'General Production',
                'title' => $data['title'] ?? "{$user->name} — Daily Production Report ({$dateFormatted})",
                'executive_summary' => $data['executive_summary'] ?? null,
                'tasks_completed' => $data['tasks_completed'] ?? null,
                'work_in_progress' => $data['work_in_progress'] ?? null,
                'pending_tasks' => $data['pending_tasks'] ?? null,
                'blockers' => $data['blockers'] ?? null,
                'achievements' => $data['achievements'] ?? null,
                'deliverable_links' => $links,
                'next_priorities' => $data['next_priorities'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => $newStatus,
                'is_late' => $isLate,
                'deadline_at' => $deadline,
                'submitted_at' => $submittedAt,
                'submitted_by' => $submittedBy,
            ];

            if (!$report) {
                $attributes['report_code'] = $this->generateReportCode('DAILY', $reportingDate, $user->id, $teamCode);
                $report = ProductionReport::create($attributes);
            } else {
                $report->update($attributes);
            }

            // Sync measurable task items if provided
            if (array_key_exists('items', $data) && is_array($data['items'])) {
                $this->syncReportItems($report, $user, $data['items'], $reportingDate);
            }

            // Process uploaded media files & external media entries
            $this->processMediaUploads($report, $user, $uploadedFiles, $data['media_meta'] ?? [], $data['external_media'] ?? []);

            // Record revision snapshot
            $action = $submit
                ? ($isLate ? 'submitted_late' : 'submitted')
                : ($previousStatus ? 'draft_saved' : 'created');

            $this->recordRevision($report, $user, $action, $previousStatus, $newStatus, $data['revision_notes'] ?? null);

            if ($submit) {
                $this->notifyDailySubmitted($report);
            }

            return $report->fresh(['items', 'media', 'revisions', 'user']);
        });
    }

    /**
     * Compile all team members' daily reports for a selected team and date range into structured weekly sections.
     * Never invents task quantities or statistics.
     */
    public function compileWeeklyTeamData(string $teamCode, string $startDate, string $endDate): array
    {
        $code = strtoupper(trim($teamCode));
        $start = Carbon::parse($startDate, self::TIMEZONE)->startOfDay();
        $end = Carbon::parse($endDate, self::TIMEZONE)->endOfDay();

        $teamMembers = $this->permissionService->getTeamMembers($code);
        $teamHead = $this->permissionService->getTeamHead($code);
        $memberIds = $teamMembers->pluck('id')->toArray();

        // Expected working days in period (Mon..Sat or Mon..Fri within range, excluding Sundays)
        $expectedDates = [];
        foreach (CarbonPeriod::create($start->toDateString(), $end->toDateString()) as $day) {
            if (!$day->isSunday()) {
                $expectedDates[] = $day->toDateString();
            }
        }
        if (empty($expectedDates)) {
            $expectedDates[] = $start->toDateString();
        }

        // Fetch all daily reports for these members in the period
        $dailyReports = ProductionReport::with(['user', 'items', 'media'])
            ->where('report_type', ProductionReport::TYPE_DAILY)
            ->where(function ($q) use ($code, $memberIds) {
                $q->where('team_code', $code);
                if (!empty($memberIds)) {
                    $q->orWhereIn('user_id', $memberIds);
                }
            })
            ->whereBetween('reporting_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', [
                ProductionReport::STATUS_SUBMITTED,
                ProductionReport::STATUS_SUBMITTED_LATE,
                ProductionReport::STATUS_APPROVED,
            ])
            ->orderBy('reporting_date')
            ->get();

        // Also check drafts/not submitted
        $draftReports = ProductionReport::where('report_type', ProductionReport::TYPE_DAILY)
            ->whereIn('user_id', $memberIds ?: [0])
            ->whereBetween('reporting_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', [ProductionReport::STATUS_DRAFT, ProductionReport::STATUS_RETURNED])
            ->get();

        $memberSummaryTable = [];
        $missingReports = [];
        $lateReportsCount = $dailyReports->where('is_late', true)->count();

        $allCompletedLines = [];
        $allWipLines = [];
        $allPendingLines = [];
        $allBlockerLines = [];
        $allAchievementLines = [];
        $outputByCategory = [];
        $totalMeasurableQuantity = 0;
        $totalCompletedItemsCount = 0;

        foreach ($teamMembers as $member) {
            $mReports = $dailyReports->where('user_id', $member->id);
            $submittedDateStrings = $mReports->map(fn ($r) => $r->reporting_date?->toDateString())->filter()->unique()->values()->toArray();
            $missingDatesForMember = array_values(array_diff($expectedDates, $submittedDateStrings));

            foreach ($missingDatesForMember as $mDate) {
                $hasDraft = $draftReports->first(fn ($d) => (int) $d->user_id === (int) $member->id && $d->reporting_date?->toDateString() === $mDate);
                $missingReports[] = [
                    'user_id' => $member->id,
                    'member_name' => $member->name,
                    'username' => $member->username,
                    'date' => $mDate,
                    'date_formatted' => Carbon::parse($mDate)->format('D, M d'),
                    'state' => $hasDraft ? 'Draft (Not Submitted)' : 'Missing',
                ];
            }

            $memberCompletedTexts = [];
            $memberWipTexts = [];
            $memberPendingTexts = [];
            $memberAchievements = [];
            $memberCompletedCount = 0;
            $memberMeasurableQty = 0;

            foreach ($mReports as $rep) {
                $dLabel = $rep->reporting_date ? $rep->reporting_date->format('M d') : '';
                if (filled($rep->tasks_completed)) {
                    $memberCompletedTexts[] = "[{$dLabel}] " . trim($rep->tasks_completed);
                    $allCompletedLines[] = "• {$member->name} ({$dLabel}): " . trim($rep->tasks_completed);
                }
                if (filled($rep->work_in_progress)) {
                    $memberWipTexts[] = trim($rep->work_in_progress);
                    $allWipLines[] = "• {$member->name} ({$dLabel}): " . trim($rep->work_in_progress);
                }
                if (filled($rep->pending_tasks)) {
                    $memberPendingTexts[] = trim($rep->pending_tasks);
                    $allPendingLines[] = "• {$member->name} ({$dLabel}): " . trim($rep->pending_tasks);
                }
                if (filled($rep->blockers)) {
                    $allBlockerLines[] = "• {$member->name} ({$dLabel}): " . trim($rep->blockers);
                }
                if (filled($rep->achievements)) {
                    $memberAchievements[] = trim($rep->achievements);
                    $allAchievementLines[] = "• {$member->name} ({$dLabel}): " . trim($rep->achievements);
                }

                foreach ($rep->items as $item) {
                    $cat = $item->task_category ?: ($rep->project_category ?: 'General');
                    $qty = (float) ($item->quantity ?: 1);
                    if ($item->status === 'completed') {
                        $memberCompletedCount++;
                        $memberMeasurableQty += $qty;
                        $totalCompletedItemsCount++;
                        $totalMeasurableQuantity += $qty;
                        $outputByCategory[$cat] = ($outputByCategory[$cat] ?? 0) + $qty;
                    }
                }
            }

            // If a member typed tasks_completed text without structured rows, count non-empty lines as submitted task lines
            if ($memberCompletedCount === 0 && !empty($memberCompletedTexts)) {
                foreach ($mReports as $rep) {
                    $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $rep->tasks_completed)));
                    $lineCount = count($lines);
                    $memberCompletedCount += $lineCount;
                    $totalCompletedItemsCount += $lineCount;
                    $cat = $rep->project_category ?: 'General Production';
                    if ($lineCount > 0) {
                        $outputByCategory[$cat] = ($outputByCategory[$cat] ?? 0) + $lineCount;
                    }
                }
            }

            $memberSummaryTable[] = [
                'user_id' => $member->id,
                'member_name' => $member->name,
                'username' => $member->username,
                'role_title' => $member->team_role ?: 'Production Member',
                'submitted_days' => count($submittedDateStrings),
                'expected_days' => count($expectedDates),
                'missing_days' => count($missingDatesForMember),
                'late_days' => $mReports->where('is_late', true)->count(),
                'completed_tasks_count' => $memberCompletedCount,
                'measurable_quantity' => $memberMeasurableQty,
                'completed_tasks_summary' => !empty($memberCompletedTexts) ? implode("\n", $memberCompletedTexts) : 'No submitted tasks in period',
                'work_in_progress' => !empty($memberWipTexts) ? implode(" | ", array_unique($memberWipTexts)) : '—',
                'pending_tasks' => !empty($memberPendingTexts) ? implode(" | ", array_unique($memberPendingTexts)) : '—',
                'key_achievements' => !empty($memberAchievements) ? implode(" | ", array_unique($memberAchievements)) : '—',
            ];
        }

        // Collect all media uploaded in those daily reports so Head can select Best Posters/Graphics & Best Videos
        $allMedia = $dailyReports->flatMap(fn (ProductionReport $r) => $r->media)->values();
        $candidatePosters = $allMedia->filter(fn (ProductionReportMedia $m) => in_array($m->media_type, ['poster', 'graphic', 'screenshot'], true) || $m->isImage())->values();
        $candidateVideos = $allMedia->filter(fn (ProductionReportMedia $m) => $m->media_type === 'video' || $m->isVideo() || in_array(strtolower((string) $m->platform), ['youtube', 'tiktok', 'facebook', 'instagram'], true))->values();

        $expectedTotalSubmissions = count($teamMembers) * count($expectedDates);
        $actualTotalSubmissions = $dailyReports->count();
        $submissionRate = $expectedTotalSubmissions > 0
            ? round(($actualTotalSubmissions / $expectedTotalSubmissions) * 100, 1)
            : 0.0;

        $teamName = $this->permissionService->getTeamName($code);
        $periodFormatted = $start->format('M d, Y') . ' – ' . $end->format('M d, Y');

        $autoExecutiveSummary = "{$teamName} weekly production summary for {$periodFormatted}. "
            . "Out of {$expectedTotalSubmissions} expected daily reports across " . count($teamMembers) . " team members, "
            . "{$actualTotalSubmissions} daily reports were submitted ({$submissionRate}% submission rate, {$lateReportsCount} late, " . count($missingReports) . " missing). "
            . "Total recorded completed deliverables/tasks: {$totalCompletedItemsCount}.";

        return [
            'team_code' => $code,
            'team_name' => $teamName,
            'team_head' => $teamHead ? [
                'id' => $teamHead->id,
                'name' => $teamHead->name,
                'username' => $teamHead->username,
            ] : null,
            'reporting_start_date' => $start->toDateString(),
            'reporting_end_date' => $end->toDateString(),
            'period_formatted' => $periodFormatted,
            'expected_dates' => $expectedDates,
            'team_members_count' => $teamMembers->count(),
            'expected_submissions' => $expectedTotalSubmissions,
            'actual_submissions' => $actualTotalSubmissions,
            'late_submissions' => $lateReportsCount,
            'missing_submissions_count' => count($missingReports),
            'submission_rate' => $submissionRate,
            'total_completed_tasks' => $totalCompletedItemsCount,
            'total_measurable_quantity' => $totalMeasurableQuantity,
            'output_by_category' => $outputByCategory,
            'member_summary_table' => $memberSummaryTable,
            'missing_reports' => $missingReports,
            'compiled_texts' => [
                'executive_summary' => $autoExecutiveSummary,
                'tasks_completed' => !empty($allCompletedLines) ? implode("\n", $allCompletedLines) : 'No daily tasks submitted for this period.',
                'work_in_progress' => !empty($allWipLines) ? implode("\n", $allWipLines) : 'None reported.',
                'pending_tasks' => !empty($allPendingLines) ? implode("\n", $allPendingLines) : 'None reported.',
                'blockers' => !empty($allBlockerLines) ? implode("\n", $allBlockerLines) : 'No blockers reported.',
                'achievements' => !empty($allAchievementLines) ? implode("\n", $allAchievementLines) : 'None reported.',
            ],
            'candidate_posters' => $candidatePosters,
            'candidate_videos' => $candidateVideos,
            'all_daily_reports' => $dailyReports,
        ];
    }

    /**
     * Save Draft or Submit a Weekly Team Report (by Dara for Team A or Kim for Team B).
     */
    public function saveOrSubmitWeeklyReport(
        User $head,
        array $data,
        array $uploadedFiles = [],
        bool $submit = false,
        ?ProductionReport $existingReport = null
    ): ProductionReport {
        $assignedTeam = $this->permissionService->getAssignedTeamCode($head);
        $requestedTeam = strtoupper(trim((string) ($data['team_code'] ?? $assignedTeam)));

        if (!$this->permissionService->canSubmitWeekly($head, $requestedTeam)) {
            abort(403, 'You are only authorized to create or submit weekly reports for your assigned production team.');
        }

        if ($submit) {
            $rating = isset($data['performance_rating']) ? (int) $data['performance_rating'] : 0;
            if ($rating < 1 || $rating > 5) {
                throw ValidationException::withMessages([
                    'performance_rating' => 'Team Performance Rating (1 to 5) is required before submitting a Weekly Team Report.',
                ]);
            }
        }

        $startDate = Carbon::parse($data['reporting_start_date'], self::TIMEZONE)->toDateString();
        $endDate = Carbon::parse($data['reporting_end_date'], self::TIMEZONE)->toDateString();
        $compiled = $this->compileWeeklyTeamData($requestedTeam, $startDate, $endDate);

        $report = $existingReport ?: ProductionReport::withTrashed()
            ->where('report_type', ProductionReport::TYPE_WEEKLY)
            ->where('team_code', $requestedTeam)
            ->whereDate('reporting_start_date', $startDate)
            ->whereDate('reporting_end_date', $endDate)
            ->first();

        if ($report && $report->trashed()) {
            $report->restore();
        }

        if ($report) {
            if (strtoupper((string) $report->team_code) !== $assignedTeam) {
                abort(403, 'Cross-team access is strictly prohibited.');
            }
            if (!$report->isEditableByAuthor()) {
                throw ValidationException::withMessages([
                    'reporting_start_date' => 'This weekly team report has already been submitted. Reopen it first if corrections are needed.',
                ]);
            }
        }

        $deadline = $this->calculateWeeklyDeadline($startDate, $endDate);
        $previousStatus = $report?->status;

        return DB::transaction(function () use ($head, $data, $uploadedFiles, $submit, $report, $requestedTeam, $startDate, $endDate, $compiled, $deadline, $previousStatus) {
            $newStatus = ProductionReport::STATUS_DRAFT;
            $isLate = false;
            $submittedAt = $report?->submitted_at;
            $submittedBy = $report?->submitted_by;

            if ($submit) {
                $timing = $this->evaluateSubmissionTiming($deadline);
                $newStatus = $timing['status'];
                $isLate = $timing['is_late'];
                $submittedAt = $timing['submitted_at'];
                $submittedBy = $head->id;
            }

            $rating = !empty($data['performance_rating']) ? (int) $data['performance_rating'] : null;
            $ratingLabel = $rating ? (ProductionReport::RATING_LABELS[$rating] ?? null) : null;

            $compiledSummaryPayload = [
                'expected_dates' => $compiled['expected_dates'],
                'team_members_count' => $compiled['team_members_count'],
                'expected_submissions' => $compiled['expected_submissions'],
                'actual_submissions' => $compiled['actual_submissions'],
                'late_submissions' => $compiled['late_submissions'],
                'missing_submissions_count' => $compiled['missing_submissions_count'],
                'submission_rate' => $compiled['submission_rate'],
                'total_completed_tasks' => $compiled['total_completed_tasks'],
                'total_measurable_quantity' => $compiled['total_measurable_quantity'],
                'output_by_category' => $compiled['output_by_category'],
                'member_summary_table' => $compiled['member_summary_table'],
                'missing_reports' => $compiled['missing_reports'],
                'selected_poster_media_ids' => array_map('intval', (array) ($data['selected_poster_ids'] ?? [])),
                'selected_video_media_ids' => array_map('intval', (array) ($data['selected_video_ids'] ?? [])),
                'health_status' => in_array($data['health_status'] ?? null, ['on_track', 'at_risk', 'blocked'], true)
                    ? $data['health_status']
                    : 'on_track',
            ];

            $attributes = [
                'report_type' => ProductionReport::TYPE_WEEKLY,
                'user_id' => $head->id,
                'team_code' => $requestedTeam,
                'team_name' => $compiled['team_name'],
                'reporting_date' => $endDate,
                'reporting_start_date' => $startDate,
                'reporting_end_date' => $endDate,
                'reporting_month' => (int) Carbon::parse($endDate)->month,
                'reporting_year' => (int) Carbon::parse($endDate)->year,
                'project_category' => 'Weekly Team Production',
                'title' => $data['title'] ?? "Team {$requestedTeam} Weekly Production Report ({$compiled['period_formatted']})",
                'executive_summary' => $data['executive_summary'] ?? $compiled['compiled_texts']['executive_summary'],
                'tasks_completed' => $data['tasks_completed'] ?? $compiled['compiled_texts']['tasks_completed'],
                'work_in_progress' => $data['work_in_progress'] ?? $compiled['compiled_texts']['work_in_progress'],
                'pending_tasks' => $data['pending_tasks'] ?? $compiled['compiled_texts']['pending_tasks'],
                'blockers' => $data['blockers'] ?? $compiled['compiled_texts']['blockers'],
                'achievements' => $data['achievements'] ?? $compiled['compiled_texts']['achievements'],
                'deliverable_links' => $this->normalizeLinks($data['deliverable_links'] ?? []),
                'next_priorities' => $data['next_priorities'] ?? null,
                'notes' => $data['notes'] ?? null,
                'performance_rating' => $rating,
                'performance_rating_label' => $ratingLabel,
                'head_comments' => $data['head_comments'] ?? null,
                'member_ratings_json' => $data['member_ratings'] ?? null,
                'compiled_summary_json' => $compiledSummaryPayload,
                'status' => $newStatus,
                'is_late' => $isLate,
                'deadline_at' => $deadline,
                'submitted_at' => $submittedAt,
                'submitted_by' => $submittedBy,
            ];

            if (!$report) {
                $attributes['report_code'] = $this->generateReportCode('WEEKLY', $startDate, $head->id, $requestedTeam);
                $report = ProductionReport::create($attributes);
            } else {
                $report->update($attributes);
            }

            // Sync highlighted media from daily reports into weekly report media
            $this->syncHighlightedMediaForCompiledReport(
                $report,
                $head,
                (array) ($data['selected_poster_ids'] ?? []),
                (array) ($data['selected_video_ids'] ?? [])
            );

            // Process any additional direct uploads by the Head
            $this->processMediaUploads($report, $head, $uploadedFiles, $data['media_meta'] ?? [], $data['external_media'] ?? []);

            $action = $submit
                ? ($isLate ? 'submitted_late' : 'submitted')
                : ($previousStatus ? 'draft_saved' : 'compiled');

            $this->recordRevision($report, $head, $action, $previousStatus, $newStatus, $data['revision_notes'] ?? null);

            if ($submit) {
                $this->notifyWeeklySubmitted($report);
            }

            return $report->fresh(['items', 'media', 'revisions', 'user']);
        });
    }

    /**
     * Automatically compile a member's Monthly Report data from their daily reports for ($year, $month).
     * Never invents missing daily activities.
     */
    public function compileMonthlyMemberData(User $member, int $year, int $month): array
    {
        $start = Carbon::createFromDate($year, $month, 1, self::TIMEZONE)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $now = $this->nowCambodia();
        $checkEnd = $end->gt($now) ? $now->copy()->endOfDay() : $end;

        $expectedDates = [];
        if ($start->lte($checkEnd)) {
            foreach (CarbonPeriod::create($start->toDateString(), $checkEnd->toDateString()) as $day) {
                if (!$day->isSunday()) {
                    $expectedDates[] = $day->toDateString();
                }
            }
        }

        $dailyReports = ProductionReport::with(['items', 'media'])
            ->where('user_id', $member->id)
            ->where('report_type', ProductionReport::TYPE_DAILY)
            ->whereBetween('reporting_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', [
                ProductionReport::STATUS_SUBMITTED,
                ProductionReport::STATUS_SUBMITTED_LATE,
                ProductionReport::STATUS_APPROVED,
            ])
            ->orderBy('reporting_date')
            ->get();

        $submittedDates = $dailyReports->map(fn ($r) => $r->reporting_date?->toDateString())->filter()->unique()->values()->toArray();
        $missingDates = array_values(array_diff($expectedDates, $submittedDates));
        $lateCount = $dailyReports->where('is_late', true)->count();

        $completedLines = [];
        $wipLines = [];
        $pendingLines = [];
        $blockerLines = [];
        $achievementLines = [];
        $outputByCategory = [];
        $totalCompletedItems = 0;
        $totalMeasurableQuantity = 0;
        $allLinks = [];

        foreach ($dailyReports as $rep) {
            $dLabel = $rep->reporting_date ? $rep->reporting_date->format('M d') : '';
            if (filled($rep->tasks_completed)) {
                $completedLines[] = "• [{$dLabel}] " . trim($rep->tasks_completed);
            }
            if (filled($rep->work_in_progress)) {
                $wipLines[] = "• [{$dLabel}] " . trim($rep->work_in_progress);
            }
            if (filled($rep->pending_tasks)) {
                $pendingLines[] = "• [{$dLabel}] " . trim($rep->pending_tasks);
            }
            if (filled($rep->blockers)) {
                $blockerLines[] = "• [{$dLabel}] " . trim($rep->blockers);
            }
            if (filled($rep->achievements)) {
                $achievementLines[] = "• [{$dLabel}] " . trim($rep->achievements);
            }
            if (is_array($rep->deliverable_links)) {
                foreach ($rep->deliverable_links as $lnk) {
                    $allLinks[] = $lnk;
                }
            }

            $repItemCount = 0;
            foreach ($rep->items as $item) {
                $cat = $item->task_category ?: ($rep->project_category ?: 'General');
                $qty = (float) ($item->quantity ?: 1);
                if ($item->status === 'completed') {
                    $repItemCount++;
                    $totalCompletedItems++;
                    $totalMeasurableQuantity += $qty;
                    $outputByCategory[$cat] = ($outputByCategory[$cat] ?? 0) + $qty;
                }
            }

            if ($repItemCount === 0 && filled($rep->tasks_completed)) {
                $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $rep->tasks_completed)));
                $c = count($lines);
                $totalCompletedItems += $c;
                $cat = $rep->project_category ?: 'General Production';
                if ($c > 0) {
                    $outputByCategory[$cat] = ($outputByCategory[$cat] ?? 0) + $c;
                }
            }
        }

        $allMedia = $dailyReports->flatMap(fn (ProductionReport $r) => $r->media)->values();
        $candidatePosters = $allMedia->filter(fn (ProductionReportMedia $m) => in_array($m->media_type, ['poster', 'graphic', 'screenshot'], true) || $m->isImage())->values();
        $candidateVideos = $allMedia->filter(fn (ProductionReportMedia $m) => $m->media_type === 'video' || $m->isVideo() || in_array(strtolower((string) $m->platform), ['youtube', 'tiktok', 'facebook', 'instagram'], true))->values();

        $monthLabel = $start->format('F Y');
        $submissionRate = count($expectedDates) > 0
            ? round((count($submittedDates) / count($expectedDates)) * 100, 1)
            : 0.0;

        $autoSummary = "{$member->name} — Monthly Production Summary for {$monthLabel}. "
            . "Submitted " . count($submittedDates) . " of " . count($expectedDates) . " expected daily reports ({$submissionRate}% attendance/reporting rate, "
            . count($missingDates) . " missing daily report dates). Total completed tasks/deliverables recorded: {$totalCompletedItems}.";

        return [
            'user_id' => $member->id,
            'member_name' => $member->name,
            'year' => $year,
            'month' => $month,
            'month_label' => $monthLabel,
            'reporting_start_date' => $start->toDateString(),
            'reporting_end_date' => $end->toDateString(),
            'expected_days_count' => count($expectedDates),
            'submitted_days_count' => count($submittedDates),
            'late_days_count' => $lateCount,
            'missing_days_count' => count($missingDates),
            'missing_dates' => $missingDates,
            'submission_rate' => $submissionRate,
            'total_completed_tasks' => $totalCompletedItems,
            'total_measurable_quantity' => $totalMeasurableQuantity,
            'output_by_category' => $outputByCategory,
            'compiled_texts' => [
                'executive_summary' => $autoSummary,
                'tasks_completed' => !empty($completedLines) ? implode("\n", $completedLines) : 'No daily reports submitted for this month.',
                'work_in_progress' => !empty($wipLines) ? implode("\n", $wipLines) : 'None reported.',
                'pending_tasks' => !empty($pendingLines) ? implode("\n", $pendingLines) : 'None reported.',
                'blockers' => !empty($blockerLines) ? implode("\n", $blockerLines) : 'No blockers reported.',
                'achievements' => !empty($achievementLines) ? implode("\n", $achievementLines) : 'None reported.',
            ],
            'deliverable_links' => $allLinks,
            'candidate_posters' => $candidatePosters,
            'candidate_videos' => $candidateVideos,
            'daily_reports' => $dailyReports,
        ];
    }

    /**
     * Save Draft or Submit a Monthly Production Report.
     */
    public function saveOrSubmitMonthlyReport(
        User $user,
        array $data,
        array $uploadedFiles = [],
        bool $submit = false,
        ?ProductionReport $existingReport = null
    ): ProductionReport {
        if (!$this->permissionService->canSubmitMonthly($user)) {
            abort(403, 'You are not authorized to submit monthly production reports.');
        }

        $year = (int) ($data['reporting_year'] ?? $this->nowCambodia()->year);
        $month = (int) ($data['reporting_month'] ?? $this->nowCambodia()->month);
        $compiled = $this->compileMonthlyMemberData($user, $year, $month);

        $teamCode = $this->permissionService->getAssignedTeamCode($user) ?: 'A';
        $teamName = $this->permissionService->getTeamName($teamCode);

        $report = $existingReport ?: ProductionReport::withTrashed()
            ->where('user_id', $user->id)
            ->where('report_type', ProductionReport::TYPE_MONTHLY)
            ->where('project_category', '!=', ProductionReport::TEAM_MONTHLY_CATEGORY)
            ->where('reporting_year', $year)
            ->where('reporting_month', $month)
            ->first();

        if ($report && $report->trashed()) {
            $report->restore();
        }

        if ($report) {
            if ((int) $report->user_id !== (int) $user->id) {
                abort(403, 'You cannot modify another member\'s monthly report.');
            }
            if (!$report->isEditableByAuthor()) {
                throw ValidationException::withMessages([
                    'reporting_month' => 'This monthly report has already been submitted. Request a reopen if corrections are needed.',
                ]);
            }
        }

        $deadline = $this->calculateMonthlyDeadline($year, $month);
        $previousStatus = $report?->status;

        if ($submit) {
            $this->assertMonthlyDocumentAttached($report, $uploadedFiles);
        }

        return DB::transaction(function () use ($user, $data, $uploadedFiles, $submit, $report, $year, $month, $compiled, $teamCode, $teamName, $deadline, $previousStatus) {
            $newStatus = ProductionReport::STATUS_DRAFT;
            $isLate = false;
            $submittedAt = $report?->submitted_at;
            $submittedBy = $report?->submitted_by;

            if ($submit) {
                $timing = $this->evaluateSubmissionTiming($deadline);
                $newStatus = $timing['status'];
                $isLate = $timing['is_late'];
                $submittedAt = $timing['submitted_at'];
                $submittedBy = $user->id;
            }

            $compiledSummaryPayload = [
                'expected_days_count' => $compiled['expected_days_count'],
                'submitted_days_count' => $compiled['submitted_days_count'],
                'late_days_count' => $compiled['late_days_count'],
                'missing_days_count' => $compiled['missing_days_count'],
                'missing_dates' => $compiled['missing_dates'],
                'submission_rate' => $compiled['submission_rate'],
                'total_completed_tasks' => $compiled['total_completed_tasks'],
                'total_measurable_quantity' => $compiled['total_measurable_quantity'],
                'output_by_category' => $compiled['output_by_category'],
                'selected_poster_media_ids' => array_map('intval', (array) ($data['selected_poster_ids'] ?? [])),
                'selected_video_media_ids' => array_map('intval', (array) ($data['selected_video_ids'] ?? [])),
            ];

            $attributes = [
                'report_type' => ProductionReport::TYPE_MONTHLY,
                'user_id' => $user->id,
                'team_code' => $teamCode,
                'team_name' => $teamName,
                'reporting_date' => $compiled['reporting_end_date'],
                'reporting_start_date' => $compiled['reporting_start_date'],
                'reporting_end_date' => $compiled['reporting_end_date'],
                'reporting_month' => $month,
                'reporting_year' => $year,
                'project_category' => $data['project_category'] ?? 'Monthly Production Summary',
                'title' => $data['title'] ?? "{$user->name} — Monthly Production Report ({$compiled['month_label']})",
                'executive_summary' => $data['executive_summary'] ?? $compiled['compiled_texts']['executive_summary'],
                'tasks_completed' => $data['tasks_completed'] ?? $compiled['compiled_texts']['tasks_completed'],
                'work_in_progress' => $data['work_in_progress'] ?? $compiled['compiled_texts']['work_in_progress'],
                'pending_tasks' => $data['pending_tasks'] ?? $compiled['compiled_texts']['pending_tasks'],
                'blockers' => $data['blockers'] ?? $compiled['compiled_texts']['blockers'],
                'achievements' => $data['achievements'] ?? $compiled['compiled_texts']['achievements'],
                'deliverable_links' => $this->normalizeLinks($data['deliverable_links'] ?? $compiled['deliverable_links']),
                'next_priorities' => $data['next_priorities'] ?? null,
                'notes' => $data['notes'] ?? null,
                'compiled_summary_json' => $compiledSummaryPayload,
                'status' => $newStatus,
                'is_late' => $isLate,
                'deadline_at' => $deadline,
                'submitted_at' => $submittedAt,
                'submitted_by' => $submittedBy,
            ];

            if (!$report) {
                $attributes['report_code'] = $this->generateReportCode('MONTHLY', $compiled['reporting_start_date'], $user->id, $teamCode);
                $report = ProductionReport::create($attributes);
            } else {
                $report->update($attributes);
            }

            $this->syncHighlightedMediaForCompiledReport(
                $report,
                $user,
                (array) ($data['selected_poster_ids'] ?? []),
                (array) ($data['selected_video_ids'] ?? [])
            );

            $this->processMediaUploads($report, $user, $uploadedFiles, $data['media_meta'] ?? [], $data['external_media'] ?? []);

            $action = $submit
                ? ($isLate ? 'submitted_late' : 'submitted')
                : ($previousStatus ? 'draft_saved' : 'compiled');

            $this->recordRevision($report, $user, $action, $previousStatus, $newStatus, $data['revision_notes'] ?? null);

            if ($submit) {
                $this->notifyMonthlySubmitted($report);
            }

            return $report->fresh(['items', 'media', 'revisions', 'user']);
        });
    }

    /**
     * A monthly report can only be submitted with a PDF / PowerPoint / Word document attached.
     * Checks already-stored attachments plus the files uploaded in this request (before anything is written).
     */
    protected function assertMonthlyDocumentAttached(?ProductionReport $existing, array $uploadedFiles): void
    {
        $hasDoc = false;

        foreach ($uploadedFiles as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: '');
                if (in_array($ext, self::DOCUMENT_EXTENSIONS, true)) {
                    $hasDoc = true;
                    break;
                }
            }
        }

        if (!$hasDoc && $existing) {
            $hasDoc = $existing->media()
                ->whereNotNull('file_path')
                ->get()
                ->contains(fn (ProductionReportMedia $m) => in_array(
                    strtolower(pathinfo((string) ($m->original_filename ?: $m->file_path), PATHINFO_EXTENSION)),
                    self::DOCUMENT_EXTENSIONS,
                    true
                ));
        }

        if (!$hasDoc) {
            throw ValidationException::withMessages([
                'attachments' => 'A monthly report must include a PDF, PowerPoint (PPT/PPTX) or Word (DOCX) document. Please upload one before submitting.',
            ]);
        }
    }

    /** Documents (pdf/ppt/pptx/doc/docx) attached to a report, as light arrays for the UI. */
    public function reportDocuments(ProductionReport $report): array
    {
        return $report->media()->whereNotNull('file_path')->get()
            ->filter(fn (ProductionReportMedia $m) => in_array(
                strtolower(pathinfo((string) ($m->original_filename ?: $m->file_path), PATHINFO_EXTENSION)),
                self::DOCUMENT_EXTENSIONS,
                true
            ))
            ->map(fn (ProductionReportMedia $m) => [
                'id' => $m->id,
                'media_id' => $m->id,
                'title' => $m->title ?: $m->original_filename,
                'filename' => $m->original_filename,
                'extension' => strtolower(pathinfo((string) ($m->original_filename ?: $m->file_path), PATHINFO_EXTENSION)),
                'report_id' => $report->id,
            ])
            ->values()
            ->all();
    }

    /**
     * Combine every member's monthly report (and daily stats) of one team into the Head's Team Monthly view.
     */
    public function compileMonthlyTeamData(string $teamCode, int $year, int $month): array
    {
        $code = strtoupper(trim($teamCode));
        $members = $this->permissionService->getTeamMembers($code);
        $start = Carbon::createFromDate($year, $month, 1, self::TIMEZONE)->startOfMonth();
        $monthLabel = $start->format('F Y');

        $rows = [];
        $docs = [];
        $totalCompleted = 0;
        $submittedCount = 0;
        $completedLines = [];
        $achievementLines = [];
        $blockerLines = [];

        foreach ($members as $member) {
            $memberReport = ProductionReport::with('media')
                ->where('user_id', $member->id)
                ->where('report_type', ProductionReport::TYPE_MONTHLY)
                ->where('project_category', '!=', ProductionReport::TEAM_MONTHLY_CATEGORY)
                ->where('reporting_year', $year)
                ->where('reporting_month', $month)
                ->first();

            $stats = $this->compileMonthlyMemberData($member, $year, $month);
            $isSubmitted = $memberReport && in_array($memberReport->status, [
                ProductionReport::STATUS_SUBMITTED,
                ProductionReport::STATUS_SUBMITTED_LATE,
                ProductionReport::STATUS_APPROVED,
            ], true);

            if ($isSubmitted) {
                $submittedCount++;
            }
            $totalCompleted += (int) $stats['total_completed_tasks'];

            $memberDocs = $memberReport ? $this->reportDocuments($memberReport) : [];
            foreach ($memberDocs as $d) {
                $docs[] = $d + ['member_name' => $member->name];
            }

            if ($memberReport && filled($memberReport->tasks_completed)) {
                $completedLines[] = "▸ {$member->name}:\n" . trim((string) $memberReport->tasks_completed);
            }
            if ($memberReport && filled($memberReport->achievements) && trim($memberReport->achievements) !== 'None reported.') {
                $achievementLines[] = "▸ {$member->name}: " . trim((string) $memberReport->achievements);
            }
            if ($memberReport && filled($memberReport->blockers) && !str_starts_with(trim($memberReport->blockers), 'No blockers')) {
                $blockerLines[] = "▸ {$member->name}: " . trim((string) $memberReport->blockers);
            }

            $rows[] = [
                'user_id' => $member->id,
                'member_name' => $member->name,
                'role_title' => $member->team_role ?: 'Production Member',
                'report_id' => $memberReport?->id,
                'report_status' => $memberReport?->status_badge_label ?? 'Not Started',
                'report_status_color' => $memberReport?->status_badge_color ?? 'slate',
                'is_submitted' => (bool) $isSubmitted,
                'submitted_days' => $stats['submitted_days_count'],
                'expected_days' => $stats['expected_days_count'],
                'missing_days' => $stats['missing_days_count'],
                'completed_tasks' => $stats['total_completed_tasks'],
                'documents' => $memberDocs,
            ];
        }

        $membersCount = $members->count();
        $missingMembers = collect($rows)->reject(fn ($r) => $r['is_submitted'])->pluck('member_name')->values()->all();
        $rate = $membersCount > 0 ? round(($submittedCount / $membersCount) * 100, 1) : 0.0;
        $teamName = $this->permissionService->getTeamName($code);

        $summary = "{$teamName} — Team Monthly Production Summary for {$monthLabel}. "
            . "{$submittedCount} of {$membersCount} members submitted their monthly report ({$rate}%). "
            . "Total completed tasks/deliverables recorded across the team: {$totalCompleted}."
            . (!empty($missingMembers) ? ' Still missing: ' . implode(', ', $missingMembers) . '.' : '');

        return [
            'team_code' => $code,
            'team_name' => $teamName,
            'year' => $year,
            'month' => $month,
            'month_label' => $monthLabel,
            'reporting_start_date' => $start->toDateString(),
            'reporting_end_date' => $start->copy()->endOfMonth()->toDateString(),
            'members_count' => $membersCount,
            'submitted_members_count' => $submittedCount,
            'missing_members' => $missingMembers,
            'submission_rate' => $rate,
            'total_completed_tasks' => $totalCompleted,
            'member_rows' => $rows,
            'member_documents' => $docs,
            'compiled_texts' => [
                'executive_summary' => $summary,
                'tasks_completed' => !empty($completedLines) ? implode("\n\n", $completedLines) : 'No member monthly reports submitted yet.',
                'achievements' => !empty($achievementLines) ? implode("\n", $achievementLines) : 'None reported.',
                'blockers' => !empty($blockerLines) ? implode("\n", $blockerLines) : 'No blockers reported.',
            ],
        ];
    }

    /**
     * Head compiles & submits the combined Team Monthly report (Head → Supervisor).
     */
    public function saveOrSubmitTeamMonthlyReport(
        User $head,
        array $data,
        array $uploadedFiles = [],
        bool $submit = false,
        ?ProductionReport $existingReport = null
    ): ProductionReport {
        $assignedTeam = $this->permissionService->getAssignedTeamCode($head);
        $teamCode = strtoupper(trim((string) ($data['team_code'] ?? $assignedTeam)));

        if (!$this->permissionService->canSubmitTeamMonthly($head, $teamCode)) {
            abort(403, 'Only the Head of Production of this team can submit its Team Monthly report.');
        }

        $year = (int) ($data['reporting_year'] ?? $this->nowCambodia()->year);
        $month = (int) ($data['reporting_month'] ?? $this->nowCambodia()->month);
        $compiled = $this->compileMonthlyTeamData($teamCode, $year, $month);

        $report = $existingReport ?: ProductionReport::withTrashed()
            ->where('report_type', ProductionReport::TYPE_MONTHLY)
            ->where('project_category', ProductionReport::TEAM_MONTHLY_CATEGORY)
            ->where('team_code', $teamCode)
            ->where('reporting_year', $year)
            ->where('reporting_month', $month)
            ->first();

        if ($report && $report->trashed()) {
            $report->restore();
        }

        if ($report) {
            if (strtoupper((string) $report->team_code) !== $assignedTeam) {
                abort(403, 'Cross-team access is strictly prohibited.');
            }
            if (!$report->isEditableByAuthor()) {
                throw ValidationException::withMessages([
                    'reporting_month' => 'This Team Monthly report has already been submitted. Ask the Supervisor to return it if corrections are needed.',
                ]);
            }
        }

        if ($submit) {
            $rating = isset($data['performance_rating']) ? (int) $data['performance_rating'] : 0;
            if ($rating < 1 || $rating > 5) {
                throw ValidationException::withMessages([
                    'performance_rating' => 'Team Performance Rating (1 to 5) is required before submitting the Team Monthly report.',
                ]);
            }
            $this->assertMonthlyDocumentAttached($report, $uploadedFiles);
        }

        $deadline = $this->calculateMonthlyDeadline($year, $month);
        $previousStatus = $report?->status;

        return DB::transaction(function () use ($head, $data, $uploadedFiles, $submit, $report, $teamCode, $year, $month, $compiled, $deadline, $previousStatus) {
            $newStatus = ProductionReport::STATUS_DRAFT;
            $isLate = false;
            $submittedAt = $report?->submitted_at;
            $submittedBy = $report?->submitted_by;

            if ($submit) {
                $timing = $this->evaluateSubmissionTiming($deadline);
                $newStatus = $timing['status'];
                $isLate = $timing['is_late'];
                $submittedAt = $timing['submitted_at'];
                $submittedBy = $head->id;
            }

            $rating = !empty($data['performance_rating']) ? (int) $data['performance_rating'] : null;

            $summaryPayload = [
                'scope' => 'team',
                'members_count' => $compiled['members_count'],
                'submitted_members_count' => $compiled['submitted_members_count'],
                'missing_members' => $compiled['missing_members'],
                'submission_rate' => $compiled['submission_rate'],
                'total_completed_tasks' => $compiled['total_completed_tasks'],
                'member_rows' => $compiled['member_rows'],
                'member_documents' => $compiled['member_documents'],
                'health_status' => in_array($data['health_status'] ?? null, ['on_track', 'at_risk', 'blocked'], true) ? $data['health_status'] : 'on_track',
            ];

            $attributes = [
                'report_type' => ProductionReport::TYPE_MONTHLY,
                'user_id' => $head->id,
                'team_code' => $teamCode,
                'team_name' => $compiled['team_name'],
                'reporting_date' => $compiled['reporting_end_date'],
                'reporting_start_date' => $compiled['reporting_start_date'],
                'reporting_end_date' => $compiled['reporting_end_date'],
                'reporting_month' => $month,
                'reporting_year' => $year,
                'project_category' => ProductionReport::TEAM_MONTHLY_CATEGORY,
                'title' => $data['title'] ?? "{$compiled['team_name']} — Team Monthly Production Report ({$compiled['month_label']})",
                'executive_summary' => $data['executive_summary'] ?? $compiled['compiled_texts']['executive_summary'],
                'tasks_completed' => $data['tasks_completed'] ?? $compiled['compiled_texts']['tasks_completed'],
                'work_in_progress' => $data['work_in_progress'] ?? null,
                'pending_tasks' => $data['pending_tasks'] ?? null,
                'blockers' => $data['blockers'] ?? $compiled['compiled_texts']['blockers'],
                'achievements' => $data['achievements'] ?? $compiled['compiled_texts']['achievements'],
                'next_priorities' => $data['next_priorities'] ?? null,
                'notes' => $data['notes'] ?? null,
                'head_comments' => $data['head_comments'] ?? null,
                'performance_rating' => $rating,
                'performance_rating_label' => $rating ? (ProductionReport::RATING_LABELS[$rating] ?? null) : null,
                'compiled_summary_json' => $summaryPayload,
                'status' => $newStatus,
                'is_late' => $isLate,
                'deadline_at' => $deadline,
                'submitted_at' => $submittedAt,
                'submitted_by' => $submittedBy,
            ];

            if (!$report) {
                $attributes['report_code'] = $this->generateReportCode('TMONTHLY', $compiled['reporting_start_date'], $head->id, $teamCode);
                $report = ProductionReport::create($attributes);
            } else {
                $report->update($attributes);
            }

            $this->processMediaUploads($report, $head, $uploadedFiles, $data['media_meta'] ?? [], $data['external_media'] ?? []);

            $action = $submit
                ? ($isLate ? 'submitted_late' : 'submitted')
                : ($previousStatus ? 'draft_saved' : 'compiled');

            $this->recordRevision($report, $head, $action, $previousStatus, $newStatus, $data['revision_notes'] ?? null);

            if ($submit) {
                $this->notifyTeamMonthlySubmitted($report);
            }

            return $report->fresh(['items', 'media', 'revisions', 'user']);
        });
    }

    /**
     * Return a submitted report for correction (by Head or Supervisor).
     */
    public function returnForCorrection(User $actor, ProductionReport $report, string $reason): ProductionReport
    {
        if (!$this->permissionService->canReviewOrReturnReport($actor, $report)) {
            abort(403, 'You are not authorized to return this report for correction.');
        }

        $previousStatus = $report->status;
        $report->update([
            'status' => ProductionReport::STATUS_RETURNED,
            'returned_reason' => $reason,
            'reviewed_by' => $actor->id,
            'reviewed_at' => $this->nowCambodia(),
        ]);

        $this->recordRevision($report, $actor, 'returned_for_correction', $previousStatus, ProductionReport::STATUS_RETURNED, $reason);

        if ($report->user && (int) $report->user_id !== (int) $actor->id) {
            $this->sendSafeNotification($report->user, [
                'module' => 'digital',
                'title' => 'Production Report Returned for Correction',
                'message' => "{$actor->name} returned your {$report->report_type} report ({$report->title}) for correction: {$reason}",
                'description' => $reason,
                'actor_name' => $actor->name,
                'link' => route('production-reports.show', $report->id),
            ]);
        }

        return $report->fresh();
    }

    /**
     * Approve a submitted report (by Head or Supervisor).
     */
    public function approveReport(User $actor, ProductionReport $report, ?string $comments = null): ProductionReport
    {
        if (!$this->permissionService->canReviewOrReturnReport($actor, $report)) {
            abort(403, 'You are not authorized to approve this report.');
        }

        $previousStatus = $report->status;
        $updates = [
            'status' => ProductionReport::STATUS_APPROVED,
            'reviewed_by' => $actor->id,
            'reviewed_at' => $this->nowCambodia(),
        ];
        if ($this->permissionService->isSupervisor($actor) && filled($comments)) {
            $updates['supervisor_comments'] = $comments;
        } elseif ($this->permissionService->isProductionHead($actor) && filled($comments)) {
            $updates['head_comments'] = $comments;
        }

        $report->update($updates);
        $this->recordRevision($report, $actor, 'approved', $previousStatus, ProductionReport::STATUS_APPROVED, $comments);

        if ($report->user && (int) $report->user_id !== (int) $actor->id) {
            $this->sendSafeNotification($report->user, [
                'module' => 'digital',
                'title' => 'Production Report Approved',
                'message' => "{$actor->name} approved your {$report->report_type} report ({$report->title}).",
                'actor_name' => $actor->name,
                'link' => route('production-reports.show', $report->id),
            ]);
        }

        return $report->fresh();
    }

    /**
     * Sync structured measurable task items on a report.
     */
    protected function syncReportItems(ProductionReport $report, User $user, array $items, string $defaultDate): void
    {
        $report->items()->delete();

        $sort = 0;
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['task_title'] ?? ''));
            if ($title === '') {
                continue;
            }

            ProductionReportItem::create([
                'production_report_id' => $report->id,
                'user_id' => $user->id,
                'task_title' => $title,
                'task_description' => $row['task_description'] ?? null,
                'task_category' => $row['task_category'] ?? ($report->project_category ?: 'General'),
                'quantity' => is_numeric($row['quantity'] ?? null) ? (float) $row['quantity'] : 1,
                'unit' => $row['unit'] ?? 'items',
                'status' => $row['status'] ?? 'completed',
                'completion_date' => $row['completion_date'] ?? $defaultDate,
                'external_link' => $row['external_link'] ?? null,
                'sort_order' => $sort++,
            ]);
        }
    }

    /**
     * Process uploaded media files and external media links into private storage.
     */
    public function processMediaUploads(
        ProductionReport $report,
        User $uploader,
        array $uploadedFiles,
        array $mediaMeta = [],
        array $externalMedia = []
    ): void {
        $folder = "{$report->storage_folder}/attachments";

        foreach ($uploadedFiles as $idx => $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
            if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                throw ValidationException::withMessages([
                    'attachments' => "Unsupported file extension (.{$ext}). Allowed: JPG, PNG, WEBP, PDF, MP4, MOV, WEBM, PPT, PPTX, DOC, DOCX, XLSX, CSV.",
                ]);
            }

            $uuidName = (string) Str::uuid() . '.' . $ext;
            $storedPath = $file->storeAs($folder, $uuidName, 'local');

            $meta = $mediaMeta[$idx] ?? [];
            $mime = $file->getClientMimeType() ?: 'application/octet-stream';
            $defaultType = str_starts_with($mime, 'video/') || in_array($ext, ['mp4', 'mov', 'webm'], true)
                ? 'video'
                : (str_starts_with($mime, 'image/') ? 'poster' : 'document');

            ProductionReportMedia::create([
                'production_report_id' => $report->id,
                'uploaded_by' => $uploader->id,
                'created_by_user_id' => $meta['created_by_user_id'] ?? $uploader->id,
                'team_code' => $report->team_code,
                'title' => !empty($meta['title']) ? $meta['title'] : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'description' => $meta['description'] ?? null,
                'media_type' => $meta['media_type'] ?? $defaultType,
                'project_category' => $meta['project_category'] ?? $report->project_category,
                'production_date' => $meta['production_date'] ?? ($report->reporting_date?->toDateString() ?? $this->nowCambodia()->toDateString()),
                'disk' => 'local',
                'file_path' => $storedPath,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $mime,
                'file_size' => $file->getSize(),
                'external_url' => $meta['external_url'] ?? null,
                'platform' => $meta['platform'] ?? 'Local Upload',
                'duration_label' => $meta['duration_label'] ?? null,
                'is_highlighted' => !empty($meta['is_highlighted']),
                'highlight_category' => $meta['highlight_category'] ?? null,
            ]);
        }

        foreach ($externalMedia as $extRow) {
            if (!is_array($extRow)) {
                continue;
            }
            $url = trim((string) ($extRow['external_url'] ?? ''));
            $title = trim((string) ($extRow['title'] ?? ''));
            if ($url === '' && $title === '') {
                continue;
            }

            ProductionReportMedia::create([
                'production_report_id' => $report->id,
                'uploaded_by' => $uploader->id,
                'created_by_user_id' => $extRow['created_by_user_id'] ?? $uploader->id,
                'team_code' => $report->team_code,
                'title' => $title ?: $url,
                'description' => $extRow['description'] ?? null,
                'media_type' => $extRow['media_type'] ?? 'video',
                'project_category' => $extRow['project_category'] ?? $report->project_category,
                'production_date' => $extRow['production_date'] ?? ($report->reporting_date?->toDateString() ?? $this->nowCambodia()->toDateString()),
                'disk' => 'local',
                'external_url' => $url,
                'platform' => $extRow['platform'] ?? $this->detectPlatformFromUrl($url),
                'duration_label' => $extRow['duration_label'] ?? null,
                'is_highlighted' => !empty($extRow['is_highlighted']),
                'highlight_category' => $extRow['highlight_category'] ?? null,
            ]);
        }
    }

    /**
     * Link selected Best Posters/Graphics and Best Videos from daily reports into a Weekly or Monthly report.
     */
    protected function syncHighlightedMediaForCompiledReport(
        ProductionReport $report,
        User $actor,
        array $selectedPosterIds,
        array $selectedVideoIds
    ): void {
        // Remove previously cloned highlight links from daily reports on this compiled report
        ProductionReportMedia::where('production_report_id', $report->id)
            ->whereNotNull('source_daily_report_id')
            ->delete();

        $allIds = array_values(array_unique(array_filter(array_merge(
            array_map('intval', $selectedPosterIds),
            array_map('intval', $selectedVideoIds)
        ))));

        if (empty($allIds)) {
            return;
        }

        $sourceMediaItems = ProductionReportMedia::whereIn('id', $allIds)->get();
        $order = 1;
        foreach ($sourceMediaItems as $src) {
            // Security check: ensure actor can view the source media's team/owner
            if (!$this->permissionService->canAccessMedia($actor, $src)) {
                continue;
            }

            $isPosterHighlight = in_array((int) $src->id, array_map('intval', $selectedPosterIds), true);

            ProductionReportMedia::create([
                'production_report_id' => $report->id,
                'source_daily_report_id' => $src->production_report_id,
                'uploaded_by' => $actor->id,
                'created_by_user_id' => $src->created_by_user_id ?: $src->uploaded_by,
                'team_code' => $report->team_code,
                'title' => $src->title,
                'description' => $src->description,
                'media_type' => $src->media_type,
                'project_category' => $src->project_category,
                'production_date' => $src->production_date,
                'disk' => $src->disk,
                'file_path' => $src->file_path,
                'thumbnail_path' => $src->thumbnail_path,
                'original_filename' => $src->original_filename,
                'mime_type' => $src->mime_type,
                'file_size' => $src->file_size,
                'external_url' => $src->external_url,
                'platform' => $src->platform,
                'duration_label' => $src->duration_label,
                'is_highlighted' => true,
                'highlight_category' => $isPosterHighlight ? 'best_poster' : 'best_video',
                'highlight_order' => $order++,
            ]);
        }
    }

    /**
     * Record an immutable audit/revision snapshot.
     */
    public function recordRevision(
        ProductionReport $report,
        ?User $actor,
        string $action,
        ?string $previousStatus,
        ?string $newStatus,
        ?string $notes = null
    ): ProductionReportRevision {
        $nextRev = ((int) ProductionReportRevision::where('production_report_id', $report->id)->max('revision_number')) + 1;

        return ProductionReportRevision::create([
            'production_report_id' => $report->id,
            'user_id' => $actor?->id,
            'revision_number' => $nextRev,
            'action' => $action,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'snapshot_json' => [
                'title' => $report->title,
                'executive_summary' => $report->executive_summary,
                'tasks_completed' => $report->tasks_completed,
                'work_in_progress' => $report->work_in_progress,
                'pending_tasks' => $report->pending_tasks,
                'blockers' => $report->blockers,
                'achievements' => $report->achievements,
                'next_priorities' => $report->next_priorities,
                'performance_rating' => $report->performance_rating,
                'head_comments' => $report->head_comments,
                'status' => $report->status,
                'submitted_at' => $report->submitted_at?->toIso8601String(),
            ],
            'notes' => $notes,
            'ip_address' => request()?->ip(),
            'created_at' => $this->nowCambodia(),
        ]);
    }

    protected function normalizeLinks(mixed $rawLinks): array
    {
        if (is_string($rawLinks)) {
            $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $rawLinks)));
            return array_values(array_map(fn ($u) => [
                'title' => $u,
                'url' => $u,
                'platform' => $this->detectPlatformFromUrl($u),
            ], $lines));
        }

        if (!is_array($rawLinks)) {
            return [];
        }

        $normalized = [];
        foreach ($rawLinks as $item) {
            if (is_string($item) && trim($item) !== '') {
                $u = trim($item);
                $normalized[] = [
                    'title' => $u,
                    'url' => $u,
                    'platform' => $this->detectPlatformFromUrl($u),
                ];
            } elseif (is_array($item) && !empty($item['url'])) {
                $u = trim((string) $item['url']);
                $normalized[] = [
                    'title' => trim((string) ($item['title'] ?? $u)),
                    'url' => $u,
                    'platform' => $item['platform'] ?? $this->detectPlatformFromUrl($u),
                ];
            }
        }

        return $normalized;
    }

    protected function detectPlatformFromUrl(string $url): string
    {
        $u = strtolower($url);
        return match (true) {
            str_contains($u, 'youtube.com') || str_contains($u, 'youtu.be') => 'YouTube',
            str_contains($u, 'tiktok.com') => 'TikTok',
            str_contains($u, 'facebook.com') || str_contains($u, 'fb.watch') => 'Facebook',
            str_contains($u, 'drive.google.com') || str_contains($u, 'docs.google.com') => 'Google Drive',
            str_contains($u, 'instagram.com') => 'Instagram',
            str_contains($u, 'canva.com') => 'Canva',
            default => 'External Link',
        };
    }

    /**
     * Generate a cleanly formatted plain text summary of multiple production reports,
     * suitable for 1-click clipboard copying to Slack, Telegram, or email.
     */
    public function generateCombinedTextSummary(
        \Illuminate\Support\Collection $reports,
        ?string $title = null,
        ?string $subtitle = null
    ): string {
        $nowStr = $this->nowCambodia()->format('M d, Y g:i A');
        $count = $reports->count();

        if ($count === 0) {
            return "No production reports match the selected date range and filter criteria.";
        }

        $earliest = $reports->min('reporting_start_date') ?: $reports->min('reporting_date');
        $latest = $reports->max('reporting_end_date') ?: $reports->max('reporting_date');
        $earliestStr = $earliest instanceof Carbon ? $earliest->format('M d, Y') : (string) $earliest;
        $latestStr = $latest instanceof Carbon ? $latest->format('M d, Y') : (string) $latest;

        $authors = $reports->map(fn ($r) => $r->user?->name)->filter()->unique()->values()->all();
        $teams = $reports->map(fn ($r) => $r->team_code)->filter()->unique()->values()->all();

        $lines = [];
        $lines[] = '============================================================';
        $lines[] = strtoupper($title ?: 'KIUQ SYSTEM — COMBINED PRODUCTION REPORTS SUMMARY');
        if ($subtitle) {
            $lines[] = $subtitle;
        }
        $lines[] = '============================================================';
        $lines[] = "• Period: {$earliestStr} to {$latestStr}";
        $lines[] = "• Total Reports: {$count}";
        if (!empty($authors)) {
            $lines[] = "• Team Member(s): " . implode(', ', $authors);
        }
        if (!empty($teams)) {
            $lines[] = "• Production Team(s): Team " . implode(', Team ', $teams);
        }
        $lines[] = "• Generated At: {$nowStr} (Cambodia Time)";
        $lines[] = '============================================================';
        $lines[] = '';

        foreach ($reports as $idx => $r) {
            $num = $idx + 1;
            $dateLabel = $r->period_label;
            $statusLabel = strtoupper($r->status_badge_label);
            $authorName = $r->user?->name ?? 'N/A';
            $teamLabel = $r->team_code ? "Team {$r->team_code}" : ($r->team_name ?: 'General');

            $lines[] = "------------------------------------------------------------";
            $lines[] = "[#{$num}] {$dateLabel} — {$authorName} ({$teamLabel})";
            $lines[] = "Report Code: {$r->report_code} | Type: " . strtoupper($r->report_type) . " | Status: {$statusLabel}" . ($r->is_late ? ' (LATE)' : '');
            if ($r->performance_rating) {
                $lines[] = "Rating: {$r->rating_display}";
            }
            $lines[] = "------------------------------------------------------------";

            if (filled($r->executive_summary)) {
                $lines[] = "EXECUTIVE SUMMARY:";
                $lines[] = trim((string) $r->executive_summary);
                $lines[] = '';
            }

            if (filled($r->tasks_completed)) {
                $lines[] = "COMPLETED TASKS:";
                $lines[] = trim((string) $r->tasks_completed);
                $lines[] = '';
            }

            if (filled($r->work_in_progress)) {
                $lines[] = "WORK IN PROGRESS:";
                $lines[] = trim((string) $r->work_in_progress);
                $lines[] = '';
            }

            if (filled($r->pending_tasks)) {
                $lines[] = "PENDING TASKS:";
                $lines[] = trim((string) $r->pending_tasks);
                $lines[] = '';
            }

            if (filled($r->blockers) && !str_starts_with(trim($r->blockers), 'No blockers')) {
                $lines[] = "CHALLENGES / BLOCKERS:";
                $lines[] = trim((string) $r->blockers);
                $lines[] = '';
            }

            if (filled($r->achievements) && trim($r->achievements) !== 'None reported.') {
                $lines[] = "KEY ACHIEVEMENTS:";
                $lines[] = trim((string) $r->achievements);
                $lines[] = '';
            }

            if (is_array($r->deliverable_links) && !empty($r->deliverable_links)) {
                $lines[] = "DELIVERABLE LINKS:";
                foreach ($r->deliverable_links as $link) {
                    $url = is_array($link) ? ($link['url'] ?? '') : (string) $link;
                    $title = is_array($link) ? ($link['title'] ?? $url) : $url;
                    if ($url) {
                        $lines[] = "▸ {$title}: {$url}";
                    }
                }
                $lines[] = '';
            }

            $lines[] = '';
        }

        $lines[] = '============================================================';
        $lines[] = 'END OF COMBINED REPORT';
        $lines[] = '============================================================';

        return implode("\n", $lines);
    }

    protected function generateReportCode(string $prefix, string $dateStr, int $userId, ?string $teamCode): string
    {
        $cleanDate = str_replace('-', '', $dateStr);
        $teamPart = $teamCode ? strtoupper($teamCode) : 'G';
        $rand = strtoupper(Str::random(4));

        return "PR-{$prefix}-{$cleanDate}-{$teamPart}{$userId}-{$rand}";
    }

    protected function notifyDailySubmitted(ProductionReport $report): void
    {
        $author = $report->user;
        $teamCode = (string) $report->team_code;
        $head = $this->permissionService->getTeamHead($teamCode);
        $somalika = User::whereRaw('LOWER(username) = ?', ['somalika'])->first();

        $lateTag = $report->is_late ? ' (Submitted Late)' : '';
        $payload = [
            'module' => 'digital',
            'title' => "Daily Production Report Submitted{$lateTag}",
            'message' => "{$author?->name} submitted their daily report for " . ($report->reporting_date?->format('M d, Y') ?? 'today') . "{$lateTag}.",
            'actor_name' => $author?->name ?? 'Team Member',
            'link' => route('production-reports.show', $report->id),
        ];

        if ($head && (int) $head->id !== (int) $report->user_id) {
            $this->sendSafeNotification($head, $payload);
        }
        if ($somalika && (int) $somalika->id !== (int) $report->user_id) {
            $this->sendSafeNotification($somalika, $payload);
        }
    }

    protected function notifyWeeklySubmitted(ProductionReport $report): void
    {
        $head = $report->user;
        $somalika = User::whereRaw('LOWER(username) = ?', ['somalika'])->first();
        if ($somalika) {
            $this->sendSafeNotification($somalika, [
                'module' => 'digital',
                'title' => "Weekly Team {$report->team_code} Report Submitted",
                'message' => "{$head?->name} submitted the Weekly Production Report for {$report->team_name} ({$report->period_label}) — Rating: {$report->rating_display}.",
                'actor_name' => $head?->name ?? 'Production Head',
                'link' => route('production-reports.show', $report->id),
            ]);
        }
    }

    protected function notifyMonthlySubmitted(ProductionReport $report): void
    {
        $author = $report->user;
        $head = $this->permissionService->getTeamHead((string) $report->team_code);
        $somalika = User::whereRaw('LOWER(username) = ?', ['somalika'])->first();

        $payload = [
            'module' => 'digital',
            'title' => 'Monthly Production Report Submitted',
            'message' => "{$author?->name} submitted their Monthly Production Report ({$report->period_label}).",
            'actor_name' => $author?->name ?? 'Team Member',
            'link' => route('production-reports.show', $report->id),
        ];

        // Member monthly reports go to the Head; the Head then submits the combined Team Monthly to the Supervisor.
        if ($head && (int) $head->id !== (int) $report->user_id) {
            $this->sendSafeNotification($head, $payload);
        }
    }

    protected function notifyTeamMonthlySubmitted(ProductionReport $report): void
    {
        $head = $report->user;
        $somalika = User::whereRaw('LOWER(username) = ?', ['somalika'])->first();
        if ($somalika) {
            $this->sendSafeNotification($somalika, [
                'module' => 'digital',
                'title' => "Team {$report->team_code} Monthly Report Submitted",
                'message' => "{$head?->name} submitted the combined Team Monthly Production Report for {$report->team_name} ({$report->period_label}) — Rating: {$report->rating_display}.",
                'actor_name' => $head?->name ?? 'Production Head',
                'link' => route('production-reports.show', $report->id),
            ]);
        }
    }

    public function sendSafeNotification(User $recipient, array $payload): void
    {
        try {
            $recipient->notify(new GenericDatabaseNotification($payload));
        } catch (\Throwable) {
            // Ignore notification errors in test/offline environments
        }
    }
}

