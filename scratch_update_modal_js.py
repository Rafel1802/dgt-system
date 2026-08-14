import re

file_path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/resources/views/websites/index.blade.php'
with open(file_path, 'r') as f:
    content = f.read()

# Update openQcErrorModal to set the Website ID
content = re.sub(
    r'openQcErrorModal\(websiteId, websiteName\) \{([\s\n]+)this\.qcErrorModalName',
    r'openQcErrorModal(websiteId, websiteName) {\1this.qcErrorModalWebsiteId = websiteId;\1this.qcErrorModalName',
    content
)

# Update openSupervisorErrorModal to set the Website ID
content = re.sub(
    r'openSupervisorErrorModal\(websiteId, websiteName\) \{([\s\n]+)this\.supervisorErrorModalName',
    r'openSupervisorErrorModal(websiteId, websiteName) {\1this.supervisorErrorModalWebsiteId = websiteId;\1this.supervisorErrorModalName',
    content
)

# Ensure the properties exist in the Alpine component definition
content = re.sub(
    r'qcErrorModalName: \'\',',
    r'qcErrorModalName: \'\',\n        qcErrorModalWebsiteId: null,',
    content
)

content = re.sub(
    r'supervisorErrorModalName: \'\',',
    r'supervisorErrorModalName: \'\',\n        supervisorErrorModalWebsiteId: null,',
    content
)

with open(file_path, 'w') as f:
    f.write(content)
