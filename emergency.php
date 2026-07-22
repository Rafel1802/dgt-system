<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Emergency Fix Tool</h1>";

$baseDir = __DIR__;

// 1. Delete Bootstrap Cache
$bootstrapCacheDir = $baseDir . '/bootstrap/cache';
echo "<h3>Clearing Bootstrap Cache...</h3>";
if (is_dir($bootstrapCacheDir)) {
    $files = glob($bootstrapCacheDir . '/*.php');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
            echo "Deleted: " . basename($file) . "<br>";
        }
    }
} else {
    echo "Directory not found: $bootstrapCacheDir<br>";
}

// 2. Delete Views Cache
$viewsCacheDir = $baseDir . '/storage/framework/views';
echo "<h3>Clearing Views Cache...</h3>";
if (is_dir($viewsCacheDir)) {
    $files = glob($viewsCacheDir . '/*.php');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    echo "Views cache cleared.<br>";
}

// 3. Delete Data Cache
$dataCacheDir = $baseDir . '/storage/framework/cache/data';
echo "<h3>Clearing Data Cache...</h3>";
function deleteDirectoryContents($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? deleteDirectoryContents($path) : unlink($path);
        if (is_dir($path)) rmdir($path);
    }
}
deleteDirectoryContents($dataCacheDir);
echo "Data cache cleared.<br>";

// 4. Run Artisan Optimize
echo "<h3>Running Artisan Optimize...</h3>";
require $baseDir.'/vendor/autoload.php';
$app = require_once $baseDir.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

try {
    $status = $kernel->call('optimize');
    echo "Optimization complete! Exit code: " . $status . "<br>";
} catch (Exception $e) {
    echo "Optimization failed: " . $e->getMessage() . "<br>";
}

echo "<h2>All done! Please delete this file for security.</h2>";
