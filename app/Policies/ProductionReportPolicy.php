<?php

namespace App\Policies;

use App\Models\ProductionReport\ProductionReport;
use App\Models\User;
use App\Services\ProductionReport\ProductionReportPermissionService;

class ProductionReportPolicy
{
    public function __construct(
        private readonly ProductionReportPermissionService $permissionService
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->permissionService->canAccessModule($user);
    }

    public function view(User $user, ProductionReport $report): bool
    {
        return $this->permissionService->canViewReport($user, $report);
    }

    public function createDaily(User $user): bool
    {
        return $this->permissionService->canSubmitDaily($user);
    }

    public function createWeekly(User $user, ?string $teamCode = null): bool
    {
        return $this->permissionService->canSubmitWeekly($user, $teamCode);
    }

    public function createMonthly(User $user): bool
    {
        return $this->permissionService->canSubmitMonthly($user);
    }

    public function update(User $user, ProductionReport $report): bool
    {
        return $this->permissionService->canEditReport($user, $report);
    }

    public function submit(User $user, ProductionReport $report): bool
    {
        if ($this->permissionService->isSupervisor($user)) {
            return false;
        }

        return $this->permissionService->canEditReport($user, $report);
    }

    public function review(User $user, ProductionReport $report): bool
    {
        return $this->permissionService->canReviewOrReturnReport($user, $report);
    }

    public function export(User $user, ProductionReport $report): bool
    {
        return $this->permissionService->canViewReport($user, $report);
    }
}

