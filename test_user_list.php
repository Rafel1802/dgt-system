<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$count = \App\Models\User::count();
echo "Total Users: $count\n";

foreach (\App\Models\User::all() as $u) {
    echo "- ID: {$u->id}, Name: {$u->name}, Roles: " . implode(', ', $u->getRoleNames()->toArray()) . "\n";
}
unlink(__FILE__);
