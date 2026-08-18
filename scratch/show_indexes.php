<?php
$tables = ['leads', 'ebay_customer_records', 'customers', 'shipments', 'shipment_customers', 'tech_support_cases', 'call_reports', 'call_requests'];

foreach ($tables as $table) {
    echo "Indexes for table: $table\n";
    $indexes = DB::select("PRAGMA index_list(`$table`)");
    foreach ($indexes as $index) {
        $info = DB::select("PRAGMA index_info(`{$index->name}`)");
        $cols = array_map(fn($c) => $c->name, $info);
        echo "  - {$index->name} (" . implode(', ', $cols) . ") unique=" . $index->unique . "\n";
    }
    echo "\n";
}
