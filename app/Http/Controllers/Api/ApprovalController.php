<?php

namespace App\Http\Controllers\Api;

use App\Enums\CardStatus;
use App\Http\Controllers\Api\Concerns\FormatsApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\Card;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    use FormatsApiResponses;

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyRole(['super-admin', 'admin-digital', 'admin-crm', 'boss']), 403);

        $boardIds = $request->input('board_ids');
        if (is_array($boardIds) && count($boardIds) > 0) {
            $boardIds = array_map('intval', $boardIds);
        } else {
            $boardIds = Board::where('is_archived', false)
                ->whereHas('lists', fn ($query) => $query->where('name', 'like', '%Approved%'))
                ->pluck('id')
                ->all();
        }

        $pendingCards = Card::with(['board:id,name,slug', 'boardList:id,name', 'creator:id,name,avatar', 'assignees:id,name,avatar', 'labels'])
            ->whereIn('board_id', $boardIds)
            ->where('status', CardStatus::Review->value)
            ->where('is_archived', false)
            ->orderByRaw("FIELD(priority, 'urgent','high','medium','low')")
            ->orderBy('deadline')
            ->paginate($request->integer('per_page', 25));

        $activeCards = Card::with(['boardList', 'labels'])
            ->whereIn('board_id', $boardIds)
            ->where('is_archived', false)
            ->get();

        return response()->json([
            ...$this->paginated($pendingCards),
            'stats' => [
                'pending_review' => $pendingCards->total(),
                'urgent' => $activeCards->filter(fn ($card) => stripos($card->boardList?->name ?? '', 'Urgent') !== false)->count(),
                'overdue' => $activeCards->filter(fn ($card) => $card->isOverdue())->count(),
                'approved_today' => Card::whereIn('board_id', $boardIds)->whereDate('approved_at', today())->count(),
                'approved_week' => Card::whereIn('board_id', $boardIds)->whereBetween('approved_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            ],
            'available_boards' => Board::where('is_archived', false)->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }
}
