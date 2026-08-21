import os
import sys
import subprocess

def run_cmd(cmd_args, password="Digital@PhnomPenh#!2027\n"):
    askpass_path = os.path.abspath("askpass.sh")
    env = os.environ.copy()
    env["SSH_ASKPASS"] = askpass_path
    env["DISPLAY"] = "dummy"
    env["SSH_ASKPASS_REQUIRE"] = "force"
    
    proc = subprocess.run(cmd_args, env=env)

if __name__ == "__main__":
    ssh_cmd = [
        "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u355625773@157.173.215.124",
        "grep '^DB_' domains/lightcyan-weasel-711536.hostingersite.com/public_html/.env"
    ]
    run_cmd(ssh_cmd)
