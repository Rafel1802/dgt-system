<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$card = App\Models\Card::with('syncSiblings.boardList')->find(1380);
if ($card) {
    $card->append('workflow_status');
    echo json_encode($card->toArray(), JSON_PRETTY_PRINT);
}

// Let's also check if there are ANY cards in a Workflow board with this sync_group_id
$workflowCard = App\Models\Card::where('sync_group_id', '87a745f4-35bf-4a1f-845f-597d86c6b93b')
    ->whereHas('board', function($q) {
        $q->where('name', 'like', '%Workflow%');
    })->first();

if ($workflowCard) {
    echo "\nWorkflow card exists: " . $workflowCard->id . " in list " . $workflowCard->boardList->name . "\n";
} else {
    echo "\nNo Workflow card for this sync group yet.\n";
}
