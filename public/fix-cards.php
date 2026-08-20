<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$approvedLists = \App\Models\BoardList::where('name', 'like', '%Approved%')->pluck('id');
if ($approvedLists->isEmpty()) {
    echo "No Approved lists found.\n";
    exit;
}

$cardsInApproved = \App\Models\Card::whereIn('board_list_id', $approvedLists)->get();
$fixedCount = 0;

foreach ($cardsInApproved as $card) {
    if ($card->status->value !== 'approved') {
        $card->update(['status' => 'approved']);
    }
    if ($card->sync_group_id) {
        $twins = \App\Models\Card::where('sync_group_id', $card->sync_group_id)->where('id', '!=', $card->id)->get();
        foreach ($twins as $twin) {
            if ($twin->status->value !== 'approved') {
                $twin->update(['status' => 'approved']);
                $fixedCount++;
            }
        }
    }
}
echo "Fixed {$fixedCount} twin cards.\n";
