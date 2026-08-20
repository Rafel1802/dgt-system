<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$appearance = ['background_type' => 'gradient', 'cover_value' => 'url("image.png")'];
echo Illuminate\Support\Js::from($appearance)->toHtml() . "\n";
