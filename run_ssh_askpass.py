import os
import subprocess

askpass_path = "/Applications/XAMPP/xamppfiles/htdocs/dgt-system/askpass.sh"
with open(askpass_path, "w") as f:
    f.write("#!/bin/bash\necho 'Digital@PhnomPenh#!2027'\n")
os.chmod(askpass_path, 0o755)

env = os.environ.copy()
env["SSH_ASKPASS"] = askpass_path
env["DISPLAY"] = "dummy"
env["SSH_ASKPASS_REQUIRE"] = "force" # Required for newer SSH versions when no terminal is present

cmd = "ls -la domains/lightcyan-weasel-711536.hostingersite.com/public_html/"
proc = subprocess.run(
    ["ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u355625773@157.173.215.124", cmd],
    env=env,
    stdin=subprocess.DEVNULL,
    stdout=subprocess.PIPE,
    stderr=subprocess.PIPE,
    text=True
)
print("STDOUT:")
print(proc.stdout)
print("STDERR:")
print(proc.stderr)
