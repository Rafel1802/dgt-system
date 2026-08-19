import os
import subprocess

PHP = "/opt/alt/php84/usr/bin/php"
SSH_CMD = ["ssh", "-i", "/Users/phanithlim/.ssh/hostinger_mvillage", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u355625773@157.173.215.124"]
HOSTINGER_DIR = "domains/lightcyan-weasel-711536.hostingersite.com/public_html"

php_code = """
\\DB::table('leads')->where('status', 'machine_return')->update(['status' => 'approve_return']);
\\DB::table('leads')->where('status', 'tech_red_case')->update(['status' => 'potential_return']);
\\DB::table('leads')->where('status', 'tech_in_progress')->update(['status' => 'technical_issues']);
\\DB::table('leads')->where('status', 'technical_support')->update(['status' => 'technical_issues']);
\\DB::table('leads')->where('status', 'in_transit')->update(['status' => 'loaded']);
\\DB::table('leads')->where('status', 'delayed_shipment')->update(['status' => 'pending_delivery']);
\\DB::table('leads')->where('status', 'in_delivery')->update(['status' => 'pending_delivery']);
\\DB::table('leads')->where('status', 'successful')->update(['status' => 'successful_lead']);
\\DB::table('leads')->where('status', 'lost')->update(['status' => 'lost_interest']);
\\DB::table('leads')->where('status', 'new_lead')->update(['status' => 'new_inquiry']);
\\DB::table('leads')->where('status', 'resolved')->update(['status' => 'resolve']);
echo "Done replacing legacy statuses.";
"""

command = SSH_CMD + [f"cd {HOSTINGER_DIR} && {PHP} artisan tinker --execute=\"{php_code}\""]
print("Running command...")
subprocess.run(" ".join(command), shell=True)
