<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatConversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'chat_conversations';

    protected $fillable = [
        'type', // 'private' or 'group'
        'title',
        'avatar',
        'description',
        'created_by',
        'last_message_id',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ChatConversationMember::class, 'conversation_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_conversation_members', 'conversation_id', 'user_id')
            ->withPivot(['role', 'is_pinned', 'is_archived', 'is_muted', 'last_read_message_id', 'last_read_at', 'custom_nickname', 'custom_wallpaper'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id')->orderBy('id', 'asc');
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'last_message_id');
    }

    public function pinnedMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id')->where('is_pinned', true)->orderByDesc('pinned_at');
    }

    /**
     * Check if a specific user is an active member of this conversation.
     */
    public function hasMember(int $userId): bool
    {
        if ($this->relationLoaded('members')) {
            return $this->members->contains('user_id', $userId);
        }
        return $this->members()->where('user_id', $userId)->exists();
    }

    /**
     * Check if a user is an admin of this group.
     */
    public function isAdmin(int $userId): bool
    {
        if ($this->relationLoaded('members')) {
            return $this->members->where('user_id', $userId)->where('role', 'admin')->isNotEmpty();
        }
        return $this->members()->where('user_id', $userId)->where('role', 'admin')->exists();
    }

    /**
     * Get the conversation display title for a given user.
     */
    public function isSavedMessages(int $userId): bool
    {
        if ($this->created_by !== $userId) {
            return false;
        }
        if ($this->title === 'Saved Messages') {
            return true;
        }
        $memberCount = $this->relationLoaded('members')
            ? $this->members->count()
            : $this->members()->count();

        return $memberCount === 1;
    }

    /**
     * Get the conversation display title for a given user (honoring custom nickname if set).
     */
    public function getDisplayTitle(User $viewer): string
    {
        if ($this->isSavedMessages($viewer->id)) {
            return 'Saved Messages';
        }

        // Check if viewer has set a custom nickname
        $viewerMember = $this->relationLoaded('members')
            ? $this->members->firstWhere('user_id', $viewer->id)
            : $this->members()->where('user_id', $viewer->id)->first();

        if (!empty($viewerMember?->custom_nickname)) {
            return $viewerMember->custom_nickname;
        }

        return $this->getRealTitle($viewer);
    }

    /**
     * Get the real/canonical title of the conversation regardless of custom nickname.
     */
    public function getRealTitle(User $viewer): string
    {
        if ($this->isSavedMessages($viewer->id)) {
            return 'Saved Messages';
        }

        if ($this->type === 'group') {
            return $this->title ?: 'Group Chat';
        }

        $otherUser = $this->relationLoaded('users')
            ? $this->users->firstWhere('id', '!=', $viewer->id)
            : $this->users()->where('users.id', '!=', $viewer->id)->first();

        return $otherUser ? $otherUser->name : 'Saved Messages';
    }

    /**
     * Get the conversation display avatar for a given user.
     */
    public function getDisplayAvatar(User $viewer): ?string
    {
        if ($this->isSavedMessages($viewer->id)) {
            return asset('favicon.svg');
        }

        if ($this->type === 'group') {
            return $this->avatar ? asset("storage/{$this->avatar}") : User::initialsAvatarDataUri($this->title ?: 'Group', '#4f46e5');
        }

        $otherUser = $this->users->firstWhere('id', '!=', $viewer->id);
        return $otherUser ? $otherUser->avatar_url : User::initialsAvatarDataUri('User', '#64748b');
    }
}
