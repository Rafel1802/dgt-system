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

        $comment = $card->comments()->create([
            'user_id'   => auth()->id(),
            'content'   => $validated['content'],
            'is_system' => false,
        ]);

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
