import re

file_path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/resources/views/websites/index.blade.php'
with open(file_path, 'r') as f:
    content = f.read()

# Replace \\' with ' in openHistoryModal calls
content = re.sub(
    r"@click=\"openHistoryModal\(\{\{ \$website->id \}\}, \\\\'\{\{ addslashes\(\$website->name\) \}\}\\\\'",
    r'''@click="openHistoryModal({{ $website->id }}, '{{ addslashes($website->name) }}' ''',
    content
)

with open(file_path, 'w') as f:
    f.write(content)

print("Fixed backslash quotes.")
