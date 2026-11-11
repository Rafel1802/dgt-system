<?php

namespace App\Models\Ai;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiVoiceOver extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ai_voice_overs';

    protected $fillable = [
        'user_id',
        'title',
        'script',
        'voice_name',
        'voice_gender',
        'style',
        'speed',
        'audio_path',
        'duration_seconds',
    ];

    protected $casts = [
        'speed' => 'float',
        'duration_seconds' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
