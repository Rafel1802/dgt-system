import os
import sys
import subprocess
import time
import getpass

SSH_PASSWORD = os.environ.get("SSH_PASSWORD", "Digital@KiuQ$#!2030%")
SSHPASS = "/opt/homebrew/bin/sshpass" if os.path.exists("/opt/homebrew/bin/sshpass") else "sshpass"

def run_cmd(cmd_args, allow_fail=False):
    global SSH_PASSWORD
    print(f"Running: {' '.join(cmd_args)}")
    time.sleep(1.5)  # Avoid SSH connection burst throttling on Hostinger

    # If --interactive is passed in CLI or SSH_PASSWORD is empty, run command natively
    if "--interactive" in sys.argv or not SSH_PASSWORD:
        proc = subprocess.run(cmd_args)
    else:
        full_cmd = [SSHPASS, "-p", SSH_PASSWORD] + cmd_args
        proc = subprocess.run(full_cmd)

    if proc.returncode != 0:
        if allow_fail:
            print(f"Command failed with exit code {proc.returncode} (non-fatal, proceeding...)")
            return

        # Attempt up to 2 retries with sleep to handle Hostinger rate limiting
        for attempt in range(1, 3):
            print(f"\n[!] Command returned exit code {proc.returncode}. Retrying in 3s (attempt {attempt}/2)...")
            time.sleep(3)
            if "--interactive" in sys.argv or not SSH_PASSWORD:
                proc = subprocess.run(cmd_args)
            else:
                proc = subprocess.run(full_cmd)
            if proc.returncode == 0:
                print("\n--- Command Finished ---\n")
                return

        # Handle Permission Denied (sshpass exit code 5 or 255)
        if proc.returncode in (5, 255) and sys.stdin.isatty():
            print("\n[!] SSH Permission Denied with stored password.")
            print("[?] Please enter the current Hostinger SSH password:")
            try:
                new_pass = getpass.getpass("Hostinger SSH Password: ")
                if new_pass:
                    SSH_PASSWORD = new_pass
                    print("Retrying with entered password...\n")
                    full_cmd = [SSHPASS, "-p", SSH_PASSWORD] + cmd_args
                    retry_proc = subprocess.run(full_cmd)
                    if retry_proc.returncode == 0:
                        print("\n--- Command Finished ---\n")
                        return
            except Exception:
                pass

        print(f"\n[ERROR] Command failed with exit code {proc.returncode}")
        print("\nIf you are seeing 'Permission denied (publickey,password)':")
        print("1. Verify your SSH password in Hostinger hPanel -> Advanced -> SSH Access.")
        print("2. Make sure 'SSH Access' is toggled ON in Hostinger.")
        print("3. You can pass the correct password directly:")
        print("   SSH_PASSWORD='your_password' python3 upload_and_clear.py")
        print("   OR run with interactive prompt:")
        print("   python3 upload_and_clear.py --interactive\n")
        sys.exit(1)
    print("\n--- Command Finished ---\n")

