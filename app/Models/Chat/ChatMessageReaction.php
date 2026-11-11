<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessageReaction extends Model
{
    use HasFactory;

    protected $table = 'chat_message_reactions';

    protected $fillable = [
        'message_id',
        'user_id',
        'reaction',
        'emoji',
    ];

    public function getEmojiAttribute(): ?string
    {
        return $this->attributes['reaction'] ?? null;
    }

    public function setEmojiAttribute(?string $value): void
    {
        $this->attributes['reaction'] = $value;
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
