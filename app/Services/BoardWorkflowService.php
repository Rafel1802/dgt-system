<?php

namespace App\Services;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CardComment;
use App\Models\Label;
use App\Models\Workspace;
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

        // If card has an incomplete checklist, do not allow any comment automation (move or copy)
        if ($card->hasIncompleteChecklist()) {
            \Log::info("Card #{$card->id} comment automation trigger ignored because checklist is not 100% complete.");
            return;
        }

        $isPlanning = stripos($board->name ?? '', 'planning') !== false 
            || $board->type === 'smm' 
            || !empty($board->is_active_smm) 
            || $board->is_template;

        $isWorkflow = stripos($board->name ?? '', 'workflow') !== false
            || ($board->type ?? '') === 'workflow'
            || stripos($board->name ?? '', 'team a') !== false
            || stripos($board->name ?? '', 'team b') !== false
            || stripos($board->name ?? '', 'production') !== false
            || $board->lists()->where(function($q) {
                $q->where('name', 'like', '%production%')
                  ->orWhere('name', 'like', '%digital department%')
                  ->orWhere('name', 'like', '%approved%');
            })->exists();

        $commentText = strtolower(trim($comment->content ?? $comment->body ?? ''));
        $isProductionApprovedSmm = str_contains($commentText, 'production approved smm');

        if ($isPlanning) {
            $this->handlePlanningBoardComment($card, $comment);
        } elseif ($isWorkflow || $isProductionApprovedSmm) {
            $this->handleWorkflowBoardComment($card, $comment);
        }
    }

    /**
     * Determine if a comment text acts as an automation trigger (built-in workflow or board automation rule) that moves or copies the card.
     */
    public function isAutomationComment(Card $card, string $commentText): bool
    {
        $text = strtolower(trim($commentText));
        if ($text === '') {
            return false;
        }

        // Built-in workflow triggers that move, copy, or advance cards
        $workflowTriggers = [
            'ready',
            'caption ready',
            'production approved smm',
            'production approved',
            'qc approved smm',
            'qc approved',
            'approved smm',
            'team approved',
            'head approved',
            'supervisor approved',
            'approved',
            'blocked',
            'reject',
            'rejected',
            'block',
            'error',
        ];

        foreach ($workflowTriggers as $kw) {
            if ($kw === 'block') {
                if (preg_match('/\bblock\b/i', $text)) {
                    return true;
                }
            } elseif ($kw === 'ready') {
                if (preg_match('/\bready\b/i', $text)) {
                    return true;
                }
            } elseif (str_contains($text, $kw)) {
                return true;
            }
        }

        // Check database-defined BoardAutomation rules for keyword triggers that move or copy
        if ($card->board_id) {
            $hasDbAutomation = \App\Models\BoardAutomation::where('board_id', $card->board_id)
                ->whereIn('trigger_type', ['keyword', 'both'])
                ->whereIn('action_type', ['move', 'copy'])
                ->whereNotNull('trigger_word')
                ->where('trigger_word', '!=', '')
                ->get()
                ->contains(function ($rule) use ($text) {
                    $triggerWord = strtolower(trim($rule->trigger_word));
                    if ($triggerWord === '') return false;
                    return str_contains($text, $triggerWord) || preg_match('/\b' . preg_quote($triggerWord, '/') . '\b/i', $text);
                });

            if ($hasDbAutomation) {
                return true;
            }
        }

        return false;
    }

    private function handlePlanningBoardComment(Card $card, CardComment $newComment)
    {
        $text = trim(strtolower($newComment->content ?? $newComment->body));
        $user = $newComment->user ?? \App\Models\User::find($newComment->user_id);
        $isAdmin = $user && ($user->hasAnyRole(['super-admin', 'admin-digital', 'admin']) || $user->isSupervisorRole());
        $isQcUser = $user && ($user->isQc() || $user->hasRole('qc') || stripos($user->team_role ?? '', 'qc') !== false || stripos($user->name ?? '', 'dara') !== false);
        $isSupervisorUser = $user && ($user->isSupervisorRole() || $user->hasRole('supervisor') || stripos($user->team_role ?? '', 'supervisor') !== false);

        // 1. Ready comments (e.g. "Ready", "caption ready")
        if (str_contains($text, 'ready')) {
            // If card has an incomplete checklist, do not copy to workflow board or move
            if ($card->hasIncompleteChecklist()) {
                \Log::info("Card #{$card->id} ready trigger ignored because checklist is not 100% complete.");
                return;
            }

            $assignees = $card->assignees;
            if ($isAdmin || $assignees->count() === 0 || $assignees->contains('id', $newComment->user_id)) {
                $this->triggerPlanningToWorkflowCopy($card);
            }

            // Auto-move to Final Captions on SMM Planning Board if they comment "caption ready"
            if (str_contains($text, 'caption ready')) {
                $board = $card->board;
                if ($board) {
                    $finalList = $board->lists()->where('name', 'like', '%Final Captions%')->first();
                    if ($finalList && $card->board_list_id !== $finalList->id) {
                        $card->update(['board_list_id' => $finalList->id]);
                        app(\App\Http\Controllers\Board\CardController::class)->addSystemComment(
                            $card,
                            "Card automatically moved to **{$finalList->name}**."
                        );
                    }
                }
            }
            return;
        }

        // 2. SMM QC / Production Approval (e.g. "Production approved SMM", "QC approved SMM", "QC approved") or Supervisor Approval ("Approved")
        $isProductionApprovedSmm = str_contains($text, 'production approved smm');
        $isQcApproved = str_contains($text, 'qc approved') || str_contains($text, 'approved smm') || $isProductionApprovedSmm;
        $isSupervisorApproved = str_contains($text, 'approved') && !str_contains($text, 'qc') && !str_contains($text, 'head') && !str_contains($text, 'team') && !str_contains($text, 'production');

        $isDara = $user && (str_contains(strtolower($user->username ?? ''), 'dara') || str_contains(strtolower($user->name ?? ''), 'dara') || in_array($user->id, [12, 24]));
        $isKim = $user && (str_contains(strtolower($user->username ?? ''), 'kim') || str_contains(strtolower($user->name ?? ''), 'kim') || $user->id === 13);

        if ($isProductionApprovedSmm && ($isAdmin || $isSupervisorUser || $isDara || $isKim || $isQcUser)) {
            $board = $card->board;
            if ($board) {
                $apprList = $board->lists()->where('name', 'like', '%approved%')->first();
                if ($apprList && $card->board_list_id !== $apprList->id) {
                    $card->update(['board_list_id' => $apprList->id]);
                }
            }
            $card->update([
                'status' => 'approved',
                'approved_at' => $card->approved_at ?? now(),
            ]);
            $actorName = $user?->name ?? 'System';
            $leadTitle = ($isDara || $isKim) ? ($isDara ? 'Mr. Dara' : 'Mr. Kim') : 'Production Lead';
            app(\App\Http\Controllers\Board\CardController::class)->addSystemComment(
                $card,
                "Card approved by {$leadTitle} ({$actorName})."
            );
            $this->syncListStateAcrossBoards($card, 'Approved');
            return;
        }

        if (($isQcApproved && ($isAdmin || $isQcUser || $isDara || $isKim)) || ($isSupervisorApproved && ($isAdmin || $isSupervisorUser))) {
            $card->update([
                'status' => 'approved',
                'approved_at' => $card->approved_at ?? now(),
            ]);
            $actorName = $user?->name ?? 'System';
            $approverType = $isQcApproved ? 'QC' : 'Supervisor';
            app(\App\Http\Controllers\Board\CardController::class)->addSystemComment(
                $card,
                "Card approved by {$approverType} ({$actorName})."
            );
            $this->syncListStateAcrossBoards($card, 'Approved');
            return;
        }

        // 3. Supervisor / Admin Block or Reject on Planning Board (moves to Block/Waiting if list exists or created)
        if ((str_contains($text, 'blocked') || str_contains($text, 'reject') || $text === 'block') && ($isAdmin || $isSupervisorUser)) {
            $board = $card->board;
            if ($board) {
                $blockList = $board->lists()->where(function($q) {
                    $q->where('name', 'like', '%block%')
                      ->orWhere('name', 'like', '%waiting%');
                })->first();
                if (!$blockList) {
                    $blockList = $board->lists()->create([
                        'name' => 'Block/Waiting',
                        'position' => ($board->lists()->max('position') ?? 0) + 1,
                    ]);
                }
                if ($blockList && $card->board_list_id !== $blockList->id) {
                    $card->update(['board_list_id' => $blockList->id]);
                    $actorName = $user?->name ?? 'System';
                    app(\App\Http\Controllers\Board\CardController::class)->addSystemComment(
                        $card,
                        "Card moved to **{$blockList->name}** by Supervisor ({$actorName})."
                    );
                    $this->syncListStateAcrossBoards($card, 'Block/Waiting');
                }
            }
            return;
        }
    }

    public function triggerPlanningToWorkflowCopy(Card $card)
    {
        if ($card->hasIncompleteChecklist()) {
            \Log::info("Card #{$card->id} planning to workflow copy ignored because checklist is not 100% complete.");
            return;
        }

        $planningBoard = $card->board;
        if (!$planningBoard) {
            return;
        }

        $isSmmBoard = $planningBoard->type === 'smm'
            || !empty($planningBoard->is_active_smm)
            || stripos($planningBoard->name ?? '', 'smm') !== false
            || stripos($planningBoard->workspace?->name ?? '', 'social media') !== false;

        // If SMM board, ensure card is distributed to the team planning board first if not already distributed
        if ($isSmmBoard) {
            try {
                $this->distributeSmmCardToTeam($card);
            } catch (\Throwable $e) {
                \Log::warning("Error distributing SMM card to team planning board: " . $e->getMessage());
            }
        }

        // Ensure sync_group_id exists on the card
        if (!$card->sync_group_id) {
            Card::withoutEvents(function () use ($card) {
                $card->sync_group_id = (string)Str::uuid();
                $card->save();
            });
        }

        // Determine card's team ('A' or 'B')
        $cardTeam = $card->team ?: $card->detectTeam();
        if (!$cardTeam) {
            $actor = auth()->user();
            if ($actor) {
                $cardTeam = $actor->getDigitalTeam($planningBoard->workspace_id);
            }
        }
        if ($cardTeam && !$card->team) {
            try {
                $card->update(['team' => $cardTeam]);
            } catch (\Throwable $e) {}
        }

        $workflowBoard = $this->findWorkflowBoardForCard($card, $cardTeam);
        if (!$workflowBoard) {
            return; // Nowhere to copy
        }

        // Hard guarantee: Team Planning Board cards can NEVER leave their workspace
        if (!$isSmmBoard && (int)$workflowBoard->workspace_id !== (int)$planningBoard->workspace_id) {
            \Log::warning("Aborted copying card across workspaces: Planning Board #{$planningBoard->id} (WS #{$planningBoard->workspace_id}) to Workflow Board #{$workflowBoard->id} (WS #{$workflowBoard->workspace_id})");
            return;
        }

        // Find or create "Draft" list
        $draftList = $workflowBoard->lists()->where('name', 'like', '%Draft%')->first();
        if (!$draftList) {
            $draftList = $workflowBoard->lists()->create([
                'name' => 'Draft',
                'position' => 1
            ]);
        }

        // Check if card is already synced to avoid duplicate copies on this workflow board
        if ($card->sync_group_id) {
            $existingTwin = Card::where('sync_group_id', $card->sync_group_id)
                ->where('board_id', $workflowBoard->id)
                ->first();
            if ($existingTwin) {
                return; // Already copied
            }
        }

        // Replicate and sync (copies assignees, labels, checklists, sync_group_id)
        $copy = $card->replicateRelationally($workflowBoard->id, $draftList->id, $card->title, null, true);
        if ($cardTeam) {
            try {
                $copy->update(['team' => $cardTeam]);
            } catch (\Throwable $e) {}
        }
        
        // Add a system comment on the source card
        app(\App\Http\Controllers\Board\CardController::class)->addSystemComment(
            $card, 
            "All assignees are ready. Card automatically copied to **{$workflowBoard->name}** (List: Draft)."
        );

        // Add a system comment on the new copy
        $actor = auth()->user();
        $actorName = $actor?->name ?? 'System';
        try {
            $copy->comments()->create([
                'user_id' => $actor?->id ?? $card->created_by ?? 1,
                'content' => "Card automatically copied from **{$planningBoard->name}** (List: {$card->boardList?->name}) by {$actorName}.",
                'is_system' => true,
            ]);
            app(\App\Http\Controllers\Board\CardController::class)->logCardActivity(
                $copy,
                'copied',
                "copied this card from **" . ($card->boardList?->name ?? 'list') . "** on **{$planningBoard->name}** to **Draft**"
            );
        } catch (\Throwable $e) {
            // Ignore comment error
        }
    }

    /**
     * Find the matching workflow board for a card from either a team planning board or SMM planning board.
     */
    public function findWorkflowBoardForCard(Card $card, ?string $team = null): ?Board
    {
        $planningBoard = $card->board;
        if (!$planningBoard) {
            return null;
        }

        $cardTeam = $team ?: ($card->team ?: $card->detectTeam());
        if (!$cardTeam) {
            $actor = auth()->user();
            if ($actor) {
                $cardTeam = $actor->getDigitalTeam($planningBoard->workspace_id);
            }
        }

        $isSmmBoard = $planningBoard->type === 'smm'
            || !empty($planningBoard->is_active_smm)
            || stripos($planningBoard->name ?? '', 'smm') !== false
            || stripos($planningBoard->workspace?->name ?? '', 'social media') !== false;

        // Extract Month Year e.g. "September 2026", "August 2026", "July 2026"
        $monthYear = null;
        $monthName = null;
        if (preg_match('/(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})/i', $planningBoard->name ?? '', $matches)) {
            $monthYear = $matches[0];
            $monthName = $matches[1];
        } elseif (preg_match('/(January|February|March|April|May|June|July|August|September|October|November|December)/i', $planningBoard->name ?? '', $matches)) {
            $monthName = $matches[1];
        }

        if ($isSmmBoard) {
            // 1. If card already has a twin on a team planning board, look at that team board's workspace
            $targetWorkspace = null;
            if ($card->sync_group_id) {
                $teamTwin = Card::where('sync_group_id', $card->sync_group_id)
                    ->where('id', '!=', $card->id)
                    ->whereHas('board', function ($bq) {
                        $bq->where('type', '!=', 'smm')
                           ->where('name', 'not like', '%smm%')
                           ->where('name', 'not like', '%Workflow%');
                    })
                    ->with('board.workspace')
                    ->first();
                if ($teamTwin && $teamTwin->board && $teamTwin->board->workspace) {
                    $targetWorkspace = $teamTwin->board->workspace;
                }
            }

            // 2. If not found via twin, resolve team label and match workspace
            if (!$targetWorkspace) {
                $resolvedLabel = $this->resolveSmmTeamLabel($card);
                $clean = fn(string $s) => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $s));
                $normLabel = $resolvedLabel ? $clean($resolvedLabel) : '';

                $workspaces = Workspace::with(['boards.lists'])->get();

                foreach ($workspaces as $workspace) {
                    if ($workspace->id === $planningBoard->workspace_id) {
                        continue;
                    }
                    if (stripos($workspace->name, 'social media') !== false || stripos($workspace->name, 'smm') !== false) {
                        continue;
                    }

                    $normWs = $clean($workspace->name);
                    if (!empty($normLabel) && (str_contains($normWs, $normLabel) || str_contains($normLabel, $normWs))) {
                        $targetWorkspace = $workspace;
                        break;
                    }

                    $keywords = ['graphic', 'video', 'listing', 'content', 'qc', 'technical', 'writer', 'design'];
                    foreach ($keywords as $kw) {
                        if (str_contains($normLabel, $kw) && str_contains($normWs, $kw)) {
                            $targetWorkspace = $workspace;
                            break 2;
                        }
                    }
                }
            }

            if (!$targetWorkspace) {
                $targetWorkspace = Workspace::where(function($q) {
                    $q->where('name', 'like', '%Digital Department%')
                      ->orWhere('name', 'like', '%KiuQ%');
                })->first();
            }

            // 3. Find Workflow board strictly in the target workspace
            if ($targetWorkspace) {
                $wsBoards = $targetWorkspace->boards()->where('is_archived', false)->get();
                if ($wsBoards->isNotEmpty()) {
                    if ($cardTeam) {
                        $teamKey = 'team ' . strtolower($cardTeam);
                        $matched = $wsBoards->first(function ($board) use ($teamKey, $monthYear) {
                            $isWf = stripos($board->name, 'Workflow') !== false || $board->type === 'workflow';
                            if (!$isWf || !str_contains(strtolower($board->name), $teamKey)) return false;
                            if ($monthYear) return stripos($board->name, $monthYear) !== false;
                            return true;
                        });
                        if ($matched) return $matched;
                    }

                    if ($monthYear) {
                        $matched = $wsBoards->first(function ($board) use ($monthYear) {
                            return (stripos($board->name, 'Workflow') !== false || $board->type === 'workflow')
                                && stripos($board->name, $monthYear) !== false;
                        });
                        if ($matched) return $matched;
                    }

                    if ($monthName) {
                        $matched = $wsBoards->first(function ($board) use ($monthName) {
                            return (stripos($board->name, 'Workflow') !== false || $board->type === 'workflow')
                                && stripos($board->name, $monthName) !== false;
                        });
                        if ($matched) return $matched;
                    }

                    $workflowBoard = $wsBoards->first(function ($board) {
                        return stripos($board->name, 'Workflow') !== false || $board->type === 'workflow';
                    });
                    if ($workflowBoard) return $workflowBoard;
                }
            }

            // Do NOT fall back across all workspaces — that risks dumping cards into other teams' workflow boards!
            return null;
        }

        // Standard Team Planning Board (Workflow board is strictly in the SAME workspace)
        $wsBoards = Board::where('workspace_id', $planningBoard->workspace_id)
            ->where('is_archived', false)
            ->where(function ($q) {
                $q->where('name', 'like', '%Workflow%')
                  ->orWhere('type', 'workflow');
            })
            ->get();

        // If card belongs to Team A or Team B, prioritize matching that specific Team Workflow Board
        if ($cardTeam === 'A' || $cardTeam === 'B') {
            $teamKey = 'team ' . strtolower($cardTeam);
            $teamAlt = 'team' . strtolower($cardTeam);

            if ($monthYear) {
                $matched = $wsBoards->first(function ($b) use ($teamKey, $teamAlt, $monthYear) {
                    $bn = strtolower($b->name);
                    return (str_contains($bn, $teamKey) || str_contains($bn, $teamAlt)) && stripos($b->name, $monthYear) !== false;
                });
                if ($matched) return $matched;
            }

            if ($monthName) {
                $matched = $wsBoards->first(function ($b) use ($teamKey, $teamAlt, $monthName) {
                    $bn = strtolower($b->name);
                    return (str_contains($bn, $teamKey) || str_contains($bn, $teamAlt)) && stripos($b->name, $monthName) !== false;
                });
                if ($matched) return $matched;
            }

            $matched = $wsBoards->first(function ($b) use ($teamKey, $teamAlt) {
                $bn = strtolower($b->name);
                return str_contains($bn, $teamKey) || str_contains($bn, $teamAlt);
            });
            if ($matched) return $matched;
        }

        // Fallback: match by Month/Year
        if ($monthYear) {
            $matched = $wsBoards->first(fn($b) => stripos($b->name, $monthYear) !== false);
            if ($matched) return $matched;
        }

        if ($monthName) {
            $matched = $wsBoards->first(fn($b) => stripos($b->name, $monthName) !== false);
            if ($matched) return $matched;
        }

        if ($wsBoards->isNotEmpty()) {
            return $wsBoards->sortByDesc('created_at')->first();
        }

        return null;
    }

    private function handleWorkflowBoardComment(Card $card, CardComment $newComment)
    {
        $text = trim(strtolower($newComment->content ?? $newComment->body));
        $user = $newComment->user ?? \App\Models\User::find($newComment->user_id);
        if (!$user) return;
        $role = strtolower(trim($user->team_role ?? ''));
        $username = strtolower(trim($user->username ?? ''));
        $name = strtolower(trim($user->name ?? ''));
        $board = $card->board;
        $boardName = strtolower($board?->name ?? '');

        $isAdmin = $user->hasAnyRole(['super-admin', 'admin-digital', 'admin']) || $user->isSupervisorRole();
        $isDara = str_contains($username, 'dara') || str_contains($name, 'dara') || in_array($user->id, [12, 24]);
        $isKim = str_contains($username, 'kim') || str_contains($name, 'kim') || $user->id === 13;
        $isQcUser = $isAdmin || ($user && ($user->isQc() || $user->hasRole('qc') || stripos($role, 'qc') !== false || stripos($name, 'dara') !== false || stripos($name, 'kim') !== false));
        $isProductionLead = $isAdmin || $isDara || $isKim || str_contains($role, 'production') || str_contains($role, 'qc') || str_contains($role, 'head');
        $isSupervisorUser = $isAdmin || $user->isSupervisorRole() || $user->hasRole('supervisor') || str_contains($role, 'supervisor');
        $isDigitalDepartment = $isAdmin || $isSupervisorUser || $user->hasRole('digital-team') || in_array($user->id, [1, 2, 5, 12, 13, 24]);

        // 0. Blocked / Rejected rule (applies across ALL lists on workflow boards)
        // Allow BOTH production team leads (Dara for Team A, Kim for Team B, production users) AND supervisor to comment Blocked!
        if ((str_contains($text, 'blocked') || str_contains($text, 'reject') || $text === 'block') && ($isProductionLead || $isSupervisorUser)) {
            $actorTitle = $isSupervisorUser ? 'Supervisor' : 'Production Team';
            $this->moveCardToList($card, 'Block', "Blocked by {$actorTitle} ({$user->name})", 'Blocked/Waiting');
            return;
        }

        $currentList = strtolower(trim($card->boardList->name ?? ''));
        $isProductionList = str_contains($currentList, 'production') || str_contains($currentList, 'head') || str_contains($currentList, 'qc');
        $isDigitalDeptList = str_contains($currentList, 'digital department') || str_contains($currentList, 'supervisor');

        // 1. Draft -> Production Team list (keyword: "team approved")
        if (str_contains($currentList, 'draft') && str_contains($text, 'team approved')) {
            $targetName = 'Production Team';
            if ($board) {
                $prodList = $board->lists()->where(function($q) {
                    $q->where('name', 'like', '%production%')
                      ->orWhere('name', 'like', '%head%')
                      ->orWhere('name', 'like', '%qc%');
                })->first();
                if ($prodList) {
                    $targetName = $prodList->name;
                } elseif (str_contains($boardName, 'team b')) {
                    $targetName = 'Production Team B';
                } elseif (str_contains($boardName, 'team a')) {
                    $targetName = 'Production Team A';
                }
            }
            $this->moveCardToList($card, $targetName, "Team approved by {$user->name}", $targetName);
            return;
        }

        // 1.2. Head Review -> QC Review (keyword: "head approved")
        if (str_contains($currentList, 'head') && !str_contains($currentList, 'qc') && str_contains($text, 'head approved')) {
            $qcList = $board?->lists()->where(function($q) {
                $q->where('name', 'like', '%qc%')->orWhere('name', 'like', '%text%');
            })->first();
            if ($qcList) {
                $this->moveCardToList($card, $qcList->name, "Head approved by {$user->name}", $qcList->name);
                return;
            }
        }

        // 1.5. Production approved SMM -> Approved list (Can be triggered by Dara, Kim, Admin, Supervisor, QC, or Production lead from any list)
        if (str_contains($text, 'production approved smm')) {
            $canApprove = $isAdmin || $isSupervisorUser || $isDara || $isKim || $isProductionLead || $isQcUser;
            if ($canApprove) {
                $targetName = 'Approved';
                if ($board) {
                    $apprList = $board->lists()->where(function($q) {
                        $q->where('name', 'Approved')
                          ->orWhere('name', 'like', '%approved%');
                    })->first();
                    if ($apprList) {
                        $targetName = $apprList->name;
                    }
                }
                $leadTitle = ($isDara || $isKim) ? ($isDara ? 'Mr. Dara' : 'Mr. Kim') : 'Production Lead';
                $this->moveCardToList($card, $targetName, "Production approved SMM by {$leadTitle} ({$user->name})", $targetName);
                return;
            }
        }
        
        // 2. Production Team list -> Digital Department list (when Dara or Kim comments "production approved", backward-compatible: "head approved", "qc approved")
        elseif ($isProductionList && !str_contains($text, 'production approved smm') && (str_contains($text, 'production approved') || str_contains($text, 'head approved') || str_contains($text, 'qc approved') || str_contains($text, 'approved smm'))) {
            $canApprove = $isAdmin || $isSupervisorUser || $isDara || $isKim || $isProductionLead;

            if ($canApprove) {
                $targetName = 'Digital Department';
                if ($board) {
                    $ddList = $board->lists()->where(function($q) {
                        $q->where('name', 'like', '%digital department%')
                          ->orWhere('name', 'like', '%supervisor%');
                    })->first();
                    if ($ddList) {
                        $targetName = $ddList->name;
                    }
                }
                $this->moveCardToList($card, $targetName, "Production Approved by {$user->name}", $targetName);
                return;
            }
        }

        // 3. Error in Production Team -> Draft
        elseif ($isProductionList && str_contains($text, 'error')) {
            if ($isProductionLead || $isAdmin || $isSupervisorUser) {
                $this->moveCardToList($card, 'Draft', "Error reported by Production Team ({$user->name})", 'Draft');
                return;
            }
        }

        // 4. Digital Department list or Production Team list -> Approved list (keyword: "approved" or "supervisor approved")
        elseif (($isDigitalDeptList || $isProductionList) && str_contains($text, 'approved') && !str_contains($text, 'production') && !str_contains($text, 'head') && !str_contains($text, 'team') && !str_contains($text, 'qc')) {
            if ($isSupervisorUser || $isDigitalDepartment || $isAdmin) {
                $targetName = 'Approved';
                if ($board) {
                    $apprList = $board->lists()->where('name', 'like', '%approved%')->first();
                    if ($apprList) {
                        $targetName = $apprList->name;
                    }
                }
                $this->moveCardToList($card, $targetName, "Approved by {$user->name}", $targetName);
                return;
            }
        }
    }

    private function moveCardToList(Card $card, string $searchStr, string $reason, ?string $createName = null)
    {
        if ($card->hasIncompleteChecklist()) {
            \Log::info("Card #{$card->id} move to list ignored because checklist is not 100% complete.");
            return;
        }

        $createName = $createName ?? $searchStr;
        $board = $card->board;
        if (!$board) return;

        $targetList = null;
        if (str_contains(strtolower($searchStr), 'block')) {
            $targetList = $board->lists()->where(function($q) {
                $q->where('name', 'like', '%block%')
                  ->orWhere('name', 'like', '%waiting%');
            })->first();
        } elseif (str_contains(strtolower($searchStr), 'approved')) {
            $targetList = $board->lists()->where(function($q) {
                $q->where('name', 'Approved')
                  ->orWhere('name', 'like', '%approved%');
            })->first();
        } else {
            $targetList = $board->lists()->where(function($q) use ($searchStr, $createName) {
                $q->where('name', $searchStr)
                  ->orWhere('name', $createName)
                  ->orWhere('name', 'like', "%{$searchStr}%")
                  ->orWhere('name', 'like', "%{$createName}%");
            })->first();
        }
        
        if (!$targetList) {
            $targetList = $board->lists()->create([
                'name' => $createName,
                'position' => ($board->lists()->max('position') ?? 0) + 1
            ]);
        }

        // Shift existing cards in the target list down to place moved card at position 0
        \App\Models\Card::where('board_list_id', $targetList->id)
            ->where('id', '!=', $card->id)
            ->increment('position');

        $card->update([
            'board_id' => $board->id,
            'board_list_id' => $targetList->id,
            'position' => 0,
        ]);
        $card->unsetRelation('board');
        $card->unsetRelation('boardList');
        
        app(\App\Http\Controllers\Board\CardController::class)->addSystemComment(
            $card,
            "Card automatically moved to **{$targetList->name}** ({$reason})."
        );
        
        // Also trigger the cross-board list sync if it's Block/Waiting or Approved
        if (str_contains(strtolower($createName), 'block')) {
            $this->syncListStateAcrossBoards($card, 'Block/Waiting');
        } elseif (str_contains(strtolower($createName), 'approved') || str_contains(strtolower($targetList->name), 'approved')) {
            $card->update([
                'status' => 'approved',
                'approved_at' => $card->approved_at ?? now()
            ]);
            $this->syncListStateAcrossBoards($card, 'Approved');
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
            
            if (trim(strtolower($targetListName)) === 'approved') {
                $twin->update([
                    'status' => 'approved',
                    'approved_at' => $twin->approved_at ?? now()
                ]);
            }
            if (trim(strtolower($targetListName)) === 'todo') {
                $twin->update([
                    'status' => 'todo',
                    'approved_at' => null
                ]);
            }
            
            // If the twin board is a Planning board, we do NOT move it to the target list. 
            // We just update its status (handled above) and skip list movement!
            if ((trim(strtolower($targetListName)) === 'approved' || trim(strtolower($targetListName)) === 'todo') && 
                (stripos($twinBoard->name, 'Planning board') !== false || stripos($twinBoard->name, 'planning') !== false || $twinBoard->is_template)) {
                continue; // Skip the list moving logic!
            }
            
            // If the target state is "Block/Waiting" and the twin board is a Planning board,
            // we do NOT move it to the "Block/Waiting" list. It should stay in its current week.
            if (str_contains(strtolower($targetListName), 'block') && 
                (stripos($twinBoard->name, 'Planning board') !== false || stripos($twinBoard->name, 'planning') !== false || $twinBoard->is_template)) {
                
                $sourceName = $card->board?->name ?? 'connected board';
                $twin->comments()->create([
                    'user_id' => auth()->id() ?? $twin->created_by ?? 1,
                    'content' => "Card on {$sourceName} was moved to **Block/Waiting**.",
                    'is_system' => true,
                ]);
                continue; // Skip the list moving logic!
            }

            $twinList = null;
            if (str_contains(strtolower($targetListName), 'block')) {
                $twinList = $twinBoard->lists()->where(function($q) {
                    $q->where('name', 'like', '%block%')
                      ->orWhere('name', 'like', '%waiting%');
                })->first();
            } else {
                $twinList = $twinBoard->lists()->where('name', 'like', "%{$targetListName}%")->first();
            }
            
            // Auto-create the list if it doesn't exist on the synced board
            if (!$twinList) {
                $twinList = $twinBoard->lists()->create([
                    'name' => $targetListName,
                    'position' => ($twinBoard->lists()->max('position') ?? 0) + 1
                ]);
            }

            if ($twinList && $twin->board_list_id !== $twinList->id) {
                $twin->update(['board_list_id' => $twinList->id]);
                
                $twin->comments()->create([
                    'user_id' => auth()->id() ?? $twin->created_by ?? 1,
                    'content' => "Card automatically moved to **{$twinList->name}** (Synced from connected board).",
                    'is_system' => true,
                ]);
            }
        }
    }

    /**
     * Synchronize a card's list across connected Planning boards (e.g. SMM Planning Board <-> Team Planning Board)
     * when moved between Week lists (Week 1, Week 2, etc.) or matching planning lists.
     */
    public function syncPlanningWeekList(Card $card, BoardList $targetList): void
    {
        if (!$card->sync_group_id) {
            return;
        }

        $sourceBoard = $card->board;
        // Do not sync week lists if the source board is a Workflow board (e.g. Draft -> QC)
        if ($sourceBoard && stripos($sourceBoard->name, 'Workflow') !== false) {
            return;
        }

        $targetListName = trim($targetList->name);

        // Do not sync workflow status lists (Approved, Draft, QC, Supervisor, Block/Waiting) through week sync
        $lowerTarget = strtolower($targetListName);
        if (
            str_contains($lowerTarget, 'approved') ||
            str_contains($lowerTarget, 'draft') ||
            str_contains($lowerTarget, 'qc') ||
            str_contains($lowerTarget, 'supervisor') ||
            str_contains($lowerTarget, 'head review') ||
            str_contains($lowerTarget, 'block') ||
            str_contains($lowerTarget, 'waiting')
        ) {
            return;
        }

        $targetWeekNum = null;
        if (preg_match('/^Week\s*(\d+)/i', $targetListName, $m)) {
            $targetWeekNum = $m[1];
        }

        $twins = Card::where('sync_group_id', $card->sync_group_id)
            ->where('id', '!=', $card->id)
            ->get();

        foreach ($twins as $twin) {
            $twinBoard = $twin->board;
            if (!$twinBoard) {
                continue;
            }

            // Exclude Workflow boards (Workflow boards follow Draft -> QC -> Head -> Supervisor -> Approved)
            if (stripos($twinBoard->name, 'Workflow') !== false) {
                continue;
            }

            // Must be a Planning board or have Week lists
            $isTwinPlanning = stripos($twinBoard->name, 'Planning') !== false
                || $twinBoard->lists()->where('name', 'like', 'Week%')->exists();
            if (!$isTwinPlanning) {
                continue;
            }

            // Find matching list on twin board
            $twinList = $twinBoard->lists->first(function ($list) use ($targetListName, $targetWeekNum) {
                $lName = trim($list->name);
                // 1. If target is a Week list (e.g. "Week 2" or "Week 2 (08 - 14 Sep)"), match by week number
                if ($targetWeekNum !== null) {
                    if (preg_match('/^Week\s*(\d+)/i', $lName, $lm)) {
                        return $lm[1] === $targetWeekNum;
                    }
                    return false;
                }
                // 2. Exact match (case insensitive)
                if (strcasecmp($lName, $targetListName) === 0) {
                    return true;
                }
                // 3. Urgent / Priority match
                if (stripos($targetListName, 'urgent') !== false && stripos($lName, 'urgent') !== false) {
                    return true;
                }
                return false;
            });

            if ($twinList && $twin->board_list_id !== $twinList->id) {
                $oldTwinListName = $twin->boardList?->name ?? 'Previous list';
                $twin->update([
                    'board_list_id' => $twinList->id,
                ]);

                $actorName = auth()->user()?->name ?? 'System';
                $sourceBoardName = $sourceBoard?->name ?? 'connected board';

                $twin->comments()->create([
                    'user_id' => auth()->id() ?? $card->created_by ?? 1,
                    'content' => "Card automatically moved from **{$oldTwinListName}** to **{$twinList->name}** (Synced from {$sourceBoardName} by {$actorName}).",
                    'is_system' => true,
                ]);

                try {
                    app(\App\Http\Controllers\Board\CardController::class)->logCardActivity(
                        $twin,
                        'moved',
                        "moved this card from **{$oldTwinListName}** to **{$twinList->name}** (Synced from {$sourceBoardName})"
                    );
                } catch (\Throwable $e) {
                    // Ignore activity logging errors if any
                }
            }
        }
    }

    /**
     * Distribute an SMM board card to the matching team workspace Planning Board
     * based on team label (Graphic, Video, Listing, Content Writing, QC, etc.).
     *
     * @param Card $card
     * @param string|null $teamLabel Explicit team label (e.g. 'Graphic', 'Video Team').
     * @param \Illuminate\Support\Collection|null $workspaces Preloaded workspaces collection.
     * @return Board|null The target team board if distributed, or null.
     */
    public function distributeSmmCardToTeam(Card $card, ?string $teamLabel = null, $workspaces = null): ?Board
    {
        $card->loadMissing('board.workspace', 'boardList', 'labels');
        $sourceBoard = $card->board;
        if (!$sourceBoard) {
            return null;
        }

        // 1. Verify this is strictly an SMM board (never distribute from a team board)
        $isSmm = $sourceBoard->type === 'smm'
            || !empty($sourceBoard->is_active_smm)
            || stripos($sourceBoard->name ?? '', 'smm') !== false
            || stripos($sourceBoard->workspace?->name ?? '', 'social media') !== false;

        // If not SMM board, do nothing
        if (!$isSmm) {
            return null;
        }

        // 2. Resolve team label
        $resolvedLabel = $this->resolveSmmTeamLabel($card, $teamLabel);

        if (empty($resolvedLabel) || $resolvedLabel === 'SMM') {
            return null;
        }

        // Synchronize smm_team_label on the card if missing or changed
        if ($card->smm_team_label !== $resolvedLabel) {
            Card::withoutEvents(function () use ($card, $resolvedLabel) {
                $card->smm_team_label = $resolvedLabel;
                $card->save();
            });
        }

        // Ensure matching canonical global label exists and is attached to this card
        try {
            $canonicalLabelName = match ($resolvedLabel) {
                'Graphic Team', 'Graphic'         => 'Graphic',
                'Video Team', 'Video'             => 'Video',
                'Listing Team', 'Listing'         => 'Listing',
                'Content Writing Team', 'Content' => 'Content',
                'SMM'                             => 'SMM',
                default                           => null,
            };

            if ($canonicalLabelName) {
                $teamLabelModel = Label::firstOrCreate(
                    ['name' => $canonicalLabelName, 'workspace_id' => null, 'board_id' => null],
                    ['color' => $this->getSmmTeamColor($canonicalLabelName)]
                );
                if (!$card->labels->contains('id', $teamLabelModel->id)) {
                    $card->labels()->syncWithoutDetaching([$teamLabelModel->id]);
                }
            }
        } catch (\Throwable $e) {
            // Ignore label sync error
        }

        // 3. Preload workspaces if not supplied
        if (!$workspaces) {
            $workspaces = Workspace::with(['boards.lists'])->get();
        }

        $mainBoardName = $sourceBoard->name ?? '';
        $monthYear = '';
        if (preg_match('/(January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{4}/i', $mainBoardName, $matches)) {
            $monthYear = $matches[0];
        }

        $teamBoard = null;

        // 4. Check in primary "Digital Department @ KiuQ" workspace first
        $digitalWs = $workspaces->first(function ($ws) {
            $n = strtolower($ws->name ?? '');
            return str_contains($n, 'digital department') || str_contains($n, 'kiuq');
        });

        if ($digitalWs) {
            // Priority: Check if unified Planning Board exists (e.g. Planning Board@KiuQ – October 2026)
            if ($monthYear) {
                $teamBoard = $digitalWs->boards->first(function ($b) use ($monthYear) {
                    if ($b->is_archived) return false;
                    $bn = strtolower($b->name);
                    return str_contains($bn, 'planning') && str_contains($bn, 'kiuq') && !str_contains($bn, 'video') && !str_contains($bn, 'graphic') && !str_contains($bn, 'listing') && !str_contains($bn, 'content') && stripos($b->name, $monthYear) !== false;
                });
            }
            if (!$teamBoard) {
                $teamBoard = $digitalWs->boards->first(function ($b) {
                    if ($b->is_archived) return false;
                    $bn = strtolower($b->name);
                    return str_contains($bn, 'planning') && str_contains($bn, 'kiuq') && !str_contains($bn, 'video') && !str_contains($bn, 'graphic') && !str_contains($bn, 'listing') && !str_contains($bn, 'content');
                });
            }

            // Fallback: Legacy per-category boards
            if (!$teamBoard) {
                $boardKeyword = match ($resolvedLabel) {
                    'Video Team'           => 'video',
                    'Graphic Team'         => 'graphic',
                    'Listing Team'         => 'listing',
                    'Content Writing Team' => 'content',
                    default                => null
                };

                if ($boardKeyword) {
                    // Try monthYear + keyword e.g. VideoPlanningBoard@KiuQ - October 2026
                    if ($monthYear) {
                        $teamBoard = $digitalWs->boards->first(function ($b) use ($boardKeyword, $monthYear) {
                            if ($b->is_archived) return false;
                            $bn = strtolower($b->name);
                            return str_contains($bn, $boardKeyword) && str_contains($bn, 'planning') && stripos($b->name, $monthYear) !== false;
                        });
                    }
                    // Try matching VideoPlanningBoard@KiuQ without month restriction
                    if (!$teamBoard) {
                        $teamBoard = $digitalWs->boards->first(function ($b) use ($boardKeyword) {
                            if ($b->is_archived) return false;
                            $bn = strtolower($b->name);
                            return str_contains($bn, $boardKeyword) && (str_contains($bn, 'planning') || str_contains($bn, '@kiuq'));
                        });
                    }
                }
            }
        }

        // 5. Fallback: match by team workspace
        if (!$teamBoard) {
            $clean = fn(string $s) => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $s));
            $normLabel = $clean($resolvedLabel);
            $targetWorkspace = null;

            foreach ($workspaces as $workspace) {
                if ($workspace->id === $sourceBoard->workspace_id) {
                    continue;
                }
                if (stripos($workspace->name, 'social media') !== false || stripos($workspace->name, 'smm') !== false) {
                    continue;
                }

                $normWs = $clean($workspace->name);

                if (!empty($normLabel) && (str_contains($normWs, $normLabel) || str_contains($normLabel, $normWs))) {
                    $targetWorkspace = $workspace;
                    break;
                }

                $keywords = ['graphic', 'video', 'listing', 'content', 'qc', 'technical', 'writer', 'design'];
                foreach ($keywords as $kw) {
                    if (str_contains($normLabel, $kw) && str_contains($normWs, $kw)) {
                        $targetWorkspace = $workspace;
                        break 2;
                    }
                }
            }

            if ($targetWorkspace && $targetWorkspace->boards->isNotEmpty()) {
                $teamBoard = $targetWorkspace->boards->first(function ($board) use ($monthYear) {
                    if ($board->is_archived) return false;
                    $isPlanning = stripos($board->name, 'Planning') !== false;
                    if ($monthYear) {
                        return $isPlanning && stripos($board->name, $monthYear) !== false;
                    }
                    return $isPlanning;
                });

                if (!$teamBoard) {
                    $teamBoard = $targetWorkspace->boards->first(function ($board) {
                        return !$board->is_archived && stripos($board->name, 'Planning') !== false;
                    });
                }

                if (!$teamBoard) {
                    $teamBoard = $targetWorkspace->boards->first(function ($board) {
                        return !$board->is_archived && stripos($board->name, 'Workflow') === false;
                    });
                }

                if (!$teamBoard) {
                    $teamBoard = $targetWorkspace->boards->first(fn($b) => !$b->is_archived) ?? $targetWorkspace->boards->first();
                }
            }
        }

        if ($teamBoard) {
            $teamBoard->loadMissing('lists');
        }

        if (!$teamBoard || $teamBoard->lists->isEmpty()) {
            return null;
        }

        // 6. Match list on the team board
        $originalListName = $card->boardList->name ?? '';
        $targetWeekNum = null;
        if (preg_match('/^Week\s*(\d+)/i', $originalListName, $m)) {
            $targetWeekNum = $m[1];
        }

        $teamList = $teamBoard->lists->first(function ($list) use ($originalListName, $targetWeekNum) {
            $lName = trim($list->name);
            $oName = trim($originalListName);

            if ($targetWeekNum !== null && preg_match('/^Week\s*(\d+)/i', $lName, $lm)) {
                return $lm[1] === $targetWeekNum;
            }

            if (strcasecmp($lName, $oName) === 0) {
                return true;
            }

            if ((stripos($lName, 'urgent') !== false || stripos($lName, 'priority') !== false) &&
                (stripos($oName, 'urgent') !== false || stripos($oName, 'priority') !== false)) {
                return true;
            }

            return false;
        });

        if (!$teamList) {
            $teamList = $teamBoard->lists->first();
        }

        // 7. Ensure sync_group_id exists
        if (!$card->sync_group_id) {
            Card::withoutEvents(function () use ($card) {
                $card->sync_group_id = (string)Str::uuid();
                $card->save();
            });
        }

        // 8. Avoid duplicate replication to the same board
        $alreadySynced = Card::where('board_id', $teamBoard->id)
            ->where('sync_group_id', $card->sync_group_id)
            ->exists();

        if ($alreadySynced) {
            return $teamBoard;
        }

        // 9. Replicate relationally
        $clone = $card->replicateRelationally($teamBoard->id, $teamList->id);
        $clone->update(['status' => 'todo']);

        // Explicitly detect and persist team ('A' or 'B') on both cards so team filtering works instantly
        $cardTeam = $card->team ?: $card->detectTeam();
        if ($cardTeam) {
            $card->update(['team' => $cardTeam]);
            $clone->update(['team' => $cardTeam]);
        }

        // 10. System comments and notifications
        $actor = auth()->user();
        $actorName = $actor?->name ?? 'System';

        try {
            $clone->comments()->create([
                'user_id' => $actor?->id ?? $card->created_by ?? 1,
                'content' => "Card automatically created from **{$sourceBoard->name}** (List: {$originalListName}) by {$actorName}.",
                'is_system' => true,
            ]);

            $card->comments()->create([
                'user_id' => $actor?->id ?? $card->created_by ?? 1,
                'content' => "Card automatically synced to **{$teamBoard->name}** (List: {$teamList->name}).",
                'is_system' => true,
            ]);
        } catch (\Throwable $e) {
            // Ignore comment error
        }

        try {
            $teamMessage = "{$actorName} created card \"{$card->title}\" synced to your Planning Board";
            foreach ($teamBoard->members as $member) {
                if ($member->id !== $actor?->id) {
                    $member->notify(new \App\Notifications\GenericDatabaseNotification([
                        'actor_id'     => $actor?->id,
                        'actor_name'   => $actorName,
                        'actor_avatar' => $actor?->avatar_url,
                        'module'       => 'digital',
                        'message'      => $teamMessage,
                        'link'         => route('boards.show', $teamBoard->slug)
                    ]));
                }
            }
        } catch (\Throwable $e) {
            // Ignore notification error
        }

        return $teamBoard;
    }

    /**
     * Resolve the target team label for an SMM card based on explicit input,
     * title tags/prefixes, content type / format keywords in the title, attached labels, or list name.
     */
    public function resolveSmmTeamLabel(Card $card, ?string $explicitTeam = null): ?string
    {
        // 1. Explicit team passed or already set on card
        $rawTeam = trim((string)($explicitTeam ?: ($card->smm_team_label ?: '')));
        if (!empty($rawTeam)) {
            return $this->normalizeSmmTeamName($rawTeam);
        }

        $title = (string)($card->title ?? '');

        // 2. Bracketed or tagged team prefix, e.g. [Graphic], [Graphic Team], (Video), Graphic: ...
        if (preg_match('/^[\[\(](.+?)[\]\)]/i', $title, $m)) {
            $tag = trim($m[1]);
            if ($matched = $this->matchSmmTeamKeyword($tag)) {
                return $matched;
            }
        }
        if (preg_match('/^(graphic|video|listing|content\s*writing|content|qc|technical)\s*[\:\-]/i', $title, $m)) {
            if ($matched = $this->matchSmmTeamKeyword($m[1])) {
                return $matched;
            }
        }

        // 3. Match format/content-type keywords in title (standard formats used on SMM boards)
        // Video keywords:
        if (preg_match('/\b(short\s*reel|long\s*landscape|reels?|shorts?|videos?|landscape|tiktok|youtube|motion|animation|clip)\b/i', $title)) {
            return 'Video Team';
        }
        // Graphic keywords:
        if (preg_match('/\b(poster\s*design|posters?|banners?|graphics?|flyers?|photos?|infographics?|thumbnails?|brochures?|artworks?|illustrations?|designs?)\b/i', $title)) {
            return 'Graphic Team';
        }
        // Listing keywords (specifically share blog, listings, captions):
        if (preg_match('/\b(share\s*blog|listings?|captions?)\b/i', $title)) {
            return 'Listing Team';
        }
        // Content Writing keywords:
        if (preg_match('/\b(content\s*writing|content\s*writer|blogs?|articles?|copywrit(ing|er))\b/i', $title)) {
            return 'Content Writing Team';
        }
        // QC keywords:
        if (preg_match('/\b(qc|quality\s*control|technical|tech\s*support|audits?|inspections?)\b/i', $title)) {
            return 'QC Team';
        }

        // 4. Any general team keyword anywhere in title
        if ($matched = $this->matchSmmTeamKeyword($title)) {
            return $matched;
        }

        // 5. Attached labels
        if ($card->relationLoaded('labels') && $card->labels->isNotEmpty()) {
            foreach ($card->labels as $lbl) {
                if ($matched = $this->matchSmmTeamKeyword($lbl->name)) {
                    return $matched;
                }
            }
        }

        // 6. List name
        $listName = $card->boardList?->name ?? '';
        if ($matched = $this->matchSmmTeamKeyword($listName)) {
            return $matched;
        }

        // 7. SMM cluster / class label
        if (!empty($card->smm_cluster_label) && ($matched = $this->matchSmmTeamKeyword($card->smm_cluster_label))) {
            return $matched;
        }

        return null;
    }

    /**
     * Map any string containing team keywords into the standard canonical SMM team name.
     */
    public function matchSmmTeamKeyword(string $text): ?string
    {
        $clean = strtolower(trim($text));

        // 1. Explicit / full team name checks first
        if (str_contains($clean, 'content writing') || str_contains($clean, 'content writer')) {
            return 'Content Writing Team';
        }
        if (str_contains($clean, 'listing team') || $clean === 'listing') {
            return 'Listing Team';
        }
        if (str_contains($clean, 'video team') || str_contains($clean, 'video') || str_contains($clean, 'reel') || str_contains($clean, 'landscape')) {
            return 'Video Team';
        }
        if (str_contains($clean, 'graphic team') || str_contains($clean, 'graphic') || str_contains($clean, 'poster') || str_contains($clean, 'banner') || str_contains($clean, 'flyer') || str_contains($clean, 'design')) {
            return 'Graphic Team';
        }
        if (str_contains($clean, 'qc team') || str_contains($clean, 'qc') || str_contains($clean, 'technical') || str_contains($clean, 'quality')) {
            return 'QC Team';
        }

        // 2. Specific keywords
        if (str_contains($clean, 'share blog') || str_contains($clean, 'caption') || str_contains($clean, 'listing')) {
            return 'Listing Team';
        }
        if (str_contains($clean, 'content') || str_contains($clean, 'writing') || str_contains($clean, 'writer') || str_contains($clean, 'blog') || str_contains($clean, 'article') || str_contains($clean, 'copywrit')) {
            return 'Content Writing Team';
        }
        if (str_contains($clean, 'smm') || $clean === 'smm') {
            return 'SMM';
        }

        return null;
    }

    /**
     * Normalize team name string to standard canonical name.
     */
    public function normalizeSmmTeamName(string $name): string
    {
        return $this->matchSmmTeamKeyword($name) ?: trim($name);
    }

    /**
     * Return canonical color for SMM team label.
     */
    public function getSmmTeamColor(string $teamName): string
    {
        return match ($this->normalizeSmmTeamName($teamName)) {
            'Graphic Team', 'Graphic'         => '#f43f5e',
            'Video Team', 'Video'             => '#f43f5e',
            'Content Writing Team', 'Content' => '#0ea5e9',
            'Listing Team', 'Listing'         => '#f59e0b',
            'SMM'                             => '#50C878',
            default                           => '#f43f5e',
        };
    }
}
