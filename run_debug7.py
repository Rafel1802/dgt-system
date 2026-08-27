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
    return proc.returncode

# 1. SCP
scp_cmd = [
    "scp", "-o", "StrictHostKeyChecking=no", "-P", "65002",
    "debug7.php",
    "u355625773@157.173.215.124:domains/lightcyan-weasel-711536.hostingersite.com/public_html/debug7_smm.php"
]
run_cmd(scp_cmd)

# 2. Execute
ssh_cmd = [
    "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002",
    "u355625773@157.173.215.124",
    "cd domains/lightcyan-weasel-711536.hostingersite.com/public_html && /opt/alt/php84/usr/bin/php debug7_smm.php && rm debug7_smm.php"
]
run_cmd(ssh_cmd)
