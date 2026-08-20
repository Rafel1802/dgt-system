<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$out = \Illuminate\Support\Facades\Blade::render('x-data="dashboardAppearance(@json([\'background_type\' => \'gradient\']))"');
echo $out . "\n";
