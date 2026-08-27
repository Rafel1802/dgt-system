import os
import glob
import re

base_dir = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/resources/views'

# Find all blade files
blade_files = glob.glob(os.path.join(base_dir, '**', '*.blade.php'), recursive=True)

for file_path in blade_files:
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    # Remove wire:navigate.hover and wire:navigate
    new_content = re.sub(r'\s*wire:navigate\.hover\s*', ' ', content)
    new_content = re.sub(r'\s*wire:navigate\s*', ' ', new_content)

    if new_content != content:
        with open(file_path, 'w', encoding='utf-8') as f:
            f.write(new_content)
        print(f"Updated {file_path}")
