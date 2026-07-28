<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::first();
auth()->login($user);

$workspace = \App\Models\Workspace::first();

$board = \App\Models\Board::firstOrCreate([
    'workspace_id' => $workspace->id,
    'name' => 'Test Workflow Board'
]);

$list1 = $board->lists()->firstOrCreate(['name' => 'Supervisor Review (Ms. Somalika)', 'position' => 1]);
$list2 = $board->lists()->firstOrCreate(['name' => 'Block/Waiting', 'position' => 2]);

$card = $board->cards()->firstOrCreate([
    'board_list_id' => $list1->id,
    'title' => 'Test Card',
    'created_by' => $user->id
]);

\App\Models\BoardAutomation::firstOrCreate([
    'board_id' => $board->id,
    'trigger_type' => 'keyword',
    'trigger_word' => 'Rejected',
    'trigger_board_id' => $board->id,
    'trigger_list_id' => $list1->id,
    'target_board_id' => $board->id,
    'target_list_id' => $list2->id,
    'action_type' => 'move'
]);

// Call the storeComment controller method directly to see the exception
$request = \Illuminate\Http\Request::create("/api/cards/{$card->id}/comments", 'POST', ['body' => 'Rejected']);
$request->setUserResolver(function () use ($user) { return $user; });

$controller = app(\App\Http\Controllers\Board\CardController::class);
try {
    $response = $controller->storeComment($request, $card);
    echo "Success: " . $response->getContent() . "\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
