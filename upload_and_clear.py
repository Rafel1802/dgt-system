import os
import sys
import subprocess

def run_cmd(cmd_args, password="Digital@PhnomPenh#!2027\n"):
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
        "rsync", "-avz", "-e", "ssh -o StrictHostKeyChecking=no -p 65002",
        "--exclude", ".git/",
        "--exclude", "vendor/",
        "--exclude", "node_modules/",
        "--exclude", ".env",
        "--exclude", ".DS_Store",
        "--exclude", "bootstrap/cache/",
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
        # A sample customer-data spreadsheet dropped at the project root (for
        # designing the Process Trucking import template) got deployed to the
        # live document root this way and was briefly publicly downloadable —
        # same class of leak as the *.php/*.py exclusions above, just for
        # spreadsheets instead of scripts.
        "--exclude", "/*.xlsx",
        "--exclude", "/*.xls",
        "--exclude", "/*.csv",
        "--exclude", ".phpunit.result.cache",
        "./",
        "u355625773@157.173.215.124:domains/lightcyan-weasel-711536.hostingersite.com/public_html/"
    ]
    run_cmd(rsync_cmd)

    # 1c. Sync user-uploaded files (QC error images, avatars, attachments, etc.)
    # These are stored in storage/app/public/ locally and served via storage symlink on server.
    rsync_storage_cmd = [
        "rsync", "-avz", "-e", "ssh -o StrictHostKeyChecking=no -p 65002",
        "--ignore-existing",  # Don't overwrite files that already exist on the server
        "storage/app/public/",
        "u355625773@157.173.215.124:domains/lightcyan-weasel-711536.hostingersite.com/public_html/storage/app/public/"
    ]
    run_cmd(rsync_storage_cmd)

    # 1b. Upload assets directly to public_html/js/ (without public/ prefix) just in case public_html is the document root
    rsync_workspace_alpine_cmd = [
        "rsync", "-avz", "-e", "ssh -o StrictHostKeyChecking=no -p 65002",
        "public/js/workspace-alpine.js",
        "u355625773@157.173.215.124:domains/lightcyan-weasel-711536.hostingersite.com/public_html/js/"
    ]
    run_cmd(rsync_workspace_alpine_cmd)

    rsync_trello_board_cmd = [
        "rsync", "-avz", "-e", "ssh -o StrictHostKeyChecking=no -p 65002",
        "public/js/trello-board.js",
        "u355625773@157.173.215.124:domains/lightcyan-weasel-711536.hostingersite.com/public_html/js/"
    ]
    run_cmd(rsync_trello_board_cmd)

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
        "->whereNotNull('sync_group_id')->pluck('sync_group_id')->unique()"
        "->each(fn(\\$g) => App\\Models\\Card::where('sync_group_id', \\$g)->update(['status' => 'approved']));"
        '" && '
    )
    tinker_clean = PHP + ' artisan tinker --execute="App\\Models\\SocialMediaClass::whereIn(\'name\', [\'Long Landscape\', \'Share Blog\', \'Short Reel\', \'Poster Design\', \'Reel\'])->delete();" && '
    
    tinker_fix_colors = PHP + ' artisan tinker --execute="App\\Models\\BoardList::whereIn(\'name\', [\'Week 3\', \'Week 4\'])->update([\'color\' => null]);" && '

    tinker_fix_returns = PHP + ' artisan tinker --execute="App\\\\Models\\\\MachineReturn::where(\'status\', \'received\')->with(\'customer.ebayCustomerRecords\')->get()->each(function(\\$r) { if(\\$r->customer) { foreach(\\$r->customer->ebayCustomerRecords as \\$rec) { if(\\$rec->tab_type !== \'return_received\') \\$rec->updateQuietly([\'tab_type\' => \'return_received\']); } } });" && '
    
    tinker_update_socials = PHP + ' artisan tinker --execute="\\$data = [\'MachineryBargains\' => [\'Facebook\' => \'https://www.facebook.com/Machinery.Bargains\',\'Instagram\' => \'https://www.instagram.com/machinery.bargains\',\'X\' => \'https://x.com/Machin_Bargains\',\'X(Twitter)\' => \'https://x.com/Machin_Bargains\',\'TikTok\' => \'https://www.tiktok.com/@machinery.bargains\',\'YouTube\' => \'https://www.youtube.com/@Machinery.Bargains\',\'Tumblr\' => \'https://www.tumblr.com/machinerybargains\',\'Pinterest\' => \'https://www.pinterest.com/MachineryBargains/\'],\'MiniExca\' => [\'Facebook\' => \'https://www.facebook.com/MiniExcaMachinery/\',\'Instagram\' => \'https://www.instagram.com/miniexcamachinery\',\'X\' => \'https://x.com/miniexcamachine\',\'X(Twitter)\' => \'https://x.com/miniexcamachine\',\'TikTok\' => \'https://www.tiktok.com/@miniexcamachinery\',\'YouTube\' => \'https://www.youtube.com/@MiniExcavatorMachinery\',\'Tumblr\' => \'https://www.tumblr.com/miniexca\',\'Pinterest\' => \'https://www.pinterest.com/miniexca\'],\'MachineryAsia.Online\' => [\'Facebook\' => \'https://www.facebook.com/MachineryAsiaOnlinee\',\'Instagram\' => \'https://www.instagram.com/machineryasiaonline/\',\'X\' => \'https://x.com/MachineryLoader\',\'X(Twitter)\' => \'https://x.com/MachineryLoader\',\'TikTok\' => \'https://www.tiktok.com/@machineryasiaonline\',\'YouTube\' => \'https://www.youtube.com/@MachineryAsiaOnline\',\'Tumblr\' => \'https://www.tumblr.com/blog/machineryasiaonline\',\'Pinterest\' => \'https://www.pinterest.com/MachineryAsiaOnline/\'],\'ImpossibleMachinery\' => [\'Facebook\' => \'https://www.facebook.com/ImpossibleMachinery/\',\'Instagram\' => \'https://www.instagram.com/impossiblemachinery/\',\'X\' => \'https://x.com/impss_machinery\',\'X(Twitter)\' => \'https://x.com/impss_machinery\',\'TikTok\' => \'https://www.tiktok.com/@impossiblemachinery\',\'YouTube\' => \'https://www.youtube.com/@ImpossibleMachinery\',\'Tumblr\' => \'https://www.tumblr.com/impossiblemachinery\',\'Pinterest\' => \'https://www.pinterest.com/ImpossibleMachinery\'],\'SkidSteers\' => [\'Facebook\' => \'https://www.facebook.com/Americanskidsteers\',\'Instagram\' => \'https://www.instagram.com/americanskidsteer/\',\'X\' => \'https://x.com/iloveSkidSteer\',\'X(Twitter)\' => \'https://x.com/iloveSkidSteer\',\'TikTok\' => \'https://www.tiktok.com/@americanskidsteer\',\'YouTube\' => \'https://youtube.com/@AmericanSkidSteer\',\'Tumblr\' => \'https://www.tumblr.com/skidsteerforamerican\',\'Pinterest\' => \'https://www.pinterest.com/american_skidsteer/\'],\'Machinery.Org\' => [\'Facebook\' => \'https://www.facebook.com/machineryorg\',\'Instagram\' => \'https://www.instagram.com/machineryorg\'],\'MachineryAsia (FB)\' => [\'Facebook\' => \'https://www.facebook.com/MachineryAsiaOnlinee\']]; foreach (\\$data as \\$c => \\$s) { \\$cls = \\\\App\\\\Models\\\\SocialMediaClass::where(\'name\', \\$c)->first(); if(!\\$cls) continue; foreach(\\$s as \\$p => \\$u) { \\$itm = \\$cls->items()->where(\'name\', \\$p)->first(); if(\\$itm) { \\$itm->url = \\$u; \\$itm->save(); } } } echo \'Done Socials\';" && '

    tinker_fix_statuses = PHP + ' artisan tinker --execute="App\\\\Models\\\\EbayCustomerRecord::whereIn(\'tab_type\', [\'tech_in_progress\', \'tech_potential_return\'])->update([\'tab_type\' => \'technical_issues\']); App\\\\Models\\\\EbayCustomerRecord::where(\'tab_type\', \'tech_return_machine\')->update([\'tab_type\' => \'return_approved\']); App\\\\Models\\\\EbayCustomerRecord::where(\'tab_type\', \'pickup_arranged\')->update([\'tab_type\' => \'loaded_for_return\']); App\\\\Models\\\\TechSupportCase::where(\'status\', \'in_progress\')->update([\'status\' => \'new_case\']); App\\\\Models\\\\TechSupportCase::where(\'status\', \'return_machine\')->update([\'status\' => \'return_approved\']); App\\\\Models\\\\MachineReturn::where(\'status\', \'pickup_arranged\')->update([\'status\' => \'in_transit_return\']); App\\\\Models\\\\TechSupportCase::where(\'status\', \'return_approved\')->get()->each(function(\\$c) { if (\\$c->source && \\$c->source_type === \'App\\\\Models\\\\EbayCustomerRecord\' && \\$c->source->tab_type !== \'return_approved\') { \\$c->source->updateQuietly([\'tab_type\' => \'return_approved\']); } });" && '
    
    tinker_fix_deal_stage = PHP + ' artisan tinker --execute="App\\\\Models\\\\Customer::where(\'pipeline_stage\', \'new_inquiry\')->update([\'pipeline_stage\' => \'new_lead\']);" && '

    ssh_cmd = [
        "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u355625773@157.173.215.124",
        (
            "cd domains/lightcyan-weasel-711536.hostingersite.com/public_html && "
            + "mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions && "
            + "chmod -R 775 storage bootstrap/cache && "
            + "rm -f public/hot && rm -f bootstrap/cache/*.php && rm -f database/migrations/2026_08_07_075801_modify_unique_constraint_on_comment_reactions.php && "
            + PHP + " artisan optimize && "
            + PHP + " artisan migrate --force && "
            + tinker_sync
            + tinker_clean
            + tinker_fix_colors
            + PHP + " artisan smm:fix-labels && "
            + PHP + " artisan cards:restore-block-smm && "
            + tinker_fix_returns
            + tinker_update_socials
            + tinker_fix_statuses
            + tinker_fix_deal_stage
            + PHP + " artisan app:setup-blog-report-permission && "
            + PHP + " artisan view:cache && "
            + PHP + " artisan storage:link"
        )
    ]
    run_cmd(ssh_cmd)

    print("Done! Code uploaded, database migrated, and server cache cleared.")
