import re
file_path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/resources/views/layouts/app.blade.php'
with open(file_path, 'r') as f:
    content = f.read()

# Fix AI Tools wrapper
content = content.replace('<span class="flex items-center gap-[0.625rem]">\n                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-[18px] h-[18px]">',
                          '<div class="flex items-center gap-[0.625rem]">\n                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-[18px] h-[18px]">')
content = content.replace('<span>AI Tools</span>\n                            </span>', '<span>AI Tools</span>\n                            </div>')

# Fix title= to data-tooltip= in AI tools and Web tools
content = re.sub(r'class="sidebar-submenu-item"\s*title="\{\{\s*\$tool\[\'label\'\]\s*\}\}"', r'class="sidebar-submenu-item" data-tooltip="{{ $tool[\'label\'] }}"', content)
content = re.sub(r'class="sidebar-item sidebar-tool-item sidebar-tool-item-web"\s*title="\{\{\s*\$tool\[\'label\'\]\s*\}\}"', r'class="sidebar-item sidebar-tool-item sidebar-tool-item-web" data-tooltip="{{ $tool[\'label\'] }}"', content)

with open(file_path, 'w') as f:
    f.write(content)
