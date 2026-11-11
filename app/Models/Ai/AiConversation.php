<?php

namespace App\Models\Ai;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiConversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ai_conversations';

    protected $fillable = [
        'user_id',
        'title',
        'mode',
        'is_pinned',
        'is_archived',
        'last_message_at',
    ];

    protected $casts = [
        'is_pinned'       => 'boolean',
        'is_archived'     => 'boolean',
        'last_message_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id')->orderBy('created_at', 'asc');
    }

    public function latestMessages(int $limit = 20): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id')
            ->orderBy('created_at', 'desc')
            ->take($limit);
    }
}
