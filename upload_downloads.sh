#!/bin/bash
export SSH_ASKPASS=/Applications/XAMPP/xamppfiles/htdocs/dgt-system/askpass.sh
export DISPLAY=dummy
export SSH_ASKPASS_REQUIRE=force
rsync -avz -e "ssh -o StrictHostKeyChecking=no -p 65002" public/downloads/ u355625773@157.173.215.124:domains/lightcyan-weasel-711536.hostingersite.com/public_html/public/downloads/
