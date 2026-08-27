import os
import sys
import subprocess

def run_cmd(cmd_args):
    print(f"Running: {' '.join(cmd_args)}")
    askpass_path = os.path.abspath("askpass.sh")
    env = os.environ.copy()
    env["SSH_ASKPASS"] = askpass_path
    env["DISPLAY"] = "dummy"
    env["SSH_ASKPASS_REQUIRE"] = "force"
    
    proc = subprocess.run(cmd_args, env=env)
    if proc.returncode != 0:
        print(f"Command failed with exit code {proc.returncode}")
        sys.exit(1)
    print("\n--- Command Finished ---\n")

tinker_command = """
$b = App\\Models\\Board::find(75);
echo "ID 75 Name: '" . $b->name . "'\\n";
// Let's replace regardless of spaces
$b->name = preg_replace('/July\\s*2026/i', 'August 2026', $b->name);
$b->save();
echo "ID 75 After: '" . $b->name . "'\\n";

$card = App\\Models\\Card::where('title', 'like', '%TYPH-0507 / TYPH-5005M%')->first();
if ($card) {
    echo "Found Card ID: " . $card->id . " Title: " . $card->title . "\\n";
    echo "Sync Group ID: " . $card->sync_group_id . "\\n";
    $siblings = App\\Models\\Card::where('sync_group_id', $card->sync_group_id)->get();
    echo "Siblings count: " . $siblings->count() . "\\n";
    foreach($siblings as $sib) {
        $list = $sib->boardList;
        echo " - Sibling ID: " . $sib->id . " Board: " . ($sib->board->name ?? 'none') . " List: " . ($list->name ?? 'none') . "\\n";
    }
} else {
    echo "Card not found\\n";
}
"""

cmd = [
    "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002",
    "u355625773@157.173.215.124",
    f"cd domains/lightcyan-weasel-711536.hostingersite.com/public_html && /opt/alt/php84/usr/bin/php artisan tinker --execute=\"{tinker_command}\""
]

run_cmd(cmd)
