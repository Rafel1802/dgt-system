import pty
import os
import sys

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

if __name__ == "__main__":
    # 1. Main rsync with relative paths
    rsync_cmd = [
        "rsync", "-avz", "-e", "ssh -o StrictHostKeyChecking=no -p 65002", "--relative",
        "check_db_info.php",
        "resources/views/crm/products/create.blade.php",
        "resources/views/crm/products/edit.blade.php",
        "app/Policies/CardPolicy.php",
        "public/js/trello-board.js",
        "public/js/workspace-alpine.js",
        "resources/views/admin/emails/accounts.blade.php",
        "resources/views/layouts/app.blade.php",
        "resources/views/boards/workspaces.blade.php",
        "resources/views/boards/partials/board-menu.blade.php",
        "app/Http/Controllers/Board/BoardController.php",
        "app/Http/Controllers/Board/CardController.php",
        "u768808434@191.101.12.132:domains/rosybrown-baboon-228003.hostingersite.com/public_html/"
    ]
    run_cmd(rsync_cmd)

    # 2. Upload assets directly to public_html/js/ (without public/ prefix) just in case public_html is the document root
    rsync_workspace_alpine_cmd = [
        "rsync", "-avz", "-e", "ssh -o StrictHostKeyChecking=no -p 65002",
        "public/js/workspace-alpine.js",
        "u768808434@191.101.12.132:domains/rosybrown-baboon-228003.hostingersite.com/public_html/js/"
    ]
    run_cmd(rsync_workspace_alpine_cmd)

    rsync_trello_board_cmd = [
        "rsync", "-avz", "-e", "ssh -o StrictHostKeyChecking=no -p 65002",
        "public/js/trello-board.js",
        "u768808434@191.101.12.132:domains/rosybrown-baboon-228003.hostingersite.com/public_html/js/"
    ]
    run_cmd(rsync_trello_board_cmd)
