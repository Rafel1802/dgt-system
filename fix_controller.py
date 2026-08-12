import re

with open('app/Http/Controllers/WebsiteController.php', 'r') as f:
    content = f.read()

# Change `'live'        => $allWebsites->where('status', Website::STATUS_LIVE)->count(),`
# to `'live'        => $allWebsites->filter(fn($w) => $w->isLiveOrMaintenance())->count(),`

content = content.replace(
    "'live'        => $allWebsites->where('status', Website::STATUS_LIVE)->count(),",
    "'live'        => $allWebsites->filter(fn($w) => $w->isLiveOrMaintenance())->count(),"
)

with open('app/Http/Controllers/WebsiteController.php', 'w') as f:
    f.write(content)

print("Fixed WebsiteController.php")
