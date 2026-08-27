<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PopupAd extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'image_path',
        'body_text',
        'button_text',
        'button_link',
        'notification_text',
        'notification_icon',
        'start_time',
        'end_time',
        'interval_minutes',
        'is_active',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class)
                    ->withPivot(['last_shown_at', 'is_clicked'])
                    ->withTimestamps();
    }
}
