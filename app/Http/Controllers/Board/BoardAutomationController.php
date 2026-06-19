<?php

namespace App\Http\Controllers\Board;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\BoardAutomation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardAutomationController extends Controller
{
    public function index(Board $board): JsonResponse
    {
        $automations = BoardAutomation::where('board_id', $board->id)
            ->with(['targetBoard:id,name', 'targetList:id,name', 'triggerBoard:id,name', 'triggerList:id,name', 'targetAssignee:id,name'])
            ->get();

        return response()->json(['automations' => $automations]);
    }

    public function store(Request $request, Board $board): JsonResponse
    {
        $validated = $request->validate([
            'trigger_word' => 'nullable|string|max:100',
            'trigger_board_id' => 'nullable|exists:boards,id',
            'trigger_list_id' => 'nullable|exists:board_lists,id',
            'target_board_id' => 'required|exists:boards,id',
            'target_list_id' => 'required|exists:board_lists,id',
            'target_assignee_id' => 'nullable|exists:users,id',
            'target_assignee_role' => 'nullable|string|max:50',
            'action_type' => 'nullable|in:move,copy',
        ]);

        $triggerType = 'both';
        if (empty($validated['trigger_list_id'])) {
            $triggerType = 'keyword';
        } elseif (empty($validated['trigger_word'])) {
            $triggerType = 'list';
        }

        $automation = BoardAutomation::create([
            'board_id' => $board->id, // Automation belongs to current board
            'trigger_type' => $triggerType,
            'trigger_word' => $validated['trigger_word'] ?? null,
            'trigger_board_id' => $validated['trigger_board_id'] ?? null,
            'trigger_list_id' => $validated['trigger_list_id'] ?? null,
            'target_board_id' => $validated['target_board_id'],
            'target_list_id' => $validated['target_list_id'],
            'target_assignee_id' => $validated['target_assignee_id'] ?? null,
            'target_assignee_role' => $validated['target_assignee_role'] ?? null,
            'action_type' => $validated['action_type'] ?? 'move',
        ]);

        $automation->load(['targetBoard:id,name', 'targetList:id,name', 'triggerBoard:id,name', 'triggerList:id,name', 'targetAssignee:id,name']);

        return response()->json([
            'message' => 'Automation created.',
            'automation' => $automation
        ]);
    }

    public function update(Request $request, Board $board, BoardAutomation $automation): JsonResponse
    {
        if ($automation->board_id !== $board->id) {
            return response()->json(['message' => 'Invalid automation'], 403);
        }

        $validated = $request->validate([
            'trigger_word' => 'nullable|string|max:100',
            'trigger_board_id' => 'nullable|exists:boards,id',
            'trigger_list_id' => 'nullable|exists:board_lists,id',
            'target_board_id' => 'required|exists:boards,id',
            'target_list_id' => 'required|exists:board_lists,id',
            'target_assignee_id' => 'nullable|exists:users,id',
            'target_assignee_role' => 'nullable|string|max:50',
            'action_type' => 'nullable|in:move,copy',
        ]);

        $triggerType = 'both';
        if (empty($validated['trigger_list_id'])) {
            $triggerType = 'keyword';
        } elseif (empty($validated['trigger_word'])) {
            $triggerType = 'list';
        }

        $automation->update([
            'trigger_type' => $triggerType,
            'trigger_word' => $validated['trigger_word'] ?? null,
            'trigger_board_id' => $validated['trigger_board_id'] ?? null,
            'trigger_list_id' => $validated['trigger_list_id'] ?? null,
            'target_board_id' => $validated['target_board_id'],
            'target_list_id' => $validated['target_list_id'],
            'target_assignee_id' => $validated['target_assignee_id'] ?? null,
            'target_assignee_role' => $validated['target_assignee_role'] ?? null,
            'action_type' => $validated['action_type'] ?? 'move',
        ]);

        $automation->load(['targetBoard:id,name', 'targetList:id,name', 'triggerBoard:id,name', 'triggerList:id,name', 'targetAssignee:id,name']);

        return response()->json([
            'message' => 'Automation updated.',
            'automation' => $automation
        ]);
    }

    public function destroy(Board $board, BoardAutomation $automation): JsonResponse
    {
        if ($automation->board_id !== $board->id) {
            return response()->json(['message' => 'Invalid automation'], 403);
        }

        $automation->delete();
        return response()->json(['message' => 'Automation deleted.']);
    }
}
