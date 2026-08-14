import re

file_path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/resources/views/websites/index.blade.php'
with open(file_path, 'r') as f:
    content = f.read()

# 1. Add the Alpine.js submit handler
alpine_method = '''
        async optimisticSubmit(e, websiteIdProperty, modalProperty) {
            const form = e.target;
            const action = form.action;
            const formData = new FormData(form);
            const websiteId = this[websiteIdProperty];
            const submitBtn = form.querySelector('button[type="submit"]');
            
            // 1. Hide modal immediately
            this[modalProperty] = false;
            
            // 2. Hide card immediately (optimistic UI)
            const cardEl = document.getElementById('website-card-' + websiteId);
            if (cardEl) {
                cardEl.style.transition = 'all 0.3s ease';
                cardEl.style.opacity = '0.3';
                cardEl.style.pointerEvents = 'none';
                cardEl.style.transform = 'scale(0.98)';
            }

            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = 'Processing...';
            submitBtn.disabled = true;

            try {
                const response = await fetch(action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                
                if (response.ok) {
                    if (window.Turbo) {
                        window.Turbo.visit(window.location.href, { action: 'replace' });
                    } else {
                        window.location.reload();
                    }
                } else {
                    window.showToast('Action failed.', 'error');
                    if (cardEl) {
                        cardEl.style.opacity = '1';
                        cardEl.style.pointerEvents = 'auto';
                        cardEl.style.transform = 'none';
                    }
                }
            } catch (err) {
                window.showToast('Network error.', 'error');
                if (cardEl) {
                    cardEl.style.opacity = '1';
                    cardEl.style.pointerEvents = 'auto';
                    cardEl.style.transform = 'none';
                }
            } finally {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                form.reset();
            }
        },'''

# Insert it before showQcModal: false
content = content.replace('showQcModal:          false,', alpine_method + '\n        showQcModal:          false,')


# 2. Update QC Modal Form
content = re.sub(
    r'<form :action="qcModalAction" method="POST" class="p-5 space-y-4" data-no-processing="true">',
    r'<form :action="qcModalAction" method="POST" class="p-5 space-y-4" data-no-processing="true" @submit.prevent="optimisticSubmit($event, \'qcModalWebsiteId\', \'showQcModal\')">',
    content
)

# 3. Update QC Error Modal Form
content = re.sub(
    r'<form :action="qcErrorModalAction" method="POST" class="p-5 space-y-4" data-no-processing="true">',
    r'<form :action="qcErrorModalAction" method="POST" class="p-5 space-y-4" data-no-processing="true" @submit.prevent="optimisticSubmit($event, \'qcErrorModalWebsiteId\', \'showQcErrorModal\')">',
    content
)

# 4. Update Supervisor Modal Form
content = re.sub(
    r'<form :action="supervisorModalAction" method="POST" class="p-5 space-y-4" data-no-processing="true">',
    r'<form :action="supervisorModalAction" method="POST" class="p-5 space-y-4" data-no-processing="true" @submit.prevent="optimisticSubmit($event, \'supervisorModalWebsiteId\', \'showSupervisorModal\')">',
    content
)

# 5. Update Supervisor Error Modal Form
content = re.sub(
    r'<form :action="supervisorErrorModalAction" method="POST" class="p-5 space-y-4" data-no-processing="true">',
    r'<form :action="supervisorErrorModalAction" method="POST" class="p-5 space-y-4" data-no-processing="true" @submit.prevent="optimisticSubmit($event, \'supervisorErrorModalWebsiteId\', \'showSupervisorErrorModal\')">',
    content
)

with open(file_path, 'w') as f:
    f.write(content)

print("Updated modals to use optimistic UI.")
