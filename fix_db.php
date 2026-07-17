<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$db = config('database.connections.mysql.database');
echo "Checking database: $db\n";

try {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
    
    // Get all tables
    $tablesStmt = $pdo->query("SHOW TABLES");
    $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        // Check if table has 'id' column
        $colsStmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE 'id'");
        $idCol = $colsStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($idCol) {
            // Check if it is already auto_increment
            if (str_contains($idCol['Extra'], 'auto_increment')) {
                echo "Table `$table`: id is already AUTO_INCREMENT\n";
            } else {
                echo "Table `$table`: id is NOT auto_increment. Attempting to fix...\n";
                try {
                    // We need to modify the column to be auto_increment.
                    // First check column type (usually bigint unsigned)
                    $type = $idCol['Type'];
                    $pdo->exec("ALTER TABLE `$table` MODIFY `id` $type AUTO_INCREMENT");
                    echo "✅ Fixed table `$table`\n";
                } catch (\Exception $e) {
                    echo "❌ Failed to fix table `$table`: " . $e->getMessage() . "\n";
                }
            }
        }
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
