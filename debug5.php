<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cards = App\Models\Card::whereIn('id', [1333, 1334, 1335, 1336, 1337, 1338, 1372])->get();
foreach($cards as $c) {
    echo "ID: " . $c->id . " Title: " . $c->title . " Status: " . $c->workflow_status . " List: " . ($c->boardList->name ?? 'none') . "\n";
}
