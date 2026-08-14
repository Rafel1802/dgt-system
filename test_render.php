<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$allWebsites = App\Models\Website::all();
$liveWebsites = $allWebsites->filter(function($w) { return $w->isLiveOrMaintenance(); })->values();

$setting = App\Models\Setting::where('key', 'website_classes_order')->first();
$orderArray = $setting ? json_decode($setting->value, true) : [];

$groups = [];
foreach ($orderArray as $cat) {
    $groups[$cat] = $liveWebsites->where('category', $cat);
}
$groups['Uncategorized'] = $liveWebsites->filter(function($w) { return empty($w->category); })->values();

$total = 0;
foreach($groups as $k => $g) {
    $c = $g->count();
    $total += $c;
    echo "$k: $c\n";
}
echo "Total in groups: $total\n";
echo "Total liveWebsites: " . $liveWebsites->count() . "\n";
