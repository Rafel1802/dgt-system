import pexpect
import sys

child = pexpect.spawn('rsync -avz -e "ssh -o StrictHostKeyChecking=no -p 65002" u768808434@191.101.12.132:domains/rosybrown-baboon-228003.hostingersite.com/public_html/storage/logs/laravel.log ./remote_laravel.log')
child.expect('password:')
child.sendline('KhmerLucky#2888')
child.expect(pexpect.EOF, timeout=60)
print(child.before.decode('utf-8'))
