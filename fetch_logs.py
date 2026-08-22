import os
import sys
import subprocess

def run_cmd(cmd_args):
    askpass_path = os.path.abspath("askpass.sh")
    env = os.environ.copy()
    env["SSH_ASKPASS"] = askpass_path
    env["DISPLAY"] = "dummy"
    env["SSH_ASKPASS_REQUIRE"] = "force"
    
    proc = subprocess.run(cmd_args, env=env)
    if proc.returncode != 0:
        sys.exit(1)

ssh_cmd = [
    "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u355625773@157.173.215.124",
    "cd domains/lightcyan-weasel-711536.hostingersite.com/public_html && grep -B 2 -A 5 'production.ERROR:' storage/logs/laravel.log | tail -n 50"
]
run_cmd(ssh_cmd)
