import pexpect
import sys

child = pexpect.spawn('rsync -avz -e "ssh -o StrictHostKeyChecking=no -p 65002" u355625773@157.173.215.124:domains/lightcyan-weasel-711536.hostingersite.com/public_html/storage/logs/laravel.log ./remote_laravel.log')
child.expect('password:')
child.sendline('Digital@PhnomPenh#!2027')
child.expect(pexpect.EOF, timeout=60)
print(child.before.decode('utf-8'))
