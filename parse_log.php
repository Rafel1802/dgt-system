<?php
$log = file_get_contents('/Applications/XAMPP/xamppfiles/htdocs/dgt-system/storage/logs/laravel.log');
preg_match_all('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] production\.ERROR:.*?\{/s', $log, $matches);
if (!empty($matches[0])) {
    echo "LAST 3 ERRORS:\n";
    $recent = array_slice($matches[0], -3);
    foreach ($recent as $err) {
        // Output up to 300 chars of each error
        echo substr($err, 0, 300) . "\n-------------------\n";
    }
} else {
    echo "No errors found.";
}
