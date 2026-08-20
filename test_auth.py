import subprocess
PHP = "/opt/alt/php84/usr/bin/php"
ssh_cmd = [
    "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u355625773@157.173.215.124",
    f"cd domains/lightcyan-weasel-711536.hostingersite.com/public_html && {PHP} artisan tinker --execute=\"\$u = \\\\App\\\\Models\\\\User::where('email', 'sophorn@kiuq.com')->first(); echo 'AUTH: ' . (\$u->hasWebsiteAccess() || \$u->hasEbayAccess() || \$u->hasCrmAccess() || \$u->hasRole(['tech-support', 'logistic-team', 'logistic-supervisor']) ? 'YES' : 'NO') . PHP_EOL;\""
]
import os
subprocess.run(ssh_cmd, env=dict(os.environ, SSH_ASKPASS=os.path.abspath("askpass.sh"), DISPLAY="dummy", SSH_ASKPASS_REQUIRE="force"))
