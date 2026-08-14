import re

file_path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/resources/views/websites/index.blade.php'
with open(file_path, 'r') as f:
    content = f.read()

# Fix form and button
content = content.replace(
    '<form @submit.prevent="submitHistoryComment($event, historyWebsiteId)" enctype="multipart/form-data" class="flex flex-col gap-2">',
    '<form @submit.prevent="submitHistoryComment($event, historyWebsiteId)" data-no-processing="true" enctype="multipart/form-data" class="flex flex-col gap-2">'
)
content = content.replace(
    '<button type="submit" class="btn text-sm bg-indigo-500 hover:bg-indigo-600 text-white font-bold px-4 py-1.5 rounded-xl shadow-md shadow-indigo-500/20 active:scale-95 transition-all">Post</button>',
    '<button type="submit" class="btn text-sm bg-indigo-500 hover:bg-indigo-600 text-white font-bold px-4 py-1.5 rounded-xl shadow-md shadow-indigo-500/20 active:scale-95 transition-all">Comment</button>'
)

with open(file_path, 'w') as f:
    f.write(content)
