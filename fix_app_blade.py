import re

with open('resources/views/layouts/app.blade.php', 'r') as f:
    content = f.read()

# 1. Add [x-cloak] to styles
if '[x-cloak]' not in content:
    content = content.replace(
        '<style>',
        '<style>\n        [x-cloak] { display: none !important; }\n'
    )

# 2. Add turbo:submit-end listener
submit_end_code = """
            // Revert submit buttons when Turbo completes the request
            document.addEventListener('turbo:submit-end', function(e) {
                const form = e.target;
                const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
                submitButtons.forEach(button => {
                    if (button.dataset.originalHtml) {
                        button.disabled = false;
                        button.classList.remove('opacity-75', 'cursor-not-allowed');
                        button.innerHTML = button.dataset.originalHtml;
                        delete button.dataset.originalHtml;
                    }
                });
            });
"""

if 'turbo:submit-end' not in content:
    content = content.replace(
        "// Store current path and scroll position",
        submit_end_code + "\n                // Store current path and scroll position"
    )

with open('resources/views/layouts/app.blade.php', 'w') as f:
    f.write(content)

print("Fixed app.blade.php")
