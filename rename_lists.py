import os
import subprocess

ssh_cmd = "ssh -o StrictHostKeyChecking=no -p 65002 u355625773@157.173.215.124 'cd domains/lightcyan-weasel-711536.hostingersite.com/public_html && /opt/alt/php84/usr/bin/php artisan tinker --execute=\"\\App\\Models\\BoardList::where(\\\"name\\\", \\\"LIKE\\\", \\\"Week %\\\")->get()->each(function (\\$list) { \\$list->name = preg_replace(\\\"/\\\\s*\\\\(.*?\\\\)/\\\", \\\"\\\", \\$list->name); \\$list->save(); }); echo \\\"Renamed successfully\\\\n\\\";\"'"

print("Running rename script...")
os.system(ssh_cmd)
print("Done.")
