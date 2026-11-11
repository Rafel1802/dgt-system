<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatCallSignal extends Model
{
    use HasFactory;

    protected $table = 'chat_call_signals';

    protected $fillable = [
        'call_id',
        'sender_id',
        'receiver_id',
        'type', // 'offer', 'answer', 'ice-candidate', 'status'
        'payload',
        'is_read',
    ];

    protected $casts = [
        'payload' => 'array',
        'is_read' => 'boolean',
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(ChatCall::class, 'call_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
