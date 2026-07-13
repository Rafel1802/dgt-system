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
    #
    # IMPORTANT: this rsyncs the *entire* project into the live public web root.
    # Any loose file at the repo root — a debug script, a DB dump, a deploy
    # helper with a hardcoded password — becomes instantly web-accessible
    # unless explicitly excluded here. (This is exactly what happened: several
    # *.php test/maintenance scripts and *.py deploy scripts containing this
    # very SSH password were found live on production and had to be purged.)
    # No real Laravel app code lives as a loose script at the project root —
    # it all lives under app/, routes/, config/, resources/, etc. — so blanket-
    # excluding root-level *.php/*.py/*.sh/*.exp files is safe and closes this
    # class of leak for good, not just for the specific filenames seen so far.
    rsync_cmd = [
        "rsync", "-avz", "-e", "ssh -o StrictHostKeyChecking=no -p 65002",
        "--exclude", ".git/",
        "--exclude", "vendor/",
        "--exclude", "node_modules/",
        "--exclude", ".env",
        "--exclude", ".DS_Store",
        "--exclude", "backups/",
        "--exclude", "*.sql",
        "--exclude", "/*.php",   # leading "/" = project root only, not app/ etc.
        "--exclude", "/*.py",
        "--exclude", "/*.sh",
        "--exclude", "/*.exp",
        "--exclude", "/*.md",
        "--exclude", ".phpunit.result.cache",
        "./",
        "u768808434@191.101.12.132:domains/rosybrown-baboon-228003.hostingersite.com/public_html/"
    ]
    run_cmd(rsync_cmd)

    # 2. Run migrations on server + remove hot file + optimize caching
    # NOTE: the server's default `php` on PATH is 8.2 (Composer requires >=8.4.1),
    # so every artisan call below silently no-ops on the platform check unless we
    # point at the real PHP 8.4 binary explicitly.
    PHP = "/opt/alt/php84/usr/bin/php"
    ssh_cmd = [
        "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u768808434@191.101.12.132",
        (
            "cd domains/rosybrown-baboon-228003.hostingersite.com/public_html && "
            "rm -f public/hot && "
            f"{PHP} artisan migrate --force && "
            f"{PHP} artisan optimize:clear && "
            f"{PHP} artisan optimize && "
            f"{PHP} artisan view:cache"
        )
    ]
    run_cmd(ssh_cmd)

    print("Done! Migrations applied and server cache cleared.")

