<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\Kpi\KpiAuditLog;
use App\Models\Kpi\KpiPeriod;
use App\Models\Kpi\KpiSquad;
use App\Models\Kpi\KpiTask;
use App\Models\Kpi\KpiTaskSubmission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KpiTaskController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isSupervisor = $user->isKpiSupervisor();
        $userSquadId = $user->getKpiSquadId();

        $currentPeriod = KpiPeriod::where('status', 'Open')->latest('id')->first() ?? KpiPeriod::latest('id')->first();

        $query = KpiTask::with(['squad', 'assignee', 'creator', 'submissions'])
            ->when($currentPeriod, fn($q) => $q->where('kpi_period_id', $currentPeriod->id))
            ->when(!$isSupervisor && $userSquadId, fn($q) => $q->where('squad_id', $userSquadId))
            ->latest('id');

        $tasks = $query->paginate(15);
        $squads = $isSupervisor ? KpiSquad::all() : KpiSquad::where('id', $userSquadId)->get();
        $staffUsers = User::whereIn('username', ['dara', 'kim'])->get();

        return view('kpi.tasks', compact('tasks', 'squads', 'staffUsers', 'isSupervisor', 'currentPeriod'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'squad_id' => 'required|exists:kpi_squads,id',
            'assignee_id' => 'required|exists:users,id',
            'priority' => 'required|in:Low,Medium,High,Urgent',
            'due_date' => 'nullable|date',
        ]);

        $currentPeriod = KpiPeriod::where('status', 'Open')->latest('id')->first() ?? KpiPeriod::latest('id')->first();

        $task = KpiTask::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'squad_id' => $validated['squad_id'],
            'assignee_id' => $validated['assignee_id'],
            'creator_id' => $user->id,
            'kpi_period_id' => $currentPeriod?->id ?? 1,
            'priority' => $validated['priority'],
            'status' => 'Assigned',
            'due_date' => $validated['due_date'] ?? null,
        ]);

        KpiAuditLog::create([
            'user_id' => $user->id,
            'action' => 'task_created',
            'entity_type' => KpiTask::class,
            'entity_id' => $task->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => ['title' => $task->title, 'assignee' => $task->assignee_id],
        ]);

        return redirect()->back()->with('success', 'KPI Deliverable task created successfully.');
    }

    public function submitEvidence(Request $request, KpiTask $task): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'evidence_url' => 'required|url|max:500',
            'notes' => 'nullable|string',
        ]);

        KpiTaskSubmission::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'evidence_url' => $validated['evidence_url'],
            'notes' => $validated['notes'] ?? null,
            'submitted_at' => now(),
        ]);

        $task->update([
            'status' => 'Submitted',
            'completed_at' => now(),
        ]);

        KpiAuditLog::create([
            'user_id' => $user->id,
            'action' => 'task_submitted',
            'entity_type' => KpiTask::class,
            'entity_id' => $task->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => ['evidence_url' => $validated['evidence_url']],
        ]);

        return redirect()->back()->with('success', 'Deliverable evidence submitted for review.');
    }

    public function approve(Request $request, KpiTask $task): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'quality_score' => 'required|numeric|min:0|max:100',
            'feedback' => 'nullable|string',
        ]);

        $task->update([
            'status' => 'Approved',
            'quality_score' => $validated['quality_score'],
            'feedback' => $validated['feedback'] ?? null,
        ]);

        KpiAuditLog::create([
            'user_id' => $user->id,
            'action' => 'task_approved',
            'entity_type' => KpiTask::class,
            'entity_id' => $task->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => ['quality_score' => $validated['quality_score']],
        ]);

        return redirect()->back()->with('success', 'Task successfully approved and scored.');
    }
}
