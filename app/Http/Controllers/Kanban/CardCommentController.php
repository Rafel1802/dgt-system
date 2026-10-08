<?php

namespace App\Http\Controllers\Kanban;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\CardComment;
use App\Services\KanbanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CardCommentController extends Controller
{
    /**
     * Store a new comment on a card.
     */
    public function store(Request $request, Card $card): JsonResponse
    {
        $this->authorize('comment', $card);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
        ]);

        if ($card->hasIncompleteChecklist() && app(\App\Services\BoardWorkflowService::class)->isAutomationComment($card, $validated['content'])) {
            $msg = preg_match('/\bready\b/i', $validated['content'])
                ? 'All checklist items must be 100% completed before marking this card as ready.'
                : 'All checklist items must be 100% completed before using comment automations to move or copy this card.';
            return response()->json([
                'error' => $msg,
                'message' => $msg,
                'checklist_incomplete' => true,
            ], 422);
        }

        $comment = $card->comments()->create([
            'user_id'   => auth()->id(),
            'content'   => $validated['content'],
            'is_system' => false,
        ]);

        $autoResult = app(\App\Http\Controllers\Board\CardController::class)->checkAutomations($card, null, $validated['content']);
        if (!$autoResult || stripos($validated['content'], 'ready') !== false || stripos($validated['content'], 'block') !== false) {
            app(\App\Services\BoardWorkflowService::class)->handleCommentTrigger($card, $comment);
        }

        $comment->load('user:id,name,avatar');

        return response()->json([
            'success' => true,
            'comment' => $comment,
        ], 201);
    }

    /**
     * Delete a comment (own comment or admin).
     */
    public function destroy(Card $card, CardComment $comment): JsonResponse
    {
        if ($comment->user_id !== auth()->id() && ! auth()->user()->hasAnyRole(['admin-digital', 'super-admin'])) {
            abort(403, 'You cannot delete this comment.');
        }

        if ($comment->is_system) {
            abort(403, 'System comments cannot be deleted.');
        }

        $comment->delete();

        return response()->json(['success' => true]);
    }
}
