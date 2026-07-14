#!/usr/bin/env python3
import os
import sys
import json
import re
import subprocess
import shutil

def main():
    # 1. Read current version from public/appcast/latest-mac.json
    app_root = "/Applications/XAMPP/xamppfiles/htdocs/dgt-system"
    appcast_path = os.path.join(app_root, "public/appcast/latest-mac.json")
    
    try:
        with open(appcast_path, "r") as f:
            appcast_data = json.load(f)
            current_version = appcast_data.get("version", "1.0.5")
    except Exception as e:
        print(f"Error reading appcast: {e}")
        current_version = "1.0.5"

    print(f"Current macOS app version on Hostinger: {current_version}")

    # Determine next version by incrementing the patch number
    parts = current_version.split('.')
    if len(parts) == 3:
        next_version = f"{parts[0]}.{parts[1]}.{int(parts[2]) + 1}"
    else:
        next_version = "1.0.6"

    # If a version is provided as command-line arg, use it
    if len(sys.argv) > 1:
        new_version = sys.argv[1]
    else:
        new_version = input(f"Enter new version to deploy [{next_version}]: ").strip()
        if not new_version:
            new_version = next_version

    print(f"Deploying version: {new_version}")

    # 2. Build the macOS DMG using build_staff_dmg.sh
    build_script = os.path.join(app_root, "dgt_macos_app/build_staff_dmg.sh")
    domain = "https://rosybrown-baboon-228003.hostingersite.com"

    print(f"Building macOS app DMG for {domain} with version {new_version}...")
    try:
        # Run the bash build script
        result = subprocess.run(
            [build_script, domain, new_version],
            check=True,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            text=True
        )
        print(result.stdout)
    except subprocess.CalledProcessError as e:
        print("Failed to build macOS DMG:")
        print(e.stdout)
        sys.exit(1)

    # The DMG path created by build_staff_dmg.sh is:
    # dgt_macos_app/build/dmg/KIUQ-SYSTEM-<new_version>.dmg
    built_dmg_path = os.path.join(app_root, f"dgt_macos_app/build/dmg/KIUQ-SYSTEM-{new_version}.dmg")
    dest_dmg_path = os.path.join(app_root, f"public/downloads/KIUQ-SYSTEM-{new_version}.dmg")

    if not os.path.exists(built_dmg_path):
        print(f"Error: Built DMG not found at {built_dmg_path}")
        sys.exit(1)

    # Copy DMG to public/downloads/
    print(f"Copying built DMG to public/downloads/...")
    os.makedirs(os.path.dirname(dest_dmg_path), exist_ok=True)
    shutil.copy2(built_dmg_path, dest_dmg_path)

    # 3. Update public/appcast/latest-mac.json
    print("Updating public/appcast/latest-mac.json...")
    appcast_data["version"] = new_version
    appcast_data["download_url"] = f"{domain}/downloads/KIUQ-SYSTEM-{new_version}.dmg"
    appcast_data["release_notes"] = f"Version {new_version} update."
    with open(appcast_path, "w") as f:
        json.dump(appcast_data, f, indent=2)

    # 4. Update routes/web.php
    web_route_path = os.path.join(app_root, "routes/web.php")
    print("Updating version in routes/web.php...")
    with open(web_route_path, "r") as f:
        web_content = f.read()

    # We want to replace $version = '...'; inside the mac-app/download route
    pattern_web = r"(Route::get\('/mac-app/download',\s*function\s*\(\)\s*\{[^}]*?\$version\s*=\s*')[^']+('\s*;)"
    if re.search(pattern_web, web_content):
        web_content = re.sub(pattern_web, rf"\g<1>{new_version}\g<2>", web_content)
        with open(web_route_path, "w") as f:
            f.write(web_content)
        print("Successfully updated routes/web.php")
    else:
        print("Warning: Could not automatically update version in routes/web.php. Please verify manually.")

    # 5. Update resources/views/downloads/mac-app.blade.php
    blade_path = os.path.join(app_root, "resources/views/downloads/mac-app.blade.php")
    print("Updating version in resources/views/downloads/mac-app.blade.php...")
    with open(blade_path, "r") as f:
        blade_content = f.read()

    pattern_blade_ver = r"(\$version\s*=\s*')[^']+('\s*;)"
    pattern_blade_url = r"(\$downloadUrl\s*=\s*asset\('downloads/KIUQ-SYSTEM-)[^']+(\.dmg'\)\s*;)"

    if re.search(pattern_blade_ver, blade_content) and re.search(pattern_blade_url, blade_content):
        blade_content = re.sub(pattern_blade_ver, rf"\g<1>{new_version}\g<2>", blade_content)
        blade_content = re.sub(pattern_blade_url, rf"\g<1>{new_version}\g<2>", blade_content)
        with open(blade_path, "w") as f:
            f.write(blade_content)
        print("Successfully updated mac-app.blade.php")
    else:
        print("Warning: Could not automatically update version in mac-app.blade.php. Please verify manually.")

    # 6. Upload files to Hostinger
    print("Uploading updated files to Hostinger...")

    def run_cmd_pty(args, password="KhmerLucky#2888"):
        print(f"Running: {' '.join(args)}")
        import pty
        pid, fd = pty.fork()
        if pid == 0:
            try:
                os.execvp(args[0], args)
            except Exception as e:
                print(f"Exec failed: {e}")
                sys.exit(1)
        else:
            output = b""
            password_sent = False
            while True:
                try:
                    data = os.read(fd, 1024)
                    if not data:
                        break
                    output += data
                    # Stream output to terminal
                    sys.stdout.write(data.decode("utf-8", "replace"))
                    sys.stdout.flush()
                    
                    if b"password:" in data.lower() and not password_sent:
                        os.write(fd, f"{password}\n".encode())
                        password_sent = True
                except OSError:
                    break
            _, status = os.waitpid(pid, 0)
            if status != 0:
                print(f"Command failed with exit status {status}")
                sys.exit(1)

    # Upload the new DMG, appcast, web.php, and blade template
    rsync_cmd = [
        "rsync", "-avzR", "-e", "ssh -o StrictHostKeyChecking=no -p 65002",
        f"public/downloads/KIUQ-SYSTEM-{new_version}.dmg",
        "public/appcast/latest-mac.json",
        "routes/web.php",
        "resources/views/downloads/mac-app.blade.php",
        "u768808434@191.101.12.132:domains/rosybrown-baboon-228003.hostingersite.com/public_html/"
    ]
    run_cmd_pty(rsync_cmd)

    # 7. Clear caches
    print("Clearing server caches...")
    ssh_cmd = [
        "ssh", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u768808434@191.101.12.132",
        "cd domains/rosybrown-baboon-228003.hostingersite.com/public_html && rm -rf bootstrap/cache/*.php storage/framework/cache/data/* storage/framework/views/* || true"
    ]
    run_cmd_pty(ssh_cmd)

    print(f"\nSuccessfully deployed KIUQ SYSTEM version {new_version} to Hostinger!")

if __name__ == "__main__":
    main()
