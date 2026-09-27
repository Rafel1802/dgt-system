<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\Kpi\KpiAssignment;
use App\Models\Kpi\KpiAuditLog;
use App\Models\Kpi\KpiPeriod;
use App\Models\Kpi\KpiReport;
use App\Models\Kpi\KpiReview;
use App\Models\Kpi\KpiSquad;
use App\Models\Kpi\KpiSupervisorReport;
use App\Models\Kpi\KpiTask;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KpiExportController extends Controller
{
    public function exportPdf(Request $request)
    {
        $user = $request->user();
        $isSupervisor = $user->isKpiSupervisor();
        $userSquadId = $user->getKpiSquadId();

        $currentPeriod = KpiPeriod::where('status', 'Open')->latest('id')->first() ?? KpiPeriod::latest('id')->first();

        $squads = KpiSquad::with('lead')
            ->when(!$isSupervisor && $userSquadId, fn($q) => $q->where('id', $userSquadId))
            ->get();

        $assignments = KpiAssignment::with(['user', 'squad', 'items', 'review'])
            ->when($currentPeriod, fn($q) => $q->where('kpi_period_id', $currentPeriod->id))
            ->when(!$isSupervisor && $userSquadId, fn($q) => $q->where('squad_id', $userSquadId))
            ->get();

        $tasks = KpiTask::with(['assignee', 'squad'])
            ->when($currentPeriod, fn($q) => $q->where('kpi_period_id', $currentPeriod->id))
            ->when(!$isSupervisor && $userSquadId, fn($q) => $q->where('squad_id', $userSquadId))
            ->get();

        $reviews = KpiReview::with(['user', 'assignment.squad'])
            ->when($currentPeriod, fn($q) => $q->where('kpi_period_id', $currentPeriod->id))
            ->when(!$isSupervisor && $userSquadId, function ($q) use ($userSquadId) {
                $q->whereHas('assignment', fn($sq) => $sq->where('squad_id', $userSquadId));
            })
            ->get();

        $reports = KpiSupervisorReport::with(['squad', 'submittedBy'])
            ->when($currentPeriod, fn($q) => $q->where('kpi_period_id', $currentPeriod->id))
            ->when(!$isSupervisor && $userSquadId, fn($q) => $q->where('squad_id', $userSquadId))
            ->get();

        $pdf = Pdf::loadView('kpi.pdf', compact('currentPeriod', 'squads', 'assignments', 'tasks', 'reviews', 'reports'));

        KpiAuditLog::create([
            'user_id' => $user->id,
            'action' => 'kpi_pdf_exported',
            'entity_type' => KpiReport::class,
            'entity_id' => $currentPeriod?->id ?? 1,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => ['type' => 'pdf', 'period' => $currentPeriod?->name],
        ]);

        return $pdf->download("DigitalMedia_KPI_Summary_{$currentPeriod?->name}.pdf");
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $user = $request->user();
        $isSupervisor = $user->isKpiSupervisor();
        $userSquadId = $user->getKpiSquadId();

        $currentPeriod = KpiPeriod::where('status', 'Open')->latest('id')->first() ?? KpiPeriod::latest('id')->first();

        $tasks = KpiTask::with(['assignee', 'squad'])
            ->when($currentPeriod, fn($q) => $q->where('kpi_period_id', $currentPeriod->id))
            ->when(!$isSupervisor && $userSquadId, fn($q) => $q->where('squad_id', $userSquadId))
            ->get();

        $fileName = "kpi_deliverables_{$currentPeriod?->name}.csv";

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$fileName\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($tasks) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Task Title', 'Squad', 'Assignee', 'Priority', 'Status', 'Due Date', 'Quality Score', 'Completed At']);

            foreach ($tasks as $t) {
                fputcsv($handle, [
                    $t->id,
                    $t->title,
                    $t->squad?->name ?? 'N/A',
                    $t->assignee?->name ?? 'N/A',
                    $t->priority,
                    $t->status,
                    $t->due_date?->format('Y-m-d') ?? 'N/A',
                    $t->quality_score ? $t->quality_score . '%' : 'N/A',
                    $t->completed_at?->format('Y-m-d H:i') ?? 'N/A',
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }
}
