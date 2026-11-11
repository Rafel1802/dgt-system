<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'chat_messages';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'reply_to_id',
        'type', // 'text', 'image', 'video', 'document', 'voice', 'system'
        'content',
        'body',
        'poll_data',
        'forward_from',
        'is_edited',
        'edited_at',
        'is_pinned',
        'pinned_at',
        'pinned_by',
        'is_unsent',
        'call_id',
    ];

    public function getBodyAttribute(): ?string
    {
        return $this->attributes['content'] ?? null;
    }

    public function setBodyAttribute(?string $value): void
    {
        $this->attributes['content'] = $value;
    }

    protected $casts = [
        'id'              => 'integer',
        'conversation_id' => 'integer',
        'user_id'         => 'integer',
        'reply_to_id'     => 'integer',
        'link_preview'    => 'array',
        'poll_data'       => 'array',
        'forward_from'    => 'array',
        'is_edited'       => 'boolean',
        'is_unsent'       => 'boolean',
        'edited_at'       => 'datetime',
        'is_pinned'       => 'boolean',
        'pinned_at'       => 'datetime',
        'call_id'         => 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'reply_to_id');
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(ChatCall::class, 'call_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ChatMessageAttachment::class, 'message_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(ChatMessageReaction::class, 'message_id');
    }

    public function pinnedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pinned_by');
    }
}
