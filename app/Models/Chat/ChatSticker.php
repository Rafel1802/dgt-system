<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ChatSticker extends Model
{
    use HasFactory;

    protected $table = 'chat_stickers';

    protected $fillable = [
        'user_id',
        'name',
        'image_path',
        'category',
        'is_public',
    ];

    protected $casts = [
        'id'        => 'integer',
        'user_id'   => 'integer',
        'is_public' => 'boolean',
    ];

    protected $appends = [
        'image_url',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getImageUrlAttribute(): string
    {
        $path = $this->image_path;
        if (!$path) {
            return '';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, 'storage/')) {
            return asset($path);
        }

        if (str_starts_with($path, 'chat_stickers/')) {
            return asset('storage/' . $path);
        }

        if (str_starts_with($path, '/')) {
            return asset(ltrim($path, '/'));
        }

        return asset($path);
    }
}
