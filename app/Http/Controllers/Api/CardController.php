<?php

namespace App\Http\Controllers\Api;

use App\Enums\CardPriority;
use App\Enums\CardStatus;
use App\Http\Controllers\Api\Concerns\FormatsApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Notifications\BoardActivityNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CardController extends Controller
{
    use FormatsApiResponses;

    public function index(Request $request): JsonResponse
    {
        $query = Card::with(['board:id,name,slug', 'boardList:id,name,board_id', 'creator:id,name,avatar', 'assignees:id,name,avatar', 'labels'])
            ->where('is_archived', false)
            ->latest('updated_at');

        if ($request->filled('board_id')) {
            $query->where('board_id', $request->integer('board_id'));
        }

        if ($request->filled('board_list_id')) {
            $query->where('board_list_id', $request->integer('board_list_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->boolean('mine')) {
            $query->whereHas('assignees', fn ($assignees) => $assignees->where('users.id', $request->user()->id));
        }

        if ($request->filled('q')) {
            $term = $request->string('q')->toString();
            $query->where(fn ($q) => $q->where('title', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%"));
        }

        return response()->json($this->paginated($query->paginate($request->integer('per_page', 25))));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'board_id' => ['nullable', 'exists:boards,id'],
            'board_list_id' => ['nullable', 'exists:board_lists,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'label' => ['nullable', 'string', 'max:100'],
            'sub_label' => ['nullable', 'string', 'max:100'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'status' => ['nullable', 'in:todo,in_progress,review,approved,rejected,done'],
            'deadline' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $board = empty($validated['board_id']) ? null : Board::findOrFail($validated['board_id']);
        if ($board) {
            abort_unless($board->hasMember($request->user()->id) || $request->user()->hasRole('super-admin'), 403);
        }

        $listId = $validated['board_list_id'] ?? null;
        if (! $listId && $board) {
            $listId = $board->lists()->orderBy('position')->value('id');
        }

        if ($listId && ! $board) {
            $board = BoardList::findOrFail($listId)->board;
            $validated['board_id'] = $board->id;
        }

        $card = Card::create([
            ...collect($validated)->except('assignee_ids')->all(),
            'board_id' => $board?->id,
            'board_list_id' => $listId,
            'label' => $validated['label'] ?? 'CRM',
            'priority' => $validated['priority'] ?? CardPriority::Medium->value,
            'status' => $validated['status'] ?? CardStatus::Todo->value,
            'position' => $listId ? Card::where('board_list_id', $listId)->max('position') + 1 : 0,
            'created_by' => $request->user()->id,
        ]);

        if (! empty($validated['assignee_ids'])) {
            $card->assignees()->sync(collect($validated['assignee_ids'])->mapWithKeys(fn ($id) => [$id => ['assigned_at' => now()]])->all());
        }

        if ($board) {
            BoardActivityNotification::send($board, 'card_created', "{$request->user()->name} created card {$card->title}", $card, true);
        }

        return response()->json(['card' => $card->load(['board', 'boardList', 'assignees', 'labels'])], 201);
    }

    public function update(Request $request, Card $card): JsonResponse
    {
        $card->loadMissing('board');
        if ($card->board) {
            abort_unless($card->board->hasMember($request->user()->id) || $request->user()->hasRole('super-admin'), 403);
        }

        $validated = $request->validate([
            'board_list_id' => ['sometimes', 'nullable', 'exists:board_lists,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'label' => ['nullable', 'string', 'max:100'],
            'sub_label' => ['nullable', 'string', 'max:100'],
            'priority' => ['sometimes', 'in:low,medium,high,urgent'],
            'status' => ['sometimes', 'in:todo,in_progress,review,approved,rejected,done'],
            'deadline' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $card->update(collect($validated)->except('assignee_ids')->all());

        if (array_key_exists('assignee_ids', $validated)) {
            $card->assignees()->sync(collect($validated['assignee_ids'] ?? [])->mapWithKeys(fn ($id) => [$id => ['assigned_at' => now()]])->all());
        }

        if ($card->board) {
            BoardActivityNotification::send($card->board, 'card_updated', "{$request->user()->name} updated card {$card->title}", $card, true);
        }

        return response()->json(['card' => $card->fresh(['board', 'boardList', 'assignees', 'labels'])]);
    }

    public function destroy(Request $request, Card $card): JsonResponse
    {
        $card->loadMissing('board');
        if ($card->board) {
            abort_unless($card->board->hasMember($request->user()->id) || $request->user()->hasRole('super-admin'), 403);
            BoardActivityNotification::send($card->board, 'card_deleted', "{$request->user()->name} archived card {$card->title}", $card, true);
        }

        $card->update(['is_archived' => true]);
        $card->delete();

        return response()->json(['message' => 'Card archived.']);
    }
}
