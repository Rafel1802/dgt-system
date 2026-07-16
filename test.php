<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$board = App\Models\Board::latest('updated_at')->first();
echo json_encode($board->only(['id','name','background_type', 'background_value']));
