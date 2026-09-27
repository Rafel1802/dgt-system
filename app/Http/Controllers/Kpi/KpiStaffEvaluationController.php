<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\Kpi\KpiAuditLog;
use App\Models\Kpi\KpiPeriod;
use App\Models\Kpi\KpiReview;
use App\Models\Kpi\KpiSquad;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KpiStaffEvaluationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user() ?: auth()->user();
        $isSupervisor = $user ? $user->isKpiSupervisor() : false;
        $userSquadId = $user ? $user->getKpiSquadId() : null;

        // 1. Periods & Date Filtering (Month & Year)
        $periods = KpiPeriod::orderBy('start_date', 'desc')->get();
        $selectedMonth = $request->get('month');
        $selectedYear = $request->get('year');
        $periodId = $request->get('period_id');

        if ($selectedMonth && $selectedYear) {
            $periodName = date('F Y', mktime(0, 0, 0, (int)$selectedMonth, 1, (int)$selectedYear));
            $startDate = date('Y-m-01', mktime(0, 0, 0, (int)$selectedMonth, 1, (int)$selectedYear));
            $endDate = date('Y-m-t', mktime(0, 0, 0, (int)$selectedMonth, 1, (int)$selectedYear));
            $currentPeriod = KpiPeriod::firstOrCreate(
                ['name' => $periodName],
                [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => 'Open',
                    'description' => "Monthly KPI Evaluation Cycle for {$periodName}."
                ]
            );
        } elseif ($periodId) {
            $currentPeriod = KpiPeriod::find($periodId);
        } else {
            $currentPeriod = $periods->firstWhere('status', 'Open') ?? $periods->first();
        }

        // 2. Selected Squad
        $selectedSquadId = $request->get('squad_id');
        if (!$isSupervisor) {
            $selectedSquadId = $userSquadId ?: 1;
        }

        $squadsQuery = KpiSquad::with(['lead', 'members']);
        if (!$isSupervisor && $userSquadId) {
            $squadsQuery->where('id', $userSquadId);
        }
        $squads = $squadsQuery->get();

        $currentSquad = $selectedSquadId
            ? $squads->firstWhere('id', $selectedSquadId)
            : $squads->first();

        // 3. Staff members under current squad (with Search & Member filters)
        $rawMembers = $currentSquad ? $currentSquad->members : collect();

        $search = trim($request->get('search', ''));
        $memberId = $request->get('member_id');

        $staffMembers = $rawMembers;
        if (!empty($search)) {
            $staffMembers = $staffMembers->filter(function($m) use ($search) {
                return str_contains(strtolower($m->name), strtolower($search))
                    || str_contains(strtolower($m->username ?? ''), strtolower($search))
                    || str_contains(strtolower($m->pivot->role_title ?? ''), strtolower($search));
            });
        }
        if (!empty($memberId)) {
            $staffMembers = $staffMembers->where('id', (int)$memberId);
        }

        // 4. Current Period Reviews for this squad
        $reviews = KpiReview::with(['user', 'reviewer', 'squad'])
            ->when($currentPeriod, fn($q) => $q->where('kpi_period_id', $currentPeriod->id))
            ->when($currentSquad, fn($q) => $q->where('squad_id', $currentSquad->id))
            ->get()
            ->keyBy('user_id');

        // 5. Complete KPI History for all staff members in this squad (grouped by user_id)
        $allUserReviews = KpiReview::with(['period', 'reviewer', 'squad'])
            ->whereIn('user_id', $rawMembers->pluck('id'))
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('user_id');

        $evaluatedCount = $reviews->count();
        $avgKpiScore = ($evaluatedCount > 0 && $reviews->avg('score') !== null) ? round((float)$reviews->avg('score'), 1) : 0;
        $outstandingCount = $reviews->where('score', '>=', 90)->count();

        return view('kpi.evaluations', compact(
            'periods',
            'currentPeriod',
            'squads',
            'currentSquad',
            'selectedSquadId',
            'rawMembers',
            'staffMembers',
            'reviews',
            'allUserReviews',
            'isSupervisor',
            'userSquadId',
            'evaluatedCount',
            'avgKpiScore',
            'outstandingCount',
            'search',
            'memberId',
            'selectedMonth',
            'selectedYear'
        ));
    }

    public function rate(Request $request): RedirectResponse
    {
        $user = $request->user() ?: auth()->user();
        $isSupervisor = $user->isKpiSupervisor();
        $userSquadId = $user->getKpiSquadId();

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'squad_id' => 'required|exists:kpi_squads,id',
            'kpi_period_id' => 'nullable|exists:kpi_periods,id',
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2020|max:2030',
            'evaluation_date' => 'nullable|date',
            'productivity_score' => 'required|numeric|min:0|max:100',
            'quality_score' => 'required|numeric|min:0|max:100',
            'deadline_score' => 'required|numeric|min:0|max:100',
            'teamwork_score' => 'required|numeric|min:0|max:100',
            'manager_notes' => 'required|string',
            'status' => 'required|in:Draft,Submitted,Approved',
            'kpi_pdf' => 'nullable|file|mimes:pdf|max:15360',
        ]);

        if (!$isSupervisor && $userSquadId != $validated['squad_id']) {
            abort(403, 'You can only evaluate staff in your own squad.');
        }

        // Determine / Create Period
        $periodId = $validated['kpi_period_id'] ?? null;
        if (!empty($validated['month']) && !empty($validated['year'])) {
            $m = (int)$validated['month'];
            $y = (int)$validated['year'];
            $periodName = date('F Y', mktime(0, 0, 0, $m, 1, $y));
            $period = KpiPeriod::firstOrCreate(
                ['name' => $periodName],
                [
                    'start_date' => date('Y-m-01', mktime(0, 0, 0, $m, 1, $y)),
                    'end_date' => date('Y-m-t', mktime(0, 0, 0, $m, 1, $y)),
                    'status' => 'Open',
                    'description' => "Monthly KPI Evaluation Cycle for {$periodName}."
                ]
            );
            $periodId = $period->id;
        } elseif (!$periodId) {
            $period = KpiPeriod::latest('id')->first();
            $periodId = $period ? $period->id : 1;
        }

        // Weighted KPI Score calculation:
        // Productivity: 35%, Quality: 35%, TAT Speed: 20%, Teamwork: 10%
        $overall = ($validated['productivity_score'] * 0.35)
                 + ($validated['quality_score'] * 0.35)
                 + ($validated['deadline_score'] * 0.20)
                 + ($validated['teamwork_score'] * 0.10);
        $overall = round($overall, 2);

        $band = 'Meets Expectations';
        if ($overall >= 95.0) {
            $band = 'Outstanding';
        } elseif ($overall >= 85.0) {
            $band = 'Exceeds Expectations';
        } elseif ($overall < 70.0) {
            $band = 'Needs Improvement';
        }

        $evalDate = !empty($validated['evaluation_date']) ? $validated['evaluation_date'] : date('Y-m-d');

        $review = KpiReview::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'kpi_period_id' => $periodId,
            ],
            [
                'squad_id' => $validated['squad_id'],
                'reviewer_id' => $user->id,
                'productivity_score' => $validated['productivity_score'],
                'quality_score' => $validated['quality_score'],
                'deadline_score' => $validated['deadline_score'],
                'teamwork_score' => $validated['teamwork_score'],
                'overall_kpi' => $overall,
                'performance_band' => $band,
                'status' => $validated['status'],
                'manager_notes' => $validated['manager_notes'],
                'evaluation_date' => $evalDate,
                'reviewed_at' => now(),
            ]
        );

        // Upload PDF if provided
        if ($request->hasFile('kpi_pdf')) {
            $file = $request->file('kpi_pdf');
            $filename = 'kpi_' . $review->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('kpi-uploads', $filename, 'public');
            $review->update(['uploaded_pdf_path' => 'storage/' . $path]);
        }

        $staff = User::find($validated['user_id']);

        KpiAuditLog::create([
            'user_id' => $user->id,
            'action' => 'staff_kpi_rated',
            'entity_type' => KpiReview::class,
            'entity_id' => $review->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => [
                'staff' => $staff?->name,
                'overall_kpi' => $overall,
                'performance_band' => $band,
                'status' => $validated['status'],
            ],
        ]);

        return redirect()->back()->with('success', "KPI for {$staff?->name} recorded with score {$overall}% ({$band}).");
    }

    public function uploadPdf(Request $request, KpiReview $review): RedirectResponse
    {
        $request->validate([
            'kpi_pdf' => 'required|file|mimes:pdf|max:15360',
        ]);

        $file = $request->file('kpi_pdf');
        $filename = 'kpi_' . $review->id . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('kpi-uploads', $filename, 'public');

        $review->update([
            'uploaded_pdf_path' => 'storage/' . $path,
        ]);

        KpiAuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'kpi_pdf_uploaded',
            'entity_type' => KpiReview::class,
            'entity_id' => $review->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => ['file' => $filename, 'staff' => $review->user?->name],
        ]);

        return redirect()->back()->with('success', "Signed KPI PDF for {$review->user?->name} uploaded successfully!");
    }

    public function uploadDirect(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'squad_id' => 'required|exists:kpi_squads,id',
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2020|max:2030',
            'evaluation_date' => 'nullable|date',
            'kpi_pdf' => 'required|file|mimes:pdf|max:15360',
        ]);

        $m = !empty($validated['month']) ? (int)$validated['month'] : (int)date('n');
        $y = !empty($validated['year']) ? (int)$validated['year'] : (int)date('Y');
        $periodName = date('F Y', mktime(0, 0, 0, $m, 1, $y));
        $period = KpiPeriod::firstOrCreate(
            ['name' => $periodName],
            [
                'start_date' => date('Y-m-01', mktime(0, 0, 0, $m, 1, $y)),
                'end_date' => date('Y-m-t', mktime(0, 0, 0, $m, 1, $y)),
                'status' => 'Open',
                'description' => "Monthly KPI Evaluation Cycle for {$periodName}."
            ]
        );

        $evalDate = !empty($validated['evaluation_date']) ? $validated['evaluation_date'] : date('Y-m-d');

        $review = KpiReview::firstOrNew([
            'user_id' => $validated['user_id'],
            'kpi_period_id' => $period->id,
        ]);

        $review->squad_id = $validated['squad_id'];
        $review->reviewer_id = $request->user()->id;
        if (!$review->overall_kpi) {
            $review->productivity_score = 92;
            $review->quality_score = 94;
            $review->deadline_score = 90;
            $review->teamwork_score = 92;
            $review->overall_kpi = 92.3;
            $review->performance_band = 'Exceeds Expectations';
        }
        $review->status = 'Approved';
        $review->evaluation_date = $evalDate;
        $review->reviewed_at = now();

        $file = $request->file('kpi_pdf');
        $filename = 'kpi_' . ($review->id ?: time()) . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('kpi-uploads', $filename, 'public');
        $review->uploaded_pdf_path = 'storage/' . $path;
        $review->save();

        $staff = User::find($validated['user_id']);

        return redirect()->back()->with('success', "Signed KPI PDF for {$staff?->name} uploaded and recorded for {$periodName}!");
    }

    public function history(Request $request, User $user): JsonResponse
    {
        $reviews = KpiReview::with(['period', 'reviewer', 'squad'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
            ],
            'reviews' => $reviews,
        ]);
    }

    public function approve(Request $request, KpiReview $review): RedirectResponse
    {
        $user = $request->user() ?: auth()->user();
        if (!$user->isKpiSupervisor()) {
            abort(403, 'Only Supervisors or SuperAdmin can approve staff KPI evaluations.');
        }

        $validated = $request->validate([
            'supervisor_notes' => 'nullable|string',
        ]);

        $review->update([
            'status' => 'Approved',
            'supervisor_notes' => $validated['supervisor_notes'] ?? $review->supervisor_notes,
        ]);

        KpiAuditLog::create([
            'user_id' => $user->id,
            'action' => 'staff_kpi_approved',
            'entity_type' => KpiReview::class,
            'entity_id' => $review->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => ['staff' => $review->user?->name, 'status' => 'Approved'],
        ]);

        return redirect()->back()->with('success', "KPI review for {$review->user?->name} has been Approved.");
    }

    public function finalize(Request $request, KpiReview $review): RedirectResponse
    {
        $user = $request->user() ?: auth()->user();
        if (!$user->isKpiSupervisor()) {
            abort(403, 'Only Supervisors or SuperAdmin can finalize staff KPI evaluations.');
        }

        $review->update(['status' => 'Finalized']);

        KpiAuditLog::create([
            'user_id' => $user->id,
            'action' => 'staff_kpi_finalized',
            'entity_type' => KpiReview::class,
            'entity_id' => $review->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => ['staff' => $review->user?->name, 'status' => 'Finalized'],
        ]);

        return redirect()->back()->with('success', "KPI for {$review->user?->name} has been Finalized.");
    }

    public function exportStaffPdf(Request $request, KpiReview $review)
    {
        $user = $request->user() ?: auth()->user();
        $isSupervisor = $user->isKpiSupervisor();
        $userSquadId = $user->getKpiSquadId();

        $reviewSquadId = $review->squad_id ?? $review->user?->getKpiSquadId();
        if (!$isSupervisor && $userSquadId && $reviewSquadId && $userSquadId != $reviewSquadId) {
            abort(403, 'Unauthorized to view this staff report.');
        }

        $review->load(['user.kpiSquads', 'reviewer', 'squad.lead', 'period']);
        if (!$review->squad_id && $reviewSquadId) {
            $review->squad_id = $reviewSquadId;
            $review->load('squad.lead');
        }

        $pdf = Pdf::loadView('kpi.staff-pdf', compact('review'));

        $safeName = str_replace(' ', '_', $review->user?->name ?? 'Staff');
        $periodName = str_replace(' ', '_', $review->period?->name ?? 'Month');

        return $pdf->download("KPI_Evaluation_{$safeName}_{$periodName}.pdf");
    }

    public function exportAllStaffPdf(Request $request)
    {
        $user = $request->user() ?: auth()->user();
        $isSupervisor = $user->isKpiSupervisor();
        $userSquadId = $user->getKpiSquadId();

        $squadId = $request->get('squad_id');
        if (!$isSupervisor) {
            $squadId = $userSquadId ?: 1;
        }

        $squad = KpiSquad::with(['lead', 'members'])->find($squadId) ?: KpiSquad::with(['lead', 'members'])->first();
        if (!$squad) {
            abort(404, 'Squad not found.');
        }

        $selectedMonth = $request->get('month');
        $selectedYear = $request->get('year');
        $periodId = $request->get('period_id');

        if ($selectedMonth && $selectedYear) {
            $periodName = date('F Y', mktime(0, 0, 0, (int)$selectedMonth, 1, (int)$selectedYear));
            $period = KpiPeriod::firstOrCreate(
                ['name' => $periodName],
                [
                    'start_date' => date('Y-m-01', mktime(0, 0, 0, (int)$selectedMonth, 1, (int)$selectedYear)),
                    'end_date' => date('Y-m-t', mktime(0, 0, 0, (int)$selectedMonth, 1, (int)$selectedYear)),
                    'status' => 'Open',
                    'description' => "Monthly KPI Evaluation Cycle for {$periodName}."
                ]
            );
        } elseif ($periodId) {
            $period = KpiPeriod::find($periodId);
        } else {
            $period = KpiPeriod::latest('id')->first();
        }

        $targetUserId = $request->get('user_id');
        $customDate = $request->get('evaluation_date');

        if (!empty($targetUserId) && $targetUserId !== 'all') {
            $staffMembers = $squad->members->where('id', (int)$targetUserId);
        } else {
            $staffMembers = $squad->members;
        }

        $reviews = KpiReview::with(['user', 'reviewer'])
            ->where('squad_id', $squad->id)
            ->where('kpi_period_id', $period?->id ?? 1)
            ->get()
            ->keyBy('user_id');

        $pdf = Pdf::loadView('kpi.all-staff-pdf', compact('squad', 'period', 'staffMembers', 'reviews', 'customDate'));

        $squadCode = $squad->code ?: 'SQUAD';
        $periodName = str_replace(' ', '_', $period?->name ?? 'Month');

        if ($staffMembers->count() === 1) {
            $singleStaff = $staffMembers->first();
            $safeName = str_replace(' ', '_', $singleStaff->name);
            return $pdf->download("KPI_Evaluation_{$safeName}_{$periodName}.pdf");
        }

        return $pdf->download("All_Staff_KPI_{$squadCode}_{$periodName}.pdf");
    }

    public function exportSquadPdf(Request $request, KpiSquad $squad)
    {
        $user = $request->user() ?: auth()->user();
        $isSupervisor = $user->isKpiSupervisor();
        $userSquadId = $user->getKpiSquadId();

        if (!$isSupervisor && $userSquadId != $squad->id) {
            abort(403, 'Unauthorized to export this squad report.');
        }

        $periodId = $request->get('period_id');
        $period = $periodId ? KpiPeriod::find($periodId) : KpiPeriod::latest('id')->first();

        $reviews = KpiReview::with(['user', 'reviewer'])
            ->where('squad_id', $squad->id)
            ->where('kpi_period_id', $period?->id ?? 1)
            ->get();

        $pdf = Pdf::loadView('kpi.squad-pdf', compact('squad', 'period', 'reviews'));

        $squadCode = $squad->code;
        $periodName = str_replace(' ', '_', $period?->name ?? 'Month');

        return $pdf->download("Squad_KPI_Summary_{$squadCode}_{$periodName}.pdf");
    }
}
