<?php

namespace App\Policies;

use App\Models\EbayCustomerRecord;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EbayCustomerRecordPolicy
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
        return $user->hasAnyRole(['admin-crm', 'sales-crm', 'boss', 'super-admin', 'ebay-supervisor', 'ebay-team']);
    }

    public function view(User $user, EbayCustomerRecord $record): bool
    {
        return $user->hasFullCrmEdit() || $user->hasAnyRole(['ebay-supervisor', 'ebay-team', 'sales-crm']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin-crm', 'sales-crm', 'boss', 'super-admin', 'ebay-supervisor', 'ebay-team']);
    }

    public function update(User $user, EbayCustomerRecord $record): bool
    {
        return $user->hasFullCrmEdit() || $user->hasRole('ebay-supervisor');
    }

    public function interact(User $user, EbayCustomerRecord $record): bool
    {
        return $user->hasFullCrmEdit() || $user->hasAnyRole(['ebay-supervisor', 'ebay-team', 'sales-crm']);
    }

    public function delete(User $user, EbayCustomerRecord $record): bool
    {
        return $user->canDeleteCrmRecords('ebay');
    }

    public function changeHandler(User $user, EbayCustomerRecord $record): bool
    {
        return $user->hasFullCrmEdit() || $user->hasRole('ebay-supervisor');
    }
}
