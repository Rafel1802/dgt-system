<?php

namespace App\Policies;

use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\User;

class CardPolicy
{
    /**
     * Super admins bypass all policy checks.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }
        return null;
    }

    /** Any user with kanban.view can list the board */
    public function viewAny(User $user): bool
    {
        return $user->can('kanban.view');
    }

    /** Any authenticated user with kanban.view permission can see cards */
    public function view(User $user, Card $card): bool
    {
        return $user->can('kanban.view');
    }

    /** Staff, digital-team, sales-crm, supervisor, admin can create */
    public function create(User $user): bool
    {
        return $user->can('kanban.create');
    }

    /** Creators can edit their own cards; admins/supervisors can edit any */
    public function update(User $user, Card $card): bool
    {
        if ($user->hasAnyRole(['admin', 'supervisor'])) {
            return true;
        }
        return $user->can('kanban.edit') && $card->created_by === $user->id;
    }

    /** Only admins can delete cards */
    public function delete(User $user, Card $card): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    /** Supervisors and admins can approve cards in 'review' status */
    public function approve(User $user, Card $card): bool
    {
        return $user->can('kanban.approve')
            && $card->status === CardStatus::Review;
    }

    /** Supervisors and admins can reject cards in 'review' status */
    public function reject(User $user, Card $card): bool
    {
        return $user->can('kanban.reject')
            && $card->status === CardStatus::Review;
    }

    /** Check if user can move a card to a specific status */
    public function moveTo(User $user, Card $card, CardStatus $newStatus): bool
    {
        $role = $user->roles->first()?->name ?? '';
        $allowed = $card->status->allowedTransitions($role);

        return in_array($newStatus, $allowed);
    }

    /** Can assign users to a card */
    public function assign(User $user, Card $card): bool
    {
        return $user->can('kanban.assign')
            || ($user->can('kanban.edit') && $card->created_by === $user->id);
    }

    /** Any viewer can comment */
    public function comment(User $user, Card $card): bool
    {
        return $user->can('kanban.view');
    }

    /** Can upload files */
    public function upload(User $user, Card $card): bool
    {
        return $user->can('kanban.edit')
            || $card->created_by === $user->id
            || $card->assignees->contains($user->id);
    }
}
