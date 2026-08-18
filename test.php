<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$statusCounts = \App\Models\Website::where('is_archived', false)
    ->selectRaw('status, count(*) as count')
    ->groupBy('status')
    ->pluck('count', 'status')
    ->toArray();

echo "PLUCK:\n";
print_r($statusCounts);

$statusCountsGet = \App\Models\Website::where('is_archived', false)
    ->selectRaw('status, count(*) as count')
    ->groupBy('status')
    ->get()
    ->pluck('count', 'status')
    ->toArray();

echo "GET PLUCK:\n";
print_r($statusCountsGet);

$allCount = \App\Models\Website::count();
echo "ALL COUNT: " . $allCount . "\n";
