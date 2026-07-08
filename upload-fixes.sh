#!/bin/bash
# upload-fixes.sh - Pushes board realtime fix to Hostinger production
# Run from your Mac terminal (NOT from SSH)

set -e

echo "=== Uploading board realtime fix to Hostinger ==="
echo ""

# Upload modified files (Relative paths - creates public_html/app/... and public_html/public/...)
echo "[1/3] Uploading files (Relative paths)..."
rsync -avzR -e "ssh -p 65002" \
  app/Notifications/BoardActivityNotification.php \
  public/js/trello-board.js \
  public/build/manifest.json \
  public/build/assets/ \
  public/debug_path.php \
  u768808434@191.101.12.132:domains/rosybrown-baboon-228003.hostingersite.com/public_html/

# Also upload assets directly to public_html/ (without public/ prefix) just in case public_html is the document root
echo "Uploading assets directly to public_html/ folder structure..."
rsync -avz -e "ssh -p 65002" \
  public/js/trello-board.js \
  u768808434@191.101.12.132:domains/rosybrown-baboon-228003.hostingersite.com/public_html/js/

rsync -avz -e "ssh -p 65002" \
  public/build/manifest.json \
  u768808434@191.101.12.132:domains/rosybrown-baboon-228003.hostingersite.com/public_html/build/

rsync -avz -e "ssh -p 65002" \
  public/build/assets/ \
  u768808434@191.101.12.132:domains/rosybrown-baboon-228003.hostingersite.com/public_html/build/assets/

echo ""
echo "[2/3] Clearing server caches (both artisan and manual rm)..."
ssh -p 65002 u768808434@191.101.12.132 \
  "cd domains/rosybrown-baboon-228003.hostingersite.com/public_html && rm -rf bootstrap/cache/*.php storage/framework/cache/data/* storage/framework/views/* || true"

echo ""
echo "[3/3] Done!"
echo ""
echo "Now hard-refresh your browser (Shift + Command + R on Mac) to load the new trello-board.js!"
echo ""