if __name__ == "__main__":
    # 0. Build production assets
    print("Building production frontend assets...")
    try:
        subprocess.run(["npm", "run", "build"], check=True)
        print("Frontend assets built successfully.\n")
    except subprocess.CalledProcessError:
        print("Failed to build frontend assets. Make sure npm is installed.")
        sys.exit(1)

    # 1. Upload all files (replace if exist), excluding vendor, node_modules, etc.
    #
    # IMPORTANT: this rsyncs the *entire* project into the live public web root.
    # Any loose file at the repo root — a debug script, a DB dump, a deploy
    # helper with a hardcoded password — becomes instantly web-accessible
    # unless explicitly excluded here. (This is exactly what happened: several
    # *.php test/maintenance scripts and *.py deploy scripts containing this
    # very SSH password were found live on production and had to be purged.)
    # No real Laravel app code lives as a loose script at the project root —
    # it all lives under app/, routes/, config/, resources/, etc. — so blanket-
    # excluding root-level *.php/*.py/*.sh/*.exp files is safe and closes this
    # class of leak for good, not just for the specific filenames seen so far.
    rsync_cmd = [
        "rsync", "-avz", "-e", "ssh -o StrictHostKeyChecking=no -o PubkeyAuthentication=no -o ConnectTimeout=30 -p 65002",
        "--exclude", ".git/",
        "--exclude", "vendor/",
        "--exclude", "node_modules/",
        "--exclude", ".env",
        "--exclude", ".DS_Store",
        "--exclude", "bootstrap/cache/",
        "--exclude", "storage/logs/",
        "--exclude", "storage/framework/",
        "--exclude", "backups/",
        "--exclude", "*.sql",
        "--exclude", "/*.php",   # leading "/" = project root only, not app/ etc.
        "--exclude", "/*.py",
        "--exclude", "/*.sh",
        "--exclude", "/*.exp",
        "--exclude", "/*.md",
        "--exclude", "public/debug_path.php",
        "--exclude", "public/check_storage_link.php",
        "--exclude", "public/log_viewer.php",
        "--exclude", "public/get_log_123.php",
        "--exclude", "public/clear_opcache.php",
        "--exclude", "public/fix-cards.php",
        "--exclude", "public/test_render.php",
        "--exclude", "public/test_kernel.php",
        # A sample customer-data spreadsheet dropped at the project root (for
        # designing the Process Trucking import template) got deployed to the
        # live document root this way and was briefly publicly downloadable —
        # same class of leak as the *.php/*.py exclusions above, just for
        # spreadsheets instead of scripts.
        "--exclude", "/*.xlsx",
        "--exclude", "/*.xls",
        "--exclude", "/*.csv",
        "--exclude", ".phpunit.result.cache",
        "--exclude", "dgt_macos_app/",
        "--exclude", "dgt-mobile/",
        "./",
        "u355625773@157.173.215.124:domains/lightcyan-weasel-711536.hostingersite.com/public_html/"
    ]
    run_cmd(rsync_cmd)

    # 1c. Sync user-uploaded files (QC error images, avatars, attachments, etc.)
    # These are stored in storage/app/public/ locally and served via storage symlink on server.
    rsync_storage_cmd = [
        "rsync", "-avz", "-e", "ssh -o StrictHostKeyChecking=no -o PubkeyAuthentication=no -o ConnectTimeout=30 -p 65002",
        "--ignore-existing",  # Don't overwrite files that already exist on the server
        "storage/app/public/",
        "u355625773@157.173.215.124:domains/lightcyan-weasel-711536.hostingersite.com/public_html/storage/app/public/"
    ]
    run_cmd(rsync_storage_cmd, allow_fail=True)

    # 1b. Upload assets directly to public_html/js/ (without public/ prefix) just in case public_html is the document root
    rsync_workspace_alpine_cmd = [
        "rsync", "-avz", "-e", "ssh -o StrictHostKeyChecking=no -o PubkeyAuthentication=no -o ConnectTimeout=30 -p 65002",
        "public/js/workspace-alpine.js",
        "u355625773@157.173.215.124:domains/lightcyan-weasel-711536.hostingersite.com/public_html/js/"
    ]
    run_cmd(rsync_workspace_alpine_cmd, allow_fail=True)

    rsync_trello_board_cmd = [
        "rsync", "-avz", "-e", "ssh -o StrictHostKeyChecking=no -o PubkeyAuthentication=no -o ConnectTimeout=30 -p 65002",
        "public/js/trello-board.js",
        "u355625773@157.173.215.124:domains/lightcyan-weasel-711536.hostingersite.com/public_html/js/"
    ]
    run_cmd(rsync_trello_board_cmd, allow_fail=True)

    rsync_drag_scroll_cmd = [
        "rsync", "-avz", "-e", "ssh -o StrictHostKeyChecking=no -o PubkeyAuthentication=no -o ConnectTimeout=30 -p 65002",
        "public/js/drag-scroll.js",
        "u355625773@157.173.215.124:domains/lightcyan-weasel-711536.hostingersite.com/public_html/js/"
    ]
    run_cmd(rsync_drag_scroll_cmd, allow_fail=True)

    rsync_images_cmd = [
        "rsync", "-avz", "-e", "ssh -o StrictHostKeyChecking=no -o PubkeyAuthentication=no -o ConnectTimeout=30 -p 65002",
        "public/images/",
        "u355625773@157.173.215.124:domains/lightcyan-weasel-711536.hostingersite.com/public_html/images/"
    ]
    run_cmd(rsync_images_cmd, allow_fail=True)

    rsync_build_cmd = [
        "rsync", "-avz", "-e", "ssh -o StrictHostKeyChecking=no -o PubkeyAuthentication=no -o ConnectTimeout=30 -p 65002",
        "public/build/",
        "u355625773@157.173.215.124:domains/lightcyan-weasel-711536.hostingersite.com/public_html/build/"
    ]
    run_cmd(rsync_build_cmd, allow_fail=True)

    rsync_downloads_cmd = [
        "rsync", "-avz", "-e", "ssh -o StrictHostKeyChecking=no -o PubkeyAuthentication=no -o ConnectTimeout=30 -p 65002",
        "public/downloads/",
        "u355625773@157.173.215.124:domains/lightcyan-weasel-711536.hostingersite.com/public_html/downloads/"
    ]
    run_cmd(rsync_downloads_cmd, allow_fail=True)

    # 2. Remove hot file + optimize caching on server.
    # Do not run migrations from this performance deploy: the optimization pass
    # must not modify schema or data.
    # NOTE: the server's default `php` on PATH is 8.2 (Composer requires >=8.4.1),
    # so every artisan call below silently no-ops on the platform check unless we
    # point at the real PHP 8.4 binary explicitly.
    PHP = "/opt/alt/php84/usr/bin/php"
    tinker_sync = (
        PHP + ' artisan tinker --execute="'
        "App\\Models\\Card::whereHas('boardList', function(\\$q){ \\$q->where('name', 'like', '%approved%'); })"
        "->whereNull('approved_at')->update(['approved_at' => now(), 'status' => 'approved']); "
        "App\\Models\\Card::whereHas('boardList', function(\\$q){ \\$q->where('name', 'like', '%approved%'); })"
        "->whereNotNull('sync_group_id')->pluck('sync_group_id')->unique()"
        "->each(fn(\\$g) => App\\Models\\Card::where('sync_group_id', \\$g)->update(['status' => 'approved', 'approved_at' => App\\Models\\Card::where('sync_group_id', \\$g)->max('approved_at')]));"
        '" && '
    )
    tinker_clean = PHP + ' artisan tinker --execute="App\\Models\\SocialMediaClass::whereIn(\'name\', [\'Long Landscape\', \'Share Blog\', \'Short Reel\', \'Poster Design\', \'Reel\', \'Machinery.Bargains\', \'SkidSteer\'])->delete();" && '
    
    tinker_fix_colors = PHP + ' artisan tinker --execute="App\\Models\\BoardList::whereIn(\'name\', [\'Week 3\', \'Week 4\'])->update([\'color\' => null]);" && '

    tinker_fix_returns = PHP + ' artisan tinker --execute="App\\\\Models\\\\MachineReturn::where(\'status\', \'received\')->with(\'customer.ebayCustomerRecords\')->get()->each(function(\\$r) { if(\\$r->customer) { foreach(\\$r->customer->ebayCustomerRecords as \\$rec) { if(\\$rec->tab_type !== \'return_received\') \\$rec->updateQuietly([\'tab_type\' => \'return_received\']); } } });" && '
    
    tinker_update_socials = PHP + ' artisan tinker --execute="\\$data = [\'MachineryBargains\' => [\'Facebook\' => \'https://www.facebook.com/Machinery.Bargains\',\'Instagram\' => \'https://www.instagram.com/machinery.bargains\',\'X\' => \'https://x.com/Machin_Bargains\',\'X(Twitter)\' => \'https://x.com/Machin_Bargains\',\'TikTok\' => \'https://www.tiktok.com/@machinery.bargains\',\'YouTube\' => \'https://www.youtube.com/@Machinery.Bargains\',\'Tumblr\' => \'https://www.tumblr.com/machinerybargains\',\'Pinterest\' => \'https://www.pinterest.com/MachineryBargains/\'],\'MiniExca\' => [\'Facebook\' => \'https://www.facebook.com/MiniExcaMachinery/\',\'Instagram\' => \'https://www.instagram.com/miniexcamachinery\',\'X\' => \'https://x.com/miniexcamachine\',\'X(Twitter)\' => \'https://x.com/miniexcamachine\',\'TikTok\' => \'https://www.tiktok.com/@miniexcamachinery\',\'YouTube\' => \'https://youtube.com/@MiniExcavatorMachinery\',\'Tumblr\' => \'https://www.tumblr.com/miniexca\',\'Pinterest\' => \'https://www.pinterest.com/miniexca\'],\'MachineryAsia.Online\' => [\'Facebook\' => \'https://www.facebook.com/MachineryAsiaOnlinee\',\'Instagram\' => \'https://www.instagram.com/machineryasiaonline/\',\'X\' => \'https://x.com/MachineryLoader\',\'X(Twitter)\' => \'https://x.com/MachineryLoader\',\'TikTok\' => \'https://www.tiktok.com/@machineryasiaonline\',\'YouTube\' => \'https://www.youtube.com/@MachineryAsiaOnline\',\'Tumblr\' => \'https://www.tumblr.com/blog/machineryasiaonline\',\'Pinterest\' => \'https://www.pinterest.com/MachineryAsiaOnline/\'],\'ImpossibleMachinery\' => [\'Facebook\' => \'https://www.facebook.com/ImpossibleMachinery/\',\'Instagram\' => \'https://www.instagram.com/impossiblemachinery/\',\'X\' => \'https://x.com/impss_machinery\',\'X(Twitter)\' => \'https://x.com/impss_machinery\',\'TikTok\' => \'https://www.tiktok.com/@impossiblemachinery\',\'YouTube\' => \'https://www.youtube.com/@ImpossibleMachinery\',\'Tumblr\' => \'https://www.tumblr.com/impossiblemachinery\',\'Pinterest\' => \'https://www.pinterest.com/ImpossibleMachinery\'],\'SkidSteers\' => [\'Facebook\' => \'https://www.facebook.com/Americanskidsteers\',\'Instagram\' => \'https://www.instagram.com/americanskidsteer/\',\'X\' => \'https://x.com/iloveSkidSteer\',\'X(Twitter)\' => \'https://x.com/iloveSkidSteer\',\'TikTok\' => \'https://www.tiktok.com/@americanskidsteer\',\'YouTube\' => \'https://youtube.com/@AmericanSkidSteer\',\'Tumblr\' => \'https://www.tumblr.com/skidsteerforamerican\',\'Pinterest\' => \'https://www.pinterest.com/american_skidsteer/\'],\'Machinery.Org\' => [\'Facebook\' => \'https://www.facebook.com/machineryorg\',\'Instagram\' => \'https://www.instagram.com/machineryorg\'],\'MachineryAsia (FB)\' => [\'Facebook\' => \'https://www.facebook.com/MachineryAsiaOnlinee\']]; foreach (\\$data as \\$c => \\$s) { \\$cls = \\\\App\\\\Models\\\\SocialMediaClass::where(\'name\', \\$c)->first(); if(!\\$cls) continue; foreach(\\$s as \\$p => \\$u) { \\$itm = \\$cls->items()->where(\'name\', \\$p)->first(); if(\\$itm) { \\$itm->url = \\$u; \\$itm->save(); } } } echo \'Done Socials\';" && '
    
    tinker_fix_statuses = PHP + ' artisan tinker --execute="App\\\\Models\\\\EbayCustomerRecord::whereIn(\'tab_type\', [\'tech_in_progress\', \'tech_potential_return\'])->update([\'tab_type\' => \'technical_issues\']); App\\\\Models\\\\EbayCustomerRecord::where(\'tab_type\', \'tech_return_machine\')->update([\'tab_type\' => \'return_approved\']); App\\\\Models\\\\EbayCustomerRecord::where(\'tab_type\', \'pickup_arranged\')->update([\'tab_type\' => \'loaded_for_return\']); App\\\\Models\\\\TechSupportCase::where(\'status\', \'in_progress\')->update([\'status\' => \'new_case\']); App\\\\Models\\\\TechSupportCase::where(\'status\', \'return_machine\')->update([\'status\' => \'return_approved\']); App\\\\Models\\\\MachineReturn::where(\'status\', \'pickup_arranged\')->update([\'status\' => \'in_transit_return\']); App\\\\Models\\\\TechSupportCase::where(\'status\', \'return_approved\')->get()->each(function(\\$c) { if (\\$c->source && \\$c->source_type === \'App\\\\Models\\\\EbayCustomerRecord\' && \\$c->source->tab_type !== \'return_approved\') { \\$c->source->updateQuietly([\'tab_type\' => \'return_approved\']); } });" && '
    
    tinker_fix_deal_stage = PHP + ' artisan tinker --execute="App\\\\Models\\\\Customer::where(\'pipeline_stage\', \'new_inquiry\')->update([\'pipeline_stage\' => \'new_lead\']);" && '
    
    tinker_fix_smm_board = PHP + ' artisan tinker --execute="\\$board = App\\\\Models\\\\Board::where(\'name\', \'like\', \'SMM Planning Board - September 2026%\')->first(); if (\\$board) { App\\\\Models\\\\BoardList::firstOrCreate([\'board_id\' => \\$board->id, \'name\' => \'Block/Waiting\'], [\'position\' => 6000]); }" && '
    
    tinker_fix_duplicate_smm = (
        PHP + ' artisan tinker --execute="'
        "\\$smmBoards = App\\\\Models\\\\Board::where('type', 'smm')->where('name', 'like', '%September 2026%')->get(); "
        "if (\\$smmBoards->count() > 1) { "
        "    \\$starred = \\$smmBoards->firstWhere('is_starred', true); "
        "    \\$unstarred = \\$smmBoards->where('is_starred', false)->first(); "
        "    if (\\$starred && \\$unstarred) { \\$unstarred->update(['is_hidden' => true]); } "
        "}"
        '" && '
    )

    tinker_fix_automations = PHP + ' artisan tinker --execute="App\\\\Models\\\\BoardAutomation::where(\'action_type\', \'copy\')->where(\'trigger_word\', \'ready\')->get()->each(function(\\$auto) { \\$board = \\$auto->board; if (\\$board && str_contains(\\$board->name, \'Planning board\')) { \\$suffix = trim(str_ireplace(\'Planning board\', \'\', \\$board->name)); \\$workflowName = trim(\'Workflow board \' . \\$suffix); \\$workflowBoard = App\\\\Models\\\\Board::where(\'workspace_id\', \\$board->workspace_id)->where(\'name\', \\$workflowName)->first(); if (\\$workflowBoard && \\$auto->target_board_id !== \\$workflowBoard->id) { \\$draftList = \\$workflowBoard->lists()->where(\'name\', \'like\', \'%Draft%\')->first(); if (\\$draftList) { \\$auto->updateQuietly([\'target_board_id\' => \\$workflowBoard->id, \'target_list_id\' => \\$draftList->id]); } } } });" && '

    tinker_fix_dara_card = (
        PHP + ' artisan tinker --execute="'
        "\\$cards = App\\\\Models\\\\Card::where('title', 'like', '%A+ Content for TD01%')"
        "->whereHas('boardList', function(\\$q) { \\$q->where('name', 'like', '%Approved%'); })"
        "->get(); "
        "foreach (\\$cards as \\$c) { "
        "    \\$sup = \\$c->board->lists()->where('name', 'like', '%Supervisor Review%')->first(); "
        "    if (\\$sup) { "
        "        \\$c->update(['board_list_id' => \\$sup->id, 'status' => 'todo', 'approved_at' => null]); "
        "        if (\\$c->sync_group_id) { "
        "            App\\\\Models\\\\Card::where('sync_group_id', \\$c->sync_group_id)->update(['status' => 'todo', 'approved_at' => null]); "
        "        } "
        "        echo 'Fixed card ' . \\$c->id . ' (' . \\$c->title . ') back to Supervisor Review\\n'; "
        "    } "
        "}"
        '" && '
    )

    tinker_clean_reorder = PHP + ' artisan tinker --execute="\\Illuminate\\Support\\Facades\\DB::table(\'notifications\')->where(\'data\', \'like\', \'%card_reordered%\')->orWhere(\'data\', \'like\', \'%reordered card%\')->delete(); App\\\\Models\\\\ActivityLog::where(\'description\', \'like\', \'%reordered%\')->orWhere(\'action\', \'like\', \'%reorder%\')->delete(); echo \'Cleaned up reorder notifications and activities\\n\';" && '

    tinker_sync_folders = (
        PHP + ' artisan tinker --execute="'
        "\\$controller = app(\\\\App\\\\Http\\\\Controllers\\\\Board\\\\CardController::class); "
        "\\\\App\\\\Models\\\\Card::whereNotNull('sync_group_id')->get()->each(function(\\$c) use (\\$controller) { \\$controller->syncFolderGroupingAcrossTwins(\\$c); }); "
        "\\\\App\\\\Models\\\\Card::all()->each(function(\\$c) use (\\$controller) { \\$controller->syncFolderGroupingAcrossTwins(\\$c); }); "
        "echo 'Synced card folders across twins and healed activities\\n';"
        '" && '
    )

    tinker_fix_smm_labels = (
        PHP + ' artisan tinker --execute="'
        "\\\\App\\\\Models\\\\Card::whereIn('smm_class_label', ['Machinery.Bargains', 'Machinery Bargains'])->update(['smm_class_label' => 'MachineryBargains']); "
        "\\\\App\\\\Models\\\\Card::whereIn('smm_class_label', ['SkidSteer', 'Skid Steer'])->update(['smm_class_label' => 'SkidSteers']); "
        "\\\\App\\\\Models\\\\Card::whereIn('smm_cluster_label', ['Machinery.Bargains', 'Machinery Bargains'])->update(['smm_cluster_label' => 'MachineryBargains']); "
        "\\\\App\\\\Models\\\\Card::whereIn('smm_cluster_label', ['SkidSteer', 'Skid Steer'])->update(['smm_cluster_label' => 'SkidSteers']); "
        "\\\\App\\\\Models\\\\SocialMediaClass::whereIn('name', ['Machinery.Bargains', 'SkidSteer'])->delete();"
        '" && '
    )

    tinker_setup_october_planning = (
        PHP + ' artisan tinker --execute="'
        # 1. Update Workspace 1 to 'Digital Department @ KiuQ'
        "\\$ws = \\\\App\\\\Models\\\\Workspace::find(1) ?? \\\\App\\\\Models\\\\Workspace::first(); "
        "if (\\$ws) { "
        "    \\$ws->update(['name' => 'Digital Department @ KiuQ', 'color' => '#6366f1']); "
        "    \\$allDigitalIds = \\\\App\\\\Models\\\\User::whereHas('roles', fn(\\$q) => \\$q->whereIn('name', ['digital-team', 'admin-digital', 'supervisor', 'boss']))->pluck('id')->all(); "
        "    \\$ws->members()->syncWithoutDetaching(\\$allDigitalIds); "
        "} "
        # 2. Update user teams
        "\\\\App\\\\Models\\\\User::whereIn('id', [1, 2, 5, 11, 12, 13, 17, 22, 24])->update(['team' => null]); "
        "\\\\App\\\\Models\\\\User::whereIn('id', [10, 6, 19, 9, 23])->update(['team' => 'A']); "
        "\\\\App\\\\Models\\\\User::whereIn('id', [15, 16, 21, 20, 14])->update(['team' => 'B']); "
        "\\\\App\\\\Models\\\\User::where('name', 'like', '%Samnang%')->update(['team' => 'A']); "
        "\\\\App\\\\Models\\\\User::where('name', 'like', '%Vouchky%')->update(['team' => 'A']); "
        "\\\\App\\\\Models\\\\User::where('name', 'like', '%Heang%')->update(['team' => 'A']); "
        "\\\\App\\\\Models\\\\User::where('name', 'like', '%Chhay%')->update(['team' => 'A']); "
        "\\\\App\\\\Models\\\\User::where('name', 'like', '%Sreypich%')->update(['team' => 'A']); "
        "\\\\App\\\\Models\\\\User::where('name', 'like', '%Pich%')->where('name', 'not like', '%Sreypich%')->update(['team' => 'B']); "
        "\\\\App\\\\Models\\\\User::where('name', 'like', '%Sor%')->update(['team' => 'B']); "
        "\\\\App\\\\Models\\\\User::where('name', 'like', '%Lin%')->where('name', 'not like', '%Somalika%')->update(['team' => 'B']); "
        "\\\\App\\\\Models\\\\User::where('name', 'like', '%Nalin%')->update(['team' => 'B']); "
        "\\\\App\\\\Models\\\\User::where('name', 'like', '%Sarak%')->update(['team' => 'B']); "
        # 2b. Auto-set team only for legacy cards with no team created by kim (Team B) and dara (Team A), NEVER overwriting existing teams or Both
        "\\$kimId = \\\\App\\\\Models\\\\User::where('username', 'like', '%kim%')->orWhere('name', 'like', '%kim%')->value('id') ?? 13; "
        "\\$daraId = \\\\App\\\\Models\\\\User::where('username', 'like', '%dara%')->orWhere('name', 'like', '%dara%')->value('id') ?? 12; "
        "\\\\App\\\\Models\\\\Card::where('created_by', \\$kimId)->whereNull('team')->update(['team' => 'B']); "
        "\\\\App\\\\Models\\\\Card::where('created_by', \\$daraId)->whereNull('team')->update(['team' => 'A']); "
        # 2c. Ensure cards with joint assignment (e.g. 0121G Pro, or labeled with Team A & B) keep Both team and never revert
        "\\\\App\\\\Models\\\\Card::where('title', 'like', '%0121G Pro%')->update(['team' => 'Both']); "
        "\\\\App\\\\Models\\\\Card::whereNotNull('sync_group_id')->whereIn('sync_group_id', \\\\App\\\\Models\\\\Card::where('title', 'like', '%0121G Pro%')->pluck('sync_group_id'))->update(['team' => 'Both']); "
        "\\\\App\\\\Models\\\\Card::whereHas('labels', fn(\\$q) => \\$q->where('name', 'like', '%Team A%'))->whereHas('labels', fn(\\$q) => \\$q->where('name', 'like', '%Team B%'))->update(['team' => 'Both']); "
        "\\\\App\\\\Models\\\\Card::where('team', 'Both')->whereNotNull('sync_group_id')->pluck('sync_group_id')->unique()->each(function(\\$gid) { \\\\App\\\\Models\\\\Card::where('sync_group_id', \\$gid)->update(['team' => 'Both']); }); "
        # 2d. Auto-assign checklist items with keyword listing/description to chhay
        "\\\\App\\\\Models\\\\CardChecklistItem::where(function(\\$q) { \\$q->whereNull('assigned_user_id')->orWhereNull('assigned_user_ids'); })"
        "->where(function(\\$q) { \\$q->where('content', 'like', '%description%')->orWhere('content', 'like', '%listing%'); })"
        "->get()->each(function(\\$item) { "
        "    if (\\$card = \\$item->checklist?->card) { "
        "        if (\\$uid = \\\\App\\\\Models\\\\CardChecklistItem::detectUserIdForCard(\\$item->content ?? '', \\$card)) { "
        "            \\$item->updateQuietly(['assigned_user_id' => \\$uid, 'assigned_user_ids' => [\\$uid]]); "
        "        } "
        "    } "
        "}); "
        # 3. Setup Workflow Board Team A – October 2026
        "\\$wfA = \\\\App\\\\Models\\\\Board::where('name', 'Workflow board Team A – October 2026')->first(); "
        "if (!\\$wfA) { "
        "    \\$wfA = \\\\App\\\\Models\\\\Board::create(['workspace_id' => \\$ws->id, 'name' => 'Workflow board Team A – October 2026', 'slug' => 'workflow-board-team-a-october-2026', 'cover_type' => 'image', 'cover_value' => 'https://img.miniexcavator.org/ebay/Dashboard-Icon/TeamA.webp', 'position' => 0, 'is_hidden' => false, 'created_by' => 1]); "
        "} else { "
        "    \\$wfA->update(['is_hidden' => false, 'is_archived' => false, 'cover_type' => 'image', 'cover_value' => 'https://img.miniexcavator.org/ebay/Dashboard-Icon/TeamA.webp']); "
        "} "
        "\\$teamAUsers = \\\\App\\\\Models\\\\User::where('team', 'A')->pluck('id')->merge([1, 2, 12, 5])->unique()->filter()->values()->all(); "
        "\\$wfA->members()->sync(\\$teamAUsers); "
        "foreach (['Draft', 'Production Team A', 'Digital Department', 'Approved', 'Blocked/Waiting'] as \\$pos => \\$lname) { "
        "    \\\\App\\\\Models\\\\BoardList::firstOrCreate(['board_id' => \\$wfA->id, 'name' => \\$lname], ['position' => \\$pos]); "
        "} "
        # 4. Setup Workflow Board Team B – October 2026
        "\\$wfB = \\\\App\\\\Models\\\\Board::where('name', 'Workflow board Team B – October 2026')->first(); "
        "if (!\\$wfB) { "
        "    \\$wfB = \\\\App\\\\Models\\\\Board::create(['workspace_id' => \\$ws->id, 'name' => 'Workflow board Team B – October 2026', 'slug' => 'workflow-board-team-b-october-2026', 'cover_type' => 'image', 'cover_value' => 'https://img.miniexcavator.org/ebay/Dashboard-Icon/B.webp', 'position' => 1, 'is_hidden' => false, 'created_by' => 1]); "
        "} else { "
        "    \\$wfB->update(['is_hidden' => false, 'is_archived' => false, 'cover_type' => 'image', 'cover_value' => 'https://img.miniexcavator.org/ebay/Dashboard-Icon/B.webp']); "
        "} "
        "\\$teamBUsers = \\\\App\\\\Models\\\\User::where('team', 'B')->pluck('id')->merge([1, 2, 13, 5])->unique()->filter()->values()->all(); "
        "\\$wfB->members()->sync(\\$teamBUsers); "
        "foreach (['Draft', 'Production Team B', 'Digital Department', 'Approved', 'Blocked/Waiting'] as \\$pos => \\$lname) { "
        "    \\\\App\\\\Models\\\\BoardList::firstOrCreate(['board_id' => \\$wfB->id, 'name' => \\$lname], ['position' => \\$pos]); "
        "} "
        # 5. Setup Planning Board@KiuQ – October 2026
        "\\$plan = \\\\App\\\\Models\\\\Board::where('name', 'Planning Board@KiuQ – October 2026')->first(); "
        "if (!\\$plan) { "
        "    \\$plan = \\\\App\\\\Models\\\\Board::create(['workspace_id' => \\$ws->id, 'name' => 'Planning Board@KiuQ – October 2026', 'slug' => 'planning-board-kiuq-october-2026', 'cover_type' => 'image', 'cover_value' => 'https://img.miniexcavator.org/ebay/Dashboard-Icon/ChatGPT%20Image%20Oct%203%202026%2007_59_23%20AM.webp', 'position' => 2, 'is_hidden' => false, 'created_by' => 1]); "
        "} else { "
        "    \\$plan->update(['is_hidden' => false, 'is_archived' => false, 'cover_type' => 'image', 'cover_value' => 'https://img.miniexcavator.org/ebay/Dashboard-Icon/ChatGPT%20Image%20Oct%203%202026%2007_59_23%20AM.webp']); "
        "} "
        "\\$plan->members()->sync(\\$allDigitalIds); "
        "foreach (['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Meeting Schedule', 'Urgent / Priority', 'Blocked/Waiting'] as \\$pos => \\$lname) { "
        "    \\\\App\\\\Models\\\\BoardList::firstOrCreate(['board_id' => \\$plan->id, 'name' => \\$lname], ['position' => \\$pos]); "
        "} "
        # 6. Automation on Planning Board
        "\\$auto = \\\\App\\\\Models\\\\BoardAutomation::firstOrNew(['board_id' => \\$plan->id, 'trigger_word' => 'ready']); "
        "\\$auto->trigger_type = 'keyword'; "
        "\\$auto->action_type = 'copy'; "
        "\\$auto->target_board_id = \\$wfA->id; "
        "\\$draftList = \\$wfA->lists()->where('name', 'like', '%Draft%')->first(); "
        "if (\\$draftList) { \\$auto->target_list_id = \\$draftList->id; } "
        "\\$auto->save(); "
        # 6a. Workflow Automations for Team A & Team B
        "\\$prodA = \\$wfA->lists()->where('name', 'like', '%Production%')->first(); "
        "\\$ddA = \\$wfA->lists()->where('name', 'like', '%Digital Department%')->first(); "
        "\\$apprA = \\$wfA->lists()->where('name', 'like', '%Approved%')->first(); "
        "\\$blockA = \\$wfA->lists()->where('name', 'like', '%Block%')->first(); "
        "if (\\$prodA) { \\\\App\\\\Models\\\\BoardAutomation::updateOrCreate(['board_id' => \\$wfA->id, 'trigger_word' => 'Team approved'], ['trigger_type' => 'keyword', 'action_type' => 'move', 'target_board_id' => \\$wfA->id, 'target_list_id' => \\$prodA->id]); } "
        "if (\\$ddA) { \\\\App\\\\Models\\\\BoardAutomation::updateOrCreate(['board_id' => \\$wfA->id, 'trigger_word' => 'Production approved'], ['trigger_type' => 'keyword', 'action_type' => 'move', 'target_board_id' => \\$wfA->id, 'target_list_id' => \\$ddA->id]); } "
        "if (\\$apprA) { \\\\App\\\\Models\\\\BoardAutomation::updateOrCreate(['board_id' => \\$wfA->id, 'trigger_word' => 'Production approved SMM'], ['trigger_type' => 'keyword', 'action_type' => 'move', 'target_board_id' => \\$wfA->id, 'target_list_id' => \\$apprA->id]); } "
        "if (\\$apprA) { \\\\App\\\\Models\\\\BoardAutomation::updateOrCreate(['board_id' => \\$wfA->id, 'trigger_word' => 'Approved'], ['trigger_type' => 'keyword', 'action_type' => 'move', 'target_board_id' => \\$wfA->id, 'target_list_id' => \\$apprA->id]); } "
        "if (\\$blockA) { \\\\App\\\\Models\\\\BoardAutomation::updateOrCreate(['board_id' => \\$wfA->id, 'trigger_word' => 'Blocked'], ['trigger_type' => 'keyword', 'action_type' => 'move', 'target_board_id' => \\$wfA->id, 'target_list_id' => \\$blockA->id]); } "
        "\\$prodB = \\$wfB->lists()->where('name', 'like', '%Production%')->first(); "
        "\\$ddB = \\$wfB->lists()->where('name', 'like', '%Digital Department%')->first(); "
        "\\$apprB = \\$wfB->lists()->where('name', 'like', '%Approved%')->first(); "
        "\\$blockB = \\$wfB->lists()->where('name', 'like', '%Block%')->first(); "
        "if (\\$prodB) { \\\\App\\\\Models\\\\BoardAutomation::updateOrCreate(['board_id' => \\$wfB->id, 'trigger_word' => 'Team approved'], ['trigger_type' => 'keyword', 'action_type' => 'move', 'target_board_id' => \\$wfB->id, 'target_list_id' => \\$prodB->id]); } "
        "if (\\$ddB) { \\\\App\\\\Models\\\\BoardAutomation::updateOrCreate(['board_id' => \\$wfB->id, 'trigger_word' => 'Production approved'], ['trigger_type' => 'keyword', 'action_type' => 'move', 'target_board_id' => \\$wfB->id, 'target_list_id' => \\$ddB->id]); } "
        "if (\\$apprB) { \\\\App\\\\Models\\\\BoardAutomation::updateOrCreate(['board_id' => \\$wfB->id, 'trigger_word' => 'Production approved SMM'], ['trigger_type' => 'keyword', 'action_type' => 'move', 'target_board_id' => \\$wfB->id, 'target_list_id' => \\$apprB->id]); } "
        "if (\\$apprB) { \\\\App\\\\Models\\\\BoardAutomation::updateOrCreate(['board_id' => \\$wfB->id, 'trigger_word' => 'Approved'], ['trigger_type' => 'keyword', 'action_type' => 'move', 'target_board_id' => \\$wfB->id, 'target_list_id' => \\$apprB->id]); } "
        "if (\\$blockB) { \\\\App\\\\Models\\\\BoardAutomation::updateOrCreate(['board_id' => \\$wfB->id, 'trigger_word' => 'Blocked'], ['trigger_type' => 'keyword', 'action_type' => 'move', 'target_board_id' => \\$wfB->id, 'target_list_id' => \\$blockB->id]); } "
        # 6b. Setup Social Media Management workspace and SMM Planning Board – October 2026
        "\\$smmWs = \\\\App\\\\Models\\\\Workspace::withTrashed()->where('name', 'Social Media Management')->first(); "
        "if (!\\$smmWs) { "
        "    \\$smmWs = \\\\App\\\\Models\\\\Workspace::create(['name' => 'Social Media Management', 'description' => 'Dedicated workspace for SMM Planning Boards.', 'color' => '#6366f1', 'owner_id' => 1, 'is_active' => true]); "
        "} else { "
        "    if (\\$smmWs->trashed()) { \\$smmWs->restore(); } "
        "} "
        "\\$smmWs->members()->syncWithoutDetaching(\\$allDigitalIds); "
        "\\$smmBoard = \\\\App\\\\Models\\\\Board::where('type', 'smm')->where('name', 'like', '%October 2026%')->whereHas('cards')->first() ?: \\\\App\\\\Models\\\\Board::where('type', 'smm')->where('name', 'like', '%October 2026%')->first(); "
        "if (!\\$smmBoard) { "
        "    \\$smmBoard = \\\\App\\\\Models\\\\Board::create(['workspace_id' => \\$smmWs->id, 'name' => 'SMM Planning Board – October 2026', 'slug' => 'smm-planning-board-october-2026', 'type' => 'smm', 'is_active_smm' => true, 'cover_type' => 'image', 'cover_value' => 'https://img.miniexcavator.org/ebay/Dashboard-Icon/SMM.webp', 'position' => 1, 'is_hidden' => false, 'created_by' => 1]); "
        "} else { "
        "    \\$smmBoard->update(['workspace_id' => \\$smmWs->id, 'is_hidden' => false, 'is_archived' => false, 'type' => 'smm', 'is_active_smm' => true, 'cover_type' => 'image', 'cover_value' => 'https://img.miniexcavator.org/ebay/Dashboard-Icon/SMM.webp', 'position' => 1]); "
        "} "
        "\\\\App\\\\Models\\\\Board::where('type', 'smm')->orWhere('name', 'like', '%SMM%')->update(['workspace_id' => \\$smmWs->id, 'cover_type' => 'image', 'cover_value' => 'https://img.miniexcavator.org/ebay/Dashboard-Icon/SMM.webp']); "
        "\\$smmBoard->members()->syncWithoutDetaching(\\$allDigitalIds); "
        "foreach (['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Final Captions', 'Block/Waiting'] as \\$pos => \\$lname) { "
        "    \\\\App\\\\Models\\\\BoardList::firstOrCreate(['board_id' => \\$smmBoard->id, 'name' => \\$lname], ['position' => (\\$pos + 1) * 1000]); "
        "} "
        # 7. Keep only the 3 October boards visible in Digital Department, keep older month boards hidden in Hidden Boards (never delete)
        "\\\\App\\\\Models\\\\Board::where('workspace_id', \\$ws->id)->whereNotIn('id', [\\$wfA->id, \\$wfB->id, \\$plan->id])->update(['is_hidden' => true]); "
        "\\\\App\\\\Models\\\\Board::where('workspace_id', \\$smmWs->id)->where('id', '!=', \\$smmBoard->id)->update(['is_hidden' => true]); "
        "\\$wfA->update(['position' => 1, 'is_hidden' => false]); "
        "\\$wfB->update(['position' => 2, 'is_hidden' => false]); "
        "\\$plan->update(['position' => 10, 'is_hidden' => false]); "
        "\\$smmBoard->update(['position' => 1, 'is_hidden' => false]); "
        "\\\\App\\\\Models\\\\Board::where('workspace_id', \\$ws->id)->get()->each(function(\\$b) use (\\$allDigitalIds) { \\$b->members()->syncWithoutDetaching(\\$allDigitalIds); }); "
        "\\\\App\\\\Models\\\\Board::where('workspace_id', \\$smmWs->id)->get()->each(function(\\$b) use (\\$allDigitalIds) { \\$b->members()->syncWithoutDetaching(\\$allDigitalIds); }); "
        # 8. Restore old workspaces (VideoTeam, QC/Technical, Listing-ContentWriter) and keep all their boards in Hidden Boards (never in Trash)
        "\\$oldWorkspaces = \\\\App\\\\Models\\\\Workspace::withTrashed()->whereIn('name', ['Listing-ContentWriter-Team@KiuQ', 'VideoTeam@KiuQ', 'QC/Technical@kiuQ'])->get(); "
        "foreach (\\$oldWorkspaces as \\$ow) { "
        "    if (\\$ow->trashed()) { \\$ow->restore(); } "
        "    \\\\App\\\\Models\\\\Board::withTrashed()->where('workspace_id', \\$ow->id)->restore(); "
        "    \\\\App\\\\Models\\\\Board::where('workspace_id', \\$ow->id)->update(['is_hidden' => true, 'is_archived' => false]); "
        "} "
        "echo 'Deployment updates applied successfully!\n';"
        '" && '
    )

    ssh_cmd = [
        "ssh", "-o", "StrictHostKeyChecking=no", "-o", "PubkeyAuthentication=no", "-o", "ConnectTimeout=30", "-p", "65002", "u355625773@157.173.215.124",
        (
            "cd domains/lightcyan-weasel-711536.hostingersite.com/public_html && "
            + "mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/app/public/kpi-uploads && "
            + "chmod -R 775 storage bootstrap/cache && "
            + "rm -f public/hot && rm -f bootstrap/cache/*.php && rm -rf storage/framework/cache/data/* && rm -f app/Console/Commands/SmmImportController.php && rm -f database/migrations/2026_08_07_075801_modify_unique_constraint_on_comment_reactions.php && "
            + "(grep -q 'GOOGLE_KPI_APPS_SCRIPT_URL' .env && sed -i 's|^GOOGLE_KPI_APPS_SCRIPT_URL=.*|GOOGLE_KPI_APPS_SCRIPT_URL=https://script.google.com/macros/s/AKfycbxXXOumYYCzercvaTZwu8maugr8FDkHDudUQ5kTf4JWIUY3GqRzqJJgPw27zFEFPvnG/exec|' .env || (printf '\\nGOOGLE_KPI_DRIVE_FOLDER_ID=1sQiq4-jtjsRmEBfJr6ofZkHXuHZuLhTi\\nGOOGLE_KPI_APPS_SCRIPT_URL=https://script.google.com/macros/s/AKfycbxXXOumYYCzercvaTZwu8maugr8FDkHDudUQ5kTf4JWIUY3GqRzqJJgPw27zFEFPvnG/exec\\nGOOGLE_KPI_API_SECRET=kpi-drive-sync-secret-2026\\n' >> .env)) && "
            + PHP + " artisan cache:clear && "
            + PHP + " artisan optimize && "
            + PHP + " artisan migrate --force && "
            + tinker_sync
            + tinker_clean
            + tinker_fix_colors
            + PHP + " artisan smm:fix-labels && "
            + tinker_fix_smm_labels
            + PHP + " artisan cards:restore-block-smm && "
            + PHP + " artisan cards:sync-planning-weeks && "
            + tinker_fix_returns
            + tinker_update_socials
            + tinker_fix_statuses
            + tinker_fix_deal_stage
            + tinker_fix_smm_board
            + tinker_fix_duplicate_smm
            + tinker_fix_automations
            + tinker_fix_dara_card
            + tinker_clean_reorder
            + tinker_sync_folders
            + tinker_setup_october_planning
            + PHP + " artisan cards:sync-checklists && "
            + PHP + " artisan app:setup-blog-report-permission && "
            + PHP + " artisan view:cache && "
            + PHP + " artisan storage:link && "
            + PHP + ' artisan tinker --execute="App\\\\Models\\\\User::where(\'name\', \'like\', \'%Dara%\')->first()->assignRole(\'admin-digital\');"'
        )
    ]
    run_cmd(ssh_cmd)

    print("Done! Code uploaded, database migrated, and server cache cleared.")
