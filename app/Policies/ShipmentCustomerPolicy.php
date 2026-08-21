<?php

namespace App\Policies;

use App\Models\ShipmentCustomer;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ShipmentCustomerPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasAnyRole(['super-admin', 'boss', 'admin-crm'])) return true;
        if ($user->hasRole('tech-support') && !$user->canModifyCrmData()) return false;
        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin-crm', 'sales-crm', 'boss', 'super-admin', 'logistic-supervisor', 'logistic-team']);
    }

    public function view(User $user, ShipmentCustomer $shipmentCustomer): bool
    {
        if ($user->hasFullCrmEdit() || $user->hasRole('logistic-supervisor')) return true;
        return $shipmentCustomer->handler_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin-crm', 'sales-crm', 'boss', 'super-admin', 'logistic-supervisor', 'logistic-team']);
    }

    public function update(User $user, ShipmentCustomer $shipmentCustomer): bool
    {
        return $user->hasFullCrmEdit() || $user->hasRole('logistic-supervisor');
    }

    public function interact(User $user, ShipmentCustomer $shipmentCustomer): bool
    {
        return $user->hasFullCrmEdit() || $user->hasAnyRole(['logistic-supervisor', 'logistic-team']);
    }

    public function delete(User $user, ShipmentCustomer $shipmentCustomer): bool
    {
        return $user->canDeleteCrmRecords('logistic');
    }

    public function changeHandler(User $user, ShipmentCustomer $shipmentCustomer): bool
    {
        return $user->hasFullCrmEdit() || $user->hasRole('logistic-supervisor');
    }
}
