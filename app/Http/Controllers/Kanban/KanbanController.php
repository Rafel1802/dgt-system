<?php

namespace App\Http\Controllers\Kanban;

use App\Enums\CardLabel;
use App\Enums\CardPriority;
use App\Enums\CardStatus;
use App\Enums\CardSubLabel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kanban\StoreCardRequest;
use App\Http\Requests\Kanban\UpdateCardRequest;
use App\Models\Card;
use App\Models\User;
use App\Services\KanbanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KanbanController extends Controller
{
    public function __construct(
        private readonly KanbanService $kanbanService
    ) {}

    /**
     * Show the Kanban board.
     */
    public function index(): View
    {
        $this->authorize('viewAny', Card::class);

        $columns   = $this->kanbanService->getBoardData(auth()->user());
        $users     = User::active()->with('roles')->get(['id', 'name', 'avatar']);
        $labels    = CardLabel::options();
        $priorities = CardPriority::cases();

        return view('kanban.board', compact('columns', 'users', 'labels', 'priorities'));
    }

    /**
     * Get a single card's full data (AJAX).
     */
    public function show(Card $card): JsonResponse
    {
        $this->authorize('view', $card);

        $card->load([
            'creator:id,name,avatar',
            'assignees:id,name,avatar',
            'comments.user:id,name,avatar',
            'checklists.items.completedBy:id,name',
            'files.uploader:id,name',
        ]);

        return response()->json([
            'card'       => $card,
            'subLabels'  => CardSubLabel::forLabel($card->label),
            'canEdit'    => auth()->user()->can('update', $card),
            'canApprove' => auth()->user()->can('approve', $card),
            'canReject'  => auth()->user()->can('reject', $card),
        ]);
    }

    /**
     * Store a new card.
     */
    public function store(StoreCardRequest $request): JsonResponse
    {
        $card = $this->kanbanService->createCard(
            $request->validated(),
            auth()->user()
        );

        $card->load(['creator:id,name,avatar', 'assignees:id,name,avatar']);

        return response()->json([
            'success' => true,
            'message' => 'Task created successfully!',
            'card'    => $card,
        ], 201);
    }

    /**
     * Update card details.
     */
    public function update(UpdateCardRequest $request, Card $card): JsonResponse
    {
        $card = $this->kanbanService->updateCard(
            $card,
            $request->validated(),
            auth()->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Task updated successfully!',
            'card'    => $card->load(['creator:id,name,avatar', 'assignees:id,name,avatar']),
        ]);
    }

    /**
     * Move a card to a new status/position (drag-drop endpoint).
     */
    public function move(Request $request, Card $card): JsonResponse
    {
        $validated = $request->validate([
            'status'   => ['required', 'string'],
            'position' => ['required', 'integer', 'min:0'],
        ]);

        $newStatus = CardStatus::tryFrom($validated['status']);
        if (! $newStatus) {
            return response()->json(['message' => 'Invalid status.'], 422);
        }

        $user = auth()->user();

        // Check transition is allowed
        if (! $user->can('moveTo', [$card, $newStatus])) {
            return response()->json(['message' => 'You are not allowed to move this card to ' . $newStatus->label() . '.'], 403);
        }

        $card = $this->kanbanService->moveCard($card, $newStatus, $validated['position'], $user);

        return response()->json([
            'success' => true,
            'card'    => $card,
        ]);
    }

    /**
     * Bulk reorder cards within a column after drag-drop.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string'],
            'ids'    => ['required', 'array'],
            'ids.*'  => ['integer'],
        ]);

        $status = CardStatus::tryFrom($validated['status']);
        if (! $status) {
            return response()->json(['message' => 'Invalid status.'], 422);
        }

        $this->kanbanService->reorderCards($validated['ids'], $status);

        return response()->json(['success' => true]);
    }

    /**
     * Approve a card (Supervisor).
     */
    public function approve(Card $card): JsonResponse
    {
        $this->authorize('approve', $card);

        $card = $this->kanbanService->approveCard($card, auth()->user());

        return response()->json([
            'success' => true,
            'message' => 'Task approved! Boss has been notified via email.',
            'card'    => $card,
        ]);
    }

    /**
     * Reject a card (Supervisor).
     */
    public function reject(Request $request, Card $card): JsonResponse
    {
        $this->authorize('reject', $card);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $card = $this->kanbanService->rejectCard($card, auth()->user(), $validated['reason']);

        return response()->json([
            'success' => true,
            'message' => 'Task rejected. Creator has been notified.',
            'card'    => $card,
        ]);
    }

    /**
     * Soft-delete a card.
     */
    public function destroy(Card $card): JsonResponse
    {
        $this->authorize('delete', $card);

        $card->delete();

        return response()->json(['success' => true, 'message' => 'Task deleted.']);
    }

    /**
     * Get sub-labels for a given label (AJAX for dynamic form).
     */
    public function subLabels(Request $request): JsonResponse
    {
        $label = $request->validate(['label' => 'required|string'])['label'];
        return response()->json(['sub_labels' => CardSubLabel::forLabel($label)]);
    }
}
