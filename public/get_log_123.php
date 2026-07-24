<?php
header('Content-Type: text/plain');
$log = __DIR__ . '/../storage/logs/laravel.log';
if (file_exists($log)) {
    // Read the last 20000 bytes
    $fp = fopen($log, 'r');
    fseek($fp, -20000, SEEK_END);
    echo fread($fp, 20000);
    fclose($fp);
} else {
    echo "Log file not found.";
}
unlink(__FILE__); // self delete
