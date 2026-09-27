<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\Kpi\KpiAssignment;
use App\Models\Kpi\KpiAuditLog;
use App\Models\Kpi\KpiPeriod;
use App\Models\Kpi\KpiReview;
use App\Models\Kpi\KpiReviewItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KpiReviewController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isSupervisor = $user->isKpiSupervisor();
        $userSquadId = $user->getKpiSquadId();

        $currentPeriod = KpiPeriod::where('status', 'Open')->latest('id')->first() ?? KpiPeriod::latest('id')->first();

        $reviews = KpiReview::with(['assignment.squad', 'user', 'reviewer', 'items.assignmentItem'])
            ->when($currentPeriod, fn($q) => $q->where('kpi_period_id', $currentPeriod->id))
            ->when(!$isSupervisor && $userSquadId, function ($q) use ($userSquadId) {
                $q->whereHas('assignment', fn($sq) => $sq->where('squad_id', $userSquadId));
            })
            ->get();

        $assignments = KpiAssignment::with(['user', 'squad'])
            ->when($currentPeriod, fn($q) => $q->where('kpi_period_id', $currentPeriod->id))
            ->get();

        return view('kpi.reviews', compact('reviews', 'assignments', 'isSupervisor', 'currentPeriod'));
    }

    public function evaluate(Request $request, KpiAssignment $assignment): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isKpiSupervisor()) {
            abort(403, 'Evaluation requires supervisor privileges.');
        }

        $validated = $request->validate([
            'productivity_score' => 'required|numeric|min:0|max:100',
            'quality_score' => 'required|numeric|min:0|max:100',
            'deadline_score' => 'required|numeric|min:0|max:100',
            'teamwork_score' => 'required|numeric|min:0|max:100',
            'manager_notes' => 'nullable|string',
        ]);

        // Calculate weighted score (Prod 35%, Qual 35%, Dead 20%, Team 10%)
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
                'kpi_assignment_id' => $assignment->id,
            ],
            [
                'user_id' => $assignment->user_id,
                'reviewer_id' => $user->id,
                'kpi_period_id' => $assignment->kpi_period_id,
                'productivity_score' => $validated['productivity_score'],
                'quality_score' => $validated['quality_score'],
                'deadline_score' => $validated['deadline_score'],
                'teamwork_score' => $validated['teamwork_score'],
                'overall_kpi' => $overall,
                'performance_band' => $band,
                'status' => 'Approved',
                'manager_notes' => $validated['manager_notes'] ?? null,
                'reviewed_at' => now(),
            ]
        );

        $assignment->update(['status' => 'Evaluated']);

        KpiAuditLog::create([
            'user_id' => $user->id,
            'action' => 'kpi_evaluated',
            'entity_type' => KpiReview::class,
            'entity_id' => $review->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => ['overall_kpi' => $overall, 'performance_band' => $band],
        ]);

        return redirect()->back()->with('success', "Evaluation completed: Overall KPI score {$overall}% ({$band}).");
    }

    public function finalize(Request $request, KpiReview $review): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isKpiSupervisor()) {
            abort(403, 'Finalizing KPI requires supervisor privileges.');
        }

        $review->update(['status' => 'Finalized']);
        $review->assignment?->update(['status' => 'Finalized']);

        KpiAuditLog::create([
            'user_id' => $user->id,
            'action' => 'kpi_finalized',
            'entity_type' => KpiReview::class,
            'entity_id' => $review->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => ['status' => 'Finalized'],
        ]);

        return redirect()->back()->with('success', 'KPI review officially finalized and signed off.');
    }
}
