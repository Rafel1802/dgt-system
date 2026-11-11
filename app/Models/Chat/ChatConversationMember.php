<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatConversationMember extends Model
{
    use HasFactory;

    protected $table = 'chat_conversation_members';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'role', // 'member', 'admin'
        'is_pinned',
        'is_archived',
        'is_muted',
        'last_read_message_id',
        'last_read_at',
        'custom_nickname',
        'custom_wallpaper',
    ];

    protected $casts = [
        'is_pinned'    => 'boolean',
        'is_archived'  => 'boolean',
        'is_muted'     => 'boolean',
        'last_read_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
