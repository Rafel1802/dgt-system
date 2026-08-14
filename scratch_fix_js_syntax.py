import re

file_path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/resources/views/websites/index.blade.php'
with open(file_path, 'r') as f:
    content = f.read()

# Fix the literal backslashes
content = content.replace("qcErrorModalName: \\'\\',", "qcErrorModalName: '',")
content = content.replace("supervisorErrorModalName: \\'\\',", "supervisorErrorModalName: '',")

with open(file_path, 'w') as f:
    f.write(content)
