<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingAlarm extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'meeting_time',
        'meeting_link',
        'sound',
        'created_by',
        'is_active',
        'ring_duration',
    ];

    protected function casts(): array
    {
        return [
            'meeting_time' => 'datetime',
            'is_active' => 'boolean',
            'ring_duration' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getSoundAttribute($value): string
    {
        return $value ?: 'funny.wav';
    }

    public function getSoundUrlAttribute(): string
    {
        $sound = $this->sound ?: 'funny.wav';
        $path = 'clocksound/' . $sound;
        if (file_exists(public_path($path))) {
            return asset($path);
        }
        return asset('clocksound/funny.wav');
    }
}
