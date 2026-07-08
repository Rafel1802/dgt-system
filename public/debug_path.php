<?php
header('Content-Type: text/plain');
echo "Current directory: " . getcwd() . "\n\n";

echo "Listing current directory:\n";
foreach (glob('*') as $file) {
    echo "- " . $file . (is_dir($file) ? '/' : '') . "\n";
}

echo "\nListing parent directory:\n";
foreach (glob('../*') as $file) {
    echo "- " . $file . (is_dir($file) ? '/' : '') . "\n";
}

echo "\nListing public/js/ directory:\n";
foreach (glob('js/*.js') as $file) {
    echo "- " . $file . " (size: " . filesize($file) . ", modified: " . date('Y-m-d H:i:s', filemtime($file)) . ")\n";
}

echo "\nChecking if public/js/trello-board.js contains our changes:\n";
$file = 'js/trello-board.js';
if (file_exists($file)) {
    $content = file_get_contents($file);
    if (strpos($content, 'ENABLE_BOARD_REALTIME_SYNC') !== false) {
        echo "YES, contains ENABLE_BOARD_REALTIME_SYNC\n";
    } else {
        echo "NO, does not contain ENABLE_BOARD_REALTIME_SYNC\n";
    }
} else {
    echo "File not found\n";
}
