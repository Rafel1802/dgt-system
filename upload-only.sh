#!/bin/bash
# upload-only.sh - Uploads ONLY the 5 specific files we just updated

echo "=== Uploading ONLY updated files to Hostinger ==="
echo ""

rsync -avzR -e "ssh -p 65002" \
  resources/views/boards/workspaces.blade.php \
  resources/views/boards/partials/board-menu.blade.php \
  app/Http/Controllers/Board/BoardController.php \
  app/Http/Controllers/Board/CardController.php \
  resources/views/layouts/app.blade.php \
  u355625773@157.173.215.124:domains/lightcyan-weasel-711536.hostingersite.com/public_html/

echo ""
echo "Done! Only 5 files were uploaded."
