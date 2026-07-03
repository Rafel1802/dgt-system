<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Workspace;
use App\Notifications\BoardActivityNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BoardController extends Controller
{
    use FormatsApiResponses;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Board::with(['workspace:id,name,slug,color', 'creator:id,name,avatar'])
            ->withCount(['lists', 'cards'])
            ->where('is_archived', false)
            ->where('is_hidden', false);

        if (! $user->hasRole('super-admin')) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhere('visibility', 'public')
                    ->orWhereHas('members', fn ($members) => $members->where('users.id', $user->id))
                    ->orWhereHas('workspace', fn ($workspace) => $workspace
                        ->where('owner_id', $user->id)
                        ->orWhere('visibility', 'team')
                        ->orWhereHas('members', fn ($members) => $members->where('users.id', $user->id)));
            });
        }

        if ($request->filled('workspace_id')) {
            $query->where('workspace_id', $request->integer('workspace_id'));
        }

        if ($request->filled('q')) {
            $term = $request->string('q')->toString();
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%"));
        }

        return response()->json($this->paginated(
            $query->orderBy('workspace_id')->orderBy('position')->latest()->paginate($request->integer('per_page', 25))
        ));
    }

    public function show(Request $request, Board $board): JsonResponse
    {
        $this->authorizeBoard($request, $board);

        return response()->json([
            'board' => $board->load([
                'workspace:id,name,slug,color',
                'creator:id,name,avatar',
                'members:id,name,email,avatar',
                'labels',
                'lists.cards' => fn ($query) => $query->with(['assignees:id,name,avatar', 'labels'])->where('is_archived', false)->orderBy('position'),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workspace_id' => ['nullable', 'exists:workspaces,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'background_type' => ['nullable', 'in:color,image,gradient'],
            'background_value' => ['nullable', 'string', 'max:2048'],
            'visibility' => ['nullable', 'in:private,workspace,public'],
        ]);

        $workspace = empty($validated['workspace_id'])
            ? $this->defaultWorkspace($request)
            : Workspace::findOrFail($validated['workspace_id']);

        abort_unless($request->user()->hasRole('super-admin') || $workspace->hasMember($request->user()->id) || $workspace->owner_id === $request->user()->id, 403);

        $board = Board::create([
            ...$validated,
            'workspace_id' => $workspace->id,
            'slug' => Str::slug($validated['name']) . '-' . Str::random(4),
            'created_by' => $request->user()->id,
            'position' => Board::where('workspace_id', $workspace->id)->max('position') + 1,
        ]);

        foreach (['To Do', 'In Progress', 'Review', 'Done'] as $position => $name) {
            BoardList::create(['board_id' => $board->id, 'name' => $name, 'position' => $position + 1]);
        }

        $board->members()->syncWithoutDetaching([$request->user()->id => ['role' => 'admin']]);
        BoardActivityNotification::send($board, 'new_board', "{$request->user()->name} created board {$board->name}", null, true);

        return response()->json(['board' => $board->load('lists')], 201);
    }

    public function update(Request $request, Board $board): JsonResponse
    {
        $this->authorizeBoard($request, $board, true);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'background_type' => ['sometimes', 'in:color,image,gradient'],
            'background_value' => ['nullable', 'string', 'max:2048'],
            'cover_type' => ['nullable', 'in:color,image,gradient'],
            'cover_value' => ['nullable', 'string', 'max:2048'],
            'visibility' => ['sometimes', 'in:private,workspace,public'],
            'notifications_enabled' => ['sometimes', 'boolean'],
            'browser_notifications_enabled' => ['sometimes', 'boolean'],
        ]);

        $board->update($validated);
        BoardActivityNotification::send($board, 'board_updated', "{$request->user()->name} updated board {$board->name}", null, true);

        return response()->json(['board' => $board->fresh(['workspace', 'members'])]);
    }

    public function destroy(Request $request, Board $board): JsonResponse
    {
        $this->authorizeBoard($request, $board, true);
        $board->update(['is_archived' => true]);
        $board->delete();

        return response()->json(['message' => 'Board archived.']);
    }

    private function authorizeBoard(Request $request, Board $board, bool $write = false): void
    {
        $user = $request->user();
        $board->loadMissing('members', 'workspace.members');
        $allowed = $user->hasRole('super-admin')
            || $board->created_by === $user->id
            || (! $write && $board->visibility === 'public')
            || $board->members->contains('id', $user->id)
            || $board->workspace?->hasMember($user->id);

        abort_unless($allowed, 403);
    }

    private function defaultWorkspace(Request $request): Workspace
    {
        return Workspace::query()
            ->where('owner_id', $request->user()->id)
            ->orWhereHas('members', fn ($query) => $query->where('users.id', $request->user()->id))
            ->orderBy('position')
            ->first()
            ?? Workspace::create([
                'name' => 'DGT Staff',
                'description' => 'Default workspace for desktop-created boards.',
                'color' => '#2F68ED',
                'visibility' => 'team',
                'owner_id' => $request->user()->id,
                'is_active' => true,
            ]);
    }
}
