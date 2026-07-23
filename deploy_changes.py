import os
import subprocess
import sys

askpass_path = "/Applications/XAMPP/xamppfiles/htdocs/dgt-system/askpass.sh"
if not os.path.isdir(os.path.dirname(askpass_path)):
    askpass_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "askpass.sh")

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

# Full CRM navigation + layout performance deploy, merged with the CRM
# feature-batch file list (Customer workflow/permissions/notifications/
# reports/duplicate-detection/dashboard work) — both bodies of work touch
# this repo concurrently, so this list has to cover all of it or a deploy
# silently stops syncing whichever half isn't listed.
rsync_cmd = [
    "rsync", "-avz", "-e", "ssh -o StrictHostKeyChecking=no -p 65002", "--relative",
    "bootstrap/app.php",
    # Layout / Turbo / static
    "resources/views/layouts/app.blade.php",
    "public/js/turbo.es2017-esm.js",
    "public/.htaccess",
    # Services / cache helpers
    "app/Services/CrmCustomerMatchService.php",
    "app/Services/CrmService.php",
    "app/Services/TechSupportCaseService.php",
    "app/Support/CrmLookupCache.php",
    "app/Support/CrmTeamNotifier.php",
    "app/Models/Setting.php",
    "app/Models/EbayCustomerRecord.php",
    "app/Models/Customer.php",
    "app/Models/CustomerWorkflowLog.php",
    "app/Models/Lead.php",
    "app/Models/TechSupportCase.php",
    "app/Models/User.php",
    "app/Policies/CustomerPolicy.php",
    "app/Enums/CustomerQueue.php",
    "app/Http/Requests/Crm/StoreCustomerRequest.php",
    "app/Http/Requests/Crm/UpdateCustomerRequest.php",
    "app/Providers/AppServiceProvider.php",
    "app/Http/Middleware/LogActivity.php",
    "app/Notifications/GenericDatabaseNotification.php",
    "database/seeders/RolesAndPermissionsSeeder.php",
    # CRM controllers (list pages)
    "app/Http/Controllers/CRM/WebsiteCrmController.php",
    "app/Http/Controllers/CRM/CustomerController.php",
    "app/Http/Controllers/CRM/EbayCustomerController.php",
    "app/Http/Controllers/CRM/EbayCrmController.php",
    "app/Http/Controllers/CRM/EbayStoreController.php",
    "app/Http/Controllers/CRM/ShipmentController.php",
    "app/Http/Controllers/CRM/TechSupportController.php",
    "app/Http/Controllers/CRM/TruckingCompanyController.php",
    "app/Http/Controllers/CRM/CrmDashboardController.php",
    "app/Http/Controllers/CRM/CrmReportController.php",
    "app/Http/Controllers/Admin/NotificationController.php",
    "app/Http/Controllers/Admin/UserController.php",
    "app/Http/Controllers/Admin/DashboardController.php",
    "routes/web.php",
    # Views touched by perf / UI / CRM feature batch
    "resources/views/crm/website/index.blade.php",
    "resources/views/crm/website/show.blade.php",
    "resources/views/crm/website/call-requests.blade.php",
    "resources/views/crm/tech-support/index.blade.php",
    "resources/views/crm/tech-support/show.blade.php",
    "resources/views/crm/ebay/show.blade.php",
    "resources/views/crm/ebay/index.blade.php",
    "resources/views/crm/ebay/customers/show.blade.php",
    "resources/views/crm/ebay/customers/index.blade.php",
    "resources/views/crm/index.blade.php",
    "resources/views/crm/create.blade.php",
    "resources/views/crm/edit.blade.php",
    "resources/views/crm/show.blade.php",
    "resources/views/crm/dashboard.blade.php",
    "resources/views/crm/logistics/issues.blade.php",
    "resources/views/crm/logistics/trucking-queue.blade.php",
    "resources/views/crm/logistics/shipments/index.blade.php",
    "resources/views/crm/logistics/trucking/index.blade.php",
    "resources/views/crm/partials/customer_combobox.blade.php",
    "resources/views/reports/crm_export.blade.php",
    "resources/views/admin/users/create.blade.php",
    "resources/views/admin/users/edit.blade.php",
    "resources/views/dashboard/index.blade.php",
    # Migrations if present
    "database/migrations/2026_07_23_010001_add_current_queue_to_customers_table.php",
    "database/migrations/2026_07_23_010002_create_customer_workflow_logs_table.php",
    "database/migrations/2026_07_23_120000_add_crm_performance_indexes.php",
    "u768808434@191.101.12.132:domains/rosybrown-baboon-228003.hostingersite.com/public_html/"
]
run_cmd(rsync_cmd)

ssh_cmd = [
    "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u768808434@191.101.12.132",
    "cd domains/rosybrown-baboon-228003.hostingersite.com/public_html && "
    "PHP=/opt/alt/php84/usr/bin/php && "
    # Clear runtime caches then rebuild Laravel optimize caches (config/route/view/event).
    "rm -rf bootstrap/cache/*.php storage/framework/cache/data/* storage/framework/views/* 2>/dev/null || true; "
    "$PHP artisan cache:clear 2>&1 || true; "
    "$PHP artisan view:clear 2>&1 || true; "
    "$PHP artisan config:cache 2>&1 || true; "
    "$PHP artisan route:cache 2>&1 || true; "
    "$PHP artisan view:cache 2>&1 || true; "
    "$PHP artisan event:cache 2>&1 || true; "
    # Apply performance indexes if migration not run yet (safe / no-op if already applied).
    "$PHP artisan migrate --force --path=database/migrations/2026_07_23_120000_add_crm_performance_indexes.php 2>&1 || true; "
    "$PHP -r 'if (function_exists(\"opcache_reset\")) { var_dump(opcache_reset()); } else { echo \"no opcache\\n\"; }' && "
    "ls -la public/js/turbo.es2017-esm.js bootstrap/cache/ 2>&1 | head -30"
]
run_cmd(ssh_cmd)

print("Deploy successful!")
