import re

file_path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/resources/views/websites/index.blade.php'
with open(file_path, 'r') as f:
    content = f.read()

# Pattern for QC Approve form -> button
content = re.sub(
    r'<form action="/websites/\{\{ \$website->id \}\}/approve-qc" method="POST" class="flex-1 flex" data-no-processing>\s*@csrf\s*<button type="submit" class="([^"]+) w-full text-center justify-center">\s*✓ QC Approve\s*</button>\s*</form>',
    r'''<button type="button"
                            @click="openQcModal({{ $website->id }}, '{{ addslashes($website->name) }}')"
                            class="\1">
                        ✓ QC Approve
                    </button>''',
    content
)

# Pattern for QC Error form -> button
content = re.sub(
    r'<form action="/websites/\{\{ \$website->id \}\}/qc-error" method="POST" class="flex flex-none" data-no-processing>\s*@csrf\s*<button type="submit" class="([^"]+) text-center justify-center">\s*✗ QC Error\s*</button>\s*</form>',
    r'''<button type="button"
                            @click="openQcErrorModal({{ $website->id }}, '{{ addslashes($website->name) }}')"
                            class="\1">
                        ✗ QC Error
                    </button>''',
    content
)

# Pattern for Supervisor Approve form -> button
content = re.sub(
    r'<form action="/websites/\{\{ \$website->id \}\}/approve-supervisor" method="POST" class="flex-1 flex" data-no-processing>\s*@csrf\s*<button type="submit" class="([^"]+) w-full text-center justify-center">\s*✓ Supervisor Approve\s*</button>\s*</form>',
    r'''<button type="button"
                            @click="openSupervisorModal({{ $website->id }}, '{{ addslashes($website->name) }}')"
                            class="\1">
                        ✓ Supervisor Approve
                    </button>''',
    content
)

# Pattern for Supervisor Error form -> button
content = re.sub(
    r'<form action="/websites/\{\{ \$website->id \}\}/supervisor-error" method="POST" class="flex flex-none" data-no-processing>\s*@csrf\s*<button type="submit" class="([^"]+) text-center justify-center">\s*✗ Sup\. Error\s*</button>\s*</form>',
    r'''<button type="button"
                            @click="openSupervisorErrorModal({{ $website->id }}, '{{ addslashes($website->name) }}')"
                            class="\1">
                        ✗ Sup. Error
                    </button>''',
    content
)

# ALSO: Now modify the modals to add data-no-processing to their forms, and instantly hide on submit!
content = content.replace(
    '''<form :action="qcModalAction" method="POST" class="p-5 space-y-4">''',
    '''<form :action="qcModalAction" method="POST" class="p-5 space-y-4" data-no-processing @submit="showQcModal = false">'''
)
content = content.replace(
    '''<form :action="qcErrorModalAction" method="POST" class="p-5 space-y-4">''',
    '''<form :action="qcErrorModalAction" method="POST" class="p-5 space-y-4" data-no-processing @submit="showQcErrorModal = false">'''
)
content = content.replace(
    '''<form :action="supervisorModalAction" method="POST" class="p-5 space-y-4">''',
    '''<form :action="supervisorModalAction" method="POST" class="p-5 space-y-4" data-no-processing @submit="showSupervisorModal = false">'''
)
content = content.replace(
    '''<form :action="supervisorErrorModalAction" method="POST" class="p-5 space-y-4">''',
    '''<form :action="supervisorErrorModalAction" method="POST" class="p-5 space-y-4" data-no-processing @submit="showSupervisorErrorModal = false">'''
)

with open(file_path, 'w') as f:
    f.write(content)

print("Reverted forms to buttons and updated modals.")
