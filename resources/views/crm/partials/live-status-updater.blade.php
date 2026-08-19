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
                
                // Extract and replace the client summary card
                const newClientCard = doc.querySelector('.card.relative.overflow-hidden');
                const oldClientCard = document.querySelector('.card.relative.overflow-hidden');
                
                if (newClientCard && oldClientCard) {
                    oldClientCard.innerHTML = newClientCard.innerHTML;
                }
                
                // Extract and replace the pipeline/status card
                // The status card usually has the title "CUSTOMER STATUS"
                const newPipelineCards = Array.from(doc.querySelectorAll('.card'));
                const oldPipelineCards = Array.from(document.querySelectorAll('.card'));
                
                const newPipelineCard = newPipelineCards.find(c => c.innerHTML.includes('CUSTOMER STATUS'));
                const oldPipelineCard = oldPipelineCards.find(c => c.innerHTML.includes('CUSTOMER STATUS'));
                
                if (newPipelineCard && oldPipelineCard) {
                    oldPipelineCard.innerHTML = newPipelineCard.innerHTML;
                }
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
                
                const newCards = Array.from(doc.querySelectorAll('.card'));
                const oldCards = Array.from(document.querySelectorAll('.card'));
                
                const newHandledByCard = newCards.find(c => c.innerHTML.includes('Handled-by History') || c.innerHTML.includes('Handled-By History'));
                const oldHandledByCard = oldCards.find(c => c.innerHTML.includes('Handled-by History') || c.innerHTML.includes('Handled-By History'));
                
                if (newHandledByCard && oldHandledByCard) {
                    oldHandledByCard.innerHTML = newHandledByCard.innerHTML;
                }
            })
            .catch(err => console.error('Failed to hot-swap handler UI:', err));
    });
});
</script>
