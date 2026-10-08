<?php

namespace App\Http\Controllers\Board;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\Card;
use App\Models\BoardList;
use App\Models\CardChecklist;
use App\Models\CardChecklistItem;
use App\Models\CardComment;
use App\Models\CardFile;
use App\Models\ActivityLog;
use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * CardController (Board edition)
 *
 * Handles card CRUD, card detail modal data, card moves, checklists, comments, and attachments.
 * All responses are JSON (consumed by Alpine.js on the board view).
 */
class CardController extends Controller
{
    /** Return full card data for the detail modal. */
    public function show($id): JsonResponse
    {
        $card = Card::find($id);
        
        if (!$card) {
            return response()->json(['error' => 'This card has been deleted or no longer exists.'], 404);
        }

        $card->load([
            'assignees',
            'labels',
            'checklists.items',
            'comments.user',
            'comments.reactions.user',
            'files',
            'creator:id,name,avatar,username',
            'boardList:id,name',
        ]);

        $activities = ActivityLog::with('user')
            ->where('subject_type', Card::class)
            ->where('subject_id', $card->id)
            ->whereNotIn('action', ['card_reordered', 'reordered', 'card.reordered'])
            ->where('description', 'not like', '%reordered%')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn($log) => [
                'id'          => $log->id,
                'user_name'   => $log->user?->name ?? 'System',
                'user_avatar' => $log->user?->avatar_url ?? $this->defaultAvatar(),
                'user_initials' => $log->user?->avatar_initials ?? 'SY',
                'user_avatar_color' => $log->user?->avatar_color ?? '#64748b',
                'description' => preg_replace(
                    ['/\bbulk\s+copied\b/i', '/\bbulk\s+moved\b/i', '/^copied\s+from\s+card\b/i', '/^Copied\s+From\s+Card\b/'],
                    ['copied', 'moved', 'copied this card from', 'copied this card from'],
                    $log->description
                ),
                'action'      => $log->action,
                'created_at'  => $log->created_at?->toISOString(),
                'time_ago'    => $log->created_at ? $log->created_at->format('M j, Y, g:i A') : 'N/A',
            ]);

        // Build a clean card payload with correct avatar URLs
        $cardData = $card->toArray();
        $cardData['team'] = $card->team;
        $cardData['content_public_date'] = $card->content_public_date?->format('Y-m-d');
        $cardData['board_list_name'] = $card->boardList?->name;
        $cardData['files'] = $card->files->map(fn($file) => $this->filePayload($file))->values()->all();
        $cardData['assignees'] = $card->assignees->map(fn($u) => [
            'id'     => $u->id,
            'name'   => $u->name,
            'email'  => $u->email,
            'avatar' => $u->avatar_url,
            'initials' => $u->avatar_initials,
            'avatar_color' => $u->avatar_color,
        ])->values()->all();
        // Explicitly re-map creator so avatar fields are always included
        // (toArray() only has what was loaded via 'creator:id,name,avatar,username')
        $cardData['creator'] = $card->creator ? [
            'id'          => $card->creator->id,
            'name'        => $card->creator->name,
            'avatar'      => $card->creator->avatar_url,
            'initials'    => $card->creator->avatar_initials,
            'avatar_color'=> $card->creator->avatar_color,
        ] : null;
        $cardData['comments'] = $card->comments
            ->reject(fn($c) => $c->is_system)
            ->map(fn($c) => [
            'id'         => $c->id,
            'body'       => $c->body ?? $c->content,
            'content'    => $c->body ?? $c->content,
            'user_id'    => $c->user_id,
            'created_at' => $c->created_at?->toISOString(),
            'user'       => $c->user ? [
                'id'     => $c->user->id,
                'name'   => $c->user->name,
                'avatar' => $c->user->avatar_url,
                'avatar_initials' => $c->user->avatar_initials,
                'avatar_color' => $c->user->avatar_color,
            ] : null,
            'reactions'  => $c->reactions ? $c->reactions->map(fn($r) => [
                'id' => $r->id,
                'emoji' => $r->emoji,
                'user_id' => $r->user_id,
                'user' => $r->user ? [
                    'id' => $r->user->id,
                    'name' => $r->user->name,
                    'avatar_url' => $r->user->avatar_url,
                ] : null,
            ])->values()->all() : [],
        ])->values()->all();

