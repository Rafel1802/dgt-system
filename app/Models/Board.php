<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Board extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id', 'name', 'slug', 'description',
        'background_type', 'background_value', 'visibility',
        'cover_type', 'cover_value',
        'member_permissions', 'card_covers_enabled',
        'notifications_enabled', 'browser_notifications_enabled',

        'is_starred', 'is_archived', 'is_hidden', 'is_template', 'created_by', 'position',
    ];

    protected function casts(): array
    {
        return [
            'is_starred'   => 'boolean',
            'is_archived'  => 'boolean',
            'is_hidden' => 'boolean',
            'is_template'  => 'boolean',
            'card_covers_enabled' => 'boolean',
            'notifications_enabled' => 'boolean',
            'browser_notifications_enabled' => 'boolean',

        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Board $board) {
            if (empty($board->slug)) {
                $board->slug = Str::slug($board->name) . '-' . Str::random(4);
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'board_members')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    public function lists(): HasMany
    {
        return $this->hasMany(BoardList::class)->orderBy('position');
    }

    public function activeLists(): HasMany
    {
        return $this->hasMany(BoardList::class)
                    ->where('is_archived', false)
                    ->orderBy('position');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    public function labels(): HasMany
    {
        return $this->hasMany(Label::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Background CSS class or inline style value for the board header.
     */
    public function backgroundStyle(): string
    {
        if ($this->background_type === 'image' && $this->background_value) {
            return "background-image: url('{$this->background_value}'); background-size: cover; background-position: center;";
        }
        if ($this->background_type === 'color' && $this->background_value) {
            return "background-color: {$this->background_value};";
        }
        return 'background-color: #6366f1;'; // Default indigo
    }

    public function coverStyle(): string
    {
        $type = $this->cover_type ?? $this->background_type;
        $val = $this->cover_value ?? $this->background_value;

        if ($type === 'image' && $val) {
            return "background-image: url('{$val}'); background-size: cover; background-position: center;";
        }
        if ($type === 'color' && $val) {
            return "background-color: {$val};";
        }
        return 'background-color: #6366f1;'; // Default indigo
    }

    public function userRole(int $userId): ?string
    {
        if ($this->created_by === $userId) return 'admin';
        return $this->members->firstWhere('id', $userId)?->pivot->role;
    }

    public function getNameAttribute($value)
    {
        // If the stored name already has a month suffix, extract and preserve it
        $pattern = '/\s+[-–]\s+(January|February|March|April|May|June|July|August|September|October|November|December)(?:\s+(\d{4}))?$/i';
        if (preg_match($pattern, (string)$value, $matches)) {
            $month = $matches[1];
            $year = $matches[2] ?? ($this->created_at ? $this->created_at->format('Y') : date('Y'));
            $cleanName = preg_replace($pattern, '', (string)$value);
            return $cleanName . " – {$month} {$year}";
        }

        $createdAt = $this->created_at ?? now();
        $month = $createdAt->format('F');
        $year = $createdAt->format('Y');
        $suffix = " – {$month} {$year}";

        // If it already ends with this exact suffix, return as is
        if (str_ends_with((string)$value, $suffix)) {
            return $value;
        }

        // Clean up old "- June" or "– June 2026" suffixes
        $cleanName = preg_replace('/ [-–] [A-Za-z]+( \d{4})?$/u', '', (string)$value);

        return $cleanName . $suffix;
    }

    public function hasMember(int $userId): bool
    {
        $user = User::find($userId);
        if (!$user) return false;
        if ($user->hasRole('super-admin')) {
            return true;
        }
        return $this->created_by === $userId
            || $this->members()->where('users.id', $userId)->exists();
    }
}
