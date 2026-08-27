<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$board = App\Models\Board::where('slug', 'smm-planning-board-august-2026-Quma')->first();
if ($board) {
    echo "Board found!\n";
    echo "ID: " . $board->id . "\n";
    echo "Name: " . $board->name . "\n";
    echo "Name Hex: " . bin2hex($board->name) . "\n";
    
    // forcefully set the name to exactly "SMM Planning Board - August 2026"
    $board->name = "SMM Planning Board - August 2026";
    $board->save();
    echo "Updated successfully to August 2026\n";
} else {
    echo "Board not found by slug!\n";
}

// Let's also check the actual name of board 75 just to be sure
$b75 = App\Models\Board::find(75);
if ($b75) {
    echo "Board 75 Name: " . $b75->name . "\n";
}
