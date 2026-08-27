<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cards = App\Models\Card::whereNotNull('sync_group_id')->get();
$found = 0;
foreach($cards as $c) {
    if ($c->workflow_status) {
        $found++;
        echo "Card ID: " . $c->id . " on Board: " . ($c->board->name ?? 'none') . " has status: " . $c->workflow_status . "\n";
    }
}
echo "Total cards with workflow_status: " . $found . "\n";
