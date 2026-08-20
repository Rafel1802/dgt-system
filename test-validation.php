<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$validator = \Illuminate\Support\Facades\Validator::make(
    ['url' => 'https://usaforklift.org/why-the-right-forklift-capacity-make:'],
    ['url' => 'url']
);
var_dump($validator->passes());
var_dump($validator->errors()->all());

$validator2 = \Illuminate\Support\Facades\Validator::make(
    ['created_at' => '18/08/2026'],
    ['created_at' => 'date']
);
var_dump($validator2->passes());
var_dump($validator2->errors()->all());
