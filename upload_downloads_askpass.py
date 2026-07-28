import os
import subprocess
import sys

askpass_path = "/Applications/XAMPP/xamppfiles/htdocs/dgt-system/askpass.sh"
with open(askpass_path, "w") as f:
    f.write("#!/bin/bash\necho 'Digital@PhnomPenh#!2027'\n")
os.chmod(askpass_path, 0o755)

env = os.environ.copy()
env["SSH_ASKPASS"] = askpass_path
env["DISPLAY"] = "dummy"
env["SSH_ASKPASS_REQUIRE"] = "force"

print("Uploading to Hostinger...")
rsync_cmd = [
    "rsync", "-avz", "-e", "ssh -o StrictHostKeyChecking=no -p 65002",
    "public/downloads/",
    "u355625773@157.173.215.124:domains/lightcyan-weasel-711536.hostingersite.com/public_html/public/downloads/"
]

proc = subprocess.run(
    rsync_cmd,
    env=env,
    stdin=subprocess.DEVNULL,
    stdout=subprocess.PIPE,
    stderr=subprocess.PIPE,
    text=True
)
print("STDOUT:", proc.stdout)
print("STDERR:", proc.stderr)

if proc.returncode == 0:
    print("Upload completed successfully.")
else:
    print("Upload failed.")
