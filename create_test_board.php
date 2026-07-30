<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Workspace;
use Illuminate\Support\Str;

$workspace = Workspace::firstOrCreate(['name' => 'Graphic Team']);
$board = Board::create([
    'name' => 'Test SMM Board',
    'workspace_id' => $workspace->id,
    'description' => 'Test SMM Board created automatically',
    'type' => 'smm',
    'is_active_smm' => true,
    'slug' => Str::slug('Test SMM Board') . '-' . Str::random(4),
    'created_by' => 1,
    'background_type' => 'color',
    'background_value' => '#6366f1'
]);
$defaultLists = ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Final Captions'];
foreach ($defaultLists as $index => $listName) {
    BoardList::create([
        'board_id' => $board->id,
        'name' => $listName,
        'position' => ($index + 1) * 1000,
    ]);
}
echo "SMM Board 'Test SMM Board' created successfully with default lists.\n";
