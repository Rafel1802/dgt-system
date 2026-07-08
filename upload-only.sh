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
  u768808434@191.101.12.132:domains/rosybrown-baboon-228003.hostingersite.com/public_html/

echo ""
echo "Done! Only 5 files were uploaded."
