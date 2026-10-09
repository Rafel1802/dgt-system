<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardChecklistItem extends Model
{
    protected $fillable = [
        'checklist_id',
        'content',
        'title',
        'is_completed',
        'completed_by',
        'completed_at',
        'assigned_user_id',
        'assigned_user_ids',
        'position',
        'sync_id',
        'is_marked',
        'marked_by',
        'marked_at',
        'has_issue',
        'issue_by',
        'issue_at',
        'is_approved',
        'approved_by',
        'approved_at',
    ];

    /**
     * Usernames allowed to tick the green "mark" / red "issue" boxes
     * (Production Team A & B).
     */
    public const REVIEW_MARKER_USERNAMES = ['dara', 'kim'];

    /**
     * Users who may tick/untick ANY checklist item, even when it is assigned to someone else
     * (Production leads + Supervisor).
     */
    public const TICK_OVERRIDE_USERNAMES = ['dara', 'kim', 'somalika'];

    /**
     * Auto-assigned users for checklist categories that are not tied to card members.
     */
    public const CATEGORY_USERNAMES = [
        'listing' => 'chhay',
        'content' => 'sreypich',
    ];

    protected $with = ['assignedUser', 'markedBy', 'issueBy'];

    protected static function booted()
    {
        static::created(function ($item) {
            if (\App\Models\Card::$isSyncing) {
                return;
            }
            $checklist = $item->checklist;
            if ($checklist) {
                if (!$checklist->sync_id && $checklist->card?->sync_group_id) {
                    $checklist->sync_id = (string) \Illuminate\Support\Str::uuid();
                    $checklist->saveQuietly();
                }
            }
            if ($checklist && $checklist->sync_id) {
                \App\Models\Card::$isSyncing = true;
                try {
                    if (!$item->sync_id) {
                        $item->sync_id = (string) \Illuminate\Support\Str::uuid();
                        $item->saveQuietly();
                    }
                    $otherChecklists = \App\Models\CardChecklist::where('sync_id', $checklist->sync_id)
                        ->where('id', '!=', $checklist->id)
                        ->get();
                    foreach ($otherChecklists as $otherChecklist) {
                        if (!\App\Models\CardChecklistItem::where('checklist_id', $otherChecklist->id)->where('sync_id', $item->sync_id)->exists()) {
                            \App\Models\CardChecklistItem::create([
                                'checklist_id'     => $otherChecklist->id,
                                'content'          => $item->content ?? '',
                                'is_completed'     => $item->is_completed ?? false,
                                'completed_by'     => $item->completed_by,
                                'completed_at'     => $item->completed_at,
                                'assigned_user_id' => $item->assigned_user_id,
                                'assigned_user_ids' => $item->assigned_user_ids,
                                'position'         => $item->position ?? 0,
                                'sync_id'          => $item->sync_id,
                                'is_marked'        => $item->is_marked ?? false,
                                'marked_by'        => $item->marked_by,
                                'marked_at'        => $item->marked_at,
                                'has_issue'        => $item->has_issue ?? false,
                                'issue_by'         => $item->issue_by,
                                'issue_at'         => $item->issue_at,
                                'is_approved'      => $item->is_approved ?? false,
                                'approved_by'      => $item->approved_by,
                                'approved_at'      => $item->approved_at,
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
                $allowed = [
                    'content',
                    'is_completed', 'completed_by', 'completed_at',
                    'assigned_user_id', 'assigned_user_ids',
                    'position',
                    'is_marked', 'marked_by', 'marked_at',
                    'has_issue', 'issue_by', 'issue_at',
                    'is_approved', 'approved_by', 'approved_at',
                ];
                $changes = array_intersect_key($item->getChanges(), array_flip($allowed));
                if (empty($changes)) {
                    return;
                }

                if (array_key_exists('assigned_user_ids', $changes) && is_array($changes['assigned_user_ids'])) {
                    $changes['assigned_user_ids'] = json_encode(array_values($changes['assigned_user_ids']));
                }

                \App\Models\Card::$isSyncing = true;
                try {
                    \App\Models\CardChecklistItem::where('sync_id', $item->sync_id)
                        ->where('id', '!=', $item->id)
                        ->update($changes);
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

    protected $appends = ['title', 'assigned_users', 'marked_user', 'issue_user'];

    /**
     * Effective assignee ids: the multi-assignee list, falling back to the single legacy column.
     *
     * @return array<int>
     */
    public function effectiveAssigneeIds(): array
    {
        $ids = $this->assigned_user_ids;
        if (is_array($ids) && count($ids)) {
            return array_values(array_unique(array_map('intval', $ids)));
        }
        if ($this->assigned_user_id) {
            return [(int) $this->assigned_user_id];
        }

        try {
            $card = $this->relationLoaded('checklist') ? $this->checklist?->card : null;
            if (!$card && $this->checklist_id) {
                $card = $this->checklist?->card;
            }
            if ($card) {
                $detected = self::detectUserIdForCard($this->content ?? '', $card);
                if ($detected) {
                    return [$detected];
                }
            }
        } catch (\Throwable $e) {}

        return [];
    }

    /**
     * Assigned users (id, name, avatar...) for the UI.
     */
    public function getAssignedUsersAttribute(): array
    {
        $ids = $this->effectiveAssigneeIds();
        if (!$ids) return [];

        $users = User::withTrashed()->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)
            ->map(fn ($id) => $users->get($id))
            ->filter()
            ->map(fn ($u) => [
                'id'              => $u->id,
                'name'            => $u->name,
                'username'        => $u->username,
                'email'           => $u->email,
                'avatar'          => $u->avatar_url,
                'avatar_url'      => $u->avatar_url,
                'avatar_initials' => $u->avatar_initials,
                'avatar_color'    => $u->avatar_color,
            ])
            ->values()
            ->all();
    }

    public function getMarkedUserAttribute(): ?array
    {
        if (!$this->marked_by) return null;
        $u = $this->relationLoaded('markedBy') ? $this->markedBy : User::withTrashed()->find($this->marked_by);
        if (!$u) return null;
        return [
            'id'              => $u->id,
            'name'            => $u->name,
            'username'        => $u->username,
            'email'           => $u->email,
            'avatar'          => $u->avatar_url,
            'avatar_url'      => $u->avatar_url,
            'avatar_initials' => $u->avatar_initials,
            'avatar_color'    => $u->avatar_color,
        ];
    }

    public function getIssueUserAttribute(): ?array
    {
        if (!$this->issue_by) return null;
        $u = $this->relationLoaded('issueBy') ? $this->issueBy : User::withTrashed()->find($this->issue_by);
        if (!$u) return null;
        return [
            'id'              => $u->id,
            'name'            => $u->name,
            'username'        => $u->username,
            'email'           => $u->email,
            'avatar'          => $u->avatar_url,
            'avatar_url'      => $u->avatar_url,
            'avatar_initials' => $u->avatar_initials,
            'avatar_color'    => $u->avatar_color,
        ];
    }

    public function getTitleAttribute(): string
    {
        return $this->content ?? '';
    }

    public function setTitleAttribute(?string $value): void
    {
        $this->attributes['content'] = $value ?? '';
    }

    protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'is_marked'    => 'boolean',
        'marked_at'    => 'datetime',
        'has_issue'    => 'boolean',
        'issue_at'     => 'datetime',
        'is_approved'  => 'boolean',
        'approved_at'  => 'datetime',
        'assigned_user_ids' => 'array',
    ];

    /**
     * Whether the given user may tick/untick the green mark and red issue boxes.
     */
    public static function canReviewMark($user): bool
    {
        if (!$user) return false;
        $haystacks = [
            strtolower(trim($user->username ?? '')),
            strtolower(trim($user->name ?? '')),
            strtolower(trim($user->email ?? '')),
        ];
        foreach (self::REVIEW_MARKER_USERNAMES as $target) {
            foreach ($haystacks as $h) {
                if ($h !== '' && str_contains($h, $target)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Whether the given user may approve a marked / issue-flagged item (admins only).
     */
    public static function canApprove($user): bool
    {
        if (!$user) return false;
        return $user->hasAnyRole(['super-admin', 'admin', 'admin-digital', 'boss']);
    }

    /**
     * Whether the given user may tick/untick any item regardless of assignment
     * (dara, kim, somalika).
     */
    public static function canOverrideTick($user): bool
    {
        if (!$user) return false;
        $haystacks = [
            strtolower(trim($user->username ?? '')),
            strtolower(trim($user->name ?? '')),
            strtolower(trim($user->email ?? '')),
        ];
        foreach (self::TICK_OVERRIDE_USERNAMES as $target) {
            foreach ($haystacks as $h) {
                if ($h !== '' && str_contains($h, $target)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * An assigned item can only be ticked by its assignee(s) or an override user.
     * Unassigned items stay open to everyone.
     */
    public function canBeTickedBy($user): bool
    {
        $ids = $this->effectiveAssigneeIds();
        if (!$ids) return true;
        if (!$user) return false;
        return in_array((int) $user->id, $ids, true) || self::canOverrideTick($user);
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(CardChecklist::class, 'checklist_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by')->withTrashed();
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id')->withTrashed();
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by')->withTrashed();
    }

    public function issueBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issue_by')->withTrashed();
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    /**
     * Detect category from checklist text (video or graphic).
     */
    public static function detectCategory(string $text): ?string
    {
        $trimmed = trim($text);
        if ($trimmed === '') return null;

        $videoPatterns = [
            '/\bvideo\s+short\b/i',
            '/\bshort\s+video\b/i',
            '/\bvideo\s+landscape\b/i',
            '/\blandscape\s+video\b/i',
            '/\bvideo\s+content\b/i',
            '/\bvideo\b/i',
            '/\bshort\b/i',
            '/\breels?\b/i',
        ];

        $graphicPatterns = [
            '/\bsocial\s+media\s+graphic\b/i',
            '/\bgraphic\s+design\b/i',
            '/\bposter\b/i',
            '/\bgraphic\b/i',
            '/\bdesign\b/i',
            '/\bartwork\b/i',
            '/\bbanner\b/i',
            '/\bcreative\b/i',
        ];

        foreach ($videoPatterns as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                return 'video';
            }
        }

        foreach ($graphicPatterns as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                return 'graphic';
            }
        }

        $listingPatterns = [
            '/\blistings?\b/i',
            '/\bdescriptions?\b/i',
            '/\bdesc\b/i',
        ];

        foreach ($listingPatterns as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                return 'listing';
            }
        }

        if (preg_match('/\bcontent\b/i', $trimmed)) {
            return 'content';
        }

        return null;
    }

    /**
     * For "listing" / "content" checklist text, return the fixed user
     * (listing => chhay, content => sreypich), regardless of card members.
     */
    public static function detectSpecialUserId(string $text): ?int
    {
        $category = self::detectCategory($text);
        $username = self::CATEGORY_USERNAMES[$category] ?? null;
        if (!$username) return null;

        $user = User::whereRaw('LOWER(username) = ?', [$username])->first()
            ?? User::whereRaw('LOWER(username) LIKE ?', ['%' . $username . '%'])->first()
            ?? User::whereRaw('LOWER(name) LIKE ?', ['%' . $username . '%'])->first();

        return $user?->id;
    }

    /**
     * Check if a user belongs to the Listing team (Chhay).
     */
    public static function isListingUser($user): bool
    {
        if (!$user) return false;
        $name = strtolower($user->name ?? '');
        $username = strtolower($user->username ?? '');
        return str_contains($name, 'chhay') || str_contains($username, 'chhay');
    }

    /**
     * Check if a user belongs to the Video team (Samnang, Nalin, Sarak).
     */
    public static function isVideoUser($user): bool
    {
        if (!$user) return false;
        $name = strtolower($user->name ?? '');
        $username = strtolower($user->username ?? '');
        foreach (['samnang', 'nalin', 'sarak'] as $n) {
            if (str_contains($name, $n) || str_contains($username, $n)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a user belongs to the Graphic team (Vouchky, Pich, Sor, Kim).
     */
    public static function isGraphicUser($user): bool
    {
        if (!$user) return false;
        $name = strtolower($user->name ?? '');
        $username = strtolower($user->username ?? '');
        foreach (['vouchky', 'pich', 'sor', 'kim'] as $n) {
            if ($n === 'sor') {
                if (str_contains($name, 'sopor') || str_contains($username, 'sopor') || preg_match('/\bsor\b/i', $name) || preg_match('/\bsor\b/i', $username) || str_starts_with($name, 'sor')) {
                    return true;
                }
            } elseif ($n === 'pich') {
                if (str_contains($name, 'pich') || str_contains($name, 'sreypich') || str_contains($username, 'pich')) {
                    return true;
                }
            } else {
                if (str_contains($name, $n) || str_contains($username, $n)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Detect and return the user ID to assign from the card members.
     * Respects: Checks card members first, falling back to special fixed user.
     */
    public static function detectUserIdForCard(string $text, Card $card): ?int
    {
        $category = self::detectCategory($text);
        if (!$category) return null;

        $assignees = $card->relationLoaded('assignees') ? $card->assignees : $card->assignees()->get();
        if ($assignees->isNotEmpty()) {
            $matching = $assignees->filter(function ($user) use ($category) {
                if ($category === 'video') return self::isVideoUser($user);
                if ($category === 'graphic') return self::isGraphicUser($user);
                if ($category === 'listing') return self::isListingUser($user);
                if ($category === 'content') return str_contains(strtolower($user->name ?? ''), 'sreypich') || str_contains(strtolower($user->username ?? ''), 'sreypich');
                return false;
            });

            if ($matching->count() === 1) {
                return $matching->first()->id;
            }
        }

        if (isset(self::CATEGORY_USERNAMES[$category])) {
            return self::detectSpecialUserId($text);
        }

        return null;
    }

    /**
     * Detect assigned user model for this checklist item.
     */
    public function detectAssignedUser(?Card $card = null): ?User
    {
        $targetCard = $card ?? $this->checklist?->card;
        if (!$targetCard) return null;
        $userId = self::detectUserIdForCard($this->content ?? '', $targetCard);
        return $userId ? User::find($userId) : null;
    }
}
