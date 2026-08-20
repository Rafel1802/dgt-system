import subprocess
PHP = "/opt/alt/php84/usr/bin/php"
ssh_cmd = [
    "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u355625773@157.173.215.124",
    f"cd domains/lightcyan-weasel-711536.hostingersite.com/public_html && {PHP} artisan tinker --execute=\"echo 'CASE_ID:' . \\\\App\\\\Models\\\\TechSupportCase::where('source_type', \\\\App\\\\Models\\\\EbayCustomerRecord::class)->where('source_id', 26)->first()->id . PHP_EOL;\""
]
import os
subprocess.run(ssh_cmd, env=dict(os.environ, SSH_ASKPASS=os.path.abspath("askpass.sh"), DISPLAY="dummy", SSH_ASKPASS_REQUIRE="force"))
