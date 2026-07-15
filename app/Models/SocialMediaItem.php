<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialMediaItem extends Model
{
    protected $fillable = ['social_media_class_id', 'name', 'icon', 'status', 'sort_order', 'created_by'];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function socialMediaClass(): BelongsTo
    {
        return $this->belongsTo(SocialMediaClass::class, 'social_media_class_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(SocialMediaPost::class, 'social_media_item_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Emoji icon based on common social media names */
    public function getIconAttribute(): string
    {
        return $this->attributes['icon'] ?? null ?: $this->getFallbackEmoji();
    }

    public function getIconHtmlAttribute(): string
    {
        $raw = $this->attributes['icon'] ?? null;
        if ($raw) {
            if (filter_var($raw, FILTER_VALIDATE_URL) 
                || str_starts_with($raw, '/') 
                || str_starts_with($raw, 'storage/') 
                || preg_match('/\.(jpeg|jpg|png|gif|svg|webp)/i', $raw)
            ) {
                $src = filter_var($raw, FILTER_VALIDATE_URL) ? $raw : asset(ltrim($raw, '/'));
                return '<img src="'.htmlspecialchars($src).'" class="w-5 h-5 object-contain inline-block flex-shrink-0">';
            }
            return htmlspecialchars($raw);
        }
        return $this->getFallbackEmoji();
    }

    protected function getFallbackEmoji(): string
    {
        $map = [
            'facebook'  => '📘',
            'fb'        => '📘',
            'instagram' => '📸',
            'ig'        => '📸',
            'tiktok'    => '🎵',
            'tik tok'   => '🎵',
            'youtube'   => '▶️',
            'yt'        => '▶️',
            'tumblr'    => '📝',
            'linkedin'  => '💼',
            'x'         => '𝕏',
            'twitter'   => '🐦',
            'telegram'  => '✈️',
            'pinterest' => '📌',
            'snapchat'  => '👻',
            'whatsapp'  => '💬',
            'shopee'    => '🛒',
            'lazada'    => '🛍️',
        ];

        $lower = strtolower($this->name);
        foreach ($map as $key => $emoji) {
            if (str_contains($lower, $key)) {
                return $emoji;
            }
        }
        return '📱';
    }
}
