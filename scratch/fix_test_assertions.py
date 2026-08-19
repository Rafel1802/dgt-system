import os

files_to_update = []
for root, dirs, files in os.walk('tests'):
    for file in files:
        if file.endswith('.php'): files_to_update.append(os.path.join(root, file))

replacements = {
    "'Resolved'": "'Resolve'",
    '"Resolved"': '"Resolve"',
    "'New Lead'": "'New Inquiry'",
    '"New Lead"': '"New Inquiry"',
    "'Technical Support'": "'Technical Issues'",
    '"Technical Support"': '"Technical Issues"',
}

for file_path in files_to_update:
    with open(file_path, 'r') as f:
        content = f.read()
    
    modified = False
    for old, new in replacements.items():
        if old in content:
            content = content.replace(old, new)
            modified = True
            
    if modified:
        with open(file_path, 'w') as f:
            f.write(content)
print("Test assertion strings replaced.")
