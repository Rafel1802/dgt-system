import re

path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/resources/views/layouts/app.blade.php'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

# Upgrade wire:navigate to wire:navigate.hover
content = content.replace('<a wire:navigate href', '<a wire:navigate.hover href')

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Upgraded to wire:navigate.hover.")
