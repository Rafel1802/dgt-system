import os
import subprocess

def run_cmd(cmd_args):
    askpass_path = os.path.abspath("askpass.sh")
    env = os.environ.copy()
    env["SSH_ASKPASS"] = askpass_path
    env["DISPLAY"] = "dummy"
    env["SSH_ASKPASS_REQUIRE"] = "force"
    subprocess.run(cmd_args, env=env)

PHP = "/opt/alt/php84/usr/bin/php"
tinker_cmd = PHP + ' artisan tinker --execute="\\$c = App\\\\Models\\\\Customer::where(\'name\', \'like\', \'%Hour%\')->first(); if(\\$c) { \\$c->delete(); echo \'Deleted \' . \\$c->name; } else { echo \'Not found\'; }"'

ssh_cmd = [
    "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u355625773@157.173.215.124",
    f"cd domains/lightcyan-weasel-711536.hostingersite.com/public_html && {tinker_cmd}"
]
run_cmd(ssh_cmd)
