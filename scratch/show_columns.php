<?php
$tables = ['call_requests', 'call_reports'];
foreach ($tables as $t) {
    echo "Columns for table: $t\n";
    $columns = DB::select("PRAGMA table_info(`$t`)");
    foreach ($columns as $c) {
        echo "  - {$c->name} ({$c->type})\n";
    }
    echo "\n";
}
