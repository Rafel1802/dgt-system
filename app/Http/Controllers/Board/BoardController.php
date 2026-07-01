<?php

namespace App\Http\Controllers\Board;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\BoardActivityNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * BoardController
 *
 * Handles: workspace list, board CRUD, and the Trello-style board view.
 */
class BoardController extends Controller
{
    // ── Workspace / Board Index ────────────────────────────────────────────────

    /** Show all workspaces the authenticated user belongs to or owns. */
    public function workspaces(): View
    {
        $user = auth()->user();
        $workspaces = $this->getAuthorizedWorkspaces($user);
        
        $hiddenBoards = collect();
        $trashedWorkspaces = collect();
        if ($user->hasAnyRole(['super-admin', 'admin-digital'])) {
            $hiddenBoards = \App\Models\Board::where('is_hidden', true)->with('workspace')->get();
            $trashedWorkspaces = \App\Models\Workspace::onlyTrashed()->get();
        }

        return view('boards.workspaces', compact('workspaces', 'hiddenBoards', 'trashedWorkspaces'));
    }

    /** Show a single board with its lists and cards (the Trello view). */
    public function storeWorkspace(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', 'string', 'max:10'],
            'icon_text' => ['nullable', 'string', 'max:5'],
        ]);

        $maxPosition = Workspace::max('position') ?? 0;

        Workspace::create([
            'name' => $validated['name'],
            'color' => $validated['color'],
            'icon_text' => $validated['icon_text'] ?: strtoupper(substr($validated['name'], 0, 1)),
            'owner_id' => auth()->id(),
            'is_active' => true,
            'position' => $maxPosition + 1,
        ]);

        return back()->with('success', 'Workspace created successfully.');
    }

    /** Update an existing workspace. */
    public function updateWorkspace(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorizeWorkspace($workspace->id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', 'string', 'max:10'],
            'icon_text' => ['nullable', 'string', 'max:5'],
        ]);

        $workspace->update([
            'name' => $validated['name'],
            'color' => $validated['color'],
            'icon_text' => $validated['icon_text'] ?: strtoupper(substr($validated['name'], 0, 1)),
        ]);

        return back()->with('success', 'Workspace renamed successfully.');
    }

    /** Move a workspace to trash (soft delete). */
    public function destroyWorkspace(Request $request, Workspace $workspace): RedirectResponse
    {
        if (!auth()->user()->hasAnyRole(['super-admin', 'admin-digital'])) {
            abort(403, 'Unauthorized.');
        }

        $workspace->delete();

        return back()->with('success', 'Workspace moved to trash.');
    }

    /** Restore a trashed workspace. */
    public function restoreWorkspace($id): RedirectResponse
    {
        if (!auth()->user()->hasAnyRole(['super-admin', 'admin-digital'])) {
            abort(403, 'Unauthorized.');
        }

        $workspace = Workspace::onlyTrashed()->findOrFail($id);
        $workspace->restore();

        return back()->with('success', 'Workspace recovered successfully.');
    }

    /** Permanently delete a workspace. */
    public function forceDeleteWorkspace($id): RedirectResponse
    {
        if (!auth()->user()->hasAnyRole(['super-admin', 'admin-digital'])) {
            abort(403, 'Unauthorized.');
        }

        $workspace = Workspace::onlyTrashed()->findOrFail($id);
        
        // Let's also delete all related boards.
        foreach ($workspace->boards()->withTrashed()->get() as $board) {
            $board->forceDelete();
        }

        $workspace->forceDelete();

        return back()->with('success', 'Workspace permanently deleted.');
    }

    /** Move workspace up (decrease position). */
    public function moveUpWorkspace(Request $request, Workspace $workspace): RedirectResponse
    {
        if (!auth()->user()->hasAnyRole(['super-admin', 'admin-digital'])) {
            abort(403, 'Unauthorized.');
        }

        $previous = Workspace::where('position', '<=', $workspace->position)
            ->where('id', '!=', $workspace->id)
            ->orderBy('position', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if ($previous) {
            $temp = $workspace->position;
            $workspace->update(['position' => $previous->position]);
            $previous->update(['position' => $temp]);
        }

        return back()->with('success', 'Workspace moved up.');
    }

    /** Move workspace down (increase position). */
    public function moveDownWorkspace(Request $request, Workspace $workspace): RedirectResponse
    {
        if (!auth()->user()->hasAnyRole(['super-admin', 'admin-digital'])) {
            abort(403, 'Unauthorized.');
        }

        $next = Workspace::where('position', '>=', $workspace->position)
            ->where('id', '!=', $workspace->id)
            ->orderBy('position', 'asc')
            ->orderBy('id', 'asc')
            ->first();

        if ($next) {
            $temp = $workspace->position;
            $workspace->update(['position' => $next->position]);
            $next->update(['position' => $temp]);
        }

        return back()->with('success', 'Workspace moved down.');
    }

    /** Persist drag-and-drop board ordering inside a workspace. */
    public function reorderWorkspaceBoards(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeWorkspace($workspace->id);

        $validated = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'integer', 'distinct', 'exists:boards,id'],
        ]);

        $boardIds = collect($validated['order'])->map(fn ($id) => (int) $id)->values();
        $validCount = Board::where('workspace_id', $workspace->id)
            ->whereIn('id', $boardIds)
            ->count();

        if ($validCount !== $boardIds->count()) {
            return response()->json(['message' => 'One or more boards do not belong to this workspace.'], 422);
        }

        DB::transaction(function () use ($boardIds, $workspace) {
            foreach ($boardIds as $position => $boardId) {
                Board::where('workspace_id', $workspace->id)
                    ->whereKey($boardId)
                    ->update(['position' => $position]);
            }
        });

        return response()->json(['message' => 'Board order saved.']);
    }

    /** Show a single board with its lists and cards (the Trello view). */
    public function show(Board $board): View
    {
        $this->authorizeBoard($board);
        $user = auth()->user();

        $allWorkspaces = $this->getAuthorizedWorkspaces($user);

        $board->load([
            'workspace.members',
            'labels',
            'activeLists.cards.assignees',
            'activeLists.cards.labels',
            'activeLists.cards.checklists.items',
            'members',
        ]);

        $user = auth()->user();
        $workspaceBoards = $board->workspace
            ->boards()
            ->where('is_archived', false)
            ->where('is_hidden', false)
            ->orderBy('position')
            ->get()
            ->filter(function ($b) use ($user) {
                $isQc = str_contains(strtolower($user->team_role ?? ''), 'qc');
                $isBypassed = $user->hasAnyRole(['super-admin', 'admin-digital', 'admin', 'supervisor', 'boss']) || $isQc;

                if ($isBypassed) {
                    return true;
                }

                if ($user->hasAnyRole(['digital-team', 'sales-crm'])) {
                    return $b->hasMember($user->id);
                }
                return true;
            });

        $boardData = [
            'board'     => [
                'id'         => $board->id,
                'name'       => $board->name,
                'slug'       => $board->slug,
                'description'=> $board->description,
                'workspace_id' => $board->workspace_id,
                'visibility' => $board->visibility,
                'background_type' => $board->background_type,
                'background_value' => $board->background_value,
                'cover_type' => $board->cover_type,
                'cover_value' => $board->cover_value,
                'member_permissions' => $board->member_permissions ?? 'members',
                'card_covers_enabled' => (bool) ($board->card_covers_enabled ?? true),
                'notifications_enabled' => (bool) ($board->notifications_enabled ?? true),
                'browser_notifications_enabled' => (bool) ($board->browser_notifications_enabled ?? false),

                'is_starred' => (bool)$board->is_starred,
                'can_manage_board' => $this->canManageBoard($user, $board),
                'can_delete_board' => $this->canDeleteBoard($user, $board),
            ],
            'boardId'   => $board->id,
            'boardSlug' => $board->slug,
            'csrfToken' => csrf_token(),
            'currentUserId' => $user->id,
            'lists'     => $board->activeLists->map(fn($l) => [
                'id'       => $l->id,
                'name'     => $l->name,
                'color'    => $l->color,
                'cards'    => $l->cards->map(fn($c) => [
                    'id'         => $c->id,
                    'title'      => $c->title,
                    'priority'   => $c->priority?->value ?? 'medium',
                    'due_at'     => $c->due_at?->format('Y-m-d'),
                    'start_date' => $c->start_date?->format('Y-m-d'),
                    'due_time'   => $c->due_time,
                    'reminder'   => $c->reminder,
                    'recurring'  => $c->recurring ?? 'none',
                    'labels'   => $c->labels->map(fn($lb) => ['id'=>$lb->id,'name'=>$lb->name,'color'=>$lb->color]),
                    'assignees'=> $c->assignees->map(fn($u) => [
                        'id' => $u->id,
                        'name' => $u->name,
                        'email' => $u->email,
                        'avatar' => $u->avatar_url,
                        'initials' => $u->avatar_initials,
                        'avatar_color' => $u->avatar_color,
                    ]),
                    'checklist_total' => $c->checklists->flatMap->items->count(),
                    'checklist_done'  => $c->checklists->flatMap->items->where('is_completed',true)->count(),
                    'has_files'       => $c->files->count() > 0,
                    'comment_count'   => $c->comments->count(),
                ])->values()->all(),
            ])->values()->all(),
            'labels'           => \App\Models\Label::where(function($q) use ($board) {
                $q->whereNull('workspace_id')->whereNull('board_id')
                  ->orWhere('workspace_id', $board->workspace_id)
                  ->orWhere('board_id', $board->id);
            })->orderBy('name')
            ->get()
            ->unique(function ($item) {
                return strtolower($item->name);
            })
            ->map(fn($l) => ['id'=>$l->id,'name'=>$l->name,'color'=>$l->color])
            ->values()
            ->all(),
            'boardMembers'     => $board->members->map(fn($u) => [
                'id'      => $u->id,
                'name'    => $u->name,
                'email'   => $u->email,
                'avatar'  => $u->avatar_url,
                'initials'=> $u->avatar_initials,
                'avatar_color' => $u->avatar_color,
                'role'    => $u->pivot->role ?? 'member',
            ])->values()->all(),
            'workspaceMembers' => $board->workspace->members
                ->filter(fn($u) => !$board->members->contains('id', $u->id))
                ->map(fn($u) => [
                    'id'      => $u->id,
                    'name'    => $u->name,
                    'email'   => $u->email,
                    'avatar'  => $u->avatar_url,
                    'initials'=> $u->avatar_initials,
                    'avatar_color' => $u->avatar_color,
                    'role'    => 'workspace',
                ])->values()->all(),
            'allWorkspaces' => $allWorkspaces->map(fn($ws) => [
                'id' => $ws->id,
                'name' => $ws->name,
                'slug' => $ws->slug,
                'boards' => $ws->boards->map(fn($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'slug' => $b->slug,
                    'workspace_id' => $b->workspace_id,
                    'is_starred' => (bool) $b->is_starred,
                    'background_type' => $b->background_type,
                    'background_value' => $b->background_value,
                    'cover_type' => $b->cover_type,
                    'cover_value' => $b->cover_value,
                    'lists' => $b->activeLists->map(fn($list) => [
                        'id' => $list->id,
                        'name' => $list->name,
                        'position' => $list->position,
                    ])->values()->all(),
                    'members' => $b->members->map(fn($m) => [
                        'id' => $m->id,
                        'name' => $m->name,
                        'avatar' => $m->avatar_url,
                    ])->values()->all(),
                ])->values()->all(),
            ])->values()->all(),
        ];

        $externalTools = Setting::externalTools();

        return view('boards.show', compact('board', 'workspaceBoards', 'allWorkspaces', 'boardData', 'externalTools'));
    }

    // ── Board CRUD ────────────────────────────────────────────────────────────

    /** Store a new board. */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->canCreateBoards(), 403, 'Unauthorized action.');

        $validated = $request->validate([
            'workspace_id'     => ['required', 'exists:workspaces,id'],
            'name'             => ['nullable', 'string', 'max:100'],
            'background_type'  => ['required', 'in:color,gradient,image'],
            'background_value' => ['nullable', 'string', 'max:2048'],
            'background_image_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:8192'],
            'visibility'       => ['required', 'in:private,workspace,public'],
            'template'         => ['nullable', 'string', 'in:normal,workflow,planning'],
            'template_month'   => ['nullable', 'string'],
            'template_year'    => ['nullable', 'string'],
        ]);

        $backgroundValue = $validated['background_value'] ?? '';
        if ($validated['background_type'] === 'image' && $request->hasFile('background_image_file')) {
            $uploadedUrl = $this->handleBackgroundImageUpload($request->file('background_image_file'));
            if ($uploadedUrl) {
                $backgroundValue = $uploadedUrl;
            }
        }

        $this->authorizeWorkspace($validated['workspace_id']);

        $boardName = $validated['name'] ?? 'Untitled Board';
        if (in_array($validated['template'] ?? '', ['workflow', 'planning'])) {
            $month = $validated['template_month'] ?? '';
            $year = $validated['template_year'] ?? '';
            $prefix = $validated['template'] === 'workflow' ? 'Workflow board' : 'Planning board';
            if ($month && $year) {
                $boardName = "{$prefix} - {$month}";
                if ($year !== date('Y')) {
                    $boardName .= " {$year}"; // Only append year if it's not the current year, or just append it anyway? The screenshot shows "Workflow board - June". Let's match: "Workflow board - June" if year is current, else "Workflow board - June 2026"
                }
            } else {
                $boardName = $prefix;
            }
        }

        $position = Board::where('workspace_id', $validated['workspace_id'])->count();

        $board = Board::create([
            'workspace_id' => $validated['workspace_id'],
            'background_type' => $validated['background_type'],
            'background_value' => $backgroundValue,
            'cover_type' => $validated['background_type'],
            'cover_value' => $backgroundValue,
            'visibility' => $validated['visibility'],
            'name' => $boardName,
            'created_by' => auth()->id(),
            'position'   => $position,
        ]);

        if (in_array($validated['template'] ?? '', ['workflow', 'planning'])) {
            $prefix = $validated['template'] === 'workflow' ? 'Workflow board' : 'Planning board';
            // First: find the most recent template board in the SAME workspace (so we copy the correct members)
            $templateBoard = Board::where('name', 'like', $prefix . '%')
                ->where('id', '!=', $board->id)
                ->where('workspace_id', $board->workspace_id)
                ->with(['lists', 'labels', 'members'])
                ->orderBy('created_at', 'desc')
                ->first();

            // Fallback: if no template found in the same workspace, search globally for structure only
            if (!$templateBoard) {
                $templateBoard = Board::where('name', 'like', $prefix . '%')
                    ->where('id', '!=', $board->id)
                    ->with(['lists', 'labels', 'members'])
                    ->orderBy('created_at', 'asc')
                    ->first();
            }

            if ($templateBoard) {
                $listMap = [];
                foreach ($templateBoard->lists as $list) {
                    if (($validated['template'] ?? '') === 'workflow' && strtolower($list->name) === 'urgent') {
                        continue; // Skip Urgent list for Workflow templates
                    }

                    $newList = $board->lists()->create([
                        'name' => $list->name,
                        'position' => $list->position,
                        'color' => $list->color,
                        'wip_limit' => $list->wip_limit,
                    ]);
                    $listMap[$list->id] = $newList->id;
                }
                foreach ($templateBoard->labels as $label) {
                    $board->labels()->create([
                        'name' => $label->name,
                        'color' => $label->color,
                    ]);
                }

                // Copy board members ONLY if the template board belongs to the same workspace
                if ($templateBoard->workspace_id == $board->workspace_id) {
                    foreach ($templateBoard->members as $member) {
                        // Make sure member is in target workspace
                        if (!$board->workspace->hasMember($member->id)) {
                            $board->workspace->members()->syncWithoutDetaching([
                                $member->id => ['role' => 'member']
                            ]);
                        }
                        // Sync member to board with their role
                        $board->members()->syncWithoutDetaching([
                            $member->id => ['role' => $member->pivot->role ?? 'member']
                        ]);
                    }
                }

                // Copy automations from the template board
                $templateAutomations = \App\Models\BoardAutomation::where('board_id', $templateBoard->id)->get();
                $newMonthYear = $request->template_month && $request->template_year ? $request->template_month . ' ' . $request->template_year : null;

                foreach ($templateAutomations as $auto) {
                    // Skip automations that rely on a skipped list on this board
                    if ($auto->trigger_board_id == $templateBoard->id && !isset($listMap[$auto->trigger_list_id])) continue;
                    if ($auto->target_board_id == $templateBoard->id && !isset($listMap[$auto->target_list_id])) continue;

                    $newTriggerBoardId = ($auto->trigger_board_id == $templateBoard->id) ? $board->id : $auto->trigger_board_id;
                    $newTriggerListId = isset($listMap[$auto->trigger_list_id]) ? $listMap[$auto->trigger_list_id] : $auto->trigger_list_id;
                    $newTargetBoardId = ($auto->target_board_id == $templateBoard->id) ? $board->id : $auto->target_board_id;
                    $newTargetListId = isset($listMap[$auto->target_list_id]) ? $listMap[$auto->target_list_id] : $auto->target_list_id;

                    // Intelligent Cross-Board Reference Resolution
                    if ($auto->target_board_id != $templateBoard->id && $auto->target_board_id && $newMonthYear) {
                        $oldTargetBoard = \App\Models\Board::find($auto->target_board_id);
                        if ($oldTargetBoard) {
                            $baseNamePattern = trim(preg_replace('/[–-]?\s*[A-Za-z]+\s+20\d{2}$/u', '', $oldTargetBoard->name));
                            if ($baseNamePattern) {
                                $resolvedBoard = \App\Models\Board::where('workspace_id', $board->workspace_id)
                                    ->where('name', 'like', $baseNamePattern . '%')
                                    ->where('name', 'like', '%' . $newMonthYear . '%')
                                    ->first();
                                
                                if ($resolvedBoard) {
                                    $newTargetBoardId = $resolvedBoard->id;
                                    $oldList = \App\Models\BoardList::find($auto->target_list_id);
                                    if ($oldList) {
                                        $resolvedList = \App\Models\BoardList::where('board_id', $resolvedBoard->id)
                                            ->where('name', $oldList->name)->first();
                                        if ($resolvedList) {
                                            $newTargetListId = $resolvedList->id;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    \App\Models\BoardAutomation::create([
                        'board_id' => $board->id,
                        'trigger_type' => $auto->trigger_type,
                        'trigger_word' => $auto->trigger_word,
                        'trigger_board_id' => $newTriggerBoardId,
                        'trigger_list_id' => $newTriggerListId,
                        'target_board_id' => $newTargetBoardId,
                        'target_list_id' => $newTargetListId,
                        'target_assignee_id' => $auto->target_assignee_id,
                        'target_assignee_role' => $auto->target_assignee_role,
                    ]);
                }

                // Retroactive Linkup: If THIS newly created board acts as a target for other boards created THIS month
                if ($newMonthYear) {
                    $automationsPointingToTemplate = \App\Models\BoardAutomation::where('target_board_id', $templateBoard->id)
                        ->where('board_id', '!=', $templateBoard->id)
                        ->get();

                    foreach ($automationsPointingToTemplate as $autoToUpdate) {
                        $ownerBoard = \App\Models\Board::find($autoToUpdate->board_id);
                        if ($ownerBoard && str_contains($ownerBoard->name, $newMonthYear) && $ownerBoard->workspace_id == $board->workspace_id) {
                            $oldList = \App\Models\BoardList::find($autoToUpdate->target_list_id);
                            if ($oldList) {
                                $matchedList = \App\Models\BoardList::where('board_id', $board->id)->where('name', $oldList->name)->first();
                                if ($matchedList) {
                                    $autoToUpdate->update([
                                        'target_board_id' => $board->id,
                                        'target_list_id' => $matchedList->id
                                    ]);
                                }
                            }
                        }
                    }
                }
            } else {
                $defaults = ['To Do', 'In Progress', 'Done'];
                foreach ($defaults as $i => $name) {
                    $board->lists()->create(['name' => $name, 'position' => $i]);
                }
            }
        } else {
            $defaults = ['To Do', 'In Progress', 'Done'];
            foreach ($defaults as $i => $name) {
                $board->lists()->create(['name' => $name, 'position' => $i]);
            }
        }

        return redirect()->route('boards.show', $board)
            ->with('success', "Board \"{$board->name}\" created.");
    }

    /** Update board settings (name, background, etc.). */
    public function toggleHidden(Board $board): JsonResponse
    {
        $user = auth()->user();
        if (! $user->hasAnyRole(['super-admin', 'admin-digital'])) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $board->update(['is_hidden' => !$board->is_hidden]);
        return response()->json(['success' => true, 'is_hidden' => $board->is_hidden]);
    }

    /** Update board settings (name, background, etc.). */
    public function update(Request $request, Board $board): JsonResponse
    {
        $this->authorizeBoard($board);

        $validated = $request->validate([
            'workspace_id'     => ['sometimes', 'integer', 'exists:workspaces,id'],
            'name'             => ['sometimes', 'required', 'string', 'max:100'],
            'description'      => ['nullable', 'string', 'max:5000'],
            'background_type'  => ['sometimes', 'in:color,gradient,image'],
            'background_value' => ['sometimes', 'string', 'max:2048'],
            'visibility'       => ['sometimes', 'in:private,workspace,public'],
            'member_permissions' => ['sometimes', 'in:admins,members,workspace'],
            'card_covers_enabled' => ['sometimes', 'boolean'],
            'notifications_enabled' => ['sometimes', 'boolean'],
            'browser_notifications_enabled' => ['sometimes', 'boolean'],

            'is_starred'       => ['sometimes', 'boolean'],
            'is_archived'      => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('background_type', $validated) || array_key_exists('background_value', $validated)) {
            $backgroundType = $validated['background_type'] ?? $board->background_type;
            $backgroundValue = $validated['background_value'] ?? $board->background_value;

            if (! is_string($backgroundValue) || trim($backgroundValue) === '') {
                return response()->json(['errors' => ['background_value' => ['Choose a board background.']]], 422);
            }

            if ($backgroundType === 'image' && ! $this->isAllowedBackgroundImageValue($backgroundValue)) {
                return response()->json(['errors' => ['background_value' => ['Enter a valid background image URL.']]], 422);
            }

            if ($backgroundType === 'color' && ! preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $backgroundValue)) {
                return response()->json(['errors' => ['background_value' => ['Choose a valid hex background color.']]], 422);
            }
        }

        $settingsFields = [
            'workspace_id',
            'name',
            'description',
            'background_type',
            'background_value',
            'visibility',
            'member_permissions',
            'card_covers_enabled',
            'notifications_enabled',
            'browser_notifications_enabled',

            'is_archived',
        ];

        if (array_intersect(array_keys($validated), $settingsFields) && ! $this->canManageBoard(auth()->user(), $board)) {
            return response()->json(['error' => 'You do not have permission to update board settings.'], 403);
        }

        if (isset($validated['workspace_id'])) {
            $this->authorizeWorkspace((int) $validated['workspace_id']);

            if ((int) $validated['workspace_id'] !== (int) $board->workspace_id) {
                $validated['position'] = (int) Board::where('workspace_id', $validated['workspace_id'])->max('position') + 1;
            }
        }

        $trackedFields = array_intersect(array_keys($validated), $settingsFields);
        $before = $board->only($trackedFields);

        $board->update($validated);
        $board = $board->fresh(['workspace']);

        $after = $board->only($trackedFields);
        $changed = [];
        foreach ($trackedFields as $field) {
            if (($before[$field] ?? null) != ($after[$field] ?? null)) {
                $changed[] = $field;
            }
        }

        // Auto delete unused background image
        if (in_array('background_value', $changed)) {
            $oldBg = $before['background_value'] ?? null;
            if ($oldBg && $oldBg !== $board->cover_value) {
                $this->deleteStoredBoardBackground($oldBg);
            }
        }

        if ($changed) {
            $action = in_array('is_archived', $changed, true)
                ? ($board->is_archived ? 'archived' : 'unarchived')
                : 'settings_updated';

            $description = $action === 'archived'
                ? "archived board **{$board->name}**"
                : ($action === 'unarchived'
                    ? "unarchived board **{$board->name}**"
                    : 'updated board settings: **' . implode(', ', array_map(fn($field) => $this->settingLabel($field), $changed)) . '**');

            $this->logBoardActivity($board, $action, $description, [
                'changed' => $changed,
                'before' => $before,
                'after' => $after,
            ]);
        }

        return response()->json(['message' => 'Board updated.', 'board' => $this->boardPayload($board)]);
    }

    /** Helper to handle background image uploads and convert to WebP */
    private function handleBackgroundImageUpload($file): ?string
    {
        if (!$file || !$file->isValid()) return null;

        $extension = strtolower($file->extension());
        $image = match($extension) {
            'jpeg', 'jpg' => @imagecreatefromjpeg($file->path()),
            'png' => @imagecreatefrompng($file->path()),
            'gif' => @imagecreatefromgif($file->path()),
            'webp' => @imagecreatefromwebp($file->path()),
            default => null
        };

        if ($image) {
            $filename = \Illuminate\Support\Str::random(40) . '.webp';
            $path = "board-backgrounds/{$filename}";
            \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory('board-backgrounds');
            
            // For PNG/GIF transparency
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);
            
            imagewebp($image, \Illuminate\Support\Facades\Storage::disk('public')->path($path), 85);
            imagedestroy($image);
            
            return \Illuminate\Support\Facades\Storage::url($path);
        }

        // Fallback if conversion fails
        $path = $file->store("board-backgrounds", 'public');
        return \Illuminate\Support\Facades\Storage::url($path);
    }

    /** Basic update for board name and background from the workspaces view. */
    public function updateBoardBasic(Request $request, Board $board): RedirectResponse
    {
        $this->authorizeBoard($board);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'background_type' => ['required', 'in:color,image'],
            'background_value' => ['nullable', 'string', 'max:2048'],
            'background_image_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:8192'],
        ]);

        $backgroundValue = $validated['background_value'] ?? '';

        if ($validated['background_type'] === 'image') {
            if ($request->hasFile('background_image_file')) {
                $uploadedUrl = $this->handleBackgroundImageUpload($request->file('background_image_file'));
                if ($uploadedUrl) {
                    $backgroundValue = $uploadedUrl;
                }
            } elseif (! $this->isAllowedBackgroundImageValue($backgroundValue)) {
                return back()->with('error', 'Enter a valid background image URL or upload an image.');
            }
        } elseif ($validated['background_type'] === 'color' && ! preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $backgroundValue)) {
            return back()->with('error', 'Choose a valid hex background color.');
        }

        $oldCover = $board->cover_value;

        $board->update([
            'name' => $validated['name'],
            'cover_type' => $validated['background_type'],
            'cover_value' => $backgroundValue,
        ]);

        if ($oldCover && $oldCover !== $backgroundValue && $oldCover !== $board->background_value) {
            $this->deleteStoredBoardBackground($oldCover);
        }

        return back()->with('success', 'Board updated successfully.');
    }

    /** Upload and apply a board background image. */
    public function uploadBackground(Request $request, Board $board): JsonResponse
    {
        $this->authorizeBoard($board);

        if (! $this->canManageBoard(auth()->user(), $board)) {
            return response()->json(['error' => 'You do not have permission to update board background.'], 403);
        }

        $validated = $request->validate([
            'background_image' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:8192'],
        ]);

        $path = $validated['background_image']->store("board-backgrounds/{$board->id}", 'public');
        $oldBackground = $board->background_value;

        $board->update([
            'background_type' => 'image',
            'background_value' => Storage::url($path),
        ]);

        if ($oldBackground !== $board->cover_value) {
            $this->deleteStoredBoardBackground($oldBackground);
        }

        $board = $board->fresh(['workspace']);
        $this->logBoardActivity($board, 'background_updated', "updated board background for **{$board->name}**", [
            'background_type' => 'image',
            'background_value' => $board->background_value,
        ]);

        return response()->json([
            'message' => 'Board background image uploaded.',
            'board' => $this->boardPayload($board),
        ], 201);
    }

    /** Copy a board, optionally including active lists and cards. */
    public function copy(Request $request, Board $board): JsonResponse
    {
        $this->authorizeBoard($board);

        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'workspace_id'  => ['nullable', 'exists:workspaces,id'],
            'include_cards' => ['sometimes', 'boolean'],
        ]);

        $workspaceId = $validated['workspace_id'] ?? $board->workspace_id;
        $this->authorizeWorkspace($workspaceId);

        $position = Board::where('workspace_id', $workspaceId)->max('position') + 1;

        $copy = Board::create([
            'workspace_id'      => $workspaceId,
            'name'              => $validated['name'],
            'description'       => $board->description,
            'background_type'   => $board->background_type,
            'background_value'  => $board->background_value,
            'visibility'        => $board->visibility,
            'member_permissions' => $board->member_permissions ?? 'members',
            'card_covers_enabled' => $board->card_covers_enabled,
            'notifications_enabled' => $board->notifications_enabled,
            'browser_notifications_enabled' => $board->browser_notifications_enabled,
            'created_by'        => auth()->id(),
            'position'          => $position,
        ]);

        $labelMap = [];
        foreach ($board->labels as $label) {
            $newLabel = $copy->labels()->create([
                'name'  => $label->name,
                'color' => $label->color,
            ]);
            $labelMap[$label->id] = $newLabel->id;
        }

        $sourceLists = $board->lists()
            ->where('is_archived', false)
            ->with(['allCards' => fn($q) => $q->where('is_archived', false)->with('labels')])
            ->get();

        $listMap = [];
        foreach ($sourceLists as $sourceList) {
            $newList = $copy->lists()->create([
                'name'       => $sourceList->name,
                'position'   => $sourceList->position,
                'color'      => $sourceList->color,
                'wip_limit'  => $sourceList->wip_limit,
            ]);
            $listMap[$sourceList->id] = $newList->id;

            if (!($validated['include_cards'] ?? true)) {
                continue;
            }

            foreach ($sourceList->allCards as $sourceCard) {
                $newCard = Card::create([
                    'board_id'      => $copy->id,
                    'board_list_id' => $newList->id,
                    'title'         => $sourceCard->title,
                    'description'   => $sourceCard->description,
                    'label'         => $sourceCard->label,
                    'sub_label'     => $sourceCard->sub_label,
                    'priority'      => $sourceCard->priority?->value ?? $sourceCard->priority,
                    'status'        => $sourceCard->status?->value ?? $sourceCard->status,
                    'position'      => $sourceCard->position,
                    'deadline'      => $sourceCard->deadline,
                    'due_at'        => $sourceCard->due_at,
                    'start_date'    => $sourceCard->start_date,
                    'due_time'      => $sourceCard->due_time,
                    'reminder'      => $sourceCard->reminder,
                    'recurring'     => $sourceCard->recurring,
                    'cover_image'   => $sourceCard->cover_image,
                    'created_by'    => auth()->id(),
                ]);

                $newCard->labels()->sync(
                    $sourceCard->labels
                        ->pluck('id')
                        ->map(fn($id) => $labelMap[$id] ?? null)
                        ->filter()
                        ->values()
                        ->all()
                );
            }
        }

        // Copy automations when copying board
        $sourceAutomations = \App\Models\BoardAutomation::where('board_id', $board->id)->get();
        foreach ($sourceAutomations as $auto) {
            $newTriggerBoardId = ($auto->trigger_board_id == $board->id) ? $copy->id : $auto->trigger_board_id;
            $newTriggerListId = isset($listMap[$auto->trigger_list_id]) ? $listMap[$auto->trigger_list_id] : $auto->trigger_list_id;
            $newTargetBoardId = ($auto->target_board_id == $board->id) ? $copy->id : $auto->target_board_id;
            $newTargetListId = isset($listMap[$auto->target_list_id]) ? $listMap[$auto->target_list_id] : $auto->target_list_id;

            \App\Models\BoardAutomation::create([
                'board_id' => $copy->id,
                'trigger_type' => $auto->trigger_type,
                'trigger_word' => $auto->trigger_word,
                'trigger_board_id' => $newTriggerBoardId,
                'trigger_list_id' => $newTriggerListId,
                'target_board_id' => $newTargetBoardId,
                'target_list_id' => $newTargetListId,
                'target_assignee_id' => $auto->target_assignee_id,
                'target_assignee_role' => $auto->target_assignee_role,
            ]);
        }

        // Copy board members when copying board
        foreach ($board->members as $member) {
            if (!$copy->workspace->hasMember($member->id)) {
                $copy->workspace->members()->syncWithoutDetaching([
                    $member->id => ['role' => 'member']
                ]);
            }
            $copy->members()->syncWithoutDetaching([
                $member->id => ['role' => $member->pivot->role ?? 'member']
            ]);
        }

        return response()->json([
            'message' => "Board copied as \"{$copy->name}\".",
            'board' => [
                'id' => $copy->id,
                'name' => $copy->name,
                'slug' => $copy->slug,
                'url' => route('boards.show', $copy),
            ],
        ], 201);
    }

    /** Delete a board permanently. */
    public function destroy(Request $request, Board $board): JsonResponse|RedirectResponse
    {
        $this->authorizeBoard($board);

        if (! $this->canDeleteBoard(auth()->user(), $board)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Only board admins can delete this board.'], 403);
            }

            abort(403, 'Only board admins can delete this board.');
        }

        $boardName = $board->name;
        $this->logBoardActivity($board, 'deleted', "deleted board **{$boardName}**");
        $board->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => "Board \"{$boardName}\" deleted."]);
        }

        return redirect()->route('boards.workspaces')
            ->with('success', "Board \"{$boardName}\" deleted.");
    }

    // ── List AJAX endpoints ───────────────────────────────────────────────────

    /** Add a new list to a board. */
    public function storeList(Request $request, Board $board): JsonResponse
    {
        $this->authorizeBoard($board);

        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'color'     => ['nullable', 'string', 'max:7'],
            'wip_limit' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $position = $board->lists()->max('position') + 1;

        $list = $board->lists()->create([
            ...$validated,
            'position' => $position,
        ]);

        try {
            \App\Notifications\BoardActivityNotification::send($board, 'list_created', "created list **{$list->name}**");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Notification failed: " . $e->getMessage());
        }

        return response()->json(['list' => $list, 'message' => 'List created.'], 201);
    }

    /** Rename a list. */
    public function updateList(Request $request, BoardList $list): JsonResponse
    {
        $this->authorizeBoard($list->board);

        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:100'],
            'color'       => ['nullable', 'string', 'max:7'],
            'wip_limit'   => ['nullable', 'integer', 'min:0'],
            'is_archived' => ['sometimes', 'boolean'],
        ]);

        $oldName = $list->name;
        $list->update($validated);

        try {
            if ($request->has('name') && $request->name !== $oldName) {
                \App\Notifications\BoardActivityNotification::send($list->board, 'list_renamed', "renamed list **{$oldName}** to **{$list->name}**");
            } elseif ($request->has('is_archived')) {
                $action = $list->is_archived ? 'list_archived' : 'list_unarchived';
                $desc = $list->is_archived ? "archived list **{$list->name}**" : "unarchived list **{$list->name}**";
                \App\Notifications\BoardActivityNotification::send($list->board, $action, $desc);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Notification failed: " . $e->getMessage());
        }

        return response()->json(['list' => $list, 'message' => 'List updated.']);
     }

     /** Delete a list permanently. */
     public function destroyList(BoardList $list): JsonResponse
     {
         $this->authorizeBoard($list->board);
         
         try {
             \App\Notifications\BoardActivityNotification::send($list->board, 'list_deleted', "permanently deleted list **{$list->name}**");
         } catch (\Throwable $e) {
             \Illuminate\Support\Facades\Log::error("Notification failed: " . $e->getMessage());
         }

         // Delete all cards inside this list
         $list->cards()->delete();
         $list->delete();

         return response()->json(['message' => 'List and its cards permanently deleted.']);
     }

    /** Reorder lists via drag-and-drop. */
    public function reorderLists(Request $request, Board $board): JsonResponse
    {
        $this->authorizeBoard($board);

        $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer', 'exists:board_lists,id'],
        ]);

        foreach ($request->order as $position => $listId) {
            BoardList::where('id', $listId)
                     ->where('board_id', $board->id)
                     ->update(['position' => $position]);
        }

        return response()->json(['message' => 'Lists reordered.']);
    }

    /** Reorder / move cards via drag-and-drop. */
    public function reorderCards(Request $request, Board $board): JsonResponse
    {
        $this->authorizeBoard($board);

        $request->validate([
            'list_id'  => ['required', 'exists:board_lists,id'],
            'order'    => ['required', 'array'],
            'order.*'  => ['integer', 'exists:cards,id'],
        ]);

        foreach ($request->order as $position => $cardId) {
            Card::where('id', $cardId)
                ->where('board_id', $board->id)
                ->update([
                    'board_list_id' => $request->list_id,
                    'position'      => $position,
                ]);
        }

        return response()->json(['message' => 'Cards reordered.']);
    }

    // ── Board Member Management ──────────────────────────────────────────────

    /** Add a member to a board. */
    public function addMember(Request $request, Board $board): JsonResponse
    {
        $user = auth()->user();
        if (!$this->canManageBoard($user, $board)) {
            return response()->json(['error' => 'You do not have permission to manage board members.'], 403);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role'    => ['sometimes', 'in:admin,member,observer'],
        ]);

        $targetUser = \App\Models\User::findOrFail($validated['user_id']);

        // Verify that the user is a member of the workspace first! (Unless they are digital team/boss)
        if (!$board->workspace->hasMember($targetUser->id) && !$targetUser->hasAnyRole(['digital-team', 'admin-digital', 'admin', 'boss', 'supervisor', 'staff'])) {
            return response()->json(['error' => 'User must be a member of the workspace first.'], 422);
        }

        // Add
        $board->members()->syncWithoutDetaching([
            $targetUser->id => ['role' => $validated['role'] ?? 'member']
        ]);

        return response()->json([
            'message' => "{$targetUser->name} added to board successfully.",
            'members' => $board->members()->get()->map(fn($u) => [
                'id'     => $u->id,
                'name'   => $u->name,
                'email'  => $u->email,
                'avatar' => $u->avatar_url,
                'initials' => $u->avatar_initials,
                'avatar_color' => $u->avatar_color,
            ]),
        ]);
    }

    /** Remove a member from a board. */
    public function removeMember(Board $board, \App\Models\User $user): JsonResponse
    {
        $currentUser = auth()->user();
        if ($currentUser->id !== $user->id && !$this->canManageBoard($currentUser, $board)) {
            return response()->json(['error' => 'You do not have permission to manage board members.'], 403);
        }

        // Cannot remove the board creator
        if ($board->created_by === $user->id) {
            return response()->json(['error' => 'Cannot remove the board creator.'], 422);
        }

        $board->members()->detach($user->id);

        return response()->json([
            'message' => "{$user->name} removed from board.",
            'members' => $board->members()->get()->map(fn($u) => [
                'id'     => $u->id,
                'name'   => $u->name,
                'email'  => $u->email,
                'avatar' => $u->avatar_url,
                'initials' => $u->avatar_initials,
                'avatar_color' => $u->avatar_color,
            ]),
        ]);
    }

    private function canManageBoard(\App\Models\User $user, Board $board): bool
    {
        // Super Admin, Digital Admin, and Supervisors manage everything
        if ($user->hasAnyRole(['super-admin', 'admin-digital']) || $user->isSupervisorRole()) {
            return true;
        }

        if ($board->created_by === $user->id || $board->workspace?->owner_id === $user->id) {
            return true;
        }

        if ($board->members()->where('users.id', $user->id)->wherePivot('role', 'admin')->exists()) {
            return true;
        }

        return false;
    }

    private function canDeleteBoard(User $user, Board $board): bool
    {
        if ($user->hasAnyRole(['super-admin', 'admin-digital'])) {
            return true;
        }

        if ($board->created_by === $user->id) {
            return true;
        }

        return $board->members()->where('users.id', $user->id)->wherePivot('role', 'admin')->exists();
    }

    private function settingLabel(string $field): string
    {
        return [
            'workspace_id' => 'workspace',
            'background_type' => 'background type',
            'background_value' => 'background',
            'member_permissions' => 'member permissions',
            'card_covers_enabled' => 'card cover setting',
            'notifications_enabled' => 'notifications',
            'browser_notifications_enabled' => 'browser notifications',
            'is_archived' => 'archive status',
        ][$field] ?? str_replace('_', ' ', $field);
    }

    private function isAllowedBackgroundImageValue(string $value): bool
    {
        $value = trim($value);

        return (bool) filter_var($value, FILTER_VALIDATE_URL)
            || Str::startsWith($value, ['/storage/', 'storage/']);
    }

    private function deleteStoredBoardBackground(?string $value): void
    {
        if (! $value) {
            return;
        }

        $normalized = ltrim(parse_url($value, PHP_URL_PATH) ?: $value, '/');

        if (! Str::startsWith($normalized, 'storage/board-backgrounds/')) {
            return;
        }

        $relativePath = Str::after($normalized, 'storage/');
        Storage::disk('public')->delete($relativePath);
    }

    private function boardPayload(Board $board): array
    {
        return [
            'id' => $board->id,
            'name' => $board->name,
            'slug' => $board->slug,
            'description' => $board->description,
            'workspace_id' => $board->workspace_id,
            'visibility' => $board->visibility,
            'background_type' => $board->background_type,
            'background_value' => $board->background_value,
            'member_permissions' => $board->member_permissions ?? 'members',
            'card_covers_enabled' => (bool) ($board->card_covers_enabled ?? true),
            'notifications_enabled' => (bool) ($board->notifications_enabled ?? true),
            'browser_notifications_enabled' => (bool) ($board->browser_notifications_enabled ?? false),

            'is_starred' => (bool) $board->is_starred,
            'is_archived' => (bool) $board->is_archived,
            'workspace_name' => $board->workspace?->name,
            'can_manage_board' => $this->canManageBoard(auth()->user(), $board),
            'can_delete_board' => $this->canDeleteBoard(auth()->user(), $board),
        ];
    }

    private function logBoardActivity(Board $board, string $action, string $description, array $properties = []): void
    {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => "board.{$action}",
            'module' => 'kanban',
            'description' => $description,
            'subject_type' => Board::class,
            'subject_id' => $board->id,
            'properties' => $properties,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        try {
            BoardActivityNotification::send($board, "board_{$action}", $description, null, true);
        } catch (\Throwable $e) {
            Log::error('Failed sending board notification: ' . $e->getMessage());
        }
    }

    /** Search members for the member picker (board + workspace). */
    public function searchMembers(Request $request, Board $board): JsonResponse
    {
        $this->authorizeBoard($board);
        $q = strtolower(trim($request->get('q', '')));

        $mapUser = fn($u, $source) => [
            'id'       => $u->id,
            'name'     => $u->name,
            'email'    => $u->email,
            'avatar'   => $u->avatar_url,
            'initials' => $u->avatar_initials,
            'avatar_color' => $u->avatar_color,
            'source'   => $source,
        ];

        $boardMemberIds = $board->members->pluck('id');

        $boardMembers = $board->members
            ->filter(fn($u) => !$q || str_contains(strtolower($u->name), $q) || str_contains(strtolower($u->email), $q))
            ->map(fn($u) => $mapUser($u, 'board'))
            ->values();

        $workspaceMembers = $board->workspace->members
            ->filter(fn($u) => !$boardMemberIds->contains($u->id))
            ->filter(fn($u) => !$q || str_contains(strtolower($u->name), $q) || str_contains(strtolower($u->email), $q))
            ->map(fn($u) => $mapUser($u, 'workspace'))
            ->values();

        return response()->json([
            'board_members'     => $boardMembers,
            'workspace_members' => $workspaceMembers,
        ]);
    }

    /** Fetch all recent activity logs for a board. */
    public function activities(Board $board): JsonResponse
    {
        $this->authorizeBoard($board);

        $cardIds = $board->cards()->pluck('id');

        $activities = ActivityLog::with('user')
            ->where(function ($query) use ($cardIds, $board) {
                $query->where(function ($cardQuery) use ($cardIds) {
                    $cardQuery->where('subject_type', Card::class)
                        ->whereIn('subject_id', $cardIds);
                })->orWhere(function ($boardQuery) use ($board) {
                    $boardQuery->where('subject_type', Board::class)
                        ->where('subject_id', $board->id);
                });
            })
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn($log) => [
                'id'          => $log->id,
                'user_name'   => $log->user?->name ?? 'System',
                'user_avatar' => $log->user?->avatar_url ?? User::initialsAvatarDataUri('System', '#64748b'),
                'user_initials' => $log->user?->avatar_initials ?? 'SY',
                'user_avatar_color' => $log->user?->avatar_color ?? '#64748b',
                'action'      => str_replace(['card.', 'board.'], '', $log->action),
                'description' => $log->description,
                'time_ago'    => $log->created_at ? $log->created_at->format('M j, Y, g:i A') : 'N/A',
            ]);

        return response()->json(['activities' => $activities]);
    }

    /** Fetch archived cards and lists for the board menu. */
    public function archivedItems(Board $board): JsonResponse
    {
        $this->authorizeBoard($board);

        $lists = $board->lists()
            ->where('is_archived', true)
            ->withCount('allCards')
            ->orderBy('position')
            ->get()
            ->map(fn($list) => [
                'id' => $list->id,
                'name' => $list->name,
                'card_count' => $list->all_cards_count,
                'archived_at' => $list->updated_at?->diffForHumans(),
            ]);

        $cards = $board->cards()
            ->where('is_archived', true)
            ->with('boardList:id,name')
            ->latest('updated_at')
            ->get()
            ->map(fn($card) => [
                'id' => $card->id,
                'title' => $card->title,
                'list_name' => $card->boardList?->name ?? 'No list',
                'archived_at' => $card->updated_at?->diffForHumans(),
            ]);

        return response()->json([
            'lists' => $lists,
            'cards' => $cards,
        ]);
    }

    // ── Workspace Member Management ───────────────────────────────────────────

    public function addWorkspaceMember(Workspace $workspace, Request $request): JsonResponse
    {
        $this->authorizeWorkspace($workspace->id);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        if (! $workspace->members()->where('users.id', $validated['user_id'])->exists()) {
            $workspace->members()->attach($validated['user_id']);
        }

        return response()->json(['message' => 'Member added to workspace']);
    }

    public function removeWorkspaceMember(Workspace $workspace, User $user): JsonResponse
    {
        $this->authorizeWorkspace($workspace->id);

        $workspace->members()->detach($user->id);

        return response()->json(['message' => 'Member removed from workspace']);
    }

    // ── Private Auth Helpers ──────────────────────────────────────────────────

    private function authorizeBoard(Board $board, string $minRole = 'member'): void
    {
        $user = auth()->user();

        // Super-admins and system admins bypass board access checks
        if ($user->hasAnyRole(['super-admin', 'admin-digital'])) return;

        // If explicitly a member of the board, they have access
        if ($board->hasMember($user->id)) return;

        // Boss and other supervisor/QC roles also bypass the membership check
        $isQc = str_contains(strtolower($user->team_role ?? ''), 'qc');
        $isBypassed = $user->hasAnyRole(['admin', 'supervisor', 'boss']) || $isQc;
        if ($isBypassed) return;

        abort_unless($board->workspace->hasMember($user->id), 403, 'You are not a member of this workspace.');

        // If they are a normal member, check board membership (must be explicitly added)
        if ($user->hasAnyRole(['digital-team', 'sales-crm'])) {
            abort_unless($board->hasMember($user->id), 403, 'You do not have permission to access this board.');
        }
    }

    private function authorizeWorkspace(int $workspaceId): void
    {
        $user = auth()->user();
        if ($user->hasAnyRole(['super-admin', 'admin-digital'])) return;

        $ws = Workspace::findOrFail($workspaceId);
        abort_unless($ws->hasMember($user->id), 403, 'You are not a member of this workspace.');
    }

    /** Helper to get all workspaces and boards a user can access. */
    private function getAuthorizedWorkspaces(\App\Models\User $user)
    {
        if ($user->hasAnyRole(['super-admin', 'admin-digital'])) {
            $workspaces = Workspace::with([
                'boards' => fn($q) => $q->where('is_archived', false)->where('is_hidden', false)->orderBy('position'),
                'boards.activeLists' => fn($q) => $q->where('is_archived', false)->orderBy('position'),
                'boards.members:id,name,avatar',
            ])
                ->where('is_active', true)
                ->orderBy('position')
                ->orderBy('id')
                ->get();
        } else {
            $allActiveWorkspaces = Workspace::with([
                'boards' => fn($q) => $q->where('is_archived', false)->where('is_hidden', false)->orderBy('position'),
                'boards.activeLists' => fn($q) => $q->where('is_archived', false)->orderBy('position'),
                'boards.members:id,name,avatar',
            ])
                ->where('is_active', true)
                ->orderBy('position')
                ->orderBy('id')
                ->get();
            $workspaces = $allActiveWorkspaces->filter(function ($ws) use ($user) {
                if ($ws->hasMember($user->id)) return true;
                foreach ($ws->boards as $board) {
                    if ($board->hasMember($user->id)) return true;
                }
                return false;
            });
        }

        foreach ($workspaces as $workspace) {
            $workspace->setRelation('boards', $workspace->boards->filter(function ($board) use ($user) {
                $isQc = str_contains(strtolower($user->team_role ?? ''), 'qc');
                $isBypassed = $user->hasAnyRole(['super-admin', 'admin-digital', 'admin', 'supervisor', 'boss']) || $isQc;

                if ($isBypassed) {
                    return true;
                }

                if ($user->hasAnyRole(['digital-team', 'sales-crm'])) {
                    return $board->hasMember($user->id);
                }
                return true;
            }));
        }

        $isQc = str_contains(strtolower($user->team_role ?? ''), 'qc');
        $isBypassed = $user->hasAnyRole(['super-admin', 'admin-digital', 'admin', 'supervisor', 'boss']) || $isQc;
        if (!$isBypassed && $user->hasRole('digital-team')) {
            $workspaces = $workspaces->filter(function ($ws) {
                return $ws->boards->isNotEmpty();
            });
        }

        return $workspaces;
    }

    /** Create a label for this board directly from the UI. */
    public function createLabel(Request $request, Board $board): JsonResponse
    {
        $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:50'],
        ]);

        $label = \App\Models\Label::create([
            'board_id' => $board->id,
            'name'     => $request->name,
            'color'    => $request->color,
        ]);

        return response()->json([
            'message' => 'Label created successfully.',
            'label'   => ['id' => $label->id, 'name' => $label->name, 'color' => $label->color]
        ]);
    }
}
