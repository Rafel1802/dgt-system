import re

file_path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/routes/web.php'
with open(file_path, 'r') as f:
    content = f.read()

# Add the comment route under history
content = content.replace(
    "Route::get('/websites/{website}/history', [\App\Http\Controllers\WebsiteController::class, 'getHistory'])->name('websites.history');",
    "Route::get('/websites/{website}/history', [\App\Http\Controllers\WebsiteController::class, 'getHistory'])->name('websites.history');\n            Route::post('/websites/{website}/history-logs/comment', [\App\Http\Controllers\WebsiteController::class, 'addHistoryComment'])->name('websites.history-logs.comment.store');"
)

with open(file_path, 'w') as f:
    f.write(content)
