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
    "app/Http/Controllers/CRM/CustomerController.php",
    "app/Http/Controllers/CRM/WebsiteCrmController.php",
    "app/Http/Controllers/Admin/UserController.php",
    "app/Services/CrmCustomerMatchService.php",
    "resources/views/crm/create.blade.php",
    "resources/views/crm/edit.blade.php",
    "resources/views/crm/partials/customer_combobox.blade.php",
    "resources/views/crm/website/show.blade.php",
    "resources/views/admin/users/create.blade.php",
    "resources/views/admin/users/edit.blade.php",
    "u768808434@191.101.12.132:domains/rosybrown-baboon-228003.hostingersite.com/public_html/"
]
run_cmd(rsync_cmd)

ssh_cmd = [
    "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u768808434@191.101.12.132",
    "cd domains/rosybrown-baboon-228003.hostingersite.com/public_html && "
    "rm -rf bootstrap/cache/*.php storage/framework/cache/data/* storage/framework/views/* || true; "
    "php -r 'if (function_exists(\"opcache_reset\")) { var_dump(opcache_reset()); } else { echo \"no opcache\\n\"; }'"
]
run_cmd(ssh_cmd)

verify_cmd = [
    "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u768808434@191.101.12.132",
    "cd domains/rosybrown-baboon-228003.hostingersite.com/public_html && "
    "grep -n 'canDeleteCrmRecords' app/Http/Controllers/CRM/WebsiteCrmController.php"
]
run_cmd(verify_cmd)

print("Deploy successful!")
