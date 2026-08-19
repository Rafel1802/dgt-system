import os

files_to_update = []
for root, dirs, files in os.walk('database'):
    for file in files:
        if file.endswith('.php'): files_to_update.append(os.path.join(root, file))

replacements = {
    "WebsiteLeadStatus::NewLead": "WebsiteLeadStatus::NewInquiry",
    "WebsiteLeadStatus::Lost": "WebsiteLeadStatus::LostInterest",
    "WebsiteLeadStatus::Successful": "WebsiteLeadStatus::SuccessfulLead",
    "WebsiteLeadStatus::TechnicalSupport": "WebsiteLeadStatus::TechnicalIssues",
    "WebsiteLeadStatus::TechInProgress": "WebsiteLeadStatus::TechnicalIssues",
    "WebsiteLeadStatus::TechRedCase": "WebsiteLeadStatus::PotentialReturn",
    "WebsiteLeadStatus::MachineReturn": "WebsiteLeadStatus::ApproveReturn",
    "WebsiteLeadStatus::Resolved": "WebsiteLeadStatus::Resolve",
    "WebsiteLeadStatus::InDelivery": "WebsiteLeadStatus::PendingDelivery",
    "WebsiteLeadStatus::DelayedShipment": "WebsiteLeadStatus::PendingDelivery",
    "WebsiteLeadStatus::InTransit": "WebsiteLeadStatus::Loaded",
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
print("Seeders WebsiteLeadStatus Replacement script finished.")
