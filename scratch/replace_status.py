import os
import re

files_to_update = [
    "app/Enums/CustomerQueue.php",
    "app/Models/LeadFollowUp.php",
    "app/Http/Controllers/CRM/CrmDashboardController.php",
    "app/Http/Controllers/CRM/WebsiteCrmController.php",
    "app/Http/Controllers/CRM/ShipmentController.php",
    "app/Services/CrmReportService.php",
    "app/Services/TechSupportCaseService.php",
    "app/Services/UniversalStatusSyncService.php",
    "app/Services/CrmCustomerMatchService.php",
    "app/Services/ReportService.php",
    "app/Services/CrmService.php",
    "tests/Feature/WebsiteLeadProductsTest.php",
    "tests/Feature/TechSupportViewOnlyRestrictionTest.php",
    "tests/Feature/CrmDeletePermissionsTest.php",
    "tests/Feature/ShipmentProblemPropagationTest.php",
    "tests/Feature/WebsiteCrmVisibilityTest.php",
    "tests/Feature/CrmTeamNotifierDiagTest.php",
    "tests/Feature/TechSupportTest.php",
    "tests/Feature/EbayRecordDeleteCascadesCustomerTest.php",
    "tests/Feature/LeadDeleteCascadesCustomerTest.php",
    "tests/Feature/CrmStaffReportTest.php",
    "tests/Feature/WebsiteLeadReassignmentNotificationTest.php",
    "tests/Feature/UnifiedCustomerDirectoryTest.php",
    "database/seeders/CrmSeeder.php",
    "database/factories/LeadFactory.php",
    "resources/views/crm/website/show.blade.php",
    "resources/views/crm/website/index.blade.php"
]

for file_path in files_to_update:
    if not os.path.exists(file_path): continue
    with open(file_path, 'r') as f:
        content = f.read()
    
    # Simple replacements
    content = content.replace("WebsiteLeadStatus::", "CustomerStatus::")
    content = content.replace("use App\\Enums\\WebsiteLeadStatus;", "use App\\Enums\\CustomerStatus;")
    
    # Old statuses to new statuses (based on old WebsiteLeadStatus case names)
    content = content.replace("CustomerStatus::NewLead", "CustomerStatus::NewOrder")
    content = content.replace("CustomerStatus::TechnicalSupport", "CustomerStatus::TechnicalIssues")
    content = content.replace("CustomerStatus::Resolved", "CustomerStatus::Resolve")
    content = content.replace("CustomerStatus::Successful", "CustomerStatus::SuccessfulLead")
    content = content.replace("CustomerStatus::InDelivery", "CustomerStatus::PendingDelivery")
    content = content.replace("CustomerStatus::DelayedShipment", "CustomerStatus::PendingDelivery")
    content = content.replace("CustomerStatus::MachineReturn", "CustomerStatus::PotentialReturn")
    content = content.replace("CustomerStatus::Lost", "CustomerStatus::LostInterest")
    content = content.replace("CustomerStatus::TechInProgress", "CustomerStatus::TechnicalIssues")
    content = content.replace("CustomerStatus::TechRedCase", "CustomerStatus::PotentialReturn")
    content = content.replace("CustomerStatus::InTransit", "CustomerStatus::Loaded")
    
    with open(file_path, 'w') as f:
        f.write(content)
print("Replacement script finished.")
