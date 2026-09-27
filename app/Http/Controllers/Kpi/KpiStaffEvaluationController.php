<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\Kpi\KpiAuditLog;
use App\Models\Kpi\KpiPeriod;
use App\Models\Kpi\KpiReview;
use App\Models\Kpi\KpiSquad;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KpiStaffEvaluationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isSupervisor = $user->isKpiSupervisor();
        $userSquadId = $user->getKpiSquadId();

        // 1. Periods & Selected Period
        $periods = KpiPeriod::orderBy('start_date', 'desc')->get();
        $periodId = $request->get('period_id');
        $currentPeriod = $periodId
            ? KpiPeriod::find($periodId)
            : ($periods->firstWhere('status', 'Open') ?? $periods->first());

        // 2. Selected Squad
        $selectedSquadId = $request->get('squad_id');
        if (!$isSupervisor) {
            $selectedSquadId = $userSquadId;
        }

        $squadsQuery = KpiSquad::with(['lead', 'members']);
        if (!$isSupervisor && $userSquadId) {
            $squadsQuery->where('id', $userSquadId);
        }
        $squads = $squadsQuery->get();

        $currentSquad = $selectedSquadId
            ? $squads->firstWhere('id', $selectedSquadId)
            : $squads->first();

        // 3. Staff members under current squad
        $staffMembers = $currentSquad ? $currentSquad->members : collect();

        // 4. Reviews for this squad & period
        $reviews = KpiReview::with(['user', 'reviewer', 'squad'])
            ->when($currentPeriod, fn($q) => $q->where('kpi_period_id', $currentPeriod->id))
            ->when($currentSquad, fn($q) => $q->where('squad_id', $currentSquad->id))
            ->get()
            ->keyBy('user_id');

        return view('kpi.evaluations', compact(
            'periods',
            'currentPeriod',
            'squads',
            'currentSquad',
            'staffMembers',
            'reviews',
            'isSupervisor',
            'userSquadId'
        ));
    }

    public function rate(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isSupervisor = $user->isKpiSupervisor();
        $userSquadId = $user->getKpiSquadId();

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'squad_id' => 'required|exists:kpi_squads,id',
            'kpi_period_id' => 'required|exists:kpi_periods,id',
            'productivity_score' => 'required|numeric|min:0|max:100',
            'quality_score' => 'required|numeric|min:0|max:100',
            'deadline_score' => 'required|numeric|min:0|max:100',
            'teamwork_score' => 'required|numeric|min:0|max:100',
            'manager_notes' => 'required|string',
            'status' => 'required|in:Draft,Submitted,Approved',
        ]);

        if (!$isSupervisor && $userSquadId != $validated['squad_id']) {
            abort(403, 'You can only evaluate staff in your own squad.');
        }

        // Weighted KPI Score calculation:
        // Productivity: 35%, Quality: 35%, Speed/TAT: 20%, Teamwork: 10%
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

        $review = KpiReview::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'kpi_period_id' => $validated['kpi_period_id'],
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
                'reviewed_at' => now(),
            ]
        );

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

        return redirect()->back()->with('success', "KPI for {$staff?->name} saved with score {$overall}% ({$band}).");
    }

    public function approve(Request $request, KpiReview $review): RedirectResponse
    {
        $user = $request->user();
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
        $user = $request->user();
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
        $user = $request->user();
        $isSupervisor = $user->isKpiSupervisor();
        $userSquadId = $user->getKpiSquadId();

        if (!$isSupervisor && $userSquadId != $review->squad_id) {
            abort(403, 'Unauthorized to view this staff report.');
        }

        $review->load(['user', 'reviewer', 'squad.lead', 'period']);

        $pdf = Pdf::loadView('kpi.staff-pdf', compact('review'));

        $safeName = str_replace(' ', '_', $review->user?->name ?? 'Staff');
        $periodName = str_replace(' ', '_', $review->period?->name ?? 'Month');

        return $pdf->download("KPI_Report_{$safeName}_{$periodName}.pdf");
    }

    public function exportSquadPdf(Request $request, KpiSquad $squad)
    {
        $user = $request->user();
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
