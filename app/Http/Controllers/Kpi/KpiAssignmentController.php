<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\Kpi\KpiAssignment;
use App\Models\Kpi\KpiAssignmentItem;
use App\Models\Kpi\KpiAuditLog;
use App\Models\Kpi\KpiPeriod;
use App\Models\Kpi\KpiSquad;
use App\Models\Kpi\KpiTemplate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KpiAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isSupervisor = $user->isKpiSupervisor();
        $userSquadId = $user->getKpiSquadId();

        $currentPeriod = KpiPeriod::where('status', 'Open')->latest('id')->first() ?? KpiPeriod::latest('id')->first();

        $assignments = KpiAssignment::with(['user', 'squad', 'period', 'items', 'review'])
            ->when($currentPeriod, fn($q) => $q->where('kpi_period_id', $currentPeriod->id))
            ->when(!$isSupervisor && $userSquadId, fn($q) => $q->where('squad_id', $userSquadId))
            ->get();

        $squads = KpiSquad::all();
        $templates = KpiTemplate::with('items')->get();
        $targetUsers = User::whereIn('username', ['dara', 'kim'])->get();

        return view('kpi.assignments', compact('assignments', 'squads', 'templates', 'targetUsers', 'isSupervisor', 'currentPeriod'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isKpiSupervisor()) {
            abort(403, 'Only Supervisors or SuperAdmin can assign monthly KPI targets.');
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'squad_id' => 'required|exists:kpi_squads,id',
            'template_id' => 'nullable|exists:kpi_templates,id',
            'target_deliverables' => 'required|integer|min:1|max:200',
        ]);

        $currentPeriod = KpiPeriod::where('status', 'Open')->latest('id')->first() ?? KpiPeriod::latest('id')->first();

        $assignment = KpiAssignment::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'kpi_period_id' => $currentPeriod?->id ?? 1,
            ],
            [
                'squad_id' => $validated['squad_id'],
                'assigned_by' => $user->id,
                'template_id' => $validated['template_id'] ?? null,
                'target_deliverables' => $validated['target_deliverables'],
                'status' => 'Active',
            ]
        );

        // If template selected, copy items
        if (!empty($validated['template_id'])) {
            $template = KpiTemplate::with('items')->find($validated['template_id']);
            if ($template) {
                $assignment->items()->delete();
                foreach ($template->items as $item) {
                    KpiAssignmentItem::create([
                        'assignment_id' => $assignment->id,
                        'template_item_id' => $item->id,
                        'name' => $item->name,
                        'pillar' => $item->pillar,
                        'weight' => $item->weight,
                        'target_value' => $item->target_value,
                        'unit' => $item->unit,
                    ]);
                }
            }
        }

        KpiAuditLog::create([
            'user_id' => $user->id,
            'action' => 'assignment_saved',
            'entity_type' => KpiAssignment::class,
            'entity_id' => $assignment->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => ['target_deliverables' => $assignment->target_deliverables],
        ]);

        return redirect()->back()->with('success', 'Monthly KPI Target successfully configured.');
    }
}
