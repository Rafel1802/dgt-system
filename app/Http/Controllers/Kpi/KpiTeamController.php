<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\Kpi\KpiAuditLog;
use App\Models\Kpi\KpiSquad;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KpiTeamController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isSupervisor = $user->isKpiSupervisor();
        $userSquadId = $user->getKpiSquadId();

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

        // Get users available to be added (exclude existing squad members)
        $existingMemberIds = $currentSquad ? $currentSquad->members->pluck('id')->toArray() : [];
        $existingMemberIds[] = $currentSquad?->lead_id; // don't add the lead as staff

        $availableUsers = User::whereNotIn('id', array_filter($existingMemberIds))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'email', 'team_role']);

        return view('kpi.team', compact('squads', 'currentSquad', 'availableUsers', 'isSupervisor', 'userSquadId'));
    }

    public function addMember(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isSupervisor = $user->isKpiSupervisor();
        $userSquadId = $user->getKpiSquadId();

        $validated = $request->validate([
            'squad_id' => 'required|exists:kpi_squads,id',
            'user_id' => 'required|exists:users,id',
            'role_title' => 'nullable|string|max:100',
        ]);

        if (!$isSupervisor && $userSquadId != $validated['squad_id']) {
            abort(403, 'You can only add team members to your own squad.');
        }

        $squad = KpiSquad::findOrFail($validated['squad_id']);
        $newMember = User::findOrFail($validated['user_id']);

        $squad->members()->syncWithoutDetaching([
            $newMember->id => [
                'role_title' => $validated['role_title'] ?? 'Team Member',
                'joined_date' => now()->toDateString(),
            ]
        ]);

        KpiAuditLog::create([
            'user_id' => $user->id,
            'action' => 'team_member_added',
            'entity_type' => KpiSquad::class,
            'entity_id' => $squad->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => ['squad' => $squad->name, 'member' => $newMember->name, 'role' => $validated['role_title'] ?? 'Team Member'],
        ]);

        return redirect()->back()->with('success', "{$newMember->name} has been added to {$squad->name}.");
    }

    public function removeMember(Request $request, KpiSquad $squad, User $member): RedirectResponse
    {
        $user = $request->user();
        $isSupervisor = $user->isKpiSupervisor();
        $userSquadId = $user->getKpiSquadId();

        if (!$isSupervisor && $userSquadId != $squad->id) {
            abort(403, 'You can only remove members from your own squad.');
        }

        $squad->members()->detach($member->id);

        KpiAuditLog::create([
            'user_id' => $user->id,
            'action' => 'team_member_removed',
            'entity_type' => KpiSquad::class,
            'entity_id' => $squad->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => ['squad' => $squad->name, 'removed_member' => $member->name],
        ]);

        return redirect()->back()->with('success', "{$member->name} has been removed from {$squad->name}.");
    }
}
