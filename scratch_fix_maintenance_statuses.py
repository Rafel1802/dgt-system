import re

file_path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/app/Http/Controllers/WebsiteController.php'
with open(file_path, 'r') as f:
    content = f.read()

content = content.replace(
    "$isMaintenanceFlow = in_array($website->status, Website::MAINTENANCE_STATUSES);",
    """$isMaintenanceFlow = in_array($website->status, [
            Website::STATUS_MAINTENANCE,
            Website::STATUS_MAINTENANCE_PROGRESS,
            Website::STATUS_MAINTENANCE_QC_CHECKING,
            Website::STATUS_MAINTENANCE_SUPERVISOR_CHECKING,
            Website::STATUS_MAINTENANCE_QC_ERROR,
            Website::STATUS_MAINTENANCE_SUPERVISOR_ERROR,
        ]);"""
)

with open(file_path, 'w') as f:
    f.write(content)
