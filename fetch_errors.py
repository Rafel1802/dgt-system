import os
import sys
import subprocess

cmd_args = ["ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u355625773@157.173.215.124", "grep -B 5 -A 10 -i 'production.ERROR' domains/lightcyan-weasel-711536.hostingersite.com/public_html/storage/logs/laravel.log | tail -n 50"]

askpass_path = os.path.abspath("askpass.sh")
env = os.environ.copy()
env["SSH_ASKPASS"] = askpass_path
env["DISPLAY"] = "dummy"
env["SSH_ASKPASS_REQUIRE"] = "force"

proc = subprocess.run(cmd_args, env=env)
