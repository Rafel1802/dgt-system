<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::first();
auth()->login($user);

$controller = app(\App\Http\Controllers\Board\BoardController::class);

$start = microtime(true);
$workspaces = $controller->getAuthorizedWorkspaces($user);
$workspacesTime = microtime(true) - $start;

echo "getAuthorizedWorkspaces took: " . round($workspacesTime, 4) . " seconds\n";

$board = \App\Models\Board::first();
if ($board) {
    $start = microtime(true);
    $board->load(['activeLists.cards' => function ($query) {
        $query->with([
            'creator:id,name,avatar,username',
            'assignees:id,name,avatar,username',
            'labels',
        ])->withCount([
            'files', 
            'comments',
            'checklistItems as checklist_total',
            'checklistItems as checklist_done' => function($q) {
                $q->where('card_checklist_items.is_completed', true);
            }
        ]);
    }]);
    $boardTime = microtime(true) - $start;
    echo "Board loading took: " . round($boardTime, 4) . " seconds\n";
} else {
    echo "No boards found.\n";
}
