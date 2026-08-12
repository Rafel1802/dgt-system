import re

with open('resources/views/websites/index.blade.php', 'r') as f:
    content = f.read()

def replacer(match):
    # match.group(1) is the variable name, e.g., 'showDeleteClassModal'
    # we convert camelCase to kebab-case
    var_name = match.group(1)
    
    # Handle complex expression like `showAttachmentPreview && !previewIsImage`
    if '&&' in var_name or '!' in var_name or ' ' in var_name:
        var_name = var_name.split()[0].replace('!', '')
        
    # kebab-case conversion
    kebab_name = re.sub(r'(?<!^)(?=[A-Z])', '-', var_name).lower()
    if not kebab_name.endswith('-modal'):
        kebab_name += '-modal'
    
    # Check if it already has data-turbo-permanent
    if 'data-turbo-permanent' in match.group(0):
        return match.group(0)

    # Reconstruct the div
    return f'<div id="{kebab_name}" data-turbo-permanent {match.group(0)[5:]}'

# regex to find `<div x-show="somethingModal"` or similar modals
# that have `fixed inset-0` to ensure they are modals
content = re.sub(r'<div x-show="([^"]+)"[^>]*fixed inset-0[^>]*>', replacer, content)

with open('resources/views/websites/index.blade.php', 'w') as f:
    f.write(content)

print("Fixed modals")
