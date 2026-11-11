<?php

namespace App\Http\Controllers\Chat;

use App\Events\Chat\UserTypingEvent;
use App\Http\Controllers\Controller;
use App\Models\Chat\ChatConversation;
use App\Models\Chat\ChatConversationMember;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatMessageAttachment;
use App\Models\Chat\ChatUserSetting;
use App\Models\User;
use App\Services\Chat\DgtChatService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DgtChatController extends Controller
{
    public function __construct(
        private readonly DgtChatService $chatService,
        private readonly \App\Services\Chat\DgtChatCallService $callService
    ) {}

    /**
     * Display the DGT Chat main workspace.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        if (!$user->isChatEnabled()) {
            return redirect()->route('dashboard')->with('error', 'Chat access is currently disabled for your account. Please contact Dara or Super Admin.');
        }

        // Ensure user settings record exists
        $settings = ChatUserSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'theme'              => 'system',
                'bubble_color'       => '#4f46e5',
                'font_size'          => 'medium',
                'notification_sound' => true,
                'status_message'     => 'Available',
            ]
        );

        // Mark user as active in cache for online status
        \Illuminate\Support\Facades\Cache::put("user-is-online-{$user->id}", true, 180);
        \Illuminate\Support\Facades\Cache::put("user-last-seen-{$user->id}", now()->timestamp, 86400 * 30);

        $initialConversations = $this->chatService->getConversations($user);
        $requestedConvId = (int) ($request->get('conv') ?: $request->get('c') ?: 0);
        $initialMessages = $this->chatService->getInitialMessagesMap($user, $initialConversations, $requestedConvId ?: null, 8);
        $usersDirectory = User::where('is_active', true)
            ->where('id', '!=', $user->id)
            ->select(['id', 'name', 'avatar', 'team_role', 'last_login_at', 'can_use_chat', 'username'])
            ->orderBy('name')
            ->get()
            ->filter(fn($u) => $u->isChatEnabled())
            ->map(function ($u) {
                return [
                    'id'         => $u->id,
                    'name'       => $u->name,
                    'avatar_url' => $u->avatar_url,
                    'team_role'  => $u->team_role ?? 'Team Member',
                    'is_online'  => $u->isOnline(),
                    'last_seen'  => $u->getLastSeenFormatted(),
                ];
            })
            ->values();

        $iceServers = $this->callService->getIceServers();
        $activeCall = $this->callService->getActiveCall($user);

        return view('chat.index', compact('user', 'settings', 'initialConversations', 'initialMessages', 'usersDirectory', 'iceServers', 'activeCall'));
    }

    /**
     * Get user's conversation list.
     */
    public function getConversations(Request $request): JsonResponse
    {
        $user   = auth()->user();
        \Illuminate\Support\Facades\Cache::put("user-is-online-{$user->id}", true, 180);
        \Illuminate\Support\Facades\Cache::put("user-last-seen-{$user->id}", now()->timestamp, 86400 * 30);
        $tab    = $request->get('tab', 'all');
        $search = $request->get('search');

        $conversations = $this->chatService->getConversations($user, $tab, $search);

        return response()->json([
            'success'       => true,
            'conversations' => $conversations,
        ]);
    }

    /**
     * Start or open a 1-on-1 private chat.
     */
    public function startPrivateChat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = auth()->user();
        if ((int)$validated['user_id'] === $user->id) {
            return response()->json(['success' => false, 'error' => 'Cannot start chat with yourself.'], 400);
        }

        $otherUser = User::find((int)$validated['user_id']);
        if (!$otherUser || !$otherUser->isChatEnabled()) {
            return response()->json(['success' => false, 'error' => 'This user has not enabled chat.'], 403);
        }

        $conv = $this->chatService->getOrCreatePrivateChat($user, (int)$validated['user_id']);
        if (!$conv) {
            return response()->json(['success' => false, 'error' => 'Unable to start chat.'], 500);
        }

        return response()->json([
            'success'         => true,
            'conversation_id' => $conv->id,
            'conversation'    => [
                'id'          => $conv->id,
                'type'        => 'direct',
                'title'       => $conv->getDisplayTitle($user),
                'real_name'   => $conv->getRealTitle($user),
                'nickname'    => $conv->members->firstWhere('user_id', $user->id)?->custom_nickname,
                'avatar_url'  => $conv->getDisplayAvatar($user),
                'description' => $conv->description,
                'other_user_id'=> (int) $otherUser->id,
                'is_online'   => $otherUser->isOnline(),
                'last_seen'   => $otherUser->getLastSeenFormatted(),
                'team_role'   => $otherUser->team_role ?? 'Team Member',
                'unread_count'=> 0,
                'members'     => $conv->members,
            ],
        ]);
    }

    /**
     * Create a new group chat.
     */
    public function createGroup(Request $request): JsonResponse
    {
        $members = $request->input('members') ?: ($request->input('user_ids') ?: []);

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'avatar'      => ['nullable', 'image', 'max:5120'],
        ]);

        $user = auth()->user();
        $conv = $this->chatService->createGroup(
            $user,
            $validated['title'],
            $validated['description'] ?? null,
            is_array($members) ? $members : [],
            $request->file('avatar')
        );

        return response()->json([
            'success'         => true,
            'conversation_id' => $conv->id,
            'title'           => $conv->title,
            'conversation'    => [
                'id'          => $conv->id,
                'type'        => $conv->type,
                'title'       => $conv->title,
                'avatar_url'  => $conv->getDisplayAvatar($user),
                'description' => $conv->description,
                'unread_count'=> 0,
                'members'     => $conv->members,
            ],
        ]);
    }

    /**
     * Get message history for a conversation.
     */
    public function getMessages(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $conv = ChatConversation::with(['users:id,name,avatar,last_login_at', 'members'])->findOrFail($id);

        if (!$conv->members->contains('user_id', $user->id)) {
            return response()->json(['success' => false, 'error' => 'Unauthorized.'], 403);
        }

        $beforeId = $request->get('before_id');
        $query = ChatMessage::where('conversation_id', $conv->id)
            ->with(['user:id,name,avatar', 'attachments', 'replyTo.user:id,name', 'reactions.user:id,name', 'call.caller', 'call.receiver']);

        if ($beforeId) {
            $query->where('id', '<', (int)$beforeId);
        }

        $messages = $query->orderBy('id', 'desc')->take(50)->get()->reverse()->values();

        // Mark as read (skipped for silent background prefetch so nothing is marked "seen" by accident)
        if (!$request->boolean('prefetch')) {
            $this->chatService->markAsRead($user, $conv->id);
        }

        $memberPivot = $conv->members->firstWhere('user_id', $user->id);
        $isDirect = ($conv->type === 'private' || $conv->type === 'direct');
        $otherMember = $conv->members->firstWhere('user_id', '!=', $user->id);
        $otherUser = $isDirect ? ($conv->users->firstWhere('id', '!=', $user->id) ?? ($otherMember ? User::find($otherMember->user_id) : null)) : null;
        $otherLastReadId = $otherMember?->last_read_message_id ?? 0;

        return response()->json([
            'success'      => true,
            'conversation' => [
                'id'               => $conv->id,
                'type'             => $isDirect ? 'direct' : $conv->type,
                'title'            => $conv->getDisplayTitle($user),
                'avatar'           => $conv->getDisplayAvatar($user),
                'description'      => $conv->description,
                'other_user_id'    => $otherUser?->id,
                'is_online'        => $otherUser ? $otherUser->isOnline() : false,
                'last_seen'        => $otherUser ? $otherUser->getLastSeenFormatted() : 'Offline',
                'is_pinned'        => (bool) ($memberPivot?->is_pinned ?? false),
                'is_muted'         => (bool) ($memberPivot?->is_muted ?? false),
                'is_admin'         => $conv->isAdmin($user->id),
                'custom_wallpaper' => $memberPivot?->custom_wallpaper,
                'members_count'    => $conv->users->count(),
                'members'          => $conv->users->map(fn($u) => [
                    'id'        => $u->id,
                    'name'      => $u->name,
                    'avatar'    => $u->avatar ? asset('storage/' . $u->avatar) : null,
                    'role'      => $u->pivot->role,
                    'is_online' => $u->isOnline(),
                ])->all(),
            ],
            'messages'     => $messages->map(fn($m) => $this->chatService->formatMessage($m, $user, $otherLastReadId))->all(),
        ]);
    }

    /**
     * Delta sync messages since last message ID.
     */
    public function syncMessages(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        \Illuminate\Support\Facades\Cache::put("user-is-online-{$user->id}", true, 180);
        \Illuminate\Support\Facades\Cache::put("user-last-seen-{$user->id}", now()->timestamp, 86400 * 30);
        $afterId = (int)$request->get('after_id', 0);

        try {
            $newMessages = $this->chatService->syncMessages($user, $id, $afterId);
            // Since user is actively syncing this open chat window, ensure read pointer is current
            $this->chatService->markAsRead($user, $id);

            $conv = ChatConversation::with(['members', 'users'])->find($id);
            $partnerMember = $conv?->members->firstWhere('user_id', '!=', $user->id);
            $partnerUser = $partnerMember ? User::find($partnerMember->user_id) : $conv?->users->firstWhere('id', '!=', $user->id);
            $maxOtherReadId = $conv?->members->where('user_id', '!=', $user->id)->max('last_read_message_id') ?? 0;

            // Fetch active typing users in this conversation (excluding current viewer)
            $cacheKey = "chat:typing:conv:{$id}";
            $rawTyping = \Illuminate\Support\Facades\Cache::get($cacheKey, []);
            $activeTypers = [];
            $nowTs = now()->timestamp;
            if (is_array($rawTyping)) {
                foreach ($rawTyping as $uid => $tInfo) {
                    if ((int)$uid !== $user->id && ($tInfo['expires_at'] ?? 0) > $nowTs) {
                        $activeTypers[] = $tInfo['name'] ?? 'Someone';
                    }
                }
            }

            return response()->json([
                'success'              => true,
                'messages'             => $newMessages,
                'new_messages'         => $newMessages,
                'typing_users'         => array_values(array_unique($activeTypers)),
                'partner_last_read_id' => $maxOtherReadId,
                'partner_is_online'    => $partnerUser ? $partnerUser->isOnline() : false,
                'partner_last_seen'    => $partnerUser ? $partnerUser->getLastSeenFormatted() : 'Offline',
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 403);
        }
    }

    /**
     * Send a message with optional files.
     */
    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $content = $request->input('content') ?? $request->input('body', '');
        $clientTempId = $request->input('client_temp_id');
        $replyToId = $request->input('reply_to_id');
        $uploadedFiles = $request->file('files') ?: ($request->file('attachments') ?: []);

        if (empty(trim($content)) && empty($uploadedFiles)) {
            return response()->json(['success' => false, 'error' => 'Message or attachment required.'], 422);
        }

        $user = auth()->user();
        \Illuminate\Support\Facades\Cache::put("user-is-online-{$user->id}", true, 180);
        \Illuminate\Support\Facades\Cache::put("user-last-seen-{$user->id}", now()->timestamp, 86400 * 30);

        try {
            $message = $this->chatService->sendMessage(
                $user,
                $id,
                [
                    'content'          => $content,
                    'client_temp_id'   => $clientTempId,
                    'reply_to_id'      => $replyToId ? (int)$replyToId : null,
                    'type'             => $request->input('type'),
                    'duration_seconds' => $request->input('duration_seconds'),
                    'waveform_data'    => $request->input('waveform_data'),
                ],
                is_array($uploadedFiles) ? $uploadedFiles : [$uploadedFiles]
            );

            // Clear current user from active typing cache
            $cacheKey = "chat:typing:conv:{$id}";
            $typingList = \Illuminate\Support\Facades\Cache::get($cacheKey, []);
            if (is_array($typingList) && isset($typingList[$user->id])) {
                unset($typingList[$user->id]);
                \Illuminate\Support\Facades\Cache::put($cacheKey, $typingList, 15);
            }

            $message->load(['user:id,name,avatar', 'attachments', 'replyTo.user:id,name', 'reactions.user:id,name', 'call']);
            $formatted = $this->chatService->formatMessage($message, $user);
            if ($clientTempId) {
                $formatted['client_temp_id'] = $clientTempId;
            }

            return response()->json([
                'success' => true,
                'message' => $formatted,
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Mark conversation as read.
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        \Illuminate\Support\Facades\Cache::put("user-is-online-{$user->id}", true, 180);
        \Illuminate\Support\Facades\Cache::put("user-last-seen-{$user->id}", now()->timestamp, 86400 * 30);
        $messageId = $request->input('message_id');
        $this->chatService->markAsRead($user, $id, $messageId ? (int)$messageId : null);
        return response()->json(['success' => true]);
    }

    /**
     * Resolve conversation ID from numeric ID, 'member-XX' string, or user ID.
     */
    private function resolveConversationId(User $user, string|int $id): ?int
    {
        $idStr = trim((string) $id);
        if (str_starts_with($idStr, 'member-')) {
            $otherUserId = (int) str_replace('member-', '', $idStr);
            $conv = $this->chatService->getOrCreatePrivateChat($user, $otherUserId);
            return $conv?->id;
        }

        if (is_numeric($idStr)) {
            $convId = (int) $idStr;
            $exists = ChatConversationMember::where('conversation_id', $convId)->where('user_id', $user->id)->exists();
            if ($exists) {
                return $convId;
            }
            // Check if $convId was an other_user_id directly
            $conv = $this->chatService->getOrCreatePrivateChat($user, $convId);
            return $conv?->id;
        }

        return null;
    }

    /**
     * Toggle Pin conversation.
     */
    public function togglePin(Request $request, string|int $id): JsonResponse
    {
        $user = auth()->user();
        $convId = $this->resolveConversationId($user, $id);
        if (!$convId) {
            return response()->json(['success' => false, 'error' => 'Conversation not found.'], 404);
        }

        $member = ChatConversationMember::where('conversation_id', $convId)->where('user_id', $user->id)->firstOrFail();
        $member->is_pinned = !$member->is_pinned;
        $member->save();

        return response()->json([
            'success'         => true,
            'conversation_id' => $convId,
            'is_pinned'       => (bool) $member->is_pinned,
        ]);
    }

    /**
     * Toggle Archive conversation.
     */
    public function toggleArchive(Request $request, string|int $id): JsonResponse
    {
        $user = auth()->user();
        $convId = $this->resolveConversationId($user, $id);
        if (!$convId) {
            return response()->json(['success' => false, 'error' => 'Conversation not found.'], 404);
        }

        $member = ChatConversationMember::where('conversation_id', $convId)->where('user_id', $user->id)->firstOrFail();
        $member->is_archived = !$member->is_archived;
        $member->save();

        return response()->json([
            'success'         => true,
            'conversation_id' => $convId,
            'is_archived'     => (bool) $member->is_archived,
        ]);
    }

    /**
     * Toggle Mute conversation.
     */
    public function toggleMute(Request $request, string|int $id): JsonResponse
    {
        $user = auth()->user();
        $convId = $this->resolveConversationId($user, $id);
        if (!$convId) {
            return response()->json(['success' => false, 'error' => 'Conversation not found.'], 404);
        }

        $member = ChatConversationMember::where('conversation_id', $convId)->where('user_id', $user->id)->firstOrFail();
        $member->is_muted = !$member->is_muted;
        $member->save();

        return response()->json([
            'success'         => true,
            'conversation_id' => $convId,
            'is_muted'        => (bool) $member->is_muted,
        ]);
    }

    /**
     * Delete / hide conversation for current user.
     */
    public function deleteConversation(Request $request, string|int $id): JsonResponse
    {
        $user = auth()->user();
        $convId = $this->resolveConversationId($user, $id);
        if ($convId) {
            ChatConversationMember::where('conversation_id', $convId)->where('user_id', $user->id)->delete();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Update custom nickname for conversation.
     */
    public function updateNickname(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'nickname' => ['nullable', 'string', 'max:100'],
        ]);

        $user = auth()->user();
        $member = ChatConversationMember::where('conversation_id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $rawNickname = $validated['nickname'] ?? null;
        $nickname = !empty(trim($rawNickname ?? '')) ? trim($rawNickname) : null;

        $member->custom_nickname = $nickname;
        $member->save();

        $conv = ChatConversation::with(['users', 'members'])->findOrFail($id);

        return response()->json([
            'success'   => true,
            'nickname'  => $nickname,
            'title'     => $conv->getDisplayTitle($user),
            'real_name' => $conv->getRealTitle($user),
        ]);
    }

    /**
     * Toggle an emoji reaction on a message.
     */
    public function toggleReaction(Request $request, int $id): JsonResponse
    {
        $reaction = $request->input('reaction') ?? $request->input('emoji');
        if (empty($reaction)) {
            return response()->json(['success' => false, 'error' => 'Reaction required.'], 422);
        }

        $user = auth()->user();
        $result = $this->chatService->toggleReaction($user, $id, $reaction);

        return response()->json($result);
    }

    /**
     * Edit message content (author only).
     */
    public function editMessage(Request $request, int $id): JsonResponse
    {
        $content = $request->input('content') ?? $request->input('body');
        if (empty(trim($content))) {
            return response()->json(['success' => false, 'error' => 'Content cannot be empty.'], 422);
        }

        $user = auth()->user();
        $message = ChatMessage::findOrFail($id);

        if ($message->user_id !== $user->id) {
            return response()->json(['success' => false, 'error' => 'You can only edit your own messages.'], 403);
        }

        $message->update([
            'content'   => trim($content),
            'is_edited' => true,
            'edited_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => $this->chatService->formatMessage($message, $user)]);
    }

    /**
     * Delete message (author, group admin, or system supervisor).
     */
    public function deleteMessage(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $message = ChatMessage::with(['conversation', 'attachments'])->findOrFail($id);

        $canDelete = ($message->user_id === $user->id) 
            || ($message->conversation && $message->conversation->isAdmin($user->id))
            || $user->canManageChatPermissions();

        if (!$canDelete) {
            return response()->json(['success' => false, 'error' => 'Unauthorized.'], 403);
        }

        $convId = (int) $message->conversation_id;
        $messageId = (int) $message->id;

        // Clean up attached media files from disk and delete attachment records
        foreach ($message->attachments as $att) {
            try {
                if ($att->file_path) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($att->file_path);
                }
            } catch (\Throwable $e) {}
            try {
                $att->delete();
            } catch (\Throwable $e) {}
        }

        // Clean up message reactions
        try {
            $message->reactions()->delete();
        } catch (\Throwable $e) {}

        // Delete / soft delete the message
        $message->delete();

        // Broadcast real-time deletion event to all participants
        try {
            broadcast(new \App\Events\Chat\ChatMessageDeleted($convId, $messageId, $user->id))->toOthers();
        } catch (\Throwable $e) {}

        return response()->json([
            'success'         => true,
            'message_id'      => $messageId,
            'conversation_id' => $convId,
        ]);
    }

    /**
     * Broadcast typing indicator status.
     */
    public function sendTyping(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'integer', 'exists:chat_conversations,id'],
            'is_typing'       => ['required', 'boolean'],
        ]);

        $user = auth()->user();
        \Illuminate\Support\Facades\Cache::put("user-is-online-{$user->id}", true, 180);
        \Illuminate\Support\Facades\Cache::put("user-last-seen-{$user->id}", now()->timestamp, 86400 * 30);

        $convId = (int)$validated['conversation_id'];
        $isTyping = (bool)$validated['is_typing'];

        // Privacy: only members of the conversation may emit typing events into it
        $isMember = \App\Models\Chat\ChatConversationMember::where('conversation_id', $convId)
            ->where('user_id', $user->id)
            ->exists();
        if (!$isMember) {
            return response()->json(['success' => false, 'error' => 'Unauthorized.'], 403);
        }

        $cacheKey = "chat:typing:conv:{$convId}";

        $typingList = \Illuminate\Support\Facades\Cache::get($cacheKey, []);
        if (!is_array($typingList)) {
            $typingList = [];
        }

        if ($isTyping) {
            $typingList[$user->id] = [
                'id'         => $user->id,
                'name'       => $user->name,
                'expires_at' => now()->addSeconds(6)->timestamp,
            ];
        } else {
            unset($typingList[$user->id]);
        }
        \Illuminate\Support\Facades\Cache::put($cacheKey, $typingList, 15);

        // Typing signals are fire-and-forget: push them after the response so the request returns instantly
        $typingUserId = $user->id;
        $typingUserName = $user->name;
        app()->terminating(function () use ($convId, $typingUserId, $typingUserName, $isTyping) {
            try {
                broadcast(new UserTypingEvent(
                    $convId,
                    $typingUserId,
                    $typingUserName,
                    $isTyping
                ))->toOthers();
            } catch (\Throwable $e) {}
        });

        return response()->json(['success' => true]);
    }

    /**
     * Get directory of all active DGT users to start chats.
     */
    public function getUsersDirectory(Request $request): JsonResponse
    {
        $user = auth()->user();
        $users = User::where('is_active', true)
            ->where('id', '!=', $user->id)
            ->select(['id', 'name', 'avatar', 'team_role', 'last_login_at'])
            ->orderBy('name')
            ->get()
            ->map(fn($u) => [
                'id'        => $u->id,
                'name'      => $u->name,
                'avatar'    => $u->avatar ? asset('storage/' . $u->avatar) : null,
                'role'      => $u->team_role ?? 'Team Member',
                'is_online' => $u->isOnline(),
            ]);

        return response()->json([
            'success' => true,
            'users'   => $users,
        ]);
    }

    /**
     * Get unread message count for navbar badge.
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        $user = auth()->user();
        $count = $this->chatService->getUnreadCount($user);

        return response()->json([
            'success'      => true,
            'count'        => $count,
            'unread_count' => $count,
        ]);
    }

    /**
     * Update user chat settings & appearance.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme'              => ['nullable', 'string', 'in:light,dark,system'],
            'wallpaper'          => ['nullable', 'string', 'max:255'],
            'wallpaper_image'    => ['nullable', 'string'],
            'bubble_color'       => ['nullable', 'string', 'max:20'],
            'font_size'          => ['nullable', 'string', 'in:small,medium,large'],
            'notification_sound' => ['nullable', 'boolean'],
            'status_message'     => ['nullable', 'string', 'max:150'],
        ]);

        $user = auth()->user();
        $settings = ChatUserSetting::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        return response()->json([
            'success'  => true,
            'settings' => $settings,
        ]);
    }

    /**
     * Secure attachment download (authorized members only).
     */
    public function downloadAttachment(Request $request, int $id): BinaryFileResponse
    {
        $user = auth()->user();
        $attachment = ChatMessageAttachment::with('message.conversation')->findOrFail($id);

        if (!$attachment->message->conversation->hasMember($user->id)) {
            abort(403, 'Unauthorized to access this attachment.');
        }

        $fullPath = Storage::disk('public')->path($attachment->file_path);
        if (!file_exists($fullPath)) {
            $altPaths = [
                public_path('storage/' . ltrim($attachment->file_path, '/')),
                storage_path('app/public/' . ltrim($attachment->file_path, '/')),
                public_path(ltrim($attachment->file_path, '/')),
            ];
            foreach ($altPaths as $alt) {
                if (file_exists($alt)) {
                    $fullPath = $alt;
                    break;
                }
            }
        }
        if (!file_exists($fullPath)) {
            abort(404, 'File not found on storage.');
        }

        return response()->download($fullPath, $attachment->file_name);
    }

    /**
     * Inline attachment view/stream (images, videos, audio for in-app media preview).
     */
    public function viewAttachment(Request $request, int $id): BinaryFileResponse
    {
        $user = auth()->user();
        $attachment = ChatMessageAttachment::with('message.conversation')->findOrFail($id);

        if (!$attachment->message->conversation->hasMember($user->id)) {
            abort(403, 'Unauthorized to view this attachment.');
        }

        $fullPath = Storage::disk('public')->path($attachment->file_path);
        if (!file_exists($fullPath)) {
            $altPaths = [
                public_path('storage/' . ltrim($attachment->file_path, '/')),
                storage_path('app/public/' . ltrim($attachment->file_path, '/')),
                public_path(ltrim($attachment->file_path, '/')),
            ];
            foreach ($altPaths as $alt) {
                if (file_exists($alt)) {
                    $fullPath = $alt;
                    break;
                }
            }
        }
        if (!file_exists($fullPath)) {
            abort(404, 'File not found on storage.');
        }

        $contentType = $attachment->file_type ?: 'application/octet-stream';
        if ($contentType === 'application/octet-stream' || !str_contains($contentType, '/')) {
            $ext = strtolower(pathinfo($attachment->file_name ?: $attachment->file_path, PATHINFO_EXTENSION));
            $mimeMap = [
                'png'  => 'image/png',
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif'  => 'image/gif',
                'webp' => 'image/webp',
                'svg'  => 'image/svg+xml',
                'bmp'  => 'image/bmp',
                'ico'  => 'image/x-icon',
                'avif' => 'image/avif',
                'heic' => 'image/heic',
                'heif' => 'image/heif',
                'tif'  => 'image/tiff',
                'tiff' => 'image/tiff',
                'mp4'  => 'video/mp4',
                'webm' => 'video/webm',
                'mov'  => 'video/quicktime',
                'mp3'  => 'audio/mpeg',
                'wav'  => 'audio/wav',
                'ogg'  => 'audio/ogg',
            ];
            if (isset($mimeMap[$ext])) {
                $contentType = $mimeMap[$ext];
            }
        }

        return response()->file($fullPath, [
            'Content-Type'  => $contentType,
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Get or create user's Saved Messages conversation (Telegram-style cloud bookmark).
     */
    public function savedMessages(Request $request): JsonResponse
    {
        $user = auth()->user();
        $conv = $this->chatService->getOrCreateSavedMessagesChat($user);

        return response()->json([
            'success'         => true,
            'conversation_id' => $conv->id,
            'conversation'    => [
                'id'          => $conv->id,
                'type'        => $conv->type,
                'title'       => 'Saved Messages',
                'avatar_url'  => asset('favicon.svg'),
                'description' => 'Your personal cloud bookmarks & notes',
                'is_online'   => true,
                'unread_count'=> 0,
                'members'     => $conv->members,
            ],
        ]);
    }

    /**
     * Create an interactive poll in a conversation.
     */
    public function createPoll(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'question'         => ['required', 'string', 'max:255'],
            'options'          => ['required', 'array', 'min:2', 'max:10'],
            'options.*'        => ['required', 'string', 'max:150'],
            'multiple_answers' => ['nullable', 'boolean'],
            'is_anonymous'     => ['nullable', 'boolean'],
        ]);

        $user = auth()->user();

        try {
            $message = $this->chatService->createPoll(
                $user,
                $id,
                $validated['question'],
                $validated['options'],
                (bool) ($validated['multiple_answers'] ?? false),
                (bool) ($validated['is_anonymous'] ?? false)
            );

            return response()->json([
                'success' => true,
                'message' => $this->chatService->formatMessage($message, $user),
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Vote on an option in a poll.
     */
    public function votePoll(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'option_id' => ['required', 'integer'],
        ]);

        $user = auth()->user();

        try {
            $formattedMessage = $this->chatService->votePoll($user, $id, (int) $validated['option_id']);

            return response()->json([
                'success' => true,
                'message' => $formattedMessage,
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Unsend a message (delete for everyone with "message unsent" placeholder).
     */
    public function unsendMessage(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();

        try {
            $formattedMessage = $this->chatService->unsendMessage($user, $id);

            return response()->json([
                'success' => true,
                'message' => $formattedMessage,
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 403);
        }
    }

    /**
     * Forward a message to another conversation.
     */
    public function forwardMessage(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'target_conversation_id' => ['required', 'integer', 'exists:chat_conversations,id'],
        ]);

        $user = auth()->user();

        try {
            $message = $this->chatService->forwardMessage($user, $id, (int) $validated['target_conversation_id']);

            return response()->json([
                'success' => true,
                'message' => $this->chatService->formatMessage($message, $user),
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get user settings.
     */
    public function getSettings(Request $request): JsonResponse
    {
        $user = auth()->user();
        $settings = ChatUserSetting::firstOrCreate(['user_id' => $user->id]);

        return response()->json([
            'success'                => true,
            'settings'               => $settings,
            'can_manage_permissions' => $user->canManageChatPermissions(),
        ]);
    }

    /**
     * Get all users and their chat access status (Dara & Super Admin only).
     */
    public function getAdminUsersPermissions(Request $request): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser->canManageChatPermissions()) {
            return response()->json(['success' => false, 'error' => 'Unauthorized.'], 403);
        }

        $users = User::where('is_active', true)
            ->select(['id', 'name', 'username', 'email', 'avatar', 'team_role', 'can_use_chat'])
            ->orderBy('name')
            ->get()
            ->map(function ($u) {
                return [
                    'id'           => $u->id,
                    'name'         => $u->name,
                    'username'     => $u->username,
                    'email'        => $u->email,
                    'avatar_url'   => $u->avatar_url,
                    'team_role'    => $u->team_role ?? 'Member',
                    'can_use_chat' => (bool) ($u->can_use_chat ?? true),
                    'is_manager'   => $u->canManageChatPermissions(),
                ];
            });

        return response()->json([
            'success' => true,
            'users'   => $users,
        ]);
    }

    /**
     * Toggle chat access for a user (Dara & Super Admin only).
     */
    public function toggleUserChatPermission(Request $request): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser->canManageChatPermissions()) {
            return response()->json(['success' => false, 'error' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'user_id'      => ['required', 'integer', 'exists:users,id'],
            'can_use_chat' => ['nullable', 'boolean'],
        ]);

        $targetUser = User::findOrFail($validated['user_id']);

        if ($targetUser->canManageChatPermissions() && isset($validated['can_use_chat']) && !$validated['can_use_chat']) {
            return response()->json([
                'success' => false,
                'error'   => 'Cannot disable chat for Dara or Super Admin.',
            ], 422);
        }

        $newVal = isset($validated['can_use_chat'])
            ? (bool) $validated['can_use_chat']
            : !((bool) ($targetUser->can_use_chat ?? true));

        $targetUser->update(['can_use_chat' => $newVal]);

        return response()->json([
            'success'      => true,
            'user_id'      => $targetUser->id,
            'can_use_chat' => (bool) $targetUser->can_use_chat,
            'message'      => "Chat access for {$targetUser->name} has been " . ($newVal ? 'ENABLED' : 'DISABLED') . ".",
        ]);
    }

    /**
     * Batch toggle all users chat access (Dara & Super Admin only).
     */
    public function batchToggleChatPermissions(Request $request): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser->canManageChatPermissions()) {
            return response()->json(['success' => false, 'error' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'enable' => ['required', 'boolean'],
        ]);

        $enable = (bool) $validated['enable'];

        User::query()->update(['can_use_chat' => $enable]);

        return response()->json([
            'success' => true,
            'message' => 'Chat access for all users updated successfully.',
        ]);
    }

    /**
     * Convert chat images to WebP manually on demand (Dara & Super Admin only).
     */
    public function convertImagesWebpNow(Request $request): JsonResponse
    {
        $currentUser = auth()->user();
        if (!$currentUser->canManageChatPermissions()) {
            return response()->json(['success' => false, 'error' => 'Unauthorized.'], 403);
        }

        \Illuminate\Support\Facades\Artisan::call('chat:convert-images-webp', ['--force' => true]);
        $output = \Illuminate\Support\Facades\Artisan::output();

        return response()->json([
            'success' => true,
            'output'  => trim($output),
            'message' => 'Image conversion to WebP completed.',
        ]);
    }

    /**
     * Send a voice message note (Telegram style).
     */
    public function sendVoiceMessage(Request $request, ?int $id = null): JsonResponse
    {
        $conversationId = $id ?: (int)$request->input('conversation_id');
        if (!$conversationId) {
            return response()->json(['success' => false, 'error' => 'Conversation ID is required.'], 422);
        }

        $audioFile = $request->file('voice_file') ?: $request->file('audio');
        if (!$audioFile) {
            return response()->json(['success' => false, 'error' => 'Audio recording file is required.'], 422);
        }

        $duration = (int)($request->input('duration') ?: $request->input('duration_seconds', 1));
        $clientTempId = $request->input('client_temp_id');
        $user = auth()->user();

        try {
            $waveform = $request->input('waveform_data');
            if (is_string($waveform)) {
                $waveform = json_decode($waveform, true);
            }

            $message = $this->chatService->sendMessage(
                $user,
                $conversationId,
                [
                    'content'          => '🎤 Voice Message',
                    'type'             => 'voice',
                    'duration_seconds' => $duration,
                    'waveform_data'    => $waveform,
                    'client_temp_id'   => $clientTempId,
                ],
                [$audioFile]
            );

            $message->load(['user:id,name,avatar', 'attachments', 'replyTo.user:id,name', 'reactions.user:id,name', 'call']);
            $formatted = $this->chatService->formatMessage($message, $user);
            if ($clientTempId) {
                $formatted['client_temp_id'] = $clientTempId;
            }

            return response()->json([
                'success' => true,
                'message' => $formatted,
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Initiate a 1-to-1 WebRTC Voice or Video Call.
     */
    public function initiateCall(Request $request): JsonResponse
    {
        $user = auth()->user();
        $receiverId = (int) $request->input('receiver_id');
        $conversationId = (int) $request->input('conversation_id');
        $callType = in_array($request->input('call_type'), ['voice', 'video'], true)
            ? $request->input('call_type')
            : 'voice';

        if (!$receiverId) {
            return response()->json(['success' => false, 'error' => 'Receiver contact is required.'], 400);
        }

        // Auto-resolve or create private conversation between caller and receiver if not provided
        if (!$conversationId) {
            $conv = $this->chatService->getOrCreatePrivateChat($user, $receiverId);
            $conversationId = $conv?->id ?? 0;
        }

        if (!$conversationId) {
            return response()->json(['success' => false, 'error' => 'Conversation could not be initiated.'], 400);
        }

        try {
            $call = $this->callService->initiateCall(
                $user,
                $conversationId,
                $receiverId,
                $callType
            );

            $formattedCall = $this->callService->formatCall($call, $user);
            $formattedCall['call_type'] = $callType;

            return response()->json([
                'success' => true,
                'call'    => $formattedCall,
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Send WebRTC signal (Offer, Answer, ICE candidate, Mute, Video Toggle, End).
     */
    public function sendCallSignal(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'type'    => ['required', 'string', 'in:offer,answer,ice-candidate,ice_candidate,status,mute,unmute,video_on,video_off,renegotiate,end'],
            'payload' => ['nullable', 'array'],
        ]);

        $user = auth()->user();

        try {
            $signal = $this->callService->sendSignal(
                $user,
                $id,
                $validated['type'],
                $validated['payload'] ?? []
            );

            return response()->json([
                'success'   => true,
                'signal_id' => $signal->id,
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Retrieve pending signals for a call (polling fallback / hybrid).
     */
    public function getCallSignals(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $afterId = (int) ($request->get('after_id') ?? $request->get('last_signal_id') ?? 0);

        try {
            $call = \App\Models\Chat\ChatCall::findOrFail($id);
            $signals = $this->callService->getPendingSignals($user, $id, $afterId);

            return response()->json([
                'success'     => true,
                'call_status' => $call->status,
                'signals'     => $signals,
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Accept incoming call.
     */
    public function acceptCall(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();

        try {
            $call = $this->callService->acceptCall($user, $id);

            return response()->json([
                'success' => true,
                'call'    => $this->callService->formatCall($call, $user),
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Decline incoming call.
     */
    public function declineCall(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $reason = $request->input('reason', 'declined');

        try {
            $call = $this->callService->declineCall($user, $id, $reason);

            return response()->json([
                'success' => true,
                'call'    => $this->callService->formatCall($call, $user),
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * End active call or cancel outgoing call.
     */
    public function endCall(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $reason = $request->input('reason', 'normal');

        try {
            $call = $this->callService->endCall($user, $id, $reason);

            return response()->json([
                'success' => true,
                'call'    => $this->callService->formatCall($call, $user),
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Check if user has an active or ringing call.
     */
    public function getActiveCall(Request $request): JsonResponse
    {
        $user = auth()->user();
        $activeCall = $this->callService->getActiveCall($user);

        return response()->json([
            'success'     => true,
            'active'      => (bool)$activeCall,
            'call'        => $activeCall,
            'active_call' => $activeCall,
        ]);
    }

    /**
     * Get all available preset and user custom stickers.
     * System presets (user_id IS NULL) are available to everyone.
     * User custom or saved stickers (user_id IS NOT NULL) are strictly PRIVATE to the owner.
     */
    public function getStickers(): JsonResponse
    {
        $userId = auth()->id();
        $dbStickers = \App\Models\Chat\ChatSticker::where(function ($q) use ($userId) {
                $q->whereNull('user_id')
                  ->orWhere('user_id', $userId);
            })
            ->orderByRaw('CASE WHEN user_id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('id', 'desc')
            ->get();

        $presets = [
            ['id' => 9001, 'name' => 'Thank... Kim', 'category' => 'team', 'image_url' => asset('images/stickers/thank_kim.svg')],
            ['id' => 9002, 'name' => 'Jo hah', 'category' => 'reaction', 'image_url' => asset('images/stickers/jo_hah.svg')],
            ['id' => 9003, 'name' => 'JoX Sunglasses', 'category' => 'reaction', 'image_url' => asset('images/stickers/jox.svg')],
            ['id' => 9004, 'name' => 'FU YAK', 'category' => 'meme', 'image_url' => asset('images/stickers/fu_yak.svg')],
            ['id' => 9005, 'name' => 'Hart mee???', 'category' => 'meme', 'image_url' => asset('images/stickers/hart_mee.svg')],
            ['id' => 9006, 'name' => 'Blocked Doggo', 'category' => 'meme', 'image_url' => asset('images/stickers/blocked.svg')],
            ['id' => 9007, 'name' => 'Plom? Cat', 'category' => 'meme', 'image_url' => asset('images/stickers/plom_cat.svg')],
            ['id' => 9008, 'name' => 'Nis Caption!', 'category' => 'team', 'image_url' => asset('images/stickers/nis_caption.svg')],
            ['id' => 9009, 'name' => 'Qi bu dui', 'category' => 'reaction', 'image_url' => asset('images/stickers/qi_bu_dui.svg')],
            ['id' => 9010, 'name' => 'Fire Explosion', 'category' => 'reaction', 'image_url' => asset('images/stickers/fire_exp.svg')],
        ];

        // Existing DB sticker names
        $existingNames = $dbStickers->pluck('name')->map(fn($n) => strtolower(trim($n)))->all();
        $missingPresets = collect($presets)->filter(function ($p) use ($existingNames) {
            return !in_array(strtolower(trim($p['name'])), $existingNames, true);
        })->values();

        // Custom stickers on top, followed by team presets
        $allStickers = $dbStickers->concat($missingPresets);

        return response()->json([
            'success'  => true,
            'stickers' => $allStickers->values(),
        ]);
    }

    /**
     * Create or save custom sticker (upload, canvas base64, or save from message URL).
     */
    public function createSticker(Request $request): JsonResponse
    {
        $user = auth()->user();
        $name = trim($request->input('name', 'Custom Sticker'));
        $category = $request->input('category', 'custom');
        $storedPath = null;

        if ($request->hasFile('sticker_image')) {
            $file = $request->file('sticker_image');
            $ext = $file->getClientOriginalExtension() ?: 'png';
            $filename = 'sticker_' . time() . '_' . \Illuminate\Support\Str::random(8) . '.' . $ext;
            $storedPath = $file->storeAs('chat_stickers', $filename, 'public');
        } elseif ($request->filled('image_data')) {
            $imageData = $request->input('image_data');
            if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $type)) {
                $data = substr($imageData, strpos($imageData, ',') + 1);
                $type = strtolower($type[1]);
                $decoded = base64_decode($data);
                if ($decoded === false) {
                    return response()->json(['success' => false, 'error' => 'Invalid image data.'], 422);
                }
                $filename = 'sticker_' . time() . '_' . \Illuminate\Support\Str::random(8) . '.' . $type;
                \Illuminate\Support\Facades\Storage::disk('public')->put('chat_stickers/' . $filename, $decoded);
                $storedPath = 'chat_stickers/' . $filename;
            }
        } elseif ($request->filled('image_url')) {
            $imageUrl = trim($request->input('image_url'));
            if ($imageUrl) {
                if (str_contains($imageUrl, '/chat_stickers/')) {
                    $parts = explode('/chat_stickers/', $imageUrl);
                    $existingRel = 'chat_stickers/' . end($parts);
                    if (\Illuminate\Support\Facades\Storage::disk('public')->exists($existingRel)) {
                        $ext = pathinfo($existingRel, PATHINFO_EXTENSION) ?: 'png';
                        $filename = 'sticker_' . time() . '_' . \Illuminate\Support\Str::random(8) . '.' . $ext;
                        \Illuminate\Support\Facades\Storage::disk('public')->copy($existingRel, 'chat_stickers/' . $filename);
                        $storedPath = 'chat_stickers/' . $filename;
                    } else {
                        $storedPath = $existingRel;
                    }
                } elseif (str_contains($imageUrl, '/images/stickers/')) {
                    $parts = explode('/images/stickers/', $imageUrl);
                    $svgName = end($parts);
                    $localPath = public_path('images/stickers/' . $svgName);
                    if (file_exists($localPath)) {
                        $filename = 'sticker_' . time() . '_' . \Illuminate\Support\Str::random(8) . '_' . $svgName;
                        \Illuminate\Support\Facades\Storage::disk('public')->put('chat_stickers/' . $filename, file_get_contents($localPath));
                        $storedPath = 'chat_stickers/' . $filename;
                    } else {
                        $storedPath = 'images/stickers/' . $svgName;
                    }
                } elseif (str_starts_with($imageUrl, 'http://') || str_starts_with($imageUrl, 'https://')) {
                    try {
                        $contents = @file_get_contents($imageUrl);
                        if ($contents) {
                            $filename = 'sticker_' . time() . '_' . \Illuminate\Support\Str::random(8) . '.png';
                            \Illuminate\Support\Facades\Storage::disk('public')->put('chat_stickers/' . $filename, $contents);
                            $storedPath = 'chat_stickers/' . $filename;
                        }
                    } catch (\Throwable $e) {}
                    if (!$storedPath) {
                        $storedPath = $imageUrl;
                    }
                } else {
                    $storedPath = $imageUrl;
                }
            }
        }

        if (!$storedPath) {
            return response()->json(['success' => false, 'error' => 'No sticker image provided.'], 422);
        }

        $sticker = \App\Models\Chat\ChatSticker::create([
            'user_id'    => $user->id,
            'name'       => $name,
            'image_path' => $storedPath,
            'category'   => $category,
            'is_public'  => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sticker saved successfully!',
            'sticker' => $sticker,
        ]);
    }

    /**
     * Delete user custom sticker.
     */
    public function deleteSticker(int $id): JsonResponse
    {
        $user = auth()->user();
        $sticker = \App\Models\Chat\ChatSticker::findOrFail($id);
        if ($sticker->user_id === null && !$user->canManageChatPermissions()) {
            return response()->json(['success' => false, 'error' => 'Cannot delete system preset stickers.'], 403);
        }
        if ($sticker->user_id !== null && $sticker->user_id !== $user->id && !$user->canManageChatPermissions()) {
            return response()->json(['success' => false, 'error' => 'Unauthorized to delete this sticker.'], 403);
        }
        $sticker->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Search worldwide GIFs or return trending GIFs with fast caching.
     */
    public function searchGifs(Request $request): JsonResponse
    {
        $query = trim($request->input('q', ''));
        $cacheKey = 'chat_gifs_search_' . md5($query);

        $gifs = \Illuminate\Support\Facades\Cache::remember($cacheKey, 600, function () use ($query) {
            try {
                $apiKey = 'sXpGFDGZs0Dv1mmNFvYaGUvYwKX0PWIh'; // Giphy public SDK key
                $endpoint = !empty($query)
                    ? "https://api.giphy.com/v1/gifs/search?api_key={$apiKey}&q=" . urlencode($query) . "&limit=32&rating=g"
                    : "https://api.giphy.com/v1/gifs/trending?api_key={$apiKey}&limit=32&rating=g";

                $response = \Illuminate\Support\Facades\Http::timeout(3.5)->get($endpoint);
                if ($response->successful()) {
                    $json = $response->json();
                    if (!empty($json['data']) && is_array($json['data'])) {
                        return array_map(function ($item) {
                            return [
                                'id'          => $item['id'] ?? uniqid(),
                                'title'       => $item['title'] ?? 'GIF',
                                'url'         => $item['images']['fixed_height']['url'] ?? ($item['images']['original']['url'] ?? ''),
                                'preview_url' => $item['images']['fixed_height_small']['url'] ?? ($item['images']['fixed_height']['url'] ?? ''),
                                'width'       => (int) ($item['images']['fixed_height']['width'] ?? 200),
                                'height'      => (int) ($item['images']['fixed_height']['height'] ?? 200),
                            ];
                        }, $json['data']);
                    }
                }
            } catch (\Throwable $e) {}

            return $this->getCuratedFallbackGifs($query);
        });

        return response()->json(['success' => true, 'gifs' => $gifs]);
    }

    protected function getCuratedFallbackGifs(string $query = ''): array
    {
        $curated = [
            ['title' => 'Happy Birthday Cat', 'url' => 'https://media.giphy.com/media/ICOgUNjpvO0PC/giphy.gif', 'preview_url' => 'https://media.giphy.com/media/ICOgUNjpvO0PC/giphy.gif'],
            ['title' => 'Good Morning Coffee', 'url' => 'https://media.giphy.com/media/3o6ozk8XnuhWuhv8vC/giphy.gif', 'preview_url' => 'https://media.giphy.com/media/3o6ozk8XnuhWuhv8vC/giphy.gif'],
            ['title' => 'Ciao Ciao Ciao', 'url' => 'https://media.giphy.com/media/3o7TKMt1VVNkHV2PaE/giphy.gif', 'preview_url' => 'https://media.giphy.com/media/3o7TKMt1VVNkHV2PaE/giphy.gif'],
            ['title' => 'Celebration Dance', 'url' => 'https://media.giphy.com/media/blSTtZehjAZ8I/giphy.gif', 'preview_url' => 'https://media.giphy.com/media/blSTtZehjAZ8I/giphy.gif'],
            ['title' => 'Laughing Out Loud', 'url' => 'https://media.giphy.com/media/10JhviFuU2gWD6/giphy.gif', 'preview_url' => 'https://media.giphy.com/media/10JhviFuU2gWD6/giphy.gif'],
            ['title' => 'Thumbs Up Great Job', 'url' => 'https://media.giphy.com/media/111ebonMs90YLu/giphy.gif', 'preview_url' => 'https://media.giphy.com/media/111ebonMs90YLu/giphy.gif'],
            ['title' => 'Mind Blown', 'url' => 'https://media.giphy.com/media/26ufdipQqU2lhNA4g/giphy.gif', 'preview_url' => 'https://media.giphy.com/media/26ufdipQqU2lhNA4g/giphy.gif'],
            ['title' => 'Cheers Beer Party', 'url' => 'https://media.giphy.com/media/zw97U5DZjg0OA/giphy.gif', 'preview_url' => 'https://media.giphy.com/media/zw97U5DZjg0OA/giphy.gif'],
            ['title' => 'Dancing Dog', 'url' => 'https://media.giphy.com/media/l41lI4bYmcsPJX9Go/giphy.gif', 'preview_url' => 'https://media.giphy.com/media/l41lI4bYmcsPJX9Go/giphy.gif'],
            ['title' => 'Thank You So Much', 'url' => 'https://media.giphy.com/media/3oz8xIsloV7zOmt81G/giphy.gif', 'preview_url' => 'https://media.giphy.com/media/3oz8xIsloV7zOmt81G/giphy.gif'],
            ['title' => 'High Five', 'url' => 'https://media.giphy.com/media/3oEjHV0z8S7WM4MwnK/giphy.gif', 'preview_url' => 'https://media.giphy.com/media/3oEjHV0z8S7WM4MwnK/giphy.gif'],
            ['title' => 'Yes Exactly', 'url' => 'https://media.giphy.com/media/3oFzmdYd4bfEO2JlvO/giphy.gif', 'preview_url' => 'https://media.giphy.com/media/3oFzmdYd4bfEO2JlvO/giphy.gif'],
        ];

        if ($query) {
            $q = strtolower($query);
            $filtered = array_values(array_filter($curated, fn($g) => str_contains(strtolower($g['title']), $q)));
            return !empty($filtered) ? $filtered : $curated;
        }

        return $curated;
    }

    /**
     * Get shared media, docs, and links for conversation profile card.
     */
    public function getConversationMedia(string|int $id): JsonResponse
    {
        $user = auth()->user();
        $convId = $this->resolveConversationId($user, $id);
        $conv = \App\Models\Chat\ChatConversation::findOrFail($convId);

        if (!$conv->hasMember($user->id)) {
            return response()->json(['success' => false, 'error' => 'Unauthorized.'], 403);
        }

        $attachments = \App\Models\Chat\ChatMessageAttachment::whereHas('message', function ($q) use ($convId) {
                $q->where('conversation_id', $convId)->where('is_unsent', false);
            })
            ->with('message:id,created_at')
            ->orderBy('id', 'desc')
            ->get();

        $media = [];
        $docs  = [];

        foreach ($attachments as $a) {
            $mime = strtolower($a->file_type ?? '');
            $isMedia = str_starts_with($mime, 'image/') || str_starts_with($mime, 'video/');
            $item = [
                'id'           => $a->id,
                'message_id'   => $a->message_id,
                'file_name'    => $a->file_name,
                'file_size'    => $a->formatted_size,
                'file_type'    => $a->file_type,
                'file_url'     => asset('storage/' . $a->file_path),
                'view_url'     => route('chat.attachments.view', $a->id),
                'download_url' => route('chat.attachments.download', $a->id),
                'created_at'   => $a->message?->created_at?->format('M d, Y') ?? 'Recently',
            ];
            if ($isMedia) {
                $media[] = $item;
            } else {
                $docs[] = $item;
            }
        }

        $messagesWithLinks = \App\Models\Chat\ChatMessage::where('conversation_id', $convId)
            ->where('is_unsent', false)
            ->where(function ($q) {
                $q->whereNotNull('link_preview')
                  ->orWhere('content', 'LIKE', '%http%');
            })
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        $links = [];
        foreach ($messagesWithLinks as $msg) {
            if ($msg->link_preview && is_array($msg->link_preview) && !empty($msg->link_preview['url'])) {
                $links[] = [
                    'url'         => $msg->link_preview['url'],
                    'title'       => $msg->link_preview['title'] ?? $msg->link_preview['url'],
                    'description' => $msg->link_preview['description'] ?? '',
                    'image'       => $msg->link_preview['image'] ?? null,
                    'created_at'  => $msg->created_at->format('M d, Y'),
                ];
            } elseif (preg_match('/(https?:\/\/[^\s<>"]+)/i', $msg->content ?? '', $m)) {
                $links[] = [
                    'url'         => $m[1],
                    'title'       => $m[1],
                    'description' => '',
                    'image'       => null,
                    'created_at'  => $msg->created_at->format('M d, Y'),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'media'   => $media,
            'docs'    => $docs,
            'links'   => $links,
            'counts'  => [
                'media' => count($media),
                'docs'  => count($docs),
                'links' => count($links),
                'total' => count($media) + count($docs) + count($links),
            ]
        ]);
    }

    /**
     * Update group profile (title, description, avatar) or user status text.
     */
    public function updateProfile(Request $request, string|int $id): JsonResponse
    {
        $user = auth()->user();
        $convId = $this->resolveConversationId($user, $id);
        $conv = \App\Models\Chat\ChatConversation::findOrFail($convId);

        if (!$conv->hasMember($user->id)) {
            return response()->json(['success' => false, 'error' => 'Unauthorized.'], 403);
        }

        if ($conv->type === 'group') {
            if ($request->has('title') && trim($request->input('title'))) {
                $conv->title = trim($request->input('title'));
            }
            if ($request->has('description')) {
                $conv->description = trim($request->input('description'));
            }
            if ($request->hasFile('avatar')) {
                $conv->avatar = $request->file('avatar')->store('chat_avatars', 'public');
            }
            $conv->save();
        }

        if ($request->has('status_text')) {
            $settings = \App\Models\Chat\ChatUserSettings::firstOrCreate(['user_id' => $user->id]);
            $settings->status_text = trim($request->input('status_text'));
            $settings->save();
        }

        return response()->json([
            'success'      => true,
            'message'      => 'Profile updated successfully!',
            'conversation' => $this->chatService->formatConversation($conv, $user),
        ]);
    }
}
