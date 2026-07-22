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

print("Updating list names in Hostinger database...")
ssh_cmd = [
    "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u768808434@191.101.12.132",
    """cd domains/rosybrown-baboon-228003.hostingersite.com/public_html &&
    DB_USER=$(grep '^DB_USERNAME=' .env | cut -d '=' -f2 | tr -d '"')
    DB_PASS=$(grep '^DB_PASSWORD=' .env | cut -d '=' -f2 | tr -d '"')
    DB_NAME=$(grep '^DB_DATABASE=' .env | cut -d '=' -f2 | tr -d '"')
    
    SQL_QUERY="
    UPDATE board_lists SET name = 'Week 1' WHERE name LIKE 'Week 1 (%';
    UPDATE board_lists SET name = 'Week 2' WHERE name LIKE 'Week 2 (%';
    UPDATE board_lists SET name = 'Week 3' WHERE name LIKE 'Week 3 (%';
    UPDATE board_lists SET name = 'Week 4' WHERE name LIKE 'Week 4 (%';
    "
    
    echo "Running update query..."
    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "$SQL_QUERY"
    """
]
run_cmd(ssh_cmd)

print("List names updated successfully!")
