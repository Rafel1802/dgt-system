import re

with open('/Applications/XAMPP/xamppfiles/htdocs/dgt-system/resources/views/websites/index.blade.php', 'r') as f:
    content = f.read()

def replacer(match):
    # match.group(0) is the entire <button ...>
    # We want to add " relative group" to the class attribute, 
    # and " aria-label='...'" instead of "title='...'"
    button_tag = match.group(1)
    
    # Extract title
    title_match = re.search(r'title="([^"]+)"', button_tag)
    if not title_match:
        return match.group(0)
    title = title_match.group(1)
    
    # Remove title attribute
    button_tag = re.sub(r'\s*title="[^"]+"', '', button_tag)
    
    # Ensure it has relative group in class
    if 'class="' in button_tag:
        # Avoid adding it twice if it already has it
        if ' group ' not in button_tag and '"group ' not in button_tag:
            button_tag = re.sub(r'class="([^"]*)"', r'class="\1 relative group"', button_tag, count=1)
    else:
        button_tag += ' class="relative group"'
        
    # Reconstruct the button tag with aria-label
    button_tag = f"{button_tag} aria-label=\"{title}\">"
    
    # The inner content is match.group(2)
    inner = match.group(2)
    
    # The tooltip HTML
    tooltip_html = f"""
    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-slate-800 text-white text-[10px] rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
        {title}
        <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-800"></div>
    </div>
"""
    
    # If the button already has a tooltip div, don't add another one
    if "absolute bottom-full left-1/2" in inner:
        return match.group(0)
        
    return f"{button_tag}{inner}{tooltip_html}</button>"

# Regex to match <button ...>...</button>
# This regex handles newlines within the button tag and content
new_content = re.sub(r'(<button[^>]+title="[^"]+"[^>]*>)(.*?)</button>', replacer, content, flags=re.DOTALL)

with open('/Applications/XAMPP/xamppfiles/htdocs/dgt-system/resources/views/websites/index.blade.php', 'w') as f:
    f.write(new_content)
    print("Done converting tooltips.")
