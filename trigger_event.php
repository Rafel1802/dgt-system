<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
event(new \App\Events\CustomerStatusUpdatedLive(26, 'RESOLVED TEST', 'green', 'Test Agent', 'Tech Support', 'ebay'));
echo "Event triggered on private-crm.customer.ebay.26\n";
