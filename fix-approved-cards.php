<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Card;
use App\Models\BoardList;

$approvedLists = BoardList::where('name', 'like', '%Approved%')->pluck('id');

if ($approvedLists->isEmpty()) {
    echo "No Approved lists found.\n";
    exit;
}

$cardsInApproved = Card::whereIn('board_list_id', $approvedLists)->get();
$fixedCount = 0;

foreach ($cardsInApproved as $card) {
    // Make sure the workflow card itself is approved
    if ($card->status !== \App\Enums\CardStatus::Approved) {
        $card->update(['status' => 'approved']);
    }

    if ($card->sync_group_id) {
        $twins = Card::where('sync_group_id', $card->sync_group_id)->where('id', '!=', $card->id)->get();
        foreach ($twins as $twin) {
            if ($twin->status !== \App\Enums\CardStatus::Approved) {
                $twin->update(['status' => 'approved']);
                echo "Fixed twin card ID {$twin->id} (sync group {$card->sync_group_id})\n";
                $fixedCount++;
            }
        }
    }
}

echo "Fixed {$fixedCount} cards.\n";
