import re

file_path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/resources/views/websites/index.blade.php'
with open(file_path, 'r') as f:
    content = f.read()

replacement = """
            try {
                const response = await fetch(`/websites/${websiteId}/history-logs/comment`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'), 'Accept': 'application/json' },
                    body: formData
                });
                if (response.ok) {
                    const result = await response.json();
                    if (result.success) {
                        this.showHistoryModal = false;
                        window.location.reload();
                    } else {
                        alert("Error: " + result.message);
                    }
                } else {
                    const result = await response.json().catch(() => ({}));
                    alert("Error: " + (result.message || 'Failed to add comment'));
                    console.error('Failed to add comment');
                }
            } catch (error) {
                alert("Network Error: " + error.message);
                console.error(error);
            } finally {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
"""

# Replace the try block inside submitHistoryComment
content = re.sub(r'try\s*\{.*?(?=finally\s*\{)', replacement.replace("finally {", "").strip() + "\n            ", content, flags=re.DOTALL)

with open(file_path, 'w') as f:
    f.write(content)
