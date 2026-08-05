<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$allUsers = \App\Models\User::select('id', 'name', 'username')->get();
$userLookup = [];
foreach ($allUsers as $u) {
    $userLookup[strtolower(trim($u->name))] = ['id' => $u->id, 'name' => $u->name];
    $userLookup[strtolower(trim($u->username))] = ['id' => $u->id, 'name' => $u->name];
    $coreName = preg_replace('/^(Mr\.|Ms\.|Mrs\.)\s*/i', '', $u->name);
    $coreName = preg_replace('/\s*\(.*?\)/', '', $coreName);
    $coreName = strtolower(trim($coreName));
    if ($coreName) {
        $userLookup[$coreName] = ['id' => $u->id, 'name' => $u->name];
    }
}

function resolveMember($rawName, $userLookup)
{
    $rawName = trim($rawName);
    if (empty($rawName) || in_array(strtolower($rawName), ['none', 'n/a', '-', 'blank', 'no member'])) {
        return ['id' => null, 'warning' => null, 'resolved_name' => ''];
    }
    $lowerRaw = strtolower($rawName);
    if (isset($userLookup[$lowerRaw])) {
        return ['id' => $userLookup[$lowerRaw]['id'], 'warning' => null, 'resolved_name' => $userLookup[$lowerRaw]['name']];
    }
    $core = preg_replace('/^(Mr\.|Ms\.|Mrs\.)\s*/i', '', $rawName);
    $core = preg_replace('/\s*\(.*?\)/', '', $core);
    $core = strtolower(trim($core));
    if (isset($userLookup[$core])) {
        return ['id' => $userLookup[$core]['id'], 'warning' => null, 'resolved_name' => $userLookup[$core]['name']];
    }
    
    // Check partial matches just in case
    $matches = [];
    foreach ($userLookup as $key => $val) {
        if (strpos($key, 'nalin') !== false || strpos($key, 'sreypich') !== false) {
            $matches[$key] = $val['name'];
        }
    }
    return ['id' => null, 'warning' => "Not matched", 'resolved_name' => $rawName, 'matches' => $matches];
}

echo "Nalin: \n";
print_r(resolveMember('Nalin', $userLookup));

echo "Lyza: \n";
print_r(resolveMember('Lyza', $userLookup));

echo "\nAll DB Users:\n";
foreach($allUsers as $u) {
    if (stripos($u->name, 'sreypich') !== false || stripos($u->name, 'nalin') !== false || stripos($u->name, 'lyza') !== false) {
        echo "- {$u->name} (Username: {$u->username})\n";
    }
}
