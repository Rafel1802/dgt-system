<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoardList extends Model
{
    use HasFactory;

    protected $table = 'board_lists';

    protected $fillable = [
        'board_id', 'name', 'position', 'is_archived', 'color', 'wip_limit',
    ];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'wip_limit'   => 'integer',
            'position'    => 'integer',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class, 'board_list_id')
                    ->where('is_archived', false)
                    ->orderBy('position');
    }

    public function allCards(): HasMany
    {
        return $this->hasMany(Card::class, 'board_list_id')->orderBy('position');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isAtWipLimit(): bool
    {
        if ($this->wip_limit === 0) return false;
        return $this->cards()->count() >= $this->wip_limit;
    }

    public function cardCount(): int
    {
        return $this->cards()->count();
    }
}
