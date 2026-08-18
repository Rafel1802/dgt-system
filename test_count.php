<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

\App\Models\EbayCustomerRecord::truncate();
\App\Models\EbayCustomerOrder::truncate();
\App\Models\EbayCustomerHandlerHistory::truncate();

// Simulate Mr Raksa (Admin-crm)
$user = \App\Models\User::role('admin-crm')->first() ?? \App\Models\User::factory()->create()->assignRole('admin-crm');

$ebayUserIds = \App\Models\User::role(['ebay-team', 'ebay-supervisor', 'super-admin', 'boss', 'admin-crm'])->pluck('id');

// Insert Sarak (New Order) created THIS month
$sarak = \App\Models\EbayCustomerRecord::create([
    'username' => 'Sarak',
    'tab_type' => 'new_order',
    'created_by' => $user->id,
    'date' => '2026-08-18'
]);
\App\Models\EbayCustomerOrder::create([
    'ebay_customer_record_id' => $sarak->id,
    'ebay_store_id' => 1,
    'order_id' => '123',
    'ordered_at' => '2026-08-18',
    'created_by' => $user->id,
    'price' => 12000
]);

// Insert Kim Marady (Negatives Feedbacks) created THIS month
$kim = \App\Models\EbayCustomerRecord::create([
    'username' => 'Kim Marady',
    'tab_type' => 'negatives_feedbacks',
    'created_by' => $user->id,
    // date is null
]);

$since = \Carbon\Carbon::now()->startOfMonth();
$until = \Carbon\Carbon::now()->endOfMonth();

$count = \App\Models\EbayCustomerRecord::where(fn($q) => $q->whereNull('created_by')->orWhere('created_by', 0)->orWhereIn('created_by', $ebayUserIds)->orWhereHas('handlerHistory', fn($h) => $h->whereIn('user_id', $ebayUserIds)))->whereBetween('created_at', [$since, $until])->count();
$negCount = \App\Models\EbayCustomerRecord::where(fn($q) => $q->whereNull('created_by')->orWhere('created_by', 0)->orWhereIn('created_by', $ebayUserIds)->orWhereHas('handlerHistory', fn($h) => $h->whereIn('user_id', $ebayUserIds)))->where('tab_type', \App\Models\EbayCustomerRecord::TAB_NEGATIVES)->whereBetween('created_at', [$since, $until])->count();

echo "Total Customer Count: $count\n";
echo "Negative Feedback Count: $negCount\n";
