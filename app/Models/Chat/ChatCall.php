<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatCall extends Model
{
    use HasFactory;

    protected $table = 'chat_calls';

    protected $fillable = [
        'conversation_id',
        'caller_id',
        'receiver_id',
        'call_type',
        'status', // 'calling', 'ringing', 'connected', 'ended', 'declined', 'missed', 'cancelled', 'busy'
        'started_at',
        'answered_at',
        'ended_at',
        'duration_seconds',
        'end_reason',
        'metadata',
    ];

    protected $casts = [
        'started_at'       => 'datetime',
        'answered_at'      => 'datetime',
        'ended_at'         => 'datetime',
        'duration_seconds' => 'integer',
        'metadata'         => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function signals(): HasMany
    {
        return $this->hasMany(ChatCallSignal::class, 'call_id');
    }

    /**
     * Check if call is currently active or ringing.
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['calling', 'ringing', 'connected']);
    }

    /**
     * Check whether this session is a video call.
     */
    public function isVideo(): bool
    {
        return $this->call_type === 'video' || $this->end_reason === 'video';
    }

    /**
     * Get human-readable formatted call duration (e.g. "2m 14s" or "45s").
     */
    public function getFormattedDurationAttribute(): string
    {
        $sec = $this->duration_seconds;
        if ($sec <= 0) return '0s';

        $hours = floor($sec / 3600);
        $minutes = floor(($sec % 3600) / 60);
        $seconds = $sec % 60;

        if ($hours > 0) {
            return sprintf('%dh %02dm %02ds', $hours, $minutes, $seconds);
        }
        if ($minutes > 0) {
            return sprintf('%dm %02ds', $minutes, $seconds);
        }
        return sprintf('%ds', $seconds);
    }
}
