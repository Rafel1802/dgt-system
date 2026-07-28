<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardChecklist extends Model
{
    protected $fillable = ['card_id', 'title', 'position', 'sync_id'];

    protected static function booted()
    {
        static::created(function ($checklist) {
            if (\App\Models\Card::$isSyncing) {
                return;
            }
            $card = $checklist->card;
            if ($card && $card->sync_group_id) {
                \App\Models\Card::$isSyncing = true;
                try {
                    if (!$checklist->sync_id) {
                        $checklist->sync_id = (string) \Illuminate\Support\Str::uuid();
                        $checklist->save();
                    }
                    $otherCards = \App\Models\Card::where('sync_group_id', $card->sync_group_id)
                        ->where('id', '!=', $card->id)
                        ->get();
                    foreach ($otherCards as $otherCard) {
                        if (!\App\Models\CardChecklist::where('card_id', $otherCard->id)->where('sync_id', $checklist->sync_id)->exists()) {
                            \App\Models\CardChecklist::create([
                                'card_id' => $otherCard->id,
                                'title' => $checklist->title ?? '',
                                'position' => $checklist->position ?? 0,
                                'sync_id' => $checklist->sync_id,
                            ]);
                        }
                    }
                } finally {
                    \App\Models\Card::$isSyncing = false;
                }
            }
        });

        static::updated(function ($checklist) {
            if (\App\Models\Card::$isSyncing) {
                return;
            }
            if ($checklist->sync_id) {
                \App\Models\Card::$isSyncing = true;
                try {
                    \App\Models\CardChecklist::where('sync_id', $checklist->sync_id)
                        ->where('id', '!=', $checklist->id)
                        ->update([
                            'title' => $checklist->title ?? '',
                            'position' => $checklist->position ?? 0,
                        ]);
                } finally {
                    \App\Models\Card::$isSyncing = false;
                }
            }
        });

        static::deleted(function ($checklist) {
            if (\App\Models\Card::$isSyncing) {
                return;
            }
            if ($checklist->sync_id) {
                \App\Models\Card::$isSyncing = true;
                try {
                    $otherChecklists = \App\Models\CardChecklist::where('sync_id', $checklist->sync_id)
                        ->where('id', '!=', $checklist->id)
                        ->get();
                    foreach ($otherChecklists as $oc) {
                        $oc->delete();
                    }
                } finally {
                    \App\Models\Card::$isSyncing = false;
                }
            }
        });
    }

    protected $appends = ['name'];

    public function getNameAttribute(): string
    {
        return $this->title ?? '';
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CardChecklistItem::class, 'checklist_id')->orderBy('position');
    }

    public function progressPercent(): int
    {
        $total = $this->items->count();
        if ($total === 0) {
            return 0;
        }
        return (int) round(($this->items->where('is_completed', true)->count() / $total) * 100);
    }
}
