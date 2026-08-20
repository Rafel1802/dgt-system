<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class WebsiteLeadPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('super-admin') || $user->hasRole('boss')) return true;
        if ($user->hasRole('tech-support') && !$user->canModifyCrmData()) return false;
        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin-crm', 'sales-crm', 'boss', 'super-admin']);
    }

    public function view(User $user, Lead $lead): bool
    {
        if ($user->hasFullCrmEdit()) return true;
        return $lead->handled_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin-crm', 'sales-crm', 'boss', 'super-admin']);
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->hasFullCrmEdit();
    }

    public function interact(User $user, Lead $lead): bool
    {
        return $user->hasFullCrmEdit() || $user->hasRole('sales-crm');
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->canDeleteCrmRecords('website');
    }

    public function changeHandler(User $user, Lead $lead): bool
    {
        return $user->hasFullCrmEdit();
    }
}
