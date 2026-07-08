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
    "resources/views/boards/workspaces.blade.php",
    "resources/views/boards/partials/board-menu.blade.php",
    "app/Http/Controllers/Board/BoardController.php",
    "app/Http/Controllers/Board/CardController.php",
    "app/Models/Board.php",
    "resources/views/layouts/app.blade.php",
    "routes/web.php",
    "u768808434@191.101.12.132:domains/rosybrown-baboon-228003.hostingersite.com/public_html/"
]
run_cmd(rsync_cmd)

ssh_cmd = [
    "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u768808434@191.101.12.132",
    "cd domains/rosybrown-baboon-228003.hostingersite.com/public_html && rm -rf bootstrap/cache/*.php storage/framework/cache/data/* storage/framework/views/* || true"
]
run_cmd(ssh_cmd)

print("Deploy successful!")
