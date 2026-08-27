<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Let's replace 'July' with 'August' using simple str_replace
$b = App\Models\Board::find(75);
if ($b) {
    // The previous regex might have failed due to some weird encoding. Let's just str_replace 'July'
    $b->name = str_replace('July', 'August', $b->name);
    $b->save();
    echo "ID 75 Now: '" . $b->name . "'\n";
}

// Let's check what boards exist in the database with "August 2026"
$boards = App\Models\Board::where('name', 'like', '%August 2026%')->get();
foreach($boards as $board) {
    echo "Board ID: " . $board->id . " Name: " . $board->name . "\n";
}

$card = App\Models\Card::where('title', 'like', '%TYPH-0507 / TYPH-5005M%')->first();
if ($card) {
    echo "Sync Group ID: " . $card->sync_group_id . "\n";
    // Get all cards in db with this sync_group_id (ignoring board restriction)
    $allCards = App\Models\Card::where('sync_group_id', $card->sync_group_id)->get();
    echo "Total cards with this sync group: " . $allCards->count() . "\n";
    foreach($allCards as $c) {
        $list = $c->boardList;
        echo " - ID: " . $c->id . " Board: " . ($c->board->name ?? 'none') . " List: " . ($list->name ?? 'none') . "\n";
    }
}
