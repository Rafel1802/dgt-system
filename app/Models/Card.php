<?php

namespace App\Models;

use App\Enums\CardLabel;
use App\Enums\CardPriority;
use App\Enums\CardStatus;
use App\Enums\CardSubLabel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Card extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'label'     => 'CRM',
        'sub_label' => '',
    ];

    protected $fillable = [
        'board_id',
        'board_list_id',
        'title',
        'description',
        'label',
        'sub_label',
        'priority',
        'status',
        'position',
        'deadline',
        'due_at',
        'start_date',
        'due_time',
        'reminder',
        'recurring',
        'due_reminder_sent',
        'cover_image',
        'is_archived',
        'created_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'sync_group_id',
    ];

    protected $casts = [
        'deadline'          => 'date',
        'due_at'            => 'datetime',
        'start_date'        => 'date',
        'due_reminder_sent' => 'boolean',
        'is_archived'       => 'boolean',
        'approved_at'       => 'datetime',
        'reviewed_at'       => 'datetime',
        'status'            => CardStatus::class,
        'priority'          => CardPriority::class,
    ];

    public static $isSyncing = false;

    protected static function booted()
    {
        static::updated(function ($card) {
            if (self::$isSyncing) {
                return;
            }
            if ($card->sync_group_id) {
                self::$isSyncing = true;
                try {
                    $dirty = $card->getDirty();
                    $syncFields = [
                        'title', 'description', 'label', 'sub_label', 'priority', 'status',
                        'deadline', 'due_at', 'start_date', 'due_time', 'reminder', 'recurring',
                        'cover_image', 'is_archived', 'approved_by', 'approved_at', 'rejection_reason',
                        'reviewed_by', 'reviewed_at'
                    ];

                    $toUpdate = [];
                    foreach ($syncFields as $field) {
                        if (array_key_exists($field, $dirty)) {
                            $toUpdate[$field] = $card->$field;
                        }
                    }

                    if (!empty($toUpdate)) {
                        self::where('sync_group_id', $card->sync_group_id)
                            ->where('id', '!=', $card->id)
                            ->update($toUpdate);
                    }
                } finally {
                    self::$isSyncing = false;
                }
            }
        });
    }

    public function replicateRelationally(int $targetBoardId, int $targetListId, ?string $newTitle = null, ?int $createdBy = null, bool $enableSync = true)
    {
        $isSameBoard = (int)$targetBoardId === (int)$this->board_id;
        if (empty($newTitle)) {
            $newTitle = $isSameBoard ? $this->title . ' (copy)' : $this->title;
        }

        if ($enableSync && !$this->sync_group_id) {
            $this->sync_group_id = (string)\Illuminate\Support\Str::uuid();
            $this->save();
        }

        $position = Card::where('board_list_id', $targetListId)->max('position') + 1;
        $replica = $this->replicate();
        $replica->board_id = $targetBoardId;
        $replica->board_list_id = $targetListId;
        $replica->title = $newTitle;
        $replica->position = $position;
        $replica->created_by = $createdBy ?? auth()->id() ?? $this->created_by;
        if ($enableSync) {
            $replica->sync_group_id = $this->sync_group_id;
        } else {
            $replica->sync_group_id = null;
        }
        $replica->save();

        $originalSyncing = self::$isSyncing;
        self::$isSyncing = true;

        try {
            // Copy assignees
            $replica->assignees()->sync(
                collect($this->assignees)->mapWithKeys(fn($user) => [
                    $user->id => ['assigned_at' => $user->pivot->assigned_at ?? now()]
                ])->all()
            );

            // Copy labels
            $replica->labels()->sync($this->labels->pluck('id')->all());

            // Copy checklists & items
            foreach ($this->checklists as $checklist) {
                if ($enableSync && !$checklist->sync_id) {
                    $checklist->sync_id = (string)\Illuminate\Support\Str::uuid();
                    $checklist->save();
                }

                $newChecklist = $checklist->replicate();
                $newChecklist->card_id = $replica->id;
                if ($enableSync) {
                    $newChecklist->sync_id = $checklist->sync_id;
                }
                $newChecklist->save();

                foreach ($checklist->items as $item) {
                    if ($enableSync && !$item->sync_id) {
                        $item->sync_id = (string)\Illuminate\Support\Str::uuid();
                        $item->save();
                    }

                    $newItem = $item->replicate();
                    $newItem->checklist_id = $newChecklist->id;
                    if ($enableSync) {
                        $newItem->sync_id = $item->sync_id;
                    }
                    $newItem->save();
                }
            }

            // Copy comments
            foreach ($this->comments as $comment) {
                if ($enableSync && !$comment->sync_id) {
                    $comment->sync_id = (string)\Illuminate\Support\Str::uuid();
                    $comment->save();
                }

                $newComment = $comment->replicate();
                $newComment->card_id = $replica->id;
                if ($enableSync) {
                    $newComment->sync_id = $comment->sync_id;
                }
                $newComment->save();
            }

            // Copy files
            foreach ($this->files as $file) {
                if ($enableSync && !$file->sync_id) {
                    $file->sync_id = (string)\Illuminate\Support\Str::uuid();
                    $file->save();
                }

                $newFile = $file->replicate();
                $newFile->card_id = $replica->id;
                if ($enableSync) {
                    $newFile->sync_id = $file->sync_id;
                }

                $newPath = "kanban/{$replica->id}/{$file->stored_name}";
                if (\Illuminate\Support\Facades\Storage::exists($file->path)) {
                    \Illuminate\Support\Facades\Storage::copy($file->path, $newPath);
                }
                $newFile->path = $newPath;
                $newFile->save();
            }
        } finally {
            self::$isSyncing = $originalSyncing;
        }

        return $replica;
    }

    // ─── New Board-Hierarchy Relationships ───────────────────────────────────

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function boardList(): BelongsTo
    {
        return $this->belongsTo(BoardList::class, 'board_list_id');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'card_labels')->withTimestamps();
    }

    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'card_watchers')->withTimestamps();
    }

    // ─── Original Relationships ───────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'card_assignees')
                    ->withPivot('assigned_at');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(CardComment::class)->orderBy('created_at');
    }

    /**
     * QC "approved" comments on this card (non-system, containing "QC approved").
     * Used to count how many times QC reviewed a card (supports revision cycle counting).
     */
    public function qcApprovalComments(): HasMany
    {
        return $this->hasMany(CardComment::class)
                    ->where('is_system', false)
                    ->whereRaw("LOWER(content) LIKE '%qc approved%'")
                    ->orderBy('created_at');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(CardChecklist::class)->orderBy('position');
    }

    public function files(): HasMany
    {
        return $this->hasMany(CardFile::class)->orderBy('created_at');
    }

    // ─── Accessors & Helpers ──────────────────────────────────────────────────

    public function getLabelColorAttribute(): string
    {
        return CardLabel::tryFrom($this->label)?->color() ?? '#6b7280';
    }

    public function getLabelBgAttribute(): string
    {
        return CardLabel::tryFrom($this->label)?->bgColor() ?? '#f3f4f6';
    }

    public function isOverdue(): bool
    {
        return $this->deadline
            && $this->deadline->isPast()
            && ! in_array($this->status, [CardStatus::Done, CardStatus::Approved]);
    }

    public function checklistProgress(): array
    {
        $total = $this->checklists->flatMap->items->count();
        $done  = $this->checklists->flatMap->items->where('is_completed', true)->count();

        return [
            'total'   => $total,
            'done'    => $done,
            'percent' => $total > 0 ? round(($done / $total) * 100) : 0,
        ];
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeByStatus($query, CardStatus $status): mixed
    {
        return $query->where('status', $status->value);
    }

    public function scopeForUser($query, int $userId): mixed
    {
        return $query->where('created_by', $userId)
                     ->orWhereHas('assignees', fn($q) => $q->where('users.id', $userId));
    }
}
