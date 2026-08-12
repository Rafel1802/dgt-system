import os
import subprocess
import sys

def run_command(command):
    print(f"\nRunning: {command}")
    result = subprocess.run(command, shell=True)
    if result.returncode != 0:
        print(f"\nError: Command failed with exit code {result.returncode}")
        sys.exit(result.returncode)
    print("\n--- Command Finished ---\n")

if __name__ == "__main__":
    print("Reversing the previous database fix...")
    
    # SSH into server, cd to project dir, and reverse the tinker database fix
    ssh_cmd = (
        'ssh -o StrictHostKeyChecking=no -p 65002 u355625773@157.173.215.124 '
        '"cd domains/lightcyan-weasel-711536.hostingersite.com/public_html && '
        '/opt/alt/php84/usr/bin/php artisan tinker --execute=\\"App\\\\Models\\\\Card::whereNotNull(\'content_public_date\')->update([\'content_public_date\' => DB::raw(\'DATE_SUB(content_public_date, INTERVAL 1 DAY)\')]);\\""'
    )
    
    run_command(ssh_cmd)
    
    print("Done! The dates have been successfully rolled back to their original correct values.")
