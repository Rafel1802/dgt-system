<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
    echo "Connected to DB successfully.\n";
    
    $usersCount = \App\Models\User::count();
    echo "Number of users: " . $usersCount . "\n";
} catch (\Exception $e) {
    echo "DB Connection Error: " . $e->getMessage() . "\n";
}
