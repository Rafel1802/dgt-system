<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\Kpi\KpiAuditLog;
use App\Models\Kpi\KpiPeriod;
use App\Models\Kpi\KpiReview;
use App\Models\Kpi\KpiSquad;
use App\Models\Kpi\KpiSupervisorReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KpiSupervisorReportController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isSupervisor = $user->isKpiSupervisor();
        $userSquadId = $user->getKpiSquadId();

        $currentPeriod = KpiPeriod::where('status', 'Open')->latest('id')->first() ?? KpiPeriod::latest('id')->first();

        $reports = KpiSupervisorReport::with(['squad.lead', 'period', 'submittedBy', 'reviewedBy'])
            ->when($currentPeriod, fn($q) => $q->where('kpi_period_id', $currentPeriod->id))
            ->when(!$isSupervisor && $userSquadId, fn($q) => $q->where('squad_id', $userSquadId))
            ->get();

        $squads = $isSupervisor ? KpiSquad::all() : KpiSquad::where('id', $userSquadId)->get();

        return view('kpi.supervisor-reports', compact('reports', 'squads', 'isSupervisor', 'currentPeriod'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $userSquadId = $user->getKpiSquadId();

        $validated = $request->validate([
            'squad_id' => 'required|exists:kpi_squads,id',
            'summary' => 'required|string',
            'strengths' => 'nullable|string',
            'improvements' => 'nullable|string',
        ]);

        if (!$user->isKpiSupervisor() && $userSquadId != $validated['squad_id']) {
            abort(403, 'You can only submit monthly reports for your own squad.');
        }

        $currentPeriod = KpiPeriod::where('status', 'Open')->latest('id')->first() ?? KpiPeriod::latest('id')->first();

        // Calculate squad average KPI from reviews
        $avgKpi = KpiReview::whereHas('assignment', fn($q) => $q->where('squad_id', $validated['squad_id']))
            ->where('kpi_period_id', $currentPeriod?->id ?? 1)
            ->avg('overall_kpi') ?: 95.0;

        $report = KpiSupervisorReport::updateOrCreate(
            [
                'squad_id' => $validated['squad_id'],
                'kpi_period_id' => $currentPeriod?->id ?? 1,
            ],
            [
                'submitted_by' => $user->id,
                'overall_team_kpi' => $avgKpi,
                'team_productivity' => 95.0,
                'team_quality' => 96.0,
                'team_deadline' => 94.0,
                'status' => 'Submitted',
                'summary' => $validated['summary'],
                'strengths' => $validated['strengths'] ?? null,
                'improvements' => $validated['improvements'] ?? null,
                'submitted_at' => now(),
            ]
        );

        KpiAuditLog::create([
            'user_id' => $user->id,
            'action' => 'supervisor_report_submitted',
            'entity_type' => KpiSupervisorReport::class,
            'entity_id' => $report->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => ['squad_id' => $report->squad_id, 'status' => 'Submitted'],
        ]);

        return redirect()->back()->with('success', 'Monthly Squad Report submitted to Supervisor for approval.');
    }

    public function approve(Request $request, KpiSupervisorReport $report): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isKpiSupervisor()) {
            abort(403, 'Only Supervisor or SuperAdmin can approve squad reports.');
        }

        $validated = $request->validate([
            'supervisor_notes' => 'nullable|string',
        ]);

        $report->update([
            'reviewed_by' => $user->id,
            'status' => 'Approved',
            'supervisor_notes' => $validated['supervisor_notes'] ?? null,
            'reviewed_at' => now(),
        ]);

        KpiAuditLog::create([
            'user_id' => $user->id,
            'action' => 'supervisor_report_approved',
            'entity_type' => KpiSupervisorReport::class,
            'entity_id' => $report->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => ['status' => 'Approved'],
        ]);

        return redirect()->back()->with('success', 'Squad report approved successfully.');
    }

    public function finalize(Request $request, KpiSupervisorReport $report): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isKpiSupervisor()) {
            abort(403, 'Only Supervisor or SuperAdmin can finalize squad reports.');
        }

        $report->update(['status' => 'Finalized']);

        KpiAuditLog::create([
            'user_id' => $user->id,
            'action' => 'supervisor_report_finalized',
            'entity_type' => KpiSupervisorReport::class,
            'entity_id' => $report->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => ['status' => 'Finalized'],
        ]);

        return redirect()->back()->with('success', 'Squad report marked as Finalized.');
    }
}
