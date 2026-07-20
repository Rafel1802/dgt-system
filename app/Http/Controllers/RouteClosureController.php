<?php

namespace App\Http\Controllers;

use App\Models\Board;
use Illuminate\Http\Request;

class RouteClosureController extends Controller
{
    /**
     * Handle the root redirect.
     */
    public function index()
    {
        if (auth()->check()) {
            if (auth()->user()->hasRole('admin-crm')) {
                return redirect()->route('crm.dashboard');
            }
            return redirect()->route('dashboard');
        }
        return redirect()->route('login');
    }

    /**
     * Download the Mac App.
     */
    public function downloadMacApp()
    {
        $version = '1.0.6';
        return redirect(asset("downloads/KIUQ-SYSTEM-{$version}.dmg"));
    }

    /**
     * Seed automations for boards.
     */
    public function seedAutomations()
    {
        $boards = Board::all();
        $count = 0;
        foreach ($boards as $board) {
            $name = strtolower($board->name);
            $isWorkflow = str_contains($name, 'workflow');
            $isPlanning = str_contains($name, 'planning');
            
            if ($isWorkflow) {
                \App\Models\BoardAutomation::where('board_id', $board->id)
                    ->where('trigger_board_id', $board->id)
                    ->where('target_board_id', $board->id)
                    ->delete();

                $lists = $board->lists()->get()->keyBy('name');
                $rules = [
                    ['Draft', 'Team approved', 'Head Review', 'Standard Member'],
                    ['Head Review', 'Head approved', 'Text (QC) Review (Mr. Dara)', 'Standard Member'],
                    ['Text (QC) Review (Mr. Dara)', 'QC approved', 'Supervisor Review (Ms. Somalika)', 'QC'],
                    ['Text (QC) Review (Mr. Dara)', 'Error', 'Draft', 'QC'],
                    ['Supervisor Review (Ms. Somalika)', 'Approved', 'Approved', 'Supervisor'],
                    ['Supervisor Review (Ms. Somalika)', 'Rejected', 'Block/Waiting', 'Supervisor'],
                ];
                foreach ($rules as $rule) {
                    if (isset($lists[$rule[0]]) && isset($lists[$rule[2]])) {
                        \App\Models\BoardAutomation::create([
                            'board_id' => $board->id,
                            'trigger_type' => 'keyword',
                            'trigger_word' => $rule[1],
                            'trigger_board_id' => $board->id,
                            'trigger_list_id' => $lists[$rule[0]]->id,
                            'target_board_id' => $board->id,
                            'target_list_id' => $lists[$rule[2]]->id,
                            'action_type' => 'move',
                            'target_assignee_role' => $rule[3]
                        ]);
                        $count++;
                    }
                }
            } elseif ($isPlanning) {
                $suffix = trim(str_ireplace('Planning board', '', $board->name));
                $workflowName = trim("Workflow board " . $suffix);
                $workflowBoard = \App\Models\Board::where('workspace_id', $board->workspace_id)
                    ->where('name', $workflowName)->first() 
                    ?? \App\Models\Board::where('workspace_id', $board->workspace_id)->where('name', 'like', '%Workflow board%')->latest()->first();

                if ($workflowBoard) {
                    $draftList = $workflowBoard->lists()->where('name', 'like', '%Draft%')->first();
                    if ($draftList) {
                        \App\Models\BoardAutomation::firstOrCreate([
                            'board_id' => $board->id,
                            'trigger_type' => 'keyword',
                            'trigger_word' => 'ready',
                            'trigger_board_id' => $board->id,
                            'trigger_list_id' => null,
                            'target_board_id' => $workflowBoard->id,
                            'target_list_id' => $draftList->id,
                            'action_type' => 'copy'
                        ]);
                        $count++;
                    }
                }
            }
        }
        return 'Automations seeded for ' . $count . ' rules across ' . $boards->count() . ' boards.';
    }

    /**
     * View tail of debug log.
     */
    public function debugLog()
    {
        $logPath = storage_path('logs/laravel.log');
        if (!file_exists($logPath)) return "No log file.";
        $lines = array_slice(file($logPath), -200);
        return "<pre>" . implode("", $lines) . "</pre>";
    }
}
