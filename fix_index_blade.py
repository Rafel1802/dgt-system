import re

with open('resources/views/websites/index.blade.php', 'r') as f:
    content = f.read()

# Match any button that has title="..."
# We will find title="([^"]+)" inside any <button ...>
def replacer(match):
    full_button = match.group(0)
    
    # Extract title
    title_match = re.search(r'title="([^"]+)"', full_button)
    if not title_match:
        return full_button
    title = title_match.group(1)
    
    # Check if we already processed it (it has relative group or aria-label)
    if 'aria-label="' in full_button and 'relative group' in full_button:
        return full_button
        
    # Remove title
    new_button = re.sub(r'\s*title="[^"]+"', '', full_button)
    
    # Split into open tag and the rest
    # We find the first > that closes the button tag. Since there might be > inside attributes,
    # it's tricky. But for our buttons, usually the opening tag ends after the class="...".
    # Let's find the position of the last attribute before >
    open_tag_end = new_button.find('>')
    # Wait, if there's a > inside @click, find('>') will find it too early.
    # Better: regex to match the end of the opening tag.
    
    # Let's just do simple string replacements for the known button patterns!
    return full_button

# Actually, let's just do exact string replacements for the opening tags of the remaining buttons:
replacements = [
    (r'''class="btn btn-secondary text-xs py-1.5 px-2.5" title="Edit">''', 
     r'''class="btn btn-secondary text-xs py-1.5 px-2.5 relative group" aria-label="Edit">
    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-slate-800 text-white text-[10px] rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
        Edit
        <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-800"></div>
    </div>'''),
    
    (r'''class="btn btn-secondary text-xs py-1.5 px-2.5" title="View History">''', 
     r'''class="btn btn-secondary text-xs py-1.5 px-2.5 relative group" aria-label="View History">
    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-slate-800 text-white text-[10px] rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
        View History
        <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-800"></div>
    </div>'''),

    (r'''title="Move Up">''', 
     r'''class="relative group" aria-label="Move Up">
    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-slate-800 text-white text-[10px] rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
        Move Up
        <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-800"></div>
    </div>'''),
    
    (r'''title="Move Down">''', 
     r'''class="relative group" aria-label="Move Down">
    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-slate-800 text-white text-[10px] rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
        Move Down
        <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-800"></div>
    </div>'''),
    
    (r'''title="Approve QC">''', 
     r'''class="relative group" aria-label="Approve QC">
    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-slate-800 text-white text-[10px] rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
        Approve QC
        <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-800"></div>
    </div>'''),
    
    (r'''title="Drag to reorder">''', 
     r'''class="relative group" aria-label="Drag to reorder">
    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-slate-800 text-white text-[10px] rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
        Drag to reorder
        <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-800"></div>
    </div>'''),

    (r'''title="Save">''', 
     r'''class="relative group" aria-label="Save">
    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-slate-800 text-white text-[10px] rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
        Save
        <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-800"></div>
    </div>'''),
    
    (r'''title="Cancel">''', 
     r'''class="relative group" aria-label="Cancel">
    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-slate-800 text-white text-[10px] rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
        Cancel
        <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-800"></div>
    </div>'''),
    
    (r'''title="Edit Class">''', 
     r'''class="relative group" aria-label="Edit Class">
    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-slate-800 text-white text-[10px] rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
        Edit Class
        <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-800"></div>
    </div>'''),

    (r'''title="Remove Class">''', 
     r'''class="relative group" aria-label="Remove Class">
    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-slate-800 text-white text-[10px] rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
        Remove Class
        <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-800"></div>
    </div>'''),
    
    (r'''title="Edit Role">''', 
     r'''class="relative group" aria-label="Edit Role">
    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-slate-800 text-white text-[10px] rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
        Edit Role
        <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-800"></div>
    </div>'''),
    
    (r'''title="Remove">''', 
     r'''class="relative group" aria-label="Remove">
    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-slate-800 text-white text-[10px] rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
        Remove
        <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-800"></div>
    </div>''')
]

for old, new in replacements:
    # Be careful not to replace things that already have relative group from my previous fix
    content = content.replace(old, new)

# Fix double class="relative group" if some had it added from the previous regex
content = content.replace('class="relative group" class="relative group"', 'class="relative group"')
# For Move Up/Down which had class="..." title="Move Up", they now have class="..." class="relative group". 
# Let's fix that by merging them
content = re.sub(r'class="([^"]+)" class="relative group"', r'class="\1 relative group"', content)
# And the Delete button from my first script:
# <button type="submit" class="btn btn-secondary text-xs py-1.5 px-2.5 text-rose-500 hover:text-white hover:bg-rose-500 hover:border-rose-500 relative group"  aria-label="Delete">
# That's perfectly fine.

with open('resources/views/websites/index.blade.php', 'w') as f:
    f.write(content)
print("Finished replacements!")
