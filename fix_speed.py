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

print("Applying performance optimizations to Hostinger...")
PHP = "/opt/alt/php84/usr/bin/php"
ssh_cmd = [
    "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u768808434@191.101.12.132",
    (
        "cd domains/rosybrown-baboon-228003.hostingersite.com/public_html && "
        # 1. Turn off debug mode (which drastically slows down Laravel on production)
        "sed -i 's/APP_DEBUG=true/APP_DEBUG=false/g' .env && "
        # 2. Clear any corrupted or improperly permissioned cache files
        "rm -f bootstrap/cache/*.php && "
        # 3. Clear application caches
        f"{PHP} artisan cache:clear && "
        f"{PHP} artisan view:clear && "
        f"{PHP} artisan config:clear && "
        f"{PHP} artisan route:clear && "
        # 4. Rebuild caches cleanly
        f"{PHP} artisan config:cache && "
        f"{PHP} artisan route:cache && "
        f"{PHP} artisan view:cache && "
        # 5. Fix permissions so the web server can read the new cache files quickly
        "chmod -R 775 storage bootstrap/cache && "
        # 6. Reset PHP OPcache to force the server to load the fresh, optimized files
        f"{PHP} -r 'if (function_exists(\"opcache_reset\")) {{ opcache_reset(); }}'"
    )
]
run_cmd(ssh_cmd)
print("\nSpeed optimizations applied! Your app should be much faster now.")
