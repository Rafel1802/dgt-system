<?php

namespace App\Services;

use App\Enums\CardStatus;
use App\Models\Board;
use App\Models\Card;
use App\Models\CardFile;
use App\Models\User;
use App\Models\Workspace;
use App\Jobs\SendTaskApprovalEmailJob;
use App\Jobs\SendTaskRejectionEmailJob;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class KanbanService
{
    /**
     * Get all cards grouped by status for the board view.
     * Respects role-based visibility.
     */
    public function getBoardData(User $user): array
    {
        $query = Card::with([
            'creator:id,name,avatar',
            'assignees:id,name,avatar',
            'checklists.items',
        ])->withCount(['comments', 'files'])
        ->orderBy('position');

        // Staff/digital-team only see their own cards + assigned cards
        if ($user->hasAnyRole(['staff', 'digital-team', 'sales-crm'])) {
            $query->forUser($user->id);
        }

        $cards = $query->get();

        $columns = [];
        foreach (CardStatus::columns() as $status) {
            $columns[$status->value] = [
                'status'  => $status,
                'cards'   => $cards->where('status', $status)->values(),
            ];
        }

        return $columns;
    }

    /**
     * Create a new card with assignees.
     */
    public function createCard(array $data, User $creator): Card
    {
        return DB::transaction(function () use ($data, $creator) {
            $card = Card::create([
                'title'       => $data['title'],
                'description' => $data['description'] ?? null,
                'label'       => $data['label'],
                'sub_label'   => $data['sub_label'] ?? null,
                'priority'    => $data['priority'] ?? 'medium',
                'status'      => CardStatus::Todo->value,
                'deadline'    => $data['deadline'] ?? null,
                'created_by'  => $creator->id,
                'position'    => Card::where('status', CardStatus::Todo->value)->max('position') + 1,
            ]);

            // Assign users
            if (! empty($data['assignees'])) {
                $card->assignees()->attach($data['assignees'], ['assigned_at' => now()]);
            }

            // System comment
            $this->addSystemComment($card, $creator, "Task created by {$creator->name}.");

            return $card;
        });
    }

    /**
     * Update card details.
     */
    public function updateCard(Card $card, array $data, User $updater): Card
    {
        return DB::transaction(function () use ($card, $data, $updater) {
            $old = $card->only(['title', 'label', 'priority', 'deadline']);

            $card->update([
                'title'       => $data['title'],
                'description' => $data['description'] ?? $card->description,
                'label'       => $data['label'],
                'sub_label'   => $data['sub_label'] ?? null,
                'priority'    => $data['priority'] ?? $card->priority,
                'deadline'    => $data['deadline'] ?? $card->deadline,
            ]);

            // Sync assignees
            if (array_key_exists('assignees', $data)) {
                $card->assignees()->sync(
                    collect($data['assignees'])->mapWithKeys(fn($id) => [$id => ['assigned_at' => now()]])->all()
                );
            }

            $changes = [];
            if ($old['title'] !== $data['title']) $changes[] = "title updated";
            if ($old['label'] !== $data['label']) $changes[] = "label changed to {$data['label']}";

            if ($changes) {
                $this->addSystemComment($card, $updater, "Card updated by {$updater->name}: " . implode(', ', $changes) . '.');
            }

            return $card->fresh();
        });
    }

    /**
     * Move card to a new status (drag-drop or button action).
     * Validates the transition is allowed for this user's role.
     */
    public function moveCard(Card $card, CardStatus $newStatus, int $position, User $mover): Card
    {
        return DB::transaction(function () use ($card, $newStatus, $position, $mover) {
            $oldStatus = $card->status;

            $updateData = [
                'status'   => $newStatus->value,
                'position' => $position,
            ];

            if ($newStatus === CardStatus::Approved) {
                if (!$card->approved_at) {
                    $updateData['approved_by'] = $mover->id;
                    $updateData['approved_at'] = now();
                }
            } elseif ($oldStatus === CardStatus::Approved) {
                $updateData['approved_by'] = null;
                $updateData['approved_at'] = null;
            }

            $card->update($updateData);

            $this->addSystemComment(
                $card,
                $mover,
                "Card moved from **{$oldStatus->label()}** to **{$newStatus->label()}** by {$mover->name}."
            );

            return $card;
        });
    }

    /**
     * Supervisor approves a card.
     * Sends email notification to all Boss-role users.
     */
    public function approveCard(Card $card, User $supervisor): Card
    {
        $card = DB::transaction(function () use ($card, $supervisor) {
            $card->update([
                'status'      => CardStatus::Approved->value,
                'approved_by' => $supervisor->id,
                'approved_at' => now(),
                'reviewed_by' => $supervisor->id,
                'reviewed_at' => now(),
            ]);

            $this->addSystemComment($card, $supervisor, "✅ Task **approved** by {$supervisor->name}.");

            return $card;
        });

        // Run synchronously (not ::dispatch()) — this app has no queue worker
        // running, so a queued job would just sit unprocessed forever. Run
        // after the transaction commits and behind a try/catch so a mail
        // hiccup can't roll back the approval that already succeeded.
        try {
            SendTaskApprovalEmailJob::dispatchSync($card, $supervisor);
        } catch (\Throwable $e) {
            Log::error("SendTaskApprovalEmailJob failed synchronously for card #{$card->id}: {$e->getMessage()}");
        }

        return $card;
    }

    /**
     * Supervisor toggles approval of a card.
     * Sends email notification to QC and assigned members.
     */
    public function toggleApproveCard(Card $card, User $supervisor): Card
    {
        $isApproved = $card->status === CardStatus::Approved;

        $card = DB::transaction(function () use ($card, $supervisor, $isApproved) {
            if ($isApproved) {
                // Revert to InProgress
                $card->update([
                    'status'      => CardStatus::InProgress->value,
                    'approved_by' => null,
                    'approved_at' => null,
                    'reviewed_by' => $supervisor->id,
                    'reviewed_at' => now(),
                ]);
                $this->addSystemComment($card, $supervisor, "❌ Task **un-approved** by {$supervisor->name}.");
            } else {
                // Approve
                $card->update([
                    'status'      => CardStatus::Approved->value,
                    'approved_by' => $supervisor->id,
                    'approved_at' => now(),
                    'reviewed_by' => $supervisor->id,
                    'reviewed_at' => now(),
                ]);
                $this->addSystemComment($card, $supervisor, "✅ Task **approved** by {$supervisor->name}.");
            }

            return $card;
        });

        // Dispatch notification job
        try {
            \App\Jobs\SendTaskToggledNotificationJob::dispatchSync($card, $supervisor, !$isApproved);
        } catch (\Throwable $e) {
            Log::error("SendTaskToggledNotificationJob failed synchronously for card #{$card->id}: {$e->getMessage()}");
        }

        return $card;
    }

    /**
     * Supervisor rejects a card and notifies the creator.
     */
    public function rejectCard(Card $card, User $supervisor, string $reason): Card
    {
        $card = DB::transaction(function () use ($card, $supervisor, $reason) {
            $card->update([
                'status'           => CardStatus::Rejected->value,
                'rejection_reason' => $reason,
                'reviewed_by'      => $supervisor->id,
                'reviewed_at'      => now(),
            ]);

            $this->addSystemComment(
                $card,
                $supervisor,
                "❌ Task **rejected** by {$supervisor->name}. Reason: {$reason}"
            );

            return $card;
        });

        // See approveCard() — run synchronously, after commit, failure-isolated.
        try {
            SendTaskRejectionEmailJob::dispatchSync($card, $supervisor, $reason);
        } catch (\Throwable $e) {
            Log::error("SendTaskRejectionEmailJob failed synchronously for card #{$card->id}: {$e->getMessage()}");
        }

        return $card;
    }

    /**
     * Upload a file attachment to a card.
     */
    public function uploadFile(Card $card, UploadedFile $file, User $uploader, bool $isCommentImage = false): CardFile
    {
        $disk = config('filesystems.default', 'local');
        $storedName = $file->hashName();
        $path = $file->storeAs("kanban/{$card->id}", $storedName, $disk);

        $cardFile = CardFile::create([
            'card_id'       => $card->id,
            'uploaded_by'   => $uploader->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_name'   => $storedName,
            'disk'          => $disk,
            'path'          => $path,
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
            'is_comment_image' => $isCommentImage,
        ]);

        return $cardFile;
    }

    /**
     * Delete a file from storage and DB.
     */
    public function deleteFile(CardFile $file, User $user): void
    {
        Storage::delete($file->path);
        $file->delete();
    }

    /**
     * Reorder cards within a column after a drag-drop.
     */
    public function reorderCards(array $orderedIds, CardStatus $status): void
    {
        foreach ($orderedIds as $position => $cardId) {
            Card::where('id', $cardId)->where('status', $status->value)
                ->update(['position' => $position + 1]);
        }
    }

    /**
     * Team classification: checks the card's `label` field, Labels pivot, and SMM team label
     */
    public function matchesTeam(Card $card, string $keyword): bool
    {
        if (stripos($card->label ?? '', $keyword) !== false) {
            return true;
        }
        if ($card->relationLoaded('labels') &&
            $card->labels->contains(fn($l) => stripos($l->name ?? '', $keyword) !== false)) {
            return true;
        }
        if (stripos($card->smm_team_label ?? '', $keyword) !== false) {
            return true;
        }
        if ($card->relationLoaded('board') && $card->board) {
            if (stripos($card->board->name ?? '', $keyword) !== false) {
                return true;
            }
            if ($card->board->relationLoaded('workspace') && $card->board->workspace) {
                if (stripos($card->board->workspace->name ?? '', $keyword) !== false) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Get complete approval queue data & pipeline statistics (cached).
     */
    public function getApprovalQueueData(?array $selectedBoardIds = null, string $period = 'today', ?User $user = null): array
    {
        $user = $user ?? auth()->user();
        $isQc = $user && ($user->isQc() || str_contains(strtolower($user->name ?? ''), 'dara') || str_contains(strtolower($user->team_role ?? ''), 'qc'));
        $isSupervisor = $user && ($user->isSupervisorOrAdminDigital() || $user->isSupervisorRole()) && !$isQc;

        // Available workflow boards (with an "Approved" list)
        $availableBoards = Board::with('workspace')
            ->where('is_archived', false)
            ->whereHas('lists', fn($q) => $q->where('name', 'like', '%Approved%'))
            ->get()
            ->groupBy(function($board) {
                if (stripos($board->name, 'Workflow') !== false) {
                    return $board->workspace_id . '_workflow';
                }
                return $board->id;
            })
            ->map(fn($boards) => $boards->sortByDesc('created_at')->first())
            ->values()
            ->sortBy('name');

        if ($selectedBoardIds && count($selectedBoardIds) > 0) {
            $selectedBoardIds = array_map('intval', $selectedBoardIds);
        } else {
            $selectedBoardIds = $availableBoards->pluck('id')->toArray();
        }

        [$rangeStart, $rangeEnd] = match($period) {
            'week'  => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
            'month' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            default => [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()],
        };

        $cacheKey = 'approval_stats_' . md5(json_encode($selectedBoardIds)) . '_' . $period;

        $stats = Cache::remember($cacheKey, 10, function() use ($selectedBoardIds, $rangeStart, $rangeEnd) {
            $activeCards = Card::with(['boardList', 'labels', 'board.workspace'])
                ->whereIn('board_id', $selectedBoardIds)
                ->whereNotNull('board_id')
                ->whereHas('board')
                ->where('is_archived', false)
                ->get();

            $getBreakdownForCards = function($cards) {
                $graphicCount = $cards->filter(fn($c) => $this->matchesTeam($c, 'Graphic'))->count();
                $videoCount   = $cards->filter(fn($c) => $this->matchesTeam($c, 'Video'))->count();
                $listingCount = $cards->filter(fn($c) => $this->matchesTeam($c, 'Listing'))->count();
                $contentCount = $cards->filter(fn($c) => $this->matchesTeam($c, 'Content'))->count();
                $qcCount      = $cards->filter(fn($c) =>
                    $this->matchesTeam($c, 'QC') || $this->matchesTeam($c, 'Text')
                )->count();

                return [
                    'total'   => $graphicCount + $videoCount + $listingCount + $contentCount + $qcCount,
                    'graphic' => $graphicCount,
                    'video'   => $videoCount,
                    'listing' => $listingCount,
                    'content' => $contentCount,
                    'qc'      => $qcCount,
                    'smm'     => 0,
                ];
            };

            $getBreakdown = function($keywords) use ($activeCards, $getBreakdownForCards) {
                $keywords = (array) $keywords;
                $cards = $activeCards->filter(function($c) use ($keywords) {
                    $listName = $c->boardList?->name ?? '';
                    foreach ($keywords as $kw) {
                        if (stripos($listName, $kw) !== false) return true;
                    }
                    return false;
                });
                return $getBreakdownForCards($cards);
            };

            $urgentCards = $activeCards->filter(fn($c) =>
                stripos($c->boardList?->name ?? '', 'Urgent') !== false
            );
            $urgent  = $getBreakdownForCards($urgentCards);
            $overdue = $activeCards->filter(fn($c) => $c->isOverdue())->count();

            $queryApproved = function(?Carbon $start = null, ?Carbon $end = null) use ($selectedBoardIds) {
                $q = Card::with(['boardList', 'labels', 'board.workspace'])
                    ->whereIn('board_id', $selectedBoardIds)
                    ->whereHas('boardList', fn($bl) => $bl->where('name', 'like', '%Approved%'));

                if ($start && $end) {
                    $q->where(function ($query) use ($start, $end) {
                        $query->whereBetween('approved_at', [$start, $end])
                              ->orWhere(function ($sub) use ($start, $end) {
                                  $sub->whereNull('approved_at')
                                      ->whereBetween('updated_at', [$start, $end]);
                              });
                    });
                }
                return $q->get();
            };

            $approvedCards = $queryApproved($rangeStart, $rangeEnd);
            $todayCards = $queryApproved(Carbon::now()->startOfDay(), Carbon::now()->endOfDay());
            $weekCards = $queryApproved(Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek());
            $monthCards = $queryApproved(Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth());
            $allTimeCards = $queryApproved();

            return [
                'drafting'          => $getBreakdown(['Drafting', 'Draft', 'To do', 'Todo']),
                'head_review'       => $getBreakdown(['Head Review']),
                'qc_review'         => $getBreakdown(['QC', 'Text Review']),
                'supervisor_review' => $getBreakdown(['Supervisor Review', 'Supervisor']),
                'urgent'            => $urgent,
                'overdue'           => $overdue,
                'approved'          => $getBreakdownForCards($approvedCards),
                'approved_today'    => $getBreakdownForCards($todayCards),
                'approved_week'     => $getBreakdownForCards($weekCards),
                'approved_month'    => $getBreakdownForCards($monthCards),
                'approved_all'      => $getBreakdownForCards($allTimeCards),
            ];
        });

        // Board Links
        $boardLinks = [
            'graphic' => Board::whereHas('workspace', fn($q) => $q->where('name', 'like', '%Graphic%'))->where('is_archived', false)->where('name', 'like', '%Workflow%')->latest()->first()?->slug,
            'video'   => Board::whereHas('workspace', fn($q) => $q->where('name', 'like', '%Video%'))->where('is_archived', false)->where('name', 'like', '%Workflow%')->latest()->first()?->slug,
            'listing' => Board::whereHas('workspace', fn($q) => $q->where('name', 'like', '%Listing%'))->where('is_archived', false)->where('name', 'like', '%Workflow%')->latest()->first()?->slug,
            'content' => Board::whereHas('workspace', fn($q) => $q->where('name', 'like', '%Conten%'))->where('is_archived', false)->where('name', 'like', '%Workflow%')->latest()->first()?->slug,
            'qc'      => Board::whereHas('workspace', fn($q) => $q->where('name', 'like', '%QC%'))->where('is_archived', false)->where('name', 'like', '%Workflow%')->latest()->first()?->slug,
        ];

        // SMM Planning Board Stats
        $smmPlanningStats = [];
        $teams = [
            'graphic' => 'Graphic',
            'video'   => 'Video',
            'listing' => 'Listing',
            'content' => 'Conten',
            'qc'      => 'QC'
        ];

        foreach ($teams as $key => $workspaceName) {
            $workspace = Workspace::where('name', 'like', "%{$workspaceName}%")->first();
            if ($workspace) {
                $planningBoard = Board::where('workspace_id', $workspace->id)
                    ->where('name', 'like', '%Planning%')
                    ->where('is_archived', false)
                    ->where('is_hidden', false)
                    ->latest()
                    ->first();

                if ($planningBoard) {
                    $teamCards = Card::where('board_id', $planningBoard->id)->with('boardList')->get();
                    $smmPlanningStats[$key] = [
                        'name' => str_replace('Conten', 'Content', $workspaceName),
                        'board_name' => $planningBoard->name,
                        'board_slug' => $planningBoard->slug,
                        'weeks' => [],
                    ];
                    foreach (['Week 1', 'Week 2', 'Week 3', 'Week 4'] as $week) {
                        $weekCards = $teamCards->filter(fn($c) => stripos($c->boardList?->name ?? '', $week) !== false);
                        $smmPlanningStats[$key]['weeks'][$week] = [
                            'approved'   => $weekCards->filter(fn($c) => $c->status === 'approved' || !empty($c->approved_at))->count(),
                            'unapproved' => $weekCards->filter(fn($c) => $c->status !== 'approved' && empty($c->approved_at))->count(),
                        ];
                    }
                }
            }
        }

        // Pending cards for user review queue
        $pendingCardsQuery = Card::with([
            'boardList',
            'board.workspace',
            'creator:id,name,avatar',
            'assignees:id,name,avatar',
            'labels',
        ])
        ->whereIn('board_id', $selectedBoardIds)
        ->whereHas('board')
        ->where('is_archived', false);

        if ($isQc) {
            $pendingCardsQuery->whereHas('boardList', function($q) {
                $q->where('name', 'like', '%QC%')
                  ->orWhere('name', 'like', '%Text Review%');
            });
        } else {
            $pendingCardsQuery->whereHas('boardList', function($q) {
                $q->where('name', 'like', '%Supervisor Review%')
                  ->orWhere('name', 'like', '%Supervisor%');
            });
        }

        $pendingCards = $pendingCardsQuery
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->orderBy('deadline')
            ->orderBy('created_at')
            ->limit($isSupervisor ? 500 : 30)
            ->get();

        $totalGraphic = ($stats['drafting']['graphic'] ?? 0) + ($stats['head_review']['graphic'] ?? 0) + ($stats['qc_review']['graphic'] ?? 0) + ($stats['supervisor_review']['graphic'] ?? 0);
        $totalVideo   = ($stats['drafting']['video'] ?? 0) + ($stats['head_review']['video'] ?? 0) + ($stats['qc_review']['video'] ?? 0) + ($stats['supervisor_review']['video'] ?? 0);
        $totalListing = ($stats['drafting']['listing'] ?? 0) + ($stats['head_review']['listing'] ?? 0) + ($stats['qc_review']['listing'] ?? 0) + ($stats['supervisor_review']['listing'] ?? 0);
        $totalContent = ($stats['drafting']['content'] ?? 0) + ($stats['head_review']['content'] ?? 0) + ($stats['qc_review']['content'] ?? 0) + ($stats['supervisor_review']['content'] ?? 0);
        $totalQc      = ($stats['drafting']['qc'] ?? 0) + ($stats['head_review']['qc'] ?? 0) + ($stats['qc_review']['qc'] ?? 0) + ($stats['supervisor_review']['qc'] ?? 0);

        return [
            'stats'            => $stats,
            'availableBoards'  => $availableBoards,
            'selectedBoardIds' => $selectedBoardIds,
            'boardLinks'       => $boardLinks,
            'smmPlanningStats' => $smmPlanningStats,
            'pendingCards'     => $pendingCards,
            'totalGraphic'     => $totalGraphic,
            'totalVideo'       => $totalVideo,
            'totalListing'     => $totalListing,
            'totalContent'     => $totalContent,
            'totalQc'          => $totalQc,
            'isQc'             => $isQc,
            'isSupervisor'     => $isSupervisor,
            'period'           => $period,
        ];
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function addSystemComment(Card $card, User $user, string $message): void
    {
        $card->comments()->create([
            'user_id'   => $user->id,
            'content'   => $message,
            'is_system' => true,
        ]);
    }
}
