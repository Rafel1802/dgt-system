import re

file_path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/resources/views/websites/index.blade.php'
with open(file_path, 'r') as f:
    content = f.read()

# Pattern for QC Approve button
content = re.sub(
    r'<button type="button"\s*@click="openQcModal\(\{\{ \$website->id \}\}, \'\{\{ addslashes\(\$website->name\) \}\}\'\)"\s*class="([^"]+)">\s*✓ QC Approve\s*</button>',
    r'''<form action="/websites/{{ $website->id }}/approve-qc" method="POST" class="flex-1 flex" data-no-processing>
    @csrf
    <button type="submit" class="\1 w-full text-center justify-center">
        ✓ QC Approve
    </button>
</form>''',
    content
)

# Pattern for QC Error button
content = re.sub(
    r'<button type="button"\s*@click="openQcErrorModal\(\{\{ \$website->id \}\}, \'\{\{ addslashes\(\$website->name\) \}\}\'\)"\s*class="([^"]+)">\s*✗ QC Error\s*</button>',
    r'''<form action="/websites/{{ $website->id }}/qc-error" method="POST" class="flex flex-none" data-no-processing>
    @csrf
    <button type="submit" class="\1 text-center justify-center">
        ✗ QC Error
    </button>
</form>''',
    content
)

# Pattern for Supervisor Approve button
content = re.sub(
    r'<button type="button"\s*@click="openSupervisorModal\(\{\{ \$website->id \}\}, \'\{\{ addslashes\(\$website->name\) \}\}\'\)"\s*class="([^"]+)">\s*✓ Supervisor Approve\s*</button>',
    r'''<form action="/websites/{{ $website->id }}/approve-supervisor" method="POST" class="flex-1 flex" data-no-processing>
    @csrf
    <button type="submit" class="\1 w-full text-center justify-center">
        ✓ Supervisor Approve
    </button>
</form>''',
    content
)

# Pattern for Supervisor Error button
content = re.sub(
    r'<button type="button"\s*@click="openSupervisorErrorModal\(\{\{ \$website->id \}\}, \'\{\{ addslashes\(\$website->name\) \}\}\'\)"\s*class="([^"]+)">\s*✗ Sup\. Error\s*</button>',
    r'''<form action="/websites/{{ $website->id }}/supervisor-error" method="POST" class="flex flex-none" data-no-processing>
    @csrf
    <button type="submit" class="\1 text-center justify-center">
        ✗ Sup. Error
    </button>
</form>''',
    content
)

with open(file_path, 'w') as f:
    f.write(content)

print("Updated buttons to forms.")
