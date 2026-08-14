import re

file_path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/resources/views/websites/index.blade.php'
with open(file_path, 'r') as f:
    content = f.read()

# Update QC Error Modal form
content = re.sub(
    r'<form :action="qcErrorModalAction" method="POST" enctype="multipart/form-data" class="p-5 space-y-4">',
    r'<form :action="qcErrorModalAction" method="POST" enctype="multipart/form-data" class="p-5 space-y-4" data-no-processing="true" @submit.prevent="optimisticSubmit($event, \'qcErrorModalWebsiteId\', \'showQcErrorModal\')">',
    content
)

# Update Supervisor Error Modal form
content = re.sub(
    r'<form :action="supervisorErrorModalAction" method="POST" enctype="multipart/form-data" class="p-5 space-y-4">',
    r'<form :action="supervisorErrorModalAction" method="POST" enctype="multipart/form-data" class="p-5 space-y-4" data-no-processing="true" @submit.prevent="optimisticSubmit($event, \'supervisorErrorModalWebsiteId\', \'showSupervisorErrorModal\')">',
    content
)

with open(file_path, 'w') as f:
    f.write(content)
