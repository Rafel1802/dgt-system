<script>
(function() {
    const initLiveUpdater = () => {
        if (!window.kiuqGetPusherClient) {
            setTimeout(initLiveUpdater, 100);
            return;
        }
        
        const pusher = window.kiuqGetPusherClient();
        if (!pusher) {
            setTimeout(initLiveUpdater, 100);
            return;
        }
        
        const channelName = 'private-crm.customer.{{ $type }}.{{ $id }}';
        
        // Prevent double binding if user navigates back and forth via Turbo
        if (window[`__kiuq_live_bound_${channelName}`]) return;
        window[`__kiuq_live_bound_${channelName}`] = true;

        const channel = pusher.subscribe(channelName);
        
        const doHotSwap = () => {
            const url = new URL(window.location.href);
            url.searchParams.set('_t', Date.now());
            fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(response => response.text())
                .then(html => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    
                    const cardsToSwap = [
                        '#client-summary-card',
                        '#pipeline-progress-card',
                        '#status-history-card',
                        '#followup-timeline',
                        '#handled-by-card',
                        '#crm-notes-section',
                        '#order-history-card',
                        '#technical-support-section',
                        '#logistics-section',
                        '#customer-interactions-list'
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
                .catch(err => console.error('Failed to hot-swap UI:', err));
        };

        const handleStatusLive = (data) => {
            if (window.showToast) {
                window.showToast(`Status updated to "${data.newStatusLabel}" by ${data.teamName} (${data.actorName})`, 'info');
            }
            doHotSwap();
        };

        const handleHandlerLive = (data) => {
            if (window.showToast) {
                window.showToast(`Handler updated to "${data.newHandlerName}" by ${data.teamName} (${data.actorName})`, 'info');
            }
            doHotSwap();
        };

        const handleDataLive = (data) => {
            if (window.showToast) {
                window.showToast(data.message || `Data updated by ${data.teamName} (${data.actorName})`, 'info');
            }
            doHotSwap();
        };

        pusher.bind_global((eventName, data) => {
            const name = String(eventName);
            if (name.includes('pusher_internal:')) return;
            
            if (name.includes('CustomerStatusUpdatedLive')) {
                handleStatusLive(data);
            } else if (name.includes('CustomerHandlerUpdatedLive')) {
                handleHandlerLive(data);
            } else if (name.includes('CustomerDataUpdatedLive')) {
                handleDataLive(data);
            }
        });

        channel.bind('App\\Events\\CustomerStatusUpdatedLive', handleStatusLive);
        channel.bind('CustomerStatusUpdatedLive', handleStatusLive);
        channel.bind('.CustomerStatusUpdatedLive', handleStatusLive);

        channel.bind('App\\Events\\CustomerHandlerUpdatedLive', handleHandlerLive);
        channel.bind('CustomerHandlerUpdatedLive', handleHandlerLive);
        channel.bind('.CustomerHandlerUpdatedLive', handleHandlerLive);

        channel.bind('App\\Events\\CustomerDataUpdatedLive', handleDataLive);
        channel.bind('CustomerDataUpdatedLive', handleDataLive);
        channel.bind('.CustomerDataUpdatedLive', handleDataLive);

        document.addEventListener('ajax-success', (e) => {
            doHotSwap();
        });
    };

    // Initialize
    initLiveUpdater();
})();
</script>
