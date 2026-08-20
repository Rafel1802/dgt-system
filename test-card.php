<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo \App\Models\Card::where('title', 'like', '%STOMP V950 - Cinematic%')->get(['id', 'title', 'status', 'board_id', 'board_list_id', 'sync_group_id'])->toJson();
