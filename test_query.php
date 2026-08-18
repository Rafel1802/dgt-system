<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$since = \Carbon\Carbon::now()->startOfMonth();
$until = \Carbon\Carbon::now()->endOfMonth();
$ebayUserIds = \App\Models\User::role(['ebay-team', 'ebay-supervisor', 'super-admin', 'boss', 'admin-crm'])->pluck('id');

$query = \App\Models\EbayCustomerRecord::where(fn($q) => $q->whereNull('created_by')->orWhere('created_by', 0)->orWhereIn('created_by', $ebayUserIds)->orWhereHas('handlerHistory', fn($h) => $h->whereIn('user_id', $ebayUserIds)))->whereBetween('created_at', [$since, $until]);

echo $query->toSql() . "\n";
echo json_encode($query->getBindings()) . "\n";
