<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$updated = \App\Models\WebsiteFollowUp::whereDate('created_at', '2026-08-14')->update(['created_at' => '2026-08-15 00:00:00']);
echo "Updated $updated follow-ups\n";
