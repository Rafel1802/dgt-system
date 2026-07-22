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

sql_file = "u768808434_dgt_system.sql"
gz_file = "u768808434_dgt_system.sql.gz"

if not os.path.exists(sql_file):
    print(f"Error: {sql_file} not found in current directory.")
    sys.exit(1)

print("1. Compressing SQL file (this may take a minute for 333MB)...")
subprocess.run(["gzip", "-k", "-f", sql_file])

print("\n2. Uploading compressed file to Hostinger...")
scp_cmd = [
    "scp", "-o", "StrictHostKeyChecking=no", "-P", "65002",
    gz_file,
    "u768808434@191.101.12.132:domains/rosybrown-baboon-228003.hostingersite.com/"
]
run_cmd(scp_cmd)

print("\n3. Importing database on Hostinger...")
ssh_cmd = [
    "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u768808434@191.101.12.132",
    """cd domains/rosybrown-baboon-228003.hostingersite.com/public_html &&
    DB_USER=$(grep '^DB_USERNAME=' .env | cut -d '=' -f2 | tr -d '"')
    DB_PASS=$(grep '^DB_PASSWORD=' .env | cut -d '=' -f2 | tr -d '"')
    DB_NAME=$(grep '^DB_DATABASE=' .env | cut -d '=' -f2 | tr -d '"')
    
    echo "Clearing existing tables in $DB_NAME..."
    # Generate DROP TABLE statements and execute them with FOREIGN_KEY_CHECKS disabled
    TABLES=$(mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -BNe "SHOW TABLES")
    if [ -n "$TABLES" ]; then
        DROP_CMD="SET FOREIGN_KEY_CHECKS = 0;"
        for t in $TABLES; do
            DROP_CMD="$DROP_CMD DROP TABLE IF EXISTS \`$t\`;"
        done
        DROP_CMD="$DROP_CMD SET FOREIGN_KEY_CHECKS = 1;"
        mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "$DROP_CMD"
    fi

    echo "Importing into database: $DB_NAME as user: $DB_USER"
    zcat ../u768808434_dgt_system.sql.gz | mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" &&
    rm ../u768808434_dgt_system.sql.gz
    """
]
run_cmd(ssh_cmd)

print("\nDatabase import successful!")
