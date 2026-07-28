import pty
import os

def run_cmd(cmd_args, password="Digital@PhnomPenh#!2027\n"):
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

ssh_cmd = [
    "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u355625773@157.173.215.124",
    (
        "cd domains/lightcyan-weasel-711536.hostingersite.com/public_html && "
        "mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions && "
        "chmod -R 775 storage bootstrap/cache"
    )
]
run_cmd(ssh_cmd)
