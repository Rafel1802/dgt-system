@extends('layouts.app')
@section('title', $board->name)

@push('head')
@if ($board->background_type === 'image' && $board->background_value)
    @php
        $preloadUrl = $board->background_value;
        if (str_starts_with($preloadUrl, '/public/')) {
            $preloadUrl = substr($preloadUrl, 7);
        }
        if (!filter_var($preloadUrl, FILTER_VALIDATE_URL)) {
            $preloadUrl = asset(ltrim($preloadUrl, '/'));
        }
    @endphp
    <link rel="preload" href="{{ str_replace('"', '\"', $preloadUrl) }}" as="image">
@endif
<meta name="turbo-visit-control" content="reload">
<meta name="turbo-cache-control" content="no-cache">
<!-- Quill Theme -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<style>
[x-cloak]{display:none!important}

/* Full Bleed Layout Overrides (scoped to board view) */
.page-content:has(.board-wrap) { padding: 0 !important; display: flex; flex-direction: column; }
.board-header-mobile { margin: 1rem 1rem 0; }
@media (min-width: 640px) { .board-header-mobile { margin: 1.5rem 1.5rem 0; } }
@media (min-width: 1024px) { .board-header-mobile { margin: 1.5rem 2.5rem 0; } }

.board-wrap{display:flex;gap:1rem;overflow-x:auto;padding:1rem 1rem 1rem 1.5rem;align-items:flex-start;min-height:calc(100vh - 64px);border-radius:0;box-shadow:none;flex:1;scroll-behavior:smooth;-webkit-overflow-scrolling:touch;overscroll-behavior-x:contain;}
@media (min-width: 1024px) { .board-wrap { padding: 1rem 1rem 1rem 2.5rem; } }
.board-list{flex-shrink:0;width:300px;background:#f1f5f9;border:1px solid rgba(255,255,255,.54);border-radius:12px;display:flex;flex-direction:column;max-height:calc(100vh - 180px);}
.list-lead-avatar{width:30px;height:30px;border-radius:9999px;object-fit:cover;flex-shrink:0;box-shadow:0 1px 3px rgba(0,0,0,.15);border:2px solid #ffffff;transition:transform .15s ease, box-shadow .15s ease;}
.list-lead-avatar:hover{transform:scale(1.08);box-shadow:0 3px 8px rgba(0,0,0,.22);}
[data-theme="dark"] .list-lead-avatar{border-color:#1e293b;box-shadow:0 1px 4px rgba(0,0,0,.45);}
[data-theme="neon"] .list-lead-avatar{border-color:#041232!important;box-shadow:0 0 8px rgba(0,180,255,.6)!important;}
[data-theme="neon"] .list-lead-avatar:hover{box-shadow:0 0 14px rgba(0,220,255,.9)!important;}
.list-header{padding:.75rem 1rem;font-weight:600;font-size:.875rem;color:#334155;display:flex;align-items:center;justify-content:space-between;cursor:pointer}
.list-cards{padding:.5rem;flex:1;overflow-y:auto;min-height:48px;-webkit-overflow-scrolling:touch;overscroll-behavior-y:contain;scrollbar-width:thin;}
/* Trello-Grade Smooth Drag and Drop */
body.is-dragging-card { user-select: none !important; -webkit-user-select: none !important; cursor: grabbing !important; }
body.is-dragging-card * { cursor: grabbing !important; }
.list-cards.drag-over{background:rgba(99,102,241,.09);border-radius:10px;outline:2px dashed rgba(99,102,241,.45);outline-offset:-2px}
.kanban-card{background:#fff;border:1px solid rgba(226,232,240,.92);border-radius:8px;padding:.75rem;margin-bottom:.5rem;box-shadow:0 1px 3px rgba(15,23,42,.06);cursor:grab;user-select:none;-webkit-user-select:none;position:relative;}
.kanban-card:hover{box-shadow:0 10px 25px rgba(16,185,129,.2), 0 0 0 1px #10b981;border-color:#10b981;}
.kanban-card:active{cursor:grabbing}
.kanban-card:hover .card-quick-btn{opacity:1}
/* Trello Ghost & Drag */
.sortable-ghost{opacity:.5!important;background:rgba(148,163,184,.2)!important;border:2px dashed #94a3b8!important;border-radius:8px!important}
.sortable-chosen{cursor:grabbing!important}
.sortable-drag{opacity:.95!important;box-shadow:0 14px 28px rgba(0,0,0,.2)!important;cursor:grabbing!important;z-index:99999!important}
.sortable-list-ghost{opacity:.55!important;background:rgba(148,163,184,.2)!important;border:2px dashed #94a3b8!important;border-radius:12px!important}
.sortable-list-drag{transform:rotate(1.5deg) scale(1.01)!important;box-shadow:0 25px 50px -12px rgba(0,0,0,.35)!important;opacity:.95!important;z-index:9999!important}
/* Block / Waiting List Circle Icon & Custom Popup Tooltip */
.block-fix-btn{width:24px;height:24px;border-radius:9999px;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .18s cubic-bezier(0.2,0,0,1);cursor:pointer}
.block-fix-btn:hover{transform:scale(1.12)}
.block-fix-btn:active{transform:scale(0.92)}
.block-fix-tooltip{position:absolute;right:calc(100% + 9px);top:50%;transform:translateY(-50%) translateX(6px);opacity:0;visibility:hidden;pointer-events:none;z-index:1000;white-space:nowrap;display:flex;align-items:center;gap:7px;padding:6px 11px;border-radius:9px;font-size:11px;font-weight:700;letter-spacing:.02em;background:#0f172a;color:#f8fafc;box-shadow:0 10px 25px -3px rgba(0,0,0,.35),0 4px 6px -4px rgba(0,0,0,.2);border:1px solid rgba(255,255,255,.14);transition:opacity .16s cubic-bezier(0.16,1,0.3,1),transform .16s cubic-bezier(0.16,1,0.3,1),visibility .16s;transform:translateZ(0)}
.block-fix-tooltip::after{content:'';position:absolute;right:-5px;top:50%;transform:translateY(-50%) rotate(45deg);width:9px;height:9px;background:inherit;border-right:1px solid rgba(255,255,255,.14);border-top:1px solid rgba(255,255,255,.14)}
.group\/blockfix:hover .block-fix-tooltip{opacity:1;visibility:visible;transform:translateY(-50%) translateX(0)}
/* Dark & Neon Theme Overrides for Block Fix & Drag */
[data-theme="dark"] .sortable-ghost{background:rgba(255,255,255,.06)!important;border:2px dashed rgba(148,163,184,.4)!important;box-shadow:inset 0 2px 10px rgba(0,0,0,.3)!important}
[data-theme="dark"] .sortable-drag{box-shadow:0 24px 45px rgba(0,0,0,.75), 0 0 0 1px rgba(255,255,255,.12)!important}
[data-theme="dark"] .list-cards.drag-over{background:rgba(255,255,255,.04)!important;outline:2px dashed rgba(148,163,184,.4)}
[data-theme="dark"] .block-fix-tooltip{background:#1e293b!important;border:1px solid #334155!important;color:#f1f5f9!important;box-shadow:0 12px 30px rgba(0,0,0,.6)!important}
[data-theme="dark"] .block-fix-tooltip::after{background:#1e293b!important;border-right:1px solid #334155!important;border-top:1px solid #334155!important}
[data-theme="dark"] .block-fix-btn.is-unfixed{border-color:#475569!important;background:#1e293b!important;color:#94a3b8!important}
[data-theme="dark"] .block-fix-btn.is-unfixed:hover{border-color:#34d399!important;color:#34d399!important;background:rgba(16,185,129,.15)!important}
[data-theme="dark"] .block-fix-btn.is-fixed{border-color:#10b981!important;background:rgba(16,185,129,.2)!important;color:#34d399!important}

[data-theme="neon"] .sortable-ghost{background:rgba(0,160,255,.12)!important;border:2px dashed rgba(0,220,255,.7)!important;box-shadow:inset 0 0 16px rgba(0,180,255,.3)!important}
[data-theme="neon"] .sortable-drag{border-color:#00f0ff!important;box-shadow:0 24px 50px rgba(0,0,0,.85), 0 0 30px rgba(0,200,255,.7)!important}
[data-theme="neon"] .list-cards.drag-over{background:rgba(0,160,255,.1)!important;outline:2px dashed rgba(0,220,255,.6);box-shadow:inset 0 0 24px rgba(0,140,255,.2)!important}
[data-theme="neon"] .block-fix-tooltip{background:rgba(3,14,44,.96)!important;border:1px solid rgba(0,190,255,.5)!important;color:#e0f2fe!important;box-shadow:0 12px 35px rgba(0,0,0,.8), 0 0 20px rgba(0,180,255,.35)!important}
[data-theme="neon"] .block-fix-tooltip::after{background:rgba(3,14,44,.96)!important;border-right:1px solid rgba(0,190,255,.5)!important;border-top:1px solid rgba(0,190,255,.5)!important}
[data-theme="neon"] .block-fix-btn.is-unfixed{border:1.5px solid rgba(0,180,255,.55)!important;background:rgba(0,30,80,.45)!important;color:#38bdf8!important;box-shadow:0 0 10px rgba(0,160,255,.25)!important}
[data-theme="neon"] .block-fix-btn.is-unfixed:hover{border-color:#00f0ff!important;color:#ffffff!important;background:rgba(0,160,255,.3)!important;box-shadow:0 0 16px rgba(0,220,255,.6)!important}
[data-theme="neon"] .block-fix-btn.is-fixed{border:1.5px solid rgba(16,185,129,.8)!important;background:rgba(16,185,129,.25)!important;color:#34d399!important;box-shadow:0 0 12px rgba(16,185,129,.45)!important}
[data-theme="neon"] .block-fix-btn.is-fixed:hover{border-color:#34d399!important;background:rgba(16,185,129,.45)!important;color:#ffffff!important;box-shadow:0 0 20px rgba(16,185,129,.75)!important}

.kanban-card-title{font-size:.95rem;line-height:1.3;font-weight:700;color:#1e293b;letter-spacing:0;margin-bottom:.5rem;padding-right:1.5rem}
.kanban-card-meta{font-size:.875rem;line-height:1.25}
.kanban-card-label{height:.6rem;width:3.25rem;border-radius:999px}
.kanban-card-avatar{width:2rem;height:2rem}
.trello-card-modal{font-size:.95rem;line-height:1.5}
.trello-card-modal .card-detail-title{font-size:1.45rem!important;line-height:1.28!important;padding-top:.35rem!important;padding-bottom:.35rem!important}
.trello-card-modal .text-xs{font-size:.875rem!important;line-height:1.45!important}
.trello-card-modal .text-\[10px\]{font-size:.75rem!important;line-height:1.35!important}
.trello-card-modal .text-\[11px\]{font-size:.8125rem!important;line-height:1.4!important}
.trello-card-modal textarea,.trello-card-modal input,.trello-card-modal select{font-size:.9375rem!important}
.trello-card-modal button{font-size:.875rem}
.trello-card-modal .prose{font-size:.9375rem;line-height:1.6}
.trello-card-modal .modal-action-btn{padding:.75rem .85rem!important;font-weight:750!important}
.add-list-btn{flex-shrink:0;width:272px;background:rgba(255,255,255,.25);border-radius:12px;padding:1rem;cursor:pointer;color:#64748b;font-weight:500;font-size:.875rem;display:flex;align-items:center;gap:.5rem;transition:background .2s;align-self:flex-start}
.add-list-btn:hover{background:rgba(255,255,255,.45);color:#334155}
.priority-urgent{border-left:3px solid #ef4444}
.priority-high{border-left:3px solid #f97316}
.priority-medium{border-left:3px solid #6366f1}
.priority-low{border-left:3px solid #94a3b8}
/* Card quick-action button */
.card-quick-btn{position:absolute;top:6px;right:6px;opacity:0;transition:opacity .15s;background:rgba(255,255,255,.9);border:1px solid #e2e8f0;border-radius:6px;width:24px;height:24px;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:10;box-shadow:0 1px 4px rgba(0,0,0,.12)}
.card-quick-btn:hover{background:#f8fafc;border-color:#c7d2dd}
/* Context menu */
#card-ctx-menu{position:fixed;z-index:9999;min-width:196px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 8px 32px rgba(0,0,0,.18),0 2px 8px rgba(0,0,0,.08);padding:6px;user-select:none;transition:opacity .1s,transform .1s}
#card-ctx-menu.hidden{display:none}
.ctx-item{display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;font-size:.8rem;font-weight:600;color:#334155;cursor:pointer;transition:background .12s,color .12s;white-space:nowrap}
	.ctx-item:hover{background:#f1f5f9;color:#1e293b}
	.ctx-item.ctx-danger{color:#dc2626}
	.ctx-item.ctx-danger:hover{background:#fef2f2;color:#b91c1c}
	.ctx-sep{height:1px;background:#f1f5f9;margin:4px 0}

/* Dark Mode Context Menu */
[data-theme="dark"] #card-ctx-menu { background: #1e293b !important; border-color: #334155 !important; box-shadow: 0 12px 36px rgba(0,0,0,0.5) !important; }
[data-theme="dark"] .ctx-item { color: #cbd5e1 !important; }
[data-theme="dark"] .ctx-item:hover { background: #334155 !important; color: #ffffff !important; }
[data-theme="dark"] .ctx-item svg { color: #94a3b8 !important; }
[data-theme="dark"] .ctx-item:hover svg { color: #38bdf8 !important; }
[data-theme="dark"] .ctx-item.ctx-danger { color: #f87171 !important; }
[data-theme="dark"] .ctx-item.ctx-danger svg { color: #f87171 !important; }
[data-theme="dark"] .ctx-item.ctx-danger:hover { background: rgba(239, 68, 68, 0.15) !important; color: #fca5a5 !important; }
[data-theme="dark"] .ctx-sep { background: #334155 !important; }
[data-theme="dark"] .card-quick-btn { background: rgba(30, 41, 59, 0.9) !important; border-color: #475569 !important; color: #94a3b8 !important; }
[data-theme="dark"] .card-quick-btn:hover { background: #334155 !important; color: #ffffff !important; }

/* Neon Mode Context Menu */
[data-theme="neon"] #card-ctx-menu {
  background: rgba(3, 14, 44, 0.98) !important;
  border: 1.5px solid rgba(0, 170, 255, 0.45) !important;
  border-radius: 16px !important;
  box-shadow: 0 16px 45px rgba(0, 0, 0, 0.8), 0 0 25px rgba(0, 140, 255, 0.3) !important;
  transform: translateZ(0);
  padding: 8px !important;
  min-width: 205px !important;
}
[data-theme="neon"] .ctx-item {
  color: #e0f2fe !important;
  font-weight: 600 !important;
  padding: 8px 12px !important;
  border-radius: 10px !important;
  transition: all 0.15s ease !important;
}
[data-theme="neon"] .ctx-item:hover {
  background: rgba(0, 110, 240, 0.28) !important;
  color: #ffffff !important;
  box-shadow: 0 0 12px rgba(0, 180, 255, 0.25) !important;
}
[data-theme="neon"] .ctx-item svg {
  color: #38bdf8 !important;
}
[data-theme="neon"] .ctx-item:hover svg {
  color: #00e5ff !important;
}
[data-theme="neon"] .ctx-item.ctx-danger {
  color: #fb7185 !important;
}
[data-theme="neon"] .ctx-item.ctx-danger svg {
  color: #fb7185 !important;
}
[data-theme="neon"] .ctx-item.ctx-danger:hover {
  background: rgba(244, 63, 94, 0.22) !important;
  color: #ffffff !important;
  box-shadow: 0 0 14px rgba(244, 63, 94, 0.35) !important;
}
[data-theme="neon"] .ctx-item.ctx-danger:hover svg {
  color: #ffffff !important;
}
[data-theme="neon"] .ctx-sep {
  background: rgba(0, 160, 255, 0.22) !important;
  margin: 5px 0 !important;
}
[data-theme="neon"] .card-quick-btn {
  background: rgba(3, 14, 44, 0.9) !important;
  border: 1px solid rgba(0, 170, 255, 0.45) !important;
  color: #38bdf8 !important;
  box-shadow: 0 0 10px rgba(0, 140, 255, 0.25) !important;
}
[data-theme="neon"] .card-quick-btn:hover {
  background: rgba(0, 120, 255, 0.35) !important;
  border-color: #00d2ff !important;
  color: #ffffff !important;
  box-shadow: 0 0 14px rgba(0, 190, 255, 0.45) !important;
}
	.board-menu-row{display:flex;width:100%;align-items:center;gap:.75rem;border-radius:.85rem;padding:.7rem .75rem;text-align:left;font-size:.875rem;font-weight:800;color:#334155;transition:background .16s ease,color .16s ease,transform .16s ease,box-shadow .16s ease}
	.board-menu-row:hover{background:linear-gradient(135deg,#eff6ff,#f8fafc);color:#1d4ed8;transform:translateX(2px);box-shadow:0 10px 24px rgba(47,104,237,.08)}
	.board-menu-row.text-rose-600{color:#dc2626}
	.board-menu-icon{display:flex;height:1.9rem;width:1.9rem;flex-shrink:0;align-items:center;justify-content:center;border-radius:.65rem;background:#f1f5f9;color:#64748b;font-size:.7rem;font-weight:900;transition:background .16s ease,color .16s ease,transform .16s ease}
	.board-menu-icon svg{height:1rem;width:1rem}
	.board-menu-row:hover .board-menu-icon{background:#dbeafe;color:#2F68ED;transform:scale(1.04)}

	[data-theme="dark"] .board-menu-row {
		color: #f1f5f9 !important;
	}
	[data-theme="dark"] .board-menu-row:hover {
		background: rgba(255, 255, 255, 0.07) !important;
		color: #38bdf8 !important;
	}
	[data-theme="dark"] .board-menu-row.text-rose-600 {
		color: #fca5a5 !important;
	}
	[data-theme="dark"] .board-menu-row.text-rose-600:hover {
		background: rgba(244, 63, 94, 0.15) !important;
		color: #fda4af !important;
	}
	[data-theme="dark"] .board-menu-icon {
		background: #272a34 !important;
		color: #94a3b8 !important;
	}
	[data-theme="dark"] .board-menu-row:hover .board-menu-icon {
		background: rgba(56, 189, 248, 0.2) !important;
		color: #38bdf8 !important;
	}

	/* ── Mobile Board App-Grade Experience (Trello Resource Standard - Scoped to Board Only) ── */
	@media (max-width: 1023px) {
		.is-board-page .topbar,
		html:has(.board-wrap) .topbar { padding: 4px 8px !important; min-height: 40px !important; border-bottom: none !important; margin-bottom: 0 !important; background: transparent !important; }
		.is-board-page .topbar .mobile-topbar-title-text,
		html:has(.board-wrap) .topbar .mobile-topbar-title-text { display: none; }
		.is-board-page .page-content,
		.page-content:has(.board-wrap) { padding: 0 !important; }

		/* Header: Seamless App Bar with easy-to-click action targets */
		.board-header-mobile {
			margin: 0.25rem 0.5rem 0 !important;
			padding: 0.4rem 0.6rem !important;
			border-radius: 16px !important;
			gap: 0.4rem !important;
			box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06) !important;
			flex-wrap: nowrap !important;
		}
		.board-header-mobile h1 {
			font-size: 0.95rem !important;
			line-height: 1.3 !important;
		}
		.board-header-mobile .btn {
			padding: 0.35rem 0.65rem !important;
			font-size: 0.8rem !important;
			height: 36px !important;
			border-radius: 12px !important;
		}
		.board-header-mobile .btn-icon {
			width: 36px !important;
			height: 36px !important;
			padding: 0 !important;
			display: flex !important;
			align-items: center !important;
			justify-content: center !important;
		}

		/* Zoom on mobile: fully visible, compact, and styled like a native segmented pill */
		.board-header-mobile .zoom-container {
			display: flex !important;
			align-items: center !important;
			padding: 2px 4px !important;
			border-radius: 12px !important;
			gap: 3px !important;
			height: 36px !important;
			background: rgba(241, 245, 249, 0.95) !important;
			border: 1px solid rgba(203, 213, 225, 0.8) !important;
		}
		[data-theme="dark"] .board-header-mobile .zoom-container {
			background: rgba(30, 41, 59, 0.95) !important;
			border-color: rgba(51, 65, 85, 0.8) !important;
		}
		.board-header-mobile .zoom-label {
			display: none !important;
		}
		.board-header-mobile .zoom-pill {
			border: none !important;
			box-shadow: none !important;
			padding: 0 !important;
			gap: 2px !important;
			background: transparent !important;
		}
		.board-header-mobile .zoom-btn {
			width: 26px !important;
			height: 26px !important;
			border-radius: 8px !important;
			font-size: 15px !important;
			font-weight: 900 !important;
			background: #ffffff !important;
			color: #0f172a !important;
			border: 1px solid rgba(203, 213, 225, 0.8) !important;
			box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06) !important;
			display: flex !important;
			align-items: center !important;
			justify-content: center !important;
			cursor: pointer;
		}
		[data-theme="dark"] .board-header-mobile .zoom-btn {
			background: #334155 !important;
			color: #f8fafc !important;
			border-color: #475569 !important;
		}
		.board-header-mobile .zoom-btn:active {
			transform: scale(0.92) !important;
			background: #e2e8f0 !important;
		}
		.board-header-mobile .zoom-pill span {
			width: 32px !important;
			font-size: 11px !important;
			font-weight: 800 !important;
			text-align: center;
		}
		.kanban-card-avatar {
			width: 1.45rem !important;
			height: 1.45rem !important;
		}

		/* Full viewport lock on mobile board view: eliminates window bounce & lag */
		html.is-board-page,
		body.is-board-page,
		html:has(.board-wrap),
		body:has(.board-wrap) {
			height: 100dvh !important;
			max-height: 100dvh !important;
			overflow: hidden !important;
			position: fixed !important;
			width: 100% !important;
		}
		.is-board-page .main-wrapper,
		.main-wrapper:has(.board-wrap) {
			height: 100dvh !important;
			max-height: 100dvh !important;
			overflow: hidden !important;
			display: flex !important;
			flex-direction: column !important;
		}
		.is-board-page .topbar,
		.main-wrapper:has(.board-wrap) .topbar {
			flex: 0 0 auto !important;
			height: auto !important;
			min-height: 0 !important;
			padding: 4px 8px !important;
		}
		.is-board-page .page-content,
		.page-content:has(.board-wrap) {
			flex: 1 1 0% !important;
			min-height: 0 !important;
			height: 100% !important;
			max-height: 100% !important;
			overflow: hidden !important;
			display: flex !important;
			flex-direction: column !important;
			padding: 0 !important;
		}

		/* Prevent iOS WebKit automatic zooming on inputs / textareas / selects */
		input, select, textarea {
			font-size: 16px !important;
		}

		/* Board canvas & lists: Responsive native app swiping with card peeking */
		.board-wrap {
			flex: 1 1 0% !important;
			min-height: 0 !important;
			height: 100% !important;
			display: flex !important;
			padding: 0.25rem 0.75rem calc(56px + max(4px, calc(env(safe-area-inset-bottom, 0px) - 18px))) 0.75rem !important;
			gap: 0.75rem !important;
			overflow-x: auto !important;
			overflow-y: hidden !important;
			-webkit-overflow-scrolling: touch !important;
			overscroll-behavior-x: contain !important;
			scroll-snap-type: x mandatory !important;
			scroll-padding: 0.75rem !important;
			touch-action: pan-x pan-y !important;
		}

		#sortable-lists-container {
			height: 100% !important;
			display: flex !important;
			align-items: stretch !important;
			min-width: 100% !important;
			width: max-content !important;
		}

		/* Responsive native-app list sizing: adaptive width with next list peeking in */
		.board-list {
			width: calc(100vw - 48px) !important;
			max-width: 330px !important;
			min-width: 275px !important;
			flex-shrink: 0 !important;
			border-radius: 18px !important;
			scroll-snap-align: start !important;
			scroll-snap-stop: always !important;
			height: 100% !important;
			max-height: 100% !important;
			display: flex !important;
			flex-direction: column !important;
			box-shadow: 0 4px 18px rgba(0, 0, 0, 0.08) !important;
		}

		/* List Header */
		.list-header {
			padding: 0.7rem 0.85rem !important;
			font-size: 0.875rem !important;
			flex-shrink: 0 !important;
		}

		/* Responsive native-app cards */
		.list-cards {
			padding: 0.5rem !important;
			gap: 0.5rem !important;
			flex: 1 1 0% !important;
			min-height: 0 !important;
			overflow-y: auto !important;
			-webkit-overflow-scrolling: touch !important;
			overscroll-behavior-y: contain !important;
		}
		.kanban-card {
			padding: 0.75rem 0.85rem !important;
			margin-bottom: 0.55rem !important;
			border-radius: 14px !important;
			border: 1px solid rgba(226, 232, 240, 0.95) !important;
			box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.04) !important;
			touch-action: pan-y !important;
			cursor: pointer !important;
			transition: transform 0.12s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.12s ease !important;
		}
		.kanban-card:active {
			transform: scale(0.985) !important;
			background: #f8fafc !important;
		}
		[data-theme="dark"] .kanban-card:active {
			background: #1e293b !important;
		}
		.kanban-card-title {
			font-size: 0.9rem !important;
			line-height: 1.35 !important;
			font-weight: 700 !important;
			color: #1e293b !important;
			display: -webkit-box;
			-webkit-line-clamp: 4;
			-webkit-box-orient: vertical;
			overflow: hidden;
		}
		[data-theme="dark"] .kanban-card-title {
			color: #f1f5f9 !important;
		}
		.kanban-card-label {
			height: 6px !important;
			width: 32px !important;
			border-radius: 9999px !important;
		}
		.kanban-card-meta {
			font-size: 0.775rem !important;
			padding: 3px 7px !important;
			border-radius: 7px !important;
			line-height: 1.25 !important;
			font-weight: 700 !important;
		}
		.card-assignees-scroll {
			scrollbar-width: none !important;
			-ms-overflow-style: none !important;
			touch-action: pan-x pan-y !important;
			-webkit-overflow-scrolling: touch !important;
		}
		.card-assignees-scroll::-webkit-scrollbar {
			display: none !important;
		}
		.card-quick-btn {
			opacity: 1 !important;
			width: 28px !important;
			height: 28px !important;
			top: 6px !important;
			right: 6px !important;
			border-radius: 8px !important;
			background: rgba(255, 255, 255, 0.96) !important;
			border: 1px solid rgba(226, 232, 240, 0.85) !important;
			box-shadow: 0 1px 3px rgba(0,0,0,0.08) !important;
			display: flex !important;
			align-items: center !important;
			justify-content: center !important;
			touch-action: manipulation !important;
		}
		.card-quick-btn:active {
			transform: scale(0.92) !important;
			background: #e2e8f0 !important;
		}
		[data-theme="dark"] .card-quick-btn {
			background: rgba(30, 41, 59, 0.95) !important;
			border-color: rgba(51, 65, 85, 0.8) !important;
			color: #cbd5e1 !important;
		}
		#card-ctx-menu {
			min-width: 210px !important;
			padding: 8px !important;
			border-radius: 16px !important;
			box-shadow: 0 12px 35px rgba(0,0,0,0.22) !important;
		}
		.ctx-item {
			padding: 10px 14px !important;
			font-size: 0.875rem !important;
			min-height: 40px !important;
			gap: 12px !important;
			border-radius: 10px !important;
		}
		.add-list-wrapper {
			scroll-snap-align: none !important;
			scroll-snap-stop: normal !important;
		}
		.add-list-btn {
			width: calc(100vw - 48px) !important;
			max-width: 330px !important;
			min-width: 275px !important;
			min-height: 46px !important;
			border-radius: 18px !important;
		}
		.adding-list-container {
			width: calc(100vw - 48px) !important;
			max-width: 330px !important;
			min-width: 275px !important;
			border-radius: 18px !important;
		}

		/* Zero 300ms tap delay on mobile interactive elements */
		.kanban-card,
		.btn,
		.card-quick-btn,
		.block-fix-btn,
		.zoom-btn,
		.board-list-menu-trigger,
		button,
		a {
			touch-action: manipulation !important;
			-webkit-tap-highlight-color: transparent !important;
		}
	}
	@media (max-width: 480px) {
		.board-list {
			width: calc(100vw - 44px) !important;
			max-width: 320px !important;
			min-width: 265px !important;
		}
		.add-list-btn, .adding-list-container {
			width: calc(100vw - 44px) !important;
			max-width: 320px !important;
			min-width: 265px !important;
		}
		.board-wrap {
			padding: 0.25rem 0.65rem calc(56px + max(4px, calc(env(safe-area-inset-bottom, 0px) - 18px))) 0.65rem !important;
			gap: 0.65rem !important;
			scroll-padding: 0.65rem !important;
		}
	}

	/* Zoom Control Styling */
	.zoom-container {
		background-color: #ffffff;
		border-color: #e2e8f0;
	}
	.zoom-label {
		color: #64748b;
	}
	.zoom-pill {
		background-color: #ffffff;
		border-color: #e2e8f0;
		color: #334155;
	}
	.zoom-btn {
		color: #334155;
	}
	.zoom-btn:hover {
		background-color: #f1f5f9;
	}
	.zoom-reset {
		color: #4f46e5;
	}
	.zoom-reset:hover {
		color: #4338ca;
	}

	/* Dark mode overrides */
	[data-theme="dark"] .kanban-comment-bubble {
		background-color: #475569 !important;
		border-color: #9E9E9E !important;
	}
	[data-theme="dark"] .kanban-comment-text {
		color: #f1f5f9 !important;
	}
	[data-theme="dark"] .kanban-comment-text::placeholder {
		color: #cbd5e1 !important;
	}
	[data-theme="dark"] .zoom-container {
		background-color: #1e293b !important;
		border-color: #334155 !important;
	}
	[data-theme="dark"] .zoom-label {
		color: #94a3b8 !important;
	}

	/* Guaranteed red hover for Cancel/Delete buttons */
	button.btn-cancel-hover.btn-cancel-hover:hover, 
	button.btn-cancel-hover.btn-cancel-hover:active {
		background-color: #ef4444 !important;
		color: #ffffff !important;
		border-color: #ef4444 !important;
	}
	[data-theme="dark"] button.btn-cancel-hover.btn-cancel-hover:hover, 
	[data-theme="dark"] button.btn-cancel-hover.btn-cancel-hover:active {
		background-color: #dc2626 !important;
		color: #ffffff !important;
		border-color: #dc2626 !important;
	}
	[data-theme="dark"] .zoom-pill {
		background-color: #000000 !important;
		border-color: #1e293b !important;
		color: #ffffff !important;
	}
	[data-theme="dark"] .zoom-btn {
		color: #ffffff !important;
	}
	[data-theme="dark"] .zoom-btn:hover {
		background-color: rgba(255, 255, 255, 0.15) !important;
	}
	[data-theme="dark"] .zoom-reset {
		color: #818cf8 !important;
	}
	[data-theme="dark"] .zoom-reset:hover {
		color: #a5b4fc !important;
	}
	[data-theme="dark"] .board-list {
		background: rgba(30, 41, 59, 0.95);
		border-color: rgba(255, 255, 255, 0.08);
	}
	[data-theme="dark"] .list-header {
		background: rgba(15, 23, 42, 0.5);
		border-bottom-color: rgba(255, 255, 255, 0.05);
	}
	[data-theme="dark"] .list-header span.text-slate-700 {
		color: #f8fafc !important;
	}
	[data-theme="dark"] .kanban-card {
		background: #0f172a;
		border-color: rgba(255, 255, 255, 0.08);
		box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
	}
	[data-theme="dark"] .kanban-card-title {
		color: #f1f5f9;
	}
	[data-theme="dark"] .add-list-btn {
		background: rgba(15, 23, 42, 0.4);
		color: #94a3b8;
	}
	[data-theme="dark"] .add-list-btn:hover {
		background: rgba(15, 23, 42, 0.6);
		color: #f8fafc;
	}

	/* ── Dark Mode Canvas Overrides ── */
	[data-theme="dark"] .board-wrap,
	html.dark .board-wrap {
		background: transparent !important;
	}
	[data-theme="dark"] .board-canvas-root:not([data-bg-type="image"]),
	html.dark .board-canvas-root:not([data-bg-type="image"]) {
		background: #0b1329 !important;
		background-size: cover !important;
	}
	[data-theme="dark"] .board-canvas-root[data-bg-type="image"],
	html.dark .board-canvas-root[data-bg-type="image"] {
		background-size: cover !important;
		background-position: center !important;
	}

	/* ── Light Mode Canvas Default ── */
	html:not([data-theme="dark"]):not([data-theme="neon"]):not(.dark) .board-canvas-root:not([data-bg-type="image"]) {
		background: #ffffff;
	}

	/* ── Neon Blue UI Overrides for Board View (Screenshot 1) ── */
	[data-theme="neon"] .board-wrap {
		background: transparent !important;
	}
	[data-theme="neon"] .board-canvas-root:not([data-bg-type="image"]) {
		background: radial-gradient(ellipse at 45% -10%, rgba(0, 150, 255, 0.42) 0%, rgba(0, 70, 210, 0.22) 42%, transparent 70%),
					radial-gradient(ellipse at 85% 90%, rgba(0, 100, 255, 0.2) 0%, transparent 50%),
					radial-gradient(ellipse at 10% 90%, rgba(0, 50, 180, 0.15) 0%, transparent 50%),
					#020819 !important;
		background-size: cover !important;
	}
	[data-theme="neon"] .board-canvas-root[data-bg-type="image"] {
		background-size: cover !important;
		background-position: center !important;
	}
	[data-theme="neon"] .board-header-mobile {
		background: rgba(3, 14, 44, 0.92) !important;
		border: 1px solid rgba(0, 170, 255, 0.4) !important;
		box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 16px rgba(0, 130, 255, 0.2) !important;
		max-width: 100vw;
		overflow: visible !important;
	}
	[data-theme="neon"] .board-header-mobile div[x-show="filtersOpen"],
	[data-theme="neon"] .board-header-mobile div[x-show="openMembers"],
	[data-theme="neon"] .board-header-mobile div[x-show="searchOpen"] {
		background: rgba(3, 14, 44, 0.98) !important;
		border: 1.5px solid rgba(0, 180, 255, 0.6) !important;
		box-shadow: 0 20px 50px rgba(0, 0, 0, 0.85), 0 0 25px rgba(0, 160, 255, 0.35) !important;
		z-index: 60 !important;
	}
	[data-theme="neon"] .board-header-mobile h1 {
		color: #ffffff !important;
		text-shadow: 0 0 12px rgba(0, 190, 255, 0.6) !important;
	}
	[data-theme="neon"] .zoom-container {
		background-color: rgba(2, 10, 32, 0.85) !important;
		border-color: rgba(0, 170, 255, 0.45) !important;
		box-shadow: 0 0 10px rgba(0, 140, 255, 0.2) !important;
	}
	[data-theme="neon"] .zoom-label {
		color: #93c5fd !important;
	}
	[data-theme="neon"] .zoom-pill {
		background-color: rgba(0, 40, 100, 0.7) !important;
		border-color: rgba(0, 170, 255, 0.4) !important;
		color: #38bdf8 !important;
		font-weight: 700 !important;
	}
	[data-theme="neon"] .zoom-btn {
		color: #bae6fd !important;
	}
	[data-theme="neon"] .zoom-btn:hover {
		background-color: rgba(0, 120, 255, 0.25) !important;
		color: #ffffff !important;
	}
	[data-theme="neon"] .board-list {
		background: rgba(4, 18, 50, 0.94) !important;
		border: 1px solid rgba(0, 150, 255, 0.38) !important;
		border-radius: 16px !important;
		box-shadow: 0 8px 24px rgba(0, 0, 0, 0.45), 0 0 15px rgba(0, 120, 255, 0.15) !important;
	}
	[data-theme="neon"] .list-header {
		background: rgba(2, 12, 36, 0.75) !important;
		border-bottom: 1px solid rgba(0, 150, 255, 0.25) !important;
		color: #f0f9ff !important;
	}
	[data-theme="neon"] .list-header span.text-slate-700,
	[data-theme="neon"] .list-header .font-bold {
		color: #f0f9ff !important;
		text-shadow: 0 0 8px rgba(0, 180, 255, 0.4) !important;
	}
	[data-theme="neon"] .kanban-card {
		background: rgba(7, 26, 68, 0.94) !important;
		border: 1px solid rgba(0, 160, 255, 0.35) !important;
		border-radius: 14px !important;
		box-shadow: 0 4px 16px rgba(0, 0, 0, 0.45), 0 0 10px rgba(0, 120, 255, 0.15) !important;
		color: #f1f5f9 !important;
	}
	[data-theme="neon"] .kanban-card:hover {
		border-color: rgba(0, 220, 255, 0.85) !important;
		box-shadow: 0 8px 28px rgba(0, 0, 0, 0.5), 0 0 20px rgba(0, 190, 255, 0.5) !important;
		transform: translateY(-2px) translateZ(0) !important;
	}
	[data-theme="neon"] .kanban-card-title {
		color: #ffffff !important;
		font-weight: 700 !important;
		text-shadow: 0 0 6px rgba(0, 150, 255, 0.3) !important;
	}
	[data-theme="neon"] .kanban-card-meta {
		color: #93c5fd !important;
	}
	[data-theme="neon"] .kanban-card-meta.bg-slate-100,
	[data-theme="neon"] .kanban-card-meta.bg-red-100 {
		background: #ff2d87 !important;
		color: #ffffff !important;
		box-shadow: 0 0 10px rgba(255, 45, 135, 0.5) !important;
		border: none !important;
	}
	[data-theme="neon"] .kanban-card .bg-slate-50 {
		background: rgba(0, 50, 130, 0.45) !important;
		border-color: rgba(0, 160, 255, 0.35) !important;
	}
	[data-theme="neon"] .kanban-card .bg-slate-50 span {
		color: #e0f2fe !important;
	}
	[data-theme="neon"] .add-list-btn {
		background: rgba(0, 70, 180, 0.2) !important;
		border: 1px dashed rgba(0, 160, 255, 0.45) !important;
		color: #7dd3fc !important;
		border-radius: 14px !important;
	}
	[data-theme="neon"] .add-list-btn:hover {
		background: rgba(0, 100, 230, 0.35) !important;
		border-color: rgba(0, 220, 255, 0.8) !important;
		color: #ffffff !important;
		box-shadow: 0 0 15px rgba(0, 170, 255, 0.4) !important;
	}
	[data-theme="neon"] .board-menu-row {
		color: #f0f9ff !important;
	}
	[data-theme="neon"] .board-menu-row:hover {
		background: rgba(0, 110, 255, 0.25) !important;
		color: #38bdf8 !important;
	}
	[data-theme="neon"] .board-menu-icon {
		background: rgba(0, 50, 130, 0.45) !important;
		color: #93c5fd !important;
	}

	/* ── Board List 3-dots Dropdown Menu ── */
	.board-list-menu {
		background: #ffffff !important;
		border: 1px solid #e2e8f0 !important;
		border-radius: 14px !important;
		box-shadow: 0 20px 40px -5px rgba(0, 0, 0, 0.2), 0 10px 20px -5px rgba(0, 0, 0, 0.1) !important;
		z-index: 1000 !important;
	}
	.board-list-menu button {
		color: #334155 !important;
		font-weight: 600 !important;
		transition: all 0.15s ease !important;
	}
	.board-list-menu button:hover {
		background: #f1f5f9 !important;
		color: #0f172a !important;
	}
	.board-list-menu button.text-indigo-600 {
		color: #4f46e5 !important;
	}
	.board-list-menu button.text-indigo-600:hover {
		background: #eef2ff !important;
		color: #4338ca !important;
	}
	.board-list-menu button.text-amber-600 {
		color: #d97706 !important;
	}
	.board-list-menu button.text-amber-600:hover {
		background: #fef3c7 !important;
		color: #b45309 !important;
	}
	.board-list-menu button.text-rose-600 {
		color: #e11d48 !important;
	}
	.board-list-menu button.text-rose-600:hover {
		background: #ffe4e6 !important;
		color: #be123c !important;
	}
	.board-list-menu-divider {
		border-top: 1px solid #f1f5f9 !important;
	}

	[data-theme="dark"] .board-list-menu {
		background: #0f172a !important;
		border: 1px solid #334155 !important;
		box-shadow: 0 20px 45px rgba(0, 0, 0, 0.75) !important;
	}
	[data-theme="dark"] .board-list-menu button {
		color: #cbd5e1 !important;
	}
	[data-theme="dark"] .board-list-menu button:hover {
		background: #1e293b !important;
		color: #f8fafc !important;
	}
	[data-theme="dark"] .board-list-menu button.text-indigo-600 {
		color: #818cf8 !important;
	}
	[data-theme="dark"] .board-list-menu button.text-indigo-600:hover {
		background: rgba(99, 102, 241, 0.2) !important;
		color: #a5b4fc !important;
	}
	[data-theme="dark"] .board-list-menu button.text-amber-600 {
		color: #fbbf24 !important;
	}
	[data-theme="dark"] .board-list-menu button.text-amber-600:hover {
		background: rgba(245, 158, 11, 0.2) !important;
		color: #fcd34d !important;
	}
	[data-theme="dark"] .board-list-menu button.text-rose-600 {
		color: #f87171 !important;
	}
	[data-theme="dark"] .board-list-menu button.text-rose-600:hover {
		background: rgba(239, 68, 68, 0.2) !important;
		color: #fca5a5 !important;
	}
	[data-theme="dark"] .board-list-menu-divider {
		border-top: 1px solid #334155 !important;
	}

	/* Neon Theme Board List 3-dots Menu */
	[data-theme="neon"] .board-list-menu {
		background: rgba(3, 14, 44, 0.98) !important;
		border: 1.5px solid rgba(0, 180, 255, 0.5) !important;
		border-radius: 16px !important;
		box-shadow: 0 20px 50px rgba(0, 0, 0, 0.9), 0 0 25px rgba(0, 140, 255, 0.4) !important;
		transform: translateZ(0);
	}
	[data-theme="neon"] .board-list-menu button {
		color: #e0f2fe !important;
	}
	[data-theme="neon"] .board-list-menu button:hover {
		background: rgba(0, 110, 240, 0.35) !important;
		color: #ffffff !important;
		box-shadow: 0 0 10px rgba(0, 180, 255, 0.3) !important;
	}
	[data-theme="neon"] .board-list-menu button.text-indigo-600 {
		color: #38bdf8 !important;
	}
	[data-theme="neon"] .board-list-menu button.text-indigo-600:hover {
		background: rgba(0, 140, 255, 0.35) !important;
		color: #ffffff !important;
		box-shadow: 0 0 12px rgba(0, 180, 255, 0.4) !important;
	}
	[data-theme="neon"] .board-list-menu button.text-amber-600 {
		color: #fbbf24 !important;
	}
	[data-theme="neon"] .board-list-menu button.text-amber-600:hover {
		background: rgba(245, 158, 11, 0.25) !important;
		color: #ffffff !important;
	}
	[data-theme="neon"] .board-list-menu button.text-rose-600 {
		color: #fb7185 !important;
	}
	[data-theme="neon"] .board-list-menu button.text-rose-600:hover {
		background: rgba(244, 63, 94, 0.3) !important;
		color: #ffffff !important;
		box-shadow: 0 0 12px rgba(244, 63, 94, 0.4) !important;
	}
	[data-theme="neon"] .board-list-menu-divider {
		border-top: 1px solid rgba(0, 160, 255, 0.25) !important;
	}
	[data-theme="neon"] .board-list-menu-trigger {
		color: #7dd3fc !important;
	}
	[data-theme="neon"] .board-list-menu-trigger:hover {
		color: #ffffff !important;
		background: rgba(0, 140, 255, 0.25) !important;
	}
	</style>
	@endpush

@section('content')
{{-- Board takes full width – no max-width constraint --}}
@php
  $bgValue = $board->background_value ?: '#ffffff';
  if ($board->background_type === 'image') {
      $safeUrl = $bgValue;
      if (str_starts_with($safeUrl, '/public/')) {
          $safeUrl = substr($safeUrl, 7);
      }
      if (!filter_var($safeUrl, FILTER_VALIDATE_URL)) {
          $safeUrl = asset(ltrim($safeUrl, '/'));
      }
      $safeUrl = str_replace('"', '\"', $safeUrl);
      $serverStyle = "background-image: linear-gradient(rgba(15,23,42,.12), rgba(15,23,42,.32)), url(\"{$safeUrl}\"); background-color: #0f172a; background-size: cover; background-position: center;";
  } else {
      $serverStyle = "background: {$bgValue};";
  }
@endphp
<div class="flex-1 flex flex-col min-h-full board-canvas-root" style="{{ $serverStyle }}" :style="sbmBoardPreviewStyle(board)" data-bg-type="{{ $board->background_type }}" x-data='trelloBoard(@json($boardData))' x-init="init()">

{{-- ── Board header ────────────── --}}
<div class="relative sm:sticky sm:top-[64px] lg:top-[76px] z-[45] flex items-center justify-between gap-1.5 sm:gap-3 mb-2 sm:mb-4 flex-nowrap bg-white dark:bg-slate-800 sm:bg-white/65 sm:dark:bg-slate-800/80 sm:backdrop-blur-xl p-1.5 sm:p-3.5 rounded-xl sm:rounded-2xl border border-slate-200 sm:border-slate-200/60 dark:border-slate-700 sm:dark:border-slate-700/60 shadow-xs sm:shadow-md board-header-mobile">
  <div class="relative flex items-center gap-1 sm:gap-2 min-w-0 flex-1 sm:flex-initial">
    <div class="min-w-0">
      <nav class="hidden sm:block text-xs text-slate-400 mb-0.5">
        @if(isset($isSmmModule) && $isSmmModule)
            <a href="{{ route('smm-boards.index') }}" class="hover:text-indigo-600 font-medium">SMM Planning Boards</a>
        @else
            <a href="{{ route('boards.workspaces') }}" class="hover:text-indigo-600 font-medium">Workspaces</a>
            <span class="mx-1 text-slate-300">›</span>
            <span class="font-medium text-slate-500" x-text="sbmBoardWorkspaceName(board)">{{ $board->workspace->name }}</span>
        @endif
      </nav>
      <div class="relative flex items-center gap-1 sm:gap-2 min-w-0">
        <button type="button" @click="openSwitchBoardsModal()" class="flex items-center gap-1 sm:gap-1.5 py-1 px-2 rounded-xl bg-slate-100/90 hover:bg-slate-200/90 dark:bg-slate-700/60 dark:hover:bg-slate-700 active:scale-95 transition-all text-left border border-slate-200/80 dark:border-slate-600/80 group min-w-0 shadow-2xs" title="Tap to switch board">
          <h1 class="font-display font-black text-slate-800 dark:text-slate-100 text-xs xs:text-sm sm:text-lg flex items-center gap-1 sm:gap-1.5 truncate max-w-[130px] xs:max-w-[190px] sm:max-w-md">
            <span x-text="board.name" class="truncate">{{ $board->name }}</span>
            <svg class="w-3.5 h-3.5 text-indigo-500 dark:text-indigo-400 flex-shrink-0 group-hover:translate-y-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
          </h1>
        </button>
        
        <!-- Star Toggle -->
        <button @click="toggleStar()" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700/60 transition-colors flex items-center justify-center text-xs sm:text-lg select-none flex-shrink-0"
                :class="board.is_starred ? 'text-amber-500' : 'text-slate-300 dark:text-slate-600 hover:text-slate-400'"
                title="Star board">
            <span x-text="board.is_starred ? '★' : '☆'"></span>
        </button>
      </div>
    </div>
  </div>

  <div class="ml-auto flex flex-shrink-0 items-center gap-1 sm:gap-2.5">
    {{-- Zoom Control (Visible and touch-optimized on both mobile and desktop) --}}
    <div class="zoom-container flex items-center gap-1 sm:gap-2 mr-0.5 sm:mr-1 rounded-xl sm:rounded-full px-1.5 sm:px-3 py-1 sm:py-1.5 border shadow-2xs sm:shadow-sm">
        <span class="zoom-label hidden md:inline text-[10px] font-extrabold uppercase tracking-widest pl-1 mr-1">Zoom</span>
        <div class="zoom-pill flex items-center gap-0.5 sm:gap-1 text-[11px] sm:text-xs font-bold rounded-lg sm:rounded-full px-1 sm:px-2 py-0.5 sm:py-1 border">
            <button type="button" @click="zoomOut()" class="zoom-btn w-6 h-6 sm:w-5 sm:h-5 flex items-center justify-center rounded-lg sm:rounded-full font-black transition-colors" :class="{'opacity-40 cursor-not-allowed': zoomLevel <= 50}" title="Zoom out">−</button>
            <span class="w-8 sm:w-10 text-center select-none cursor-pointer" @click="setZoom(100)" title="Click to reset zoom" x-text="zoomLevel + '%'"></span>
            <button type="button" @click="zoomIn()" class="zoom-btn w-6 h-6 sm:w-5 sm:h-5 flex items-center justify-center rounded-lg sm:rounded-full font-black transition-colors" :class="{'opacity-40 cursor-not-allowed': zoomLevel >= 150}" title="Zoom in">+</button>
        </div>
        <button x-show="zoomLevel !== 100" @click="setZoom(100)" x-cloak class="zoom-reset hidden sm:inline text-[11px] font-bold px-1 transition-colors">Reset</button>
    </div>

    {{-- Board Members Stack (Visible on desktop, hidden on tiny mobile) --}}
    <div class="hidden sm:flex items-center -space-x-1.5 sm:-space-x-2 mr-0.5 sm:mr-1">
      @foreach($board->members->take(3) as $bm)
        <img src="{{ $bm->avatar_url }}" alt="{{ $bm->name }}" title="{{ $bm->name }}"
             class="w-6 h-6 sm:w-7 sm:h-7 rounded-full object-cover border-2 border-white shadow-sm ring-1 ring-slate-100">
      @endforeach
      @if($board->members->count() > 3)
        <span class="w-6 h-6 sm:w-7 sm:h-7 rounded-full border-2 border-white bg-slate-900/80 text-white text-[10px] font-black flex items-center justify-center shadow-sm ring-1 ring-slate-100">
          +{{ $board->members->count() - 3 }}
        </span>
      @endif
    </div>

    {{-- Board Search --}}
    <div class="relative">
      <button type="button"
              @click="searchOpen = !searchOpen; if (searchOpen) { $nextTick(() => $refs.boardSearchInput?.focus()) }"
              class="btn btn-secondary btn-icon w-8 h-8 sm:w-9 sm:h-9"
              :class="searchQuery.trim() ? '!bg-indigo-50 !text-indigo-700 !border-indigo-200' : ''"
              title="Search cards"
              aria-label="Search cards">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m0 0A7.5 7.5 0 1 0 6.04 6.04a7.5 7.5 0 0 0 10.61 10.61Z" />
        </svg>
      </button>
      <div x-show="searchOpen" @click.outside="searchOpen = false" x-cloak
           class="fixed inset-x-3 top-16 md:absolute md:inset-auto md:right-0 md:top-auto md:mt-2 md:w-80 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 shadow-2xl z-[70]"
           x-transition:enter="transition ease-out duration-100"
           x-transition:enter-start="opacity-0 scale-95"
           x-transition:enter-end="opacity-100 scale-100">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-2 mb-3">
          <p class="text-[10px] uppercase font-black tracking-widest text-slate-400">Search cards</p>
          <div class="flex items-center gap-2">
            <button type="button" x-show="searchQuery.trim()" x-cloak @click="searchQuery = ''" class="text-[10px] font-black text-indigo-600 hover:text-indigo-800">Clear</button>
            <button type="button" @click="searchOpen = false" class="md:hidden text-slate-400 hover:text-slate-600 p-1" aria-label="Close search">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
        </div>
        <div class="relative">
          <input x-ref="boardSearchInput"
                 x-model.debounce.150ms="searchQuery"
                 type="search"
                 class="form-input w-full rounded-xl pl-9 h-11 md:h-9 text-base md:text-sm bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200"
                 placeholder="Search card or public date (e.g. 25/09/2026)...">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m0 0A7.5 7.5 0 1 0 6.04 6.04a7.5 7.5 0 0 0 10.61 10.61Z" />
          </svg>
        </div>
      </div>
    </div>

    {{-- Board Filters --}}
    <div class="relative">
      <button type="button"
              @click="filtersOpen = !filtersOpen"
              class="btn btn-secondary py-1 sm:py-1.5 px-2.5 sm:px-3 text-xs flex items-center gap-1.5 font-bold h-9 rounded-xl active:scale-95"
              :class="activeFiltersCount() ? '!bg-indigo-50 !text-indigo-700 !border-indigo-200 dark:!bg-indigo-950/50 dark:!text-indigo-400 dark:!border-indigo-800' : ''">
        <svg class="w-4 h-4 sm:w-3.5 sm:h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 20.5v-6.068a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" />
        </svg>
        <span class="inline">Filters</span>
        <span x-show="activeFiltersCount()" x-cloak class="min-w-4 h-4 px-1 rounded-full bg-indigo-600 text-white text-[10px] leading-4 text-center font-bold" x-text="activeFiltersCount()"></span>
      </button>

      {{-- Desktop Dropdown (hidden on mobile, visible on md: screens) --}}
      <div x-show="filtersOpen" @click.outside="filtersOpen = false" x-cloak
           class="hidden md:block absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 shadow-2xl z-[60]"
           x-transition:enter="transition ease-out duration-100"
           x-transition:enter-start="opacity-0 scale-95"
           x-transition:enter-end="opacity-100 scale-100">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-2 mb-3">
          <p class="text-[10px] uppercase font-black tracking-widest text-slate-400">Board filters</p>
          <button type="button" @click="clearFilters()" class="text-[10px] font-black text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">Clear</button>
        </div>
        <div class="space-y-3">
          {{-- SMM / Planning / Workflow Filters --}}
          <template x-if="board?.name?.toLowerCase().includes('smm') || board?.name?.toLowerCase().includes('planning') || board?.name?.toLowerCase().includes('workflow') || board?.template === 'workflow'">
            <div class="space-y-3">
              {{-- Team Filter for Planning Boards & SMM Planning Boards (hidden on Workflow boards) --}}
              <template x-if="!isWorkflowBoard()">
                <label class="block">
                  <span class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 mb-1">Team</span>
                  <template x-if="isNormalPlanningBoard() && !canFilterAllTeams() && currentUser?.team">
                    <input type="text" :value="'Team ' + currentUser.team + ' (Your Team)'" disabled readonly class="form-input w-full rounded-xl text-xs bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-600 cursor-not-allowed font-bold">
                  </template>
                  <template x-if="isSmmPlanningBoard() || canFilterAllTeams() || !currentUser?.team">
                    <select x-model="filterTeam" class="form-input w-full rounded-xl text-xs bg-slate-50 dark:bg-slate-700 border-slate-200 dark:border-slate-600 text-slate-800 dark:text-slate-200 font-medium">
                      <option value="">All Teams (Team A & B)</option>
                      <option value="A">Team A</option>
                      <option value="B">Team B</option>
                    </select>
                  </template>
                </label>
              </template>

              <label class="block">
                <span class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 mb-1">Assign By</span>
                <select x-model="filterAssignBy" class="form-input w-full rounded-xl text-xs bg-slate-50 dark:bg-slate-700 border-slate-200 dark:border-slate-600 text-slate-800 dark:text-slate-200 font-medium">
                  <option value="">Anyone</option>
                  <template x-for="member in (filterAvailableMembers && filterAvailableMembers.length ? filterAvailableMembers : (allBoardMembers || []))" :key="member.id">
                    <option :value="member.id" x-text="member.name"></option>
                  </template>
                </select>
              </label>
              
              <label class="block">
                <span class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 mb-1">Assign To</span>
                <select x-model="filterAssignee" class="form-input w-full rounded-xl text-xs bg-slate-50 dark:bg-slate-700 border-slate-200 dark:border-slate-600 text-slate-800 dark:text-slate-200 font-medium">
                  <option value="">Anyone</option>
                  <template x-for="member in (filterAvailableMembers && filterAvailableMembers.length ? filterAvailableMembers : (allBoardMembers || []))" :key="member.id">
                    <option :value="member.id" x-text="member.name"></option>
                  </template>
                </select>
              </label>

              <label class="block">
                <span class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 mb-1">Label</span>
                <select x-model="filterLabel" class="form-input w-full rounded-xl text-xs bg-slate-50 dark:bg-slate-700 border-slate-200 dark:border-slate-600 text-slate-800 dark:text-slate-200 font-medium">
                  <option value="">Any Label</option>
                  <template x-for="lbl in labels" :key="lbl.id">
                    <option :value="lbl.id" x-text="lbl.name"></option>
                  </template>
                </select>
              </label>

              <label class="block">
                <span class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 mb-1">Approval Status</span>
                <select x-model="filterStatus" class="form-input w-full rounded-xl text-xs bg-slate-50 dark:bg-slate-700 border-slate-200 dark:border-slate-600 text-slate-800 dark:text-slate-200 font-medium">
                  <option value="">All Statuses</option>
                  <option value="approved">Approved Only</option>
                  <option value="unapproved">Unapproved</option>
                </select>
              </label>

              {{-- Public Date Filter --}}
              <label class="block">
                <div class="flex items-center justify-between mb-1">
                  <span class="block text-[11px] font-bold text-slate-500 dark:text-slate-400">Public Date</span>
                  <button type="button" x-show="filterPublicDate" @click="filterPublicDate = ''" class="text-[10px] font-black text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">Clear</button>
                </div>
                <input type="date" x-model="filterPublicDate" class="form-input w-full rounded-xl text-xs bg-slate-50 dark:bg-slate-700 border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200">
              </label>
            </div>
          </template>

          {{-- Standard Filters --}}
          <template x-if="!(board?.name?.toLowerCase().includes('smm') || board?.name?.toLowerCase().includes('planning') || board?.name?.toLowerCase().includes('workflow') || board?.template === 'workflow')">
            <div class="space-y-3">
              <label class="block">
                <span class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 mb-1">Member</span>
                <select x-model="filterAssignee" class="form-input w-full rounded-xl text-xs bg-slate-50 dark:bg-slate-700 border-slate-200 dark:border-slate-600 text-slate-800 dark:text-slate-200 font-medium">
                  <option value="">All members</option>
                  <template x-for="member in (filterAvailableMembers && filterAvailableMembers.length ? filterAvailableMembers : (allBoardMembers || []))" :key="member.id">
                    <option :value="member.id" x-text="member.name"></option>
                  </template>
                </select>
              </label>

              <label class="block">
                <span class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 mb-1">Label</span>
                <select x-model="filterLabel" class="form-input w-full rounded-xl text-xs bg-slate-50 dark:bg-slate-700 border-slate-200 dark:border-slate-600 text-slate-800 dark:text-slate-200 font-medium">
                  <option value="">Any Label</option>
                  <template x-for="lbl in labels" :key="lbl.id">
                    <option :value="lbl.id" x-text="lbl.name"></option>
                  </template>
                </select>
              </label>

              {{-- Public / Due Date Filter --}}
              <label class="block">
                <div class="flex items-center justify-between mb-1">
                  <span class="block text-[11px] font-bold text-slate-500 dark:text-slate-400">Public / Due Date</span>
                  <button type="button" x-show="filterPublicDate" @click="filterPublicDate = ''" class="text-[10px] font-black text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">Clear</button>
                </div>
                <input type="date" x-model="filterPublicDate" class="form-input w-full rounded-xl text-xs bg-slate-50 dark:bg-slate-700 border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200">
              </label>
            </div>
          </template>
        </div>
      </div>

      {{-- Mobile Filter Drawer / Bottom Sheet (Trello-Style Mobile Native Experience) --}}
      <div class="md:hidden" x-show="filtersOpen" x-cloak>
        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-slate-950/65 backdrop-blur-xs z-[9998] transition-opacity"
             @click="filtersOpen = false"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"></div>

        {{-- Bottom Sheet Modal Container --}}
        <div class="fixed inset-x-0 bottom-0 max-h-[88vh] bg-white dark:bg-slate-900 rounded-t-3xl shadow-2xl z-[9999] flex flex-col border-t border-slate-200/80 dark:border-slate-800 overflow-hidden"
             x-transition:enter="transform transition ease-out duration-250"
             x-transition:enter-start="translate-y-full"
             x-transition:enter-end="translate-y-0"
             x-transition:leave="transform transition ease-in duration-200"
             x-transition:leave-start="translate-y-0"
             x-transition:leave-end="translate-y-full">
          
          {{-- Grab Handle --}}
          <div class="pt-3 pb-1 flex justify-center flex-shrink-0 cursor-pointer" @click="filtersOpen = false">
            <div class="w-12 h-1.5 rounded-full bg-slate-300 dark:bg-slate-700"></div>
          </div>

          {{-- Sheet Header --}}
          <div class="px-5 py-3 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 20.5v-6.068a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" />
                </svg>
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <h3 class="font-extrabold text-slate-800 dark:text-slate-100 text-base leading-none">Filter Cards</h3>
                  <span x-show="activeFiltersCount()" x-cloak class="px-2 py-0.5 rounded-full bg-indigo-600 text-white text-[11px] font-bold" x-text="activeFiltersCount() + ' active'"></span>
                </div>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Filter cards across all lists</p>
              </div>
            </div>
            
            <div class="flex items-center gap-2">
              <button type="button" @click="clearFilters(true)" x-show="activeFiltersCount()" x-cloak
                      class="text-xs font-bold text-rose-500 hover:text-rose-600 py-1.5 px-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 transition active:scale-95">
                Clear all
              </button>
              <button type="button" @click="filtersOpen = false" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 flex items-center justify-center transition" aria-label="Close filters">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
              </button>
            </div>
          </div>

          {{-- Sheet Content (Scrollable with thumb-friendly controls) --}}
          <div class="p-5 overflow-y-auto space-y-4 max-h-[calc(88vh-135px)] overscroll-contain">
            {{-- SMM / Planning / Workflow Boards --}}
            <template x-if="board?.name?.toLowerCase().includes('smm') || board?.name?.toLowerCase().includes('planning') || board?.name?.toLowerCase().includes('workflow') || board?.template === 'workflow'">
              <div class="space-y-4">
                {{-- Team Filter for Planning Boards & SMM Planning Boards (hidden on Workflow boards) --}}
                <template x-if="!isWorkflowBoard()">
                  <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Team</label>
                    <template x-if="isNormalPlanningBoard() && !canFilterAllTeams() && currentUser?.team">
                      <div class="flex items-center gap-2 p-3 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 font-bold text-sm">
                        <span class="w-3 h-3 rounded-full" :class="currentUser.team === 'A' ? 'bg-blue-600' : 'bg-emerald-600'"></span>
                        <span x-text="'Team ' + currentUser.team + ' (Your Assigned Team)'"></span>
                      </div>
                    </template>
                    <template x-if="isSmmPlanningBoard() || canFilterAllTeams() || !currentUser?.team">
                      <div class="grid grid-cols-3 gap-2 p-1 bg-slate-100 dark:bg-slate-800/90 rounded-2xl border border-slate-200/80 dark:border-slate-700/80">
                        <button type="button" @click="filterTeam = ''"
                                class="py-2.5 px-2 rounded-xl font-bold text-xs transition-all text-center flex items-center justify-center gap-1 active:scale-95"
                                :class="!filterTeam ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm border border-slate-200/80 dark:border-slate-700' : 'text-slate-600 dark:text-slate-400 hover:text-slate-800'">
                          All Teams
                        </button>
                        <button type="button" @click="filterTeam = 'A'"
                                class="py-2.5 px-2 rounded-xl font-bold text-xs transition-all text-center flex items-center justify-center gap-1 active:scale-95"
                                :class="filterTeam === 'A' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-800'">
                          <span class="w-2 h-2 rounded-full" :class="filterTeam === 'A' ? 'bg-white' : 'bg-blue-600'"></span>
                          Team A
                        </button>
                        <button type="button" @click="filterTeam = 'B'"
                                class="py-2.5 px-2 rounded-xl font-bold text-xs transition-all text-center flex items-center justify-center gap-1 active:scale-95"
                                :class="filterTeam === 'B' ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-800'">
                          <span class="w-2 h-2 rounded-full" :class="filterTeam === 'B' ? 'bg-white' : 'bg-emerald-600'"></span>
                          Team B
                        </button>
                      </div>
                    </template>
                  </div>
                </template>

                {{-- Assign By & Assign To Grid --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  {{-- Assign By Filter (Mobile) --}}
                  <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Assign By</label>
                    <select x-model="filterAssignBy" class="form-input w-full rounded-xl text-base sm:text-xs h-11 bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-medium">
                      <option value="">Anyone</option>
                      <template x-for="member in (filterAvailableMembers && filterAvailableMembers.length ? filterAvailableMembers : (allBoardMembers || []))" :key="member.id">
                        <option :value="member.id" x-text="member.name"></option>
                      </template>
                    </select>
                  </div>

                  {{-- Assign To Filter (Mobile) --}}
                  <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Assign To</label>
                    <select x-model="filterAssignee" class="form-input w-full rounded-xl text-base sm:text-xs h-11 bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-medium">
                      <option value="">Anyone</option>
                      <template x-for="member in (filterAvailableMembers && filterAvailableMembers.length ? filterAvailableMembers : (allBoardMembers || []))" :key="member.id">
                        <option :value="member.id" x-text="member.name"></option>
                      </template>
                    </select>
                  </div>
                </div>

                {{-- Label & Approval Status Grid --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Label</label>
                    <select x-model="filterLabel" class="form-input w-full rounded-xl text-base sm:text-xs h-11 bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-medium">
                      <option value="">Any Label</option>
                      <template x-for="lbl in labels" :key="lbl.id">
                        <option :value="lbl.id" x-text="lbl.name"></option>
                      </template>
                    </select>
                  </div>
                  <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Approval Status</label>
                    <select x-model="filterStatus" class="form-input w-full rounded-xl text-base sm:text-xs h-11 bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-medium">
                      <option value="">All Statuses</option>
                      <option value="approved">Approved Only</option>
                      <option value="unapproved">Unapproved</option>
                    </select>
                  </div>
                </div>

                {{-- Public Date Filter --}}
                <div>
                  <div class="flex items-center justify-between mb-1.5">
                    <span class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Public Date</span>
                    <button type="button" x-show="filterPublicDate" @click="filterPublicDate = ''" class="text-xs font-bold text-rose-500 hover:text-rose-600">Clear Date</button>
                  </div>
                  <input type="date" x-model="filterPublicDate" class="form-input w-full rounded-xl text-base sm:text-xs h-11 bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                </div>
              </div>
            </template>

            {{-- Standard Boards Filter --}}
            <template x-if="!(board?.name?.toLowerCase().includes('smm') || board?.name?.toLowerCase().includes('planning') || board?.name?.toLowerCase().includes('workflow') || board?.template === 'workflow')">
              <div class="space-y-4">
                {{-- Member Filter (Mobile) --}}
                <div>
                  <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Member</label>
                  <select x-model="filterAssignee" class="form-input w-full rounded-xl text-base sm:text-xs h-11 bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-medium">
                    <option value="">All members</option>
                    <template x-for="member in (filterAvailableMembers && filterAvailableMembers.length ? filterAvailableMembers : (allBoardMembers || []))" :key="member.id">
                      <option :value="member.id" x-text="member.name"></option>
                    </template>
                  </select>
                </div>

                {{-- Label Filter (Mobile) --}}
                <div>
                  <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Label</label>
                  <select x-model="filterLabel" class="form-input w-full rounded-xl text-base sm:text-xs h-11 bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-medium">
                    <option value="">Any Label</option>
                    <template x-for="lbl in labels" :key="lbl.id">
                      <option :value="lbl.id" x-text="lbl.name"></option>
                    </template>
                  </select>
                </div>

                <div>
                  <div class="flex items-center justify-between mb-1.5">
                    <span class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Public / Due Date</span>
                    <button type="button" x-show="filterPublicDate" @click="filterPublicDate = ''" class="text-xs font-bold text-rose-500 hover:text-rose-600">Clear Date</button>
                  </div>
                  <input type="date" x-model="filterPublicDate" class="form-input w-full rounded-xl text-base sm:text-xs h-11 bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                </div>
              </div>
            </template>
          </div>

          {{-- Bottom Sticky Apply Button --}}
          <div class="p-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/95 dark:bg-slate-900/95 backdrop-blur-xs flex-shrink-0">
            <button type="button" @click="filtersOpen = false"
                    class="w-full py-3.5 px-4 rounded-2xl bg-indigo-600 hover:bg-indigo-700 active:scale-[0.98] text-white font-extrabold text-sm shadow-md shadow-indigo-500/25 flex items-center justify-center gap-2 transition">
              <span>Apply & View Cards</span>
              <span x-show="activeFiltersCount()" x-cloak class="px-2 py-0.5 rounded-full bg-white/20 text-xs font-bold" x-text="activeFiltersCount()"></span>
            </button>
          </div>

        </div>
      </div>
    </div>

    {{-- Manage Board Members Dropdown (Only for Board Admins/Managers) --}}
    @if(auth()->user()->canManageBoardMembers($board))
      <div class="relative hidden sm:block" x-data="{ openMembers: false, search: '' }">
        <button @click="openMembers = !openMembers; search = ''" class="btn btn-secondary py-1 sm:py-1.5 px-2 sm:px-3 text-[10px] sm:text-xs flex items-center gap-1 sm:gap-1.5 font-semibold">
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
          </svg>
          <span class="hidden sm:inline">Members</span>
        </button>
        <div x-show="openMembers" @click.outside="openMembers = false" x-cloak
             class="absolute right-0 mt-2 w-64 bg-white border border-slate-200 rounded-2xl shadow-2xl z-[60] p-4"
             x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">
          <p class="text-[10px] uppercase font-black text-slate-400 pb-2 border-b border-slate-100 mb-2">Manage Board Members</p>
          
          <input type="text" x-model="search" placeholder="Search members..." class="w-full text-xs bg-slate-50 border-slate-200 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-lg py-1.5 px-2.5 mb-3" @click.stop>
          
          <div class="max-h-48 overflow-y-auto space-y-2.5 mb-2 pr-1 scrollbar-thin">
            @php
              $possibleUsers = $possibleBoardUsers ?? $board->workspace->members;
              $boardMemberIds = $boardMemberIds ?? $board->members->pluck('id');
            @endphp
            @foreach($possibleUsers as $u)
              <div x-show="search === '' || '{{ strtolower(addslashes($u->name)) }}'.includes(search.toLowerCase())" class="flex items-center justify-between gap-2 text-xs">
                <div class="flex items-center gap-1.5">
                  <img src="{{ $u->avatar_url }}" class="w-6.5 h-6.5 rounded-full object-cover border border-slate-200">
                  <span class="font-semibold text-slate-700 truncate max-w-28" title="{{ $u->name }}">{{ $u->name }}</span>
                </div>
                @if($boardMemberIds->contains($u->id) || $board->created_by === $u->id)
                  @if($board->created_by !== $u->id)
                    <button @click="removeBoardMember({{ $u->id }}, $el)" class="text-[10px] text-rose-500 font-bold hover:underline">Remove</button>
                  @else
                    <span class="text-[9px] text-slate-400 font-bold italic">Owner</span>
                  @endif
                @else
                  <button @click="addBoardMember({{ $u->id }}, $el)" class="text-[10px] text-indigo-600 font-bold hover:underline">Add</button>
                @endif
              </div>
            @endforeach
          </div>
        </div>
      </div>
    @endif


    {{-- Import Button (Visible on desktop) --}}
    <button @click="openImportModal()"
            class="hidden sm:inline-flex btn btn-secondary py-1 sm:py-1.5 px-2 sm:px-3 text-[10px] sm:text-xs items-center gap-1 sm:gap-1.5 font-semibold hover:text-white transition-colors"
            title="Import cards from CSV or Google Sheets">
      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
      </svg>
      <span class="hidden sm:inline">Import</span>
    </button>

    {{-- Board menu --}}
    <button @click="openBoardMenu('menu')" class="board-menu-btn btn btn-secondary btn-icon w-8 h-8 sm:w-9 sm:h-9" title="Board menu" aria-label="Open board menu">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM18.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
      </svg>
    </button>
  </div>
</div>

{{-- Quick Filter Toolbar for Workflow Boards, Planning Boards & SMM Planning Boards --}}
<template x-if="isWorkflowBoard() || isSmmPlanningBoard() || isPlanningBoard()">
  <div class="px-2.5 sm:px-4 py-1.5 sm:py-2 border-b border-slate-200/60 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md flex items-center justify-between gap-2 sm:gap-3 text-xs shadow-2xs overflow-x-auto no-scrollbar touch-pan-x" style="-webkit-overflow-scrolling: touch;">
    <div class="flex items-center gap-1.5 sm:gap-2 flex-nowrap min-w-max">
      {{-- Teams Switcher (All Teams, Team A, Team B) for Planning Boards & SMM Planning Boards (hidden on Workflow boards) --}}
      <template x-if="!isWorkflowBoard()">
        <div class="flex items-center gap-1.5">
          {{-- Normal Planning Board for regular user: strictly locked to their own team --}}
          <template x-if="isNormalPlanningBoard() && !canFilterAllTeams() && currentUser?.team">
            <div class="flex items-center gap-1 px-3 py-1.5 min-h-[34px] sm:min-h-0 rounded-xl text-xs font-black shadow-xs select-none"
                 :class="currentUser.team === 'A' ? 'bg-blue-600 text-white' : 'bg-emerald-600 text-white'">
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
              </svg>
              <span x-text="'Team ' + currentUser.team"></span>
            </div>
          </template>

          {{-- SMM Planning Board (for all users) OR Users who can filter all teams: can toggle All Teams, Team A, Team B --}}
          <template x-if="isSmmPlanningBoard() || canFilterAllTeams() || !currentUser?.team">
            <div class="flex items-center bg-slate-100 dark:bg-slate-800 p-0.5 rounded-xl border border-slate-200/80 dark:border-slate-700">
              <button type="button" @click="filterTeam = ''"
                      class="px-3 py-1.5 min-h-[34px] sm:min-h-0 rounded-lg font-bold transition-all text-xs sm:text-[11px] flex items-center gap-1 active:scale-95 touch-manipulation"
                      :class="!filterTeam ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-white'">
                All Teams
              </button>
              <button type="button" @click="filterTeam = 'A'"
                      class="px-3 py-1.5 min-h-[34px] sm:min-h-0 rounded-lg font-bold transition-all text-xs sm:text-[11px] flex items-center gap-1 active:scale-95 touch-manipulation"
                      :class="filterTeam === 'A' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-white'">
                <span>Team A</span>
              </button>
              <button type="button" @click="filterTeam = 'B'"
                      class="px-3 py-1.5 min-h-[34px] sm:min-h-0 rounded-lg font-bold transition-all text-xs sm:text-[11px] flex items-center gap-1 active:scale-95 touch-manipulation"
                      :class="filterTeam === 'B' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-white'">
                <span>Team B</span>
              </button>
            </div>
          </template>
        </div>
      </template>

      {{-- Divider --}}
      <div class="w-[1px] h-4 bg-slate-300 dark:bg-slate-700 flex-shrink-0 mx-0.5"></div>

      {{-- Category Quick Pills (Video, Graphic, Listing, Content, SMM) --}}
      <div class="flex items-center gap-1 sm:gap-1.5 flex-nowrap">
        <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider mr-0.5 hidden xs:inline">CATEGORY:</span>
        <button type="button" @click="filterCategory = ''"
                class="px-3.5 py-1.5 min-h-[34px] sm:min-h-0 rounded-full font-bold transition-all text-xs sm:text-[11px] border active:scale-95 touch-manipulation"
                :class="!filterCategory ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs dark:bg-indigo-600 dark:!text-white dark:border-indigo-500' : 'bg-white/80 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:border-slate-400'">
          All
        </button>
        <template x-for="cat in ['Video', 'Graphic', 'Listing', 'Content', 'SMM']" :key="cat">
          <button type="button" @click="filterCategory = (filterCategory === cat ? '' : cat)"
                  class="px-3.5 py-1.5 min-h-[34px] sm:min-h-0 rounded-full font-bold transition-all text-xs sm:text-[11px] border flex items-center gap-1 active:scale-95 whitespace-nowrap touch-manipulation"
                  :class="filterCategory === cat 
                    ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs' 
                    : 'bg-white/80 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:border-indigo-300'">
            <span x-text="cat"></span>
          </button>
        </template>
      </div>
    </div>

    <div x-show="filterCategory || (!isWorkflowBoard() && (isSmmPlanningBoard() || canFilterAllTeams() || !currentUser?.team) && filterTeam)" x-cloak class="flex-shrink-0">
      <button type="button" @click="if (isWorkflowBoard()) { filterTeam = getBoardTeam() || ''; } else if (isNormalPlanningBoard() && !canFilterAllTeams() && currentUser?.team) { filterTeam = currentUser.team; } else { filterTeam = ''; } filterCategory = '';"
              class="text-xs sm:text-[11px] font-bold text-rose-500 hover:text-rose-600 px-3 py-1.5 min-h-[34px] sm:min-h-0 rounded-full bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 whitespace-nowrap active:scale-95 flex items-center gap-1">
        Clear
      </button>
    </div>
  </div>
</template>

{{-- ── Lists row ─────────────────────────────────────────────────────── --}}
<div class="board-wrap" id="board-wrap">

  <div id="sortable-lists-container" class="flex items-start gap-4 h-full">
    <template x-for="(list, li) in lists" :key="list.id">
      <div class="board-list relative"
           x-data="{ openMenu: false }"
           :class="{ 'z-40': openMenu, 'z-10': !openMenu }"
           :id="'list-'+list.id"
           :style="zoomLevel !== 100 ? ('zoom: ' + (zoomLevel / 100)) : ''">

      {{-- List header --}}
      <div class="list-header relative z-30 flex items-center justify-between px-3 py-2 border-b border-slate-200/50 bg-slate-50/50 rounded-t-xl gap-1.5" :style="list.color ? 'border-top:3px solid '+list.color : ''">
        <div class="flex items-center gap-1.5 min-w-0 flex-1 pr-1">
          <!-- Normal view -->
          <div x-show="editingListId !== list.id" @click="startEditList(list.id, list.name)" class="cursor-pointer group flex items-center gap-1 min-w-0">
            <span class="font-extrabold text-slate-700 dark:text-slate-100 text-sm truncate" x-text="list.name" :title="list.name"></span>
            <span class="opacity-0 group-hover:opacity-100 text-[10px] text-indigo-500 font-bold transition-opacity">edit</span>
          </div>
          <!-- Edit input -->
          <div x-show="editingListId === list.id" x-cloak class="flex-1">
            <input type="text" x-model="editingListName"
                   @blur="saveListName(list.id)"
                   @keydown.enter="saveListName(list.id)"
                   @keydown.escape="editingListId = null"
                   class="form-input py-0.5 px-1.5 text-xs font-semibold text-slate-700 w-full rounded-lg"
                   :id="'list-input-'+list.id">
          </div>

          <!-- List Lead Profile Avatar (Just Profile, No Name) -->
          <template x-if="getListLead(list)">
            <div class="inline-flex items-center flex-shrink-0 cursor-pointer"
                 :title="(getListLead(list).display_name || getListLead(list).name) + (getListLead(list).role ? ' • ' + getListLead(list).role : '')">
              <template x-if="getListLead(list).avatar">
                <img :src="getListLead(list).avatar" 
                     :alt="getListLead(list).display_name || getListLead(list).name"
                     class="list-lead-avatar">
              </template>
              <template x-if="!getListLead(list).avatar">
                <span class="list-lead-avatar flex items-center justify-center text-[11px] font-black text-white shadow-xs"
                      :style="'background-color: ' + (getListLead(list).avatar_color || '#4f46e5')"
                      x-text="getListLead(list).initials || 'U'"></span>
              </template>
            </div>
          </template>
        </div>
        
        <div class="flex items-center gap-1.5 flex-shrink-0">
          <span class="text-[10px] text-slate-400 font-bold" x-text="filteredCards(list).length"></span>
          
          <!-- Dropdown menu -->
          <div class="relative z-50">
            <button @click="openMenu = !openMenu"
                    class="board-list-menu-trigger text-slate-400 hover:text-slate-600 dark:text-slate-400 dark:hover:text-slate-200 focus:outline-none p-1 rounded-lg hover:bg-slate-200/50 dark:hover:bg-slate-700/50 flex items-center transition cursor-pointer"
                    title="List options"
                    aria-label="List options">
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM18.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
              </svg>
            </button>
            <div x-show="openMenu" @click.outside="openMenu = false" x-cloak
                 class="board-list-menu absolute right-0 mt-1.5 w-44 rounded-xl shadow-2xl z-[100] py-1.5"
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
              <button @click="openMenu = false; startEditList(list.id, list.name)" class="w-full text-left px-3.5 py-2 text-xs flex items-center gap-1.5 font-medium cursor-pointer">
                ✏️ Rename
              </button>
              <button @click="openMenu = false; isSelectMode ? exitSelectMode() : startSelectMode()" class="w-full text-left px-3.5 py-2 text-xs text-indigo-600 flex items-center gap-1.5 font-medium cursor-pointer">
                <span x-text="isSelectMode ? '✖️ Deselect' : '☑️ Select'">☑️ Select</span>
              </button>
              <button @click="openMenu = false; selectAllInList(list.id)" class="w-full text-left px-3.5 py-2 text-xs text-indigo-600 flex items-center gap-1.5 font-medium cursor-pointer">
                ☑️ Select All
              </button>
              <button @click="openMenu = false; archiveList(list.id)" class="w-full text-left px-3.5 py-2 text-xs text-amber-600 flex items-center gap-1.5 font-medium cursor-pointer">
                📦 Archive
              </button>
              <button @click="openMenu = false; deleteList(list.id)" class="w-full text-left px-3.5 py-2 text-xs text-rose-600 flex items-center gap-1.5 font-medium cursor-pointer" title="Delete list and move cards to Trash">
                🗑️ Delete List
              </button>
              @if(auth()->check() && auth()->user()->canClearBoardList())
              <div class="board-list-menu-divider my-1"></div>
              <button @click="openMenu = false; clearList(list.id)" class="w-full text-left px-3.5 py-2 text-xs text-rose-600 flex items-center gap-1.5 font-medium cursor-pointer" title="Delete all cards in this list">
                🧹 Clear List
              </button>
              @endif
            </div>
          </div>
        </div>
      </div>

      {{-- Cards Container with SortableJS hook --}}
      <div class="list-cards relative z-10 flex-1 overflow-y-auto min-h-12 pb-8 scrollbar-thin transition-colors" :id="'cards-'+list.id" :data-list-id="list.id">
        <template x-for="card in filteredCards(list)" :key="card.id">
          <div class="kanban-card select-none relative"
               :class="['priority-' + card.priority, isSelectMode ? 'cursor-pointer' : (canDragCard(card, list) ? 'cursor-grab active:cursor-grabbing' : 'cursor-pointer'), (selectedCards || []).includes(card.id) ? 'ring-2 ring-indigo-500 bg-indigo-50/30 dark:bg-indigo-950/50' : '']"
               :data-id="card.id"
               :data-can-drag="!isSelectMode && canDragCard(card, list) ? '1' : '0'"
               @click="isSelectMode ? toggleCardSelection(card.id) : openCard(card.id)"
               @contextmenu.prevent="isSelectMode ? toggleCardSelection(card.id) : openCtxMenu($event, card, list)">

            {{-- Bulk Select Checkbox --}}
            <div x-show="isSelectMode" x-cloak class="absolute top-2 right-2 pointer-events-none">
                <input type="checkbox" :checked="(selectedCards || []).includes(card.id)" class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500 cursor-pointer">
            </div>

            {{-- Quick-action ⋮ button (hover-visible) --}}
            <button class="card-quick-btn"
                    x-show="!isSelectMode"
                    @click.stop="openCtxMenu($event, card, list)"
                    @touchstart.stop
                    @touchend.stop
                    title="Quick actions">
              <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor" class="text-slate-500">
                <circle cx="10" cy="4" r="1.8"/><circle cx="10" cy="10" r="1.8"/><circle cx="10" cy="16" r="1.8"/>
              </svg>
            </button>

            {{-- Standard Trello Layout --}}
            <div class="pr-6 sm:pr-0">
                {{-- Labels and Workflow Status --}}
                <div class="flex items-start justify-between mb-2.5 mt-1">
                  <div x-show="card.labels && card.labels.length" class="flex flex-wrap gap-1">
                    <template x-for="lbl in card.labels" :key="lbl.id">
                      <span class="kanban-card-label inline-flex items-center"
                            :style="'background:'+lbl.color" :title="lbl.name"></span>
                    </template>
                  </div>
                  
                  <template x-if="board?.name?.toLowerCase().includes('planning') && card.workflow_status">
                      <span class="text-[10px] font-bold px-2 py-1 rounded uppercase tracking-wider border whitespace-nowrap ml-2"
                           :class="{
                              'bg-amber-50 text-amber-600 border-amber-200': card.workflow_status === 'Draft',
                              'bg-blue-50 text-blue-600 border-blue-200': card.workflow_status === 'Head',
                              'bg-sky-50 text-sky-600 border-sky-200': card.workflow_status === 'Production Team' || card.workflow_status === 'Production',
                              'bg-purple-50 text-purple-600 border-purple-200': card.workflow_status === 'QC',
                              'bg-indigo-50 text-indigo-600 border-indigo-200': card.workflow_status === 'Supervisor' || card.workflow_status === 'Digital Department',
                              'bg-emerald-50 text-emerald-600 border-emerald-200': card.workflow_status === 'Approved',
                              'bg-rose-50 text-rose-600 border-rose-200': card.workflow_status === 'Block/waiting' || card.workflow_status === 'Blocked'
                           }"
                           x-text="card.workflow_status">
                      </span>
                  </template>
                </div>

                {{-- Title & Team Badge on Planning Boards --}}
                <div class="flex items-start justify-between gap-2 mb-2">
                  <div class="flex items-center gap-1.5 flex-wrap">
                    {{-- Both Teams badge --}}
                    <template x-if="isCardBothTeams(card)">
                      <div class="inline-flex items-center gap-1 select-none">
                        <span class="inline-flex items-center justify-center font-black text-[10px] px-1.5 py-0.5 rounded font-mono shadow-xs bg-blue-600 text-white" title="Team A">A</span>
                        <span class="inline-flex items-center justify-center font-black text-[10px] px-1.5 py-0.5 rounded font-mono shadow-xs bg-emerald-600 text-white" title="Team B">B</span>
                      </div>
                    </template>

                    {{-- Single Team A or B badge --}}
                    <template x-if="!isCardBothTeams(card) && (isPlanningBoard() || isSmmPlanningBoard()) && (card.team === 'A' || card.team === 'B')">
                      <span class="inline-flex items-center justify-center font-black text-[11px] px-2 py-0.5 rounded font-mono shadow-xs select-none"
                            :class="card.team === 'A' ? 'bg-blue-600 text-white' : 'bg-emerald-600 text-white'"
                            :title="'Team ' + card.team"
                            x-text="card.team">
                      </span>
                    </template>
                    <p class="kanban-card-title !mb-0 !pr-0 inline"
                       x-text="card.title"></p>
                  </div>
                  <template x-if="board?.name?.toLowerCase().includes('smm') && list.name === 'Final Captions'">
                    <button type="button"
                            @click.stop="toggleSupervisorApprove(card, list)"
                            :class="card.status === 'Approved' || card.status === 'approved'
                              ? 'border-emerald-400 bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white shadow-sm ring-1 ring-emerald-500/30'
                              : 'border-slate-300 bg-white text-slate-300 hover:border-emerald-400 hover:bg-emerald-50 hover:text-emerald-500 shadow-sm'"
                            class="w-7 h-7 rounded-full border flex flex-shrink-0 items-center justify-center mt-0.5 transition relative group cursor-pointer"
                            aria-label="Toggle approval">
                      <!-- Tooltip -->
                      <span class="absolute right-full mr-2 top-1/2 -translate-y-1/2 invisible group-hover:visible opacity-0 group-hover:opacity-100 transition-all bg-slate-800 text-white text-[10px] font-bold px-2 py-1 rounded whitespace-nowrap shadow-md z-10" x-text="card.status === 'Approved' || card.status === 'approved' ? 'Click to untick' : 'Click to approve'"></span>
                      
                      <svg x-show="card.status !== 'Approved' && card.status !== 'approved'" class="w-4 h-4 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <circle cx="12" cy="12" r="8.5" />
                      </svg>
                      <svg x-show="card.status === 'Approved' || card.status === 'approved'" x-cloak class="w-4 h-4 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                      </svg>
                    </button>
                  </template>
                  <template x-if="!(board?.name?.toLowerCase().includes('smm') && list.name === 'Final Captions')">
                    <svg x-show="list.name.toLowerCase().includes('approved') || card.status === 'Approved' || card.status === 'approved'" 
                         class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5 drop-shadow-sm" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                  </template>
                  {{-- Block / Waiting List Fix Indicator & Action with High-Quality UI Tooltip --}}
                  <div x-show="isBlockList(list)" x-cloak class="relative flex items-center group/blockfix flex-shrink-0 mt-0.5" data-no-drag>
                    {{-- Action button for supervisors / managers --}}
                    <button type="button"
                            x-show="currentUser.can_manage_blocked_cards"
                            @click.stop="completeBlockedCard(card, list)"
                            :class="card.block_completed_at
                              ? 'is-fixed border-emerald-400 bg-emerald-50 text-emerald-600 ring-1 ring-emerald-500/30 hover:bg-emerald-500 hover:text-white dark:border-emerald-400 dark:bg-emerald-500/25 dark:text-emerald-300'
                              : 'is-unfixed border-slate-300 bg-white text-slate-400 ring-1 ring-slate-200/80 hover:border-emerald-400 hover:text-emerald-500 hover:bg-emerald-50/50 dark:border-cyan-500/40 dark:bg-slate-900/60 dark:text-cyan-400 dark:ring-cyan-500/20 dark:hover:border-cyan-300 dark:hover:bg-cyan-500/20'"
                            class="block-fix-btn shadow-sm active:scale-95"
                            aria-label="Toggle Block Status">
                      <svg x-show="!card.block_completed_at" class="w-3.5 h-3.5 pointer-events-none stroke-[2.4]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <circle cx="12" cy="12" r="8.5" />
                      </svg>
                      <svg x-show="card.block_completed_at" x-cloak class="w-3.5 h-3.5 pointer-events-none stroke-[3]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                      </svg>
                    </button>

                    {{-- Read-only icon for non-managers --}}
                    <div x-show="!currentUser.can_manage_blocked_cards"
                         :class="card.block_completed_at
                           ? 'is-fixed border-emerald-400 bg-emerald-50 text-emerald-600 ring-1 ring-emerald-500/30 dark:border-emerald-400 dark:bg-emerald-500/25 dark:text-emerald-300'
                           : 'is-unfixed border-slate-300 bg-slate-100 text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-500'"
                         class="block-fix-btn cursor-default shadow-sm">
                      <svg x-show="!card.block_completed_at" class="w-3.5 h-3.5 pointer-events-none stroke-[2.4]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <circle cx="12" cy="12" r="8.5" />
                      </svg>
                      <svg x-show="card.block_completed_at" x-cloak class="w-3.5 h-3.5 pointer-events-none stroke-[3]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                      </svg>
                    </div>

                    {{-- Custom UI Tooltip Pop-up --}}
                    <div class="block-fix-tooltip" role="tooltip">
                      <span class="w-2 h-2 rounded-full flex-shrink-0"
                            :class="card.block_completed_at
                              ? (currentUser.can_manage_blocked_cards ? 'bg-amber-400 shadow-[0_0_8px_#fbbf24]' : 'bg-emerald-400 shadow-[0_0_8px_#34d399]')
                              : 'bg-emerald-400 shadow-[0_0_8px_#34d399] animate-pulse'"></span>
                      <span class="block-fix-tooltip-text font-semibold text-xs tracking-wide"
                            x-text="currentUser.can_manage_blocked_cards
                              ? (card.block_completed_at ? 'Unmark' : 'Mark to fix')
                              : (card.block_completed_at ? 'Fixed by Supervisor' : 'Pending Fix')"></span>
                    </div>
                  </div>
                </div>

                {{-- Meta row --}}
                <div class="flex items-center gap-2 flex-wrap">
                  {{-- Due date / Public date --}}
                  <span x-show="card.content_public_date || card.due_at"
                        :class="isOverdue(card) ? 'bg-red-100 text-red-600' : (card.content_public_date ? 'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-400' : 'bg-slate-100 text-slate-500')"
                        class="kanban-card-meta font-bold px-2 py-1 rounded-lg"
                        x-text="formatDate(card.content_public_date || card.due_at)"
                        :title="(card.content_public_date ? 'Public Date: ' : 'Due Date: ') + formatDateShort(card.content_public_date || card.due_at)"></span>

                  {{-- Checklist --}}
                  <span x-show="card.checklist_total > 0"
                        class="kanban-card-meta text-slate-500 font-bold flex items-center gap-1">
                    ✓ <span x-text="card.checklist_done+'/'+card.checklist_total"></span>
                  </span>

                  {{-- Files --}}
                  <span x-show="card.has_files" class="kanban-card-meta text-slate-500">📎</span>

                  {{-- Comments --}}
                  <span x-show="card.comment_count > 0"
                        class="kanban-card-meta text-slate-500 font-bold flex items-center gap-1">
                    💬 <span x-text="card.comment_count"></span>
                  </span>

                  {{-- Single Assignee (compact inline on meta row) --}}
                  <template x-if="card.assignees && card.assignees.length === 1">
                    <div class="ml-auto flex items-center min-w-0 max-w-[140px] flex-shrink-0"
                         data-no-drag
                         @click.stop
                         @mousedown.stop>
                      <div class="flex items-center gap-1 bg-slate-50 dark:bg-slate-800/90 rounded-full pl-1.5 pr-0.5 py-0.5 border border-slate-200/80 dark:border-slate-700 shadow-2xs group max-w-full" :title="card.assignees[0].name">
                        <span class="text-[9px] font-bold text-slate-600 dark:text-slate-300 truncate max-w-[95px] select-text" x-text="card.assignees[0].name"></span>
                        <template x-if="avatarUrl(card.assignees[0])">
                          <img :src="avatarUrl(card.assignees[0])" :alt="card.assignees[0].name" :title="card.assignees[0].name"
                               class="w-5 h-5 rounded-full object-cover border border-white dark:border-slate-800 shadow-2xs flex-shrink-0">
                        </template>
                        <template x-if="!avatarUrl(card.assignees[0])">
                          <span class="w-5 h-5 rounded-full flex items-center justify-center text-[8px] font-black text-white border border-white dark:border-slate-800 shadow-2xs flex-shrink-0"
                                :style="avatarStyle(card.assignees[0])"
                                x-text="avatarInitials(card.assignees[0])"
                                :title="card.assignees[0].name"></span>
                        </template>
                      </div>
                    </div>
                  </template>
                </div>

                {{-- Multiple Assignees (Responsive Scrollable Member Track) --}}
                <template x-if="card.assignees && card.assignees.length > 1">
                  <div class="relative w-full mt-2 pt-1.5 border-t border-slate-100/80 dark:border-slate-800/80 min-w-0 group/members"
                       data-no-drag
                       @click.stop
                       @mousedown.stop>
                    <div class="card-assignees-scroll w-full flex items-center gap-1.5 overflow-x-auto scrollbar-none py-0.5 touch-pan-x cursor-grab active:cursor-grabbing select-none"
                         x-data="{ isDown: false, startX: 0, sLeft: 0 }"
                         @mousedown="isDown = true; startX = $event.pageX - $el.offsetLeft; sLeft = $el.scrollLeft"
                         @mouseleave="isDown = false"
                         @mouseup="isDown = false"
                         @mousemove="if(!isDown) return; $event.preventDefault(); const x = $event.pageX - $el.offsetLeft; $el.scrollLeft = sLeft - (x - startX) * 1.4;"
                         @wheel.stop.prevent="$el.scrollLeft += ($event.deltaY || $event.deltaX)">
                      <template x-for="u in card.assignees" :key="u.id">
                        <div class="flex items-center gap-1 bg-slate-50 dark:bg-slate-800/90 rounded-full pl-1.5 pr-0.5 py-0.5 border border-slate-200/80 dark:border-slate-700 shadow-2xs flex-shrink-0 hover:border-indigo-400 dark:hover:border-indigo-500 transition-colors" :title="u.name">
                          <span class="text-[9px] font-bold text-slate-600 dark:text-slate-300 truncate max-w-[105px] select-text" x-text="u.name"></span>
                          <template x-if="avatarUrl(u)">
                            <img :src="avatarUrl(u)" :alt="u.name" :title="u.name"
                                 class="w-5 h-5 rounded-full object-cover border border-white dark:border-slate-800 shadow-2xs flex-shrink-0">
                          </template>
                          <template x-if="!avatarUrl(u)">
                            <span class="w-5 h-5 rounded-full flex items-center justify-center text-[8px] font-black text-white border border-white dark:border-slate-800 shadow-2xs flex-shrink-0"
                                  :style="avatarStyle(u)"
                                  x-text="avatarInitials(u)"
                                  :title="u.name"></span>
                          </template>
                        </div>
                      </template>
                    </div>
                  </div>
                </template>
              </div>
          </div>
        </template>
      </div>

      {{-- Add card button --}}
      <div class="px-2 pb-2">
        <div x-show="addingCardListId !== list.id">
          <button @click="startAddCard(list.id)"
                  class="w-full text-left text-xs text-slate-500 hover:text-indigo-600 hover:bg-white/60 dark:hover:bg-slate-800/60 px-3 py-2.5 min-h-[38px] rounded-xl transition-all flex items-center gap-2 font-bold active:scale-[0.98]">
            <svg class="w-4 h-4 text-indigo-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Add a card
          </button>
        </div>

        {{-- Inline add card form --}}
        <div x-show="addingCardListId === list.id" x-cloak>
          <textarea x-model="newCardTitle" @keydown.enter.prevent="saveCard(list.id)"
                    @keydown.escape="addingCardListId = null; newCardTeam = null; newCardAssignedTeam = null"
                    rows="2" placeholder="Card title…"
                    class="form-input text-sm resize-none w-full mb-2 rounded-xl"
                    x-ref="'newcard_'+list.id"
                    :x-ref="'newcard_'+list.id"></textarea>

          {{-- If manager and board is planning or workflow, allow selecting team A or B or Both --}}
          <template x-if="(isPlanningBoard() || isWorkflowBoard() || isSmmPlanningBoard()) && (currentUser?.is_special_manager || canFilterAllTeams() || !currentUser?.team)">
            <div class="flex items-center gap-1.5 mb-2 text-[11px] flex-wrap">
              <span class="text-slate-400 font-bold">Assign to:</span>
              <button type="button" @click="newCardAssignedTeam = 'A'"
                      class="px-2 py-0.5 rounded font-bold transition-all"
                      :class="(newCardAssignedTeam === 'A' || (!newCardAssignedTeam && filterTeam === 'A')) ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                Team A
              </button>
              <button type="button" @click="newCardAssignedTeam = 'B'"
                      class="px-2 py-0.5 rounded font-bold transition-all"
                      :class="(newCardAssignedTeam === 'B' || (!newCardAssignedTeam && filterTeam === 'B')) ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                Team B
              </button>
              <button type="button" @click="newCardAssignedTeam = 'Both'"
                      class="px-2 py-0.5 rounded font-bold transition-all"
                      :class="(newCardAssignedTeam === 'Both') ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                Both (A & B)
              </button>
            </div>
          </template>

          <div class="flex gap-2 items-center">
            <button @click="saveCard(list.id)" class="btn btn-primary text-xs py-1.5 px-3">Add</button>
            <button @click="addingCardListId = null; newCardTeam = null; newCardAssignedTeam = null" class="text-xs text-slate-400 hover:text-slate-600">✕</button>
          </div>
        </div>
      </div>
    </div>
  </template>

  {{-- Add list button --}}
  <div class="add-list-wrapper flex-shrink-0" :style="zoomLevel !== 100 ? ('zoom: ' + (zoomLevel / 100)) : ''">
    <button type="button" x-show="!addingList" class="add-list-btn border border-dashed border-white/40 text-white rounded-xl hover:border-white hover:bg-white/20 transition-colors drop-shadow-sm font-semibold" @click.stop="addingList=true; setTimeout(() => { $refs.addListInput.focus() }, 100)">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
      Add another list
    </button>
    <div x-show="addingList" x-cloak class="adding-list-container bg-slate-50 p-3 border border-slate-200 shadow-sm rounded-xl w-[272px]">
      <input x-ref="addListInput" x-model="newListName" type="text" placeholder="List name…"
             @keydown.enter="saveList" @keydown.escape="addingList=false"
             class="form-input text-xs mb-2 rounded-xl w-full">
      <div class="flex gap-2">
        <button @click="saveList" class="btn btn-primary text-xs py-1.5 px-3">Add List</button>
        <button @click="addingList=false" class="text-xs text-slate-400 hover:text-slate-600 font-semibold cursor-pointer z-50 relative">✕</button>
      </div>
    </div>
  </div>

  </div> <!-- Close sortable-lists-container -->
</div> <!-- Close board-wrap -->



{{-- ── Board Activity Feed Side Drawer ────────────────────────────────── --}}
<div x-show="activityOpen" class="fixed inset-0 z-50 overflow-hidden" x-cloak>
  <!-- Backdrop overlay -->
  <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" 
       @click="activityOpen = false"
       x-show="activityOpen"
       x-transition:enter="ease-out duration-300"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="ease-in duration-200"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0"></div>

  <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
    <div class="w-screen max-w-md"
         x-show="activityOpen"
         x-transition:enter="transform transition ease-out duration-300"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transform transition ease-in duration-200"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full">
      <div class="h-full flex flex-col bg-white shadow-2xl border-l border-slate-200">
        <!-- Drawer Header -->
        <div class="p-6 bg-slate-50 border-b border-slate-200/60 flex items-center justify-between">
          <h2 class="text-sm font-black text-slate-800 flex items-center gap-2">
            <span>📜 Board Activity Log</span>
          </h2>
          <button @click="activityOpen = false" class="text-slate-400 hover:text-slate-600 transition-colors">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <!-- Drawer Feed List -->
        <div class="flex-1 overflow-y-auto p-6 space-y-4.5 scrollbar-thin select-none">
          <template x-for="act in activities" :key="act.id">
            <div class="flex gap-3 text-xs leading-normal">
              <img :src="act.user_avatar || window.dgtInitialsAvatar(act.user_name || 'System', act.user_avatar_color || '#64748b')" class="w-8 h-8 rounded-full object-cover border border-slate-200 flex-shrink-0 mt-0.5">
              <div class="flex-1">
                <p class="text-slate-700">
                  <strong class="font-bold text-slate-800" x-text="act.user_name"></strong> 
                  <span x-html="parseMarkdown(typeof formatActivityDescription === 'function' ? formatActivityDescription(act.description || '') : (act.description || ''))"></span>
                </p>
                <span class="text-[9px] text-slate-400 font-bold block mt-1" x-text="act.time_ago"></span>
              </div>
            </div>
          </template>
          <template x-if="activities.length === 0">
            <div class="py-12 text-center text-slate-400 font-semibold">
              🌱 No activities recorded on this board yet.
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</div>

@include('boards.partials.board-menu')

{{-- ── Card Context Menu ─────────────────────────────────────────────── --}}
{{-- Rendered once; positioned via JS --}}
<div id="card-ctx-menu" class="hidden" @click.outside="closeCtxMenu()">

  {{-- Open card --}}
  <div class="ctx-item" @click="ctxAction('open')">
    <svg class="w-3.5 h-3.5 text-indigo-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
    Open card
  </div>

  <div class="ctx-sep"></div>

  {{-- Edit labels --}}
  <div class="ctx-item" @click="ctxAction('labels')">
    <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/></svg>
    Edit labels
  </div>

  {{-- Change members --}}
  <div class="ctx-item" @click="ctxAction('members')">
    <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
    Change members
  </div>

  {{-- Change cover --}}
  <div class="ctx-item" x-show="board.card_covers_enabled !== false" @click="ctxAction('cover')">
    <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/></svg>
    Change cover
  </div>

  {{-- Edit dates --}}
  <div class="ctx-item" @click="ctxAction('dates')">
    <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
    Edit dates
  </div>

  <div class="ctx-sep"></div>

  {{-- Move --}}
  <div class="ctx-item" @click="ctxAction('move')">
    <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
    Move
  </div>

  {{-- Copy card --}}
  <div class="ctx-item" @click="ctxAction('copy')">
    <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75"/></svg>
    Copy card
  </div>

  {{-- Copy link --}}
  <div class="ctx-item" @click="ctxAction('link')">
    <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
    Copy link
  </div>

  <div class="ctx-sep"></div>

  {{-- Archive --}}
  <div class="ctx-item" @click="ctxAction('archive')">
    <svg class="w-3.5 h-3.5 text-amber-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg>
    Archive
  </div>

  {{-- Delete: available to all users --}}
  <div class="ctx-item ctx-danger" @click="ctxAction('delete')">
    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
    Delete card
  </div>

</div>

{{-- ── Card Detail Modal ─────────────────────────────────────────────── --}}
@include('boards.partials.card-modal')

{{-- ── Checklist Item Modal ──────────────────────────────────────────── --}}
@include('boards.partials.checklist-item-modal')

{{-- ── Trello-style Date Picker Modal ───────────────────────────────── --}}
@include('boards.partials.date-picker-modal')

{{-- ── Trello-style Switch Boards Modal ─────────────────────────────── --}}
@include('boards.partials.switch-boards-modal')

{{-- ── Move / Copy Card Destination Modal ───────────────────────────── --}}
@include('boards.partials.card-transfer-modal')

{{-- ── Trello-style Member Picker Modal ─────────────────────────────── --}}
@include('boards.partials.member-picker-modal')

{{-- ── Trello-style Attachment Modal ────────────────────────────────── --}}
@include('boards.partials.attachment-modal')
@include('boards.partials.export-modal')
@include('boards.partials.import-modal')




{{-- Switch Board Button (Fixed at bottom middle for desktop only; mobile uses header title / menu) --}}
<div class="fixed bottom-8 left-1/2 -translate-x-1/2 z-[100] drop-shadow-2xl hidden lg:block" x-show="!activeCard && !isSelectMode" x-transition.opacity.duration.200ms>
  <button type="button"
          @click="openSwitchBoardsModal()"
          class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-400 bg-white/95 dark:bg-slate-800/95 backdrop-blur-md px-6 py-2.5 text-sm font-extrabold text-slate-700 dark:text-slate-200 shadow-xl transition-all hover:-translate-y-1 hover:bg-white dark:hover:bg-slate-700 hover:text-indigo-700 dark:hover:text-white hover:shadow-2xl">
    <svg class="h-4 w-4 text-indigo-500 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h12A2.25 2.25 0 0 1 20.25 6v12A2.25 2.25 0 0 1 18 20.25H6A2.25 2.25 0 0 1 3.75 18V6Zm4.5 3h7.5m-7.5 6h7.5" />
    </svg>
    Switch board
  </button>
</div>

  {{-- Floating Action Bar for Bulk Selection --}}
  <div x-show="isSelectMode" x-cloak
       x-transition:enter="transition ease-out duration-300 transform"
       x-transition:enter-start="translate-y-full opacity-0"
       x-transition:enter-end="translate-y-0 opacity-100"
       x-transition:leave="transition ease-in duration-200 transform"
       x-transition:leave-start="translate-y-0 opacity-100"
       x-transition:leave-end="translate-y-full opacity-0"
       class="fixed bottom-20 sm:bottom-6 left-1/2 -translate-x-1/2 z-[110] flex items-center gap-3 bg-slate-900/95 backdrop-blur-md text-white px-5 py-2.5 rounded-full shadow-2xl shadow-slate-900/40 border border-slate-700 touch-manipulation">
    <div class="flex items-center gap-2 mr-1 select-none">
      <span class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-indigo-500/25 text-indigo-300 text-xs font-bold" x-text="selectedCards.length"></span>
      <span class="font-semibold text-xs text-slate-200" x-text="selectedCards.length === 1 ? 'card selected' : 'cards selected'"></span>
    </div>
    <button @click="openBulkTransferModal('move')"
            :disabled="selectedCards.length === 0"
            class="btn btn-primary bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 disabled:pointer-events-none border-none text-xs py-1.5 px-4 rounded-full font-bold transition-all shadow-sm">
      Move
    </button>
    <button @click="openBulkTransferModal('copy')"
            :disabled="selectedCards.length === 0"
            class="btn btn-secondary bg-slate-800 hover:bg-slate-700 disabled:opacity-40 disabled:pointer-events-none border border-slate-600 text-white text-xs py-1.5 px-4 rounded-full font-bold transition-all shadow-sm">
      Copy
    </button>

    {{-- Bulk Comment Button & Popover --}}
    <div class="relative">
      <button type="button"
              @click="openBulkComment = !openBulkComment"
              :disabled="selectedCards.length === 0"
              class="btn btn-secondary bg-slate-800 hover:bg-slate-700 disabled:opacity-40 disabled:pointer-events-none border border-slate-600 text-white text-xs py-1.5 px-3.5 rounded-full font-bold transition-all shadow-sm flex items-center gap-1.5">
        <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
        </svg>
        <span>Comment</span>
      </button>

      {{-- Comment Options Popover --}}
      <div x-show="openBulkComment"
           @click.outside="openBulkComment = false"
           x-cloak
           x-transition:enter="transition ease-out duration-150 transform"
           x-transition:enter-start="opacity-0 translate-y-2 scale-95"
           x-transition:enter-end="opacity-100 translate-y-0 scale-100"
           x-transition:leave="transition ease-in duration-100 transform"
           x-transition:leave-start="opacity-100 translate-y-0 scale-100"
           x-transition:leave-end="opacity-0 translate-y-2 scale-95"
           class="absolute bottom-full mb-3 left-1/2 -translate-x-1/2 w-72 sm:w-80 max-w-[92vw] bg-slate-900/98 backdrop-blur-xl border border-slate-700 rounded-2xl shadow-2xl p-3.5 z-50 text-white">
        <div class="flex items-center justify-between pb-2 mb-2.5 border-b border-slate-800">
          <span class="text-[11px] uppercase tracking-wider font-extrabold text-slate-300">
            Comment on <span class="text-indigo-400" x-text="selectedCards.length"></span> cards
          </span>
          <button type="button" @click="openBulkComment = false" class="text-slate-400 hover:text-white text-xs p-1">✕</button>
        </div>

        {{-- Preset Words --}}
        <div class="space-y-1.5">
          <template x-for="word in ['Ready', 'Team approved', 'Production approved', 'Production approved SMM', 'Approved', 'Blocked']" :key="word">
            <button type="button"
                    @click="submitBulkComment(word)"
                    :disabled="bulkCommentSubmitting"
                    class="w-full text-xs px-3 py-2 rounded-xl bg-slate-800/90 hover:bg-indigo-600 hover:text-white border border-slate-700/80 hover:border-indigo-500 font-semibold transition-all text-slate-200 text-left flex items-center justify-between group">
              <span class="font-bold" x-text="word"></span>
              <span class="text-[10px] text-slate-400 group-hover:text-indigo-200 transition-colors">Select ↵</span>
            </button>
          </template>
        </div>
      </div>
    </div>

    {{-- Bulk Delete Button --}}
    <button type="button"
            @click="bulkDeleteCards()"
            :disabled="!selectedCards || selectedCards.length === 0"
            class="btn btn-danger bg-rose-600 hover:bg-rose-500 disabled:opacity-40 disabled:pointer-events-none border-none text-white text-xs py-1.5 px-3.5 rounded-full font-bold transition-all shadow-sm flex items-center gap-1.5"
            title="Delete selected cards (move to Trash)">
      <svg class="w-3.5 h-3.5 text-white flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
      </svg>
      <span>Delete</span>
    </button>

    <div class="h-4 w-[1px] bg-slate-700 mx-0.5"></div>
    <button @click="exitSelectMode()" class="flex items-center gap-1 text-xs font-medium text-slate-400 hover:text-white px-2 py-1 rounded-full transition-colors" title="Cancel selection">
      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
      </svg>
      <span>Cancel</span>
    </button>
  </div>

</div>
@endsection

@push('scripts')
<!-- Quill JS -->
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
@php
    $trelloBoardVersion = (file_exists(public_path('js/trello-board.js')) ? filemtime(public_path('js/trello-board.js')) : '1.0.0') . '.' . time();
    $dragScrollVersion = (file_exists(public_path('js/drag-scroll.js')) ? filemtime(public_path('js/drag-scroll.js')) : '1.0.0') . '.' . time();
@endphp
<script src="{{ asset('js/trello-board.js') }}?v={{ $trelloBoardVersion }}"></script>
<script src="{{ asset('js/drag-scroll.js') }}?v={{ $dragScrollVersion }}"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (window.trelloBoard) {
        const _orig = window.trelloBoard;
        window.trelloBoard = function(config) {
            const data = _orig(config);
            if (!data.bulkDeleteCards) {
                data.bulkDeleting = false;
                data.bulkDeleteCards = async function() {
                    if (!this.selectedCards || !this.selectedCards.length) return;
                    const count = this.selectedCards.length;
                    const ok = window.confirmModal 
                        ? await window.confirmModal({
                            title: 'Move selected cards to Trash?',
                            message: `Are you sure you want to move <strong>${count}</strong> selected card${count > 1 ? 's' : ''} to Trash?<br><span class="text-xs text-slate-500 mt-1 block">Items in Trash are kept for 7 days before being automatically removed.</span>`,
                            confirmText: 'Move to Trash',
                            tone: 'danger'
                        })
                        : confirm(`Move ${count} selected card(s) to Trash?`);
                    if (!ok) return;

                    try {
                        const res = await (this.api 
                            ? this.api(`/${this.baseRoute || 'boards'}/${this.boardSlug}/cards/bulk`, 'POST', { card_ids: this.selectedCards, action: 'delete' })
                            : window.fetchJson(`/${this.baseRoute || 'boards'}/${this.boardSlug}/cards/bulk`, { method: 'POST', body: JSON.stringify({ card_ids: this.selectedCards, action: 'delete' }) })
                        );
                        const deletedIds = new Set(this.selectedCards.map(id => Number(id)));
                        this.lists.forEach(l => {
                            if (l.cards) l.cards = l.cards.filter(c => !deletedIds.has(Number(c.id)));
                        });
                        if (window.showToast) window.showToast(res.message || `${count} cards moved to Trash (auto-removes in 7 days).`);
                        this.exitSelectMode();
                    } catch (e) {
                        console.error(e);
                        if (window.showToast) window.showToast('Failed to delete selected cards.', 'error');
                    }
                };
            }
            return data;
        };
    }
});
</script>
@endpush
