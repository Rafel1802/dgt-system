import subprocess

PHP = "/opt/alt/php84/usr/bin/php"
ssh_cmd = [
    "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u355625773@157.173.215.124",
    "cd domains/lightcyan-weasel-711536.hostingersite.com/public_html && "
    + PHP + " artisan tinker --execute=\"\\$csvUrl = 'https://docs.google.com/spreadsheets/d/1X727wTcYSEdybFppqsoqDPmbt3igZ4a844U6ncZh8JQ/export?format=csv&gid=1970379363'; \\$data = @file_get_contents(\\$csvUrl); echo 'LEN: ' . strlen(\\$data) . ' DATA: ' . substr(\\$data, 0, 100);\""
]

print("Running command...")
result = subprocess.run(ssh_cmd, capture_output=True, text=True)
print("STDOUT:")
print(result.stdout)
print("STDERR:")
print(result.stderr)
