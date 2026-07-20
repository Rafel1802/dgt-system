<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Workspace extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'logo',
        'color', 'icon_text', 'visibility', 'owner_id', 'is_active', 'position',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // ── Auto-generate slug on creation ────────────────────────────────────────
    protected static function booted(): void
    {
        static::creating(function (Workspace $workspace) {
            if (empty($workspace->slug)) {
                $workspace->slug = Str::slug($workspace->name) . '-' . Str::random(4);
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
                    ->withPivot('role', 'joined_at')
                    ->withTimestamps();
    }

    public function boards(): HasMany
    {
        return $this->hasMany(Board::class)->orderBy('position');
    }

    public function activeBoards(): HasMany
    {
        return $this->hasMany(Board::class)
                    ->where('is_archived', false)
                    ->where('is_hidden', false)
                    ->orderBy('position');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function userRole(int $userId): ?string
    {
        if ($this->owner_id === $userId) return 'owner';
        return $this->members->firstWhere('id', $userId)?->pivot->role;
    }

    public function hasMember(int $userId): bool
    {
        $currentUser = auth()->id() === $userId ? auth()->user() : null;

        if ($currentUser?->hasRole('super-admin')) {
            return true;
        }

        if ($this->relationLoaded('members')) {
            return $this->owner_id === $userId || $this->members->contains('id', $userId);
        }

        $user = $currentUser ?: User::with('roles')->find($userId);
        if (! $user) return false;

        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $this->owner_id === $userId
            || $this->members()->where('users.id', $userId)->exists();
    }
}
