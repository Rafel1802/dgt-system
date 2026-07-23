import os
import subprocess
import sys

askpass_path = "/Applications/XAMPP/xamppfiles/htdocs/dgt-system/askpass.sh"
with open(askpass_path, "w") as f:
    f.write("#!/bin/bash\necho 'KhmerLucky#2888'\n")
os.chmod(askpass_path, 0o755)

env = os.environ.copy()
env["SSH_ASKPASS"] = askpass_path
env["DISPLAY"] = "dummy"
env["SSH_ASKPASS_REQUIRE"] = "force"

def run_cmd(args):
    print(f"Running: {' '.join(args)}")
    proc = subprocess.run(args, env=env, stdin=subprocess.DEVNULL, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True)
    print(proc.stdout)
    if proc.returncode != 0:
        print(f"Command failed with exit code {proc.returncode}")
        sys.exit(1)
    print("--- Done ---\n")

rsync_cmd = [
    "rsync", "-avz", "-e", "ssh -o StrictHostKeyChecking=no -p 65002", "--relative",
    "bootstrap/app.php",
    "app/Http/Controllers/CRM/CustomerController.php",
    "app/Http/Controllers/CRM/WebsiteCrmController.php",
    "app/Http/Controllers/CRM/ShipmentController.php",
    "app/Http/Controllers/CRM/EbayCustomerController.php",
    "app/Http/Controllers/CRM/CrmReportController.php",
    "app/Http/Controllers/Admin/UserController.php",
    "app/Http/Controllers/Admin/DashboardController.php",
    "app/Http/Controllers/CRM/CrmDashboardController.php",
    "app/Http/Requests/Crm/StoreCustomerRequest.php",
    "app/Http/Requests/Crm/UpdateCustomerRequest.php",
    "app/Services/CrmCustomerMatchService.php",
    "app/Services/TechSupportCaseService.php",
    "app/Support/CrmTeamNotifier.php",
    "app/Enums/CustomerQueue.php",
    "app/Models/Customer.php",
    "app/Models/CustomerWorkflowLog.php",
    "app/Models/Lead.php",
    "app/Models/TechSupportCase.php",
    "app/Models/User.php",
    "app/Policies/CustomerPolicy.php",
    "database/seeders/RolesAndPermissionsSeeder.php",
    "database/migrations/2026_07_23_010001_add_current_queue_to_customers_table.php",
    "database/migrations/2026_07_23_010002_create_customer_workflow_logs_table.php",
    "resources/views/crm/index.blade.php",
    "resources/views/crm/create.blade.php",
    "resources/views/crm/edit.blade.php",
    "resources/views/crm/show.blade.php",
    "resources/views/crm/logistics/trucking-queue.blade.php",
    "resources/views/crm/partials/customer_combobox.blade.php",
    "resources/views/crm/website/show.blade.php",
    "resources/views/reports/crm_export.blade.php",
    "resources/views/admin/users/create.blade.php",
    "resources/views/admin/users/edit.blade.php",
    "resources/views/layouts/app.blade.php",
    "resources/views/crm/website/index.blade.php",
    "resources/views/dashboard/index.blade.php",
    "resources/views/crm/dashboard.blade.php",
    "resources/views/crm/ebay/show.blade.php",
    "resources/views/crm/logistics/shipments/index.blade.php",
    "resources/views/crm/logistics/trucking/index.blade.php",
    "routes/web.php",
    "u768808434@191.101.12.132:domains/rosybrown-baboon-228003.hostingersite.com/public_html/"
]
run_cmd(rsync_cmd)

ssh_cmd = [
    "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u768808434@191.101.12.132",
    "cd domains/rosybrown-baboon-228003.hostingersite.com/public_html && "
    # Deliberately NOT touching storage/framework/views/* — Laravel already
    # recompiles a Blade file automatically when its source mtime is newer
    # than the cached copy, which our rsync guarantees for every file we
    # actually deploy. Blanket-deleting the whole view cache instead forced
    # EVERY page on the live site into a cold recompile at once; a request
    # landing in that gap (multiple deploys during active testing this
    # session) could read a half-written/missing compiled view and get
    # truncated or corrupted HTML — self-healing on the next request once
    # recompiled, exactly the "blank page, refresh fixes it, random pages"
    # symptom reported. `php artisan view:cache` was tried as a deliberate
    # single-batch alternative but silently produces zero compiled files on
    # this host (CLI `php` here reports 8.2.30 against a composer.json
    # platform requirement of >=8.4.1 — likely a different PHP than what
    # PHP-FPM actually serves requests with), so it can't be relied on here.
    "rm -rf bootstrap/cache/*.php storage/framework/cache/data/* || true; "
    "php -r 'if (function_exists(\"opcache_reset\")) { var_dump(opcache_reset()); } else { echo \"no opcache\\n\"; }'"
]
run_cmd(ssh_cmd)

print("Deploy successful!")
