<?php
$tables = ['call_requests', 'leads', 'ebay_customer_records', 'customers', 'shipment_customers'];
foreach ($tables as $t) {
    $rows = DB::select("select sql from sqlite_master where type='table' and name=?", [$t]);
    echo "Table: $t\n";
    if ($rows) {
        echo $rows[0]->sql . "\n";
    }
    $indexRows = DB::select("select name, sql from sqlite_master where type='index' and tbl_name=?", [$t]);
    echo "Indexes:\n";
    foreach ($indexRows as $ir) {
        echo "  - " . $ir->name . ": " . $ir->sql . "\n";
    }
    echo "\n";
}
