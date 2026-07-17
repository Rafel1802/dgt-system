<?php
header('Content-Type: text/plain');

$publicStorage = __DIR__ . '/public/storage';
$realStorage = __DIR__ . '/storage/app/public';

echo "Public storage path: $publicStorage\n";
echo "Real storage path: $realStorage\n";

if (is_link($publicStorage)) {
    echo "✅ public/storage is a symlink.\n";
    echo "Symlink target: " . readlink($publicStorage) . "\n";
} else {
    echo "❌ public/storage is NOT a symlink! (It might be a directory or not exist)\n";
    if (file_exists($publicStorage)) {
        echo "It exists as a: " . filetype($publicStorage) . "\n";
    } else {
        echo "It does not exist.\n";
    }
}

// Find any files in real storage
echo "\nFiles in storage/app/public/website-error-references/:\n";
$dir = $realStorage . '/website-error-references';
if (is_dir($dir)) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            echo " - $file (" . filesize($dir . '/' . $file) . " bytes)\n";
        }
    }
} else {
    echo "Directory does not exist!\n";
}

// Check if we can read the file directly through php
echo "\nTesting file access through php:\n";
$testFile = $dir . '/bg-dark.png';
if (file_exists($testFile)) {
    echo "✅ bg-dark.png exists in storage/app/public/website-error-references/\n";
} else {
    echo "❌ bg-dark.png does NOT exist in storage/app/public/website-error-references/\n";
}
