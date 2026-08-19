import os

files_to_update = []
for root, dirs, files in os.walk('tests'):
    for file in files:
        if file.endswith('.php'): files_to_update.append(os.path.join(root, file))

for file_path in files_to_update:
    with open(file_path, 'r') as f:
        content = f.read()
    
    modified = False
    
    if "WebsiteLeadStatus::TechnicalSupport" in content:
        content = content.replace("WebsiteLeadStatus::TechnicalSupport", "WebsiteLeadStatus::TechnicalIssues")
        modified = True
        
    if "WebsiteLeadStatus::SuccessfulLeadLead" in content:
        content = content.replace("WebsiteLeadStatus::SuccessfulLeadLead", "WebsiteLeadStatus::SuccessfulLead")
        modified = True
        
    if '"new_lead"' in content:
        content = content.replace('"new_lead"', '"new_inquiry"')
        modified = True
        
    if "'new_lead'" in content:
        content = content.replace("'new_lead'", "'new_inquiry'")
        modified = True

    if modified:
        with open(file_path, 'w') as f:
            f.write(content)
print("Test fixes applied.")
