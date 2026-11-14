<?php

namespace App\Http\Controllers\ProductionReport;

use App\Http\Controllers\Controller;
use App\Models\ProductionReport\ProductionReport;
use App\Models\ProductionReport\ProductionReportDeadline;
use App\Models\ProductionReport\ProductionReportMedia;
use App\Models\ProductionReport\ProductionReportTeamMapping;
use App\Models\User;
use App\Services\ProductionReport\ProductionReportCsvService;
use App\Services\ProductionReport\ProductionReportPdfService;
use App\Services\ProductionReport\ProductionReportPermissionService;
use App\Services\ProductionReport\ProductionReportPptxService;
use App\Services\ProductionReport\ProductionReportService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductionReportController extends Controller
{
    public const REMINDER_LEAD_MINUTES = 15;

    public function __construct(
        private readonly ProductionReportPermissionService $permissionService,
        private readonly ProductionReportService $reportService,
        private readonly ProductionReportPptxService $pptxService,
        private readonly ProductionReportPdfService $pdfService,
        private readonly ProductionReportCsvService $csvService
    ) {}

    /**
     * Main Role-Aware Dashboard (/production-reports)
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $role = $this->permissionService->resolveRole($user);
        abort_if($role === null, 403);

        $now = $this->reportService->nowCambodia();
        $todayStr = $now->toDateString();
        $deadlineCfg = ProductionReportDeadline::current();
        $dailyDeadline = $this->reportService->calculateDailyDeadline($todayStr);
        $weeklyPeriod = $this->reportService->getDefaultWeeklyPeriod($now);
        $monthlyDeadline = $this->reportService->calculateMonthlyDeadline((int) $now->year, (int) $now->month);

        // 1. SUPERVISOR DASHBOARD (Somalika — View All, Never Submit)
        if ($role === ProductionReportTeamMapping::ROLE_SUPERVISOR) {
            $filterTeam = strtoupper((string) $request->get('team', 'ALL'));
            $dateFrom = $request->get('from', $now->copy()->startOfMonth()->toDateString());
            $dateTo = $request->get('to', $now->toDateString());

            $teamAMembers = $this->permissionService->getTeamMembers('A');
            $teamBMembers = $this->permissionService->getTeamMembers('B');
            $allMembers = $teamAMembers->concat($teamBMembers)->unique('id');

            $todayDailySubmitted = ProductionReport::where('report_type', ProductionReport::TYPE_DAILY)
                ->whereDate('reporting_date', $todayStr)
                ->whereIn('status', [
                    ProductionReport::STATUS_SUBMITTED,
                    ProductionReport::STATUS_SUBMITTED_LATE,
                    ProductionReport::STATUS_APPROVED,
                ])
                ->get();

            $todayDailyRate = $allMembers->count() > 0
                ? round(($todayDailySubmitted->pluck('user_id')->unique()->count() / $allMembers->count()) * 100, 1)
                : 0.0;

            $weeklyReportsThisWeek = ProductionReport::where('report_type', ProductionReport::TYPE_WEEKLY)
                ->whereDate('reporting_start_date', $weeklyPeriod['start_date'])
                ->whereIn('status', [
                    ProductionReport::STATUS_SUBMITTED,
                    ProductionReport::STATUS_SUBMITTED_LATE,
                    ProductionReport::STATUS_APPROVED,
                ])
                ->get();

            $weeklyRate = round(($weeklyReportsThisWeek->pluck('team_code')->unique()->count() / 2) * 100, 1);

            $monthlyReportsThisMonth = ProductionReport::where('report_type', ProductionReport::TYPE_MONTHLY)
                ->where('reporting_year', (int) $now->year)
                ->where('reporting_month', (int) $now->month)
                ->whereIn('status', [
                    ProductionReport::STATUS_SUBMITTED,
                    ProductionReport::STATUS_SUBMITTED_LATE,
                    ProductionReport::STATUS_APPROVED,
                ])
                ->get();

            $monthlyRate = $allMembers->count() > 0
                ? round(($monthlyReportsThisMonth->pluck('user_id')->unique()->count() / $allMembers->count()) * 100, 1)
                : 0.0;

            $reportsQuery = ProductionReport::with(['user', 'media'])
                ->whereBetween('reporting_start_date', [$dateFrom, $dateTo]);

            if (in_array($filterTeam, ['A', 'B'], true)) {
                $reportsQuery->where('team_code', $filterTeam);
            }

            $recentReports = (clone $reportsQuery)->latest('updated_at')->take(25)->get();
            $lateSubmissionsCount = (clone $reportsQuery)->where('is_late', true)->count();

            $teamAWeekly = $this->reportService->compileWeeklyTeamData('A', $weeklyPeriod['start_date'], $weeklyPeriod['end_date']);
            $teamBWeekly = $this->reportService->compileWeeklyTeamData('B', $weeklyPeriod['start_date'], $weeklyPeriod['end_date']);

            $latestTeamARating = ProductionReport::where('report_type', ProductionReport::TYPE_WEEKLY)
                ->where('team_code', 'A')
                ->whereNotNull('performance_rating')
                ->latest('reporting_end_date')
                ->first();

            $latestTeamBRating = ProductionReport::where('report_type', ProductionReport::TYPE_WEEKLY)
                ->where('team_code', 'B')
                ->whereNotNull('performance_rating')
                ->latest('reporting_end_date')
                ->first();

            $highlightedMedia = ProductionReportMedia::with(['creator', 'report'])
                ->where('is_highlighted', true)
                ->latest('id')
                ->take(8)
                ->get();

            return view('production-reports.index', [
                'role' => $role,
                'user' => $user,
                'now' => $now,
                'dailyDeadline' => $dailyDeadline,
                'weeklyPeriod' => $weeklyPeriod,
                'monthlyDeadline' => $monthlyDeadline,
                'filterTeam' => $filterTeam,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'teamAMembers' => $teamAMembers,
                'teamBMembers' => $teamBMembers,
                'totalMembersCount' => $allMembers->count(),
                'todayDailyRate' => $todayDailyRate,
                'weeklyRate' => $weeklyRate,
                'monthlyRate' => $monthlyRate,
                'lateSubmissionsCount' => $lateSubmissionsCount,
                'teamAWeekly' => $teamAWeekly,
                'teamBWeekly' => $teamBWeekly,
                'latestTeamARating' => $latestTeamARating,
                'latestTeamBRating' => $latestTeamBRating,
                'recentReports' => $recentReports,
                'highlightedMedia' => $highlightedMedia,
                'permissionService' => $this->permissionService,
            ]);
        }

        // 2. HEAD OF PRODUCTION DASHBOARD (Dara -> Team A ONLY; Kim -> Team B ONLY)
        if ($role === ProductionReportTeamMapping::ROLE_HEAD) {
            $teamCode = $this->permissionService->getAssignedTeamCode($user) ?: 'A';
            $teamName = $this->permissionService->getTeamName($teamCode);
            $teamMembers = $this->permissionService->getTeamMembers($teamCode);

            $compiledWeek = $this->reportService->compileWeeklyTeamData(
                $teamCode,
                $weeklyPeriod['start_date'],
                $weeklyPeriod['end_date']
            );

            $currentWeeklyReport = ProductionReport::where('report_type', ProductionReport::TYPE_WEEKLY)
                ->where('team_code', $teamCode)
                ->whereDate('reporting_start_date', $weeklyPeriod['start_date'])
                ->first();

            $todayTeamDailyReports = ProductionReport::with('user')
                ->where('report_type', ProductionReport::TYPE_DAILY)
                ->where('team_code', $teamCode)
                ->whereDate('reporting_date', $todayStr)
                ->get()
                ->keyBy('user_id');

            $recentTeamReports = $this->permissionService->scopeReportsQuery(
                ProductionReport::with(['user', 'media']),
                $user
            )->latest('updated_at')->take(15)->get();

            return view('production-reports.index', [
                'role' => $role,
                'user' => $user,
                'now' => $now,
                'dailyDeadline' => $dailyDeadline,
                'weeklyPeriod' => $weeklyPeriod,
                'monthlyDeadline' => $monthlyDeadline,
                'teamCode' => $teamCode,
                'teamName' => $teamName,
                'teamMembers' => $teamMembers,
                'compiledWeek' => $compiledWeek,
                'currentWeeklyReport' => $currentWeeklyReport,
                'todayTeamDailyReports' => $todayTeamDailyReports,
                'recentReports' => $recentTeamReports,
                'permissionService' => $this->permissionService,
            ]);
        }

        // 3. PRODUCTION MEMBER DASHBOARD (Own Reports Only)
        $todayReport = ProductionReport::where('user_id', $user->id)
            ->where('report_type', ProductionReport::TYPE_DAILY)
            ->whereDate('reporting_date', $todayStr)
            ->first();

        $monthlyCompiled = $this->reportService->compileMonthlyMemberData($user, (int) $now->year, (int) $now->month);
        $currentMonthlyReport = ProductionReport::where('user_id', $user->id)
            ->where('report_type', ProductionReport::TYPE_MONTHLY)
            ->where('reporting_year', (int) $now->year)
            ->where('reporting_month', (int) $now->month)
            ->first();

        $recentReports = ProductionReport::with('media')
            ->where('user_id', $user->id)
            ->latest('reporting_start_date')
            ->latest('id')
            ->take(12)
            ->get();

        return view('production-reports.index', [
            'role' => $role,
            'user' => $user,
            'now' => $now,
            'dailyDeadline' => $dailyDeadline,
            'weeklyPeriod' => $weeklyPeriod,
            'monthlyDeadline' => $monthlyDeadline,
            'todayReport' => $todayReport,
            'monthlyCompiled' => $monthlyCompiled,
            'currentMonthlyReport' => $currentMonthlyReport,
            'recentReports' => $recentReports,
            'permissionService' => $this->permissionService,
        ]);
    }

    /**
     * Dedicated Daily Report Tracker Page (/production-reports/daily-tracker)
     */
    public function dailyTracker(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $role = $this->permissionService->resolveRole($user);
        abort_if($role === null, 403);

        if ($role === ProductionReportTeamMapping::ROLE_MEMBER && $this->permissionService->canSubmitDaily($user)) {
            return redirect()->route('production-reports.daily.create');
        }

        $now = $this->reportService->nowCambodia();
        $todayStr = $now->toDateString();

        $dateParam = (string) $request->get('date', $todayStr);
        try {
            $selectedDate = Carbon::parse($dateParam, 'Asia/Phnom_Penh')->startOfDay();
        } catch (\Throwable) {
            $selectedDate = $now->copy()->startOfDay();
        }
        $selectedDateStr = $selectedDate->toDateString();
        $dailyDeadline = $this->reportService->calculateDailyDeadline($selectedDateStr);

        if ($role === ProductionReportTeamMapping::ROLE_SUPERVISOR) {
            $filterTeam = strtoupper((string) $request->get('team', 'ALL'));
            if (!in_array($filterTeam, ['ALL', 'A', 'B'], true)) {
                $filterTeam = 'ALL';
            }
            $teamsToTrack = $filterTeam === 'ALL' ? ['A', 'B'] : [$filterTeam];
        } else {
            $assignedTeam = $this->permissionService->getAssignedTeamCode($user) ?: 'A';
            $filterTeam = $assignedTeam;
            $teamsToTrack = [$assignedTeam];
        }

        $teamTrackers = [];
        $totalMembers = 0;
        $totalSubmitted = 0;
        $totalLate = 0;
        $totalCompletedTasks = 0;

        foreach ($teamsToTrack as $tCode) {
            $members = $this->permissionService->getTeamMembers($tCode);
            $reportsByMember = ProductionReport::with(['user', 'media'])
                ->where('report_type', ProductionReport::TYPE_DAILY)
                ->where('team_code', $tCode)
                ->whereDate('reporting_date', $selectedDateStr)
                ->get()
                ->keyBy('user_id');

            $teamSubmitted = 0;
            $teamLate = 0;
            $teamTasks = 0;

            foreach ($members as $m) {
                $rep = $reportsByMember->get($m->id);
                if ($rep && in_array($rep->status, [
                    ProductionReport::STATUS_SUBMITTED,
                    ProductionReport::STATUS_SUBMITTED_LATE,
                    ProductionReport::STATUS_APPROVED,
                ], true)) {
                    $teamSubmitted++;
                    if ($rep->is_late || $rep->status === ProductionReport::STATUS_SUBMITTED_LATE) {
                        $teamLate++;
                    }
                    $tasksArr = is_array($rep->completed_tasks) ? $rep->completed_tasks : [];
                    $teamTasks += count($tasksArr);
                }
            }

            $totalMembers += $members->count();
            $totalSubmitted += $teamSubmitted;
            $totalLate += $teamLate;
            $totalCompletedTasks += $teamTasks;

            $teamTrackers[] = [
                'code' => $tCode,
                'name' => $this->permissionService->getTeamName($tCode),
                'members' => $members,
                'reports' => $reportsByMember,
                'submitted_count' => $teamSubmitted,
                'missing_count' => max(0, $members->count() - $teamSubmitted),
                'late_count' => $teamLate,
                'completed_tasks' => $teamTasks,
            ];
        }

        return view('production-reports.daily-tracker', [
            'role' => $role,
            'user' => $user,
            'now' => $now,
            'todayStr' => $todayStr,
            'selectedDate' => $selectedDate,
            'selectedDateStr' => $selectedDateStr,
            'prevDateStr' => $selectedDate->copy()->subDay()->toDateString(),
            'nextDateStr' => $selectedDate->copy()->addDay()->toDateString(),
            'dailyDeadline' => $dailyDeadline,
            'filterTeam' => $filterTeam,
            'teamTrackers' => $teamTrackers,
            'totalMembers' => $totalMembers,
            'totalSubmitted' => $totalSubmitted,
            'totalMissing' => max(0, $totalMembers - $totalSubmitted),
            'totalLate' => $totalLate,
            'totalCompletedTasks' => $totalCompletedTasks,
            'permissionService' => $this->permissionService,
        ]);
    }

    /**
     * Lightweight JSON poll used by the global reminder banner.
     * Tells eligible members/heads when their Daily Report is due within 15 minutes (or overdue).
     */
    public function reminderStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $empty = ['show' => false];

        if (!$user || !$this->permissionService->canSubmitDaily($user)) {
            return response()->json($empty);
        }

        $maintModules = \Illuminate\Support\Facades\Cache::remember('active_maintenance_modules_list', 15, function() {
            $val = \App\Models\Setting::where('key', 'maintenance_modules')->value('value');
            return $val ? (json_decode($val, true) ?: []) : [];
        });
        $isMaintActive = in_array('production_reports', $maintModules, true) || in_array('reports', $maintModules, true);
        if ($isMaintActive && (!method_exists($user, 'isDara') || !$user->isDara())) {
            return response()->json($empty);
        }

        $now = $this->reportService->nowCambodia();
        if ($now->isSunday()) {
            return response()->json($empty);
        }

        $todayStr = $now->toDateString();
        $alreadySubmitted = ProductionReport::where('user_id', $user->id)
            ->where('report_type', ProductionReport::TYPE_DAILY)
            ->whereDate('reporting_date', $todayStr)
            ->whereIn('status', [
                ProductionReport::STATUS_SUBMITTED,
                ProductionReport::STATUS_SUBMITTED_LATE,
                ProductionReport::STATUS_APPROVED,
            ])
            ->exists();

        if ($alreadySubmitted) {
            return response()->json($empty);
        }

        $deadline = $this->reportService->calculateDailyDeadline($todayStr);
        $secondsLeft = $now->diffInSeconds($deadline, false);
        $leadSeconds = self::REMINDER_LEAD_MINUTES * 60;

        // Show from 15 minutes before the deadline until the end of the working day.
        if ($secondsLeft > $leadSeconds) {
            return response()->json($empty + ['seconds_until_window' => (int) ($secondsLeft - $leadSeconds)]);
        }

        return response()->json([
            'show' => true,
            'seconds_left' => (int) $secondsLeft,
            'overdue' => $secondsLeft < 0,
            'deadline_label' => $deadline->format('g:i A'),
            'lead_minutes' => self::REMINDER_LEAD_MINUTES,
            'url' => route('production-reports.daily.create', ['date' => $todayStr]),
        ]);
    }

    /**
     * My Reports List (/production-reports/my-reports)
     */
    public function myReports(Request $request): View
    {
        $user = $request->user();
        $query = $this->permissionService->scopeReportsQuery(
            ProductionReport::with(['user', 'media'])->withCount(['items', 'media']),
            $user
        );

        if ($request->filled('type') && in_array($request->get('type'), ['daily', 'weekly', 'monthly'], true)) {
            $query->where('report_type', $request->get('type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }
        if ($request->filled('from')) {
            $from = $request->get('from');
            $query->where(fn ($q) => $q->whereDate('reporting_start_date', '>=', $from)->orWhereDate('reporting_date', '>=', $from));
        }
        if ($request->filled('to')) {
            $to = $request->get('to');
            $query->where(fn ($q) => $q->whereDate('reporting_end_date', '<=', $to)->orWhereDate('reporting_date', '<=', $to));
        }
        if ($request->filled('search')) {
            $s = trim($request->get('search'));
            $query->where(function ($sub) use ($s) {
                $sub->where('title', 'like', "%{$s}%")
                    ->orWhere('report_code', 'like', "%{$s}%")
                    ->orWhere('tasks_completed', 'like', "%{$s}%")
                    ->orWhere('executive_summary', 'like', "%{$s}%");
            });
        }
        if ($request->filled('team') && $this->permissionService->isSupervisor($user)) {
            $query->where('team_code', strtoupper($request->get('team')));
        }

        $reports = $query->orderByDesc('reporting_start_date')->orderByDesc('id')->paginate(20)->withQueryString();

        return view('production-reports.my-reports', [
            'user' => $user,
            'role' => $this->permissionService->resolveRole($user),
            'reports' => $reports,
            'permissionService' => $this->permissionService,
        ]);
    }

    /**
     * Show Daily Report Submission Form (/production-reports/daily/create)
     */
    public function createDaily(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->permissionService->canSubmitDaily($user), 403, 'You are not permitted to submit daily reports.');

        $now = $this->reportService->nowCambodia();
        $selectedDate = $request->get('date', $now->toDateString());
        $selectedDate = Carbon::parse($selectedDate, ProductionReportService::TIMEZONE)->toDateString();

        $report = null;
        if ($request->filled('report_id')) {
            $report = ProductionReport::with(['items', 'media', 'revisions'])->findOrFail((int) $request->get('report_id'));
            abort_unless($this->permissionService->canViewReport($user, $report) && (int) $report->user_id === (int) $user->id, 403);
            $selectedDate = $report->reporting_date?->toDateString() ?: $selectedDate;
        } else {
            $report = ProductionReport::with(['items', 'media', 'revisions'])
                ->where('user_id', $user->id)
                ->where('report_type', ProductionReport::TYPE_DAILY)
                ->whereDate('reporting_date', $selectedDate)
                ->first();
        }

        $deadline = $this->reportService->calculateDailyDeadline($selectedDate);

        return view('production-reports.daily-form', [
            'user' => $user,
            'role' => $this->permissionService->resolveRole($user),
            'report' => $report,
            'selectedDate' => $selectedDate,
            'deadline' => $deadline,
            'now' => $now,
            'permissionService' => $this->permissionService,
        ]);
    }

    /**
     * Store / Autosave / Submit a Daily Report (POST /production-reports/daily)
     */
    public function storeDaily(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless($this->permissionService->canSubmitDaily($user), 403, 'You are not permitted to submit daily reports.');

        $isSubmit = $request->input('action') === 'submit';

        $validated = $request->validate([
            'report_id' => ['nullable', 'integer'],
            'reporting_date' => ['required', 'date'],
            'project_category' => ['nullable', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:255'],
            'tasks_completed' => $isSubmit ? ['required', 'string'] : ['nullable', 'string'],
            'work_in_progress' => ['nullable', 'string'],
            'pending_tasks' => ['nullable', 'string'],
            'blockers' => ['nullable', 'string'],
            'achievements' => ['nullable', 'string'],
            'deliverable_links' => ['nullable'],
            'notes' => ['nullable', 'string'],
            'revision_notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['nullable', 'array'],
            'media_meta' => ['nullable', 'array'],
            'external_media' => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file', 'max:102400'], // up to 100MB for video/poster uploads
        ]);

        $existing = null;
        if (!empty($validated['report_id'])) {
            $existing = ProductionReport::findOrFail((int) $validated['report_id']);
            abort_unless((int) $existing->user_id === (int) $user->id, 403);
        }

        $files = $request->file('attachments', []);
        $report = $this->reportService->saveOrSubmitDailyReport($user, $validated, is_array($files) ? $files : [$files], $isSubmit, $existing);

        if ($request->boolean('is_autosave') || $request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'report_id' => $report->id,
                'status' => $report->status,
                'status_label' => $report->status_badge_label,
                'saved_at' => now('Asia/Phnom_Penh')->format('g:i:s A'),
            ]);
        }

        $msg = $isSubmit
            ? ($report->is_late
                ? 'Daily Production Report submitted (Recorded as Submitted Late past 5:00 PM Cambodia deadline).'
                : 'Daily Production Report submitted on time!')
            : 'Daily report draft saved.';

        return redirect()->route('production-reports.show', $report->id)->with('success', $msg);
    }

    /**
     * Weekly Team Reports Index (/production-reports/weekly)
     */
    public function weeklyIndex(Request $request): View
    {
        $user = $request->user();
        $role = $this->permissionService->resolveRole($user);
        abort_if($role === null, 403);
        abort_unless($this->permissionService->canSeeFullMenu($user), 403, 'This section is available to Production Heads and the Supervisor only.');

        $query = $this->permissionService->scopeReportsQuery(
            ProductionReport::with(['user', 'media'])->where('report_type', ProductionReport::TYPE_WEEKLY),
            $user
        );

        if ($request->filled('team') && $this->permissionService->isSupervisor($user)) {
            $query->where('team_code', strtoupper($request->get('team')));
        }

        $weeklyReports = $query->orderByDesc('reporting_start_date')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('production-reports.weekly-index', [
            'user' => $user,
            'role' => $role,
            'weeklyReports' => $weeklyReports,
            'permissionService' => $this->permissionService,
        ]);
    }

    /**
     * Create / Compile Weekly Team Report Form (/production-reports/weekly/create)
     */
    public function createWeekly(Request $request): View
    {
        $user = $request->user();
        $assignedTeam = $this->permissionService->getAssignedTeamCode($user);
        $requestedTeam = strtoupper((string) $request->get('team', $assignedTeam ?: 'A'));

        abort_unless(
            $this->permissionService->canSubmitWeekly($user, $requestedTeam),
            403,
            'Access denied. Only the Head of Production for this team can create or compile its Weekly Team Report.'
        );

        $defaultPeriod = $this->reportService->getDefaultWeeklyPeriod();
        $startDate = Carbon::parse($request->get('start_date', $defaultPeriod['start_date']), ProductionReportService::TIMEZONE)->toDateString();
        $endDate = Carbon::parse($request->get('end_date', $defaultPeriod['end_date']), ProductionReportService::TIMEZONE)->toDateString();

        $report = null;
        if ($request->filled('report_id')) {
            $report = ProductionReport::with(['media', 'revisions'])->findOrFail((int) $request->get('report_id'));
            abort_unless($this->permissionService->canViewReport($user, $report) && strtoupper((string) $report->team_code) === $assignedTeam, 403);
            $startDate = $report->reporting_start_date->toDateString();
            $endDate = $report->reporting_end_date->toDateString();
        } else {
            $report = ProductionReport::with(['media', 'revisions'])
                ->where('report_type', ProductionReport::TYPE_WEEKLY)
                ->where('team_code', $requestedTeam)
                ->whereDate('reporting_start_date', $startDate)
                ->whereDate('reporting_end_date', $endDate)
                ->first();
        }

        $compiled = $this->reportService->compileWeeklyTeamData($requestedTeam, $startDate, $endDate);
        $deadline = $this->reportService->calculateWeeklyDeadline($startDate, $endDate);

        return view('production-reports.weekly-form', [
            'user' => $user,
            'role' => $this->permissionService->resolveRole($user),
            'teamCode' => $requestedTeam,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'compiled' => $compiled,
            'report' => $report,
            'deadline' => $deadline,
            'ratingLabels' => ProductionReport::RATING_LABELS,
            'permissionService' => $this->permissionService,
        ]);
    }

    /**
     * Store Draft or Submit Weekly Team Report (POST /production-reports/weekly)
     */
    public function storeWeekly(Request $request): RedirectResponse
    {
        $user = $request->user();
        $assignedTeam = $this->permissionService->getAssignedTeamCode($user);
        $requestedTeam = strtoupper((string) $request->input('team_code', $assignedTeam ?: ''));

        abort_unless(
            $this->permissionService->canSubmitWeekly($user, $requestedTeam),
            403,
            'Access denied. You can only submit weekly reports for your assigned production team.'
        );

        $isSubmit = $request->input('action') === 'submit';

        $validated = $request->validate([
            'report_id' => ['nullable', 'integer'],
            'team_code' => ['required', 'string', 'in:A,B'],
            'reporting_start_date' => ['required', 'date'],
            'reporting_end_date' => ['required', 'date', 'after_or_equal:reporting_start_date'],
            'title' => ['nullable', 'string', 'max:255'],
            'executive_summary' => ['nullable', 'string'],
            'tasks_completed' => ['nullable', 'string'],
            'work_in_progress' => ['nullable', 'string'],
            'pending_tasks' => ['nullable', 'string'],
            'blockers' => ['nullable', 'string'],
            'achievements' => ['nullable', 'string'],
            'next_priorities' => ['nullable', 'string'],
            'health_status' => ['nullable', 'string', 'in:on_track,at_risk,blocked'],
            'head_comments' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'performance_rating' => $isSubmit ? ['required', 'integer', 'min:1', 'max:5'] : ['nullable', 'integer', 'min:1', 'max:5'],
            'member_ratings' => ['nullable', 'array'],
            'selected_poster_ids' => ['nullable', 'array'],
            'selected_video_ids' => ['nullable', 'array'],
            'deliverable_links' => ['nullable'],
            'media_meta' => ['nullable', 'array'],
            'external_media' => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file', 'max:102400'],
        ]);

        $existing = null;
        if (!empty($validated['report_id'])) {
            $existing = ProductionReport::findOrFail((int) $validated['report_id']);
            abort_unless(strtoupper((string) $existing->team_code) === $assignedTeam, 403);
        }

        $files = $request->file('attachments', []);
        $report = $this->reportService->saveOrSubmitWeeklyReport(
            $user,
            $validated,
            is_array($files) ? $files : [$files],
            $isSubmit,
            $existing
        );

        $msg = $isSubmit
            ? "Weekly Team {$report->team_code} Production Report submitted (Rating: {$report->rating_display})!"
            : "Weekly Team {$report->team_code} Report draft saved.";

        return redirect()->route('production-reports.show', $report->id)->with('success', $msg);
    }

    /**
     * Create / Auto-Compile Monthly Report Form (/production-reports/monthly/create)
     */
    public function createMonthly(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->permissionService->canSubmitMonthly($user), 403, 'You are not permitted to submit monthly reports.');

        $now = $this->reportService->nowCambodia();
        $year = (int) $request->get('year', $now->year);
        $month = max(1, min(12, (int) $request->get('month', $now->month)));

        $report = ProductionReport::with(['media', 'revisions'])
            ->where('user_id', $user->id)
            ->where('report_type', ProductionReport::TYPE_MONTHLY)
            ->where('reporting_year', $year)
            ->where('reporting_month', $month)
            ->first();

        $compiled = $this->reportService->compileMonthlyMemberData($user, $year, $month);
        $deadline = $this->reportService->calculateMonthlyDeadline($year, $month);

        return view('production-reports.monthly-form', [
            'user' => $user,
            'role' => $this->permissionService->resolveRole($user),
            'year' => $year,
            'month' => $month,
            'compiled' => $compiled,
            'report' => $report,
            'deadline' => $deadline,
            'permissionService' => $this->permissionService,
        ]);
    }

    /**
     * Store Draft or Submit Monthly Report (POST /production-reports/monthly)
     */
    public function storeMonthly(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->permissionService->canSubmitMonthly($user), 403, 'You are not permitted to submit monthly reports.');

        $isSubmit = $request->input('action') === 'submit';

        $validated = $request->validate([
            'report_id' => ['nullable', 'integer'],
            'reporting_year' => ['required', 'integer', 'min:2024', 'max:2035'],
            'reporting_month' => ['required', 'integer', 'min:1', 'max:12'],
            'title' => ['nullable', 'string', 'max:255'],
            'executive_summary' => ['nullable', 'string'],
            'tasks_completed' => ['nullable', 'string'],
            'work_in_progress' => ['nullable', 'string'],
            'pending_tasks' => ['nullable', 'string'],
            'blockers' => ['nullable', 'string'],
            'achievements' => ['nullable', 'string'],
            'next_priorities' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'selected_poster_ids' => ['nullable', 'array'],
            'selected_video_ids' => ['nullable', 'array'],
            'deliverable_links' => ['nullable'],
            'media_meta' => ['nullable', 'array'],
            'external_media' => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file', 'max:102400'],
        ]);

        $existing = null;
        if (!empty($validated['report_id'])) {
            $existing = ProductionReport::findOrFail((int) $validated['report_id']);
            abort_unless((int) $existing->user_id === (int) $user->id, 403);
        }

        $files = $request->file('attachments', []);
        $report = $this->reportService->saveOrSubmitMonthlyReport(
            $user,
            $validated,
            is_array($files) ? $files : [$files],
            $isSubmit,
            $existing
        );

        $msg = $isSubmit
            ? "Monthly Production Report ({$report->period_label}) submitted!"
            : "Monthly Production Report draft saved.";

        return redirect()->route('production-reports.show', $report->id)->with('success', $msg);
    }

    /**
     * Show Head's Team Monthly Compilation Form (/production-reports/monthly/team)
     */
    public function createTeamMonthly(Request $request): View
    {
        $user = $request->user();
        $assignedTeam = $this->permissionService->getAssignedTeamCode($user);
        abort_unless($this->permissionService->canSubmitTeamMonthly($user, $assignedTeam), 403, 'Only Production Team Heads may compile and submit Team Monthly reports.');

        $now = $this->reportService->nowCambodia();
        $year = (int) $request->get('year', $now->year);
        $month = (int) $request->get('month', $now->month);

        $compiled = $this->reportService->compileMonthlyTeamData($assignedTeam, $year, $month);
        $deadline = $this->reportService->calculateMonthlyDeadline($year, $month);

        $report = ProductionReport::with(['items', 'media', 'revisions'])
            ->where('report_type', ProductionReport::TYPE_MONTHLY)
            ->where('project_category', ProductionReport::TEAM_MONTHLY_CATEGORY)
            ->where('team_code', $assignedTeam)
            ->where('reporting_year', $year)
            ->where('reporting_month', $month)
            ->first();

        return view('production-reports.team-monthly-form', [
            'user' => $user,
            'role' => $this->permissionService->resolveRole($user),
            'teamCode' => $assignedTeam,
            'teamName' => $compiled['team_name'],
            'compiled' => $compiled,
            'report' => $report,
            'deadline' => $deadline,
            'year' => $year,
            'month' => $month,
            'permissionService' => $this->permissionService,
        ]);
    }

    /**
     * Store Draft or Submit Team Monthly Report to Supervisor (POST /production-reports/monthly/team)
     */
    public function storeTeamMonthly(Request $request): RedirectResponse
    {
        $user = $request->user();
        $assignedTeam = $this->permissionService->getAssignedTeamCode($user);
        abort_unless($this->permissionService->canSubmitTeamMonthly($user, $assignedTeam), 403, 'Only Production Team Heads may compile and submit Team Monthly reports.');

        $isSubmit = $request->input('action') === 'submit';

        $rules = [
            'reporting_year' => ['required', 'integer', 'min:2024', 'max:2035'],
            'reporting_month' => ['required', 'integer', 'between:1,12'],
            'title' => ['required', 'string', 'max:255'],
            'executive_summary' => [$isSubmit ? 'required' : 'nullable', 'string'],
            'tasks_completed' => [$isSubmit ? 'required' : 'nullable', 'string'],
            'work_in_progress' => ['nullable', 'string'],
            'pending_tasks' => ['nullable', 'string'],
            'blockers' => ['nullable', 'string'],
            'achievements' => ['nullable', 'string'],
            'next_priorities' => [$isSubmit ? 'required' : 'nullable', 'string'],
            'head_comments' => ['nullable', 'string'],
            'health_status' => ['nullable', 'in:on_track,at_risk,blocked'],
            'performance_rating' => [$isSubmit ? 'required' : 'nullable', 'integer', 'between:1,5'],
            'attachments.*' => ['nullable', 'file', 'max:51200'],
            'report_id' => ['nullable', 'integer'],
        ];

        $validated = $request->validate($rules);
        $validated['team_code'] = $assignedTeam;

        $existing = null;
        if (!empty($validated['report_id'])) {
            $existing = ProductionReport::findOrFail((int) $validated['report_id']);
            abort_unless((int) $existing->user_id === (int) $user->id, 403);
        }

        $files = $request->file('attachments', []);
        $report = $this->reportService->saveOrSubmitTeamMonthlyReport(
            $user,
            $validated,
            is_array($files) ? $files : [$files],
            $isSubmit,
            $existing
        );

        $msg = $isSubmit
            ? "Team {$assignedTeam} Monthly Production Report submitted to Supervisor Somalika!"
            : "Team {$assignedTeam} Monthly Production Report draft saved.";

        return redirect()->route('production-reports.show', $report->id)->with('success', $msg);
    }

    /**
     * View a Single Production Report (/production-reports/reports/{report})
     */
    public function show(Request $request, ProductionReport $report): View
    {
        $user = $request->user();
        abort_unless($this->permissionService->canViewReport($user, $report), 403, 'You do not have permission to view this production report.');

        $report->load(['user', 'submitter', 'reviewer', 'items', 'media.creator', 'revisions.user']);
        $summary = is_array($report->compiled_summary_json) ? $report->compiled_summary_json : [];

        return view('production-reports.show', [
            'user' => $user,
            'role' => $this->permissionService->resolveRole($user),
            'report' => $report,
            'summary' => $summary,
            'canEdit' => $this->permissionService->canEditReport($user, $report),
            'canReview' => $this->permissionService->canReviewOrReturnReport($user, $report),
            'permissionService' => $this->permissionService,
        ]);
    }

    /**
     * Return a submitted report for correction
     */
    public function returnForCorrection(Request $request, ProductionReport $report): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->permissionService->canReviewOrReturnReport($user, $report), 403);

        $validated = $request->validate([
            'returned_reason' => ['required', 'string', 'max:2000'],
        ]);

        $this->reportService->returnForCorrection($user, $report, $validated['returned_reason']);

        return redirect()->route('production-reports.show', $report->id)
            ->with('success', 'Report has been returned for correction and reopened for the author.');
    }

    /**
     * Approve a submitted report
     */
    public function approve(Request $request, ProductionReport $report): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->permissionService->canReviewOrReturnReport($user, $report), 403);

        $validated = $request->validate([
            'comments' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->reportService->approveReport($user, $report, $validated['comments'] ?? null);

        return redirect()->route('production-reports.show', $report->id)
            ->with('success', 'Report has been approved.');
    }

    /**
     * Export a Report as PPTX, PDF, or CSV (/production-reports/reports/{report}/export/{format})
     */
    public function export(Request $request, ProductionReport $report, string $format): BinaryFileResponse
    {
        $user = $request->user();
        abort_unless($this->permissionService->canViewReport($user, $report), 403, 'You are not authorized to export this report.');

        $fmt = strtolower(trim($format));
        abort_unless(in_array($fmt, ['pptx', 'pdf', 'csv'], true), 404, 'Unsupported export format.');

        if ($fmt === 'pptx') {
            $fullPath = $this->pptxService->generatePptx($report);
            return response()->download(
                $fullPath,
                "{$report->report_code}.pptx",
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation']
            );
        }

        if ($fmt === 'pdf') {
            $fullPath = $this->pdfService->generatePdf($report);
            return response()->download(
                $fullPath,
                "{$report->report_code}.pdf",
                ['Content-Type' => 'application/pdf']
            );
        }

        $fullPath = $this->csvService->generateReportCsv($report);
        return response()->download(
            $fullPath,
            "{$report->report_code}.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    /**
     * Export Filtered Archive / Date-Range Reports as CSV
     */
    public function exportArchiveCsv(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($this->permissionService->canSeeFullMenu($user), 403, 'Archive export is available to Production Heads and the Supervisor only.');
        $query = $this->buildFilteredArchiveQuery($request, $user);
        $reports = $query->orderByDesc('reporting_start_date')->limit(1000)->get();

        $csvContent = $this->csvService->generateCollectionCsvContent($reports);
        $filename = 'KIUQ-Production-Reports-Archive-' . now('Asia/Phnom_Penh')->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($csvContent) {
            echo $csvContent;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Export a filtered range of reports as combined CSV or PDF (/production-reports/export-range/{format})
     */
    public function exportRange(Request $request, string $format): BinaryFileResponse|RedirectResponse
    {
        $user = $request->user();
        $fmt = strtolower($format);
        abort_unless(in_array($fmt, ['pdf', 'csv'], true), 404, 'Unsupported export format. Supported formats: pdf, csv.');

        $query = $this->permissionService->scopeReportsQuery(
            ProductionReport::with(['user', 'media', 'items'])->withCount(['items', 'media']),
            $user
        );

        if ($request->filled('from')) {
            $from = $request->get('from');
            $query->where(fn ($q) => $q->whereDate('reporting_start_date', '>=', $from)->orWhereDate('reporting_date', '>=', $from));
        }
        if ($request->filled('to')) {
            $to = $request->get('to');
            $query->where(fn ($q) => $q->whereDate('reporting_end_date', '<=', $to)->orWhereDate('reporting_date', '<=', $to));
        }
        if ($request->filled('type') && in_array($request->get('type'), ['daily', 'weekly', 'monthly'], true)) {
            $query->where('report_type', $request->get('type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }
        if ($request->filled('member_id')) {
            $memId = (int) $request->get('member_id');
            $query->where('user_id', $memId);
        }
        if ($request->filled('team') && $this->permissionService->isSupervisor($user)) {
            $query->where('team_code', strtoupper($request->get('team')));
        }
        if ($request->filled('search')) {
            $s = trim($request->get('search'));
            $query->where(function ($sub) use ($s) {
                $sub->where('title', 'like', "%{$s}%")
                    ->orWhere('report_code', 'like', "%{$s}%")
                    ->orWhere('tasks_completed', 'like', "%{$s}%")
                    ->orWhere('executive_summary', 'like', "%{$s}%");
            });
        }

        $reports = $query->orderBy('reporting_start_date', 'asc')->orderBy('id', 'asc')->get();

        if ($reports->isEmpty()) {
            return back()->with('error', 'No production reports match your selected date range and filter criteria.');
        }

        $fromLabel = $request->get('from') ?: ($reports->min('reporting_start_date')?->toDateString() ?: 'Start');
        $toLabel = $request->get('to') ?: ($reports->max('reporting_end_date')?->toDateString() ?: 'End');

        if ($fmt === 'csv') {
            $fileName = "production-reports-{$user->username}-{$fromLabel}-to-{$toLabel}.csv";
            $csvPath = $this->csvService->generateCollectionCsvFile($reports, $fileName);

            return response()->download($csvPath, $fileName, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        $title = "Production Reports Summary — {$user->name}";
        if ($this->permissionService->isProductionHead($user)) {
            $title = "Team {$this->permissionService->getAssignedTeamCode($user)} Production Reports";
        } elseif ($this->permissionService->isSupervisor($user)) {
            $title = "Consolidated Production Reports (Supervisor)";
        }
        $subtitle = "Period: {$fromLabel} to {$toLabel} • Total Reports: {$reports->count()}";

        $meta = [
            'period_range' => "{$fromLabel} to {$toLabel}",
            'authors' => $reports->map(fn ($r) => $r->user?->name)->filter()->unique()->values()->all(),
            'teams' => $reports->map(fn ($r) => $r->team_code)->filter()->unique()->values()->all(),
            'total_tasks' => $reports->sum(fn ($r) => $r->items_count ?? $r->items->count()),
        ];

        $pdfPath = $this->pdfService->generateCombinedPdf($reports, $title, $subtitle, $meta);

        return response()->download($pdfPath, basename($pdfPath), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Return 1-click clipboard summary text as JSON (/production-reports/summary-text)
     */
    public function summaryText(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = $this->permissionService->scopeReportsQuery(
            ProductionReport::with(['user', 'media', 'items'])->withCount(['items', 'media']),
            $user
        );

        if ($request->filled('from')) {
            $from = $request->get('from');
            $query->where(fn ($q) => $q->whereDate('reporting_start_date', '>=', $from)->orWhereDate('reporting_date', '>=', $from));
        }
        if ($request->filled('to')) {
            $to = $request->get('to');
            $query->where(fn ($q) => $q->whereDate('reporting_end_date', '<=', $to)->orWhereDate('reporting_date', '<=', $to));
        }
        if ($request->filled('type') && in_array($request->get('type'), ['daily', 'weekly', 'monthly'], true)) {
            $query->where('report_type', $request->get('type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }
        if ($request->filled('member_id')) {
            $query->where('user_id', (int) $request->get('member_id'));
        }
        if ($request->filled('team') && $this->permissionService->isSupervisor($user)) {
            $query->where('team_code', strtoupper($request->get('team')));
        }
        if ($request->filled('search')) {
            $s = trim($request->get('search'));
            $query->where(function ($sub) use ($s) {
                $sub->where('title', 'like', "%{$s}%")
                    ->orWhere('report_code', 'like', "%{$s}%")
                    ->orWhere('tasks_completed', 'like', "%{$s}%");
            });
        }

        $reports = $query->orderBy('reporting_start_date', 'asc')->orderBy('id', 'asc')->get();

        $fromLabel = $request->get('from') ?: ($reports->min('reporting_start_date')?->toDateString() ?: 'Start');
        $toLabel = $request->get('to') ?: ($reports->max('reporting_end_date')?->toDateString() ?: 'End');

        $title = "KIUQ PRODUCTION REPORTS SUMMARY";
        $subtitle = "Prepared for {$user->name} • Period: {$fromLabel} to {$toLabel}";

        $text = $this->reportService->generateCombinedTextSummary($reports, $title, $subtitle);

        return response()->json([
            'success' => true,
            'count' => $reports->count(),
            'period' => "{$fromLabel} to {$toLabel}",
            'text' => $text,
        ]);
    }

    /**
     * Whole Team / Multi-Report Combined Export for Dara, Kim & Somalika (POST /production-reports/team/export-combined)
     */
    public function exportTeamCombined(Request $request): BinaryFileResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->permissionService->canSeeFullMenu($user), 403, 'Only Production Heads and the Supervisor may export whole-team combined packages.');

        $format = strtolower((string) $request->input('format', 'pdf'));
        abort_unless(in_array($format, ['pdf', 'csv'], true), 404, 'Unsupported export format.');

        $query = $this->permissionService->scopeReportsQuery(
            ProductionReport::with(['user', 'media', 'items'])->withCount(['items', 'media']),
            $user
        );

        if ($request->filled('report_ids')) {
            $ids = array_map('intval', (array) $request->input('report_ids'));
            $query->whereIn('id', $ids);
        } else {
            if ($this->permissionService->isProductionHead($user)) {
                $headTeam = $this->permissionService->getAssignedTeamCode($user);
                $query->where('team_code', $headTeam);
            } elseif ($request->filled('team') && in_array(strtoupper($request->input('team')), ['A', 'B'], true)) {
                $query->where('team_code', strtoupper($request->input('team')));
            }

            if ($request->filled('year')) {
                $query->where('reporting_year', (int) $request->input('year'));
            }
            if ($request->filled('month')) {
                $query->where('reporting_month', (int) $request->input('month'));
            }
            if ($request->filled('from')) {
                $query->whereDate('reporting_start_date', '>=', $request->input('from'));
            }
            if ($request->filled('to')) {
                $query->whereDate('reporting_end_date', '<=', $request->input('to'));
            }
            if ($request->filled('report_type')) {
                $query->where('report_type', $request->input('report_type'));
            }
        }

        $reports = $query->orderBy('reporting_start_date', 'asc')->orderBy('id', 'asc')->get();

        if ($reports->isEmpty()) {
            return back()->with('error', 'No production reports match your selection for combined export.');
        }

        $teamTag = $this->permissionService->isProductionHead($user)
            ? 'Team-' . $this->permissionService->getAssignedTeamCode($user)
            : ($request->filled('team') ? 'Team-' . strtoupper($request->input('team')) : 'All-Teams');

        $timestamp = now('Asia/Phnom_Penh')->format('Ymd-His');

        if ($format === 'csv') {
            $fileName = "production-package-{$teamTag}-{$timestamp}.csv";
            $csvPath = $this->csvService->generateCollectionCsvFile($reports, $fileName);

            return response()->download($csvPath, $fileName, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        $title = "{$teamTag} Consolidated Production Package";
        $subtitle = "Exported by {$user->name} • Total Reports: {$reports->count()}";

        $meta = [
            'period_range' => ($request->input('from') ?: $reports->min('reporting_start_date')?->toDateString()) . ' to ' . ($request->input('to') ?: $reports->max('reporting_end_date')?->toDateString()),
            'authors' => $reports->map(fn ($r) => $r->user?->name)->filter()->unique()->values()->all(),
            'teams' => $reports->map(fn ($r) => $r->team_code)->filter()->unique()->values()->all(),
            'total_tasks' => $reports->sum(fn ($r) => $r->items_count ?? $r->items->count()),
        ];

        $pdfPath = $this->pdfService->generateCombinedPdf($reports, $title, $subtitle, $meta);

        return response()->download($pdfPath, basename($pdfPath), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Securely View/Preview a Media File from Private Storage
     */
    public function viewMedia(Request $request, ProductionReportMedia $media): BinaryFileResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->permissionService->canAccessMedia($user, $media), 403, 'Unauthorized media access.');

        if (!$media->file_path && $media->external_url) {
            return redirect()->away($media->external_url);
        }

        $disk = $media->disk ?: 'local';
        abort_unless($media->file_path && Storage::disk($disk)->exists($media->file_path), 404, 'Media file not found in private storage.');

        return response()->file(Storage::disk($disk)->path($media->file_path), [
            'Content-Type' => $media->mime_type ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * Securely Download a Media File from Private Storage
     */
    public function downloadMedia(Request $request, ProductionReportMedia $media): BinaryFileResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->permissionService->canAccessMedia($user, $media), 403, 'Unauthorized media download.');

        if (!$media->file_path && $media->external_url) {
            return redirect()->away($media->external_url);
        }

        $disk = $media->disk ?: 'local';
        abort_unless($media->file_path && Storage::disk($disk)->exists($media->file_path), 404, 'Media file not found in private storage.');

        $downloadName = $media->original_filename ?: basename($media->file_path);

        return response()->download(
            Storage::disk($disk)->path($media->file_path),
            $downloadName,
            ['Content-Type' => $media->mime_type ?: 'application/octet-stream']
        );
    }

    /**
     * Report Calendar View (/production-reports/calendar)
     */
    public function calendar(Request $request): View
    {
        $user = $request->user();
        $role = $this->permissionService->resolveRole($user);
        abort_if($role === null, 403);
        abort_unless($this->permissionService->canSeeFullMenu($user), 403, 'This section is available to Production Heads and the Supervisor only.');

        $now = $this->reportService->nowCambodia();
        $year = (int) $request->get('year', $now->year);
        $month = max(1, min(12, (int) $request->get('month', $now->month)));

        $startOfMonth = Carbon::createFromDate($year, $month, 1, ProductionReportService::TIMEZONE)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        // Calendar grid range: starts from Sunday of week 1 and ends on Saturday of the last week
        $calStart = $startOfMonth->copy()->startOfWeek(Carbon::SUNDAY);
        $calEnd = $endOfMonth->copy()->endOfWeek(Carbon::SATURDAY);

        // Previous and Next month navigation
        $prevDate = $startOfMonth->copy()->subMonth();
        $nextDate = $startOfMonth->copy()->addMonth();

        // Team filter resolution
        $teamFilter = $request->get('team');
        if ($this->permissionService->isSupervisor($user)) {
            $teamFilter = in_array(strtoupper((string) $teamFilter), ['A', 'B']) ? strtoupper($teamFilter) : 'ALL';
        } elseif ($this->permissionService->isProductionHead($user)) {
            $teamFilter = $this->permissionService->getAssignedTeamCode($user) ?: 'A';
        } else {
            $teamFilter = $this->permissionService->getAssignedTeamCode($user) ?: 'A';
        }

        // Active expected team members
        if ($teamFilter === 'A') {
            $teamMembers = $this->permissionService->getTeamMembers('A');
        } elseif ($teamFilter === 'B') {
            $teamMembers = $this->permissionService->getTeamMembers('B');
        } else {
            $teamMembers = $this->permissionService->getTeamMembers('A')
                ->concat($this->permissionService->getTeamMembers('B'))
                ->unique('id');
        }
        $expectedDailyCount = $teamMembers->count();

        // Query reports within the full calendar viewport
        $query = $this->permissionService->scopeReportsQuery(
            ProductionReport::with('user')
                ->where(function ($q) use ($calStart, $calEnd) {
                    $q->whereBetween('reporting_date', [$calStart->toDateString(), $calEnd->toDateString()])
                      ->orWhereBetween('reporting_start_date', [$calStart->toDateString(), $calEnd->toDateString()])
                      ->orWhereBetween('reporting_end_date', [$calStart->toDateString(), $calEnd->toDateString()]);
                }),
            $user
        );

        if ($teamFilter !== 'ALL') {
            $query->where('team_code', $teamFilter);
        }

        $reports = $query->orderBy('reporting_start_date')->get();

        // Build calendar days
        $calendarDays = [];
        $totalMonthReports = 0;
        $totalMonthDaily = 0;
        $totalMonthWeekly = 0;
        $totalMonthMonthly = 0;
        $workdayCount = 0;
        $submittedWorkdayDailyCount = 0;

        foreach (CarbonPeriod::create($calStart->toDateString(), $calEnd->toDateString()) as $day) {
            $dStr = $day->toDateString();
            $dayReports = $reports->filter(function (ProductionReport $r) use ($dStr) {
                return ($r->reporting_date && $r->reporting_date->toDateString() === $dStr)
                    || ($r->report_type !== ProductionReport::TYPE_DAILY && $r->reporting_end_date && $r->reporting_end_date->toDateString() === $dStr);
            })->values();

            $isCurrentMonth = ($day->month === $month);
            $isSunday = $day->isSunday();
            $isPast = $day->lt($now->copy()->startOfDay());
            $isToday = ($dStr === $now->toDateString());

            $dailyReports = $dayReports->where('report_type', ProductionReport::TYPE_DAILY)->values();
            $weeklyReports = $dayReports->where('report_type', ProductionReport::TYPE_WEEKLY)->values();
            $monthlyReports = $dayReports->where('report_type', ProductionReport::TYPE_MONTHLY)->values();

            $dailySubmittedCount = $dailyReports->count();

            if ($isCurrentMonth) {
                $totalMonthReports += $dayReports->count();
                $totalMonthDaily += $dailySubmittedCount;
                $totalMonthWeekly += $weeklyReports->count();
                $totalMonthMonthly += $monthlyReports->count();

                if (!$isSunday && ($isPast || $isToday)) {
                    $workdayCount++;
                    $submittedWorkdayDailyCount += $dailySubmittedCount;
                }
            }

            $missingDaily = (!$isSunday && $isPast && $dailySubmittedCount < $expectedDailyCount)
                ? ($expectedDailyCount - $dailySubmittedCount)
                : 0;

            $calendarDays[] = [
                'date' => $day->copy(),
                'date_str' => $dStr,
                'day' => $day->day,
                'day_name' => $day->format('D'),
                'is_current_month' => $isCurrentMonth,
                'is_today' => $isToday,
                'is_sunday' => $isSunday,
                'is_past' => $isPast,
                'reports' => $dayReports,
                'daily_reports' => $dailyReports,
                'weekly_reports' => $weeklyReports,
                'monthly_reports' => $monthlyReports,
                'daily_submitted_count' => $dailySubmittedCount,
                'daily_expected_count' => $expectedDailyCount,
                'missing_daily' => $missingDaily,
            ];
        }

        $expectedTotalSoFar = $workdayCount * $expectedDailyCount;
        $complianceRate = $expectedTotalSoFar > 0
            ? min(100, round(($submittedWorkdayDailyCount / $expectedTotalSoFar) * 100, 1))
            : 100.0;

        return view('production-reports.calendar', [
            'user' => $user,
            'role' => $role,
            'year' => $year,
            'month' => $month,
            'startOfMonth' => $startOfMonth,
            'prevDate' => $prevDate,
            'nextDate' => $nextDate,
            'calendarDays' => $calendarDays,
            'daysInMonth' => $calendarDays,
            'teamFilter' => $teamFilter,
            'teamMembers' => $teamMembers,
            'expectedDailyCount' => $expectedDailyCount,
            'totalMonthReports' => $totalMonthReports,
            'totalMonthDaily' => $totalMonthDaily,
            'totalMonthWeekly' => $totalMonthWeekly,
            'totalMonthMonthly' => $totalMonthMonthly,
            'complianceRate' => $complianceRate,
            'permissionService' => $this->permissionService,
        ]);
    }

    /**
     * Media & Attachments Gallery (/production-reports/media)
     */
    public function mediaGallery(Request $request): View
    {
        $user = $request->user();
        $role = $this->permissionService->resolveRole($user);
        abort_if($role === null, 403);
        abort_unless($this->permissionService->canSeeFullMenu($user), 403, 'This section is available to Production Heads and the Supervisor only.');

        $query = $this->permissionService->scopeMediaQuery(
            ProductionReportMedia::with(['creator', 'uploader', 'report']),
            $user
        );

        if ($request->filled('media_type')) {
            $query->where('media_type', $request->get('media_type'));
        }
        if ($request->filled('team') && $this->permissionService->isSupervisor($user)) {
            $query->where('team_code', strtoupper($request->get('team')));
        }
        if ($request->boolean('highlighted_only')) {
            $query->where('is_highlighted', true);
        }
        if ($request->filled('q')) {
            $q = trim((string) $request->get('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('project_category', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        $mediaItems = $query->orderByDesc('is_highlighted')->orderByDesc('production_date')->orderByDesc('id')->paginate(24)->withQueryString();

        return view('production-reports.media', [
            'user' => $user,
            'role' => $role,
            'mediaItems' => $mediaItems,
            'permissionService' => $this->permissionService,
        ]);
    }

    /**
     * Report Archive (/production-reports/archive)
     */
    public function archive(Request $request): View
    {
        $user = $request->user();
        $role = $this->permissionService->resolveRole($user);
        abort_if($role === null, 403);
        abort_unless($this->permissionService->canSeeFullMenu($user), 403, 'This section is available to Production Heads and the Supervisor only.');

        $query = $this->buildFilteredArchiveQuery($request, $user);
        $reports = $query->orderByDesc('reporting_start_date')->orderByDesc('id')->paginate(25)->withQueryString();

        $availableMembers = match ($role) {
            ProductionReportTeamMapping::ROLE_SUPERVISOR => $this->permissionService->getTeamMembers('A')->concat($this->permissionService->getTeamMembers('B'))->unique('id'),
            ProductionReportTeamMapping::ROLE_HEAD => $this->permissionService->getTeamMembers($this->permissionService->getAssignedTeamCode($user) ?: 'A'),
            default => collect([$user]),
        };

        return view('production-reports.archive', [
            'user' => $user,
            'role' => $role,
            'reports' => $reports,
            'availableMembers' => $availableMembers,
            'permissionService' => $this->permissionService,
        ]);
    }

    /**
     * Team Mapping & Deadline Settings (/production-reports/settings)
     */
    public function settings(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->permissionService->canManageSettings($user), 403);

        $this->permissionService->syncDefaultMappingsIfNeeded();
        $deadline = ProductionReportDeadline::current();

        $mappingsQuery = ProductionReportTeamMapping::with('user')
            ->whereHas('user', fn ($u) => $u->where('is_active', true))
            ->where('is_active', true)
            ->orderBy('team_code')
            ->orderBy('role_in_reporting');
        if ($this->permissionService->isProductionHead($user)) {
            $headTeam = $this->permissionService->getAssignedTeamCode($user);
            $mappingsQuery->where('team_code', $headTeam);
        }

        $mappings = $mappingsQuery->get();
        $allActiveUsers = User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'username', 'team', 'team_role']);

        return view('production-reports.settings', [
            'user' => $user,
            'role' => $this->permissionService->resolveRole($user),
            'deadline' => $deadline,
            'mappings' => $mappings,
            'allActiveUsers' => $allActiveUsers,
            'permissionService' => $this->permissionService,
        ]);
    }

    /**
     * Update Deadline Configuration (Supervisor only)
     */
    public function updateDeadlines(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->permissionService->isSupervisor($user), 403, 'Only the Production Supervisor can modify global deadline configurations.');

        $validated = $request->validate([
            'daily_deadline_time' => ['required', 'string', 'max:10'],
            'daily_reminder_time' => ['required', 'string', 'max:10'],
            'weekly_deadline_day' => ['required', 'string', 'max:20'],
            'weekly_deadline_time' => ['required', 'string', 'max:10'],
            'weekly_reminder_time' => ['required', 'string', 'max:10'],
            'weekly_period_start_day' => ['required', 'string', 'max:20'],
            'weekly_period_end_day' => ['required', 'string', 'max:20'],
            'monthly_deadline_mode' => ['required', 'string', 'in:last_day_of_month,specific_day,day_of_next_month'],
            'monthly_deadline_day' => ['required', 'integer', 'min:1', 'max:31'],
            'monthly_deadline_time' => ['required', 'string', 'max:10'],
            'allow_head_daily_submission' => ['nullable', 'boolean'],
            'allow_head_monthly_submission' => ['nullable', 'boolean'],
        ]);

        $deadline = ProductionReportDeadline::current();
        $deadline->update([
            'daily_deadline_time' => $validated['daily_deadline_time'],
            'daily_reminder_time' => $validated['daily_reminder_time'],
            'weekly_deadline_day' => $validated['weekly_deadline_day'],
            'weekly_deadline_time' => $validated['weekly_deadline_time'],
            'weekly_reminder_time' => $validated['weekly_reminder_time'],
            'weekly_period_start_day' => $validated['weekly_period_start_day'],
            'weekly_period_end_day' => $validated['weekly_period_end_day'],
            'monthly_deadline_mode' => $validated['monthly_deadline_mode'],
            'monthly_deadline_day' => $validated['monthly_deadline_day'],
            'monthly_deadline_time' => $validated['monthly_deadline_time'],
            'allow_head_daily_submission' => $request->boolean('allow_head_daily_submission'),
            'allow_head_monthly_submission' => $request->boolean('allow_head_monthly_submission'),
            'updated_by' => $user->id,
        ]);

        return redirect()->route('production-reports.settings')->with('success', 'Production Report deadlines and schedule settings updated.');
    }

    /**
     * Upsert a Team Member Mapping (Supervisor or Head for their own team)
     */
    public function upsertTeamMapping(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->permissionService->canManageSettings($user), 403);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'team_code' => ['required', 'string', 'in:A,B,ALL'],
            'role_in_reporting' => ['required', 'string', 'in:supervisor,head,member'],
            'position_title' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $targetUser = User::findOrFail((int) $validated['user_id']);

        // Head can only manage members in their own assigned team
        if ($this->permissionService->isProductionHead($user)) {
            $headTeam = $this->permissionService->getAssignedTeamCode($user);
            if ($validated['team_code'] !== $headTeam || $validated['role_in_reporting'] !== ProductionReportTeamMapping::ROLE_MEMBER) {
                abort(403, 'Production Heads can only manage members within their own assigned team.');
            }
        }

        // Protect Somalika as view-only supervisor
        if (strtolower((string) $targetUser->username) === 'somalika') {
            $validated['role_in_reporting'] = ProductionReportTeamMapping::ROLE_SUPERVISOR;
            $validated['team_code'] = 'ALL';
        }

        $isSupervisorRole = $validated['role_in_reporting'] === ProductionReportTeamMapping::ROLE_SUPERVISOR;
        $isHeadRole = $validated['role_in_reporting'] === ProductionReportTeamMapping::ROLE_HEAD;

        ProductionReportTeamMapping::updateOrCreate(
            ['user_id' => $targetUser->id],
            [
                'team_code' => $validated['team_code'],
                'team_name' => $isSupervisorRole
                    ? 'All Production Teams (Supervisor)'
                    : $this->permissionService->getTeamName($validated['team_code']),
                'role_in_reporting' => $validated['role_in_reporting'],
                'position_title' => $validated['position_title'] ?: ($targetUser->team_role ?: ucfirst($validated['role_in_reporting'])),
                'can_submit_daily' => !$isSupervisorRole && !$isHeadRole,
                'can_submit_weekly' => $isHeadRole,
                'can_submit_monthly' => !$isSupervisorRole,
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
                'assigned_by' => $user->id,
            ]
        );

        return redirect()->route('production-reports.settings')->with('success', "Updated Production Report team mapping for {$targetUser->name}.");
    }

    /**
     * Remove / Deactivate a Team Member Mapping (Supervisor or Head for their own team)
     */
    public function removeTeamMapping(Request $request, ProductionReportTeamMapping $mapping): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->permissionService->canRemoveTeamMember($user, $mapping), 403, 'You are not authorized to remove this team member.');

        $memberName = $mapping->user?->name ?? 'Member';
        $teamCode = $mapping->team_code;

        $mapping->update(['is_active' => false]);

        return redirect()->route('production-reports.settings')
            ->with('success', "Removed {$memberName} from Team {$teamCode} production reporting.");
    }

    protected function buildFilteredArchiveQuery(Request $request, User $user)
    {
        $query = $this->permissionService->scopeReportsQuery(
            ProductionReport::with(['user', 'media'])->withCount(['items', 'media']),
            $user
        );

        if ($request->filled('year')) {
            $query->where('reporting_year', (int) $request->get('year'));
        }
        if ($request->filled('month')) {
            $query->where('reporting_month', (int) $request->get('month'));
        }
        if ($request->filled('team') && in_array(strtoupper($request->get('team')), ['A', 'B'], true)) {
            if ($this->permissionService->isSupervisor($user)) {
                $query->where('team_code', strtoupper($request->get('team')));
            }
        }
        if ($request->filled('member_id')) {
            $query->where('user_id', (int) $request->get('member_id'));
        }
        if ($request->filled('report_type') && in_array($request->get('report_type'), ['daily', 'weekly', 'monthly'], true)) {
            $query->where('report_type', $request->get('report_type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }
        if ($request->filled('from')) {
            $query->whereDate('reporting_start_date', '>=', $request->get('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('reporting_end_date', '<=', $request->get('to'));
        }

        return $query;
    }
}

