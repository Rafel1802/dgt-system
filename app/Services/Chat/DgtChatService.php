<?php

namespace App\Services\Chat;

use App\Events\Chat\ChatMessageSent;
use App\Events\Chat\MessageReactionUpdated;
use App\Models\Chat\ChatConversation;
use App\Models\Chat\ChatConversationMember;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatMessageAttachment;
use App\Models\Chat\ChatMessageReaction;
use App\Models\Chat\ChatUserSetting;
use App\Models\User;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DgtChatService
{
    /**
     * Get list of conversations for a user with unread counts and last message previews.
     */
    public function getConversations(User $user, ?string $tab = 'all', ?string $search = null): Collection
    {
        // 1. Ensure private conversations exist for all active colleagues (cached to avoid redundant DB scans)
        $cacheKey = "chat:ensured_private:{$user->id}";
        if (!\Illuminate\Support\Facades\Cache::has($cacheKey)) {
            $this->ensurePrivateConversationsExist($user);
            \Illuminate\Support\Facades\Cache::put($cacheKey, true, 3600);
        }

        $query = ChatConversation::whereHas('members', fn($q) => $q->where('user_id', $user->id))
            ->with([
                'members',
                'users:id,name,avatar,team_role,last_login_at',
                'lastMessage.user:id,name',
            ]);

        // Filter by Tab (archived, active/unarchived, direct, groups)
        if ($tab === 'archived') {
            $query->whereHas('members', fn($q) => $q->where('user_id', $user->id)->where('is_archived', true));
        } elseif ($tab === 'active' || $tab === 'unarchived') {
            $query->whereHas('members', fn($q) => $q->where('user_id', $user->id)->where('is_archived', false));
        }
        // Note: when $tab is 'all' or null, we return all conversations (both active & archived)
        // with their boolean is_archived property so the client-side SPA can filter dynamically.

        if ($tab === 'direct') {
            $query->whereIn('type', ['private', 'direct']);
        } elseif ($tab === 'groups') {
            $query->where('type', 'group');
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search, $user) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('users', fn($uq) => $uq->where('id', '!=', $user->id)->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('members', fn($mq) => $mq->where('user_id', $user->id)->where('custom_nickname', 'like', "%{$search}%"));
            });
        }

        $conversations = $query->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->get();

        // 2. High-performance batch unread computation & automatic last_message healing
        $convIds = $conversations->pluck('id')->all();
        $unreadCounts = [];
        if (!empty($convIds)) {
            $latestMsgIdsByConv = ChatMessage::whereIn('conversation_id', $convIds)
                ->whereNull('deleted_at')
                ->selectRaw('conversation_id, MAX(id) as max_id')
                ->groupBy('conversation_id')
                ->pluck('max_id', 'conversation_id')
                ->all();

            $healedMsgIds = [];
            foreach ($conversations as $c) {
                $realMaxId = isset($latestMsgIdsByConv[$c->id]) ? (int) $latestMsgIdsByConv[$c->id] : 0;
                if ($realMaxId > 0 && (!$c->lastMessage || (int) $c->last_message_id !== $realMaxId)) {
                    $healedMsgIds[] = $realMaxId;
                } elseif ($realMaxId === 0 && ($c->last_message_id !== null || ($c->last_message_at !== null && $c->title !== 'Saved Messages'))) {
                    $c->setRelation('lastMessage', null);
                    $c->last_message_id = null;
                    $c->last_message_at = null;
                    ChatConversation::where('id', $c->id)->update([
                        'last_message_id' => null,
                        'last_message_at' => null,
                    ]);
                }
            }

            if (!empty($healedMsgIds)) {
                $healedMsgs = ChatMessage::whereIn('id', $healedMsgIds)
                    ->with('user:id,name')
                    ->get()
                    ->keyBy('conversation_id');
                foreach ($conversations as $c) {
                    if (isset($healedMsgs[$c->id])) {
                        $hm = $healedMsgs[$c->id];
                        $c->setRelation('lastMessage', $hm);
                        $c->last_message_id = $hm->id;
                        $c->last_message_at = $hm->created_at;
                        ChatConversation::where('id', $c->id)->update([
                            'last_message_id' => $hm->id,
                            'last_message_at' => $hm->created_at,
                        ]);
                    }
                }
            }

            $lastReadMap = [];
            foreach ($conversations as $c) {
                $mPivot = $c->members->firstWhere('user_id', $user->id);
                $lastReadMap[$c->id] = $mPivot?->last_read_message_id ?? 0;
            }

            $rawUnreads = ChatMessage::whereIn('conversation_id', $convIds)
                ->where('user_id', '!=', $user->id)
                ->whereNull('deleted_at')
                ->where(function ($q) {
                    $q->where('is_unsent', false)->orWhereNull('is_unsent');
                })
                ->select(['id', 'conversation_id'])
                ->get();

            foreach ($rawUnreads as $row) {
                $lastRead = $lastReadMap[$row->conversation_id] ?? 0;
                if ($row->id > $lastRead) {
                    $unreadCounts[$row->conversation_id] = ($unreadCounts[$row->conversation_id] ?? 0) + 1;
                }
            }
        }

        return $conversations->map(function ($conv) use ($user, $tab, $unreadCounts) {
            $memberPivot = $conv->members->firstWhere('user_id', $user->id);
            $customNickname = $memberPivot?->custom_nickname ?: null;
            $realTitle = $conv->getRealTitle($user);
            $displayTitle = $customNickname ?: $realTitle;

            // Retrieve batch-computed unread count
            $unreadCount = $unreadCounts[$conv->id] ?? 0;

            if ($tab === 'unread' && $unreadCount === 0) {
                return null;
            }

            $isDirect = ($conv->type === 'private' || $conv->type === 'direct');
            $otherMember = $conv->members->firstWhere('user_id', '!=', $user->id);
            $otherUser = $isDirect ? ($conv->users->firstWhere('id', '!=', $user->id) ?? ($otherMember ? User::find($otherMember->user_id) : null)) : null;
            $otherLastReadId = $otherMember?->last_read_message_id ?? 0;
            $lastMsgIsMe = $conv->lastMessage && $conv->lastMessage->user_id === $user->id;
            $lastMsgSeen = $lastMsgIsMe && ($otherLastReadId >= ($conv->last_message_id ?? 0));

            return [
                'id'              => $conv->id,
                'type'            => $isDirect ? 'direct' : $conv->type,
                'title'           => $displayTitle,
                'display_name'    => $displayTitle,
                'real_name'       => $realTitle,
                'nickname'        => $customNickname,
                'team_role'       => $otherUser?->team_role ?? ($isDirect ? 'Team Member' : 'Group'),
                'avatar'          => $conv->getDisplayAvatar($user),
                'avatar_url'      => $conv->getDisplayAvatar($user),
                'description'     => $conv->description,
                'other_user_id'   => $otherUser?->id,
                'is_online'       => $otherUser ? $otherUser->isOnline() : false,
                'last_seen'       => $otherUser ? $otherUser->getLastSeenFormatted() : 'Offline',
                'is_pinned'       => (bool) ($memberPivot?->is_pinned ?? false),
                'is_archived'     => (bool) ($memberPivot?->is_archived ?? false),
                'is_muted'        => (bool) ($memberPivot?->is_muted ?? false),
                'unread_count'    => $unreadCount,
                'last_message'    => $conv->lastMessage ? [
                    'id'             => $conv->lastMessage->id,
                    'user_id'        => $conv->lastMessage->user_id,
                    'body'           => Str::limit($conv->lastMessage->content ?: ($conv->lastMessage->type . ' message'), 45),
                    'content'        => Str::limit($conv->lastMessage->content ?: ($conv->lastMessage->type . ' message'), 45),
                    'sender_name'    => $lastMsgIsMe ? 'You' : ($conv->lastMessage->user->name ?? 'User'),
                    'time'           => $conv->lastMessage->created_at->format('H:i'),
                    'formatted_date' => $conv->lastMessage->created_at->isToday() ? $conv->lastMessage->created_at->format('H:i') : $conv->lastMessage->created_at->format('M d'),
                    'is_me'          => $lastMsgIsMe,
                    'is_seen'        => $lastMsgSeen,
                    'status'         => $lastMsgIsMe ? ($lastMsgSeen ? 'read' : 'sent') : 'delivered',
                ] : null,
                'last_message_at' => $conv->last_message_at ? $conv->last_message_at->toIso8601String() : null,
            ];
        })->filter()->sort(function ($a, $b) {
            if ($a['is_pinned'] !== $b['is_pinned']) {
                return $a['is_pinned'] ? -1 : 1;
            }
            $hasMsgA = !empty($a['last_message']);
            $hasMsgB = !empty($b['last_message']);
            if ($hasMsgA !== $hasMsgB) {
                return $hasMsgA ? -1 : 1;
            }
            if ($hasMsgA && $hasMsgB) {
                $timeA = !empty($a['last_message_at']) ? strtotime($a['last_message_at']) : 0;
                $timeB = !empty($b['last_message_at']) ? strtotime($b['last_message_at']) : 0;
                if ($timeB !== $timeA) {
                    return $timeB <=> $timeA;
                }
                return strcasecmp($a['title'] ?? '', $b['title'] ?? '');
            }
            // For contacts without messages: stable sort by name (avoids jumpy reordering when online toggles)
            return strcasecmp($a['title'] ?? '', $b['title'] ?? '');
        })->unique(function ($item) {
            if (($item['type'] ?? '') === 'direct' && !empty($item['other_user_id'])) {
                return 'direct_' . (int) $item['other_user_id'];
            }
            return 'conv_' . $item['id'];
        })->values();
    }

    /**
     * Preload the latest messages for the user's top active conversations so that
     * opening the chat workspace or clicking recent conversations renders in 0ms without a network roundtrip.
     */
    public function getInitialMessagesMap(User $user, Collection $conversations, ?int $extraConvId = null, int $limitConvs = 8): array
    {
        $convIds = $conversations
            ->filter(fn($c) => !empty($c['id']) && !empty($c['last_message']))
            ->take($limitConvs)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();

        if ($extraConvId && !in_array($extraConvId, $convIds, true)) {
            if ($conversations->contains(fn($c) => (int) ($c['id'] ?? 0) === $extraConvId)) {
                $convIds[] = $extraConvId;
            }
        }

        if (empty($convIds)) {
            return [];
        }

        // Batch-fetch other members' last_read_message_id per conversation
        $otherReadMap = ChatConversationMember::whereIn('conversation_id', $convIds)
            ->where('user_id', '!=', $user->id)
            ->selectRaw('conversation_id, MAX(COALESCE(last_read_message_id, 0)) as max_read_id')
            ->groupBy('conversation_id')
            ->pluck('max_read_id', 'conversation_id')
            ->all();

        $result = [];
        foreach ($convIds as $cid) {
            $otherLastReadId = (int) ($otherReadMap[$cid] ?? 0);
            $messages = ChatMessage::where('conversation_id', $cid)
                ->with(['user:id,name,avatar', 'attachments', 'replyTo.user:id,name', 'reactions.user:id,name', 'call.caller', 'call.receiver'])
                ->orderBy('id', 'desc')
                ->take(40)
                ->get()
                ->reverse()
                ->values();

            $result[$cid] = $messages->map(fn($m) => $this->formatMessage($m, $user, $otherLastReadId))->all();
        }

        return $result;
    }

    /**
     * Ensure 1-on-1 private conversations exist for all active colleagues with chat enabled.
     */
    public function ensurePrivateConversationsExist(User $user): void
    {
        $colleagues = User::where('is_active', true)
            ->where('id', '!=', $user->id)
            ->get()
            ->filter(fn($u) => $u->isChatEnabled());

        if ($colleagues->isEmpty()) {
            return;
        }

        // Get list of other user IDs that already have a private conversation with $user
        $existingOtherUserIds = ChatConversationMember::where('user_id', '!=', $user->id)
            ->whereIn('conversation_id', function ($q) use ($user) {
                $q->select('conversation_id')
                    ->from('chat_conversation_members')
                    ->where('user_id', $user->id)
                    ->whereIn('conversation_id', function ($sq) {
                        $sq->select('id')
                            ->from('chat_conversations')
                            ->where('type', 'private');
                    });
            })
            ->pluck('user_id')
            ->flip()
            ->toArray();

        foreach ($colleagues as $colleague) {
            if (!isset($existingOtherUserIds[$colleague->id])) {
                $this->getOrCreatePrivateChat($user, $colleague->id);
            }
        }
    }

    /**
     * Get or create a 1-on-1 private conversation between two users.
     */
    public function getOrCreatePrivateChat(User $userA, int $userBId): ?ChatConversation
    {
        $userB = User::find($userBId);
        if (!$userB || !$userB->isChatEnabled()) {
            return null;
        }

        // Find existing private conversation between A and B
        $existing = ChatConversation::where('type', 'private')
            ->whereHas('members', fn($q) => $q->where('user_id', $userA->id))
            ->whereHas('members', fn($q) => $q->where('user_id', $userB->id))
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($userA, $userB) {
            $existing = ChatConversation::where('type', 'private')
                ->whereHas('members', fn($q) => $q->where('user_id', $userA->id))
                ->whereHas('members', fn($q) => $q->where('user_id', $userB->id))
                ->first();

            if ($existing) {
                return $existing;
            }

            $conv = ChatConversation::create([
                'type'            => 'private',
                'created_by'      => $userA->id,
                'last_message_at' => null,
            ]);

            ChatConversationMember::create([
                'conversation_id' => $conv->id,
                'user_id'         => $userA->id,
                'role'            => 'member',
            ]);

            ChatConversationMember::create([
                'conversation_id' => $conv->id,
                'user_id'         => $userB->id,
                'role'            => 'member',
            ]);

            return $conv;
        });
    }

    /**
     * Get or create user's personal Saved Messages cloud chat.
     */
    public function getOrCreateSavedMessagesChat(User $user): ChatConversation
    {
        $existing = ChatConversation::where('type', 'private')
            ->where('title', 'Saved Messages')
            ->where('created_by', $user->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($user) {
            $conv = ChatConversation::create([
                'type'            => 'private',
                'title'           => 'Saved Messages',
                'description'     => 'Your personal cloud storage, notes & bookmarks',
                'created_by'      => $user->id,
                'last_message_at' => now(),
            ]);

            ChatConversationMember::create([
                'conversation_id' => $conv->id,
                'user_id'         => $user->id,
                'role'            => 'admin',
                'is_pinned'       => true,
            ]);

            return $conv;
        });
    }

    /**
     * Create a new group chat.
     */
    public function createGroup(User $creator, string $title, ?string $description = null, array $memberIds = [], $avatarFile = null): ChatConversation
    {
        return DB::transaction(function () use ($creator, $title, $description, $memberIds, $avatarFile) {
            $avatarPath = null;
            if ($avatarFile instanceof UploadedFile) {
                $avatarPath = $avatarFile->store('chat_avatars', 'public');
            }

            $conv = ChatConversation::create([
                'type'            => 'group',
                'title'           => trim($title),
                'description'     => $description,
                'avatar'          => $avatarPath,
                'created_by'      => $creator->id,
                'last_message_at' => now(),
            ]);

            // Add creator as admin
            ChatConversationMember::create([
                'conversation_id' => $conv->id,
                'user_id'         => $creator->id,
                'role'            => 'admin',
            ]);

            // Add invited members
            $validMembers = array_unique(array_filter($memberIds, fn($id) => (int)$id !== $creator->id));
            foreach ($validMembers as $mid) {
                if (User::where('id', $mid)->exists()) {
                    ChatConversationMember::create([
                        'conversation_id' => $conv->id,
                        'user_id'         => (int)$mid,
                        'role'            => 'member',
                    ]);
                }
            }

            // Create initial system message
            $sysMsg = ChatMessage::create([
                'conversation_id' => $conv->id,
                'user_id'         => $creator->id,
                'type'            => 'system',
                'content'         => "{$creator->name} created the group \"{$title}\"",
            ]);

            $conv->update(['last_message_id' => $sysMsg->id]);

            return $conv;
        });
    }

    /**
     * Send a message in a conversation.
     */
    public function sendMessage(User $user, int $conversationId, array $data, array $uploadedFiles = []): ChatMessage
    {
        $conv = ChatConversation::findOrFail($conversationId);

        // Security: verify user is a member of this conversation
        if (!$conv->hasMember($user->id)) {
            throw new Exception("You are not a member of this conversation.");
        }

        $content   = trim($data['content'] ?? '');
        $replyToId = !empty($data['reply_to_id']) ? (int)$data['reply_to_id'] : null;
        $type      = !empty($data['type']) ? $data['type'] : 'text';

        // Detect (but do NOT fetch) a link: the preview is fetched after the response is sent
        // so the sender never waits on a third-party website.
        $linkPreview = null;
        $hasUrl = !empty($content) && empty($uploadedFiles) && !in_array($type, ['sticker', 'gif']) && preg_match('/(https?:\/\/[^\s<>"]+|www\.[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)+(?:\/[^\s<>"]*)?)/i', $content, $matches);
        $rawUrl = $hasUrl ? rtrim($matches[0], '.,!?;:)]}') : null;
        $previewUrl = $rawUrl ? (preg_match('/^https?:\/\//i', $rawUrl) ? $rawUrl : 'https://' . $rawUrl) : null;

        // Determine message type based on attachments if not explicitly sticker, gif, or voice
        if (!empty($uploadedFiles) && !in_array($type, ['sticker', 'gif', 'voice'])) {
            $firstMime = $uploadedFiles[0]->getMimeType() ?: '';
            $firstExt  = strtolower($uploadedFiles[0]->getClientOriginalExtension() ?: '');
            $imageExts = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'bmp', 'ico', 'avif', 'heic', 'heif', 'tif', 'tiff'];
            $videoExts = ['mp4', 'webm', 'mov', 'mkv', 'avi', 'm4v'];
            $audioExts = ['mp3', 'wav', 'ogg', 'opus', 'm4a', 'aac', 'flac'];

            if (str_starts_with($firstMime, 'image/') || in_array($firstExt, $imageExts)) {
                $type = 'image';
            } elseif (str_starts_with($firstMime, 'video/') || in_array($firstExt, $videoExts)) {
                $type = 'video';
            } elseif (str_starts_with($firstMime, 'audio/') || in_array($firstExt, $audioExts)) {
                $type = 'voice';
            } else {
                $type = 'document';
            }
        }

        $clientTempId = !empty($data['client_temp_id']) ? (string)$data['client_temp_id'] : null;

        $message = DB::transaction(function () use ($conv, $user, $content, $replyToId, $type, $linkPreview, $uploadedFiles, $data, $clientTempId) {
            $message = ChatMessage::create([
                'conversation_id' => $conv->id,
                'user_id'         => $user->id,
                'reply_to_id'     => $replyToId,
                'type'            => $type,
                'content'         => $content ?: null,
                'link_preview'    => $linkPreview,
            ]);
            $message->client_temp_id = $clientTempId;

            // Save attachments
            foreach ($uploadedFiles as $file) {
                if ($file instanceof UploadedFile) {
                    $originalName = $file->getClientOriginalName();
                    $mimeType     = $file->getMimeType() ?: 'application/octet-stream';
                    $fileExt      = strtolower($file->getClientOriginalExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION));
                    $size         = $file->getSize();
                    $storedPath   = $file->store('chat_attachments', 'public');

                    $waveform = $data['waveform_data'] ?? null;
                    if (is_string($waveform)) {
                        $waveform = json_decode($waveform, true);
                    }

                    // Voice recordings must always be audio MIME type, never video/webm
                    if ($type === 'voice' || str_starts_with($originalName, 'voice_') || !empty($data['waveform_data']) || !empty($data['duration_seconds'])) {
                        $mimeType = 'audio/webm';
                    } else {
                        // Normalize all image formats (PNG, JPG, WEBP, GIF, SVG, BMP, ICO, AVIF, HEIC, TIFF)
                        $imageMimeMap = [
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
                        ];
                        if (isset($imageMimeMap[$fileExt]) && (!str_starts_with($mimeType, 'image/') || $mimeType === 'application/octet-stream')) {
                            $mimeType = $imageMimeMap[$fileExt];
                        }
                    }

                    $attachment = ChatMessageAttachment::create([
                        'message_id'       => $message->id,
                        'file_path'        => $storedPath,
                        'file_name'        => $originalName,
                        'file_type'        => $mimeType,
                        'file_size'        => $size,
                        'duration_seconds' => !empty($data['duration_seconds']) ? (int)$data['duration_seconds'] : null,
                        'waveform_data'    => $waveform,
                    ]);

                    // Only convert raster images (excluding SVGs and animated GIFs) to WebP
                    if (str_starts_with($mimeType, 'image/') && !in_array($mimeType, ['image/webp', 'image/svg+xml', 'image/gif'])) {
                        try {
                            \App\Jobs\Chat\ConvertChatImageToWebp::dispatch($attachment->id)->delay(now()->addMinutes(5));
                        } catch (\Throwable $e) {}
                    }
                }
            }

            // Update conversation last message
            $conv->update([
                'last_message_id' => $message->id,
                'last_message_at' => now(),
            ]);

            // Mark message as read for the sender
            ChatConversationMember::where('conversation_id', $conv->id)
                ->where('user_id', $user->id)
                ->update([
                    'last_read_message_id' => $message->id,
                    'last_read_at'         => now(),
                ]);

            return $message;
        });

        // Invalidate cached unread counts for all conversation members
        try {
            $memberUserIds = $conv->members ? $conv->members->pluck('user_id')->all() : ChatConversationMember::where('conversation_id', $conv->id)->pluck('user_id')->all();
            foreach ($memberUserIds as $memberUid) {
                Cache::forget("chat:unread_count:{$memberUid}");
            }
        } catch (\Throwable $e) {}

        // Clear sender's typing indicator in cache immediately so the client doesn't need a separate /chat/typing POST
        $typingKey = "chat:typing:conv:{$conv->id}";
        $rawTyping = Cache::get($typingKey);
        if (is_array($rawTyping) && isset($rawTyping[$user->id])) {
            unset($rawTyping[$user->id]);
            Cache::put($typingKey, $rawTyping, 10);
        }

        // Real-time delivery to other members via Pusher WebSocket (dispatched immediately for instant 0ms Messenger feel)
        try {
            broadcast(new ChatMessageSent($message, $clientTempId))->toOthers();
        } catch (\Throwable $e) {
            Log::debug("Broadcast skipped or failed: " . $e->getMessage());
        }

        // Web push notifications (Pusher Beams) & link previews run in background/after-response
        $this->afterResponse(function () use ($message, $clientTempId, $previewUrl) {
            // 1) Web push notification (Pusher Beams)
            try {
                app(\App\Services\PusherBeamsService::class)->notifyChatMessage($message);
            } catch (\Throwable $e) {
                Log::debug("Pusher Beams chat notify skipped: " . $e->getMessage());
            }

            // 2) Link preview: fetch, save, then push an in-place update to everyone (incl. sender)
            if ($previewUrl) {
                try {
                    $preview = $this->extractLinkPreview($previewUrl);
                    if ($preview) {
                        // Reload a clean model (the original carries a non-column client_temp_id attribute)
                        $fresh = ChatMessage::find($message->id);
                        if ($fresh) {
                            $fresh->link_preview = $preview;
                            $fresh->save();
                            broadcast(new ChatMessageSent($fresh, $clientTempId, true));
                        }
                    }
                } catch (\Throwable $e) {
                    Log::debug("Link preview update skipped: " . $e->getMessage());
                }
            }
        });

        return $message;
    }

    /**
     * Run a callback after the HTTP response has been sent to the client.
     */
    protected function afterResponse(callable $callback): void
    {
        app()->terminating(function () use ($callback) {
            try {
                $callback();
            } catch (\Throwable $e) {
                Log::debug('Deferred chat task failed: ' . $e->getMessage());
            }
        });
    }

    /**
     * Mark a conversation as read up to a given message ID.
     */
    public function markAsRead(User $user, int $conversationId, ?int $upToMessageId = null): void
    {
        $conv = ChatConversation::with('members')->findOrFail($conversationId);
        $member = $conv->members->firstWhere('user_id', $user->id);
        if (!$member) return;

        $targetMessageId = (int) ($upToMessageId ?: ($conv->last_message_id ?? 0));

        // If already marked read up to (or past) this message, skip redundant DB write and Pusher broadcast
        if ($member->last_read_message_id !== null && (int) $member->last_read_message_id >= $targetMessageId) {
            return;
        }

        ChatConversationMember::where('conversation_id', $conv->id)
            ->where('user_id', $user->id)
            ->update([
                'last_read_message_id' => $targetMessageId,
                'last_read_at'         => now(),
            ]);

        Cache::forget("chat:unread_count:{$user->id}");

        if ($targetMessageId > 0) {
            // Read receipts are pushed immediately so the other user sees "seen" (2 blue ticks) in real time
            $convId = $conv->id;
            $readerId = $user->id;
            try {
                broadcast(new \App\Events\Chat\MessageReadEvent($convId, $readerId, (int)$targetMessageId))->toOthers();
            } catch (\Throwable $e) {
                Log::debug('MessageReadEvent broadcast failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Sync delta messages since a given message ID (for real-time polling fallback).
     */
    public function syncMessages(User $user, int $conversationId, int $afterId = 0): array
    {
        $conv = ChatConversation::with('members')->findOrFail($conversationId);
        if (!$conv->hasMember($user->id)) {
            throw new Exception("Unauthorized.");
        }

        $otherLastReadId = $conv->members->where('user_id', '!=', $user->id)->max('last_read_message_id') ?? 0;

        $messages = ChatMessage::where('conversation_id', $conv->id)
            ->where('id', '>', $afterId)
            ->with(['user:id,name,avatar', 'attachments', 'replyTo.user:id,name', 'reactions.user:id,name', 'call.caller', 'call.receiver'])
            ->orderBy('id', 'asc')
            ->take(100)
            ->get();

        return $messages->map(fn($m) => $this->formatMessage($m, $user, $otherLastReadId))->all();
    }

    /**
     * Format a message for JSON response.
     */
    public function formatMessage(ChatMessage $m, User $viewer, ?int $otherMemberLastReadId = null): array
    {
        $isMe = ($m->user_id === $viewer->id);
        $isSeen = false;
        if ($isMe) {
            if ($otherMemberLastReadId !== null) {
                $isSeen = ($otherMemberLastReadId >= $m->id);
            } elseif ($m->relationLoaded('conversation') && $m->conversation && $m->conversation->relationLoaded('members')) {
                $maxOtherRead = $m->conversation->members->where('user_id', '!=', $viewer->id)->max('last_read_message_id') ?? 0;
                $isSeen = ($maxOtherRead >= $m->id);
            } else {
                $maxOtherRead = ChatConversationMember::where('conversation_id', $m->conversation_id)
                    ->where('user_id', '!=', $viewer->id)
                    ->max('last_read_message_id') ?? 0;
                $isSeen = ($maxOtherRead >= $m->id);
            }
            $status = $isSeen ? 'read' : 'sent';
        } else {
            $status = 'delivered';
            $isSeen = true;
        }

        return [
            'id'              => (int) $m->id,
            'client_temp_id'  => $m->client_temp_id ?? null,
            'conversation_id' => (int) $m->conversation_id,
            'user_id'         => (int) $m->user_id,
            'is_me'           => $isMe,
            'status'          => $status,
            'is_seen'         => $isSeen,
            'sender_name'     => $m->user->name ?? 'User',
            'sender_avatar'   => $m->user ? $m->user->avatar_url : null,
            'user'            => [
                'id'         => (int) $m->user_id,
                'name'       => $m->user->name ?? 'User',
                'avatar_url' => $m->user ? $m->user->avatar_url : null,
            ],
            'type'            => $m->type,
            'content'         => $m->content ?? '',
            'body'            => $m->content ?? '',
            'call_id'         => $m->call_id,
            'call'            => $m->call ? [
                'id'                 => $m->call->id,
                'call_type'          => $m->call->isVideo() ? 'video' : 'voice',
                'status'             => $m->call->status,
                'end_reason'         => $m->call->end_reason,
                'duration_seconds'   => $m->call->duration_seconds,
                'formatted_duration' => $m->call->formatted_duration,
                'is_caller'          => $m->call->caller_id === $viewer->id,
                'caller_id'          => $m->call->caller_id,
                'receiver_id'        => $m->call->receiver_id,
            ] : null,
            'link_preview'    => $m->link_preview,
            'poll_data'       => $m->poll_data ? $this->formatPollData($m->poll_data, $viewer) : null,
            'poll'            => $m->poll_data ? $this->formatPollData($m->poll_data, $viewer) : null,
            'forward_from'    => $m->forward_from,
            'is_edited'       => (bool) $m->is_edited,
            'is_unsent'       => (bool) $m->is_unsent,
            'is_pinned'       => (bool) $m->is_pinned,
            'reply_to'        => $m->replyTo ? [
                'id'      => $m->replyTo->id,
                'name'    => $m->replyTo->user->name ?? 'User',
                'content' => Str::limit($m->replyTo->content ?? 'Media', 45),
            ] : null,
            'attachments'     => $m->attachments->map(function ($a) use ($m) {
                $fileType = $a->file_type;
                if ($m->type === 'voice' || !empty($a->waveform_data) || str_starts_with($a->file_name, 'voice_')) {
                    if (str_starts_with($fileType, 'video/')) {
                        $fileType = 'audio/webm';
                    }
                }
                return [
                    'id'               => $a->id,
                    'file_name'        => $a->file_name,
                    'file_type'        => $fileType,
                    'file_size'        => $a->formatted_size,
                    'duration_seconds' => $a->duration_seconds,
                    'waveform_data'    => $a->waveform_data,
                    'file_url'         => asset('storage/' . $a->file_path),
                    'file_path'        => asset('storage/' . $a->file_path),
                    'view_url'         => route('chat.attachments.view', ['attachment' => $a->id]),
                    'download_url'     => $a->download_url,
                ];
            })->all(),
            'reactions'       => $m->reactions->groupBy('reaction')->map(fn($r, $k) => [
                'emoji'    => $k,
                'count'    => count($r),
                'is_me'    => $r->contains('user_id', $viewer->id),
                'users'    => $r->pluck('user.name')->all(),
            ])->values()->all(),
            'created_at'      => $m->created_at->toIso8601String(),
            'formatted_time'  => $m->created_at->format('H:i'),
        ];
    }

    /**
     * Toggle a reaction on a message.
     */
    public function toggleReaction(User $user, int $messageId, string $reaction): array
    {
        $message = ChatMessage::with('conversation')->findOrFail($messageId);
        if (!$message->conversation->hasMember($user->id)) {
            throw new Exception("Unauthorized.");
        }

        $existing = ChatMessageReaction::where('message_id', $messageId)
            ->where('user_id', $user->id)
            ->where('reaction', $reaction)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            ChatMessageReaction::create([
                'message_id' => $messageId,
                'user_id'    => $user->id,
                'reaction'   => $reaction,
            ]);
        }

        try {
            broadcast(new MessageReactionUpdated($message))->toOthers();
        } catch (Exception $e) {}

        return ['success' => true];
    }

    /**
     * Get total unread messages count across all conversations for a user.
     */
    public function getUnreadCount(User $user): int
    {
        if (!$user->isChatEnabled()) {
            return 0;
        }

        return (int) Cache::remember("chat:unread_count:{$user->id}", 5, function () use ($user) {
            return (int) DB::table('chat_conversation_members as m')
                ->join('chat_messages as msg', 'msg.conversation_id', '=', 'm.conversation_id')
                ->where('m.user_id', $user->id)
                ->where('m.is_archived', false)
                ->where('msg.user_id', '!=', $user->id)
                ->whereRaw('msg.id > COALESCE(m.last_read_message_id, 0)')
                ->whereNull('msg.deleted_at')
                ->where(function ($q) {
                    $q->where('msg.is_unsent', false)->orWhereNull('msg.is_unsent');
                })
                ->count();
        });
    }

    /**
     * Create an interactive poll.
     */
    public function createPoll(User $user, int $conversationId, string $question, array $options, bool $multipleAnswers = false, bool $isAnonymous = false): ChatMessage
    {
        $conv = ChatConversation::findOrFail($conversationId);
        if (!$conv->hasMember($user->id)) {
            throw new Exception("Unauthorized.");
        }

        $formatted = [];
        $id = 1;
        foreach ($options as $opt) {
            $val = trim((string)$opt);
            if (!empty($val)) {
                $formatted[] = [
                    'id'        => $id++,
                    'text'      => $val,
                    'voter_ids' => [],
                ];
            }
        }

        if (count($formatted) < 2) {
            throw new Exception("A poll requires at least 2 options.");
        }

        $pollData = [
            'question'         => trim($question),
            'options'          => $formatted,
            'multiple_answers' => $multipleAnswers,
            'is_anonymous'     => $isAnonymous,
            'total_votes'      => 0,
        ];

        return DB::transaction(function () use ($conv, $user, $question, $pollData) {
            $message = ChatMessage::create([
                'conversation_id' => $conv->id,
                'user_id'         => $user->id,
                'type'            => 'text',
                'content'         => "📊 Poll: " . trim($question),
                'poll_data'       => $pollData,
            ]);

            $conv->update([
                'last_message_id' => $message->id,
                'last_message_at' => now(),
            ]);

            try {
                broadcast(new \App\Events\Chat\ChatMessageSent($message))->toOthers();
            } catch (Exception $e) {}

            return $message;
        });
    }

    /**
     * Vote or un-vote on a poll option.
     */
    public function votePoll(User $user, int $messageId, int $optionId): array
    {
        $message = ChatMessage::with('conversation')->findOrFail($messageId);
        if (!$message->conversation->hasMember($user->id)) {
            throw new Exception("Unauthorized.");
        }

        $pollData = $message->poll_data;
        if (!$pollData || empty($pollData['options'])) {
            throw new Exception("This message is not a poll.");
        }

        $multiple = !empty($pollData['multiple_answers']);
        $allVoters = [];

        foreach ($pollData['options'] as &$opt) {
            $voterIds = $opt['voter_ids'] ?? [];
            if ((int)$opt['id'] === $optionId) {
                if (in_array($user->id, $voterIds)) {
                    $voterIds = array_values(array_diff($voterIds, [$user->id]));
                } else {
                    $voterIds[] = $user->id;
                }
            } elseif (!$multiple) {
                $voterIds = array_values(array_diff($voterIds, [$user->id]));
            }
            $opt['voter_ids'] = $voterIds;
            foreach ($voterIds as $vid) {
                $allVoters[$vid] = true;
            }
        }
        unset($opt);

        $pollData['total_votes'] = count($allVoters);
        $message->update(['poll_data' => $pollData]);

        try {
            broadcast(new \App\Events\Chat\ChatMessageSent($message))->toOthers();
        } catch (Exception $e) {}

        return $this->formatMessage($message, $user);
    }

    /**
     * Unsend message for all participants (like Telegram).
     */
    public function unsendMessage(User $user, int $messageId): array
    {
        $message = ChatMessage::with(['conversation', 'attachments'])->findOrFail($messageId);
        $canUnsend = ($message->user_id === $user->id) 
            || ($message->conversation && $message->conversation->isAdmin($user->id))
            || $user->canManageChatPermissions();
        if (!$canUnsend) {
            throw new Exception("Unauthorized to unsend this message.");
        }

        $message->update([
            'content'   => '🚫 This message was unsent',
            'is_unsent' => true,
            'poll_data' => null,
        ]);

        foreach ($message->attachments as $att) {
            try {
                if ($att->file_path) {
                    Storage::disk('public')->delete($att->file_path);
                }
            } catch (Exception $e) {}
            try {
                $att->delete();
            } catch (Exception $e) {}
        }

        $formatted = $this->formatMessage($message, $user);

        try {
            broadcast(new \App\Events\Chat\ChatMessageSent($message, null, true))->toOthers();
        } catch (\Throwable $e) {}

        return $formatted;
    }

    /**
     * Forward message to another conversation.
     */
    public function forwardMessage(User $user, int $messageId, int $targetConversationId): ChatMessage
    {
        $original = ChatMessage::with(['user', 'attachments', 'conversation'])->findOrFail($messageId);
        if (!$original->conversation->hasMember($user->id)) {
            throw new Exception("Unauthorized.");
        }

        $target = ChatConversation::findOrFail($targetConversationId);
        if (!$target->hasMember($user->id)) {
            throw new Exception("You are not a member of the target conversation.");
        }

        return DB::transaction(function () use ($user, $original, $target) {
            $forwardMeta = [
                'original_id'   => $original->id,
                'author_id'     => $original->user_id,
                'author_name'   => $original->user->name ?? 'User',
                'author_avatar' => $original->user && $original->user->avatar ? asset('storage/' . $original->user->avatar) : null,
            ];

            $newMsg = ChatMessage::create([
                'conversation_id' => $target->id,
                'user_id'         => $user->id,
                'type'            => $original->type,
                'content'         => $original->content,
                'poll_data'       => $original->poll_data,
                'forward_from'    => $forwardMeta,
                'link_preview'    => $original->link_preview,
            ]);

            foreach ($original->attachments as $att) {
                ChatMessageAttachment::create([
                    'message_id'       => $newMsg->id,
                    'file_path'        => $att->file_path,
                    'file_name'        => $att->file_name,
                    'file_type'        => $att->file_type,
                    'file_size'        => $att->file_size,
                    'width'            => $att->width,
                    'height'           => $att->height,
                    'duration_seconds' => $att->duration_seconds,
                ]);
            }

            $target->update([
                'last_message_id' => $newMsg->id,
                'last_message_at' => now(),
            ]);

            return $newMsg;
        });
    }

    /**
     * Format poll data with voter percentages and states.
     */
    public function formatPollData(array $poll, User $viewer): array
    {
        $total = $poll['total_votes'] ?? 0;
        $userVotedAny = false;

        $options = array_map(function ($opt) use ($viewer, $total, &$userVotedAny) {
            $voterIds = $opt['voter_ids'] ?? [];
            $voted = in_array($viewer->id, $voterIds);
            if ($voted) $userVotedAny = true;
            $count = count($voterIds);
            $pct = $total > 0 ? (int)round(($count / $total) * 100) : 0;

            return [
                'id'         => $opt['id'],
                'text'       => $opt['text'],
                'votes'      => $count,
                'percentage' => $pct,
                'percent'    => $pct,
                'voted'      => $voted,
                'user_voted' => $voted,
            ];
        }, $poll['options'] ?? []);

        return [
            'question'         => $poll['question'] ?? 'Poll',
            'options'          => $options,
            'multiple_answers' => (bool)($poll['multiple_answers'] ?? false),
            'is_anonymous'     => (bool)($poll['is_anonymous'] ?? false),
            'total_votes'      => $total,
            'user_has_voted'   => $userVotedAny,
            'has_voted'        => $userVotedAny,
        ];
    }

    /**
     * Safely extract title, description, and image preview from a URL (cached and fast non-blocking).
     */
    public function extractLinkPreview(string $url): ?array
    {
        $cacheKey = 'link_preview:' . md5($url);
        return Cache::remember($cacheKey, 86400 * 7, function () use ($url) {
            try {
                $response = Http::timeout(0.8)->connectTimeout(0.5)->get($url);
                if (!$response->successful()) return null;

                $html = $response->body();
                $title = null;
                $description = null;
                $image = null;

                if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $matches)) {
                    $title = trim(strip_tags($matches[1]));
                }
                if (preg_match('/<meta\s+name=[\'"]description[\'"]\s+content=[\'"](.*?)[\'"]/si', $html, $matches)) {
                    $description = trim($matches[1]);
                } elseif (preg_match('/<meta\s+property=[\'"]og:description[\'"]\s+content=[\'"](.*?)[\'"]/si', $html, $matches)) {
                    $description = trim($matches[1]);
                }
                if (preg_match('/<meta\s+property=[\'"]og:image[\'"]\s+content=[\'"](.*?)[\'"]/si', $html, $matches)) {
                    $image = trim($matches[1]);
                }

                return [
                    'url'         => $url,
                    'title'       => Str::limit($title ?: parse_url($url, PHP_URL_HOST), 80),
                    'description' => Str::limit($description, 150),
                    'image'       => $image,
                ];
            } catch (\Throwable $e) {
                return null;
            }
        });
    }
}
