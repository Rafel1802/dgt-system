<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatUserSetting extends Model
{
    use HasFactory;

    protected $table = 'chat_user_settings';

    protected $fillable = [
        'user_id',
        'theme',
        'wallpaper',
        'wallpaper_image',
        'bubble_color',
        'font_size',
        'notification_sound',
        'sound_choice',
        'enter_is_send',
        'show_online_status',
        'show_read_receipts',
        'status_message',
    ];

    protected $casts = [
        'notification_sound'  => 'boolean',
        'enter_is_send'       => 'boolean',
        'show_online_status'  => 'boolean',
        'show_read_receipts'  => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
