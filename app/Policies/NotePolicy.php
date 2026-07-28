<?php

namespace App\Policies;

use App\Models\Note;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class NotePolicy
{
    /**
     * Helper to check if a user belongs to a specific team
     */
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
        return true; // We filter at the query level for indexes
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Note $note): bool
    {
        if ($note->type === 'private') {
            return $user->id === $note->user_id; // ONLY owner can view private notes
        }

        // It's a team note
        return $this->hasTeamAccess($user, $note->team);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, array $data): bool
    {
        if (($data['type'] ?? 'private') === 'private') {
            return true; // Anyone can create a private note
        }

        // If creating a team note, ensure they have access to that team
        if (isset($data['team'])) {
            return $this->hasTeamAccess($user, $data['team']);
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Note $note): bool
    {
        if ($note->type === 'private') {
            return $user->id === $note->user_id; // ONLY owner can update private notes
        }

        // For team notes, members of that team can edit
        return $this->hasTeamAccess($user, $note->team);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Note $note): bool
    {
        if ($note->type === 'private') {
            return $user->id === $note->user_id; // ONLY owner can delete private notes
        }

        // For team notes, maybe only creator or admins can delete?
        // Let's allow team access for now, or just owner/super-admin
        if ($user->hasRole('super-admin') || $user->id === $note->user_id) {
            return true;
        }
        
        return $this->hasTeamAccess($user, $note->team);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Note $note): bool
    {
        return $this->delete($user, $note);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Note $note): bool
    {
        return $this->delete($user, $note);
    }
}
