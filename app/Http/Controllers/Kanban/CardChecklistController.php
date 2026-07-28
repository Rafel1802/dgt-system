<?php

namespace App\Http\Controllers\Kanban;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\CardChecklist;
use App\Models\CardChecklistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CardChecklistController extends Controller
{
    /** Create a new checklist group on a card */
    public function store(Request $request, Card $card): JsonResponse
    {
        $this->authorize('update', $card);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $checklist = $card->checklists()->create([
            'title'    => $validated['title'],
            'position' => $card->checklists()->count() + 1,
        ]);

        return response()->json(['success' => true, 'checklist' => $checklist->load('items')], 201);
    }

    /** Add an item to a checklist */
    public function storeItem(Request $request, Card $card, CardChecklist $checklist): JsonResponse
    {
        $this->authorize('update', $card);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:500'],
        ]);

        $item = $checklist->items()->create([
            'content'  => $validated['content'],
            'position' => $checklist->items()->count() + 1,
        ]);

        return response()->json(['success' => true, 'item' => $item], 201);
    }

    /** Toggle a checklist item complete/incomplete */
    public function toggleItem(Card $card, CardChecklist $checklist, CardChecklistItem $item): JsonResponse
    {
        $this->authorize('view', $card);

        $item->update([
            'is_completed' => ! $item->is_completed,
            'completed_by' => ! $item->is_completed ? auth()->id() : null,
            'completed_at' => ! $item->is_completed ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'item'    => $item->fresh(),
            'percent' => $checklist->load('items')->progressPercent(),
        ]);
    }

    /** Delete a checklist item */
    public function destroyItem(Card $card, CardChecklist $checklist, CardChecklistItem $item): JsonResponse
    {
        $this->authorize('update', $card);
        $item->delete();
        return response()->json(['success' => true]);
    }

    /** Delete an entire checklist group */
    public function destroy(Card $card, CardChecklist $checklist): JsonResponse
    {
        $this->authorize('update', $card);
        $checklist->delete();
        return response()->json(['success' => true]);
    }
}
