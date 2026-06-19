<?php

namespace App\Policies;

use App\Models\NoteFolder;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class NoteFolderPolicy
{
    private function hasTeamAccess(User $user, string $team): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        switch ($team) {
            case 'digital':
                return $user->hasAnyRole(['admin-digital', 'digital-team']);
            case 'crm':
                return $user->hasAnyRole(['admin-crm', 'sales-crm']);
            case 'logistic':
                return $user->hasAnyRole(['logistic']);
            case 'admin':
                return $user->hasAnyRole(['admin-digital', 'admin-crm', 'boss']);
            default:
                return false;
        }
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, NoteFolder $noteFolder): bool
    {
        if ($noteFolder->type === 'private') {
            return $user->id === $noteFolder->user_id;
        }
        return $this->hasTeamAccess($user, $noteFolder->team);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, array $data): bool
    {
        if (($data['type'] ?? 'private') === 'private') {
            return true;
        }
        if (isset($data['team'])) {
            return $this->hasTeamAccess($user, $data['team']);
        }
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, NoteFolder $noteFolder): bool
    {
        if ($noteFolder->type === 'private') {
            return $user->id === $noteFolder->user_id;
        }
        return $this->hasTeamAccess($user, $noteFolder->team);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, NoteFolder $noteFolder): bool
    {
        if ($noteFolder->type === 'private') {
            return $user->id === $noteFolder->user_id;
        }
        if ($user->hasRole('super-admin') || $user->id === $noteFolder->user_id) {
            return true;
        }
        return $this->hasTeamAccess($user, $noteFolder->team);
    }
}
