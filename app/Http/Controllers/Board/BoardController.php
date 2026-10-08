<?php

namespace App\Http\Controllers\Board;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Label;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\BoardActivityNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        // Retrieve workspace list per user, filter out SMM boards (which belong exclusively to /smm-boards), and filter out empty workspaces
        $workspaces = $this->getAuthorizedWorkspaces($user)
            ->map(function ($ws) {
                $ws->setRelation('boards', $ws->boards->reject(function ($b) {
                    return ($b->type === 'smm') || str_contains(strtolower($b->name ?? ''), 'smm');
                })->values());
                return $ws;
            })
            ->filter(fn($ws) => $ws->boards->count() > 0 || str_contains(strtolower($ws->name), 'digital department'))
            ->values();

        // Retrieve possible members
        $possibleWorkspaceMembers = User::active()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', [
                'digital-team',
                'admin-digital',
                'super-admin',
                'Graphic Head',
                'Video Head',
                'QC',
                'Listing Head',
                'Graphic head',
                'Video head',
                'Listing head',
            ]))
            ->orderBy('name')
            ->get()
            ->values();

        $allAuthorizedWorkspaceIds = Workspace::where('is_active', true)
            ->where(function($q) use ($user) {
                if ($user->hasAnyRole(['super-admin', 'admin-digital', 'admin', 'supervisor', 'boss'])) {
                    return;
                }
                $q->where('owner_id', $user->id)
                  ->orWhereHas('members', fn($mq) => $mq->where('users.id', $user->id))
                  ->orWhereHas('boards', fn($bq) => $bq->whereHas('members', fn($bmq) => $bmq->where('users.id', $user->id)));
            })
            ->pluck('id');

        $hiddenBoardsFn = function() use ($allAuthorizedWorkspaceIds, $user) {
            $isBypassed = $user->hasAnyRole(['super-admin', 'admin-digital', 'admin', 'supervisor', 'boss']);
            return \App\Models\Board::where('is_hidden', true)
                ->where('is_archived', false)
                ->whereIn('workspace_id', $allAuthorizedWorkspaceIds)
                ->when(!$isBypassed, function($q) use ($user) {
                    $q->where(function($sq) use ($user) {
                        $sq->where('created_by', $user->id)
                           ->orWhereHas('members', fn($mq) => $mq->where('users.id', $user->id));
                    });
                })
                ->with('workspace')
                ->orderByDesc('created_at')
                ->get();
        };
        $trashedWorkspacesFn = function() {
            return \App\Models\Workspace::onlyTrashed()->get();
        };
        $trashedBoardsFn = function() {
            return \App\Models\Board::onlyTrashed()->with('workspace')->get();
        };

        return view('boards.workspaces', compact('workspaces', 'possibleWorkspaceMembers', 'hiddenBoardsFn', 'trashedWorkspacesFn', 'trashedBoardsFn'));
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
        $this->authorizeWorkspace($workspace->id);

        $workspace->delete();

        return back()->with('success', 'Workspace moved to trash.');
    }

    /** Restore a trashed workspace. */
    public function restoreWorkspace($id): RedirectResponse
    {
        $workspace = Workspace::onlyTrashed()->findOrFail($id);
        $workspace->restore();

        return back()->with('success', 'Workspace recovered successfully.');
    }

    /** Permanently delete a workspace. */
    public function forceDeleteWorkspace($id): RedirectResponse
    {
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
        $this->authorizeWorkspace($workspace->id);

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
        $this->authorizeWorkspace($workspace->id);

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

    public function reorderWorkspaces(Request $request)
    {
        $order = $request->input('order');
        if (is_array($order)) {
            foreach ($order as $index => $id) {
                Workspace::where('id', $id)->update(['position' => $index]);
            }
        }
        return response()->json(['status' => 'success']);
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
            'members',
        ]);

        $isSpecial = $user->isSpecialManagerOrAdmin();
        $userTeam = $user->getDigitalTeam($board->workspace_id);
        $isPlanningBoard = stripos($board->name ?? '', 'planning') !== false || $board->is_template || $board->type === 'smm';

        $board->load(['activeLists.cards' => function ($query) {
            $query->select([
                'id', 'board_list_id', 'title', 'team', 'priority', 'due_at', 'start_date', 'due_time', 'reminder', 'recurring',
                'status', 'block_completed_at', 'block_completed_by', 'smm_class_label', 'smm_team_label', 'smm_cluster_label',
                'content_public_date', 'created_by', 'position', 'sync_group_id', 'is_archived', 'label', 'sub_label', 'description', 'created_at', 'updated_at'
            ])->with([
                'creator:id,name,avatar,username',
                'assignees:id,name,avatar,username',
                'labels',
                'syncSiblings' => function($q) {
                    $q->select('id', 'sync_group_id', 'board_list_id');
                },
                'syncSiblings.boardList:id,name',
                'syncSiblings.board:id,name',
                'checklists' => function ($q) {
                    $q->select('id', 'card_id')->withCount([
                        'items as checklist_total',
                        'items as checklist_done' => function ($query) {
                            $query->where('is_completed', true);
                        }
                    ]);
                }
            ])->withCount([
                'files', 
                'comments',
            ]);
        }]);

        // Append workflow_status and ensure team is resolved on each card
        foreach ($board->activeLists as $list) {
            foreach ($list->cards as $card) {
                $card->append('workflow_status');
                // Ensure team attribute is accessed/cached
                $t = $card->team;
            }
        }

        // Card visibility rules:
        // - On Workflow Boards: strictly show only that board's team cards (Team A or Team B)
        // - On SMM Planning Boards: allow all users to see both teams (no team restriction)
        // - On Normal Planning Boards: regular users can see ONLY their team (Team A or Team B); special managers/admin can see both teams
        $boardNameLower = strtolower($board->name ?? '');
        $isSmmBoard = ($board->type === 'smm') || str_contains($boardNameLower, 'smm') || ($board->workspace?->name === 'Social Media Management');
        $isWf = (str_contains($boardNameLower, 'workflow') || $board->type === 'workflow') && !str_contains($boardNameLower, 'planning');
        $isNormalPlanning = (stripos($board->name ?? '', 'planning') !== false || $board->is_template) && !$isSmmBoard && !$isWf;

        $bTeam = null;
        if (str_contains($boardNameLower, 'team a') || str_contains($boardNameLower, 'teama') || str_contains($boardNameLower, 'team-a')) {
            $bTeam = 'A';
        } elseif (str_contains($boardNameLower, 'team b') || str_contains($boardNameLower, 'teamb') || str_contains($boardNameLower, 'team-b')) {
            $bTeam = 'B';
        }

        $isGeneralSupervisor = $user->canFilterAllPlanningTeams();

        if ($isWf && $bTeam) {
            foreach ($board->activeLists as $list) {
                $filtered = $list->cards->filter(function ($card) use ($bTeam) {
                    return $card->hasTeam($bTeam);
                })->values();
                $list->setRelation('cards', $filtered);
            }
        } elseif ($isNormalPlanning && in_array($userTeam, ['A', 'B']) && !$isGeneralSupervisor) {
            foreach ($board->activeLists as $list) {
                $filtered = $list->cards->filter(function ($card) use ($userTeam, $user) {
                    if ($card->hasTeam($userTeam)) {
                        return true;
                    }
                    if ($card->relationLoaded('assignees') && $card->assignees->contains('id', $user->id)) {
                        return true;
                    }
                    return false;
                })->values();
                $list->setRelation('cards', $filtered);
            }
        }


        $workspaceBoards = $board->workspace
            ->boards()
            ->with('members:id')
            ->where('is_archived', false)
            ->where('is_hidden', false)
            ->orderBy('position')
            ->get()
            ->filter(function ($b) use ($user) {
                $isQc = str_contains(strtolower($user->team_role ?? ''), 'qc');
                $isBypassed = $user->hasAnyRole(['super-admin', 'admin-digital', 'admin', 'supervisor', 'boss']) || $isQc || $user->isSpecialManagerOrAdmin();

                if ($isBypassed) {
                    return true;
                }

                if ($user->hasAnyRole(['digital-team', 'sales-crm'])) {
                    return $b->hasMember($user->id);
                }
                return true;
            });

        $allBoardMembers = $board->members;
        $boardMemberIds = $allBoardMembers->pluck('id');
        $possibleBoardUsers = $board->workspace->members
            ->concat(
                User::active()
                    ->whereHas('roles', fn($q) => $q->whereIn('name', ['digital-team', 'admin-digital', 'admin', 'boss', 'supervisor', 'staff']))
                    ->orderBy('name')
                    ->get()
            )
            ->unique('id')
            ->sortBy(fn ($member) => ($boardMemberIds->contains($member->id) ? '0_' : '1_') . strtolower($member->name))
            ->values();

        $isWatching = !\Illuminate\Support\Facades\DB::table('board_unwatchers')
            ->where('board_id', $board->id)
            ->where('user_id', $user->id)
            ->exists();

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
                'is_watching' => $isWatching,
                'can_manage_board' => $this->canManageBoard($user, $board),
                'can_move_list' => $user->hasAnyRole(['super-admin', 'admin-digital']),
                'can_bulk_action' => true,
                'can_delete_board' => $this->canDeleteBoard($user, $board),
            ],
            'boardId'   => $board->id,
            'boardSlug' => $board->slug,
            'boardType' => $board->type,
            'autoOpenCardId' => request()->filled('card') ? (int) request('card') : null,
            'baseRoute' => $board->type === 'smm' ? 'smm-boards' : 'boards',
            'smmClasses' => \App\Models\SocialMediaClass::active()->orderBy('position')->get(['id', 'name', 'color'])->toArray(),
            'smmTeams' => ['Graphic Team', 'Video Team', 'Listing Team', 'Content Writing Team', 'QC Team'],
            'smmContentTypes' => [
                ['name' => 'Long Landscape', 'bg' => '#dcfce7', 'text' => '#166534', 'border' => '#bbf7d0', 'dot' => '#22c55e'],
                ['name' => 'Short Reel',     'bg' => '#ffd7d7', 'text' => '#991b1b', 'border' => '#fecaca', 'dot' => '#ef4444'],
                ['name' => 'Poster Design',  'bg' => '#ebd9fc', 'text' => '#6b21a8', 'border' => '#e9d5ff', 'dot' => '#a855f7'],
                ['name' => 'Share Blog',     'bg' => '#1e6f82', 'text' => '#ffffff', 'border' => '#155e75', 'dot' => '#06b6d4'],
                ['name' => 'Urgent Task',    'bg' => '#6f3710', 'text' => '#ffffff', 'border' => '#572b0d', 'dot' => '#ea580c'],
                ['name' => 'Press Release',  'bg' => '#136e43', 'text' => '#ffffff', 'border' => '#0e5333', 'dot' => '#10b981'],
            ],
            'csrfToken' => csrf_token(),
            'currentUserId' => $user->id,
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->toArray(),
                'avatar_url' => $user->avatar_url,
                'avatar_initials' => $user->avatar_initials,
                'avatar_color' => $user->avatar_color,
                'is_digital_team' => $user->hasRole('digital-team'),
                'is_special_manager' => $isSpecial,
                'can_filter_all_teams' => $user->canFilterAllPlanningTeams(),
                'team' => $userTeam,
                'can_move_any_card' => $this->canMoveAnyCard($user),
                'can_manage_blocked_cards' => $this->canManageBlockedCards($user),
                'can_review_mark' => \App\Models\CardChecklistItem::canReviewMark($user),
                'can_override_tick' => \App\Models\CardChecklistItem::canOverrideTick($user),
                'can_approve_checklist' => \App\Models\CardChecklistItem::canApprove($user),
                'is_head' => (bool) ($user && ($user->isDaraOrKim() || \App\Models\CardChecklistItem::canReviewMark($user))),
                'is_dara_or_kim' => (bool) ($user && ($user->isDaraOrKim() || \App\Models\CardChecklistItem::canReviewMark($user))),
            ],
            'lists'     => $board->activeLists->map(fn($l) => [
                'id'       => $l->id,
                'name'     => $l->name,
                'color'    => $l->color,
                'lead'     => $this->getListLeadUser($l, $board),
                'cards'    => $l->cards->map(fn($c) => [
                    'id'         => $c->id,
                    'title'      => $c->title,
                    'team'       => $c->team,
                    'priority'   => $c->priority?->value ?? 'medium',
                    'due_at'     => $c->due_at?->format('Y-m-d'),
                    'start_date' => $c->start_date?->format('Y-m-d'),
                    'due_time'   => $c->due_time,
                    'reminder'   => $c->reminder,
                    'recurring'  => $c->recurring ?? 'none',
                    'board_list_id' => $c->board_list_id,
                    'status'     => $c->status?->value ?? (string) $c->status,
                    'workflow_status' => $c->workflow_status,
                    'block_completed_at' => $c->block_completed_at?->toISOString(),
                    'block_completed_by' => $c->block_completed_by,
                    'smm_class_label'    => $c->smm_class_label,
                    'smm_team_label'     => $c->smm_team_label,
                    'smm_cluster_label'  => $c->smm_cluster_label,
                    'content_public_date'=> $c->content_public_date?->format('Y-m-d'),
                    'labels'   => $c->labels->map(fn($lb) => ['id'=>$lb->id,'name'=>$lb->name,'color'=>$lb->color]),
                    'assignees'=> $c->assignees->map(fn($u) => [
                        'id' => $u->id,
                        'name' => $u->name,
                        'email' => $u->email,
                        'avatar' => $u->avatar_url,
                        'initials' => $u->avatar_initials,
                        'avatar_color' => $u->avatar_color,
                    ]),
                    'checklist_total' => $c->checklists->sum('checklist_total'),
                    'checklist_done'  => $c->checklists->sum('checklist_done'),
                    'has_files'       => $c->files_count > 0,
                    'comment_count'   => $c->comments_count,
                    'creator' => $c->creator ? [
                        'id' => $c->creator->id,
                        'name' => $c->creator->name,
                        'avatar' => $c->creator->avatar_url,
                        'initials' => $c->creator->avatar_initials,
                        'avatar_color' => $c->creator->avatar_color,
                    ] : null,
                    'created_by' => $c->created_by,
                    'has_description' => !empty($c->description),
                    // files, comments, and checklists are loaded async when opening a card
                ])->values()->all(),
            ])->values()->all(),
            'labels'           => \App\Models\Label::where(function($q) use ($board) {
                $q->whereNull('workspace_id')->whereNull('board_id')
                  ->orWhere('workspace_id', $board->workspace_id)
                  ->orWhere('board_id', $board->id);
            })
            ->orWhereIn('id', function($sub) use ($board) {
                $sub->select('label_id')->from('card_labels')->whereIn('card_id', function($cardSub) use ($board) {
                    $cardSub->select('id')->from('cards')->where('board_id', $board->id);
                });
            })
            ->orderBy('position')->orderBy('name')
            ->get()
            ->unique(function ($item) {
                return strtolower(trim($item->name));
            })
            ->map(fn($l) => ['id'=>$l->id,'name'=>$l->name,'color'=>$l->color])
            ->values()
            ->all(),
            'boardMembers'     => $allBoardMembers->map(fn($u) => [
                'id'      => $u->id,
                'name'    => $u->name,
                'username'=> $u->username,
                'email'   => $u->email,
                'avatar'  => $u->avatar_url,
                'initials'=> $u->avatar_initials,
                'avatar_color' => $u->avatar_color,
                'role'    => $u->pivot?->role ?? 'member',
            ])->values()->all(),
            'workspaceMembers' => $board->workspace->members
                ->filter(fn($u) => !$allBoardMembers->contains('id', $u->id))
                ->map(fn($u) => [
                    'id'      => $u->id,
                    'name'    => $u->name,
                    'username'=> $u->username,
                    'email'   => $u->email,
                    'avatar'  => $u->avatar_url,
                    'initials'=> $u->avatar_initials,
                    'avatar_color' => $u->avatar_color,
                    'role'    => 'workspace',
                ])->values()->all(),
            'allWorkspaces' => $allWorkspaces->filter(fn($ws) => $ws->boards->isNotEmpty())->values()->map(fn($ws) => [
                'id' => $ws->id,
                'name' => $ws->name,
                'slug' => $ws->slug,
                'boards' => $ws->boards->map(fn($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'slug' => $b->slug,
                    'type' => $b->type,
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
            'allSystemMembers' => $possibleBoardUsers->map(fn($u) => [
                'id'      => $u->id,
                'name'    => $u->name,
                'username'=> $u->username,
                'email'   => $u->email,
                'avatar'  => $u->avatar_url,
                'initials'=> $u->avatar_initials,
                'avatar_color' => $u->avatar_color,
            ])->values()->all(),
        ];

        $externalTools = Setting::externalTools();

        $isSmmModule = ($board->type === 'smm') || ($board->workspace?->name === 'Social Media Management');

        return view('boards.show', compact('board', 'workspaceBoards', 'allWorkspaces', 'boardData', 'externalTools', 'possibleBoardUsers', 'boardMemberIds', 'isSmmModule'));
    }

    /** Return the current board state for realtime UI refreshes. */
    public function snapshot(Board $board): JsonResponse
    {
        $this->authorizeBoard($board);

        return response()->json($this->boardSnapshotPayload($board, auth()->user()));
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
            'template'         => ['nullable', 'string', 'in:normal,workflow,planning,workflow_team_a,workflow_team_b,video_planning,graphic_planning,listing_planning,content_planning'],
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
        $template = $validated['template'] ?? '';
        $isWorkflowTemplate = in_array($template, ['workflow', 'workflow_team_a', 'workflow_team_b']);
        $isPlanningTemplate = in_array($template, ['planning', 'video_planning', 'graphic_planning', 'listing_planning', 'content_planning']);

        if ($isWorkflowTemplate || $isPlanningTemplate) {
            $month = $validated['template_month'] ?? '';
            $year = $validated['template_year'] ?? '';

            $prefix = match($template) {
                'workflow_team_a' => 'Workflow board Team A',
                'workflow_team_b' => 'Workflow board Team B',
                'video_planning' => 'VideoPlanningBoard@KiuQ',
                'graphic_planning' => 'GraphicPlanningBoard@KiuQ',
                'listing_planning' => 'ListingPlanningBoard@KiuQ',
                'content_planning' => 'ContentPlanningBoard@KiuQ',
                'planning' => 'Planning board',
                default => 'Workflow board',
            };

            if ($month && $year) {
                $boardName = "{$prefix} - {$month} {$year}";
                session(['last_selected_month' => $month, 'last_selected_year' => $year]);
            } else {
                $boardName = $prefix;
            }
        }

        $position = Board::where('workspace_id', $validated['workspace_id'])->count();

        $bgType = $validated['background_type'];
        if ($isWorkflowTemplate || $isPlanningTemplate) {
            if (!$request->hasFile('background_image_file') && ($bgType !== 'image' || empty($backgroundValue))) {
                $bgType = 'color';
                $backgroundValue = '#ffffff';
            }
        }

        $coverType = $bgType;
        $coverValue = $backgroundValue;

        if ($isPlanningTemplate) {
            $coverType = 'image';
            $coverValue = 'https://img.miniexcavator.org/ebay/Dashboard-Icon/ChatGPT%20Image%20Oct%203%202026%2007_59_23%20AM.webp';
        } elseif ($template === 'workflow_team_a') {
            $coverType = 'image';
            $coverValue = 'https://img.miniexcavator.org/ebay/Dashboard-Icon/TeamA.webp';
        } elseif ($template === 'workflow_team_b') {
            $coverType = 'image';
            $coverValue = 'https://img.miniexcavator.org/ebay/Dashboard-Icon/B.webp';
        }

        $board = Board::create([
            'workspace_id' => $validated['workspace_id'],
            'background_type' => $bgType,
            'background_value' => $backgroundValue,
            'cover_type' => $coverType,
            'cover_value' => $coverValue,
            'visibility' => $validated['visibility'],
            'name' => $boardName,
            'created_by' => auth()->id(),
            'position'   => $position,
        ]);

        $copiedFromTemplate = false;

        if ($isWorkflowTemplate || $isPlanningTemplate) {
            $prefix = match($template) {
                'workflow_team_a' => 'Workflow board Team A',
                'workflow_team_b' => 'Workflow board Team B',
                'video_planning' => 'VideoPlanningBoard',
                'graphic_planning' => 'GraphicPlanningBoard',
                'listing_planning' => 'ListingPlanningBoard',
                'content_planning' => 'ContentPlanningBoard',
                'planning' => 'Planning board',
                default => 'Workflow board',
            };
            
            $templateBoard = \App\Models\Board::where('workspace_id', $board->workspace_id)
                ->where('id', '!=', $board->id)
                ->where('name', '!=', $boardName)
                ->where('name', 'like', "%{$prefix}%")
                ->orderBy('created_at', 'desc')
                ->first();

            $userHistoryBoard = \App\Models\Board::where('workspace_id', $board->workspace_id)
                ->where('created_by', auth()->id())
                ->where('id', '!=', $board->id)
                ->where('name', '!=', $boardName)
                ->where('name', 'like', "%{$prefix}%")
                ->orderBy('created_at', 'desc')
                ->first();

            if ($templateBoard) {
                // If they didn't upload a new file, copy the background/cover from their history (or fallback to template)
                if (!$request->hasFile('background_image_file')) {
                    $bgBoard = $userHistoryBoard ?: $templateBoard;
                    $bgType = $bgBoard->background_type;
                    $bgVal = $bgBoard->background_value;
                    $coverType = $bgBoard->cover_type;
                    $coverVal = $bgBoard->cover_value;
                    
                    // Enforce the specific cover image for Planning, Team A, and Team B boards if copied from template
                    if ($isPlanningTemplate) {
                        $coverType = 'image';
                        $coverVal = 'https://img.miniexcavator.org/ebay/Dashboard-Icon/ChatGPT%20Image%20Oct%203%202026%2007_59_23%20AM.webp';
                        if ($bgType !== 'image') {
                            $bgType = 'color';
                            $bgVal = '#ffffff';
                        }
                    } elseif ($template === 'workflow_team_a') {
                        $coverType = 'image';
                        $coverVal = 'https://img.miniexcavator.org/ebay/Dashboard-Icon/TeamA.webp';
                    } elseif ($template === 'workflow_team_b') {
                        $coverType = 'image';
                        $coverVal = 'https://img.miniexcavator.org/ebay/Dashboard-Icon/B.webp';
                    }

                    $board->update([
                        'background_type' => $bgType,
                        'background_value' => $bgVal,
                        'cover_type' => $coverType,
                        'cover_value' => $coverVal,
                    ]);
                }
                $listMap = [];
                $sourceLists = $templateBoard->lists()->where('is_archived', false)->get();
                foreach ($sourceLists as $sourceList) {
                    if (strcasecmp(trim($sourceList->name), 'Week 5') === 0) {
                        continue;
                    }
                    $newList = $board->lists()->create([
                        'name'       => $sourceList->name,
                        'position'   => $sourceList->position,
                        'color'      => $sourceList->color,
                        'wip_limit'  => $sourceList->wip_limit,
                    ]);
                    $listMap[$sourceList->id] = $newList->id;
                }

                $oldSuffix = trim(str_ireplace($prefix, '', $templateBoard->name));
                $newSuffix = trim(str_ireplace($prefix, '', $boardName));

                $sourceAutomations = \App\Models\BoardAutomation::where('board_id', $templateBoard->id)->get();
                foreach ($sourceAutomations as $auto) {
                    $triggerBoardId = $auto->trigger_board_id;
                    $triggerListId = $auto->trigger_list_id;
                    $targetBoardId = $auto->target_board_id;
                    $targetListId = $auto->target_list_id;

                    // Remap trigger board if it was the template
                    if ($triggerBoardId == $templateBoard->id) {
                        $triggerBoardId = $board->id;
                        $triggerListId = $listMap[$auto->trigger_list_id] ?? $auto->trigger_list_id;
                    }

                    // Remap target board if it was the template
                    if ($targetBoardId == $templateBoard->id) {
                        $targetBoardId = $board->id;
                        $targetListId = $listMap[$auto->target_list_id] ?? $auto->target_list_id;
                    } elseif ($targetBoardId && $oldSuffix && $newSuffix && $oldSuffix !== $newSuffix) {
                        // Remap to a different board based on suffix (e.g. Workflow board - August -> Workflow board - September)
                        $oldTargetBoard = \App\Models\Board::find($targetBoardId);
                        if ($oldTargetBoard && str_contains($oldTargetBoard->name, $oldSuffix)) {
                            $expectedName = str_replace($oldSuffix, $newSuffix, $oldTargetBoard->name);
                            $newTargetBoard = \App\Models\Board::where('workspace_id', $board->workspace_id)
                                ->where('name', $expectedName)->first();
                            
                            if ($newTargetBoard) {
                                $targetBoardId = $newTargetBoard->id;
                                // Also remap the list ID
                                $oldList = \App\Models\BoardList::find($targetListId);
                                if ($oldList) {
                                    $newList = $newTargetBoard->lists()->where('name', $oldList->name)->first();
                                    if ($newList) {
                                        $targetListId = $newList->id;
                                    }
                                }
                            }
                        }
                    }

                    \App\Models\BoardAutomation::create([
                        'board_id'             => $board->id,
                        'trigger_type'         => $auto->trigger_type,
                        'trigger_word'         => $auto->trigger_word,
                        'trigger_board_id'     => $triggerBoardId,
                        'trigger_list_id'      => $triggerListId,
                        'target_board_id'      => $targetBoardId,
                        'target_list_id'       => $targetListId,
                        'action_type'          => $auto->action_type,
                        'target_assignee_role' => $auto->target_assignee_role,
                    ]);
                }

                $memberData = [];
                foreach ($templateBoard->members as $member) {
                    $memberData[$member->id] = ['role' => $member->pivot->role];
                }
                if (!empty($memberData)) {
                    $board->members()->syncWithoutDetaching($memberData);
                }

                $copiedFromTemplate = true;
            }
        }

        if (!$copiedFromTemplate) {
            if ($template === 'workflow_team_a') {
                $defaults = ['Draft', 'Production Team A', 'Digital Department', 'Approved', 'Blocked/Waiting'];
            } elseif ($template === 'workflow_team_b') {
                $defaults = ['Draft', 'Production Team B', 'Digital Department', 'Approved', 'Blocked/Waiting'];
            } elseif ($template === 'workflow') {
                $defaults = ['Draft', 'Production Team', 'Digital Department', 'Approved', 'Blocked/Waiting'];
            } elseif ($isPlanningTemplate) {
                $defaults = ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Meeting Schedule', 'Urgent / Priority', 'Blocked/Waiting'];
            } else {
                $defaults = ['To Do', 'In Progress', 'Done'];
            }
            
            foreach ($defaults as $i => $name) {
                $board->lists()->create(['name' => $name, 'position' => $i]);
            }
        }

        // Add workspace members to the new board automatically if not copied
        if (!$copiedFromTemplate && $board->workspace) {
            $workspaceMembers = $board->workspace->members()->get();
            $syncData = [];
            foreach ($workspaceMembers as $member) {
                // Map workspace roles to valid board roles (board_members only allows admin, member, observer)
                $wsRole = $member->pivot->role ?? 'member';
                $boardRole = 'member';
                
                if ($wsRole === 'owner' || $wsRole === 'admin') {
                    $boardRole = 'admin';
                } elseif ($wsRole === 'guest') {
                    $boardRole = 'observer';
                }

                $syncData[$member->id] = ['role' => $boardRole];
            }
            if (!empty($syncData)) {
                $board->members()->syncWithoutDetaching($syncData);
            }
        }

        // Seed Default Automations if not copied
        if (!$copiedFromTemplate && $isWorkflowTemplate) {
            $lists = $board->lists()->get()->keyBy('name');
            $prodList = $template === 'workflow_team_a' ? 'Production Team A' : ($template === 'workflow_team_b' ? 'Production Team B' : 'Production Team');
            if (!isset($lists[$prodList])) {
                $prodList = $lists->keys()->first(fn($n) => stripos($n, 'production') !== false) ?? 'Production Team';
            }
            $digitList = 'Digital Department';
            $blockedList = 'Blocked/Waiting';

            $rules = [
                ['Draft', 'Team approved', $prodList, 'Production Team'],
                [$prodList, 'Production approved', $digitList, 'Production Team'],
                [$prodList, 'Production approved SMM', 'Approved', 'Production Team'],
                ['Draft', 'Production approved SMM', 'Approved', 'Production Team'],
                [$prodList, 'Error', 'Draft', 'Production Team'],
                [$digitList, 'Supervisor approved', 'Approved', 'Supervisor'],
                [$digitList, 'Approved', 'Approved', 'Supervisor'],
                [$prodList, 'Blocked', $blockedList, 'Production Team'],
                [$digitList, 'Blocked', $blockedList, 'Supervisor'],
            ];

            foreach ($rules as $rule) {
                if (isset($lists[$rule[0]]) && isset($lists[$rule[2]])) {
                    \App\Models\BoardAutomation::create([
                        'board_id' => $board->id,
                        'trigger_type' => 'keyword',
                        'trigger_word' => $rule[1],
                        'trigger_board_id' => $board->id,
                        'trigger_list_id' => $lists[$rule[0]]->id,
                        'target_board_id' => $board->id,
                        'target_list_id' => $lists[$rule[2]]->id,
                        'action_type' => 'move',
                        'target_assignee_role' => $rule[3]
                    ]);
                }
            }

            // Also link any existing planning boards in the workspace to this new workflow board
            $planningBoards = \App\Models\Board::where('workspace_id', $board->workspace_id)
                ->where('id', '!=', $board->id)
                ->where(function($q) {
                    $q->where('name', 'like', '%Planning%');
                })->get();

            $draftList = $board->lists()->where('name', 'like', '%Draft%')->first();
            if ($draftList) {
                foreach ($planningBoards as $pb) {
                    \App\Models\BoardAutomation::updateOrCreate(
                        [
                            'board_id' => $pb->id,
                            'trigger_type' => 'keyword',
                            'trigger_word' => 'ready',
                            'action_type' => 'copy',
                        ],
                        [
                            'trigger_board_id' => $pb->id,
                            'trigger_list_id' => null,
                            'target_board_id' => $board->id,
                            'target_list_id' => $draftList->id,
                        ]
                    );
                }
            }
        } elseif (!$copiedFromTemplate && $isPlanningTemplate) {
            // Find matching workflow board in workspace
            $workflowBoard = \App\Models\Board::where('workspace_id', $board->workspace_id)
                ->where(function($q) {
                    $q->where('name', 'like', '%Workflow%');
                })->latest()->first();

            if ($workflowBoard) {
                $draftList = $workflowBoard->lists()->where('name', 'like', '%Draft%')->first();
                if ($draftList) {
                    \App\Models\BoardAutomation::create([
                        'board_id' => $board->id,
                        'trigger_type' => 'keyword',
                        'trigger_word' => 'ready',
                        'trigger_board_id' => $board->id,
                        'trigger_list_id' => null, // Any list
                        'target_board_id' => $workflowBoard->id,
                        'target_list_id' => $draftList->id,
                        'action_type' => 'copy'
                    ]);
                }
            }
        }
        return back()->with('success', "Board \"{$board->name}\" created.");
    }

    /** Update board settings (name, background, etc.). */
    public function toggleHidden(Board $board): JsonResponse
    {
        $user = auth()->user();
        if (! $user->canManageBoards()) {
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
            'visibility',
            'member_permissions',
            'card_covers_enabled',
            'notifications_enabled',
            'browser_notifications_enabled',

            'is_archived',
        ];

        if (array_key_exists('background_type', $validated) || array_key_exists('background_value', $validated)) {
            $user = auth()->user();
            $prefs = $user->board_backgrounds ?? [];
            if (!isset($prefs[$board->id])) {
                $prefs[$board->id] = [];
            }
            $prefs[$board->id]['background_type'] = $backgroundType ?? $board->background_type;
            $prefs[$board->id]['background_value'] = $backgroundValue ?? $board->background_value;
            
            $user->board_backgrounds = $prefs;
            $user->save();

            unset($validated['background_type']);
            unset($validated['background_value']);
        }

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

        // Background image deletion skipped since backgrounds are now user-specific

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
            $dir = public_path('board-backgrounds');
            if (!file_exists($dir)) {
                @mkdir($dir, 0755, true);
            }
            $path = $dir . '/' . $filename;
            
            // For PNG/GIF transparency
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);
            
            imagewebp($image, $path, 85);
            imagedestroy($image);
            
            return '/board-backgrounds/' . $filename;
        }

        // Fallback if conversion fails
        $filename = \Illuminate\Support\Str::random(40) . '.' . $file->extension();
        $dir = public_path('board-backgrounds');
        if (!file_exists($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file->move($dir, $filename);
        return '/board-backgrounds/' . $filename;
    }

    /** Basic update for board name and background from the workspaces view. */
    public function updateBoardBasic(Request $request, Board $board)
    {
        $this->authorizeBoard($board);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'board_name_edit' => ['nullable', 'string', 'max:100'],
            'cover_type' => ['required', 'in:color,image'],
            'cover_value' => ['nullable', 'string', 'max:2048'],
            'cover_image_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:8192'],
        ]);

        $coverValue = $validated['cover_value'] ?? '';

        if ($validated['cover_type'] === 'image') {
            if ($request->hasFile('cover_image_file')) {
                $uploadedUrl = $this->handleBackgroundImageUpload($request->file('cover_image_file'));
                if ($uploadedUrl) {
                    $coverValue = $uploadedUrl;
                }
            } else {
                if (str_starts_with($coverValue, '/public/')) {
                    $coverValue = substr($coverValue, 7);
                }
                if (! $this->isAllowedBackgroundImageValue($coverValue)) {
                    if ($request->expectsJson() || $request->ajax()) {
                        return response()->json(['success' => false, 'message' => 'Enter a valid cover image URL or upload an image.'], 422);
                    }
                    return back()->withErrors(['cover_value' => 'Enter a valid cover image URL.']);
                }
            }
        } elseif ($validated['cover_type'] === 'color' && ! preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $coverValue)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Choose a valid hex cover color.'], 422);
            }
            return back()->withErrors(['cover_value' => 'Choose a valid hex cover color.']);
        }

        if (str_starts_with($coverValue, '/public/')) {
            $coverValue = substr($coverValue, 7);
        }

        $user = auth()->user();
        $prefs = $user->board_backgrounds ?? [];
        // Ensure the array structure exists
        if (!isset($prefs[$board->id])) {
            $prefs[$board->id] = [];
        }
        
        $prefs[$board->id]['cover_type'] = $validated['cover_type'];
        $prefs[$board->id]['cover_value'] = $coverValue;
        
        $user->board_backgrounds = $prefs;
        $user->save();

        $nameToSave = $validated['board_name_edit'] ?? $validated['name'] ?? null;

        if (!empty($nameToSave) && ($user->canManageBoards() || $board->workspace->owner_id === $user->id)) {
            $board->update(['name' => $nameToSave]);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true, 
                'message' => 'Board cover updated successfully.',
                'board' => [
                    'id' => $board->id,
                    'name' => $board->name,
                    'cover_type' => $validated['cover_type'],
                    'cover_value' => $coverValue,
                ]
            ]);
        }

        return back()->with('success', 'Board cover updated successfully.');
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

        $uploadedUrl = $this->handleBackgroundImageUpload($validated['background_image']);

        if (!$uploadedUrl) {
            return response()->json(['error' => 'Failed to process image.'], 422);
        }

        $user = auth()->user();
        $prefs = $user->board_backgrounds ?? [];
        if (!isset($prefs[$board->id])) {
            $prefs[$board->id] = [];
        }
        $prefs[$board->id]['background_type'] = 'image';
        $prefs[$board->id]['background_value'] = $uploadedUrl;
        
        $user->board_backgrounds = $prefs;
        $user->save();

        $board = $board->fresh(['workspace']);

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

            // If target board was not the board itself and action is copy, ensure target is in copy's workspace
            if ($newTargetBoardId != $copy->id && $auto->action_type === 'copy') {
                $oldTargetBoard = \App\Models\Board::find($auto->target_board_id);
                if ($oldTargetBoard) {
                    $matchedTgt = \App\Models\Board::where('workspace_id', $copy->workspace_id)
                        ->where('name', $oldTargetBoard->name)
                        ->first()
                        ?? \App\Models\Board::where('workspace_id', $copy->workspace_id)
                            ->where('name', 'like', '%Workflow%')
                            ->latest()->first();
                    if ($matchedTgt) {
                        $newTargetBoardId = $matchedTgt->id;
                        $draftList = $matchedTgt->lists()->where('name', 'like', '%Draft%')->first();
                        if ($draftList) {
                            $newTargetListId = $draftList->id;
                        }
                    }
                }
            }

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

    /** Restore a deleted board. */
    public function restore($id): RedirectResponse
    {
        $board = Board::onlyTrashed()->findOrFail($id);
        $board->restore();
        
        $this->logBoardActivity($board, 'restored', "restored board **{$board->name}**");

        return back()->with('success', 'Board restored successfully.');
    }

    /** Permanently delete a board. */
    public function forceDelete($id): RedirectResponse
    {
        $board = Board::onlyTrashed()->findOrFail($id);
        $boardName = $board->name;
        $board->forceDelete();

        return back()->with('success', "Board \"{$boardName}\" permanently deleted.");
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

     /** Delete a list. */
     public function destroyList(BoardList $list): JsonResponse
     {
         $this->authorizeBoard($list->board);
         
         try {
             \App\Notifications\BoardActivityNotification::send($list->board, 'list_deleted', "deleted list **{$list->name}**");
         } catch (\Throwable $e) {
             \Illuminate\Support\Facades\Log::error("Notification failed: " . $e->getMessage());
         }

         // Delete all cards inside this list
         $list->cards()->delete();
         $list->delete();

         return response()->json(['message' => 'List and its cards deleted.']);
     }

     /** Clear all cards inside a list (Super Admin, Mr Dara QC, Lyza, Sreypich). */
     public function clearList(BoardList $list): JsonResponse
     {
         if (!auth()->user() || !auth()->user()->canClearBoardList()) {
             return response()->json(['message' => 'Unauthorized. Super Admin and authorized managers only.'], 403);
         }
         
         $this->authorizeBoard($list->board);
         
         try {
             \App\Notifications\BoardActivityNotification::send($list->board, 'list_cleared', "cleared all cards from list **{$list->name}**");
         } catch (\Throwable $e) {
             \Illuminate\Support\Facades\Log::error("Notification failed: " . $e->getMessage());
         }

         // Delete all cards inside this list
         $list->cards()->delete();

         return response()->json(['message' => 'List cleared successfully.']);
     }

    /** Reorder lists via drag-and-drop. */
    public function reorderLists(Request $request, Board $board): JsonResponse
    {
        $this->authorizeBoard($board);
        abort_unless(auth()->user()->hasAnyRole(['super-admin', 'admin-digital']), 403, 'You do not have permission to reorder lists.');

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
            'moving_card_id' => ['nullable', 'integer', 'exists:cards,id'],
            'source_list_id' => ['nullable', 'integer', 'exists:board_lists,id'],
        ]);

        $user = auth()->user();
        $targetList = BoardList::where('board_id', $board->id)->findOrFail((int) $request->list_id);
        $cards = Card::with(['assignees:id', 'boardList'])
            ->where('board_id', $board->id)
            ->whereIn('id', $request->order)
            ->get()
            ->keyBy('id');

        if ($request->filled('moving_card_id')) {
            $movingCard = Card::with(['assignees:id', 'boardList'])
                ->where('board_id', $board->id)
                ->findOrFail((int) $request->moving_card_id);
            $sourceList = $request->filled('source_list_id')
                ? BoardList::where('board_id', $board->id)->find((int) $request->source_list_id)
                : $movingCard->boardList;

            if (! $this->canMoveCard($user, $movingCard, $sourceList, $targetList)) {
                return response()->json(['error' => 'You can only move cards assigned to you. Blocked cards can only be moved by supervisors.'], 403);
            }
        }

        foreach ($request->order as $position => $cardId) {
            $card = $cards->get((int) $cardId);
            if (! $card) {
                continue;
            }

            if (! $request->filled('moving_card_id') && ! $this->canMoveCard($user, $card, $card->boardList, $targetList)) {
                return response()->json(['error' => 'You can only move cards assigned to you. Blocked cards can only be moved by supervisors.'], 403);
            }

            $card->update([
                'board_list_id' => $targetList->id,
                'position'      => $position,
            ]);
        }

        if (
            isset($movingCard)
            && $request->filled('source_list_id')
            && (int) $request->source_list_id === (int) $targetList->id
        ) {
            // Realtime board sync for active viewers only; no user notification or activity entry sent
            event(new \App\Events\BoardUpdated($board->id, $board->slug, 'card_reordered', $movingCard->id, auth()->id()));
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

        // Verify workspace membership or auto-attach if not present
        if ($board->workspace && !$board->workspace->hasMember($targetUser->id)) {
            $board->workspace->members()->syncWithoutDetaching([
                $targetUser->id => ['role' => 'member']
            ]);
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
        if ($user->hasAnyRole(['super-admin', 'admin-digital', 'admin', 'supervisor', 'boss']) || $user->isSpecialManagerOrAdmin()) {
            return true;
        }

        // Allow all workspace members to manage the board
        return $board->workspace?->hasMember($user->id) ?? true;
    }

    private function isAllowedMoveUser(?\App\Models\User $user): bool
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

    private function canMoveAnyCard(\App\Models\User $user): bool
    {
        return $this->isAllowedMoveUser($user)
            || $user->hasAnyRole(['super-admin', 'admin', 'admin-digital', 'supervisor', 'boss'])
            || $user->isQcOrSupervisor()
            || str_contains(strtolower($user->team_role ?? ''), 'head');
    }

    private function canManageBlockedCards(\App\Models\User $user): bool
    {
        return $this->isAllowedMoveUser($user)
            || $user->hasAnyRole(['super-admin', 'admin', 'admin-digital', 'supervisor', 'boss'])
            || $user->isSupervisorRole()
            || str_contains(strtolower($user->team_role ?? ''), 'head');
    }

    private function canMoveCard(\App\Models\User $user, Card $card, ?BoardList $sourceList, BoardList $targetList): bool
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

    private function canDeleteBoard(User $user, Board $board): bool
    {
        if ($user->canManageBoards()) {
            return true;
        }
        // Allow all workspace members to delete the board
        return $board->workspace?->hasMember($user->id) ?? true;
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
            || Str::startsWith($value, ['/storage/', 'storage/', '/board-backgrounds/', 'board-backgrounds/', '/public/board-backgrounds/']);
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
            'cover_type' => $board->cover_type,
            'cover_value' => $board->cover_value,
            'member_permissions' => $board->member_permissions ?? 'members',
            'card_covers_enabled' => (bool) ($board->card_covers_enabled ?? true),
            'notifications_enabled' => (bool) ($board->notifications_enabled ?? true),
            'browser_notifications_enabled' => (bool) ($board->browser_notifications_enabled ?? false),

            'is_starred' => (bool) $board->is_starred,
            'is_archived' => (bool) $board->is_archived,
            'is_hidden' => (bool) $board->is_hidden,
            'workspace_name' => $board->workspace?->name,
            'can_manage_board' => $this->canManageBoard(auth()->user(), $board),
            'can_delete_board' => $this->canDeleteBoard(auth()->user(), $board),
        ];
    }

    /**
     * Resolve the lead user and profile for a board list (Workflow lists: Mr. Dara, Mr. Kim, Supervisor).
     */
    protected function getListLeadUser($list, ?Board $board = null): ?array
    {
        $listName = strtolower(trim($list->name ?? ''));
        $boardName = strtolower(trim($board?->name ?? ''));

        $leadUser = null;
        $displayName = null;
        $roleTitle = null;

        // 1. Team A / Mr. Dara (Production Team A)
        if (
            str_contains($listName, 'production team a') ||
            str_contains($listName, 'team a') ||
            str_contains($listName, 'dara') ||
            (str_contains($listName, 'qc') && !str_contains($listName, 'team b'))
        ) {
            if (!str_contains($listName, 'team b')) {
                $leadUser = \App\Models\User::find(12) ?? \App\Models\User::where('name', 'like', '%Dara%')->first();
                $displayName = 'Mr. Dara';
                $roleTitle = 'Team A QC / Lead';
            }
        }

        // 2. Team B / Mr. Kim (Production Team B)
        if (!$leadUser && (
            str_contains($listName, 'production team b') ||
            str_contains($listName, 'team b') ||
            str_contains($listName, 'kim') ||
            str_contains($listName, 'head review')
        )) {
            $leadUser = \App\Models\User::find(13) ?? \App\Models\User::where('name', 'like', '%Kim%')->first();
            $displayName = 'Mr. Kim';
            $roleTitle = 'Team B Head';
        }

        // 3. Digital Department / Supervisor
        if (!$leadUser && (
            str_contains($listName, 'digital department') ||
            str_contains($listName, 'supervisor') ||
            str_contains($listName, 'somalika')
        )) {
            $leadUser = \App\Models\User::find(2) ?? \App\Models\User::where('name', 'like', '%Somalika%')->orWhere('name', 'like', '%Supervisor%')->first();
            $displayName = 'Supervisor';
            $roleTitle = 'Digital Supervisor';
        }

        if (!$leadUser) {
            return null;
        }

        return [
            'id'           => $leadUser->id,
            'name'         => $displayName ?: $leadUser->name,
            'display_name' => $displayName ?: $leadUser->name,
            'full_name'    => $leadUser->name,
            'email'        => $leadUser->email,
            'avatar'       => $leadUser->avatar_url,
            'avatar_url'   => $leadUser->avatar_url,
            'initials'     => $leadUser->avatar_initials,
            'avatar_color' => $leadUser->avatar_color,
            'role'         => $roleTitle ?: ($leadUser->team_role ?? 'Lead'),
        ];
    }

    private function boardSnapshotPayload(Board $board, User $user): array
    {
        $board->load([
            'workspace.members',
            'labels',
            'members',
        ]);

        $isSpecial = $user->isSpecialManagerOrAdmin();
        $userTeam = $user->getDigitalTeam($board->workspace_id);
        $isPlanningBoard = stripos($board->name ?? '', 'planning') !== false || $board->is_template || $board->type === 'smm';
        
        $board->load(['activeLists.cards' => function ($query) {
            $query->select([
                'id', 'board_list_id', 'title', 'team', 'priority', 'due_at', 'start_date', 'due_time', 'reminder', 'recurring',
                'status', 'block_completed_at', 'block_completed_by', 'smm_class_label', 'smm_team_label', 'smm_cluster_label',
                'content_public_date', 'created_by', 'position', 'sync_group_id', 'is_archived', 'label', 'sub_label', 'description', 'created_at', 'updated_at'
            ])->with([
                'creator:id,name,avatar,username',
                'assignees:id,name,avatar,username',
                'labels',
                'syncSiblings' => function($q) {
                    $q->select('id', 'sync_group_id', 'board_list_id');
                },
                'syncSiblings.boardList:id,name',
                'syncSiblings.board:id,name',
                'checklists' => function ($q) {
                    $q->select('id', 'card_id')->withCount([
                        'items as checklist_total',
                        'items as checklist_done' => function ($query) {
                            $query->where('is_completed', true);
                        }
                    ]);
                }
            ])->withCount([
                'files',
                'comments',
            ]);
        }]);

        // Append workflow_status and ensure team is resolved on each card
        foreach ($board->activeLists as $list) {
            foreach ($list->cards as $card) {
                $card->append('workflow_status');
                // Ensure team attribute is accessed/cached
                $t = $card->team;
            }
        }

        // Card visibility rules:
        // - On Workflow Boards: strictly show only that board's team cards (Team A or Team B)
        // - On SMM Planning Boards: allow all users to see both teams (no team restriction)
        // - On Normal Planning Boards: regular users can see ONLY their team (Team A or Team B); special managers/admin can see both teams
        $boardNameLower = strtolower($board->name ?? '');
        $isSmmBoard = ($board->type === 'smm') || str_contains($boardNameLower, 'smm') || ($board->workspace?->name === 'Social Media Management');
        $isWf = (str_contains($boardNameLower, 'workflow') || $board->type === 'workflow') && !str_contains($boardNameLower, 'planning');
        $isNormalPlanning = (stripos($board->name ?? '', 'planning') !== false || $board->is_template) && !$isSmmBoard && !$isWf;

        $bTeam = null;
        if (str_contains($boardNameLower, 'team a') || str_contains($boardNameLower, 'teama') || str_contains($boardNameLower, 'team-a')) {
            $bTeam = 'A';
        } elseif (str_contains($boardNameLower, 'team b') || str_contains($boardNameLower, 'teamb') || str_contains($boardNameLower, 'team-b')) {
            $bTeam = 'B';
        }

        $isGeneralSupervisor = $user->canFilterAllPlanningTeams();

        if ($isWf && $bTeam) {
            foreach ($board->activeLists as $list) {
                $filtered = $list->cards->filter(function ($card) use ($bTeam) {
                    return $card->hasTeam($bTeam);
                })->values();
                $list->setRelation('cards', $filtered);
            }
        } elseif ($isNormalPlanning && in_array($userTeam, ['A', 'B']) && !$isGeneralSupervisor) {
            foreach ($board->activeLists as $list) {
                $filtered = $list->cards->filter(function ($card) use ($userTeam, $user) {
                    if ($card->hasTeam($userTeam)) {
                        return true;
                    }
                    if ($card->relationLoaded('assignees') && $card->assignees->contains('id', $user->id)) {
                        return true;
                    }
                    return false;
                })->values();
                $list->setRelation('cards', $filtered);
            }
        }

        $allWorkspaces = $this->getAuthorizedWorkspaces($user);

        return [
            'board' => $this->boardPayload($board),
            'boardId' => $board->id,
            'boardSlug' => $board->slug,
            'currentUserId' => $user->id,
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->toArray(),
                'is_digital_team' => $user->hasRole('digital-team'),
                'is_special_manager' => $isSpecial,
                'can_filter_all_teams' => $user->canFilterAllPlanningTeams(),
                'team' => $userTeam,
                'can_move_any_card' => $this->canMoveAnyCard($user),
                'can_manage_blocked_cards' => $this->canManageBlockedCards($user),
                'can_review_mark' => \App\Models\CardChecklistItem::canReviewMark($user),
                'can_override_tick' => \App\Models\CardChecklistItem::canOverrideTick($user),
                'can_approve_checklist' => \App\Models\CardChecklistItem::canApprove($user),
                'is_head' => (bool) ($user && ($user->isDaraOrKim() || \App\Models\CardChecklistItem::canReviewMark($user))),
                'is_dara_or_kim' => (bool) ($user && ($user->isDaraOrKim() || \App\Models\CardChecklistItem::canReviewMark($user))),
            ],
            'lists' => $board->activeLists->map(fn($list) => [
                'id' => $list->id,
                'name' => $list->name,
                'color' => $list->color,
                'lead' => $this->getListLeadUser($list, $board),
                'cards' => $list->cards->map(fn($card) => [
                    'id' => $card->id,
                    'title' => $card->title,
                    'team'  => $card->team,
                    'priority' => $card->priority?->value ?? 'medium',
                    'due_at' => $card->due_at?->format('Y-m-d'),
                    'start_date' => $card->start_date?->format('Y-m-d'),
                    'due_time' => $card->due_time,
                    'reminder' => $card->reminder,
                    'recurring' => $card->recurring ?? 'none',
                    'board_list_id' => $card->board_list_id,
                    'status' => $card->status?->value ?? (string) $card->status,
                    'workflow_status' => $card->workflow_status,
                    'block_completed_at' => $card->block_completed_at?->toISOString(),
                    'block_completed_by' => $card->block_completed_by,
                    'smm_class_label'    => $card->smm_class_label,
                    'smm_team_label'     => $card->smm_team_label,
                    'smm_cluster_label'  => $card->smm_cluster_label,
                    'content_public_date'=> $card->content_public_date?->format('Y-m-d'),
                    'creator' => $card->creator ? [
                        'id' => $card->creator->id,
                        'name' => $card->creator->name,
                        'avatar' => $card->creator->avatar_url,
                        'initials' => $card->creator->avatar_initials,
                        'avatar_color' => $card->creator->avatar_color,
                    ] : null,
                    'created_by' => $card->created_by,
                    'description' => $card->description,
                    'has_description' => !empty($card->description),
                    'labels' => $card->labels->map(fn($label) => [
                        'id' => $label->id,
                        'name' => $label->name,
                        'color' => $label->color,
                    ])->values()->all(),
                    'assignees' => $card->assignees->map(fn($member) => [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'avatar' => $member->avatar_url,
                        'initials' => $member->avatar_initials,
                        'avatar_color' => $member->avatar_color,
                    ])->values()->all(),
                    'checklist_total' => $card->checklists->sum('checklist_total'),
                    'checklist_done' => $card->checklists->sum('checklist_done'),
                    'has_files' => $card->files_count > 0,
                    'comment_count' => $card->comments_count,
                ])->values()->all(),
            ])->values()->all(),
            'labels' => Label::where(function ($query) use ($board) {
                $query->whereNull('workspace_id')->whereNull('board_id')
                    ->orWhere('workspace_id', $board->workspace_id)
                    ->orWhere('board_id', $board->id);
            })
                ->orderBy('position')
                ->orderBy('name')
                ->get()
                ->unique(fn($label) => strtolower($label->name))
                ->map(fn($label) => ['id' => $label->id, 'name' => $label->name, 'color' => $label->color])
                ->values()
                ->all(),
            'boardMembers' => $board->members->map(fn($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'avatar' => $member->avatar_url,
                'initials' => $member->avatar_initials,
                'avatar_color' => $member->avatar_color,
                'role' => $member->pivot->role ?? 'member',
            ])->values()->all(),
            'workspaceMembers' => $board->workspace->members
                ->filter(fn($member) => ! $board->members->contains('id', $member->id))
                ->map(fn($member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'avatar' => $member->avatar_url,
                    'initials' => $member->avatar_initials,
                    'avatar_color' => $member->avatar_color,
                    'role' => 'workspace',
                ])->values()->all(),
            'allWorkspaces' => $allWorkspaces->filter(fn($workspace) => $workspace->boards->isNotEmpty())->values()->map(fn($workspace) => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'boards' => $workspace->boards->map(fn($workspaceBoard) => [
                    'id' => $workspaceBoard->id,
                    'name' => $workspaceBoard->name,
                    'slug' => $workspaceBoard->slug,
                    'type' => $workspaceBoard->type,
                    'workspace_id' => $workspaceBoard->workspace_id,
                    'is_starred' => (bool) $workspaceBoard->is_starred,
                    'background_type' => $workspaceBoard->background_type,
                    'background_value' => $workspaceBoard->background_value,
                    'cover_type' => $workspaceBoard->cover_type,
                    'cover_value' => $workspaceBoard->cover_value,
                    'lists' => $workspaceBoard->activeLists->map(fn($list) => [
                        'id' => $list->id,
                        'name' => $list->name,
                        'position' => $list->position,
                    ])->values()->all(),
                    'members' => $workspaceBoard->members->map(fn($member) => [
                        'id' => $member->id,
                        'name' => $member->name,
                        'avatar' => $member->avatar_url,
                    ])->values()->all(),
                ])->values()->all(),
            ])->values()->all(),
            'refreshed_at' => now()->toISOString(),
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

        // For SMM boards or planning boards, also return matching digital system members so other team members can be assigned
        $isSmmOrPlanning = $board->type === 'smm'
            || !empty($board->is_active_smm)
            || stripos($board->name ?? '', 'smm') !== false
            || stripos($board->name ?? '', 'planning') !== false
            || stripos($board->workspace?->name ?? '', 'social media') !== false;

        $otherMembers = collect();
        if ($isSmmOrPlanning) {
            $existingIds = $boardMemberIds->concat($board->workspace->members->pluck('id'))->unique();
            $otherMembers = User::active()
                ->whereNotIn('id', $existingIds)
                ->where(function ($query) use ($q) {
                    if ($q) {
                        $query->whereRaw('LOWER(name) LIKE ?', ["%{$q}%"])
                              ->orWhereRaw('LOWER(username) LIKE ?', ["%{$q}%"])
                              ->orWhereRaw('LOWER(email) LIKE ?', ["%{$q}%"]);
                    }
                })
                ->take(30)
                ->get()
                ->map(fn($u) => $mapUser($u, 'system'))
                ->values();
        }

        return response()->json([
            'board_members'     => $boardMembers,
            'workspace_members' => $workspaceMembers,
            'other_members'     => $otherMembers,
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

        // For SMM Workspace: auto-add the user to ALL SMM boards in the workspace
        if ($workspace->name === 'Social Media Management' || stripos($workspace->name, 'smm') !== false) {
            foreach ($workspace->boards as $board) {
                if (! $board->members()->where('users.id', $validated['user_id'])->exists()) {
                    $board->members()->attach($validated['user_id'], ['role' => 'member']);
                }
            }
        }

        return response()->json(['message' => 'Member added to workspace']);
    }

    public function removeWorkspaceMember(Workspace $workspace, User $user): JsonResponse
    {
        $this->authorizeWorkspace($workspace->id);

        $workspace->members()->detach($user->id);

        // For SMM Workspace: auto-remove the user from ALL SMM boards in the workspace
        if ($workspace->name === 'Social Media Management' || stripos($workspace->name, 'smm') !== false) {
            foreach ($workspace->boards as $board) {
                $board->members()->detach($user->id);
            }
        }

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
        $isBypassed = $user->hasAnyRole(['admin', 'supervisor', 'boss']) || $isQc || $user->canFilterAllPlanningTeams();
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
        $userId = $user->id;
        $isQc = str_contains(strtolower($user->team_role ?? ''), 'qc');
        $isHead = str_contains(strtolower($user->team_role ?? ''), 'head');
        $canSeeSMM = $user->hasAnyRole(['super-admin', 'admin-digital', 'social_admin', 'social_qc', 'supervisor', 'boss', 'digital-team']) || $isQc || $isHead;

        if ($user->hasAnyRole(['super-admin', 'admin-digital'])) {
            $workspaces = Workspace::with([
                'boards' => fn($q) => $q->where('is_archived', false)->where('is_hidden', false)->orderBy('position')->select('id', 'workspace_id', 'name', 'slug', 'type', 'position', 'is_starred', 'background_type', 'background_value', 'cover_type', 'cover_value', 'created_by', 'created_at'),
                'boards.members:id,name,avatar,team_role',
                'boards.creator:id,name,avatar,team_role',
                'members:id,name,avatar,team_role',
            ])
                ->where('is_active', true)
                ->when(!$canSeeSMM, fn($q) => $q->where('name', '!=', 'Social Media Management'))
                ->orderBy('position')
                ->orderBy('id')
                ->get();
        } else {
            $allActiveWorkspaces = Workspace::with([
                'boards' => fn($q) => $q->where('is_archived', false)->where('is_hidden', false)->orderBy('position')->select('id', 'workspace_id', 'name', 'slug', 'type', 'position', 'is_starred', 'background_type', 'background_value', 'cover_type', 'cover_value', 'created_by', 'created_at'),
                'boards.members:id,name,avatar,team_role',
                'boards.creator:id,name,avatar,team_role',
                'members:id,name,avatar,team_role',
            ])
                ->where('is_active', true)
                ->when(!$canSeeSMM, fn($q) => $q->where('name', '!=', 'Social Media Management'))
                ->orderBy('position')
                ->orderBy('id')
                ->get();
                
            $workspaces = $allActiveWorkspaces->filter(function ($ws) use ($userId, $canSeeSMM) {
                if ($canSeeSMM && $ws->name === 'Social Media Management') return true;
                if ($ws->owner_id === $userId || $ws->members->contains('id', $userId)) return true;
                foreach ($ws->boards as $board) {
                    if ($board->created_by === $userId || $board->members->contains('id', $userId)) return true;
                }
                return false;
            });
        }

        $isBypassed = $user->hasAnyRole(['super-admin', 'admin-digital', 'admin', 'supervisor', 'boss']) || $isQc || $user->canFilterAllPlanningTeams();

        foreach ($workspaces as $workspace) {
            $workspace->setRelation('boards', $workspace->boards->filter(function ($board) use ($userId, $workspace, $canSeeSMM, $isBypassed) {
                if ($board->type === 'smm') {
                    return $canSeeSMM;
                }
                if ($canSeeSMM && $workspace->name === 'Social Media Management') {
                    return true;
                }
                if ($isBypassed) {
                    return true;
                }
                if ($workspace->owner_id === $userId) {
                    return true;
                }
                if ($board->created_by === $userId) {
                    return true;
                }
                return $board->members->contains('id', $userId);
            }));
        }

        // Filter out empty workspaces with no visible boards for all users in Switch Boards
        $workspaces = $workspaces->filter(function ($ws) {
            return $ws->boards->isNotEmpty();
        })->values();

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

    public function getTrash(Board $board): JsonResponse
    {
        $this->authorizeBoard($board);

        $boardListIds = \App\Models\BoardList::withTrashed()->where('board_id', $board->id)->pluck('id');

        $trashedLists = \App\Models\BoardList::onlyTrashed()->where('board_id', $board->id)->get()->map(function($list) {
            return [
                'id' => $list->id,
                'name' => $list->name,
                'type' => 'list',
                'deleted_at' => $list->deleted_at->toISOString(),
            ];
        });

        $trashedCards = \App\Models\Card::onlyTrashed()
            ->where(function($q) use ($board, $boardListIds) {
                $q->where('board_id', $board->id)
                  ->orWhereIn('board_list_id', $boardListIds);
            })
            ->get()
            ->map(function($card) {
                return [
                    'id' => $card->id,
                    'title' => $card->title,
                    'type' => 'card',
                    'deleted_at' => $card->deleted_at->toISOString(),
                ];
            });

        return response()->json([
            'items' => collect($trashedLists)->merge($trashedCards)->sortByDesc('deleted_at')->values()->all()
        ]);
    }

    public function restoreTrash(Request $request, Board $board): JsonResponse
    {
        $this->authorizeBoard($board);
        $request->validate([
            'id' => 'required',
            'type' => 'required|in:list,card'
        ]);

        $boardListIds = \App\Models\BoardList::withTrashed()->where('board_id', $board->id)->pluck('id');

        if ($request->type === 'list') {
            $list = \App\Models\BoardList::onlyTrashed()->where('board_id', $board->id)->findOrFail($request->id);
            $list->restore();
            \App\Models\Card::onlyTrashed()->where('board_list_id', $list->id)->restore();
        } else {
            $card = \App\Models\Card::onlyTrashed()
                ->where(function($q) use ($board, $boardListIds) {
                    $q->where('board_id', $board->id)
                      ->orWhereIn('board_list_id', $boardListIds);
                })
                ->findOrFail($request->id);

            if (!$card->board_id) {
                $card->board_id = $board->id;
                $card->save();
            }
            $card->restore();

            if ($card->sync_group_id) {
                \App\Models\Card::onlyTrashed()
                    ->where('sync_group_id', $card->sync_group_id)
                    ->restore();
            }
        }

        return response()->json(['message' => 'Item restored successfully.']);
    }

    public function restoreTrashBulk(Request $request, Board $board): JsonResponse
    {
        $this->authorizeBoard($board);
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required',
            'items.*.type' => 'required|in:list,card'
        ]);

        $boardListIds = \App\Models\BoardList::withTrashed()->where('board_id', $board->id)->pluck('id');

        foreach ($request->items as $item) {
            if ($item['type'] === 'list') {
                $list = \App\Models\BoardList::onlyTrashed()->where('board_id', $board->id)->find($item['id']);
                if ($list) {
                    $list->restore();
                    \App\Models\Card::onlyTrashed()->where('board_list_id', $list->id)->restore();
                }
            } else {
                $card = \App\Models\Card::onlyTrashed()
                    ->where(function($q) use ($board, $boardListIds) {
                        $q->where('board_id', $board->id)
                          ->orWhereIn('board_list_id', $boardListIds);
                    })
                    ->find($item['id']);
                if ($card) {
                    if (!$card->board_id) {
                        $card->board_id = $board->id;
                        $card->save();
                    }
                    $card->restore();

                    if ($card->sync_group_id) {
                        \App\Models\Card::onlyTrashed()
                            ->where('sync_group_id', $card->sync_group_id)
                            ->restore();
                    }
                }
            }
        }

        return response()->json(['message' => count($request->items) . ' items restored successfully.']);
    }

    public function forceDeleteTrashBulk(Request $request, Board $board): JsonResponse
    {
        $this->authorizeBoard($board);
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required',
            'items.*.type' => 'required|in:list,card'
        ]);

        $boardListIds = \App\Models\BoardList::withTrashed()->where('board_id', $board->id)->pluck('id');

        foreach ($request->items as $item) {
            if ($item['type'] === 'list') {
                $list = \App\Models\BoardList::onlyTrashed()->where('board_id', $board->id)->find($item['id']);
                if ($list) {
                    $list->forceDelete();
                    \App\Models\Card::onlyTrashed()->where('board_list_id', $list->id)->forceDelete();
                }
            } else {
                $card = \App\Models\Card::onlyTrashed()
                    ->where(function($q) use ($board, $boardListIds) {
                        $q->where('board_id', $board->id)
                          ->orWhereIn('board_list_id', $boardListIds);
                    })
                    ->find($item['id']);
                if ($card) {
                    $syncGroupId = $card->sync_group_id;
                    $card->forceDelete();
                    if ($syncGroupId) {
                        \App\Models\Card::onlyTrashed()
                            ->where('sync_group_id', $syncGroupId)
                            ->forceDelete();
                    }
                }
            }
        }

        return response()->json(['message' => count($request->items) . ' items permanently deleted.']);
    }

    public function forceDeleteTrash(Request $request, Board $board): JsonResponse
    {
        $this->authorizeBoard($board);
        $request->validate([
            'id' => 'required',
            'type' => 'required|in:list,card'
        ]);

        $boardListIds = \App\Models\BoardList::withTrashed()->where('board_id', $board->id)->pluck('id');

        if ($request->type === 'list') {
            $list = \App\Models\BoardList::onlyTrashed()->where('board_id', $board->id)->findOrFail($request->id);
            $list->forceDelete();
            \App\Models\Card::onlyTrashed()->where('board_list_id', $list->id)->forceDelete();
        } else {
            $card = \App\Models\Card::onlyTrashed()
                ->where(function($q) use ($board, $boardListIds) {
                    $q->where('board_id', $board->id)
                      ->orWhereIn('board_list_id', $boardListIds);
                })
                ->findOrFail($request->id);
            $syncGroupId = $card->sync_group_id;
            $card->forceDelete();
            if ($syncGroupId) {
                \App\Models\Card::onlyTrashed()
                    ->where('sync_group_id', $syncGroupId)
                    ->forceDelete();
            }
        }

        return response()->json(['message' => 'Item permanently deleted.']);
    }

    public function toggleWatch(Board $board): JsonResponse
    {
        $this->authorizeBoard($board);
        $userId = auth()->id();

        if ($board->unwatchers()->where('user_id', $userId)->exists()) {
            // Remove from unwatchers (Start watching)
            $board->unwatchers()->detach($userId);
            return response()->json(['watching' => true, 'message' => 'You are now watching this board.']);
        } else {
            // Add to unwatchers (Stop watching)
            $board->unwatchers()->attach($userId);
            return response()->json(['watching' => false, 'message' => 'You stopped watching this board.']);
        }
    }
}
