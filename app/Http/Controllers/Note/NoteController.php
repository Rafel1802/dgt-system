<?php

namespace App\Http\Controllers\Note;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\Note;
use App\Models\NoteFolder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class NoteController extends Controller
{
    private function hasTeamAccess($user, ?string $team): bool
    {
        if (! $team) {
            return false;
        }

        if ($user->hasAnyRole(['super-admin', 'boss'])) {
            return true;
        }

        return match ($team) {
            'digital' => $user->hasAnyRole(['admin-digital', 'digital-team']),
            'crm' => $user->hasAnyRole(\App\Models\User::CRM_ROLES),
            default => false,
        };
    }

    private function scopedNotesQuery(Request $request, bool $trashed = false): Builder
    {
        $user = auth()->user();
        $type = $request->input('type', 'private');
        $team = $request->input('team');

        $query = $trashed ? Note::onlyTrashed() : Note::query();

        if ($type === 'private') {
            return $query->where('type', 'private')->where('user_id', $user->id);
        }

        abort_unless($this->hasTeamAccess($user, $team), 403, 'You do not have access to this team notes area.');

        return $query->where('type', 'team')->where('team', $team);
    }

    private function safeFilename(string $name): string
    {
        $name = trim(preg_replace('/[^A-Za-z0-9._ -]+/', '-', $name));
        $name = trim(preg_replace('/\s+/', ' ', $name), ' .-_');

        return mb_substr($name ?: 'Untitled note', 0, 90);
    }

    private function noteText(Note $note): string
    {
        $body = trim((string) $note->plain_text);

        if ($body === '' && $note->content) {
            $html = preg_replace('/<(br|\/p|\/div|\/li|\/h[1-6])[^>]*>/i', "\n", $note->content);
            $body = html_entity_decode(strip_tags($html ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $body = trim(preg_replace("/\n{3,}/", "\n\n", preg_replace("/[ \t]+\n/", "\n", $body)));
        }

        return implode("\n", [
            $note->title ?: 'Untitled',
            'Updated: ' . optional($note->updated_at)->format('Y-m-d H:i'),
            str_repeat('-', 40),
            $body,
            '',
        ]);
    }

    private function downloadNotesZip(Collection $notes, string $archiveName): BinaryFileResponse
    {
        $tmpDir = storage_path('app/tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $zipPath = tempnam($tmpDir, 'notes_');
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::OVERWRITE);

        if ($notes->isEmpty()) {
            $zip->addFromString('No notes.txt', "This folder does not contain any notes yet.\n");
        }

        $usedNames = [];
        foreach ($notes as $note) {
            $baseName = $this->safeFilename($note->title ?: 'Untitled note');
            $fileName = "{$baseName}.txt";
            $counter = 2;

            while (isset($usedNames[strtolower($fileName)])) {
                $fileName = "{$baseName} {$counter}.txt";
                $counter++;
            }

            $usedNames[strtolower($fileName)] = true;
            $zip->addFromString($fileName, $this->noteText($note));
        }

        $zip->close();

        return response()
            ->download($zipPath, $this->safeFilename($archiveName) . '.zip')
            ->deleteFileAfterSend(true);
    }

    /**
     * Display the private notes index.
     */
    public function privateIndex(Request $request): View
    {
        $user = auth()->user();

        // Get private folders for this user
        $folders = NoteFolder::where('type', 'private')
            ->where('user_id', $user->id)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        // Start querying private notes for this user
        $query = Note::where('type', 'private')->where('user_id', $user->id);

        if ($request->filled('folder')) {
            $query->where('folder_id', $request->folder);
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sq) use ($q) {
                $sq->where('title', 'like', "%{$q}%")
                   ->orWhere('plain_text', 'like', "%{$q}%");
            });
        }

        $notes = $query->orderBy('is_pinned', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('notes.private', compact('folders', 'notes'));
    }

    /**
     * Display the team notes index.
     */
    public function teamIndex(Request $request): View
    {
        $user = auth()->user();
        
        // Determine which teams the user can access
        $accessibleTeams = [];
        if ($user->hasAnyRole(['super-admin', 'boss'])) {
            $accessibleTeams = ['digital', 'crm'];
        } else {
            if ($user->hasAnyRole(['admin-digital', 'digital-team'])) $accessibleTeams[] = 'digital';
            if ($user->hasAnyRole(\App\Models\User::CRM_ROLES)) $accessibleTeams[] = 'crm';
        }

        // Determine current selected team
        $currentTeam = $request->input('team');
        if (!in_array($currentTeam, $accessibleTeams)) {
            $currentTeam = count($accessibleTeams) > 0 ? $accessibleTeams[0] : null;
        }

        if (!$currentTeam) {
            abort(403, 'You do not have access to any team notes.');
        }

        // Get team folders
        $folders = NoteFolder::where('type', 'team')
            ->where('team', $currentTeam)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        // Get team notes
        $query = Note::where('type', 'team')->where('team', $currentTeam);

        if ($request->filled('folder')) {
            $query->where('folder_id', $request->folder);
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sq) use ($q) {
                $sq->where('title', 'like', "%{$q}%")
                   ->orWhere('plain_text', 'like', "%{$q}%");
            });
        }

        $notes = $query->orderBy('is_pinned', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('notes.team', compact('folders', 'notes', 'accessibleTeams', 'currentTeam'));
    }

    /**
     * AJAX: Fetch notes as JSON for dynamic switching
     */
    public function fetchNotes(Request $request)
    {
        $query = $this->scopedNotesQuery($request, $request->boolean('trash'));

        if ($request->filled('folder_id')) {
            $query->where('folder_id', $request->folder_id);
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sq) use ($q) {
                $sq->where('title', 'like', "%{$q}%")
                   ->orWhere('plain_text', 'like', "%{$q}%");
            });
        }

        $notes = $query->orderBy('is_pinned', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json($notes);
    }

    /**
     * AJAX: Store a new note
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:private,team',
            'team' => 'nullable|string',
            'folder_id' => 'nullable|exists:note_folders,id',
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'plain_text' => 'nullable|string',
        ]);

        if ($validated['type'] === 'team' && empty($validated['team'])) {
            return response()->json(['message' => 'Team is required for team notes.'], 422);
        }

        $validated['user_id'] = auth()->id();
        $validated['last_edited_by'] = auth()->id();

        $note = Note::create($validated);

        return response()->json($note);
    }

    /**
     * AJAX: Store a new folder
     */
    public function storeFolder(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:private,team',
            'team' => 'nullable|string',
            'name' => 'required|string|max:100',
        ]);

        if ($validated['type'] === 'team' && empty($validated['team'])) {
            return response()->json(['message' => 'Team is required for team folders.'], 422);
        }

        $validated['user_id'] = auth()->id();
        
        $folder = NoteFolder::create($validated);

        return response()->json($folder);
    }

    public function updateFolder(Request $request, NoteFolder $folder): JsonResponse
    {
        $this->authorize('update', $folder);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $folder->update($validated);

        return response()->json($folder);
    }

    public function destroyFolder(NoteFolder $folder): JsonResponse
    {
        $this->authorize('delete', $folder);

        $folder->notes()->update(['folder_id' => null]);
        $folder->delete();

        return response()->json(['success' => true]);
    }

    public function downloadFolder(NoteFolder $folder): BinaryFileResponse
    {
        $this->authorize('view', $folder);

        $notes = $folder->notes()
            ->orderBy('updated_at', 'desc')
            ->get();

        return $this->downloadNotesZip($notes, $folder->name ?: 'Notes folder');
    }

    public function downloadSelected(Request $request): BinaryFileResponse|JsonResponse
    {
        $validated = $request->validate([
            'note_ids' => 'required|array|min:1',
            'note_ids.*' => 'integer',
        ]);

        $notes = Note::withTrashed()
            ->whereIn('id', $validated['note_ids'])
            ->orderBy('updated_at', 'desc')
            ->get();

        foreach ($notes as $note) {
            $this->authorize('view', $note);
        }

        if ($notes->isEmpty()) {
            return response()->json(['message' => 'No notes selected.'], 422);
        }

        return $this->downloadNotesZip($notes, 'Selected notes');
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'note_ids' => 'required|array|min:1',
            'note_ids.*' => 'integer',
        ]);

        $notes = Note::whereIn('id', $validated['note_ids'])->get();

        foreach ($notes as $note) {
            $this->authorize('delete', $note);
            $note->delete();
        }

        return response()->json(['deleted_ids' => $notes->pluck('id')->values()]);
    }

    /**
     * AJAX: Update an existing note
     */
    public function update(Request $request, Note $note)
    {
        $this->authorize('update', $note);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'nullable|string',
            'plain_text' => 'nullable|string',
            'is_pinned' => 'sometimes|boolean',
            'is_favorite' => 'sometimes|boolean',
            'folder_id' => 'nullable|exists:note_folders,id',
        ]);

        $validated['last_edited_by'] = auth()->id();

        $note->update($validated);

        return response()->json($note);
    }

    /**
     * AJAX: Delete a note
     */
    public function destroy(Note $note)
    {
        $this->authorize('delete', $note);
        $note->delete(); // Soft delete

        return response()->json(['success' => true]);
    }

    public function restore(int $noteId): JsonResponse
    {
        $note = Note::withTrashed()->findOrFail($noteId);

        $this->authorize('restore', $note);
        $note->restore();

        return response()->json($note->fresh());
    }

    public function forceDestroy(int $noteId): JsonResponse
    {
        $note = Note::withTrashed()->findOrFail($noteId);

        $this->authorize('forceDelete', $note);
        $note->forceDelete();

        return response()->json(['success' => true]);
    }
}
