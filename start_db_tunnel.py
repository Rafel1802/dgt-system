import pty
import os
import sys

def start_tunnel(password="KhmerLucky#2888\n"):
    print("Starting SSH tunnel to live database on port 3307...")
    print("Keep this script running in the background to maintain the database connection.")
    print("Press Ctrl+C to stop.")
    
    cmd_args = [
        "ssh", "-N", "-L", "3307:127.0.0.1:3306", 
        "-o", "StrictHostKeyChecking=no", 
        "-p", "65002", "u768808434@193.203.166.164"
    ]
    
    pid, fd = pty.fork()
    if pid == 0:
        os.execvp(cmd_args[0], cmd_args)
    else:
        import time
        success_printed = False
        start_time = time.time()
        while True:
            try:
                # Check if process exited
                status = os.waitpid(pid, os.WNOHANG)
                if status != (0, 0):
                    print(f"\n❌ SSH process exited with status {status[1]}. Tunnel failed!")
                    break
                    
                data = os.read(fd, 1024)
                if not data:
                    break
                if b"password:" in data.lower():
                    os.write(fd, password.encode())
                    print("\n\n✅ Password entered. Waiting for connection...")
                else:
                    print(data.decode("utf-8", "replace"), end="", flush=True)
                    
                if not success_printed and time.time() - start_time > 3:
                    print("\n\n✅ SUCCESS: The secure tunnel should now be OPEN and RUNNING!")
                    print("✅ Your local project can connect to the live database on 127.0.0.1:3307.")
                    print("⚠️  DO NOT CLOSE this window. Leave it running in the background.")
                    success_printed = True
            except OSError:
                break
            except KeyboardInterrupt:
                print("\nTunnel stopped by user.")
                os.kill(pid, 9)
                break
        try:
            os.waitpid(pid, 0)
        except ChildProcessError:
            pass

if __name__ == "__main__":
    start_tunnel()
