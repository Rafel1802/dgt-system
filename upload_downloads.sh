#!/bin/bash
export SSH_ASKPASS=/Applications/XAMPP/xamppfiles/htdocs/dgt-system/askpass.sh
export DISPLAY=dummy
export SSH_ASKPASS_REQUIRE=force
rsync -avz -e "ssh -o StrictHostKeyChecking=no -p 65002" public/downloads/ u768808434@191.101.12.132:domains/rosybrown-baboon-228003.hostingersite.com/public_html/public/downloads/
