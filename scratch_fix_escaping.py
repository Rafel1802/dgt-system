import re

file_path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/resources/views/websites/index.blade.php'
with open(file_path, 'r') as f:
    content = f.read()

# Fix the backslash from previous run
content = content.replace(r"\'error_files\'", "'error_files'")

# Add paste to the second error note
content = re.sub(
    r'name="error_note" rows="3" required minlength="5"([\s\n]+)class="form-textarea w-full',
    r'name="error_note" rows="3" required minlength="5" @paste="handlePaste($event, \'error_files\')"\1class="form-textarea w-full',
    content
)

# Fix backslash again just in case the regex above added it
content = content.replace(r"\'error_files\'", "'error_files'")

with open(file_path, 'w') as f:
    f.write(content)
