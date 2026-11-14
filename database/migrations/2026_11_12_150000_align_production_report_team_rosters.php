<?php

use App\Models\ProductionReport\ProductionReportTeamMapping;
use App\Models\User;
use App\Services\ProductionReport\ProductionReportPermissionService as Perm;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Align Production Report team rosters with the official KPI squads:
 *   Team A (Dara): Samnang, Vouchky, Chhay, SokHeang, Nalin, Sreypich  (6)
 *   Team B (Kim) : Pich, Sor, Lin, Sarak                               (4)
 * Anyone else mapped as a member (e.g. Lyza) is deactivated — never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('production_report_team_mappings') || !Schema::hasTable('users')) {
            return;
        }

        $rosterUsernames = [];

        foreach (Perm::ROSTER as $teamCode => $usernames) {
            foreach ($usernames as $username) {
                $rosterUsernames[] = $username;
                $user = User::whereRaw('LOWER(username) = ?', [$username])->first();
                if (!$user) {
                    continue;
                }

                ProductionReportTeamMapping::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'team_code' => $teamCode,
                        'team_name' => Perm::TEAM_NAMES[$teamCode],
                        'role_in_reporting' => ProductionReportTeamMapping::ROLE_MEMBER,
                        'position_title' => $user->team_role ?: 'Production Member',
                        'can_submit_daily' => true,
                        'can_submit_weekly' => false,
                        'can_submit_monthly' => true,
                        'is_active' => (bool) $user->is_active,
                    ]
                );

                // Re-home this member's existing daily/monthly reports to the correct team so heads see them.
                if (Schema::hasTable('production_reports')) {
                    \App\Models\ProductionReport\ProductionReport::withTrashed()
                        ->where('user_id', $user->id)
                        ->whereIn('report_type', ['daily', 'monthly'])
                        ->where('team_code', '!=', $teamCode)
                        ->update(['team_code' => $teamCode, 'team_name' => Perm::TEAM_NAMES[$teamCode]]);
                }
            }
        }

        // Remove everyone else from the member roster (keep the record, just inactive).
        $others = ProductionReportTeamMapping::where('role_in_reporting', ProductionReportTeamMapping::ROLE_MEMBER)
            ->with('user:id,username')
            ->get();

        foreach ($others as $mapping) {
            $username = strtolower((string) $mapping->user?->username);
            if (!in_array($username, $rosterUsernames, true)) {
                $mapping->update(['is_active' => false]);
            }
        }
    }

    public function down(): void
    {
        // Roster alignment is data-only and intentionally not reverted.
    }
};
