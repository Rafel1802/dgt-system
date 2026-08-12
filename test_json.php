<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$card = \App\Models\Card::whereNotNull('content_public_date')->latest()->first();
echo json_encode($card->toArray()) . "\n";
