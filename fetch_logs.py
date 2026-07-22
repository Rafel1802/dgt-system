import os
import subprocess
import sys

askpass_path = "/Applications/XAMPP/xamppfiles/htdocs/dgt-system/askpass.sh"

env = os.environ.copy()
env["SSH_ASKPASS"] = askpass_path
env["DISPLAY"] = "dummy"
env["SSH_ASKPASS_REQUIRE"] = "force"

print("Fetching the latest error logs from Hostinger...")
ssh_cmd = [
    "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u768808434@191.101.12.132",
    "tail -n 100 domains/rosybrown-baboon-228003.hostingersite.com/public_html/storage/logs/laravel.log"
]

proc = subprocess.run(ssh_cmd, env=env, text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
print(proc.stdout)
