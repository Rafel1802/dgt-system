<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CardComment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'card_id',
        'user_id',
        'content',
        'is_system',
        'sync_id',
    ];

    protected static function booted()
    {
        static::created(function ($comment) {
            if (\App\Models\Card::$isSyncing) {
                return;
            }
            $card = $comment->card;
            if ($card && $card->sync_group_id) {
                \App\Models\Card::$isSyncing = true;
                try {
                    if (!$comment->sync_id) {
                        $comment->sync_id = (string) \Illuminate\Support\Str::uuid();
                        $comment->save();
                    }
                    $otherCards = \App\Models\Card::where('sync_group_id', $card->sync_group_id)
                        ->where('id', '!=', $card->id)
                        ->get();
                    foreach ($otherCards as $otherCard) {
                        if (!\App\Models\CardComment::where('card_id', $otherCard->id)->where('sync_id', $comment->sync_id)->exists()) {
                            \App\Models\CardComment::create([
                                'card_id' => $otherCard->id,
                                'user_id' => $comment->user_id,
                                'content' => $comment->content,
                                'is_system' => $comment->is_system ?? false,
                                'sync_id' => $comment->sync_id,
                            ]);
                        }
                    }
                } finally {
                    \App\Models\Card::$isSyncing = false;
                }
            }
        });

        static::updated(function ($comment) {
            if (\App\Models\Card::$isSyncing) {
                return;
            }
            if ($comment->sync_id) {
                \App\Models\Card::$isSyncing = true;
                try {
                    \App\Models\CardComment::where('sync_id', $comment->sync_id)
                        ->where('id', '!=', $comment->id)
                        ->update([
                            'content' => $comment->content,
                            'is_system' => $comment->is_system ?? false,
                        ]);
                } finally {
                    \App\Models\Card::$isSyncing = false;
                }
            }
        });

        static::deleted(function ($comment) {
            if (\App\Models\Card::$isSyncing) {
                return;
            }
            if ($comment->sync_id) {
                \App\Models\Card::$isSyncing = true;
                try {
                    \App\Models\CardComment::where('sync_id', $comment->sync_id)
                        ->where('id', '!=', $comment->id)
                        ->delete();
                } finally {
                    \App\Models\Card::$isSyncing = false;
                }
            }
        });
    }

    protected $appends = ['body'];

    public function getBodyAttribute(): string
    {
        return $this->content ?? '';
    }

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
