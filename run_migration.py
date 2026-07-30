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
    print("Running database migrations on the live Hostinger server...")
    run_cmd([
        "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002",
        "u355625773@157.173.215.124",
        "cd domains/lightcyan-weasel-711536.hostingersite.com/public_html && /opt/alt/php84/usr/bin/php artisan migrate --force"
    ])
    print("Migrations completed successfully!")
