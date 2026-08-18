<?php

namespace App\Http\Controllers\SocialMedia;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SmmPlanningBoardController extends Controller
{
    public function index()
    {
        $smmWorkspace = Workspace::firstOrCreate(
            ['name' => 'Social Media Management'],
            [
                'description' => 'Dedicated workspace for SMM Planning Boards.', 
                'color' => '#6366f1',
                'owner_id' => auth()->id() ?? 1
            ]
        );

        $workspaces = Workspace::where('id', $smmWorkspace->id)
            ->with(['boards' => function ($query) {
                $query->where('type', 'smm')->orderBy('created_at', 'desc');
            }, 'boards.creator'])
            ->get();

        // Pass all possible members (assuming digital team logic) for the workspace modal if needed
        $possibleWorkspaceMembers = \App\Models\User::role(['admin', 'admin-digital', 'digital-team', 'boss'])->get();

        $hiddenBoardsFn = function() {
            return \App\Models\Board::where('is_hidden', true)->where('type', 'smm')->with('workspace')->get();
        };
        $trashedWorkspacesFn = function() {
            return collect(); // SMM workspace is fixed, no need to show trashed workspaces here
        };
        $trashedBoardsFn = function() {
            return \App\Models\Board::onlyTrashed()->where('type', 'smm')->with('workspace')->get();
        };

        return view('boards.workspaces', [
            'workspaces' => $workspaces,
            'isSmmModule' => true,
            'possibleWorkspaceMembers' => $possibleWorkspaceMembers,
            'hiddenBoardsFn' => $hiddenBoardsFn,
            'trashedWorkspacesFn' => $trashedWorkspacesFn,
            'trashedBoardsFn' => $trashedBoardsFn,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'nullable|string|max:255',
            'description'    => 'nullable|string',
            'background'     => 'nullable|string',
            'template_month' => 'nullable|string',
            'template_year'  => 'nullable|string',
        ]);

        $smmWorkspace = Workspace::firstOrCreate(
            ['name' => 'Social Media Management'],
            [
                'description' => 'Dedicated workspace for SMM Planning Boards.', 
                'color' => '#6366f1',
                'owner_id' => auth()->id() ?? 1
            ]
        );

        $boardName = $validated['name'] ?? null;
        if (empty($boardName)) {
            $month = $validated['template_month'] ?? date('F');
            $year = $validated['template_year'] ?? date('Y');
            $boardName = "SMM Planning Board - {$month} {$year}";
        }

        $board = Board::create([
            'name'         => $boardName,
            'workspace_id' => $smmWorkspace->id,
            'description'  => $validated['description'] ?? '',
            'type'         => 'smm',
            'is_active_smm'=> false,
            'slug'         => Str::slug($boardName) . '-' . Str::random(4),
            'created_by'   => auth()->id(),
            'background_type' => 'color',
            'background_value' => $validated['background'] ?? '#6366f1',
        ]);

        // Auto-create default lists
        $defaultLists = ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Final Captions'];
        foreach ($defaultLists as $index => $listName) {
            BoardList::create([
                'board_id' => $board->id,
                'name'     => $listName,
                'position' => ($index + 1) * 1000,
            ]);
        }

        // Auto-add workspace members to the new board
        $workspaceMembers = $smmWorkspace->members()->get();
        foreach ($workspaceMembers as $member) {
            $wsRole = $member->pivot->role ?? 'member';
            $boardRole = 'member';
            if ($wsRole === 'owner' || $wsRole === 'admin') $boardRole = 'admin';
            elseif ($wsRole === 'guest') $boardRole = 'observer';

            $board->members()->syncWithoutDetaching([
                $member->id => ['role' => $boardRole]
            ]);
        }

        return back()->with('success', 'SMM Planning Board created successfully.');
    }

    public function duplicate(Request $request, Board $board)
    {
        abort_unless($board->type === 'smm', 403);

        $newBoard = $board->replicate();
        $newBoard->name = $board->name . ' (Copy)';
        $newBoard->slug = Str::slug($newBoard->name) . '-' . Str::random(4);
        $newBoard->is_active_smm = false;
        $newBoard->is_hidden = false;
        $newBoard->is_archived = false;
        $newBoard->created_by = auth()->id();
        $newBoard->save();

        foreach ($board->lists as $list) {
            $newList = $list->replicate();
            $newList->board_id = $newBoard->id;
            $newList->save();
        }

        // Auto-add workspace members to the duplicated board
        $workspaceMembers = $board->workspace->members()->get();
        foreach ($workspaceMembers as $member) {
            $wsRole = $member->pivot->role ?? 'member';
            $boardRole = 'member';
            if ($wsRole === 'owner' || $wsRole === 'admin') $boardRole = 'admin';
            elseif ($wsRole === 'guest') $boardRole = 'observer';

            $newBoard->members()->syncWithoutDetaching([
                $member->id => ['role' => $boardRole]
            ]);
        }

        return back()->with('success', 'Board duplicated successfully.');
    }

    public function toggleActive(Board $board)
    {
        abort_unless($board->type === 'smm', 403);

        // Deactivate all others in the SAME workspace
        Board::where('type', 'smm')
             ->where('workspace_id', $board->workspace_id)
             ->where('id', '!=', $board->id)
             ->update(['is_active_smm' => false]);

        $board->update(['is_active_smm' => !$board->is_active_smm]);

        return back()->with('success', 'Board active status updated.');
    }

    public function toggleHidden(Board $board)
    {
        abort_unless($board->type === 'smm', 403);
        $board->update(['is_hidden' => !$board->is_hidden]);
        return back()->with('success', 'Board visibility updated.');
    }

    public function destroy(Board $board)
    {
        abort_unless($board->type === 'smm', 403);
        $board->delete();
        return back()->with('success', 'Board deleted successfully.');
    }
}
