<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$prefix = 'Planning board';
$typeKeyword = 'Planning';
$workspace_id = \App\Models\Workspace::where('name', 'like', '%Graphic%')->first()->id;

$templateBoard = \App\Models\Board::where('workspace_id', $workspace_id)
    ->where(function($q) use ($prefix, $typeKeyword) {
        $q->where('name', 'like', "%{$prefix}%")
          ->orWhere(function($q2) use ($typeKeyword) {
              $q2->where('is_template', true)
                 ->where('name', 'like', "%{$typeKeyword}%");
          });
    })
    ->orderBy('is_template', 'desc') // Prefer explicit templates
    ->orderBy('created_at', 'desc')
    ->first();

echo "Matched: " . ($templateBoard ? $templateBoard->name : 'None') . "\n";

$boards = \App\Models\Board::where('workspace_id', $workspace_id)->get();
foreach($boards as $b) {
    echo $b->id . " - " . $b->name . " - is_template: " . $b->is_template . "\n";
}
