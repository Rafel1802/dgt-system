<<<<<<< HEAD
import pty
import os

def run_cmd(cmd_args, password="KhmerLucky#2888\n"):
    print(f"Running: {' '.join(cmd_args)}")
    pid, fd = pty.fork()
    if pid == 0:
        os.execvp(cmd_args[0], cmd_args)
    else:
        while True:
            try:
                data = os.read(fd, 1024)
                if not data:
                    break
                if b"password:" in data.lower():
                    os.write(fd, password.encode())
                print(data.decode("utf-8", "replace"), end="", flush=True)
            except OSError:
                break
        os.waitpid(pid, 0)
        print("\n--- Command Finished ---\n")

=======
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

print("Fixing shared hosting cache and permissions...")
>>>>>>> b3b281c (update again grape)
ssh_cmd = [
    "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u768808434@191.101.12.132",
    (
        "cd domains/rosybrown-baboon-228003.hostingersite.com/public_html && "
<<<<<<< HEAD
        "mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions && "
        "chmod -R 775 storage bootstrap/cache"
    )
]
run_cmd(ssh_cmd)
=======
        # 1. Delete all config/route caches to let the web server (FPM) build them naturally
        "rm -f bootstrap/cache/*.php && "
        # 2. Force delete all file caches and views created by the CLI user
        "rm -rf storage/framework/cache/data/* && "
        "rm -rf storage/framework/views/* && "
        "rm -rf storage/framework/sessions/* && "
        # 3. Give full write access to the storage directories so the web server doesn't silently fail caching
        "chmod -R 777 storage bootstrap/cache"
    )
]
run_cmd(ssh_cmd)
print("\nPermissions and caches completely reset! Try the site again.")
>>>>>>> b3b281c (update again grape)
