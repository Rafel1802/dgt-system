(function() {
    const boardWrap = document.getElementById('board-wrap');
    if (!boardWrap) return;

    let isDown = false;
    let startX, scrollLeft, startY, scrollTop;

    boardWrap.addEventListener('pointerdown', (e) => {
        if (e.target.closest('.kanban-card') || e.target.closest('button') || 
            e.target.closest('.list-header') || e.target.closest('input') || 
            e.target.closest('textarea') || e.target.closest('.trello-card-modal')) {
            return;
        }

        isDown = true;
        boardWrap.style.cursor = 'grabbing';
        
        // Safari/WKWebView fix: Disable scroll-snap and smooth scrolling while dragging
        boardWrap.style.scrollSnapType = 'none';
        boardWrap.style.scrollBehavior = 'auto';
        
        startX = e.pageX - boardWrap.offsetLeft;
        startY = e.pageY - boardWrap.offsetTop;
        scrollLeft = boardWrap.scrollLeft;
        scrollTop = boardWrap.scrollTop;
        
        boardWrap.setPointerCapture(e.pointerId);
    });

    const stopDragging = (e) => {
        if (!isDown) return;
        isDown = false;
        boardWrap.style.cursor = '';
        
        // Restore CSS rules
        boardWrap.style.scrollSnapType = '';
        boardWrap.style.scrollBehavior = '';
        
        if (e && e.pointerId) {
            boardWrap.releasePointerCapture(e.pointerId);
        }
    };

    boardWrap.addEventListener('pointerleave', stopDragging);
    boardWrap.addEventListener('pointerup', stopDragging);
    boardWrap.addEventListener('pointercancel', stopDragging);

    boardWrap.addEventListener('pointermove', (e) => {
        if (!isDown) return;
        e.preventDefault(); 
        
        const x = e.pageX - boardWrap.offsetLeft;
        const y = e.pageY - boardWrap.offsetTop;
        
        const walkX = (x - startX) * 1.25; 
        const walkY = (y - startY) * 1.25;
        
        boardWrap.scrollLeft = scrollLeft - walkX;
        boardWrap.scrollTop = scrollTop - walkY;
    });
})();
