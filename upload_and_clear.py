import pty
import os
import sys
import time

def run_cmd(cmd_args, password="KhmerLucky#2888\n"):
    print(f"Running: {' '.join(cmd_args)}")
    pid, fd = pty.fork()
    if pid == 0:
        os.execvp(cmd_args[0], cmd_args)
    else:
        while True:
            try:
                data = os.read(fd, 1024)
                if not data:
                    break
                # Simple check for password prompt
                if b"password:" in data.lower():
                    os.write(fd, password.encode())
                print(data.decode("utf-8", "replace"), end="", flush=True)
            except OSError:
                break
        os.waitpid(pid, 0)
        print("\n--- Command Finished ---\n")

if __name__ == "__main__":
    # 1. Upload files — migrations + controller that uses new columns
    rsync_cmd = [
        "rsync", "-avz", "-e", "ssh -o StrictHostKeyChecking=no -p 65002", "--relative",
        # Migrations that may be missing on Hostinger (2026-06 onwards)
        "database/migrations/2026_06_26_000003_create_website_follow_ups_table.php",
        "database/migrations/2026_06_26_152555_create_website_members_table.php",
        "database/migrations/2026_06_28_000001_add_error_fields_to_websites_table.php",
        "database/migrations/2026_06_29_091304_add_notification_sound_to_users_table.php",
        "database/migrations/2026_06_30_000001_create_ebay_stores_table.php",
        "database/migrations/2026_06_30_000002_alter_ebay_offers_add_store_id.php",
        "database/migrations/2026_06_30_000003_create_trucking_companies_table.php",
        "database/migrations/2026_06_30_000004_alter_logistics_add_trucking_company_id.php",
        "database/migrations/2026_06_30_000005_create_shipments_table.php",
        "database/migrations/2026_06_30_000006_create_shipment_customers_table.php",
        "database/migrations/2026_06_30_000007_add_crm_role_to_users_table.php",
        "database/migrations/2026_07_01_000001_create_product_categories_table.php",
        "database/migrations/2026_07_01_000002_add_category_id_to_products_table.php",
        "database/migrations/2026_07_01_000003_create_ebay_customer_records_table.php",
        "database/migrations/2026_07_01_000004_create_crm_external_links_table.php",
        "database/migrations/2026_07_01_140417_add_icon_url_to_product_categories_table.php",
        "database/migrations/2026_07_01_141824_add_logo_url_to_ebay_stores_table.php",
        "database/migrations/2026_07_02_075531_create_personal_access_tokens_table.php",
        "database/migrations/2026_07_03_000010_add_error_attachments_and_block_completion.php",
        "database/migrations/2026_07_04_000001_add_customer_id_to_ebay_customer_records_table.php",
        # Original user files
        "resources/views/boards/workspaces.blade.php",
        "resources/views/boards/partials/board-menu.blade.php",
        "app/Http/Controllers/Board/BoardController.php",
        "app/Http/Controllers/Board/CardController.php",
        "app/Models/Board.php",
        "resources/views/layouts/app.blade.php",
        "routes/web.php",
        "app/Services/BoardWorkflowService.php",
        "public/js/trello-board.js",
        "public/js/workspace-alpine.js",
        "u768808434@191.101.12.132:domains/rosybrown-baboon-228003.hostingersite.com/public_html/"
    ]
    run_cmd(rsync_cmd)

    # 2. Run migrations on server + clear all caches
    ssh_cmd = [
        "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u768808434@191.101.12.132",
        (
            "cd domains/rosybrown-baboon-228003.hostingersite.com/public_html && "
            "php artisan migrate --force && "
            "rm -rf bootstrap/cache/*.php storage/framework/cache/data/* storage/framework/views/* || true"
        )
    ]
    run_cmd(ssh_cmd)

    print("Done! Migrations applied and server cache cleared.")

