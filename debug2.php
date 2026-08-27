<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cards = App\Models\Card::where('title', 'like', '%TYPH-0507 / TYPH-5005M%')->get();
foreach($cards as $c) {
    echo "ID: " . $c->id . " Board: " . ($c->board->name ?? 'none') . " Sync: " . $c->sync_group_id . "\n";
}

// And why did July not replace? Let's check hex of the name
$b = App\Models\Board::find(75);
if ($b) {
    echo "Name Hex: " . bin2hex($b->name) . "\n";
    $b->update(['name' => 'SMM Planning Board – August 2026']);
    echo "Updated manually to August 2026\n";
}
