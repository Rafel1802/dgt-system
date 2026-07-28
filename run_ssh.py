import pty
import os
import sys

cmd = sys.argv[1] if len(sys.argv) > 1 else "ls -la domains/lightcyan-weasel-711536.hostingersite.com/public_html/"

pid, fd = pty.fork()
if pid == 0:
    os.execlp("ssh", "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u355625773@157.173.215.124", cmd)
else:
    output = b""
    while True:
        try:
            data = os.read(fd, 1024)
            if not data:
                break
            output += data
            if b"password:" in data.lower():
                os.write(fd, b"Digital@PhnomPenh#!2027\n")
        except OSError:
            break
    _, status = os.waitpid(pid, 0)
    print(output.decode("utf-8", "replace"))
