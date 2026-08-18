<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$roles = \Spatie\Permission\Models\Role::pluck('name');
echo "Roles: " . json_encode($roles) . "\n";

$ebayUserIds = \App\Models\User::role(['ebay-team', 'ebay-supervisor', 'super-admin', 'boss', 'admin-crm'])->pluck('id');
echo "Ebay User IDs: " . json_encode($ebayUserIds) . "\n";

$allUsers = \App\Models\User::all();
foreach ($allUsers as $u) {
    echo "User: {$u->name} (ID: {$u->id}), Roles: " . json_encode($u->getRoleNames()) . "\n";
}
