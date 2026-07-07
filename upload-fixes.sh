#!/bin/bash

# Target files and directories to upload
FILES=(
  "public/js/trello-board.js"
  "resources/views/layouts/app.blade.php"
)

# Remote server details
SSH_PORT=65002
REMOTE_USER="u768808434"
REMOTE_HOST="191.101.12.132"
REMOTE_DIR="domains/rosybrown-baboon-228003.hostingersite.com/public_html/"

echo "🚀 Uploading updated files to Hostinger..."
echo "Please enter your password (KhmerLucky#2888) when prompted."
echo "--------------------------------------------------------"

rsync -avzR -e "ssh -p ${SSH_PORT}" "${FILES[@]}" "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}"

echo "--------------------------------------------------------"
echo "✅ Upload complete!"
