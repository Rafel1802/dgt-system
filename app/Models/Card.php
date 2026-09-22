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
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
        'smm_class_label',
        'smm_team_label',
        'smm_cluster_label',
        'content_public_date',
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
        'block_completed_by',
        'block_completed_at',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'sync_group_id',
    ];

    protected $casts = [
        'deadline'          => 'date',
        'due_at'            => 'datetime',
        'start_date'        => 'date',
        'content_public_date'=> 'date',
        'due_reminder_sent' => 'boolean',
        'is_archived'       => 'boolean',
        'approved_at'       => 'datetime',
        'block_completed_at'=> 'datetime',
        'reviewed_at'       => 'datetime',
        'status'            => CardStatus::class,
        'priority'          => CardPriority::class,
    ];

    protected $appends = [
        'smm_cluster_link',
        'smm_class_link',
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
                        'title', 'description', 'label', 'sub_label', 'smm_class_label', 'smm_team_label', 'smm_cluster_label', 'priority', 'status',
                        'deadline', 'due_at', 'start_date', 'content_public_date', 'due_time', 'reminder', 'recurring',
                        'cover_image', 'is_archived', 'approved_by', 'approved_at', 'rejection_reason',
                        'reviewed_by', 'reviewed_at', 'block_completed_by', 'block_completed_at', 'created_by'
                    ];

                    $toUpdate = [];
                    foreach ($syncFields as $field) {
                        if (array_key_exists($field, $dirty)) {
                            $val = $card->$field;
                            if ($val instanceof \BackedEnum) {
                                $val = $val->value;
                            }
                            $toUpdate[$field] = $val;
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

        static::deleted(function ($card) {
            // Cascade deletion removed per user request: deleting a card 
            // in one board should not delete its synced siblings.
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

        Card::where('board_list_id', $targetListId)->increment('position');
        $replica = $this->replicate();
        $replica->board_id = $targetBoardId;
        $replica->board_list_id = $targetListId;
        $replica->title = $newTitle;
        $replica->position = 0;
        $replica->created_by = $createdBy ?? $this->created_by;
        if ($enableSync) {
            $replica->sync_group_id = $this->sync_group_id;
        } else {
            $replica->sync_group_id = null;
        }

        // Inherit approved status if source or any twin is approved
        if ($this->status === 'approved' || ($this->sync_group_id && Card::where('sync_group_id', $this->sync_group_id)->where('status', 'approved')->exists())) {
            $replica->status = 'approved';
        }

        $replica->save();

        $originalSyncing = self::$isSyncing;
        self::$isSyncing = true;

        try {
            // Copy assignees from database directly (avoid stale cached relation)
            $assigneeUsers = $this->assignees()->get();
            if ($assigneeUsers->isEmpty() && $this->sync_group_id) {
                $twin = Card::where('sync_group_id', $this->sync_group_id)
                    ->where('id', '!=', $this->id)
                    ->whereHas('assignees')
                    ->first();
                if ($twin) {
                    $assigneeUsers = $twin->assignees()->get();
                }
            }

            $replica->assignees()->sync(
                $assigneeUsers->mapWithKeys(fn($user) => [
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

                if ($file->disk !== 'url' && $file->mime_type !== 'link') {
                    $newPath = "kanban/{$replica->id}/{$file->stored_name}";
                    if (\Illuminate\Support\Facades\Storage::exists($file->path)) {
                        \Illuminate\Support\Facades\Storage::copy($file->path, $newPath);
                    }
                    $newFile->path = $newPath;
                }
                
                $newFile->save();
            }
        } finally {
            self::$isSyncing = $originalSyncing;
        }

        return $replica;
    }

    /**
     * Synchronize this card's assignees to all other cards in its sync group.
     */
    public function syncAssigneesToTwins(?array $assigneeIds = null): void
    {
        if (!$this->sync_group_id) {
            return;
        }

        $twins = self::where('sync_group_id', $this->sync_group_id)
            ->where('id', '!=', $this->id)
            ->get();

        if ($twins->isEmpty()) {
            return;
        }

        $assigneeData = $assigneeIds !== null
            ? collect($assigneeIds)->mapWithKeys(fn($id) => [$id => ['assigned_at' => now()]])->all()
            : $this->assignees()->get()->mapWithKeys(fn($u) => [$u->id => ['assigned_at' => $u->pivot->assigned_at ?? now()]])->all();

        foreach ($twins as $twin) {
            $twin->assignees()->sync($assigneeData);
        }
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
                    ->where(function($q) {
                        $q->whereRaw("LOWER(content) LIKE '%qc%approve%'");
                    })
                    ->orderBy('created_at');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(CardChecklist::class)->orderBy('position');
    }

    public function checklistItems(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(CardChecklistItem::class, CardChecklist::class, 'card_id', 'checklist_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(CardFile::class)->where('is_comment_image', false)->orderBy('created_at');
    }

    public function commentFiles(): HasMany
    {
        return $this->hasMany(CardFile::class)->where('is_comment_image', true)->orderBy('created_at');
    }

    public function allFiles(): HasMany
    {
        return $this->hasMany(CardFile::class)->orderBy('created_at');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject')->orderByDesc('created_at');
    }

    // ─── Accessors & Helpers ──────────────────────────────────────────────────

    public function getLabelColorAttribute()
    {
        $labelEnum = CardLabel::tryFrom($this->label);
        return $labelEnum ? $labelEnum->color() : '#475569';
    }

    public function getSmmClusterLinkAttribute()
    {
        if (!$this->smm_cluster_label) return null;
        
        static $clusterLinks = null;
        if ($clusterLinks === null) {
            $clusterLinks = \App\Models\SocialMediaClass::pluck('external_link', 'name')->toArray();
        }
        
        return $clusterLinks[$this->smm_cluster_label] ?? null;
    }

    public function getSmmClassLinkAttribute()
    {
        if (!$this->smm_class_label) return null;
        
        static $classLinks = null;
        if ($classLinks === null) {
            $classLinks = \App\Models\SocialMediaClass::pluck('external_link', 'name')->toArray();
        }
        
        return $classLinks[$this->smm_class_label] ?? null;
    }

    public function getLabelBgAttribute(): string
    {
        return CardLabel::tryFrom($this->label)?->bgColor() ?? '#f3f4f6';
    }

    public function getSmmClassColorAttribute()
    {
        if (!$this->smm_class_label) return '#6366f1';
        
        static $classColors = null;
        if ($classColors === null) {
            $classColors = \App\Models\SocialMediaClass::pluck('color', 'name')->toArray();
        }
        
        return $classColors[$this->smm_class_label] ?? '#6366f1';
    }

    /**
     * Check if the card is considered approved or completed,
     * checking approved_at, block_completed_at, status enum, list names,
     * 100% checklists, or connected sync twin cards on workflow boards.
     */
    public function isCompletedOrApproved(?array $approvedSyncGroupIds = null): bool
    {
        // 1. Direct approved timestamp
        if (!empty($this->approved_at)) {
            return true;
        }

        // 2. Direct block completed timestamp
        if (!empty($this->block_completed_at)) {
            return true;
        }

        // 3. Status column is approved, done, or completed
        $statusStr = is_object($this->status) ? ($this->status->value ?? '') : (string)$this->status;
        $statusLower = strtolower(trim($statusStr));
        if (in_array($statusLower, ['approved', 'done', 'completed', 'complete'])) {
            return true;
        }

        // 4. Current list name indicates approved, done, or completed
        $listName = strtolower(trim($this->boardList?->name ?? ''));
        if ($listName !== '') {
            if (str_contains($listName, 'approved') ||
                str_contains($listName, 'done') ||
                str_contains($listName, 'completed') ||
                str_contains($listName, 'complete') ||
                str_contains($listName, 'finish') ||
                str_contains($listName, 'published')) {
                return true;
            }
        }

        // 5. Check connected sync twin cards (e.g. Workflow board card)
        if ($this->sync_group_id) {
            if ($approvedSyncGroupIds !== null) {
                if (in_array($this->sync_group_id, $approvedSyncGroupIds)) {
                    return true;
                }
            } else {
                $twinApproved = self::where('sync_group_id', $this->sync_group_id)
                    ->where('id', '!=', $this->id)
                    ->where(function ($q) {
                        $q->whereNotNull('approved_at')
                          ->orWhereNotNull('block_completed_at')
                          ->orWhereIn('status', ['approved', 'done', 'completed'])
                          ->orWhereHas('boardList', function ($lq) {
                              $lq->where('name', 'like', '%approved%')
                                 ->orWhere('name', 'like', '%done%')
                                 ->orWhere('name', 'like', '%completed%')
                                 ->orWhere('name', 'like', '%complete%')
                                 ->orWhere('name', 'like', '%finish%')
                                 ->orWhere('name', 'like', '%published%');
                          });
                    })
                    ->exists();

                if ($twinApproved) {
                    return true;
                }
            }
        }

        // 6. If card has checklist items and all are completed (100%)
        if ($this->relationLoaded('checklists') && $this->checklists->isNotEmpty()) {
            $allItems = $this->checklists->flatMap->items;
            if ($allItems->isNotEmpty() && $allItems->every(fn($item) => (bool)$item->is_completed)) {
                return true;
            }
        }

        return false;
    }

    public function isOverdue(?array $approvedSyncGroupIds = null): bool
    {
        $due = $this->due_at ?? ($this->deadline ? \Carbon\Carbon::parse($this->deadline) : null);
        if (!$due || !$due->isPast()) {
            return false;
        }

        return !$this->isCompletedOrApproved($approvedSyncGroupIds);
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

    public function syncSiblings()
    {
        return $this->hasMany(Card::class, 'sync_group_id', 'sync_group_id')->where('id', '!=', $this->id);
    }

    public function getWorkflowStatusAttribute(): ?string
    {
        if (!$this->sync_group_id) {
            return null;
        }

        // We assume syncSiblings is eager loaded. If not, it will lazy load.
        $siblings = $this->syncSiblings;
        if ($siblings->isEmpty()) {
            return null;
        }

        foreach ($siblings as $sibling) {
            $list = $sibling->boardList;
            if (!$list) continue;
            
            $name = strtolower($list->name);
            if (str_contains($name, 'draft')) return 'Draft';
            if (str_contains($name, 'head')) return 'Head';
            if (str_contains($name, 'qc')) return 'QC';
            if (str_contains($name, 'supervisor')) return 'Supervisor';
            if (str_contains($name, 'block') || str_contains($name, 'waiting')) return 'Block/waiting';
            // Approved doesn't get a text label because it has a green tick, per user request.
        }

        return null;
    }
}
