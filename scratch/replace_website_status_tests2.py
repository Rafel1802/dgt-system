import os

files_to_update = []
for root, dirs, files in os.walk('tests'):
    for file in files:
        if file.endswith('.php'): files_to_update.append(os.path.join(root, file))
for root, dirs, files in os.walk('database'):
    for file in files:
        if file.endswith('.php'): files_to_update.append(os.path.join(root, file))

replacements = {
    # String values
    "'new_lead'": "'new_inquiry'",
    '"new_lead"': '"new_inquiry"',
    "'technical_support'": "'technical_issues'",
    '"technical_support"': '"technical_issues"',
    "'tech_in_progress'": "'technical_issues'",
    '"tech_in_progress"': '"technical_issues"',
    "'tech_red_case'": "'potential_return'",
    '"tech_red_case"': '"potential_return"',
    "'machine_return'": "'approve_return'",
    '"machine_return"': '"approve_return"',
    "'resolved'": "'resolve'",
    '"resolved"': '"resolve"',
    "'successful'": "'successful_lead'",
    '"successful"': '"successful_lead"',
    "'in_delivery'": "'pending_delivery'",
    '"in_delivery"': '"pending_delivery"',
    "'delayed_shipment'": "'pending_delivery'",
    '"delayed_shipment"': '"pending_delivery"',
    "'in_transit'": "'loaded'",
    '"in_transit"': '"loaded"',
    "'lost'": "'lost_interest'",
    '"lost"': '"lost_interest"',
    "'delivered'": "'delivered'", # No change needed, but added for safety
    '"delivered"': '"delivered"',

    # Enum Constants
    "WebsiteLeadStatus::NewLead": "WebsiteLeadStatus::NewInquiry",
    "WebsiteLeadStatus::TechnicalSupport": "WebsiteLeadStatus::TechnicalIssues",
    "WebsiteLeadStatus::TechInProgress": "WebsiteLeadStatus::TechnicalIssues",
    "WebsiteLeadStatus::TechRedCase": "WebsiteLeadStatus::PotentialReturn",
    "WebsiteLeadStatus::MachineReturn": "WebsiteLeadStatus::ApproveReturn",
    "WebsiteLeadStatus::Resolved": "WebsiteLeadStatus::Resolve",
    "WebsiteLeadStatus::Successful": "WebsiteLeadStatus::SuccessfulLead",
    "WebsiteLeadStatus::InDelivery": "WebsiteLeadStatus::PendingDelivery",
    "WebsiteLeadStatus::DelayedShipment": "WebsiteLeadStatus::PendingDelivery",
    "WebsiteLeadStatus::InTransit": "WebsiteLeadStatus::Loaded",
    "WebsiteLeadStatus::Lost": "WebsiteLeadStatus::LostInterest",
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
print("Tests/Factories string Replacement script finished.")
