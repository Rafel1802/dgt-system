<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Member extends Model
{
    protected $fillable = [
        'full_name',
        'job_title',
        'department',
        'email',
        'phone',
        'whatsapp',
        'bio',
        'profile_photo',
        'join_date',
        'display_order',
        'status',
    ];

    protected $casts = [
        'join_date' => 'date',
        'display_order' => 'integer',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('full_name');
    }

    public function scopeByDepartment(Builder $query, string $department): Builder
    {
        return $query->where('department', $department);
    }

    // ── Accessors ─────────────────────────────────────────────────────────

    /**
     * Returns the URL for the member's profile photo.
     * Falls back to a generated avatar if no photo is set.
     */
    public function getProfilePhotoUrlAttribute(): string
    {
        if ($this->profile_photo) {
            if (filter_var($this->profile_photo, FILTER_VALIDATE_URL)) {
                return $this->profile_photo;
            }
            return Storage::url($this->profile_photo);
        }

        $initials = urlencode(collect(explode(' ', $this->full_name))->take(2)->map(fn($w) => strtoupper($w[0] ?? ''))->implode('+'));
        $colors = [
            ['bg' => '6366f1', 'fg' => 'fff'],
            ['bg' => '0891b2', 'fg' => 'fff'],
            ['bg' => '10b981', 'fg' => 'fff'],
            ['bg' => 'f59e0b', 'fg' => 'fff'],
            ['bg' => 'ef4444', 'fg' => 'fff'],
            ['bg' => '8b5cf6', 'fg' => 'fff'],
        ];
        $colorIndex = abs(crc32($this->full_name)) % count($colors);
        $color = $colors[$colorIndex];

        return "https://ui-avatars.com/api/?name={$initials}&size=256&background={$color['bg']}&color={$color['fg']}&bold=true";
    }

    /**
     * Whatsapp contact link ready to use.
     */
    public function getWhatsappUrlAttribute(): ?string
    {
        if (! $this->whatsapp) {
            return null;
        }
        $number = preg_replace('/[^0-9]/', '', $this->whatsapp);
        return "https://wa.me/{$number}";
    }

    /**
     * Phone call link.
     */
    public function getPhoneUrlAttribute(): ?string
    {
        if (! $this->phone) {
            return null;
        }
        return "tel:{$this->phone}";
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public static function departments(): array
    {
        return [
            'Digital Team',
            'Sales Team',
            'CRM Team',
            'Support Team',
            'Management',
            'Administration',
        ];
    }
}
