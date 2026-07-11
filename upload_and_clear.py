import pty
import os
import sys
import time

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
                # Simple check for password prompt
                if b"password:" in data.lower():
                    os.write(fd, password.encode())
                print(data.decode("utf-8", "replace"), end="", flush=True)
            except OSError:
                break
        os.waitpid(pid, 0)
        print("\n--- Command Finished ---\n")

if __name__ == "__main__":
    # 1. Upload all files (replace if exist), excluding vendor, node_modules, etc.
    rsync_cmd = [
        "rsync", "-avz", "-e", "ssh -o StrictHostKeyChecking=no -p 65002",
        "--exclude", ".git/",
        "--exclude", "vendor/",
        "--exclude", "node_modules/",
        "--exclude", ".env",
        "--exclude", ".DS_Store",
        "./",
        "u768808434@191.101.12.132:domains/rosybrown-baboon-228003.hostingersite.com/public_html/"
    ]
    run_cmd(rsync_cmd)

    # 2. Run migrations on server + remove hot file + optimize caching
    ssh_cmd = [
        "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u768808434@191.101.12.132",
        (
            "cd domains/rosybrown-baboon-228003.hostingersite.com/public_html && "
            "rm -f public/hot && "
            "php artisan migrate --force && "
            "php artisan optimize:clear && "
            "php artisan optimize && "
            "php artisan view:cache"
        )
    ]
    run_cmd(ssh_cmd)

    print("Done! Migrations applied and server cache cleared.")

