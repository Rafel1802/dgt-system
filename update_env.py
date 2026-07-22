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

print("Updating .env file on Hostinger...")
ssh_cmd = [
    "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u768808434@191.101.12.132",
    """cd domains/rosybrown-baboon-228003.hostingersite.com/public_html &&
    cat > .env << 'EOF'
APP_NAME="DGT System"
APP_ENV=production
APP_KEY=base64:W7uv+peLAx+LklSrSIB/Y1Ltvt4hv3bGATax6zLaDd8=
APP_DEBUG=false
APP_URL=https://rosybrown-baboon-228003.hostingersite.com
VITE_HOST=
APP_TIMEZONE=Asia/Ho_Chi_Minh

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u768808434_dgt_system
DB_USERNAME=u768808434_dgt_system
DB_PASSWORD=""

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=pusher
FILESYSTEM_DISK=local

QUEUE_CONNECTION=database

CACHE_STORE=file

# ─── SECURITY ─────────────────────────────────────────────────────────────────
LOGIN_MAX_ATTEMPTS=5
LOGIN_DECAY_MINUTES=15
TWO_FACTOR_ENABLED=false

# ─── BACKUP ───────────────────────────────────────────────────────────────────
BACKUP_DISK=local
BACKUP_DESTINATION_PATH=backups

VITE_APP_NAME="${APP_NAME}"

PUSHER_APP_ID=2173118
PUSHER_APP_KEY=e4952db025d8d75ff386
PUSHER_APP_SECRET=bd855782424a121b238d
PUSHER_APP_CLUSTER=ap1
PUSHER_PORT=443
PUSHER_SCHEME=https
EOF
    """
]
run_cmd(ssh_cmd)
print(".env file updated successfully on Hostinger!")
