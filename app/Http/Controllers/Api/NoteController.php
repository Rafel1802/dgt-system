<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Models\NoteFolder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    use FormatsApiResponses;

    public function index(Request $request): JsonResponse
    {
        $type = $request->string('type', $request->route('type', 'private'))->toString();
        $team = $request->string('team')->toString() ?: null;

        $query = Note::with(['folder', 'user:id,name,avatar', 'lastEditedBy:id,name,avatar'])
            ->where('type', $type);

        if ($type === 'private') {
            $query->where('user_id', $request->user()->id);
        } else {
            $this->authorizeTeamNotes($request, $team);
            $query->where('team', $team);
        }

        $query
            ->when($request->filled('folder_id'), fn (Builder $q) => $q->where('folder_id', $request->integer('folder_id')))
            ->when($request->filled('q'), function (Builder $q) use ($request) {
                $term = $request->string('q')->toString();
                $q->where(fn ($sub) => $sub->where('title', 'like', "%{$term}%")->orWhere('plain_text', 'like', "%{$term}%"));
            })
            ->orderByDesc('is_pinned')
            ->latest('updated_at');

        return response()->json([
            ...$this->paginated($query->paginate($request->integer('per_page', 30))),
            'folders' => NoteFolder::query()
                ->where('type', $type)
                ->when($type === 'private', fn ($q) => $q->where('user_id', $request->user()->id), fn ($q) => $q->where('team', $team))
                ->orderBy('position')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:private,team'],
            'team' => ['nullable', 'string', 'max:50'],
            'folder_id' => ['nullable', 'exists:note_folders,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'plain_text' => ['nullable', 'string'],
            'is_pinned' => ['nullable', 'boolean'],
            'is_favorite' => ['nullable', 'boolean'],
        ]);

        if ($validated['type'] === 'team') {
            $this->authorizeTeamNotes($request, $validated['team'] ?? null);
        }

        $note = Note::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'last_edited_by' => $request->user()->id,
        ]);

        return response()->json(['note' => $note], 201);
    }

    public function update(Request $request, Note $note): JsonResponse
    {
        $this->authorizeNote($request, $note);

        $validated = $request->validate([
            'folder_id' => ['nullable', 'exists:note_folders,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'plain_text' => ['nullable', 'string'],
            'is_pinned' => ['nullable', 'boolean'],
            'is_favorite' => ['nullable', 'boolean'],
            'is_archived' => ['nullable', 'boolean'],
        ]);

        $note->update([...$validated, 'last_edited_by' => $request->user()->id]);

        return response()->json(['note' => $note->fresh(['folder', 'lastEditedBy'])]);
    }

    public function destroy(Request $request, Note $note): JsonResponse
    {
        $this->authorizeNote($request, $note);
        $note->delete();

        return response()->json(['message' => 'Note deleted.']);
    }

    private function authorizeNote(Request $request, Note $note): void
    {
        if ($note->type === 'private') {
            abort_unless($note->user_id === $request->user()->id, 403);
            return;
        }

        $this->authorizeTeamNotes($request, $note->team);
    }

    private function authorizeTeamNotes(Request $request, ?string $team): void
    {
        abort_unless($team, 422, 'Team is required for team notes.');

        $user = $request->user();
        $allowed = $user->hasAnyRole(['super-admin', 'boss'])
            || ($team === 'digital' && $user->hasAnyRole(['admin-digital', 'digital-team']))
            || ($team === 'crm' && $user->hasAnyRole(['admin-crm', 'sales-crm']));

        abort_unless($allowed, 403);
    }
}
