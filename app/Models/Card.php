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
        'team',
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
                        'title', 'description', 'label', 'sub_label', 'team', 'smm_class_label', 'smm_team_label', 'smm_cluster_label', 'priority', 'status',
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
        if ($enableSync) {
            $replica->sync_group_id = $this->sync_group_id;
            // CRITICAL: Synced twin cards represent the same task across boards.
            // They MUST retain the original assigner (created_by), rather than adopting the
            // user who triggered an automation, move, or replication.
            $replica->created_by = $this->created_by ?: ($createdBy ?? auth()->id());
        } else {
            $replica->sync_group_id = null;
            $replica->created_by = $createdBy ?? $this->created_by;
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
                        $q->whereRaw("LOWER(content) LIKE '%qc%approve%'")
                          ->orWhereRaw("LOWER(content) LIKE '%production%approve%'")
                          ->orWhereRaw("LOWER(content) LIKE '%production approved%'")
                          ->orWhereRaw("LOWER(content) LIKE '%production approved smm%'")
                          ->orWhereRaw("LOWER(content) LIKE '%head%approve%'")
                          ->orWhereRaw("LOWER(content) LIKE '%approved%smm%'");
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

    public function setSmmClassLabelAttribute($value): void
    {
        $this->attributes['smm_class_label'] = !empty($value)
            ? \App\Models\SocialMediaClass::canonicalName($value)
            : null;
    }

    public function getSmmClassLinkAttribute()
    {
        if (!$this->smm_class_label) return null;
        
        static $classLinks = null;
        if ($classLinks === null) {
            $classLinks = \App\Models\SocialMediaClass::pluck('external_link', 'name')->toArray();
        }
        
        if (isset($classLinks[$this->smm_class_label])) {
            return $classLinks[$this->smm_class_label];
        }

        $primaryClass = trim(explode(',', $this->smm_class_label)[0]);
        return $classLinks[$primaryClass] ?? null;
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
        
        if (isset($classColors[$this->smm_class_label])) {
            return $classColors[$this->smm_class_label];
        }

        $primaryClass = trim(explode(',', $this->smm_class_label)[0]);
        return $classColors[$primaryClass] ?? '#6366f1';
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

    /**
     * Check if the card has any checklist, and whether all checklist items are completed (100%).
     * Returns true if there is at least one checklist and not all items are completed.
     */
    public function hasIncompleteChecklist(): bool
    {
        $checklists = $this->relationLoaded('checklists')
            ? $this->checklists
            : $this->checklists()->with('items')->get();

        if ($checklists->isEmpty()) {
            return false;
        }

        $allItems = $checklists->flatMap(function ($cl) {
            return $cl->relationLoaded('items') ? $cl->items : $cl->items()->get();
        });

        if ($allItems->isEmpty()) {
            return false;
        }

        return $allItems->contains(fn($item) => ! (bool) $item->is_completed);
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
        // 1. If card itself is on a workflow board, deduce from its own list
        if ($this->relationLoaded('board') && $this->board && str_contains(strtolower($this->board->name), 'workflow')) {
            $list = $this->boardList;
            if ($list) {
                $name = strtolower($list->name);
                if (str_contains($name, 'draft')) return 'Draft';
                if (str_contains($name, 'production') || str_contains($name, 'head') || str_contains($name, 'qc')) return 'Production Team';
                if (str_contains($name, 'digital department') || str_contains($name, 'supervisor')) return 'Digital Department';
                if (str_contains($name, 'approved') || str_contains($name, 'done') || str_contains($name, 'completed')) return 'Approved';
                if (str_contains($name, 'block') || str_contains($name, 'waiting')) return 'Blocked/Waiting';
            }
        }

        // 2. Check sync siblings (e.g. Planning board twin card on Workflow board)
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
            if (str_contains($name, 'production') || str_contains($name, 'head') || str_contains($name, 'qc')) return 'Production Team';
            if (str_contains($name, 'digital department') || str_contains($name, 'supervisor')) return 'Digital Department';
            if (str_contains($name, 'approved') || str_contains($name, 'done') || str_contains($name, 'completed')) return 'Approved';
            if (str_contains($name, 'block') || str_contains($name, 'waiting')) return 'Blocked/Waiting';
        }

        return null;
    }

    /**
     * Detect or return the card's assigned digital team ('A', 'B', or 'Both').
     */
    public function detectTeam(): ?string
    {
        // 1. Explicit attribute
        if (!empty($this->attributes['team'])) {
            $t = strtoupper(trim($this->attributes['team']));
            if (in_array($t, ['BOTH', 'A,B', 'A, B', 'A&B', 'A & B', 'ALL', 'A+B', 'TEAM A & B', 'TEAM A & TEAM B']) || (str_contains($t, 'A') && str_contains($t, 'B'))) {
                return 'Both';
            }
            if ($t === 'A' || $t === 'B') {
                return $t;
            }
            return $t;
        }

        // 2. Check attached labels (e.g. [Team A] and [Team B], or [Team A & B])
        $labels = $this->relationLoaded('labels') ? $this->labels : $this->labels()->get();
        if ($labels && $labels->isNotEmpty()) {
            $hasA = false;
            $hasB = false;
            foreach ($labels as $lbl) {
                $name = $lbl->name ?? '';
                if (preg_match('/Team\s*A\s*(&|\+|and)\s*(Team\s*)?B\b/i', $name)) {
                    return 'Both';
                }
                if (preg_match('/Team\s*A\b/i', $name)) {
                    $hasA = true;
                }
                if (preg_match('/Team\s*B\b/i', $name)) {
                    $hasB = true;
                }
            }
            if ($hasA && $hasB) {
                return 'Both';
            }
            if ($hasA) return 'A';
            if ($hasB) return 'B';
        }

        // 3. Check title or bracketed tag e.g. [Team A], (Team B), Team A, Team B
        $title = $this->title ?? '';
        $titleHasA = preg_match('/\[Team\s*A\]/i', $title) || preg_match('/\(Team\s*A\)/i', $title) || preg_match('/\bTeam\s*A\b/i', $title);
        $titleHasB = preg_match('/\[Team\s*B\]/i', $title) || preg_match('/\(Team\s*B\)/i', $title) || preg_match('/\bTeam\s*B\b/i', $title);
        if ($titleHasA && $titleHasB) {
            return 'Both';
        }
        if ($titleHasA) return 'A';
        if ($titleHasB) return 'B';

        // 4. Check sync siblings (if twin is on Workflow Board Team A or Team B)
        try {
            $siblings = $this->relationLoaded('syncSiblings') ? $this->syncSiblings : $this->syncSiblings()->with('board')->get();
            $sibHasA = false;
            $sibHasB = false;
            foreach ($siblings as $sibling) {
                $bName = strtolower($sibling->board?->name ?? '');
                if (str_contains($bName, 'team a') || str_contains($bName, 'teama') || str_contains($bName, 'team-a')) {
                    $sibHasA = true;
                }
                if (str_contains($bName, 'team b') || str_contains($bName, 'teamb') || str_contains($bName, 'team-b')) {
                    $sibHasB = true;
                }
            }
            if ($sibHasA && $sibHasB) return 'Both';
            if ($sibHasA) return 'A';
            if ($sibHasB) return 'B';
        } catch (\Throwable $e) {}

        // 5. Check assignees (if assignees contain members of Team A and/or Team B)
        try {
            $assignees = $this->relationLoaded('assignees') ? $this->assignees : $this->assignees()->get();
            $assHasA = false;
            $assHasB = false;
            foreach ($assignees as $u) {
                $uTeam = $u->getDigitalTeam();
                if ($uTeam === 'A') $assHasA = true;
                if ($uTeam === 'B') $assHasB = true;
            }
            if ($assHasA && $assHasB) return 'Both';
            if ($assHasA) return 'A';
            if ($assHasB) return 'B';
        } catch (\Throwable $e) {}

        // 6. Check creator: Kim is Team B, Dara is Team A
        try {
            $creator = $this->relationLoaded('creator') ? $this->creator : ($this->created_by ? \App\Models\User::find($this->created_by) : null);
            if ($creator) {
                $cUser = strtolower($creator->username ?? '');
                $cName = strtolower($creator->name ?? '');
                if (str_contains($cUser, 'kim') || str_contains($cName, 'kim') || $creator->id === 13) {
                    return 'B';
                }
                if (str_contains($cUser, 'dara') || str_contains($cName, 'dara') || $creator->id === 12) {
                    return 'A';
                }
                $cTeam = $creator->getDigitalTeam();
                if ($cTeam) {
                    return $cTeam;
                }
            }
        } catch (\Throwable $e) {}

        return null;
    }

    public function isBothTeams(): bool
    {
        $team = $this->team;
        if (!$team) {
            $team = $this->detectTeam();
        }
        $t = strtoupper(trim((string)$team));
        return in_array($t, ['BOTH', 'A,B', 'A, B', 'A&B', 'A & B', 'ALL', 'A+B', 'TEAM A & B', 'TEAM A & TEAM B'])
            || (str_contains($t, 'A') && str_contains($t, 'B'));
    }

    public function hasTeam(string $team): bool
    {
        if ($this->isBothTeams()) {
            return true;
        }
        $t = strtoupper(trim((string)($this->team ?? $this->detectTeam())));
        return $t === strtoupper(trim($team));
    }

    public function getTeamAttribute(): ?string
    {
        return $this->detectTeam();
    }
}
