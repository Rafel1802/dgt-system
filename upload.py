import pty
import os
import sys

pid, fd = pty.fork()
if pid == 0:
    # Child process
    os.execlp("rsync", "rsync", "-avz", "-e", "ssh -o StrictHostKeyChecking=no -p 65002", "--relative", 
              "app/Http/Controllers/CRM/ProductController.php", 
              "resources/views/crm/products/create.blade.php", 
              "resources/views/crm/products/edit.blade.php", 
              "app/Policies/CardPolicy.php", 
              "public/js/trello-board.js", 
              "resources/views/admin/emails/accounts.blade.php", 
              "resources/views/layouts/app.blade.php", 
              "u768808434@191.101.12.132:domains/rosybrown-baboon-228003.hostingersite.com/public_html/")
else:
    # Parent process
    output = b""
    while True:
        try:
            data = os.read(fd, 1024)
            if not data:
                break
            output += data
            if b"password:" in data.lower():
                os.write(fd, b"KhmerLucky#2888\n")
            print(data.decode("utf-8", "replace"), end="", flush=True)
        except OSError:
            break
    os.waitpid(pid, 0)
