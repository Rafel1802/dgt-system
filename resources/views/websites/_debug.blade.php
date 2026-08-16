<script>
setTimeout(() => {
    const el = document.querySelector('[x-data="websitesApp()"]');
    if (el) {
        const data = Alpine.$data(el);
        console.error("ALPINE DATA KEYS:", Object.keys(data));
        console.error("HAS historyEditNote:", 'historyEditNote' in data);
    }
}, 2000);
</script>
