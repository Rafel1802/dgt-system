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
    public function show(Card $card): JsonResponse
    {
        $card->load([
            'assignees',
            'labels',
            'checklists.items',
            'comments.user',
            'files',
            'creator:id,name',
            'boardList:id,name',
        ]);

        $activities = ActivityLog::with('user')
            ->where('subject_type', Card::class)
            ->where('subject_id', $card->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($log) => [
                'id'          => $log->id,
                'user_name'   => $log->user?->name ?? 'System',
                'user_avatar' => $log->user?->avatar_url ?? $this->defaultAvatar(),
                'user_initials' => $log->user?->avatar_initials ?? 'SY',
                'user_avatar_color' => $log->user?->avatar_color ?? '#64748b',
                'description' => $log->description,
                'action'      => $log->action,
                'created_at'  => $log->created_at?->toISOString(),
                'time_ago'    => $log->created_at ? $log->created_at->format('M j, Y, g:i A') : 'N/A',
            ]);

        // Build a clean card payload with correct avatar URLs
        $cardData = $card->toArray();
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
        $cardData['comments'] = $card->comments->map(fn($c) => [
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
            'board_list_id' => ['required', 'exists:board_lists,id'],
            'title'         => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'priority'      => ['nullable', 'in:low,medium,high,urgent'],
            'due_at'        => ['nullable', 'date'],
            'label'         => ['nullable', 'string', 'max:50'],
        ]);

        $position = Card::where('board_list_id', $validated['board_list_id'])->max('position') + 1;

        $card = Card::create([
            ...$validated,
            'board_id'   => $board->id,
            'status'     => 'todo',
            'priority'   => $validated['priority'] ?? 'medium',
            'position'   => $position,
            'created_by' => auth()->id(),
        ]);

        $card->load('assignees:id,name,avatar', 'labels');

        $this->logCardActivity($card, 'created', "created this card");
        $this->checkAutomations($card, null, null, true); // new cards can trigger keyword rules if title has it

        return response()->json([
            'card'    => $card,
            'message' => 'Card created.',
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
        ]);

        $oldValues = $card->only(array_keys($validated));
        $card->update($validated);
        $card->load('assignees:id,name,avatar', 'labels');

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
                'description' => $this->logCardActivity($card, 'desc_changed',
                    'updated card description'),
                'title'       => $this->logCardActivity($card, 'title_changed',
                    "renamed card to **{$val}**"),
                default       => null,
            };
        }

        // Notify assignees when due date changes
        if (array_key_exists('due_at', $validated) && (string)($oldValues['due_at'] ?? '') !== (string)$validated['due_at']) {
            foreach ($card->assignees as $assignee) {
                if ($assignee->id !== auth()->id()) {
                    $assignee->notify(new \App\Notifications\GenericDatabaseNotification([
                        'actor_id'     => auth()->id(),
                        'actor_name'   => auth()->user()->name,
                        'actor_avatar' => auth()->user()->avatar_url,
                        'message'      => auth()->user()->name . " updated the due date on card '{$card->title}'",
                        'link'         => route('boards.show', $card->board->slug),
                    ]));
                }
            }
        }

        $titleChanged = array_key_exists('title', $validated) && (string)($oldValues['title'] ?? '') !== (string)$validated['title'];

        $originalBoardId = $card->board_id;
        $originalListId = $card->board_list_id;

        $this->checkAutomations($card, null, null, $titleChanged);

        $cardMoved = $card->board_id !== $originalBoardId || $card->board_list_id !== $originalListId;

        return response()->json([
            'card' => $this->formatCardForBoard($card), 
            'message' => 'Card updated.',
            'card_moved' => $cardMoved
        ]);
    }

    private function checkAutomations(Card $card, ?int $movedToListId = null, ?string $commentText = null, bool $titleChanged = false): ?array
    {
        $automations = \App\Models\BoardAutomation::where('trigger_board_id', $card->board_id)
            ->orWhere(function($q) use ($card) {
                $q->whereNull('trigger_board_id')->where('board_id', $card->board_id);
            })->get();
            
        if ($automations->isEmpty()) return null;

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
                $wordMatch = false;
                if ($commentText) {
                    $wordMatch = stripos($commentText, $automation->trigger_word) !== false;
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
                $sourceBoardName = $card->board?->name ?? 'Unknown board';
                $sourceListName = $card->boardList?->name ?? 'Unknown list';
                $reason = 'automation rule';
                if ($automation->trigger_type === 'keyword') $reason = "keyword '{$automation->trigger_word}'";
                elseif ($automation->trigger_type === 'list') $reason = "moving to list";
                elseif ($automation->trigger_type === 'both') $reason = "moving to list and keyword '{$automation->trigger_word}'";

                if ($automation->action_type === 'copy') {
                    $isSameBoard = (int)$automation->target_board_id === (int)$card->board_id;
                    $newTitle = $isSameBoard ? $card->title . ' (copy)' : $card->title;
                    
                    $copy = $card->replicateRelationally($automation->target_board_id, $automation->target_list_id, $newTitle, auth()->id() ?? $card->created_by, true);

                    $this->logCardActivity($copy, 'copied_by_automation', "card automatically copied from board '{$card->board->name}' list '{$card->boardList->name}' based on {$reason}");
                    
                    if ($automation->target_assignee_id) {
                        if (!$copy->assignees()->where('users.id', $automation->target_assignee_id)->exists()) {
                            $copy->assignees()->attach($automation->target_assignee_id);
                            $assignedUser = \App\Models\User::find($automation->target_assignee_id);
                            if ($assignedUser) {
                                $this->logCardActivity($copy, 'member_added', "assigned **{$assignedUser->name}** to this card via automation");
                            }
                        }
                    } elseif ($automation->target_assignee_role) {
                        $targetBoard = \App\Models\Board::find($automation->target_board_id);
                        if ($targetBoard) {
                            $roleUsers = $targetBoard->members()->where('users.team_role', $automation->target_assignee_role)->get();
                            foreach ($roleUsers as $u) {
                                if (!$copy->assignees()->where('users.id', $u->id)->exists()) {
                                    $copy->assignees()->attach($u->id);
                                    $this->logCardActivity($copy, 'member_added', "assigned **{$u->name}** (Role: {$automation->target_assignee_role}) to this card via automation");
                                }
                            }
                        }
                    }
                } else {
                    $maxPos = \App\Models\Card::where('board_list_id', $automation->target_list_id)->max('position') ?? 0;
                    $targetBoard = \App\Models\Board::find($automation->target_board_id);
                    $targetList = \App\Models\BoardList::find($automation->target_list_id);
                    
                    $card->update([
                        'board_id' => $automation->target_board_id,
                        'board_list_id' => $automation->target_list_id,
                        'position' => $maxPos + 1,
                    ]);
                    $card->unsetRelation('board');
                    $card->unsetRelation('boardList');

                    $this->logCardActivity(
                        $card,
                        'moved_by_automation',
                        "automatically moved this card from **{$sourceBoardName} / {$sourceListName}** to **" .
                        ($targetBoard?->name ?? 'Unknown board') . ' / ' . ($targetList?->name ?? 'Unknown list') .
                        "** based on {$reason}"
                    );

                    if ($automation->target_assignee_id) {
                        if (!$card->assignees()->where('users.id', $automation->target_assignee_id)->exists()) {
                            $card->assignees()->attach($automation->target_assignee_id);
                            $assignedUser = \App\Models\User::find($automation->target_assignee_id);
                            if ($assignedUser) {
                                $this->logCardActivity($card, 'member_added', "assigned **{$assignedUser->name}** to this card via automation");
                            }
                        }
                    } elseif ($automation->target_assignee_role) {
                        $targetBoard = \App\Models\Board::find($automation->target_board_id);
                        if ($targetBoard) {
                            $roleUsers = $targetBoard->members()->where('users.team_role', $automation->target_assignee_role)->get();
                            foreach ($roleUsers as $u) {
                                if (!$card->assignees()->where('users.id', $u->id)->exists()) {
                                    $card->assignees()->attach($u->id);
                                    $this->logCardActivity($card, 'member_added', "assigned **{$u->name}** (Role: {$automation->target_assignee_role}) to this card via automation");
                                }
                            }
                        }
                    }
                }

                return [
                    'triggered' => true,
                    'rule_id' => $automation->id,
                    'trigger_type' => $automation->trigger_type,
                    'action_type' => $automation->action_type,
                    'reason' => $reason,
                ]; // Only apply the first matching rule to avoid loops or conflicts
            }
        }

        return null;
    }

    /** Soft-delete a card. */
    public function destroy(Card $card): JsonResponse
    {
        $this->logCardActivity($card, 'deleted', "deleted card '{$card->title}'");
        $card->delete();
        return response()->json(['message' => 'Card deleted.']);
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
                $user->notify(new GenericDatabaseNotification([
                    'actor_id'     => auth()->id(),
                    'actor_name'   => auth()->user()->name,
                    'actor_avatar' => auth()->user()->avatar_url,
                    'message'      => auth()->user()->name . " assigned you to card '{$card->title}'",
                    'link'         => route('boards.show', $card->board->slug)
                ]));
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
        }

        // Sync labels to other cards in the sync group
        if ($card->sync_group_id) {
            $syncedCards = Card::where('sync_group_id', $card->sync_group_id)->where('id', '!=', $card->id)->get();
            $labelIds = $card->labels()->pluck('labels.id')->toArray();
            foreach ($syncedCards as $syncedCard) {
                $syncedCard->labels()->sync($labelIds);
            }
        }

        return response()->json([
            'message' => $message,
            'labels'  => $card->labels()->get(),
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

        $card->update([
            'board_id'      => $targetList->board_id,
            'board_list_id' => $targetList->id,
            'position'      => $request->position ?? 0,
        ]);
        $newList = $targetList->name;

        $this->logCardActivity($card, 'moved', "moved this card from **{$oldList}** to **{$newList}**");
        $this->addSystemComment($card, "moved this card from **{$oldList}** to **{$newList}**");
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

        $copy = $card->replicateRelationally($targetBoard->id, $targetListId, $request->title, auth()->id(), true);

        $this->logCardActivity($copy, 'copied', "copied from card **{$card->title}**");

        return response()->json([
            'card'    => $this->formatCardForBoard($copy),
            'message' => "Card copied as \"" . $copy->title . "\".",
        ], 201);
    }

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
        $this->addSystemComment($card, $message);

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
            'title' => ['required', 'string', 'max:255'],
        ]);

        $oldTitle = $checklist->title;
        $checklist->update(['title' => $validated['title']]);

        $this->logCardActivity($card, 'checklist_updated', "renamed checklist from **{$oldTitle}** to **{$checklist->title}**");

        return response()->json([
            'success'   => true,
            'checklist' => $checklist->load('items'),
        ]);
    }

    /** Add a checklist item */
    public function storeChecklistItem(Request $request, Card $card, CardChecklist $checklist): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:500'],
        ]);

        $item = $checklist->items()->create([
            'content'      => $validated['title'],
            'position'     => $checklist->items()->count() + 1,
            'is_completed' => false,
        ]);

        $this->logCardActivity($card, 'checklist_item_created', "added item **{$item->content}** to checklist **{$checklist->title}**");

        return response()->json([
            'success' => true,
            'item'    => $item,
        ], 201);
    }

    /** Toggle or update checklist item */
    public function toggleChecklistItem(Request $request, Card $card, CardChecklist $checklist, CardChecklistItem $item): JsonResponse
    {
        if ($request->has('title')) {
            $oldContent = $item->content;
            $item->update(['content' => $request->title]);
            $this->logCardActivity($card, 'checklist_item_updated', "renamed item from **{$oldContent}** to **{$item->content}**");
        } else {
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
            'item'    => $item,
            'percent' => $checklist->load('items')->progressPercent(),
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

        $comment = $card->comments()->create([
            'user_id'   => auth()->id(),
            'content'   => $validated['body'],
            'is_system' => false,
        ]);
            
        $originalBoardId = $card->board_id;
        $originalListId = $card->board_list_id;

        $this->checkAutomations($card, null, $validated['body']);

        $cardMoved = $card->board_id !== $originalBoardId || $card->board_list_id !== $originalListId;

        $logContent = \Illuminate\Support\Str::limit($comment->content, 100);
        if (str_contains($comment->content, '![screenshot](data:image')) {
            $logContent = 'added a comment with a screenshot';
        } else {
            $logContent = "added comment: \"{$logContent}\"";
        }
        $this->logCardActivity($card, 'comment_added', $logContent);

        // Notify card assignees
        foreach ($card->assignees as $assignee) {
            if ($assignee->id !== auth()->id()) {
                $assignee->notify(new GenericDatabaseNotification([
                    'actor_id'     => auth()->id(),
                    'actor_name'   => auth()->user()->name,
                    'actor_avatar' => auth()->user()->avatar_url,
                    'message'      => auth()->user()->name . " commented on card '{$card->title}'",
                    'link'         => route('boards.show', $card->board->slug)
                ]));
            }
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

            foreach ($card->assignees as $assignee) {
                if ($assignee->id !== auth()->id()) {
                    $assignee->notify(new GenericDatabaseNotification([
                        'actor_id'     => auth()->id(),
                        'actor_name'   => auth()->user()->name,
                        'actor_avatar' => auth()->user()->avatar_url,
                        'message'      => auth()->user()->name . " edited a comment on card '{$card->title}'",
                        'link'         => route('boards.show', $card->board->slug) . "?card={$card->id}",
                    ]));
                }
            }
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

        foreach ($card->assignees as $assignee) {
            if ($assignee->id !== auth()->id()) {
                $assignee->notify(new GenericDatabaseNotification([
                    'actor_id'     => auth()->id(),
                    'actor_name'   => auth()->user()->name,
                    'actor_avatar' => auth()->user()->avatar_url,
                    'message'      => auth()->user()->name . " deleted a comment on card '{$card->title}'",
                    'link'         => route('boards.show', $card->board->slug) . "?card={$card->id}",
                ]));
            }
        }

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
        if ($request->hasFile('file')) {
            // ── Security validation ───────────────────────────────────────
            $file = $request->file('file');

            // Block filenames with double extensions (e.g. evil.php.jpg)
            $originalName = $file->getClientOriginalName();
            if (substr_count($originalName, '.') > 1) {
                $dangerousExt = ['php', 'phtml', 'php3', 'php4', 'php5', 'phar',
                                 'exe', 'bat', 'sh', 'py', 'rb', 'pl', 'cgi',
                                 'js', 'jsx', 'ts', 'html', 'htm'];
                $parts = explode('.', strtolower($originalName));
                foreach ($parts as $part) {
                    if (in_array($part, $dangerousExt, true)) {
                        return response()->json(['error' => 'File type not allowed.'], 422);
                    }
                }
            }

            $request->validate([
                'file' => [
                    'required', 'file',
                    'max:20480',          // 20 MB
                ],
            ]);

            $kanbanService = app(\App\Services\KanbanService::class);
            $cardFile = $kanbanService->uploadFile($card, $file, auth()->user());

            $this->logCardActivity($card, 'file_attached', "attached file **{$cardFile->original_name}**");

            // Notify assignees
            foreach ($card->assignees as $assignee) {
                if ($assignee->id !== auth()->id()) {
                    $assignee->notify(new \App\Notifications\GenericDatabaseNotification([
                        'actor_id'     => auth()->id(),
                        'actor_name'   => auth()->user()->name,
                        'actor_avatar' => auth()->user()->avatar_url,
                        'message'      => auth()->user()->name . " attached a file to card '{$card->title}'",
                        'link'         => route('boards.show', $card->board->slug),
                    ]));
                }
            }

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
            foreach ($card->assignees as $assignee) {
                if ($assignee->id !== auth()->id()) {
                    $assignee->notify(new \App\Notifications\GenericDatabaseNotification([
                        'actor_id'     => auth()->id(),
                        'actor_name'   => auth()->user()->name,
                        'actor_avatar' => auth()->user()->avatar_url,
                        'message'      => auth()->user()->name . " added a link to card '{$card->title}'",
                        'link'         => route('boards.show', $card->board->slug),
                    ]));
                }
            }
        }

        return response()->json([
            'success' => true,
            'file'    => $this->filePayload($cardFile),
        ], 201);
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

        $this->logCardActivity($card, 'file_downloaded', "downloaded attachment **{$file->original_name}**");

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

    // ── Helper ────────────────────────────────────────────────────────────────

    private function defaultAvatar(): string
    {
        return User::initialsAvatarDataUri('System', '#64748b');
    }

    private function filePayload(CardFile $file): array
    {
        return [
            'id'             => $file->id,
            'original_name'  => $file->original_name,
            'formatted_size' => $file->formatted_size,
            'is_image'       => $file->isImage(),
            'mime_type'      => $file->mime_type,
            'icon'           => $file->icon,
            'url'            => $file->url,
            'preview_url'    => $file->preview_url,
            'download_url'   => $file->download_url,
            'disk'           => $file->disk,
            'path'           => $file->path,
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

    private function logCardActivity(Card $card, string $action, string $description): void
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

        // Sync to other cards in the same group
        if ($card->sync_group_id) {
            $syncedCards = Card::where('sync_group_id', $card->sync_group_id)
                               ->where('id', '!=', $card->id)
                               ->get();
                               
            foreach ($syncedCards as $syncedCard) {
                ActivityLog::create(array_merge($logData, ['subject_id' => $syncedCard->id]));
            }
        }

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

    private function addSystemComment(Card $card, string $content): void
    {
        $card->comments()->create([
            'user_id' => auth()->id(),
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
            'block_completed_at' => $card->block_completed_at?->toISOString(),
            'block_completed_by' => $card->block_completed_by,
            'position'        => $card->position,
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
        ];
    }

    private function canMoveAnyCard(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'admin-digital', 'supervisor', 'boss'])
            || $user->isQcOrSupervisor();
    }

    private function canManageBlockedCards(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'admin-digital', 'supervisor', 'boss'])
            || $user->isSupervisorRole();
    }

    private function canMoveCard(User $user, Card $card, ?BoardList $sourceList, BoardList $targetList): bool
    {
        if ($this->isBlockList($sourceList?->name) || $this->isBlockList($targetList->name)) {
            return $this->canManageBlockedCards($user);
        }

        if ($this->canMoveAnyCard($user)) {
            return true;
        }

        return $card->assignees->contains('id', $user->id);
    }

    private function isBlockList(?string $name): bool
    {
        return str_contains(strtolower($name ?? ''), 'block');
    }
}
