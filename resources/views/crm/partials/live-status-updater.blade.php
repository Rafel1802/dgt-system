<script>
document.addEventListener('DOMContentLoaded', () => {
    if (!window.kiuqGetPusherClient) return;
    
    const pusher = window.kiuqGetPusherClient();
    if (!pusher) return;
    
    const channelName = 'private-crm.customer.{{ $type }}.{{ $id }}';
    const channel = pusher.subscribe(channelName);
    
    channel.bind('CustomerStatusUpdatedLive', function(data) {
        console.log('CustomerStatusUpdatedLive received:', data);
        
        if (window.Alpine && window.Alpine.store('toast')) {
             window.Alpine.store('toast').show(
                 `Status updated to "${data.newStatusLabel}" by ${data.teamName} (${data.actorName})`, 
                 'info'
             );
        }

        // Silent fetch and DOM replacement
        fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                
                const cardsToSwap = [
                    '#client-summary-card',
                    '#pipeline-progress-card',
                    '#status-history-card',
                    '#followup-timeline',
                    '#handled-by-card'
                ];
                
                cardsToSwap.forEach(selector => {
                    const newEl = doc.querySelector(selector);
                    const oldEl = document.querySelector(selector);
                    if (newEl && oldEl) {
                        oldEl.innerHTML = newEl.innerHTML;
                        oldEl.className = newEl.className;
                    }
                });
            })
            .catch(err => console.error('Failed to hot-swap status UI:', err));
    });
    
    channel.bind('CustomerHandlerUpdatedLive', function(data) {
        console.log('CustomerHandlerUpdatedLive received:', data);
        
        if (window.Alpine && window.Alpine.store('toast')) {
             window.Alpine.store('toast').show(
                 `Handler updated to "${data.newHandlerName}" by ${data.teamName} (${data.actorName})`, 
                 'info'
             );
        }

        // Silent fetch and DOM replacement
        fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                
                const cardsToSwap = [
                    '#client-summary-card',
                    '#pipeline-progress-card',
                    '#status-history-card',
                    '#followup-timeline',
                    '#handled-by-card'
                ];
                
                cardsToSwap.forEach(selector => {
                    const newEl = doc.querySelector(selector);
                    const oldEl = document.querySelector(selector);
                    if (newEl && oldEl) {
                        oldEl.innerHTML = newEl.innerHTML;
                        oldEl.className = newEl.className;
                    }
                });
            })
            .catch(err => console.error('Failed to hot-swap handler UI:', err));
    });
});
</script>
