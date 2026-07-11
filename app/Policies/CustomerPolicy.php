<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('super-admin')) return true;
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
        return $user->can('crm.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        if ($user->can('crm.edit')) return true;
        return $customer->assigned_to === $user->id;
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->canDeleteCrmRecords('website');
    }

    public function addInteraction(User $user, Customer $customer): bool
    {
        return $user->can('crm.view');
    }
}
