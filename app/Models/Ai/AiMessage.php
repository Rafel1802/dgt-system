<?php

namespace App\Models\Ai;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    use HasFactory;

    protected $table = 'ai_messages';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'content',
        'action_type',
        'action_data',
        'action_status',
        'attachments',
        'tokens_used',
    ];

    protected $casts = [
        'action_data' => 'array',
        'attachments' => 'array',
        'tokens_used' => 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
