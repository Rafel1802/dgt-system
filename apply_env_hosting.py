import os
import subprocess
import sys

askpass_path = "/Applications/XAMPP/xamppfiles/htdocs/dgt-system/askpass.sh"

env = os.environ.copy()
env["SSH_ASKPASS"] = askpass_path
env["DISPLAY"] = "dummy"
env["SSH_ASKPASS_REQUIRE"] = "force"

def run_cmd(args):
    print(f"Running: {' '.join(args)}")
    proc = subprocess.run(args, env=env)
    if proc.returncode != 0:
        print(f"Command failed with exit code {proc.returncode}")
        sys.exit(1)
    print("--- Done ---\n")

print("Pushing the optimized .env-hosting to Hostinger...")
rsync_cmd = [
    "rsync", "-avz", "-e", "ssh -o StrictHostKeyChecking=no -p 65002",
    ".env-hosting",
    "u768808434@191.101.12.132:domains/rosybrown-baboon-228003.hostingersite.com/public_html/.env"
]
run_cmd(rsync_cmd)

# Clear cache on server one last time just in case database cache needs to transition to file cache safely
print("Resetting cache configurations...")
PHP = "/opt/alt/php84/usr/bin/php"
ssh_cmd = [
    "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u768808434@191.101.12.132",
    (
        "cd domains/rosybrown-baboon-228003.hostingersite.com/public_html && "
        "rm -rf storage/framework/cache/data/* && "
        f"{PHP} artisan cache:clear"
    )
]
run_cmd(ssh_cmd)

print("\nOptimized .env has been applied successfully!")
