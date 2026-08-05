import os

php_script = """
<?php
\\Illuminate\\Support\\Facades\\DB::transaction(function () {
    $cards = \\App\\Models\\Card::whereNotNull('sync_group_id')->get();
    $grouped = $cards->groupBy('sync_group_id');
    
    foreach ($grouped as $syncGroupId => $syncCards) {
        $masterCard = $syncCards->first(function($card) {
            return stripos($card->board->name, 'SMM') !== false;
        });
        
        if (!$masterCard) {
            $masterCard = $syncCards->first();
        }
        
        if (!$masterCard || !$masterCard->boardList) continue;
        
        $masterListName = trim($masterCard->boardList->name);
        
        foreach ($syncCards as $card) {
            if ($card->id === $masterCard->id) continue;
            
            $currentListName = trim($card->boardList->name ?? '');
            
            if (strcasecmp($currentListName, $masterListName) !== 0) {
                $targetList = $card->board->lists->first(function($list) use ($masterListName) {
                    $lName = trim($list->name);
                    $oName = $masterListName;
                    if (strcasecmp($lName, $oName) === 0) return true;
                    if (preg_match('/^Week\s+\d+/i', $lName, $lMatch) && preg_match('/^Week\s+\d+/i', $oName, $oMatch)) {
                        return strcasecmp($lMatch[0], $oMatch[0]) === 0;
                    }
                    return false;
                });
                
                if ($targetList && $card->board_list_id !== $targetList->id) {
                    echo "Moving card '{$card->title}' from '{$currentListName}' to '{$targetList->name}' in board '{$card->board->name}'\\n";
                    \\App\\Models\\Card::withoutEvents(function() use ($card, $targetList) {
                        $card->board_list_id = $targetList->id;
                        $card->save();
                    });
                }
            }
        }
    }
});
echo "Done moving cards!\\n";
"""

with open("temp_fix_cards.php", "w") as f:
    f.write(php_script)

print("Uploading script to server...")
os.system("rsync -avz -e 'ssh -o StrictHostKeyChecking=no -p 65002' temp_fix_cards.php u355625773@157.173.215.124:domains/lightcyan-weasel-711536.hostingersite.com/public_html/")

print("Executing script on server...")
os.system("ssh -o StrictHostKeyChecking=no -p 65002 u355625773@157.173.215.124 'cd domains/lightcyan-weasel-711536.hostingersite.com/public_html && /opt/alt/php84/usr/bin/php artisan tinker temp_fix_cards.php && rm temp_fix_cards.php'")

os.remove("temp_fix_cards.php")
print("Finished.")
