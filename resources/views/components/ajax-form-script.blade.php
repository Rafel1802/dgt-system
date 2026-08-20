<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('ajaxForm', () => ({
        loading: false,
        submit(e) {
            this.loading = true;
            const form = e.target;
            const formData = new FormData(form);
            const method = form.method || 'POST';

            fetch(form.action, {
                method: method.toUpperCase() === 'GET' ? 'GET' : 'POST',
                body: method.toUpperCase() === 'GET' ? null : formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(async res => {
                this.loading = false;
                const contentType = res.headers.get("content-type");
                let data = {};
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    data = await res.json().catch(() => ({}));
                } else {
                    data = { message: 'Success' };
                }

                if (!res.ok) {
                    let msg = data.message || 'An error occurred';
                    if (data.errors) {
                        msg = Object.values(data.errors).flat().join('\n');
                    }
                    if (window.Alpine && window.Alpine.store('toast')) {
                        window.Alpine.store('toast').show(msg, 'error');
                    }
                } else {
                    if (data.message && window.Alpine && window.Alpine.store('toast')) {
                        window.Alpine.store('toast').show(data.message, 'success');
                    }
                    form.reset();
                    // Try to close modals if they exist in the component's scope
                    if (typeof this.showAddFollowUp !== 'undefined') this.showAddFollowUp = false;
                    if (typeof this.showAddOrder !== 'undefined') this.showAddOrder = false;
                    if (typeof this.showAddInteraction !== 'undefined') this.showAddInteraction = false;
                    
                    // Dispatch a custom event so specific forms can react if needed
                    form.dispatchEvent(new CustomEvent('ajax-success', { bubbles: true }));
                }
            })
            .catch(err => {
                this.loading = false;
                if (window.Alpine && window.Alpine.store('toast')) {
                    window.Alpine.store('toast').show('Network error', 'error');
                }
            });
        }
    }));
});
</script>
