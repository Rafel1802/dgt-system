<?php

namespace App\Services;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CardComment;
use Illuminate\Support\Str;

class BoardWorkflowService
{
    /**
     * Handle custom workflows triggered by comments on planning and workflow boards.
     */
    public function handleCommentTrigger(Card $card, CardComment $comment)
    {
        $board = $card->board;
        if (!$board) return;

        $template = $board->is_template ? null : ($board->name ?? '');
        $isPlanning = stripos($template, 'Planning board') !== false || $board->is_template; // Adjust condition if we use a specific DB column later
        
        // Better check: use standard checking if template name is preserved in the board's name, or if we can track it.
        // Actually, let's just check the board name prefixes.
        $isPlanning = stripos($board->name ?? '', 'Planning board') !== false;
        $isWorkflow = stripos($board->name ?? '', 'Workflow board') !== false;

        if ($isPlanning) {
            $this->handlePlanningBoardComment($card, $comment);
        } elseif ($isWorkflow) {
            $this->handleWorkflowBoardComment($card, $comment);
        }
    }

    private function handlePlanningBoardComment(Card $card, CardComment $newComment)
    {
        $text = trim(strtolower($newComment->content ?? $newComment->body));
        
        if (!str_contains($text, 'ready')) {
            return;
        }

        // Verify the author is an assignee
        $assignees = $card->assignees;
        if (!$assignees->contains('id', $newComment->user_id)) {
            return; // Commenter is not assigned
        }

        // Trigger the workflow immediately if ANY assignee comments "Ready"
        $this->triggerPlanningToWorkflowCopy($card);
    }

    private function triggerPlanningToWorkflowCopy(Card $card)
    {
        // Find matching Workflow board in the same workspace with the same month/year
        $planningBoard = $card->board;
        
        // Extract month/year from Planning board name (e.g. "Planning board - August 2026")
        $suffix = trim(str_ireplace('Planning board', '', $planningBoard->name));
        $workflowBoardName = trim("Workflow board " . $suffix);

        $workflowBoard = Board::where('workspace_id', $planningBoard->workspace_id)
            ->where('name', $workflowBoardName)
            ->first();

        if (!$workflowBoard) {
            // Try to find ANY workflow board in the workspace if exact match fails
            $workflowBoard = Board::where('workspace_id', $planningBoard->workspace_id)
                ->where('name', 'like', '%Workflow board%')
                ->latest()
                ->first();
        }

        if (!$workflowBoard) {
            return; // Nowhere to copy
        }

        // Find "Draft" list
        $draftList = $workflowBoard->lists()->where('name', 'like', '%Draft%')->first();
        if (!$draftList) {
            // Create it if missing
            $draftList = $workflowBoard->lists()->create([
                'name' => 'Draft',
                'position' => 1
            ]);
        }

        // Check if card is already synced to avoid duplicate copies
        if ($card->sync_group_id) {
            $existingTwin = Card::where('sync_group_id', $card->sync_group_id)
                ->where('board_id', $workflowBoard->id)
                ->first();
            if ($existingTwin) {
                return; // Already copied
            }
        }

        // Replicate and sync
        $card->replicateRelationally($workflowBoard->id, $draftList->id, $card->title, null, true);
        
        // Add a system comment
        app(\App\Http\Controllers\Board\CardController::class)->addSystemComment(
            $card, 
            "All assignees are ready. Card automatically copied to **{$workflowBoard->name}** (List: Draft)."
        );
    }

    private function handleWorkflowBoardComment(Card $card, CardComment $newComment)
    {
        $text = trim(strtolower($newComment->content ?? $newComment->body));
        $user = $newComment->user;
        $role = strtolower(trim($user->team_role ?? ''));

        $currentList = strtolower(trim($card->boardList->name ?? ''));

        // 1. Draft -> Head Review (Team approved)
        if (str_contains($currentList, 'draft') && str_contains($text, 'team approved')) {
            if ($card->assignees->contains('id', $user->id)) {
                $this->moveCardToList($card, 'Head Review', "Team approved by {$user->name}");
            }
        }
        
        // 2. Head Review -> QC Review (Head Approved)
        elseif (str_contains($currentList, 'head review') && str_contains($text, 'head approved')) {
            if (str_contains($role, 'head')) {
                $this->moveCardToList($card, 'QC', "Head Approved by {$user->name}", 'Text (QC) Review (Mr. Dara)');
            }
        }

        // 3. QC Review rules
        elseif (str_contains($currentList, 'qc')) {
            if (str_contains($role, 'qc')) {
                if (str_contains($text, 'qc approved')) {
                    $this->moveCardToList($card, 'Supervisor', "QC Approved by {$user->name}", 'Supervisor Review (Ms. Somalika)');
                } elseif (str_contains($text, 'error')) {
                    $this->moveCardToList($card, 'Draft', "Error reported by QC ({$user->name})", 'Draft');
                }
            }
        }

        // 4. Supervisor rules
        elseif (str_contains($currentList, 'supervisor')) {
            if (str_contains($role, 'supervisor')) {
                if (str_contains($text, 'approved')) {
                    $this->moveCardToList($card, 'Approved', "Approved by Supervisor ({$user->name})", 'Approved');
                } elseif (str_contains($text, 'rejected')) {
                    $this->moveCardToList($card, 'Block/Waiting', "Rejected by Supervisor ({$user->name})", 'Block/Waiting');
                }
            }
        }
    }

    private function moveCardToList(Card $card, string $searchStr, string $reason, string $createName = null)
    {
        $createName = $createName ?? $searchStr;
        $board = $card->board;
        $targetList = $board->lists()->where('name', 'like', "%{$searchStr}%")->first();
        
        if (!$targetList) {
            $targetList = $board->lists()->create([
                'name' => $createName,
                'position' => $board->lists()->max('position') + 1
            ]);
        }

        $card->update(['board_list_id' => $targetList->id]);
        
        app(\App\Http\Controllers\Board\CardController::class)->addSystemComment(
            $card,
            "Card automatically moved to **{$targetList->name}** ({$reason})."
        );
        
        // Also trigger the cross-board list sync if it's Block/Waiting
        if (str_contains(strtolower($createName), 'block/waiting')) {
            $this->syncListStateAcrossBoards($card, 'Block/Waiting');
        }
    }

    public function syncListStateAcrossBoards(Card $card, string $targetListName)
    {
        if (!$card->sync_group_id) return;

        $twins = Card::where('sync_group_id', $card->sync_group_id)
            ->where('id', '!=', $card->id)
            ->get();

        foreach ($twins as $twin) {
            $twinBoard = $twin->board;
            if (!$twinBoard) continue;
            
            $twinList = $twinBoard->lists()->where('name', 'like', "%{$targetListName}%")->first();
            if ($twinList && $twin->board_list_id !== $twinList->id) {
                // We use Card::withoutEvents to prevent infinite recursion if we add observer hooks later
                // But for now just updating is fine since we aren't hooking `updated` for list syncs yet, 
                // wait, Card::updated *does* sync fields, but not board_list_id! So it's safe.
                $twin->update(['board_list_id' => $twinList->id]);
                
                $twin->comments()->create([
                    'user_id' => auth()->id(),
                    'content' => "Card automatically moved to **{$twinList->name}** (Synced from connected board).",
                    'is_system' => true,
                ]);
            }
        }
    }
}
