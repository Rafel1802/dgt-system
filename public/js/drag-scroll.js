(function() {
    function initDragScroll() {
        const boardWrap = document.getElementById('board-wrap');
        if (!boardWrap || boardWrap.dataset.dragScrollBound === 'true') return;
        boardWrap.dataset.dragScrollBound = 'true';

        let isDown = false;
        let startX, scrollLeft, startY, scrollTop;

        boardWrap.addEventListener('pointerdown', (e) => {
            // NEVER intercept touch or stylus pointers on mobile; allow 100% native hardware-accelerated touch scrolling
            if (e.pointerType === 'touch' || e.pointerType === 'pen') return;

            if (e.target.closest('.board-list') || e.target.closest('.list-cards') ||
                e.target.closest('.kanban-card') || e.target.closest('button') || 
                e.target.closest('.list-header') || e.target.closest('input') || 
                e.target.closest('textarea') || e.target.closest('.trello-card-modal') ||
                e.target.closest('.modal-content') || e.target.closest('a')) {
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
                try { boardWrap.releasePointerCapture(e.pointerId); } catch (_) {}
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
    }

    document.addEventListener('turbo:load', initDragScroll);
    document.addEventListener('DOMContentLoaded', initDragScroll);
    initDragScroll();
})();
