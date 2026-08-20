import subprocess
PHP = "/opt/alt/php84/usr/bin/php"
ssh_cmd = [
    "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u355625773@157.173.215.124",
    f"cd domains/lightcyan-weasel-711536.hostingersite.com/public_html && {PHP} artisan tinker --execute=\"event(new \App\Events\CustomerStatusUpdatedLive(25, 'Resolved', 'bg-blue-500', 'Test', 'Test Team', 'ebay'));\""
]
import os
askpass_path = os.path.abspath("askpass.sh")
env = os.environ.copy()
env["SSH_ASKPASS"] = askpass_path
env["DISPLAY"] = "dummy"
env["SSH_ASKPASS_REQUIRE"] = "force"
subprocess.run(ssh_cmd, env=env)
