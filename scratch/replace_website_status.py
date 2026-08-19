import os

files_to_update = [
    "app/Http/Controllers/CRM/CrmDashboardController.php",
    "app/Http/Controllers/CRM/ShipmentController.php",
    "app/Http/Controllers/CRM/WebsiteCrmController.php",
    "app/Models/LeadFollowUp.php",
    "app/Services/CrmCustomerMatchService.php",
    "app/Services/CrmService.php",
    "app/Services/ReportService.php"
]

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
    if not os.path.exists(file_path): continue
    with open(file_path, 'r') as f:
        content = f.read()
    
    for old, new in replacements.items():
        content = content.replace(old, new)
        
    with open(file_path, 'w') as f:
        f.write(content)
print("WebsiteLeadStatus Replacement script finished.")
