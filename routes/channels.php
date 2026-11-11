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

Broadcast::channel('websites', function ($user) {
    return $user->hasWebsiteAccess();
});

Broadcast::channel('inquiries', function ($user) {
    return $user->hasWebsiteAccess();
});

Broadcast::channel('chat.user.{userId}', function ($user, $userId) {
    if (!$user->isChatEnabled()) {
        return false;
    }
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('chat.conversation.{conversationId}', function ($user, $conversationId) {
    if (!$user->isChatEnabled()) {
        return false;
    }
    return \App\Models\Chat\ChatConversationMember::where('conversation_id', $conversationId)
        ->where('user_id', $user->id)
        ->exists();
});
