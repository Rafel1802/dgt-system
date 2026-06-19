<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardChecklistItem extends Model
{
    protected $fillable = [
        'checklist_id',
        'content',
        'is_completed',
        'completed_by',
        'completed_at',
        'position',
        'sync_id',
    ];

    protected static function booted()
    {
        static::created(function ($item) {
            if (\App\Models\Card::$isSyncing) {
                return;
            }
            $checklist = $item->checklist;
            if ($checklist && $checklist->sync_id) {
                \App\Models\Card::$isSyncing = true;
                try {
                    if (!$item->sync_id) {
                        $item->sync_id = (string) \Illuminate\Support\Str::uuid();
                        $item->save();
                    }
                    $otherChecklists = \App\Models\CardChecklist::where('sync_id', $checklist->sync_id)
                        ->where('id', '!=', $checklist->id)
                        ->get();
                    foreach ($otherChecklists as $otherChecklist) {
                        if (!\App\Models\CardChecklistItem::where('checklist_id', $otherChecklist->id)->where('sync_id', $item->sync_id)->exists()) {
                            \App\Models\CardChecklistItem::create([
                                'checklist_id' => $otherChecklist->id,
                                'content' => $item->content ?? '',
                                'is_completed' => $item->is_completed ?? false,
                                'completed_by' => $item->completed_by,
                                'completed_at' => $item->completed_at,
                                'position' => $item->position ?? 0,
                                'sync_id' => $item->sync_id,
                            ]);
                        }
                    }
                } finally {
                    \App\Models\Card::$isSyncing = false;
                }
            }
        });

        static::updated(function ($item) {
            if (\App\Models\Card::$isSyncing) {
                return;
            }
            if ($item->sync_id) {
                \App\Models\Card::$isSyncing = true;
                try {
                    \App\Models\CardChecklistItem::where('sync_id', $item->sync_id)
                        ->where('id', '!=', $item->id)
                        ->update([
                            'content' => $item->content ?? '',
                            'is_completed' => $item->is_completed ?? false,
                            'completed_by' => $item->completed_by,
                            'completed_at' => $item->completed_at,
                            'position' => $item->position ?? 0,
                        ]);
                } finally {
                    \App\Models\Card::$isSyncing = false;
                }
            }
        });

        static::deleted(function ($item) {
            if (\App\Models\Card::$isSyncing) {
                return;
            }
            if ($item->sync_id) {
                \App\Models\Card::$isSyncing = true;
                try {
                    \App\Models\CardChecklistItem::where('sync_id', $item->sync_id)
                        ->where('id', '!=', $item->id)
                        ->delete();
                } finally {
                    \App\Models\Card::$isSyncing = false;
                }
            }
        });
    }

    protected $appends = ['title'];

    public function getTitleAttribute(): string
    {
        return $this->content ?? '';
    }

    protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(CardChecklist::class, 'checklist_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by')->withTrashed();
    }
}
