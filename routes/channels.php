<?php

use App\Models\Board;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('boards.{boardId}', function ($user, $boardId) {
    $board = Board::with('workspace')->find($boardId);

    if (! $board) {
        return false;
    }

    if ($user->hasAnyRole(['super-admin', 'admin', 'admin-digital', 'supervisor', 'boss'])) {
        return true;
    }

    return $board->hasMember($user->id) || $board->workspace?->hasMember($user->id);
});
