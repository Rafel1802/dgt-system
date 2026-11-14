<?php

namespace App\Services\ProductionReport;

use App\Models\Kpi\KpiSquad;
use App\Models\ProductionReport\ProductionReport;
use App\Models\ProductionReport\ProductionReportDeadline;
use App\Models\ProductionReport\ProductionReportMedia;
use App\Models\ProductionReport\ProductionReportTeamMapping;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ProductionReportPermissionService
{
    public const TEAM_A = 'A';
    public const TEAM_B = 'B';

    public const TEAM_NAMES = [
        'A' => 'Team A — Production & Motion Graphics (Lead: Dara)',
        'B' => 'Team B — Creative Graphic & Visuals (Lead: Kim)',
    ];

    /**
     * Official production roster (matches the KPI squads): usernames per team.
     * Team A — Head: Dara (6 members). Team B — Head: Kim (4 members).
     * Anyone else (e.g. Lyza) is NOT a production reporting member.
     */
    public const ROSTER = [
        'A' => ['nang', 'vouchky', 'chhay', 'heang', 'nalin', 'sreypich'],
        'B' => ['pich', 'sor', 'lin', 'sarak'],
    ];

    protected static bool $syncedInRequest = false;

    /**
     * Synchronize default team mappings from existing User & KpiSquad records
     * without creating duplicate users or overwriting explicit mappings.
     */
    public function syncDefaultMappingsIfNeeded(): void
    {
        if (!Schema::hasTable('production_report_team_mappings')) {
            return;
        }

        if (self::$syncedInRequest && ProductionReportTeamMapping::count() > 0) {
            return;
        }

        self::$syncedInRequest = true;

        // 1. Supervisor: Somalika
        $somalika = User::where(function ($q) {
            $q->whereRaw('LOWER(username) = ?', ['somalika'])
              ->orWhereRaw('LOWER(name) LIKE ?', ['%somalika%'])
              ->orWhereRaw('LOWER(email) LIKE ?', ['somalika@%']);
        })->first();
        if ($somalika) {
            ProductionReportTeamMapping::updateOrCreate(
                ['user_id' => $somalika->id],
                [
                    'team_code' => 'ALL',
                    'team_name' => 'All Production Teams (Supervisor)',
                    'role_in_reporting' => ProductionReportTeamMapping::ROLE_SUPERVISOR,
                    'position_title' => 'Production Supervisor',
                    'can_submit_daily' => false,
                    'can_submit_weekly' => false,
                    'can_submit_monthly' => false,
                    'is_active' => true,
                ]
            );
        }

        // 2. Head of Production Team A: Dara
        $dara = User::where(function ($q) {
            $q->whereRaw('LOWER(username) = ?', ['dara'])
              ->orWhereRaw('LOWER(name) LIKE ?', ['%dara%'])
              ->orWhereRaw('LOWER(email) LIKE ?', ['dara@%']);
        })->first();
        if ($dara) {
            $existing = ProductionReportTeamMapping::where('user_id', $dara->id)->first();
            if (!$existing) {
                ProductionReportTeamMapping::create([
                    'user_id' => $dara->id,
                    'team_code' => self::TEAM_A,
                    'team_name' => self::TEAM_NAMES[self::TEAM_A],
                    'role_in_reporting' => ProductionReportTeamMapping::ROLE_HEAD,
                    'position_title' => 'Head of Production (Team A)',
                    'can_submit_daily' => false,
                    'can_submit_weekly' => true,
                    'can_submit_monthly' => true,
                    'is_active' => true,
                ]);
            }
        }

        // 3. Head of Production Team B: Kim
        $kim = User::where(function ($q) {
            $q->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(username)'), ['kim', 'kimoun'])
              ->orWhereRaw('LOWER(name) LIKE ?', ['%kim%'])
              ->orWhereRaw('LOWER(email) LIKE ?', ['kim@%'])
              ->orWhereRaw('LOWER(email) LIKE ?', ['kimoun@%']);
        })->first();
        if ($kim) {
            $existing = ProductionReportTeamMapping::where('user_id', $kim->id)->first();
            if (!$existing) {
                ProductionReportTeamMapping::create([
                    'user_id' => $kim->id,
                    'team_code' => self::TEAM_B,
                    'team_name' => self::TEAM_NAMES[self::TEAM_B],
                    'role_in_reporting' => ProductionReportTeamMapping::ROLE_HEAD,
                    'position_title' => 'Head of Production (Team B)',
                    'can_submit_daily' => false,
                    'can_submit_weekly' => true,
                    'can_submit_monthly' => true,
                    'is_active' => true,
                ]);
            }
        }

        // 4. Sync members from KPI Squads (SQUAD-1 => Team A, SQUAD-2 => Team B) if not yet mapped
        if (Schema::hasTable('kpi_squads') && Schema::hasTable('kpi_squad_members')) {
            $squad1 = KpiSquad::where('code', 'SQUAD-1')->with('members')->first();
            if ($squad1) {
                foreach ($squad1->members as $member) {
                    if ($this->isSupervisor($member) || $this->isDaraHead($member) || $this->isKimHead($member)) {
                        continue;
                    }
                    ProductionReportTeamMapping::firstOrCreate(
                        ['user_id' => $member->id],
                        [
                            'team_code' => self::TEAM_A,
                            'team_name' => self::TEAM_NAMES[self::TEAM_A],
                            'role_in_reporting' => ProductionReportTeamMapping::ROLE_MEMBER,
                            'position_title' => $member->pivot?->role_title ?? ($member->team_role ?: 'Production Member'),
                            'can_submit_daily' => true,
                            'can_submit_weekly' => false,
                            'can_submit_monthly' => true,
                            'is_active' => (bool) $member->is_active,
                        ]
                    );
                }
            }

            $squad2 = KpiSquad::where('code', 'SQUAD-2')->with('members')->first();
            if ($squad2) {
                foreach ($squad2->members as $member) {
                    if ($this->isSupervisor($member) || $this->isDaraHead($member) || $this->isKimHead($member)) {
                        continue;
                    }
                    ProductionReportTeamMapping::firstOrCreate(
                        ['user_id' => $member->id],
                        [
                            'team_code' => self::TEAM_B,
                            'team_name' => self::TEAM_NAMES[self::TEAM_B],
                            'role_in_reporting' => ProductionReportTeamMapping::ROLE_MEMBER,
                            'position_title' => $member->pivot?->role_title ?? ($member->team_role ?: 'Production Member'),
                            'can_submit_daily' => true,
                            'can_submit_weekly' => false,
                            'can_submit_monthly' => true,
                            'is_active' => (bool) $member->is_active,
                        ]
                    );
                }
            }
        }

        // 5. Sync any users who have explicit users.team = 'A', 'B', 'Team A', 'Team B', etc. and are not yet mapped
        $teamUsers = User::whereNotNull('team')->where('team', '!=', '')->get();
        foreach ($teamUsers as $u) {
            if ($this->isSupervisor($u) || $this->isDaraHead($u) || $this->isKimHead($u)) {
                continue;
            }
            $teamCode = $this->normalizeTeamCode($u->team);
            if (in_array($teamCode, [self::TEAM_A, self::TEAM_B], true)) {
                ProductionReportTeamMapping::firstOrCreate(
                    ['user_id' => $u->id],
                    [
                        'team_code' => $teamCode,
                        'team_name' => self::TEAM_NAMES[$teamCode],
                        'role_in_reporting' => ProductionReportTeamMapping::ROLE_MEMBER,
                        'position_title' => $u->team_role ?: 'Production Member',
                        'can_submit_daily' => true,
                        'can_submit_weekly' => false,
                        'can_submit_monthly' => true,
                        'is_active' => (bool) $u->is_active,
                    ]
                );
            }
        }
    }

    /**
     * Normalize raw team string ('A', 'Team A', 'B', 'Team B', 'Digital Media', etc.) to 'A' or 'B'.
     */
    public function normalizeTeamCode(?string $rawTeam): ?string
    {
        $clean = strtoupper(trim((string) $rawTeam));
        if ($clean === '') {
            return null;
        }
        if ($clean === 'A' || $clean === 'TEAM A' || str_contains($clean, 'TEAM A') || str_contains($clean, 'SQUAD-1') || str_contains($clean, 'VIDEO')) {
            return self::TEAM_A;
        }
        if ($clean === 'B' || $clean === 'TEAM B' || str_contains($clean, 'TEAM B') || str_contains($clean, 'SQUAD-2') || str_contains($clean, 'GRAPHIC') || str_contains($clean, 'DIGITAL MEDIA')) {
            return self::TEAM_B;
        }
        return null;
    }

    public function getMapping(User $user): ?ProductionReportTeamMapping
    {
        if (!Schema::hasTable('production_report_team_mappings')) {
            return null;
        }

        $this->syncDefaultMappingsIfNeeded();

        return ProductionReportTeamMapping::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Somalika is the production supervisor (view-only across all production teams).
     */
    public function isSupervisor(User $user): bool
    {
        $username = strtolower(trim((string) $user->username));
        $name = strtolower(trim((string) $user->name));
        $email = strtolower(trim((string) $user->email));

        if ($username === 'somalika' || str_contains($name, 'somalika') || str_starts_with($email, 'somalika@')) {
            return true;
        }

        $mapping = $this->getMapping($user);
        if ($mapping && $mapping->role_in_reporting === ProductionReportTeamMapping::ROLE_SUPERVISOR) {
            return true;
        }

        return false;
    }

    public function isDaraHead(User $user): bool
    {
        $username = strtolower(trim((string) $user->username));
        $name = strtolower(trim((string) $user->name));
        $email = strtolower(trim((string) $user->email));

        if ($username === 'dara' || preg_match('/\bdara\b/i', $name) || str_starts_with($email, 'dara@') || str_contains($email, 'soporadara')) {
            return true;
        }

        $mapping = $this->getMapping($user);
        return $mapping !== null
            && $mapping->role_in_reporting === ProductionReportTeamMapping::ROLE_HEAD
            && strtoupper($mapping->team_code) === self::TEAM_A;
    }

    public function isKimHead(User $user): bool
    {
        $username = strtolower(trim((string) $user->username));
        $name = strtolower(trim((string) $user->name));
        $email = strtolower(trim((string) $user->email));

        if (in_array($username, ['kim', 'kimoun'], true) || preg_match('/\b(kim|kimoun)\b/i', $name) || str_starts_with($email, 'kim@') || str_starts_with($email, 'kimoun@')) {
            return true;
        }

        $mapping = $this->getMapping($user);
        return $mapping !== null
            && $mapping->role_in_reporting === ProductionReportTeamMapping::ROLE_HEAD
            && strtoupper($mapping->team_code) === self::TEAM_B;
    }

    public function isProductionHead(User $user): bool
    {
        if ($this->isSupervisor($user)) {
            return false;
        }

        if ($this->isDaraHead($user) || $this->isKimHead($user)) {
            return true;
        }

        $mapping = $this->getMapping($user);
        return $mapping !== null && $mapping->role_in_reporting === ProductionReportTeamMapping::ROLE_HEAD;
    }

    /** A member whose reporting mapping was deactivated (e.g. not part of the official roster). */
    public function isExcludedFromRoster(User|string $user): bool
    {
        if (!Schema::hasTable('production_report_team_mappings')) {
            return false;
        }

        $userId = $user instanceof User
            ? $user->id
            : User::whereRaw('LOWER(username) = ?', [strtolower(trim($user))])->value('id');

        if (!$userId) {
            return false;
        }

        return ProductionReportTeamMapping::where('user_id', $userId)
            ->where('role_in_reporting', ProductionReportTeamMapping::ROLE_MEMBER)
            ->where('is_active', false)
            ->exists();
    }

    public function isProductionMember(User $user): bool
    {
        if ($this->isSupervisor($user) || $this->isProductionHead($user)) {
            return false;
        }

        $mapping = $this->getMapping($user);
        if ($mapping && $mapping->role_in_reporting === ProductionReportTeamMapping::ROLE_MEMBER) {
            return true;
        }

        // Explicitly removed from the production roster (inactive mapping) → not a reporting member.
        if ($this->isExcludedFromRoster($user)) {
            return false;
        }

        $normalizedTeam = $this->normalizeTeamCode($user->team);
        if (in_array($normalizedTeam, [self::TEAM_A, self::TEAM_B], true)) {
            return true;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('digital-team')) {
            return true;
        }

        return false;
    }

    /**
     * Resolve the user's reporting role: 'supervisor', 'head', 'member', or null (unauthorized).
     */
    public function resolveRole(User $user): ?string
    {
        // Frozen / deactivated accounts cannot use the Production Reports module at all.
        if ($user->is_active !== null && !$user->is_active) {
            return null;
        }

        if ($this->isSupervisor($user)) {
            return ProductionReportTeamMapping::ROLE_SUPERVISOR;
        }
        if ($this->isProductionHead($user)) {
            return ProductionReportTeamMapping::ROLE_HEAD;
        }
        if ($this->isProductionMember($user)) {
            return ProductionReportTeamMapping::ROLE_MEMBER;
        }
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['super-admin', 'admin-digital'])) {
            return ProductionReportTeamMapping::ROLE_SUPERVISOR;
        }

        return null;
    }

    /**
     * Dara, Kim and Somalika (supervisor-level) see every Report menu.
     * Regular production members only see their own tools (Dashboard, My Reports, Submit Daily, Submit Monthly).
     */
    public function canSeeFullMenu(User $user): bool
    {
        return in_array($this->resolveRole($user), [
            ProductionReportTeamMapping::ROLE_SUPERVISOR,
            ProductionReportTeamMapping::ROLE_HEAD,
        ], true);
    }

    public function canAccessModule(User $user): bool
    {
        return $this->resolveRole($user) !== null;
    }

    /**
     * Get the assigned team code ('A' or 'B') for a Head or Member.
     * Dara -> strictly 'A'. Kim -> strictly 'B'.
     */
    public function getAssignedTeamCode(User $user): ?string
    {
        if ($this->isDaraHead($user)) {
            return self::TEAM_A;
        }
        if ($this->isKimHead($user)) {
            return self::TEAM_B;
        }

        $mapping = $this->getMapping($user);
        if ($mapping && in_array(strtoupper($mapping->team_code), [self::TEAM_A, self::TEAM_B], true)) {
            return strtoupper($mapping->team_code);
        }

        $normalizedTeam = $this->normalizeTeamCode($user->team);
        if (in_array($normalizedTeam, [self::TEAM_A, self::TEAM_B], true)) {
            return $normalizedTeam;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('digital-team')) {
            return self::TEAM_A;
        }

        return null;
    }

    public function getTeamName(?string $teamCode): string
    {
        $code = strtoupper((string) $teamCode);
        return self::TEAM_NAMES[$code] ?? ($code ? "Team {$code}" : 'Unassigned Team');
    }

    /**
     * Get all active production members for a specific team ('A' or 'B').
     */
    public function getTeamMembers(string $teamCode): Collection
    {
        $this->syncDefaultMappingsIfNeeded();
        $code = strtoupper(trim($teamCode));

        $mappedUserIds = ProductionReportTeamMapping::where('team_code', $code)
            ->where('role_in_reporting', ProductionReportTeamMapping::ROLE_MEMBER)
            ->where('is_active', true)
            ->pluck('user_id')
            ->toArray();

        $users = User::where(function ($q) use ($mappedUserIds, $code) {
            if (!empty($mappedUserIds)) {
                $q->whereIn('id', $mappedUserIds);
            } else {
                $q->whereRaw('UPPER(team) = ?', [$code]);
            }
        })
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Filter out Supervisor and Heads from the member list unless they are explicitly mapped as members
        return $users->reject(function (User $u) {
            return $this->isSupervisor($u) || $this->isProductionHead($u);
        })->values();
    }

    /**
     * Get the Head of Production User for a given team code ('A' -> Dara, 'B' -> Kim).
     */
    public function getTeamHead(string $teamCode): ?User
    {
        $code = strtoupper(trim($teamCode));
        if ($code === self::TEAM_A) {
            $dara = User::whereRaw('LOWER(username) = ?', ['dara'])->first();
            if ($dara) {
                return $dara;
            }
        }
        if ($code === self::TEAM_B) {
            $kim = User::whereRaw('LOWER(username) = ?', ['kim'])->first();
            if ($kim) {
                return $kim;
            }
        }

        $mapping = ProductionReportTeamMapping::where('team_code', $code)
            ->where('role_in_reporting', ProductionReportTeamMapping::ROLE_HEAD)
            ->where('is_active', true)
            ->first();

        return $mapping?->user;
    }

    /**
     * Can the user submit a Daily Report?
     * Supervisor (Somalika): NEVER.
     * Production Member: YES.
     * Head (Dara / Kim): Only if allow_head_daily_submission or mapping->can_submit_daily is explicitly true.
     */
    public function canSubmitDaily(User $user): bool
    {
        if ($this->isSupervisor($user) || $this->resolveRole($user) === null) {
            return false;
        }

        if ($this->isProductionMember($user)) {
            $mapping = $this->getMapping($user);
            return $mapping ? (bool) $mapping->can_submit_daily : true;
        }

        if ($this->isProductionHead($user)) {
            $mapping = $this->getMapping($user);
            if ($mapping && $mapping->can_submit_daily) {
                return true;
            }
            return (bool) ProductionReportDeadline::current()->allow_head_daily_submission;
        }

        return false;
    }

    /**
     * Can the user create/compile/submit a Weekly Team Report?
     * Supervisor (Somalika): NEVER.
     * Production Member: NEVER.
     * Head (Dara for Team A, Kim for Team B): YES, for their assigned team ONLY.
     */
    public function canSubmitWeekly(User $user, ?string $targetTeamCode = null): bool
    {
        if ($this->isSupervisor($user)) {
            return false;
        }

        if (!$this->isProductionHead($user)) {
            return false;
        }

        $assignedTeam = $this->getAssignedTeamCode($user);
        if (!$assignedTeam) {
            return false;
        }

        if ($targetTeamCode !== null && strtoupper(trim($targetTeamCode)) !== $assignedTeam) {
            return false;
        }

        return true;
    }

    /**
     * Can the user compile & submit the combined Team Monthly report (Head → Supervisor)?
     * Same rule as the weekly team report: only the Head of the team, never the Supervisor.
     */
    public function canSubmitTeamMonthly(User $user, ?string $targetTeamCode = null): bool
    {
        return $this->canSubmitWeekly($user, $targetTeamCode);
    }

    /**
     * Can the user create/submit a Monthly Report?
     * Supervisor (Somalika): NEVER.
     * Production Member: YES (own).
     * Head (Dara / Kim): YES (own, if enabled).
     */
    public function canSubmitMonthly(User $user): bool
    {
        if ($this->isSupervisor($user)) {
            return false;
        }

        if ($this->isProductionMember($user)) {
            $mapping = $this->getMapping($user);
            return $mapping ? (bool) $mapping->can_submit_monthly : true;
        }

        if ($this->isProductionHead($user)) {
            $mapping = $this->getMapping($user);
            if ($mapping !== null) {
                return (bool) $mapping->can_submit_monthly;
            }
            return (bool) ProductionReportDeadline::current()->allow_head_monthly_submission;
        }

        return false;
    }

    /**
     * Can the user view a specific ProductionReport?
     */
    public function canViewReport(User $user, ProductionReport $report): bool
    {
        $role = $this->resolveRole($user);
        if ($role === null) {
            return false;
        }

        // 1. Supervisor (Somalika) views all reports across all teams
        if ($role === ProductionReportTeamMapping::ROLE_SUPERVISOR) {
            return true;
        }

        // 2. Production Head (Dara or Kim)
        if ($role === ProductionReportTeamMapping::ROLE_HEAD) {
            $headTeam = $this->getAssignedTeamCode($user);
            if (!$headTeam) {
                return false;
            }

            // Own report is always visible to its author
            if ((int) $report->user_id === (int) $user->id) {
                return true;
            }

            // NEVER allow Dara to access Kim's team or Kim to access Dara's team
            $reportTeam = strtoupper((string) $report->team_code);
            if ($reportTeam !== $headTeam) {
                return false;
            }

            // Within assigned team: Head can view daily reports and weekly team reports
            if (in_array($report->report_type, [ProductionReport::TYPE_DAILY, ProductionReport::TYPE_WEEKLY, ProductionReport::TYPE_MONTHLY], true)) {
                return true;
            }

            return false;
        }

        // 3. Production Member: ONLY their own reports
        if ($role === ProductionReportTeamMapping::ROLE_MEMBER) {
            return (int) $report->user_id === (int) $user->id;
        }

        return false;
    }

    /**
     * Can the user edit/update a specific ProductionReport?
     */
    public function canEditReport(User $user, ProductionReport $report): bool
    {
        // Supervisor NEVER edits or submits reports
        if ($this->isSupervisor($user)) {
            return false;
        }

        if (!$this->canViewReport($user, $report)) {
            return false;
        }

        // Weekly reports can be edited by the assigned team's Head while draft/returned
        if ($report->report_type === ProductionReport::TYPE_WEEKLY) {
            $headTeam = $this->getAssignedTeamCode($user);
            return $this->isProductionHead($user)
                && $headTeam !== null
                && strtoupper((string) $report->team_code) === $headTeam
                && $report->isEditableByAuthor();
        }

        // Daily and Monthly reports can only be edited by their owner while draft/returned
        return (int) $report->user_id === (int) $user->id && $report->isEditableByAuthor();
    }

    /**
     * Can the user reopen or return a submitted report for correction, or approve it?
     */
    public function canReviewOrReturnReport(User $user, ProductionReport $report): bool
    {
        // Supervisor can return/approve weekly or daily/monthly reports if needed, or Head can review their team's daily reports
        if ($this->isSupervisor($user)) {
            return true;
        }

        if ($this->isProductionHead($user)) {
            $headTeam = $this->getAssignedTeamCode($user);
            return $headTeam !== null
                && strtoupper((string) $report->team_code) === $headTeam
                && (int) $report->user_id !== (int) $user->id;
        }

        return false;
    }

    /**
     * Can the user view/download a specific ProductionReportMedia item?
     */
    public function canAccessMedia(User $user, ProductionReportMedia $media): bool
    {
        $report = $media->report;
        if ($report && $this->canViewReport($user, $report)) {
            return true;
        }

        if ($media->sourceDailyReport && $this->canViewReport($user, $media->sourceDailyReport)) {
            return true;
        }

        return false;
    }

    /**
     * Can the user manage Team Mappings & Deadline Configurations?
     */
    public function canManageSettings(User $user): bool
    {
        return $this->isSupervisor($user)
            || $this->isProductionHead($user)
            || (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['super-admin', 'admin-digital']));
    }

    /**
     * Can the user add or remove members in a given production team?
     */
    public function canAddTeamMember(User $user, ?string $teamCode = null): bool
    {
        if ($this->isSupervisor($user) || (method_exists($user, 'hasRole') && $user->hasRole('super-admin'))) {
            return true;
        }

        if ($this->isProductionHead($user) || (method_exists($user, 'hasRole') && $user->hasRole('admin-digital'))) {
            $assignedTeam = $this->getAssignedTeamCode($user);
            return $teamCode === null || strtoupper($teamCode) === $assignedTeam;
        }

        return false;
    }

    /**
     * Can the user remove a specific team member mapping?
     */
    public function canRemoveTeamMember(User $user, ProductionReportTeamMapping $mapping): bool
    {
        // Protect Somalika as permanent supervisor
        if (strtolower((string) $mapping->user?->username) === 'somalika') {
            return false;
        }

        // Supervisor or super-admin can remove members from any team
        if ($this->isSupervisor($user) || (method_exists($user, 'hasRole') && $user->hasRole('super-admin'))) {
            return true;
        }

        // Dara (Team A) and Kim (Team B) as admin-digital can remove members from their assigned team
        if ($this->isProductionHead($user) || (method_exists($user, 'hasRole') && $user->hasRole('admin-digital'))) {
            $assignedTeam = $this->getAssignedTeamCode($user);
            return strtoupper((string) $mapping->team_code) === $assignedTeam
                && $mapping->role_in_reporting === ProductionReportTeamMapping::ROLE_MEMBER;
        }

        return false;
    }

    /**
     * Apply strict backend query scoping to ProductionReport queries.
     */
    public function scopeReportsQuery(Builder $query, User $user): Builder
    {
        $role = $this->resolveRole($user);

        // Reports authored by frozen / deactivated accounts are hidden from leadership views.
        $hideFrozen = fn (Builder $q) => $q->whereHas('user', fn ($u) => $u->where('is_active', true));

        if ($role === ProductionReportTeamMapping::ROLE_SUPERVISOR) {
            return $hideFrozen($query);
        }

        if ($role === ProductionReportTeamMapping::ROLE_HEAD) {
            $headTeam = $this->getAssignedTeamCode($user);
            if (!$headTeam) {
                return $query->whereRaw('1 = 0');
            }

            return $hideFrozen($query)->where(function (Builder $q) use ($headTeam, $user) {
                $q->where('team_code', $headTeam)
                  ->orWhere('user_id', $user->id);
            });
        }

        if ($role === ProductionReportTeamMapping::ROLE_MEMBER) {
            return $query->where('user_id', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Apply strict backend query scoping to ProductionReportMedia queries.
     */
    public function scopeMediaQuery(Builder $query, User $user): Builder
    {
        $role = $this->resolveRole($user);

        // Media created by frozen / deactivated accounts is hidden from leadership views.
        $hideFrozen = fn (Builder $q) => $q->whereDoesntHave('creator', fn ($u) => $u->where('is_active', false));

        if ($role === ProductionReportTeamMapping::ROLE_SUPERVISOR) {
            return $hideFrozen($query);
        }

        if ($role === ProductionReportTeamMapping::ROLE_HEAD) {
            $headTeam = $this->getAssignedTeamCode($user);
            if (!$headTeam) {
                return $query->whereRaw('1 = 0');
            }

            return $hideFrozen($query)->where(function (Builder $q) use ($headTeam, $user) {
                $q->where('team_code', $headTeam)
                  ->orWhere('uploaded_by', $user->id)
                  ->orWhere('created_by_user_id', $user->id);
            });
        }

        if ($role === ProductionReportTeamMapping::ROLE_MEMBER) {
            return $query->where(function (Builder $q) use ($user) {
                $q->where('uploaded_by', $user->id)
                  ->orWhere('created_by_user_id', $user->id)
                  ->orWhereHas('report', fn (Builder $rq) => $rq->where('user_id', $user->id));
            });
        }

        return $query->whereRaw('1 = 0');
    }
}
