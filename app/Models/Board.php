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
        return match ($this->background_type) {
            'color'    => "background-color:{$this->background_value}",
            'gradient' => "background:{$this->background_value}",
            'image'    => "background-image:url('{$this->background_value}');background-size:cover",
            default    => "background-color:#6366f1",
        };
    }

    public function userRole(int $userId): ?string
    {
        if ($this->created_by === $userId) return 'admin';
        return $this->members->firstWhere('id', $userId)?->pivot->role;
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
