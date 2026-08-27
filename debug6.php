<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Check ALL boards with July
$boards = App\Models\Board::where('name', 'like', '%July%')->get();
foreach($boards as $board) {
    echo "Board ID: " . $board->id . " Name: " . $board->name . " Workspace: " . $board->workspace_id . "\n";
    // Fix it just in case
    if (str_contains($board->name, 'July')) {
        $board->update(['name' => str_replace('July', 'August', $board->name)]);
        echo " -> Updated to August!\n";
    }
}

// Check TYPH-0507 card
$cards = App\Models\Card::where('title', 'like', '%TYPH-0507 / TYPH-5005M - Long Landscape%')->get();
foreach($cards as $c) {
    echo "Card ID: " . $c->id . " Board: " . ($c->board->name ?? 'none') . " Sync: " . $c->sync_group_id . "\n";
    if ($c->sync_group_id) {
        $siblings = App\Models\Card::where('sync_group_id', $c->sync_group_id)->get();
        echo "  Siblings: " . $siblings->count() . "\n";
        foreach($siblings as $s) {
            echo "   -> Sib ID: " . $s->id . " Board: " . ($s->board->name ?? 'none') . " List: " . ($s->boardList->name ?? 'none') . "\n";
        }
    }
}
