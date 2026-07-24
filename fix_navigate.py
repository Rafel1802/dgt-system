import re
import os

path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/resources/views/layouts/app.blade.php'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

# Add wire:navigate to all links with route(...)
content = re.sub(r'<a href="\{\{ route\(', r'<a wire:navigate href="{{ route(', content)
content = re.sub(r'<a href="\{\{ \$', r'<a wire:navigate href="{{ $', content)

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Added wire:navigate to links in app.blade.php.")
