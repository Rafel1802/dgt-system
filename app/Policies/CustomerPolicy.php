<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasAnyRole(['super-admin', 'boss', 'admin-crm'])) return true;
        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('crm.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        if ($user->can('crm.view')) return true;
        // Sales staff can only see their own assigned customers
        return $customer->assigned_to === $user->id;
    }

    public function create(User $user): bool
    {
        if ($user->hasRole('tech-support')) return false;
        return $user->can('crm.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        if ($user->hasRole('tech-support')) return false;
        if ($user->hasFullCrmEdit()) return true;
        // Normal Staff (crm.status-update): status/notes only, and only on
        // their own assigned customers — field-level enforcement happens in
        // CustomerController::update(), not here.
        return $user->can('crm.status-update') && $customer->assigned_to === $user->id;
    }

    public function delete(User $user, Customer $customer): bool
    {
        if ($user->hasRole('tech-support')) return false;
        return $user->canDeleteCrmRecords('website');
    }

    public function routeWorkflow(User $user, Customer $customer): bool
    {
        if ($user->hasRole('tech-support')) return false;
        return $user->hasFullCrmEdit();
    }

    public function addInteraction(User $user, Customer $customer): bool
    {
        if ($user->hasRole('tech-support')) return false;
        return $user->can('crm.view');
    }
}
