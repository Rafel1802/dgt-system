<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::first();
auth()->login($user);

$board = \App\Models\Board::first();

// Call the BoardController@show method directly to see the exception
$request = \Illuminate\Http\Request::create("/boards/{$board->slug}", 'GET');
$request->setUserResolver(function () use ($user) { return $user; });

$controller = app(\App\Http\Controllers\Board\BoardController::class);
try {
    $response = $controller->show($board);
    echo "Success\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
