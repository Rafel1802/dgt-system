<?php

namespace App\Http\Controllers\Board;

use App\Http\Controllers\Controller;
use App\Models\CardComment;
use App\Models\CommentReaction;
use Illuminate\Http\Request;

class CommentReactionController extends Controller
{
    public function toggle(Request $request, CardComment $comment)
    {
        $request->validate([
            'emoji' => 'required|string|max:10',
        ]);

        $userId = auth()->id();
        $emoji = $request->emoji;

        $existingReactions = CommentReaction::where('card_comment_id', $comment->id)
            ->where('user_id', $userId)
            ->get();

        $hasExactEmoji = false;
        foreach ($existingReactions as $reaction) {
            if ($reaction->emoji === $emoji) {
                $hasExactEmoji = true;
            }
            $reaction->delete();
        }

        if (! $hasExactEmoji) {
            CommentReaction::create([
                'card_comment_id' => $comment->id,
                'user_id' => $userId,
                'emoji' => $emoji,
            ]);

            // Notify the comment owner if someone else reacted
            if ($comment->user_id && $comment->user_id !== $userId && $comment->user) {
                $user = auth()->user();
                $card = $comment->card;
                if ($card) {
                    \App\Support\InstantNotifier::send($comment->user, new \App\Notifications\GenericDatabaseNotification([
                        'type' => 'card_reaction',
                        'icon' => $emoji,
                        'title' => $user->name . ' reacted ' . $emoji . ' to your comment',
                        'message' => 'On card: ' . $card->title,
                        'link' => '/boards/' . $card->board_id . '?card=' . $card->id,
                    ]));
                }
            }
        }

        // Trigger BoardUpdated event to sync via Pusher in real-time
        $card = $comment->card;
        if ($card && $card->board_id) {
            $board = $card->board;
            if ($board) {
                event(new \App\Events\BoardUpdated($board->id, $board->slug, 'reaction_updated', $card->id, auth()->id()));
            }
        }

        // Return the updated reactions for this comment
        $reactions = $comment->reactions()->with('user:id,name,avatar_url')->get();

        return response()->json([
            'success' => true,
            'reactions' => $reactions,
        ]);
    }
}
