import time
import subprocess
import os

print("Sleeping for 15 seconds...")
time.sleep(15)
print("Waking up and triggering event...")

PHP = "/opt/alt/php84/usr/bin/php"
ssh_cmd = [
    "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u355625773@157.173.215.124",
    f"cd domains/lightcyan-weasel-711536.hostingersite.com/public_html && {PHP} artisan tinker --execute=\"\$case = \\\\App\\\\Models\\\\TechSupportCase::where('source_type', \\\\App\\\\Models\\\\EbayCustomerRecord::class)->where('source_id', 25)->first(); if (\$case) {{ app(\\\\App\\\\Http\\\\Controllers\\\\CRM\\\\TechSupportController::class)->updateStatus(request()->merge(['status' => 'resolved', 'note' => 'Agent test']), \$case); echo 'Triggered'; }} else {{ echo 'Case not found'; }}\""
]

askpass_path = os.path.abspath("askpass.sh")
env = os.environ.copy()
env["SSH_ASKPASS"] = askpass_path
env["DISPLAY"] = "dummy"
env["SSH_ASKPASS_REQUIRE"] = "force"
subprocess.run(ssh_cmd, env=env)