        return response()->json([
            'card'       => $cardData,
            'progress'   => $card->checklistProgress(),
            'activities' => $activities,
        ]);
    }

    /** Create a new card inside a board list. */
    public function store(Request $request, Board $board): JsonResponse
    {
        $validated = $request->validate([
            'board_list_id'  => ['required', 'exists:board_lists,id'],
            'title'          => ['required', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'priority'       => ['nullable', 'in:low,medium,high,urgent'],
            'due_at'         => ['nullable', 'date'],
            'label'          => ['nullable', 'string', 'max:50'],
            'smm_team_label' => ['nullable', 'string', 'max:100'],
            'smm_cluster_label' => ['nullable', 'string', 'max:100'],
            'smm_class_label'   => ['nullable', 'string', 'max:100'],
            'content_public_date' => ['nullable', 'date'],
            'team'           => ['nullable', 'string'],
            'label_ids'      => ['nullable', 'array'],
            'label_ids.*'    => ['integer', 'exists:labels,id'],
        ]);

        $user = auth()->user();
        $uname = strtolower($user?->username ?? '');
        $name = strtolower($user?->name ?? '');

        $bn = strtolower($board->name ?? '');
        $boardTeam = null;
        if (str_contains($bn, 'team a') || str_contains($bn, 'teama') || str_contains($bn, 'team-a')) {
            $boardTeam = 'A';
        } elseif (str_contains($bn, 'team b') || str_contains($bn, 'teamb') || str_contains($bn, 'team-b')) {
            $boardTeam = 'B';
        }

        if (!empty($validated['team'])) {
            $t = strtoupper(trim($validated['team']));
            if (in_array($t, ['BOTH', 'A,B', 'A, B', 'A&B', 'A & B', 'ALL', 'A+B', 'TEAM A & B', 'TEAM A & TEAM B']) || (str_contains($t, 'A') && str_contains($t, 'B'))) {
                $validated['team'] = 'Both';
            } elseif ($t === 'A' || $t === 'B') {
                $validated['team'] = $t;
            } else {
                $validated['team'] = $validated['team'];
            }
        } elseif ($boardTeam) {
            $validated['team'] = $boardTeam;
        } elseif (str_contains($uname, 'kim') || str_contains($name, 'kim') || $user?->id === 13) {
            $validated['team'] = 'B';
        } elseif (str_contains($uname, 'dara') || str_contains($name, 'dara') || $user?->id === 12) {
            $validated['team'] = 'A';
        } elseif (!empty($user?->team)) {
            $validated['team'] = strtoupper(trim($user->team));
        } elseif ($userTeam = $user?->getDigitalTeam($board->workspace_id)) {
            $validated['team'] = $userTeam;
        }

        $position = Card::where('board_list_id', $validated['board_list_id'])->max('position') + 1;

        $cardData = collect($validated)->except(['label_ids'])->all();

        $card = Card::create([
            ...$cardData,
            'board_id'   => $board->id,
            'status'     => 'todo',
            'priority'   => $validated['priority'] ?? 'medium',
            'position'   => $position,
            'created_by' => auth()->id(),
        ]);

        if (!empty($validated['label_ids'])) {
            $card->labels()->sync($validated['label_ids']);
        }

        $card->load('assignees:id,name,avatar', 'labels');

        $this->logCardActivity($card, 'created', "created this card");
        $this->checkAutomations($card, null, null, true); // new cards can trigger keyword rules if title has it

        // Auto-distribute SMM card to Team Planning Board if board is SMM
        try {
            $isSmmBoard = $board->type === 'smm'
                || !empty($board->is_active_smm)
                || stripos($board->name ?? '', 'smm') !== false
                || stripos($board->workspace?->name ?? '', 'social media') !== false;

            if ($isSmmBoard) {
                $teamLabel = $validated['smm_team_label'] ?? $validated['label'] ?? null;
                app(\App\Services\BoardWorkflowService::class)->distributeSmmCardToTeam($card, $teamLabel);
            }
        } catch (\Throwable $e) {
            \Log::warning("Error distributing SMM card to team workspace on store: " . $e->getMessage());
        }

        return response()->json([
            'card'    => $this->formatCardForBoard($card),
            'message' => 'Card created successfully.'
        ], 201);
    }

    public function update(Request $request, Card $card): JsonResponse
    {
        $validated = $request->validate([
            'title'         => ['sometimes', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'priority'      => ['sometimes', 'in:low,medium,high,urgent'],
            'due_at'        => ['nullable', 'date'],
            'start_date'    => ['nullable', 'date'],
            'due_time'      => ['nullable', 'date_format:H:i,H:i:s'],
            'reminder'      => ['nullable', 'integer', 'min:0'],
            'recurring'     => ['nullable', 'in:none,daily,weekly,monthly,yearly'],
            'cover_image'   => ['nullable', 'string'],
            'is_archived'   => ['sometimes', 'boolean'],
            'board_list_id' => ['sometimes', 'exists:board_lists,id'],
            'status'        => ['sometimes', 'string'],
            'created_by'    => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'content_public_date' => ['sometimes', 'nullable', 'date'],
            'smm_class_label' => ['sometimes', 'nullable', 'string'],
            'smm_cluster_label' => ['sometimes', 'nullable', 'string', 'max:100'],
            'smm_team_label'  => ['sometimes', 'nullable', 'string', 'max:100'],
            'team'            => ['sometimes', 'nullable', 'string'],
        ]);

        if (array_key_exists('team', $validated)) {
            if (!empty($validated['team'])) {
                $t = strtoupper(trim($validated['team']));
                if (in_array($t, ['BOTH', 'A,B', 'A, B', 'A&B', 'A & B', 'ALL', 'A+B', 'TEAM A & B', 'TEAM A & TEAM B']) || (str_contains($t, 'A') && str_contains($t, 'B'))) {
                    $validated['team'] = 'Both';
                } elseif ($t === 'A' || $t === 'B') {
                    $validated['team'] = $t;
                } else {
                    $validated['team'] = $validated['team'];
                }
            } else {
                $validated['team'] = null;
            }
        } elseif (array_key_exists('created_by', $validated) && !empty($validated['created_by']) && empty($card->team)) {
            $cUser = User::find($validated['created_by']);
            if ($cUser) {
                $cuUname = strtolower($cUser->username ?? '');
                $cuName = strtolower($cUser->name ?? '');
                if (str_contains($cuUname, 'kim') || str_contains($cuName, 'kim') || $cUser->id === 13) {
                    $validated['team'] = 'B';
                } elseif (str_contains($cuUname, 'dara') || str_contains($cuName, 'dara') || $cUser->id === 12) {
                    $validated['team'] = 'A';
                }
            }
        }

        $originalBoardId = $card->board_id;
        $originalListId = $card->board_list_id;
        $oldValues = $card->only(array_keys($validated));
        $card->update($validated);

        // Sync team assignment to all twins in the same sync group
        if (array_key_exists('team', $validated) && $card->sync_group_id) {
            Card::where('sync_group_id', $card->sync_group_id)
                ->where('id', '!=', $card->id)
                ->update(['team' => $validated['team']]);
        }

        $card->load('assignees:id,name,avatar', 'labels', 'boardList.board');

        // Log what actually changed
        foreach ($validated as $key => $val) {
            $old = $oldValues[$key] ?? null;
            if ((string)$old === (string)$val) continue;

            match ($key) {
                'due_at'      => $this->logCardActivity($card, 'due_changed',
                    $val ? "changed due date to **{$val}**" : 'removed due date'),
                'start_date'  => $this->logCardActivity($card, 'start_changed',
                    $val ? "set start date to **{$val}**" : 'removed start date'),
                'due_time'    => $this->logCardActivity($card, 'due_time_changed',
                    $val ? "set due time to **{$val}**" : 'removed due time'),
                'reminder'    => $this->logCardActivity($card, 'reminder_changed',
                    "set reminder to **{$val} minutes before**"),
                'recurring'   => $this->logCardActivity($card, 'recurring_changed',
                    "set recurring to **{$val}**"),
                'priority'    => $this->logCardActivity($card, 'priority_changed',
                    "changed priority to **{$val}**"),
                'description' => ($this->normalizeDescriptionContent($old) !== $this->normalizeDescriptionContent($val))
                    ? $this->logCardActivity($card, 'desc_changed', 'updated card description')
                    : null,
                'title'       => $this->logCardActivity($card, 'title_changed',
                    "renamed card to **{$val}**"),
                'created_by'  => $this->logCardActivity($card, 'creator_changed',
                    "changed Assigned By user"),
                'smm_class_label' => $this->logCardActivity($card, 'smm_class_changed',
                    "changed SMM Class to **{$val}**"),
                'smm_cluster_label' => $this->logCardActivity($card, 'smm_cluster_changed',
                    $val ? "changed Content Type to **{$val}**" : 'cleared Content Type'),
                'content_public_date' => $this->logCardActivity($card, 'content_public_date_changed',
                    "changed Public Date to **{$val}**"),
                'team'        => $this->logCardActivity($card, 'team_changed',
                    $val ? ($val === 'Both' ? "assigned card to **Team A & B (Both Teams)**" : "assigned card to **Team {$val}**") : 'removed team assignment'),
                default       => null,
            };
        }

        // Notify assignees when due date changes
        if (array_key_exists('due_at', $validated) && (string)($oldValues['due_at'] ?? '') !== (string)$validated['due_at']) {
            dispatch(function () use ($card) {
                foreach ($card->assignees as $assignee) {
                    if ($assignee->id !== auth()->id()) {
                        $assignee->notify(new \App\Notifications\GenericDatabaseNotification([
                            'actor_id'     => auth()->id(),
                            'actor_name'   => auth()->user()->name,
                            'actor_avatar' => auth()->user()->avatar_url,
                            'module'       => 'digital',
                            'message'      => auth()->user()->name . " updated the due date on card '{$card->title}'",
                            'link'         => route('boards.show', $card->board->slug),
                        ]));
                    }
                }
            })->afterResponse();
        }

        $titleChanged = array_key_exists('title', $validated) && (string)($oldValues['title'] ?? '') !== (string)$validated['title'];

        $this->checkAutomations($card, null, null, $titleChanged);

        $cardMoved = $card->board_id !== $originalBoardId || $card->board_list_id !== $originalListId;

        if ($cardMoved && $card->boardList) {
            app(\App\Services\BoardWorkflowService::class)->syncPlanningWeekList($card, $card->boardList);
        }

        // Auto-distribute SMM card to Team Planning Board if board is SMM
        $board = $card->board;
        $isSmmBoard = $board && (
            $board->type === 'smm'
            || !empty($board->is_active_smm)
            || stripos($board->name ?? '', 'smm') !== false
            || stripos($board->workspace?->name ?? '', 'social media') !== false
        );

        if ($isSmmBoard && ($request->filled('smm_team_label') || $request->filled('label') || ($titleChanged && empty($card->sync_group_id)))) {
            try {
                $teamLabel = $request->input('smm_team_label') ?: $request->input('label');
                app(\App\Services\BoardWorkflowService::class)->distributeSmmCardToTeam($card, $teamLabel);
            } catch (\Throwable $e) {
                \Log::warning("Error auto-distributing SMM card on update: " . $e->getMessage());
            }
        }

        return response()->json([
            'card' => $this->formatCardForBoard($card), 
            'message' => 'Card updated.',
            'card_moved' => $cardMoved
        ]);
    }

    public function checkAutomations(Card $card, ?int $movedToListId = null, ?string $commentText = null, bool $titleChanged = false): ?array
    {
        $automations = \App\Models\BoardAutomation::where('trigger_board_id', $card->board_id)
            ->orWhere(function($q) use ($card) {
                $q->whereNull('trigger_board_id')->where('board_id', $card->board_id);
            })->get();
            
        if ($automations->isEmpty()) return null;

        $automations = $automations->sortByDesc(function ($automation) {
            return strlen($automation->trigger_word ?? '');
        });

        $triggeredKeyword = null;

        foreach ($automations as $automation) {
            $isMatch = false;
            
            // Check list condition first if specified
            if ($automation->trigger_list_id) {
                // If it's a move event, compare against movedToListId. Otherwise, check current list.
                $currentListId = $movedToListId ?? $card->board_list_id;
                if ($currentListId !== $automation->trigger_list_id) {
                    continue; // List doesn't match, skip to next rule
                }
            }

            // If a trigger word is specified, we check either the new comment text OR the card title (only if title changed)
            if ($automation->trigger_word) {
                if ($triggeredKeyword !== null && strtolower($automation->trigger_word) !== strtolower($triggeredKeyword)) {
                    continue; // Skip less specific trigger words
                }

                $wordMatch = false;
                if ($commentText) {
                    $ruleTrigger = trim($automation->trigger_word);
                    $wordMatch = stripos($commentText, $ruleTrigger) !== false;

                    // Support synonym matching: if rule is "Rejected", also match "Blocked" / "Block"
                    if (!$wordMatch && (strcasecmp($ruleTrigger, 'rejected') === 0 || strcasecmp($ruleTrigger, 'blocked') === 0)) {
                        $lowerComment = strtolower($commentText);
                        if (str_contains($lowerComment, 'reject') || str_contains($lowerComment, 'block')) {
                            $wordMatch = true;
                        }
                    }

                    // If trigger word is "approved", do not match if it's "qc approved", "head approved", "team approved", "production approved", or "approved smm"
                    if ($wordMatch && strcasecmp($ruleTrigger, 'approved') === 0) {
                        $lowerComment = strtolower($commentText);
                        if (str_contains($lowerComment, 'qc approved') || str_contains($lowerComment, 'head approved') || str_contains($lowerComment, 'team approved') || str_contains($lowerComment, 'production approved') || str_contains($lowerComment, 'approved smm')) {
                            $wordMatch = false;
                        }
                    }

                    // If trigger word is "production approved", do not match if it's "production approved smm"
                    if ($wordMatch && strcasecmp($ruleTrigger, 'production approved') === 0) {
                        $lowerComment = strtolower($commentText);
                        if (str_contains($lowerComment, 'production approved smm')) {
                            $wordMatch = false;
                        }
                    }
                }
                if (!$wordMatch && $titleChanged) {
                    $wordMatch = stripos($card->title, $automation->trigger_word) !== false;
                }
                
                if ($wordMatch) {
                    $isMatch = true;
                }
            } else {
                // If NO trigger word is specified, and it matched the list condition above, it's a match!
                // But only trigger if it was just MOVED to the list (not just updated for some other reason)
                if ($automation->trigger_list_id && $movedToListId !== null) {
                    $isMatch = true;
                }
            }

            if ($isMatch) {
                // CUSTOM DGT WORKFLOW CONSTRAINTS
                if ($commentText) {
                    $trigger = strtolower($automation->trigger_word ?? '');
                    $currentUser = auth()->user();
                    $isAdmin = $currentUser && ($currentUser->hasAnyRole(['super-admin', 'admin-digital', 'admin']) || $currentUser->isSupervisorRole());
                    $isQcUser = $currentUser && ($currentUser->isQc() || $currentUser->hasRole('qc') || stripos($currentUser->team_role ?? '', 'qc') !== false || stripos($currentUser->name ?? '', 'dara') !== false);
                    $isSupervisorUser = $currentUser && ($currentUser->isSupervisorRole() || $currentUser->hasRole('supervisor') || stripos($currentUser->team_role ?? '', 'supervisor') !== false);
                    $isDara = $currentUser && (str_contains(strtolower($currentUser->username ?? ''), 'dara') || str_contains(strtolower($currentUser->name ?? ''), 'dara') || in_array($currentUser->id, [12, 24]));
                    $isKim = $currentUser && (str_contains(strtolower($currentUser->username ?? ''), 'kim') || str_contains(strtolower($currentUser->name ?? ''), 'kim') || $currentUser->id === 13);
                    $isDigitalDeptUser = $currentUser && ($currentUser->hasRole('digital-team') || $isSupervisorUser || $isAdmin || in_array($currentUser->id, [1, 2, 5, 12, 13, 24]));

                    if ($card->hasIncompleteChecklist()) {
                        continue;
                    }

                    if (str_contains($trigger, 'ready')) {
                        $assignees = $card->assignees;
                        if (!$isAdmin && $assignees->count() > 0 && !$assignees->contains('id', $currentUser?->id)) continue; // Commenter not assigned
                    }

                    if (str_contains($trigger, 'production approved smm')) {
                        if (!$isAdmin && !$isSupervisorUser && !$isDara && !$isKim && !$isQcUser) continue;
                    } elseif (str_contains($trigger, 'production approved')) {
                        if (!$isAdmin && !$isSupervisorUser && !$isDara && !$isKim) continue;
                    }
                    if (str_contains($trigger, 'head approved')) {
                        if (!$isAdmin && stripos($currentUser?->team_role ?? '', 'head') === false && !$isDara && !$isKim) continue;
                    }
                    if (str_contains($trigger, 'qc approved') || str_contains($trigger, 'error')) {
                        if (!$isAdmin && !$isQcUser && !$isDara) continue;
                    }
                    if (str_contains($trigger, 'approved smm')) {
                        if (!$isAdmin && !$isQcUser && !$isDara && !$isKim) continue;
                    }
                    if (((str_contains($trigger, 'approved') && !str_contains($trigger, 'head') && !str_contains($trigger, 'qc') && !str_contains($trigger, 'team') && !str_contains($trigger, 'production') && !str_contains($trigger, 'smm')) || str_contains($trigger, 'rejected') || str_contains($trigger, 'blocked'))) {
                        if (!$isAdmin && !$isSupervisorUser && !$isDigitalDeptUser) continue;
                    }
                    if (str_contains($trigger, 'team approved')) {
                        // "Team approved" can be triggered by any team member or assignee
                    }
                }

                // Passed role constraints: record matched keyword
                $triggeredKeyword = $automation->trigger_word;

                $sourceBoardName = $card->board?->name ?? 'Unknown board';
                $sourceListName = $card->boardList?->name ?? 'Unknown list';
                $reason = 'automation rule';
                if ($automation->trigger_type === 'keyword') $reason = "keyword '{$automation->trigger_word}'";
                elseif ($automation->trigger_type === 'list') $reason = "moving to list";
                elseif ($automation->trigger_type === 'both') $reason = "moving to list and keyword '{$automation->trigger_word}'";

                $isSmmBoard = $card->board?->type === 'smm'
                    || !empty($card->board?->is_active_smm)
                    || stripos($card->board?->name ?? '', 'smm') !== false
                    || stripos($card->board?->workspace?->name ?? '', 'social media') !== false;

                // DYNAMIC MONTH DETECTOR: Ensure it copies to the matching month's workflow board
                $targetBoard = \App\Models\Board::find($automation->target_board_id);

                // WORKSPACE & WORKFLOW ROUTING INTEGRITY:
                // If copying or transitioning from a team planning board (not SMM),
                // the workflow board MUST be strictly within the exact same workspace and match the card's Team (A or B)!
                if (!$isSmmBoard && stripos($card->board?->name ?? '', 'planning') !== false) {
                    $cardTeam = $card->team ?: $card->detectTeam();
                    $targetTeamA = $targetBoard && (str_contains(strtolower($targetBoard->name), 'team a') || str_contains(strtolower($targetBoard->name), 'teama'));
                    $targetTeamB = $targetBoard && (str_contains(strtolower($targetBoard->name), 'team b') || str_contains(strtolower($targetBoard->name), 'teamb'));
                    $teamMismatch = ($cardTeam === 'A' && $targetTeamB) || ($cardTeam === 'B' && $targetTeamA);

                    if (!$targetBoard || (int)$targetBoard->workspace_id !== (int)$card->board->workspace_id || $teamMismatch) {
                        $workflowBoard = app(\App\Services\BoardWorkflowService::class)->findWorkflowBoardForCard($card);
                        if ($workflowBoard) {
                            $targetBoard = $workflowBoard;
                            $automation->target_board_id = $workflowBoard->id;
                            $draftList = $workflowBoard->lists()->where('name', 'like', '%Draft%')->first()
                                ?? $workflowBoard->lists()->orderBy('position')->first();
                            if ($draftList) {
                                $automation->target_list_id = $draftList->id;
                            }
                        }
                    }
                }

                if ($targetBoard && $card->board) {
                    $scopeWorkspaceId = !$isSmmBoard ? $card->board->workspace_id : $targetBoard->workspace_id;
                    if (preg_match('/(January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{4}/i', $card->board->name, $currMatch)) {
                        if (preg_match('/(January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{4}/i', $targetBoard->name, $tgtMatch)) {
                            $currentMonth = $currMatch[0];
                            $targetMonth = $tgtMatch[0];
                            if (strtolower($currentMonth) !== strtolower($targetMonth)) {
                                $baseTargetNameClean = trim(preg_replace('/[^a-zA-Z0-9\s]/', '', str_ireplace($targetMonth, '', $targetBoard->name)));
                                $baseTargetNameClean = preg_replace('/\s+/', ' ', $baseTargetNameClean);

                                $dynamicTargetBoard = null;
                                $workspaceBoards = \App\Models\Board::where('workspace_id', $scopeWorkspaceId)->where('is_archived', false)->get();
                                foreach ($workspaceBoards as $wb) {
                                    if (stripos($wb->name, $currentMonth) !== false) {
                                        $wbBaseNameClean = trim(preg_replace('/[^a-zA-Z0-9\s]/', '', str_ireplace($currentMonth, '', $wb->name)));
                                        $wbBaseNameClean = preg_replace('/\s+/', ' ', $wbBaseNameClean);
                                        if (strtolower($wbBaseNameClean) === strtolower($baseTargetNameClean)) {
                                            $dynamicTargetBoard = $wb;
                                            break;
                                        }
                                    }
                                }

                                if (!$dynamicTargetBoard && !$isSmmBoard) {
                                    $dynamicTargetBoard = app(\App\Services\BoardWorkflowService::class)->findWorkflowBoardForCard($card);
                                }

                                if ($dynamicTargetBoard) {
                                    $targetBoard = $dynamicTargetBoard;
                                    $automation->target_board_id = $dynamicTargetBoard->id;
                                    $targetList = \App\Models\BoardList::find($automation->target_list_id);
                                    if ($targetList) {
                                        $dynamicTargetList = \App\Models\BoardList::where('board_id', $dynamicTargetBoard->id)
                                            ->whereRaw('TRIM(LOWER(name)) = ?', [trim(strtolower($targetList->name))])->first();
                                        if ($dynamicTargetList) {
                                            $automation->target_list_id = $dynamicTargetList->id;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // Do not allow copy or move automations if card has an incomplete checklist
                if (in_array($automation->action_type, ['copy', 'move']) && $card->hasIncompleteChecklist()) {
                    \Log::info("Automation #{$automation->id} ({$automation->action_type}) blocked on Card #{$card->id} due to incomplete checklist.");
                    continue;
                }

                if ($automation->action_type === 'copy') {
                    if ($card->hasIncompleteChecklist()) {
                        continue;
                    }

                    // Absolute security constraint: Never allow copying across workspaces from team boards
                    if (!$isSmmBoard && $targetBoard && (int)$targetBoard->workspace_id !== (int)$card->board->workspace_id) {
                        \Log::warning("Blocked cross-workspace card copy attempt: Card #{$card->id} on Board #{$card->board_id} (WS #{$card->board->workspace_id}) to Board #{$targetBoard->id} (WS #{$targetBoard->workspace_id})");
                        continue;
                    }

                    // Ensure target list belongs to target board
                    $targetList = \App\Models\BoardList::find($automation->target_list_id);
                    if (!$targetList || (int)$targetList->board_id !== (int)$automation->target_board_id) {
                        $targetList = $targetBoard?->lists()->where('name', 'like', '%Draft%')->first()
                            ?? $targetBoard?->lists()->orderBy('position')->first();
                        if ($targetList) {
                            $automation->target_list_id = $targetList->id;
                        }
                    }

                    // Prevent duplicate copies if card has already been replicated to the target board
                    if ($card->sync_group_id) {
                        $alreadyCopied = \App\Models\Card::where('sync_group_id', $card->sync_group_id)
                            ->where('board_id', $automation->target_board_id)
                            ->exists();
                        if ($alreadyCopied) {
                            return [
                                'triggered' => true,
                                'rule_id' => $automation->id,
                                'trigger_type' => $automation->trigger_type,
                                'action_type' => $automation->action_type,
                                'target_board_id' => $automation->target_board_id,
                                'target_list_id' => $automation->target_list_id,
                                'reason' => 'already copied',
                            ];
                        }
                    }

                    $isSameBoard = (int)$automation->target_board_id === (int)$card->board_id;
                    $newTitle = $isSameBoard ? $card->title . ' (copy)' : $card->title;
                    
                    $copy = $card->replicateRelationally($automation->target_board_id, $automation->target_list_id, $newTitle, $card->created_by, true);

                    $this->logCardActivity($copy, 'copied_by_automation', "copied this card from **{$sourceListName}**");
                    // Retain original members. Removed logic that automatically adds more members based on target_assignee_role.
                } else {
                    $targetBoard = \App\Models\Board::find($automation->target_board_id);
                    $targetList = \App\Models\BoardList::find($automation->target_list_id);
                    if (!$targetList) {
                        continue;
                    }
                    
                    // Shift all existing cards in the target list down to make room at position 0 (top)
                    \App\Models\Card::where('board_list_id', $automation->target_list_id)
                        ->where('id', '!=', $card->id)
                        ->increment('position');

                    $card->update([
                        'board_id' => $automation->target_board_id,
                        'board_list_id' => $automation->target_list_id,
                        'position' => 0,
                    ]);
                    $card->unsetRelation('board');
                    $card->unsetRelation('boardList');
                    
                    if (str_contains(strtolower($targetList->name), 'block/waiting')) {
                        app(\App\Services\BoardWorkflowService::class)->syncListStateAcrossBoards($card, 'Block/Waiting');
                    } elseif (str_contains(strtolower($targetList->name), 'approved')) {
                        $card->update([
                            'status' => 'approved',
                            'approved_at' => $card->approved_at ?? now()
                        ]);
                        app(\App\Services\BoardWorkflowService::class)->syncListStateAcrossBoards($card, 'Approved');
                    }

                    $this->logCardActivity(
                        $card,
                        'moved_by_automation',
                        "moved this card from **{$sourceListName}** to **" . ($targetList->name ?? 'Unknown list') . "**"
                    );

                    // Retain original members. Removed logic that automatically adds more members based on target_assignee_role.
                }

                return [
                    'triggered' => true,
                    'rule_id' => $automation->id,
                    'trigger_type' => $automation->trigger_type,
                    'action_type' => $automation->action_type,
                    'target_board_id' => $automation->target_board_id,
                    'target_list_id' => $automation->target_list_id,
                    'reason' => $reason,
                ]; // Only apply the first matching rule to avoid loops or conflicts
            }
        }

        return null;
    }

    /** Soft-delete a card (move to Trash). */
    public function destroy(Card $card): JsonResponse
    {
        $user = auth()->user();

        // Ensure board_id is set so it consistently appears in this board's trash
        if (!$card->board_id && $card->board_list_id) {
            $card->board_id = $card->boardList?->board_id;
            $card->save();
        }

        $this->logCardActivity($card, 'deleted', "moved card '{$card->title}' to Trash");
        $card->delete();

        // Also cascade soft-delete to any twin cards in the same sync group
        if ($card->sync_group_id) {
            $syncedCards = Card::where('sync_group_id', $card->sync_group_id)
                ->where('id', '!=', $card->id)
                ->get();
            foreach ($syncedCards as $twin) {
                if (!$twin->board_id && $twin->board_list_id) {
                    $twin->board_id = $twin->boardList?->board_id;
                    $twin->save();
                }
                $twin->delete();
            }
        }

        return response()->json(['message' => 'Card moved to Trash.']);
    }

    /** Toggle a user's membership on a card. */
    public function toggleMember(Request $request, Card $card): JsonResponse
    {
        $request->validate(['user_id' => ['required', 'exists:users,id']]);

        $userId = $request->user_id;
        $user = User::find($userId);

        if ($card->assignees()->where('users.id', $userId)->exists()) {
            $card->assignees()->detach($userId);
            $message = 'Member removed from card.';
            $this->logCardActivity($card, 'member_removed', "removed **{$user->name}** from card");
        } else {
            $card->assignees()->attach($userId, ['assigned_at' => now()]);
            $message = 'Member added to card.';
            $this->logCardActivity($card, 'member_added', "assigned **{$user->name}** to card");

            // Notify assigned member
            if ($userId !== auth()->id()) {
                dispatch(function () use ($user, $card) {
                    $user->notify(new \App\Notifications\GenericDatabaseNotification([
                        'actor_id'     => auth()->id(),
                        'actor_name'   => auth()->user()->name,
                        'actor_avatar' => auth()->user()->avatar_url,
                        'module'       => 'digital',
                        'message'      => auth()->user()->name . " assigned you to card '{$card->title}'",
                        'link'         => route('boards.show', $card->board->slug)
                    ]));
                })->afterResponse();
            }
        }

        // Sync assignees to other cards in the sync group
        if ($card->sync_group_id) {
            $syncedCards = Card::where('sync_group_id', $card->sync_group_id)->where('id', '!=', $card->id)->get();
            $assigneeIds = $card->assignees()->pluck('users.id')->toArray();
            foreach ($syncedCards as $syncedCard) {
                $syncedCard->assignees()->sync($assigneeIds);
            }
        }

        return response()->json([
            'message'   => $message,
            'assignees' => $card->assignees()->get()->map(fn($u) => [
                'id'     => $u->id,
                'name'   => $u->name,
                'email'  => $u->email,
                'avatar' => $u->avatar_url,
                'initials' => $u->avatar_initials,
                'avatar_color' => $u->avatar_color,
            ]),
        ]);
    }

    /** Toggle a label on a card. */
    public function toggleLabel(Request $request, Card $card): JsonResponse
    {
        $request->validate(['label_id' => ['required', 'exists:labels,id']]);

        $labelId = $request->label_id;
        $label = \App\Models\Label::find($labelId);

        if ($card->labels()->where('labels.id', $labelId)->exists()) {
            $card->labels()->detach($labelId);
            $message = 'Label removed.';
            $this->logCardActivity($card, 'label_removed', "removed label **{$label->name}**");
        } else {
            $card->labels()->attach($labelId);
            $message = 'Label added.';
            $this->logCardActivity($card, 'label_added', "added label **{$label->name}**");

            // Auto-distribute SMM card to Team Planning Board only if card is on SMM board
            try {
                $board = $card->board;
                $isSmmBoard = $board && (
                    $board->type === 'smm'
                    || !empty($board->is_active_smm)
                    || stripos($board->name ?? '', 'smm') !== false
                    || stripos($board->workspace?->name ?? '', 'social media') !== false
                );
                if ($isSmmBoard) {
                    app(\App\Services\BoardWorkflowService::class)->distributeSmmCardToTeam($card, $label->name);
                }
            } catch (\Throwable $e) {
                \Log::warning("Error auto-distributing SMM card on label toggle: " . $e->getMessage());
            }
        }

        // Sync labels to other cards in the sync group
        if ($card->sync_group_id) {
            $syncedCards = Card::where('sync_group_id', $card->sync_group_id)->where('id', '!=', $card->id)->get();
            $labelIds = $card->labels()->pluck('labels.id')->toArray();
            foreach ($syncedCards as $syncedCard) {
                $syncedCard->labels()->sync($labelIds);
            }
        }

        $refreshed = $card->fresh(['assignees:id,name,avatar', 'labels', 'checklists.items', 'files', 'comments']);

        return response()->json([
            'message'        => $message,
            'labels'         => $refreshed->labels,
            'smm_team_label' => $refreshed->smm_team_label,
            'card'           => $this->formatCardForBoard($refreshed),
        ]);
    }

    /** Move a card to a different list (and optionally a different position). */
    public function move(Request $request, Card $card): JsonResponse
    {
        $request->validate([
            'board_list_id' => ['required', 'exists:board_lists,id'],
            'source_list_id' => ['nullable', 'exists:board_lists,id'],
            'position'      => ['nullable', 'integer', 'min:0'],
        ]);

        // Drag-and-drop persists the visual order first, so the card may already point
        // at the target list here. The client supplies the real source list explicitly.
        $sourceList = $request->filled('source_list_id')
            ? BoardList::find((int) $request->source_list_id)
            : $card->boardList;
        $oldList = $sourceList?->name ?? 'Unknown';
        $targetList = BoardList::findOrFail((int) $request->board_list_id);

        $card->loadMissing('assignees:id');
        if (! $this->canMoveCard(auth()->user(), $card, $sourceList, $targetList)) {
            return response()->json([
                'error' => 'You can only move cards assigned to you. Blocked cards can only be moved by supervisors.',
            ], 403);
        }

        // Checklist completion enforcement: All items must be 100% completed before moving to another list
        $isMovingList = (int) ($sourceList?->id ?? $card->board_list_id) !== (int) $targetList->id;
        if ($isMovingList && $card->hasIncompleteChecklist()) {
            return response()->json([
                'error' => 'All checklist items must be 100% completed before moving this card to another list.',
                'checklist_incomplete' => true,
            ], 422);
        }

        // SMM Planning Board: Final Captions enforcement
        if (str_contains(strtolower($targetList->name), 'final captions')) {
            $hasCaption = $card->comments()->where(function($q) {
                $q->where('content', 'like', '%caption ready%')
                  ->orWhere('content', 'like', '%http%');
            })->exists();
            
            if (!$hasCaption) {
                return response()->json([
                    'error' => 'You must add a comment with "Caption Ready" or a link before moving to Final Captions.',
                ], 403);
            }
        }

        if (!$request->has('position')) {
            Card::where('board_list_id', $targetList->id)
                ->where('id', '!=', $card->id)
                ->increment('position');
        }

        $sourceBoard = $card->board ?? \App\Models\Board::find($card->board_id);
        $targetBoard = $targetList->board ?? \App\Models\Board::find($targetList->board_id);
        $isCrossBoard = $sourceBoard && $targetBoard && (int) $targetList->board_id !== (int) $sourceBoard->id;

        $card->update([
            'board_id'      => $targetList->board_id,
            'board_list_id' => $targetList->id,
            'position'      => $request->position ?? 0,
        ]);
        $newList = $targetList->name;

        if ($isCrossBoard) {
            $this->logCardActivity($card, 'moved', "moved this card from **{$oldList}** on **{$sourceBoard->name}** to **{$newList}**");
        } else {
            $this->logCardActivity($card, 'moved', "moved this card from **{$oldList}** to **{$newList}**");
        }

        if (str_contains(strtolower($newList), 'approved')) {
            $card->update([
                'status' => 'approved',
                'approved_at' => $card->approved_at ?? now()
            ]);
            app(\App\Services\BoardWorkflowService::class)->syncListStateAcrossBoards($card, 'Approved');
        } elseif (str_contains(strtolower($oldList), 'approved') && !str_contains(strtolower($newList), 'approved')) {
            $card->update([
                'status' => 'todo',
                'approved_at' => null
            ]);
            app(\App\Services\BoardWorkflowService::class)->syncListStateAcrossBoards($card, 'Todo');
        }

        // Sync week/planning list across twin cards on planning boards
        app(\App\Services\BoardWorkflowService::class)->syncPlanningWeekList($card, $targetList);

        $automation = $this->checkAutomations($card, $targetList->id);

        return response()->json([
            'card' => $this->formatCardForBoard($card),
            'message' => 'Card moved.',
            'automation' => $automation,
            'automation_triggered' => $automation !== null,
        ]);
    }

    /** Duplicate a card into the same list (or an optionally supplied list). */
    public function copy(Request $request, Card $card): JsonResponse
    {
        // Checklist completion enforcement: All items must be 100% completed before copying
        if ($card->hasIncompleteChecklist()) {
            return response()->json([
                'message' => 'All checklist items must be 100% completed before copying this card.',
                'checklist_incomplete' => true,
            ], 422);
        }

        $request->validate([
            'target_board_id' => ['nullable', 'exists:boards,id'],
            'board_list_id' => ['nullable', 'exists:board_lists,id'],
            'title'         => ['nullable', 'string', 'max:255'],
        ]);

        $targetList = null;
        if ($request->filled('board_list_id')) {
            $targetList = BoardList::findOrFail((int) $request->board_list_id);
        }

        $targetBoardId = (int) ($request->target_board_id ?? $targetList?->board_id ?? $card->board_id);
        $targetBoard = Board::findOrFail($targetBoardId);

        if ($targetList && (int) $targetList->board_id !== (int) $targetBoard->id) {
            return response()->json([
                'message' => 'Selected list does not belong to selected board.',
            ], 422);
        }

        if (! $targetList) {
            $targetList = $targetBoard->activeLists()->orderBy('position')->first();
        }

        if (! $targetList) {
            return response()->json([
                'message' => 'Target board has no active list to copy card into.',
            ], 422);
        }

        $targetListId = $targetList->id;

        $sourceBoard = $card->board ?? \App\Models\Board::find($card->board_id);
        $sourceList = $card->boardList ?? \App\Models\BoardList::find($card->board_list_id);
        $sourceBoardName = $sourceBoard?->name ?? 'board';
        $sourceListName = $sourceList?->name ?? 'list';
        $targetListName = $targetList->name;
        $isCrossBoard = $sourceBoard && (int) $targetBoard->id !== (int) $sourceBoard->id;

        $copy = $card->replicateRelationally($targetBoard->id, $targetListId, $request->title, $card->created_by, true);

        // We no longer sync Block/Waiting across boards
        if (str_contains(strtolower($targetList->name), 'approved')) {
            app(\App\Services\BoardWorkflowService::class)->syncListStateAcrossBoards($copy, 'Approved');
        }

        if ($isCrossBoard) {
            $this->logCardActivity(
                $copy,
                'copied',
                "copied this card from **{$sourceListName}** on **{$sourceBoardName}** to **{$targetListName}**"
            );
        } elseif ($sourceList && (int) $sourceList->id === (int) $targetList->id) {
            $this->logCardActivity(
                $copy,
                'copied',
                "copied this card from **{$sourceListName}**"
            );
        } else {
            $this->logCardActivity(
                $copy,
                'copied',
                "copied this card from **{$sourceListName}** to **{$targetListName}**"
            );
        }

        return response()->json([
            'card'    => $this->formatCardForBoard($copy),
            'message' => "Card copied as \"" . $copy->title . "\".",
        ], 201);
    }

    public function toggleApprove(Card $card, \App\Services\KanbanService $kanbanService): JsonResponse
    {
        $this->authorize('approve', $card);

        $card = $kanbanService->toggleApproveCard($card, auth()->user());

        $status = $card->status->value === 'approved' ? 'approved' : 'unapproved';
        return response()->json([
            'success' => true,
            'message' => "Task {$status}! Notifications sent.",
            'card'    => $card,
        ]);
    }

    /**
     * Mark a blocked card as fixed/completed (Supervisor only)
     */
    public function completeBlock(Card $card): JsonResponse
    {
        $user = auth()->user();
        $card->loadMissing('boardList');

        if (! $this->canManageBlockedCards($user) || ! $this->isBlockList($card->boardList?->name)) {
            return response()->json([
                'error' => 'Only supervisors can complete blocked cards.',
            ], 403);
        }

        $isCompleted = ! empty($card->block_completed_at);
        $card->update([
            'block_completed_at' => $isCompleted ? null : now(),
            'block_completed_by' => $isCompleted ? null : $user->id,
        ]);

        $message = $isCompleted
            ? 'marked this blocked card as not fixed'
            : 'marked this blocked card as fixed';

        $this->logCardActivity($card, $isCompleted ? 'block_reopened' : 'block_completed', $message);

        return response()->json([
            'card' => $this->formatCardForBoard($card),
            'message' => $isCompleted ? 'Blocked card marked not fixed.' : 'Blocked card marked complete.',
        ]);
    }

    // ── Checklists ────────────────────────────────────────────────────────────

    /** Create a new checklist group on a card */
    public function storeChecklist(Request $request, Card $card): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $checklist = $card->checklists()->create([
            'title'    => $validated['title'],
            'position' => $card->checklists()->count() + 1,
        ]);

        $this->logCardActivity($card, 'checklist_created', "created checklist **{$checklist->title}**");

        return response()->json([
            'success'   => true,
            'checklist' => $checklist->load('items'),
        ], 201);
    }

    /** Delete a checklist group */
    public function destroyChecklist(Card $card, CardChecklist $checklist): JsonResponse
    {
        $this->logCardActivity($card, 'checklist_deleted', "deleted checklist **{$checklist->title}**");
        $checklist->delete();
        return response()->json(['success' => true]);
    }

    /** Update a checklist group */
    public function updateChecklist(Request $request, Card $card, CardChecklist $checklist): JsonResponse
    {
        $validated = $request->validate([
            'title'               => ['required', 'string', 'max:255'],
            'assigned_user_id'    => ['nullable'],
            'assigned_user_ids'   => ['nullable', 'array'],
            'assigned_user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        if (!empty($validated['assigned_user_id']) && is_numeric($validated['assigned_user_id'])) {
            $request->validate([
                'assigned_user_id' => ['exists:users,id'],
            ]);
        }

        $user = auth()->user();
        $isHead = $user && ($user->isDaraOrKim() || \App\Models\CardChecklistItem::canReviewMark($user));

        $hasAssignment = $request->has('assigned_user_id') || $request->has('assigned_user_ids');
        if ($hasAssignment && !$isHead) {
            return response()->json([
                'success' => false,
                'message' => 'Only Mr. Dara and Mr. Kim (Heads) can bulk assign checklist items.',
            ], 403);
        }

        $oldTitle = $checklist->title;
        $checklist->update(['title' => $validated['title']]);

        if ($oldTitle !== $checklist->title) {
            $this->logCardActivity($card, 'checklist_updated', "renamed checklist from **{$oldTitle}** to **{$checklist->title}**");
        }

        if ($hasAssignment && $isHead) {
            $assigneeIds = $this->resolveAssigneeIds($request);
            $primaryId = !empty($assigneeIds) ? $assigneeIds[0] : null;
            $idsArray = !empty($assigneeIds) ? $assigneeIds : null;

            foreach ($checklist->items as $item) {
                $item->update([
                    'assigned_user_id'  => $primaryId,
                    'assigned_user_ids' => $idsArray,
                ]);
            }

            if ($primaryId) {
                $assignedUser = \App\Models\User::find($primaryId);
                $userName = $assignedUser ? $assignedUser->name : "User #{$primaryId}";
                $this->logCardActivity($card, 'checklist_bulk_assigned', "assigned all items in **{$checklist->title}** to **{$userName}**");
            } else {
                $this->logCardActivity($card, 'checklist_bulk_unassigned', "cleared assignees for all items in **{$checklist->title}**");
            }
        }

        return response()->json([
            'success'   => true,
            'checklist' => $checklist->fresh([
                'items.assignedUser',
                'items.completedBy',
                'items.markedBy',
                'items.issueBy',
            ]),
        ]);
    }

    /** Add a checklist item */
    public function storeChecklistItem(Request $request, Card $card, CardChecklist $checklist): JsonResponse
    {
        $validated = $request->validate([
            'title'              => ['required', 'string', 'max:500'],
            'assigned_user_id'   => ['nullable', 'exists:users,id'],
            'assigned_user_ids'  => ['nullable', 'array'],
            'assigned_user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $assigneeIds = $this->resolveAssigneeIds($request);
        if ($assigneeIds === null || empty($assigneeIds)) {
            $detected = CardChecklistItem::detectUserIdForCard($validated['title'], $card);
            if ($detected) {
                $assigneeIds = [$detected];
            }
        }

        $item = $checklist->items()->create([
            'content'           => $validated['title'],
            'position'          => $checklist->items()->count() + 1,
            'is_completed'      => false,
            'assigned_user_id'  => $assigneeIds[0] ?? null,
            'assigned_user_ids' => $assigneeIds ?: null,
        ]);

        $this->logCardActivity($card, 'checklist_item_created', "added item **{$item->content}** to checklist **{$checklist->title}**");

        return response()->json([
            'success' => true,
            'item'    => $item->load('assignedUser'),
        ], 201);
    }

    /**
     * Read the assignee list from the request.
     * Returns null when the request does not mention assignees at all.
     *
     * @return array<int>|null
     */
    private function resolveAssigneeIds(Request $request): ?array
    {
        if ($request->has('assigned_user_ids')) {
            return array_values(array_unique(array_filter(array_map('intval', (array) $request->input('assigned_user_ids')))));
        }
        if ($request->has('assigned_user_id')) {
            $id = $request->input('assigned_user_id');
            return $id ? [(int) $id] : [];
        }
        return null;
    }

    /** Toggle or update checklist item */
    public function toggleChecklistItem(Request $request, Card $card, CardChecklist $checklist, CardChecklistItem $item): JsonResponse
    {
        if ($request->has('title') || $request->has('assigned_user_id') || $request->has('assigned_user_ids')) {
            $request->validate([
                'assigned_user_ids'   => ['nullable', 'array'],
                'assigned_user_ids.*' => ['integer', 'exists:users,id'],
            ]);
            $updateData = [];
            if ($request->has('title')) {
                $oldContent = $item->content;
                $updateData['content'] = $request->title;
                $this->logCardActivity($card, 'checklist_item_updated', "renamed item from **{$oldContent}** to **{$request->title}**");
            }
            $assigneeIds = $this->resolveAssigneeIds($request);
            if ($assigneeIds !== null) {
                $updateData['assigned_user_id']  = $assigneeIds[0] ?? null;
                $updateData['assigned_user_ids'] = $assigneeIds ?: null;
            }
            $item->update($updateData);
        } else {
            // Items assigned to someone can only be ticked by them (or dara / kim / somalika)
            if (! $item->canBeTickedBy(auth()->user())) {
                return response()->json([
                    'success' => false,
                    'message' => 'This task is assigned to another member. Only the assignee, dara, kim or somalika can tick it.',
                ], 403);
            }

            $item->update([
                'is_completed' => ! $item->is_completed,
                'completed_by' => ! $item->is_completed ? auth()->id() : null,
                'completed_at' => ! $item->is_completed ? now() : null,
            ]);

            $status = $item->is_completed ? 'completed' : 'uncompleted';
            $this->logCardActivity($card, 'checklist_item_toggled', "marked item **{$item->content}** as {$status}");
        }

        return response()->json([
            'success' => true,
            'item'    => $item->fresh(['assignedUser']),
            'percent' => $checklist->load('items')->progressPercent(),
        ]);
    }

    /**
     * Review marks on a checklist item.
     *  - mark    : green tick, only dara / kim (Production Team A & B)
     *  - issue   : red cross (error / needs update), only dara / kim
     *  - approve : admins only, confirms a marked / issue-flagged item
     */
    public function reviewChecklistItem(Request $request, Card $card, CardChecklist $checklist, CardChecklistItem $item): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:mark,issue,approve'],
        ]);

        $user = auth()->user();
        $action = $validated['action'];

        if ($action === 'approve') {
            if (! CardChecklistItem::canApprove($user)) {
                return response()->json(['success' => false, 'message' => 'Only admins can approve checklist items.'], 403);
            }
        } elseif (! CardChecklistItem::canReviewMark($user)) {
            return response()->json(['success' => false, 'message' => 'Only Production Team A & B (dara, kim) can mark checklist items.'], 403);
        }

        $reset = [
            'is_marked' => false, 'marked_by' => null, 'marked_at' => null,
            'has_issue' => false, 'issue_by' => null, 'issue_at' => null,
            'is_approved' => false, 'approved_by' => null, 'approved_at' => null,
        ];

        if ($action === 'mark') {
            if ($item->is_marked) {
                $item->update($reset);
                $label = 'removed the mark from';
            } else {
                $item->update(array_merge($reset, ['is_marked' => true, 'marked_by' => $user->id, 'marked_at' => now()]));
                $label = 'marked';
            }
        } elseif ($action === 'issue') {
            if ($item->has_issue) {
                $item->update($reset);
                $label = 'cleared the issue on';
            } else {
                $item->update(array_merge($reset, ['has_issue' => true, 'issue_by' => $user->id, 'issue_at' => now()]));
                $label = 'flagged an issue on';
            }
        } else {
            if ($item->is_approved) {
                // Un-approve: fall back to the plain "marked" state
                $item->update([
                    'is_approved' => false, 'approved_by' => null, 'approved_at' => null,
                ]);
                $label = 'removed approval from';
            } elseif (! $item->is_marked && ! $item->has_issue) {
                return response()->json(['success' => false, 'message' => 'Only marked or issue-flagged items can be approved.'], 422);
            } else {
                $item->update([
                    'is_marked' => true,
                    'marked_by' => $item->marked_by ?: $user->id,
                    'marked_at' => $item->marked_at ?: now(),
                    'has_issue' => false, 'issue_by' => null, 'issue_at' => null,
                    'is_approved' => true, 'approved_by' => $user->id, 'approved_at' => now(),
                ]);
                $label = 'approved';
            }
        }

        $this->logCardActivity($card, 'checklist_item_reviewed', "{$label} item **{$item->content}**", false);

        return response()->json([
            'success' => true,
            'item'    => $item->fresh(['assignedUser', 'markedBy', 'issueBy']),
        ]);
    }

    /** Delete a checklist item */
    public function destroyChecklistItem(Card $card, CardChecklist $checklist, CardChecklistItem $item): JsonResponse
    {
        $this->logCardActivity($card, 'checklist_item_deleted', "removed item **{$item->content}**");
        $item->delete();
        return response()->json(['success' => true]);
    }

    // ── Comments ──────────────────────────────────────────────────────────────

    /** Post a comment */
    public function storeComment(Request $request, Card $card): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string'],
        ]);

        // Checklist completion enforcement: If card has a checklist, all items must be 100% completed before using comment automations (move/copy)
        if ($card->hasIncompleteChecklist() && app(\App\Services\BoardWorkflowService::class)->isAutomationComment($card, $validated['body'])) {
            $msg = preg_match('/\bready\b/i', $validated['body'])
                ? 'All checklist items must be 100% completed before marking this card as ready.'
                : 'All checklist items must be 100% completed before using comment automations to move or copy this card.';
            return response()->json([
                'error' => $msg,
                'message' => $msg,
                'checklist_incomplete' => true,
            ], 422);
        }

        $comment = $card->comments()->create([
            'user_id'   => auth()->id(),
            'content'   => $validated['body'],
            'is_system' => false,
        ]);

        // Card no longer moves to top when commented
        
        // Parse @mentions — match by username OR by name-with-no-spaces (fallback)
        preg_match_all('/(?:^|\s)@([\w.\-]+)/', $validated['body'], $matches);
        if (!empty($matches[1])) {
            dispatch(function () use ($matches, $card, $comment) {
                $usernames = array_unique($matches[1]);
                // Match by username column OR by name with spaces removed (e.g. @Ms.Vouchky → "Ms. Vouchky")
                $mentionedUsers = \App\Models\User::where(function ($q) use ($usernames) {
                    $q->whereIn('username', $usernames);
                    foreach ($usernames as $u) {
                        // Allow "Ms.Vouchky" → "Ms. Vouchky" style matching
                        $q->orWhereRaw("REPLACE(name, ' ', '') = ?", [$u])
                          ->orWhereRaw("REPLACE(name, ' ', '.') = ?", [$u]);
                    }
                })->get();

                $boardMemberUserIds = $card->board->members()->pluck('users.id')->toArray();

                foreach ($mentionedUsers as $mentionedUser) {
                    if (in_array($mentionedUser->id, $boardMemberUserIds) && $mentionedUser->id !== auth()->id()) {
                        $mentionedUser->notify(new \App\Notifications\CardMentionNotification($card, $comment, auth()->user()));
                    }
                }
            })->afterResponse();
        }
            
        $originalBoardId = $card->board_id;
        $originalListId = $card->board_list_id;

        $autoResult = $this->checkAutomations($card, null, $validated['body']);

        // Check workflow service (always handle ready, block, or if no database automation rule triggered)
        if (!$autoResult || stripos($validated['body'], 'ready') !== false || stripos($validated['body'], 'block') !== false) {
            app(\App\Services\BoardWorkflowService::class)->handleCommentTrigger($card, $comment);
        }

        // Reload board relation in case the card was moved to another board by automation
        $card->load('board');

        $cardMoved = $card->board_id !== $originalBoardId || $card->board_list_id !== $originalListId;

        if (!$cardMoved) {
            $plainText = trim(preg_replace('/!\[.*?\]\([^)]+\)/', '', $comment->content));
            $hasImage = preg_match('/!\[.*?\]\([^)]+\)/', $comment->content) || str_contains($comment->content, '<img');

            if ($hasImage) {
                if ($plainText !== '') {
                    $logContent = "added a comment with a screenshot: \"" . \Illuminate\Support\Str::limit($plainText, 100) . "\"";
                } else {
                    $logContent = 'added a comment with a screenshot';
                }
            } else {
                $logContent = "added comment: \"" . \Illuminate\Support\Str::limit($plainText, 100) . "\"";
            }
            $this->logCardActivity($card, 'comment_added', $logContent);

            // Notify card assignees
            dispatch(function () use ($card) {
                foreach ($card->assignees as $assignee) {
                    if ($assignee->id !== auth()->id()) {
                        $assignee->notify(new \App\Notifications\GenericDatabaseNotification([
                            'actor_id'     => auth()->id(),
                            'actor_name'   => auth()->user()->name,
                            'actor_avatar' => auth()->user()->avatar_url,
                            'module'       => 'digital',
                            'message'      => auth()->user()->name . " commented on card '{$card->title}'",
                            'link'         => route('boards.show', $card->board->slug)
                        ]));
                    }
                }
            })->afterResponse();
        }

        $comment->load('user');
        return response()->json([
            'success' => true,
            'comment' => [
                'id'         => $comment->id,
                'body'       => $comment->body ?? $comment->content,
                'content'    => $comment->body ?? $comment->content,
                'user_id'    => $comment->user_id,
                'created_at' => $comment->created_at?->toISOString(),
                'user'       => $comment->user ? [
                    'id'     => $comment->user->id,
                    'name'   => $comment->user->name,
                    'avatar' => $comment->user->avatar_url,
                    'avatar_initials' => $comment->user->avatar_initials,
                    'avatar_color' => $comment->user->avatar_color,
                ] : null,
                'reactions'  => [],
            ],
            'card_moved' => $cardMoved,
            'card' => $this->formatCardForBoard($card),
        ], 201);
    }

    /** Update a comment */
    public function updateComment(Request $request, Card $card, CardComment $comment): JsonResponse
    {
        if ($comment->user_id !== auth()->id() && ! auth()->user()->hasAnyRole(['super-admin', 'admin-digital'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $oldBody = trim((string) ($comment->body ?? $comment->content));
        $comment->update(['content' => $validated['body']]);
        $comment->loadMissing('user');

        $newBody = trim((string) ($comment->body ?? $comment->content));
        $shortBody = \Illuminate\Support\Str::limit($newBody, 100);
        $logContent = str_contains($newBody, '![screenshot](data:image')
            ? 'edited a comment with a screenshot'
            : "edited comment: \"{$shortBody}\"";

        if ($oldBody !== $newBody) {
            $this->logCardActivity($card, 'comment_edited', $logContent);

            dispatch(function () use ($card) {
                foreach ($card->assignees as $assignee) {
                    if ($assignee->id !== auth()->id()) {
                        $assignee->notify(new \App\Notifications\GenericDatabaseNotification([
                            'actor_id'     => auth()->id(),
                            'actor_name'   => auth()->user()->name,
                            'actor_avatar' => auth()->user()->avatar_url,
                            'module'       => 'digital',
                            'message'      => auth()->user()->name . " edited a comment on card '{$card->title}'",
                            'link'         => route('boards.show', $card->board->slug) . "?card={$card->id}",
                        ]));
                    }
                }
            })->afterResponse();
        }
        
        return response()->json([
            'success' => true,
            'comment' => [
                'id'         => $comment->id,
                'body'       => $comment->body ?? $comment->content,
                'content'    => $comment->body ?? $comment->content,
                'user_id'    => $comment->user_id,
                'created_at' => $comment->created_at?->toISOString(),
                'user'       => $comment->user ? [
                    'id'     => $comment->user->id,
                    'name'   => $comment->user->name,
                    'avatar' => $comment->user->avatar_url,
                ] : null,
                'reactions'  => $comment->reactions ? $comment->reactions->map(fn($r) => [
                    'id' => $r->id,
                    'emoji' => $r->emoji,
                    'user_id' => $r->user_id,
                    'user' => $r->user ? [
                        'id' => $r->user->id,
                        'name' => $r->user->name,
                        'avatar_url' => $r->user->avatar_url,
                    ] : null,
                ])->values()->all() : [],
            ],
        ]);
    }

    /** Delete a comment */
    public function destroyComment(Card $card, CardComment $comment): JsonResponse
    {
        if ($comment->user_id !== auth()->id() && ! auth()->user()->hasAnyRole(['super-admin', 'admin-digital'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $commentBody = trim((string) ($comment->body ?? $comment->content));
        $shortBody = \Illuminate\Support\Str::limit($commentBody, 100);
        $logContent = str_contains($commentBody, '![screenshot](data:image')
            ? 'deleted a comment with a screenshot'
            : "deleted comment: \"{$shortBody}\"";

        $comment->delete();

        $this->logCardActivity($card, 'comment_deleted', $logContent);

        dispatch(function () use ($card) {
            foreach ($card->assignees as $assignee) {
                if ($assignee->id !== auth()->id()) {
                    $assignee->notify(new \App\Notifications\GenericDatabaseNotification([
                        'actor_id'     => auth()->id(),
                        'actor_name'   => auth()->user()->name,
                        'actor_avatar' => auth()->user()->avatar_url,
                        'module'       => 'digital',
                        'message'      => auth()->user()->name . " deleted a comment on card '{$card->title}'",
                        'link'         => route('boards.show', $card->board->slug) . "?card={$card->id}",
                    ]));
                }
            }
        })->afterResponse();

        return response()->json(['success' => true]);
    }

    // ── File Uploads / Links ──────────────────────────────────────────────────

    /** Allowed MIME types for card attachments */
    private const ALLOWED_MIMES = [
        // Images
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'image/bmp', 'image/tiff',
        // Documents
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        // Archives
        'application/zip', 'application/x-zip-compressed',
        'application/x-rar-compressed', 'application/x-7z-compressed',
        'application/gzip',
        // Text
        'text/plain', 'text/csv', 'text/markdown',
        // Audio / Video
        'video/mp4', 'video/quicktime', 'video/webm',
        'audio/mpeg', 'audio/wav', 'audio/ogg',
    ];

    /** Upload file or link external URL */
    public function uploadFile(Request $request, Card $card): JsonResponse
    {
        $hasFiles = $request->hasFile('files') || $request->hasFile('file');

        if ($hasFiles) {
            $files = [];
            if ($request->hasFile('files')) {
                $rawFiles = $request->file('files');
                $files = is_array($rawFiles) ? $rawFiles : [$rawFiles];
            } elseif ($request->hasFile('file')) {
                $files = [$request->file('file')];
            }

            if (empty($files)) {
                return response()->json(['error' => 'No files were provided.'], 422);
            }

            $dangerousExt = ['php', 'phtml', 'php3', 'php4', 'php5', 'phar',
                             'exe', 'bat', 'sh', 'py', 'rb', 'pl', 'cgi',
                             'js', 'jsx', 'ts', 'html', 'htm'];

            $isCommentOnly = $request->boolean('comment_only') || $request->comment_only == '1';
            $kanbanService = app(\App\Services\KanbanService::class);
            $globalFolderName = trim((string) $request->input('folder_name', ''));
            $relativePaths = (array) $request->input('relative_paths', []);

            // Auto-create folder_name column if missing in database
            if (!\Illuminate\Support\Facades\Schema::hasColumn('card_files', 'folder_name')) {
                try {
                    \Illuminate\Support\Facades\Schema::table('card_files', function ($table) {
                        $table->string('folder_name')->nullable()->after('original_name')->index();
                    });
                } catch (\Throwable $e) {}
            }

            $uploadedCardFiles = [];

            foreach ($files as $idx => $file) {
                if (!$file->isValid()) {
                    continue;
                }

                // File size check: max 20 MB
                if ($file->getSize() > 20 * 1024 * 1024) {
                    return response()->json([
                        'error' => "File '{$file->getClientOriginalName()}' exceeds the 20 MB limit."
                    ], 422);
                }

                // Block filenames with double extensions
                $originalName = $file->getClientOriginalName();
                if (substr_count($originalName, '.') > 1) {
                    $parts = explode('.', strtolower($originalName));
                    foreach ($parts as $part) {
                        if (in_array($part, $dangerousExt, true)) {
                            return response()->json(['error' => "File '{$originalName}' has an extension that is not allowed."], 422);
                        }
                    }
                }

                // Determine folder name
                $itemFolder = $globalFolderName;
                if (empty($itemFolder) && isset($relativePaths[$idx])) {
                    $rel = str_replace('\\', '/', trim((string) $relativePaths[$idx]));
                    if (str_contains($rel, '/')) {
                        $segs = explode('/', $rel);
                        $itemFolder = $segs[0];
                    }
                }

                $cardFile = $kanbanService->uploadFile($card, $file, auth()->user(), $isCommentOnly, $itemFolder ?: null);
                $uploadedCardFiles[] = $cardFile;
            }

            if (empty($uploadedCardFiles)) {
                return response()->json(['error' => 'No valid files could be uploaded.'], 422);
            }

            if (!$isCommentOnly) {
                if ($globalFolderName) {
                    $this->logCardActivity($card, 'folder_attached', "attached folder **{$globalFolderName}** (" . count($uploadedCardFiles) . " files)");
                } elseif (count($uploadedCardFiles) === 1) {
                    $this->logCardActivity($card, 'file_attached', "attached file **{$uploadedCardFiles[0]->display_name}**");
                } else {
                    $this->logCardActivity($card, 'files_attached', "attached **" . count($uploadedCardFiles) . " files**");
                }

                // Notify assignees
                dispatch(function () use ($card, $uploadedCardFiles, $globalFolderName) {
                    $count = count($uploadedCardFiles);
                    $msgDetail = $globalFolderName ? "folder '{$globalFolderName}' ({$count} files)" : ($count === 1 ? "a file" : "{$count} files");
                    foreach ($card->assignees as $assignee) {
                        if ($assignee->id !== auth()->id()) {
                            $assignee->notify(new \App\Notifications\GenericDatabaseNotification([
                                'actor_id'     => auth()->id(),
                                'actor_name'   => auth()->user()->name,
                                'actor_avatar' => auth()->user()->avatar_url,
                                'module'       => 'digital',
                                'message'      => auth()->user()->name . " attached {$msgDetail} to card '{$card->title}'",
                                'link'         => route('boards.show', $card->board->slug),
                            ]));
                        }
                    }
                })->afterResponse();
            }

            return response()->json([
                'success' => true,
                'files'   => collect($uploadedCardFiles)->map(fn($cf) => $this->filePayload($cf))->values()->all(),
                'file'    => $this->filePayload($uploadedCardFiles[0]),
            ], 201);

        } else {
            $request->validate([
                'link_url'  => ['required', 'url', 'max:2048'],
                'link_name' => ['required', 'string', 'max:255'],
            ]);

            $cardFile = CardFile::create([
                'card_id'       => $card->id,
                'uploaded_by'   => auth()->id(),
                'original_name' => $request->link_name,
                'stored_name'   => $request->link_url,
                'disk'          => 'url',
                'path'          => $request->link_url,
                'mime_type'     => 'link',
                'size'          => 0,
            ]);

            $this->logCardActivity($card, 'link_attached', "attached link **{$request->link_name}**");

            // Notify assignees
            dispatch(function () use ($card) {
                foreach ($card->assignees as $assignee) {
                    if ($assignee->id !== auth()->id()) {
                        $assignee->notify(new \App\Notifications\GenericDatabaseNotification([
                            'actor_id'     => auth()->id(),
                            'actor_name'   => auth()->user()->name,
                            'actor_avatar' => auth()->user()->avatar_url,
                            'module'       => 'digital',
                            'message'      => auth()->user()->name . " added a link to card '{$card->title}'",
                            'link'         => route('boards.show', $card->board->slug),
                        ]));
                    }
                }
            })->afterResponse();

            return response()->json([
                'success' => true,
                'file'    => $this->filePayload($cardFile),
                'files'   => [$this->filePayload($cardFile)],
            ], 201);
        }
    }

    /** Download all files in a folder as a ZIP archive */
    public function downloadFolder(Request $request, Card $card, string $folder)
    {
        $folderName = trim(urldecode($folder));
        $files = $card->files->filter(function ($f) use ($folderName) {
            return $f->folder_name === $folderName || str_starts_with($f->original_name, "{$folderName}/");
        });

        if ($files->isEmpty()) {
            abort(404, 'Folder not found or has no files.');
        }

        if (!class_exists(\ZipArchive::class)) {
            abort(500, 'ZipArchive is not enabled on the server.');
        }

        $zip = new \ZipArchive();
        $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $folderName);
        $tmpFile = tempnam(sys_get_temp_dir(), 'card_fld_') . '.zip';

        if ($zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Could not create ZIP archive.');
        }

        foreach ($files as $f) {
            if ($f->disk !== 'url') {
                $disk = $this->attachmentDisk($f);
                if (\Illuminate\Support\Facades\Storage::disk($disk)->exists($f->path)) {
                    $content = \Illuminate\Support\Facades\Storage::disk($disk)->get($f->path);
                    $zip->addFromString($f->display_name ?: $f->original_name, $content);
                }
            }
        }

        $zip->close();

        return response()->download($tmpFile, "{$safeName}.zip")->deleteFileAfterSend(true);
    }

    /** Delete an entire folder and all its files from the card */
    public function deleteFolder(Request $request, Card $card, string $folder): JsonResponse
    {
        $folderName = trim(urldecode($folder));
        $files = $card->files->filter(function ($f) use ($folderName) {
            return $f->folder_name === $folderName || str_starts_with($f->original_name, "{$folderName}/");
        });

        if ($files->isEmpty()) {
            return response()->json(['error' => 'Folder not found.'], 404);
        }

        $kanbanService = app(\App\Services\KanbanService::class);
        $count = $files->count();

        foreach ($files as $f) {
            $kanbanService->deleteFile($f, auth()->user());
        }

        $this->logCardActivity($card, 'folder_deleted', "deleted folder **{$folderName}** ({$count} files)");

        return response()->json([
            'success' => true,
            'folder'  => $folderName,
            'deleted_count' => $count,
        ]);
    }

    /** Assign existing files on a card to a folder */
    public function assignFolder(Request $request, Card $card): JsonResponse
    {
        $validated = $request->validate([
            'folder_name' => ['required', 'string', 'max:255'],
            'file_ids'    => ['nullable', 'array'],
            'file_ids.*'  => ['integer'],
        ]);

        $folderName = trim($validated['folder_name']);

        // Auto-create folder_name column if missing in database
        if (!\Illuminate\Support\Facades\Schema::hasColumn('card_files', 'folder_name')) {
            try {
                \Illuminate\Support\Facades\Schema::table('card_files', function ($table) {
                    $table->string('folder_name')->nullable()->after('original_name')->index();
                });
            } catch (\Throwable $e) {}
        }

        $query = $card->files();
        if (!empty($validated['file_ids'])) {
            $query->whereIn('id', $validated['file_ids']);
        } else {
            // Group all standalone files on card
            $query->where(function ($q) {
                $q->whereNull('folder_name')->orWhere('folder_name', '');
            });
        }

        $files = $query->get();
        if ($files->isEmpty()) {
            return response()->json(['error' => 'No files found to group into folder.'], 422);
        }

        $hasColumn = \Illuminate\Support\Facades\Schema::hasColumn('card_files', 'folder_name');

        foreach ($files as $file) {
            $currentName = $file->original_name;
            if (str_contains($currentName, '/')) {
                $parts = explode('/', $currentName, 2);
                $cleanName = $parts[1] ?: $currentName;
            } else {
                $cleanName = $currentName;
            }

            $file->original_name = "{$folderName}/{$cleanName}";
            if ($hasColumn) {
                $file->folder_name = $folderName;
            }
            $file->save();
        }

        $count = $files->count();
        $this->logCardActivity($card, 'folder_assigned', "grouped {$count} files into folder **{$folderName}**");

        $card->load('files');

        return response()->json([
            'success'     => true,
            'folder_name' => $folderName,
            'count'       => $count,
            'files'       => $card->files->map(fn($f) => $this->filePayload($f))->values()->all(),
        ]);
    }

    /** Edit the name/URL of an existing file/link attachment, or replace file. */
    public function updateFile(Request $request, Card $card, CardFile $file): JsonResponse
    {
        $this->assertCardFile($card, $file);

        $request->validate([
            'original_name' => ['nullable', 'string', 'max:255'],
            'link_url'      => ['nullable', 'url', 'max:2048'],
            'file'          => ['nullable', 'file', 'max:20480'],
        ]);

        $oldName = $file->original_name;

        // If a replacement file is uploaded
        if ($request->hasFile('file')) {
            // Delete old physical file if it was stored locally
            if ($file->disk !== 'url') {
                $oldDisk = $this->attachmentDisk($file);
                if (Storage::disk($oldDisk)->exists($file->path)) {
                    Storage::disk($oldDisk)->delete($file->path);
                }
            }

            $uploaded = $request->file('file');
            $kanbanService = app(\App\Services\KanbanService::class);
            $newFile = $kanbanService->uploadFile($card, $uploaded, auth()->user());

            // Transfer ID: delete old record, keep the new one
            $file->delete();



            return response()->json([
                'success' => true,
                'file'    => $this->filePayload($newFile),
            ]);
        }

        // Otherwise just update name / link URL
        $updates = [];
        if ($request->filled('original_name')) {
            $updates['original_name'] = $request->original_name;
        }

        if ($file->disk === 'url' && $request->filled('link_url')) {
            $updates['path']        = $request->link_url;
            $updates['stored_name'] = $request->link_url;
        }

        if (!empty($updates)) {
            $file->update($updates);


        }

        return response()->json([
            'success' => true,
            'file'    => $this->filePayload($file->refresh()),
        ]);
    }

    /** Open a local attachment inline when the browser supports it. */
    public function previewFile(Card $card, CardFile $file): mixed
    {
        $this->assertCardFile($card, $file);

        if ($file->disk === 'url') {
            return redirect()->away($file->path);
        }

        $disk = $this->attachmentDisk($file);

        if (! Storage::disk($disk)->exists($file->path)) {
            abort(404, 'Attachment not found.');
        }

        return response()->file(Storage::disk($disk)->path($file->path), [
            'Content-Type' => $file->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . $this->safeAttachmentName($file->original_name) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Download a card attachment to the user's device. */
    public function downloadFile(Card $card, CardFile $file): mixed
    {
        $this->assertCardFile($card, $file);

        if ($file->disk === 'url') {
            return redirect()->away($file->path);
        }

        $disk = $this->attachmentDisk($file);

        if (! Storage::disk($disk)->exists($file->path)) {
            abort(404, 'Attachment not found.');
        }

        return Storage::disk($disk)->download($file->path, $file->original_name);
    }

    /** Delete card file */
    public function deleteFile(Card $card, CardFile $file): JsonResponse
    {
        $this->assertCardFile($card, $file);

        $kanbanService = app(\App\Services\KanbanService::class);
        $kanbanService->deleteFile($file, auth()->user());

        $this->logCardActivity($card, 'file_removed', "removed file **{$file->original_name}**");

        return response()->json(['success' => true]);
    }

    /**
     * Resolve a Canva share/short URL into its canonical presentation embed URL.
     */
    public function resolveCanvaEmbed(Request $request): JsonResponse
    {
        $url = (string) $request->query('url', '');
        if (empty($url)) {
            return response()->json(['success' => false, 'embed_url' => null, 'total_pages' => 1], 400);
        }

        $details = CardFile::getCanvaDetails($url);

        return response()->json([
            'success'     => !empty($details['embed_url']),
            'embed_url'   => $details['embed_url'] ?: $url,
            'total_pages' => (!empty($details['total_pages']) && $details['total_pages'] > 1) ? $details['total_pages'] : 16,
        ]);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function defaultAvatar(): string
    {
        return User::initialsAvatarDataUri('System', '#64748b');
    }

    private function filePayload(CardFile $file): array
    {
        return [
            'id'                     => $file->id,
            'original_name'          => $file->original_name,
            'folder_name'            => $file->folder_name,
            'display_name'           => $file->display_name,
            'size'                   => (int) ($file->size ?? 0),
            'formatted_size'         => $file->formatted_size,
            'is_image'               => $file->isImage(),
            'is_video'               => (bool) $file->is_video,
            'is_canva'               => (bool) $file->is_canva,
            'is_google_drive'        => (bool) $file->is_google_drive,
            'is_google_drive_folder' => (bool) $file->is_google_drive_folder,
            'embed_url'              => $file->embed_url,
            'thumbnail_url'          => $file->thumbnail_url,
            'mime_type'              => $file->mime_type,
            'icon'                   => $file->icon,
            'url'                    => $file->url,
            'preview_url'            => $file->preview_url,
            'download_url'           => $file->download_url,
            'disk'                   => $file->disk,
            'path'                   => $file->path,
        ];
    }

    private function assertCardFile(Card $card, CardFile $file): void
    {
        abort_unless((int) $file->card_id === (int) $card->id, 404);
    }

    private function attachmentDisk(CardFile $file): string
    {
        return $file->disk && $file->disk !== 'url'
            ? $file->disk
            : config('filesystems.default', 'local');
    }

    private function safeAttachmentName(string $name): string
    {
        return str_replace(['"', "\r", "\n"], '', $name);
    }

    /**
     * Normalize rich text HTML to plain text content (preserving images)
     * to verify whether actual text/image content was added, deleted, or edited,
     * ignoring pure formatting changes like font color, font family, or font size.
     */
    private function normalizeDescriptionContent(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        // Extract image src attributes so adding/removing images is treated as actual content edit
        preg_match_all('/<img[^>]+src=[\'"]([^\'"]+)[\'"]/i', $html, $matches);
        $imgs = implode(',', $matches[1] ?? []);

        // Strip HTML tags (colors, fonts, span, p, strong, em, etc.)
        $text = strip_tags($html);

        // Decode HTML entities (e.g., &nbsp;, &amp;)
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Replace non-breaking spaces with standard space
        $text = preg_replace('/\x{00A0}/u', ' ', $text);

        // Collapse whitespace
        $text = trim(preg_replace('/\s+/', ' ', $text));

        return $imgs ? "{$text} [IMG:{$imgs}]" : $text;
    }

    private function logCardActivity(Card $card, string $action, string $description, bool $notify = true): void
    {
        $logData = [
            'user_id'      => auth()->id(),
            'action'       => "card.{$action}",
            'module'       => 'kanban',
            'description'  => $description,
            'subject_type' => Card::class,
            'ip_address'   => request()->ip(),
            'user_agent'   => request()->userAgent(),
            'created_at'   => now(),
        ];

        // Create for current card
        ActivityLog::create(array_merge($logData, ['subject_id' => $card->id]));
        
        // Removed duplicate system comment creation.
        // The frontend UI now renders ActivityLog entries directly.

        // Sync to other cards in the same group
        if ($card->sync_group_id) {
            $syncedCards = Card::where('sync_group_id', $card->sync_group_id)
                               ->where('id', '!=', $card->id)
                               ->get();
                               
            foreach ($syncedCards as $syncedCard) {
                ActivityLog::create(array_merge($logData, ['subject_id' => $syncedCard->id]));
            }
        }

        if ($notify) {
            try {
                \App\Notifications\BoardActivityNotification::send(
                    $card->board,
                    $action,
                    $description,
                    $card
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Failed sending card notification: " . $e->getMessage());
            }
        }
    }

    public function addSystemComment(Card $card, string $content): void
    {
        $card->comments()->create([
            'user_id' => auth()->id() ?? $card->created_by ?? 1,
            'content' => $content,
            'is_system' => true,
        ]);
    }

    private function formatCardForBoard(Card $card): array
    {
        $card->load([
            'assignees',
            'labels',
            'checklists.items',
            'files',
            'comments',
            'creator',
        ]);

        return [
            'id'              => $card->id,
            'title'           => $card->title,
            'priority'        => $card->priority?->value ?? ($card->priority ?? 'medium'),
            'due_at'          => $card->due_at?->format('Y-m-d'),
            'start_date'      => $card->start_date?->format('Y-m-d'),
            'due_time'        => $card->due_time,
            'reminder'        => $card->reminder,
            'recurring'       => $card->recurring ?? 'none',
            'board_id'        => $card->board_id,
            'board_list_id'   => $card->board_list_id,
            'status'          => $card->status?->value ?? (string) $card->status,
            'workflow_status' => $card->workflow_status,
            'block_completed_at' => $card->block_completed_at?->toISOString(),
            'block_completed_by' => $card->block_completed_by,
            'smm_class_label'    => $card->smm_class_label,
            'smm_team_label'     => $card->smm_team_label,
            'smm_cluster_label'  => $card->smm_cluster_label,
            'content_public_date'=> $card->content_public_date?->format('Y-m-d'),
            'team'            => $card->team,
            'position'        => $card->position,
            'sync_group_id'   => $card->sync_group_id,
            'creator'         => $card->creator ? [
                'id'           => $card->creator->id,
                'name'         => $card->creator->name,
                'avatar'       => $card->creator->avatar_url,
                'initials'     => $card->creator->avatar_initials,
                'avatar_color' => $card->creator->avatar_color,
            ] : null,
            'labels'          => $card->labels->map(fn($lb) => ['id' => $lb->id, 'name' => $lb->name, 'color' => $lb->color])->values()->all(),
            'assignees'       => $card->assignees->map(fn($u) => [
                'id'           => $u->id,
                'name'         => $u->name,
                'email'        => $u->email,
                'avatar'       => $u->avatar_url,
                'initials'     => $u->avatar_initials,
                'avatar_color' => $u->avatar_color,
            ])->values()->all(),
            'checklist_total' => $card->checklists->flatMap->items->count(),
            'checklist_done'  => $card->checklists->flatMap->items->where('is_completed', true)->count(),
            'has_files'       => $card->files->count() > 0,
            'comment_count'   => $card->comments->count(),
            'has_description' => !empty($card->description),
        ];
    }

    private function isAllowedMoveUser(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $username = strtolower(trim($user->username ?? ''));
        $name = strtolower(trim($user->name ?? ''));
        $email = strtolower(trim($user->email ?? ''));

        foreach (['dara', 'kim'] as $target) {
            if (str_contains($username, $target) || str_contains($name, $target) || str_contains($email, $target)) {
                return true;
            }
        }

        return false;
    }

    private function canMoveAnyCard(User $user): bool
    {
        return $this->isAllowedMoveUser($user)
            || $user->hasAnyRole(['super-admin', 'admin', 'admin-digital', 'supervisor', 'boss'])
            || $user->isQcOrSupervisor()
            || str_contains(strtolower($user->team_role ?? ''), 'head');
    }

    private function canManageBlockedCards(User $user): bool
    {
        return $this->isAllowedMoveUser($user)
            || $user->hasAnyRole(['super-admin', 'admin', 'admin-digital', 'supervisor', 'boss'])
            || $user->isSupervisorRole()
            || str_contains(strtolower($user->team_role ?? ''), 'head');
    }

    private function canMoveCard(User $user, Card $card, ?BoardList $sourceList, BoardList $targetList): bool
    {
        if ($this->isBlockList($sourceList?->name) || $this->isBlockList($targetList->name)) {
            return $this->canManageBlockedCards($user);
        }

        if ($this->canMoveAnyCard($user)) {
            return true;
        }

        return $card->assignees->contains('id', $user->id) || (int) $card->created_by === (int) $user->id;
    }

    private function isBlockList(?string $name): bool
    {
        return str_contains(strtolower($name ?? ''), 'block');
    }

    public function bulkAction(Request $request, Board $board): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'card_ids' => 'required|array',
            'card_ids.*' => 'integer|exists:cards,id',
            'action' => 'required|in:move,copy,comment,delete',
            'target_board_id' => 'required_if:action,move,copy|nullable|exists:boards,id',
            'target_list_id' => 'required_if:action,move,copy|nullable|exists:board_lists,id',
            'comment' => 'required_if:action,comment|nullable|string',
        ]);

        $action = $validated['action'];
        $cardIds = $validated['card_ids'];

        if ($action === 'delete') {
            $successful = 0;
            $failed = 0;

            foreach ($cardIds as $id) {
                $card = Card::find($id);
                if (!$card) {
                    $failed++;
                    continue;
                }

                try {
                    if (!$card->board_id && $card->board_list_id) {
                        $card->board_id = $card->boardList?->board_id ?? $board->id;
                        $card->save();
                    }
                    $this->logCardActivity($card, 'deleted', "moved card '{$card->title}' to Trash");
                    $card->delete();
                    $successful++;
                } catch (\Exception $e) {
                    \Log::error("Bulk delete error on card {$id}: " . $e->getMessage());
                    $failed++;
                }
            }

            return response()->json([
                'message' => "Successfully moved {$successful} card" . ($successful === 1 ? '' : 's') . " to Trash."
            ]);
        }

        if ($action === 'comment') {
            $commentText = trim((string)($validated['comment'] ?? ''));
            if (empty($commentText)) {
                return response()->json(['message' => 'Comment cannot be empty.'], 422);
            }

            $successful = 0;
            $failed = 0;

            foreach ($cardIds as $id) {
                $card = Card::find($id);
                if (!$card) {
                    $failed++;
                    continue;
                }

                if ($card->hasIncompleteChecklist() && app(\App\Services\BoardWorkflowService::class)->isAutomationComment($card, $commentText)) {
                    $failed++;
                    continue;
                }

                try {
                    $comment = $card->comments()->create([
                        'user_id'   => auth()->id(),
                        'content'   => $commentText,
                        'is_system' => false,
                    ]);

                    // Check and trigger automations
                    $autoResult = $this->checkAutomations($card, null, $commentText);

                    // Check workflow service (always handle ready, block, or if no database automation rule triggered)
                    if (!$autoResult || stripos($commentText, 'ready') !== false || stripos($commentText, 'block') !== false) {
                        app(\App\Services\BoardWorkflowService::class)->handleCommentTrigger($card, $comment);
                    }

                    // Log activity
                    $plainText = trim(preg_replace('/!\[.*?\]\([^)]+\)/', '', $commentText));
                    $this->logCardActivity($card, 'comment_added', "added comment: \"" . \Illuminate\Support\Str::limit($plainText, 100) . "\"");

                    $successful++;
                } catch (\Exception $e) {
                    \Log::error("Bulk comment error on card {$id}: " . $e->getMessage());
                    $failed++;
                }
            }

            return response()->json([
                'message' => "Comment \"{$commentText}\" posted to {$successful} card" . ($successful === 1 ? '' : 's') . "."
            ]);
        }

        $targetBoardId = $validated['target_board_id'];
        $targetListId = $validated['target_list_id'];
        $targetBoard = Board::findOrFail($targetBoardId);
        $targetList = BoardList::findOrFail($targetListId);

        $successful = 0;
        $failed = 0;

        foreach ($cardIds as $id) {
            $card = Card::find($id);
            if (!$card) {
                $failed++;
                continue;
            }

            try {
                if ($action === 'move') {
                    if ($card->hasIncompleteChecklist()) {
                        $failed++;
                        continue;
                    }
                    $oldList = $card->boardList?->name ?? 'Unknown list';
                    $sourceBoard = $card->board ?? \App\Models\Board::find($card->board_id);
                    $isCrossBoard = $sourceBoard && (int) $targetBoardId !== (int) $sourceBoard->id;
                    $card->board_id = $targetBoardId;
                    $card->board_list_id = $targetListId;
                    $card->position = 65535; // Put at bottom
                    $card->saveQuietly();
                    if ($isCrossBoard && $sourceBoard) {
                        $this->logCardActivity($card, 'moved', "moved this card from **{$oldList}** on **{$sourceBoard->name}** to **{$targetList->name}**");
                    } else {
                        $this->logCardActivity($card, 'moved', "moved this card from **{$oldList}** to **{$targetList->name}**");
                    }
                    app(\App\Services\BoardWorkflowService::class)->syncPlanningWeekList($card, $targetList);
                } elseif ($action === 'copy') {
                    if ($card->hasIncompleteChecklist()) {
                        $failed++;
                        continue;
                    }
                    $newTitle = ((int)$targetBoardId === (int)$card->board_id) ? $card->title . ' (copy)' : $card->title;
                    $copy = $card->replicateRelationally($targetBoardId, $targetListId, $newTitle, $card->created_by, true);
                    $sourceListName = $card->boardList?->name ?? 'Unknown list';
                    $sourceBoard = $card->board ?? \App\Models\Board::find($card->board_id);
                    $isCrossBoard = $sourceBoard && (int) $targetBoardId !== (int) $sourceBoard->id;
                    if ($isCrossBoard && $sourceBoard) {
                        $this->logCardActivity($copy, 'copied', "copied this card from **{$sourceListName}** on **{$sourceBoard->name}** to **{$targetList->name}**");
                    } else {
                        $this->logCardActivity($copy, 'copied', "copied this card from **{$sourceListName}** to **{$targetList->name}**");
                    }
                }
                $successful++;
            } catch (\Exception $e) {
                $failed++;
            }
        }

        return response()->json([
            'message' => "Bulk {$action} complete. {$successful} succeeded, {$failed} failed."
        ]);
    }
}
