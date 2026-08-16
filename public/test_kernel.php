<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/websites?tab=follow-up');
auth()->loginUsingId(1);
$response = $kernel->handle($request);
file_put_contents(__DIR__.'/output.html', $response->getContent());
echo "Done";
