import subprocess
PHP = "/opt/alt/php84/usr/bin/php"
ssh_cmd = [
    "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u355625773@157.173.215.124",
    f"cd domains/lightcyan-weasel-711536.hostingersite.com/public_html && {PHP} artisan tinker --execute=\"event(new \\\\App\\\\Events\\\\CustomerStatusUpdatedLive(26, 'RESOLVED TEST', 'green', 'Test Agent', 'Tech Support', 'ebay')); echo 'TRIGGERED';\""
]
import os
subprocess.run(ssh_cmd, env=dict(os.environ, SSH_ASKPASS=os.path.abspath("askpass.sh"), DISPLAY="dummy", SSH_ASKPASS_REQUIRE="force"))
