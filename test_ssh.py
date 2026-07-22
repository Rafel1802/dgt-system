import subprocess

cmd = ["ssh", "-o", "ConnectTimeout=10", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u768808434@191.101.12.132", "echo 'SSH is working'"]
try:
    result = subprocess.run(cmd, capture_output=True, text=True, timeout=15)
    print("STDOUT:", result.stdout)
    print("STDERR:", result.stderr)
    print("RETURN CODE:", result.returncode)
except Exception as e:
    print("Exception:", e)
