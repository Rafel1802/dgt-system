<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MachineReturn;
use App\Models\EbayCustomerRecord;

$returns = MachineReturn::where('status', 'received')->with('customer')->get();

$count = 0;
foreach ($returns as $return) {
    if (!$return->customer) continue;

    $ebayRecords = $return->customer->ebayCustomerRecords;
    foreach ($ebayRecords as $record) {
        if ($record->tab_type !== EbayCustomerRecord::TAB_RETURN_RECEIVED) {
            $record->updateQuietly(['tab_type' => EbayCustomerRecord::TAB_RETURN_RECEIVED]);
            $count++;
            echo "Updated eBay record {$record->id} for customer {$return->customer->name}\n";
        }
    }
}

echo "Done! Updated {$count} eBay records.\n";
