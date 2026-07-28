<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::role('super-admin')->first();
if (!$user) {
    echo "No super admin found\n";
    exit;
}
auth()->login($user);
$request = Illuminate\Http\Request::create('/dashboard', 'GET');
$response = app()->make(\Illuminate\Contracts\Http\Kernel::class)->handle($request);
if ($response->getStatusCode() !== 200) {
    echo "ERROR: " . $response->getStatusCode() . "\n";
    echo $response->exception ? $response->exception->getMessage() : '';
} else {
    echo "SUCCESS\n";
}
