{{-- Production Reports — theme-aware design layer (Light = white, Dark = navy, Neon = electric blue) --}}
<style>
    /* ───────────── Theme tokens ───────────── */
    .pr-root {
        --pr-card: #ffffff;
        --pr-soft: #f1f5f9;
        --pr-border: #e2e8f0;
        --pr-text: #0f172a;
        --pr-text2: #475569;
        --pr-muted: #64748b;
        --pr-input: #ffffff;
        --pr-hero: linear-gradient(135deg, #eef2ff 0%, #ffffff 55%, #f0f9ff 100%);
        --pr-shadow: 0 1px 2px rgba(15, 23, 42, .05), 0 12px 28px -14px rgba(15, 23, 42, .18);
        --pr-ring: rgba(99, 102, 241, .22);
        width: 100%;
        max-width: none;
        color: var(--pr-text);
    }
    html[data-theme="dark"] .pr-root {
        --pr-card: #1e293b;
        --pr-soft: #273449;
        --pr-border: #334155;
        --pr-text: #e8eaf0;
        --pr-text2: #a9b4c8;
        --pr-muted: #7d8aa0;
        --pr-input: #162033;
        --pr-hero: linear-gradient(135deg, #1e293b 0%, #172033 60%, #1b2540 100%);
        --pr-shadow: 0 1px 2px rgba(0, 0, 0, .35), 0 14px 30px -16px rgba(0, 0, 0, .6);
    }
    html[data-theme="neon"] .pr-root {
        --pr-card: rgba(4, 20, 56, .88);
        --pr-soft: rgba(9, 36, 96, .55);
        --pr-border: rgba(0, 160, 255, .35);
        --pr-text: #f0f9ff;
        --pr-text2: #93c5fd;
        --pr-muted: #6b8fc4;
        --pr-input: rgba(2, 12, 36, .85);
        --pr-hero: linear-gradient(135deg, rgba(0, 102, 255, .28) 0%, rgba(4, 20, 56, .92) 60%, rgba(0, 195, 255, .14) 100%);
        --pr-shadow: 0 0 0 1px rgba(0, 160, 255, .18), 0 0 22px rgba(0, 150, 255, .2);
        --pr-ring: rgba(0, 195, 255, .35);
    }

    /* ───────────── Re-map legacy dark utilities onto the theme ───────────── */
    .pr-root .bg-slate-900,
    .pr-root .bg-slate-900\/90,
    .pr-root .bg-slate-900\/80,
    .pr-root .bg-slate-900\/70,
    .pr-root .bg-slate-900\/60,
    .pr-root .bg-slate-900\/50,
    .pr-root .bg-slate-900\/40,
    .pr-root .bg-slate-900\/30,
    .pr-root .bg-slate-900\/20,
    .pr-root .bg-slate-900\/10 {
        background-color: var(--pr-card);
    }
    .pr-root .bg-slate-800,
    .pr-root .bg-slate-800\/90,
    .pr-root .bg-slate-800\/80,
    .pr-root .bg-slate-800\/70,
    .pr-root .bg-slate-800\/60,
    .pr-root .bg-slate-800\/50,
    .pr-root .bg-slate-800\/40,
    .pr-root .bg-slate-800\/30,
    .pr-root .bg-slate-800\/20,
    .pr-root .bg-slate-950\/40 {
        background-color: var(--pr-soft);
    }
    .pr-root .hover\:bg-slate-800:hover,
    .pr-root .hover\:bg-slate-800\/50:hover,
    .pr-root .hover\:bg-slate-800\/40:hover,
    .pr-root .hover\:bg-slate-800\/30:hover,
    .pr-root .hover\:bg-slate-700:hover,
    .pr-root .hover\:bg-slate-700\/50:hover,
    .pr-root .hover\:bg-slate-700\/40:hover {
        background-color: color-mix(in srgb, var(--pr-soft) 78%, var(--pr-border));
    }
    .pr-root .border-slate-700,
    .pr-root .border-slate-700\/80,
    .pr-root .border-slate-800,
    .pr-root .border-slate-800\/60,
    .pr-root .border-slate-600 {
        border-color: var(--pr-border);
    }
    .pr-root .divide-slate-800 > :not(:last-child) {
        border-color: var(--pr-border);
    }
    .pr-root .text-white,
    .pr-root .text-slate-200,
    .pr-root .hover\:text-white:hover {
        color: var(--pr-text);
    }
    .pr-root .text-slate-300,
    .pr-root .text-slate-400 {
        color: var(--pr-text2);
    }
    .pr-root .text-slate-500,
    .pr-root .text-slate-600 {
        color: var(--pr-muted);
    }
    /* Keep white text on solid coloured buttons */
    .pr-root :is([class~="bg-indigo-600"], [class~="bg-indigo-500"], [class~="bg-amber-600"], [class~="bg-amber-500"],
                 [class~="bg-emerald-600"], [class~="bg-emerald-500"], [class~="bg-rose-600"], [class~="bg-rose-500"],
                 [class~="bg-sky-600"], [class~="bg-violet-600"]).text-white,
    .pr-root :is([class~="bg-indigo-600"], [class~="bg-amber-600"], [class~="bg-emerald-600"], [class~="bg-rose-600"]) .text-white,
    .pr-root .pr-btn,
    .pr-root .pr-tab.is-active {
        color: #ffffff;
    }

    /* Light-mode colour contrast for tinted text that was authored for dark backgrounds */
    html:not([data-theme="dark"]):not([data-theme="neon"]) .pr-root :is(.text-indigo-200, .text-indigo-300) { color: #4338ca; }
    html:not([data-theme="dark"]):not([data-theme="neon"]) .pr-root .text-indigo-400 { color: #4f46e5; }
    html:not([data-theme="dark"]):not([data-theme="neon"]) .pr-root :is(.text-emerald-300, .text-emerald-200) { color: #047857; }
    html:not([data-theme="dark"]):not([data-theme="neon"]) .pr-root .text-emerald-400 { color: #059669; }
    html:not([data-theme="dark"]):not([data-theme="neon"]) .pr-root :is(.text-amber-200, .text-amber-300) { color: #b45309; }
    html:not([data-theme="dark"]):not([data-theme="neon"]) .pr-root .text-amber-400 { color: #d97706; }
    html:not([data-theme="dark"]):not([data-theme="neon"]) .pr-root :is(.text-rose-200, .text-rose-300) { color: #be123c; }
    html:not([data-theme="dark"]):not([data-theme="neon"]) .pr-root :is(.text-rose-400, .text-rose-400\/80) { color: #e11d48; }
    html:not([data-theme="dark"]):not([data-theme="neon"]) .pr-root :is(.text-sky-300, .text-sky-400) { color: #0369a1; }
    html:not([data-theme="dark"]):not([data-theme="neon"]) .pr-root .text-purple-300 { color: #7e22ce; }

    /* ───────────── Cards ───────────── */
    .pr-root :is(.bg-slate-900, .bg-slate-900\/90, .bg-slate-900\/80).rounded-2xl {
        box-shadow: var(--pr-shadow);
        border-radius: 1.1rem;
    }

    /* ───────────── Form controls ───────────── */
    .pr-root input:not([type="checkbox"]):not([type="radio"]):not([type="file"]):not([type="hidden"]):not([type="range"]),
    .pr-root select,
    .pr-root textarea {
        background-color: var(--pr-input);
        color: var(--pr-text);
        border: 1px solid var(--pr-border);
        border-radius: .8rem;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .pr-root input::placeholder,
    .pr-root textarea::placeholder { color: var(--pr-muted); opacity: .85; }
    .pr-root input:focus,
    .pr-root select:focus,
    .pr-root textarea:focus {
        outline: none;
        border-color: #6366f1;
        box-shadow: 0 0 0 3px var(--pr-ring);
    }
    html[data-theme="neon"] .pr-root input:focus,
    html[data-theme="neon"] .pr-root select:focus,
    html[data-theme="neon"] .pr-root textarea:focus { border-color: #00c3ff; }
    .pr-root input[type="checkbox"], .pr-root input[type="radio"] { accent-color: #6366f1; }

    /* ───────────── Buttons ───────────── */
    .pr-root :is(a, button):is([class~="bg-indigo-600"], [class~="bg-amber-600"], [class~="bg-emerald-600"], [class~="bg-rose-600"], [class~="bg-sky-600"]) {
        border-radius: .8rem;
        transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
        box-shadow: 0 8px 18px -8px rgba(79, 70, 229, .55);
    }
    .pr-root :is(a, button)[class~="bg-indigo-600"] { background-image: linear-gradient(135deg, #6366f1, #4f46e5); }
    .pr-root :is(a, button)[class~="bg-amber-600"]  { background-image: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 8px 18px -8px rgba(217, 119, 6, .6); }
    .pr-root :is(a, button)[class~="bg-emerald-600"]{ background-image: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 8px 18px -8px rgba(5, 150, 105, .6); }
    .pr-root :is(a, button)[class~="bg-rose-600"]   { background-image: linear-gradient(135deg, #f43f5e, #e11d48); box-shadow: 0 8px 18px -8px rgba(225, 29, 72, .6); }
    .pr-root :is(a, button):is([class~="bg-indigo-600"], [class~="bg-amber-600"], [class~="bg-emerald-600"], [class~="bg-rose-600"], [class~="bg-sky-600"]):hover {
        transform: translateY(-1px);
        filter: brightness(1.07);
    }
    .pr-root :is(a, button):is([class~="bg-indigo-600"], [class~="bg-amber-600"], [class~="bg-emerald-600"], [class~="bg-rose-600"]):active { transform: translateY(0); }
    html[data-theme="neon"] .pr-root :is(a, button)[class~="bg-indigo-600"] { background-image: linear-gradient(135deg, #0066ff, #00c3ff); box-shadow: 0 0 18px rgba(0, 150, 255, .45); }

    /* ───────────── Hero header + tabs (nav partial) ───────────── */
    .pr-hero {
        background: var(--pr-hero);
        border: 1px solid var(--pr-border);
        border-radius: 1.4rem;
        box-shadow: var(--pr-shadow);
        padding: 1.1rem 1.25rem;
        margin-bottom: 1.25rem;
    }
    .pr-sticky-nav {
        position: sticky;
        top: 0;
        z-index: 45;
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
        background: color-mix(in srgb, var(--pr-card) 88%, transparent);
        border: 1px solid var(--pr-border);
        border-radius: 1.4rem;
        box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.28);
        padding: 0.95rem 1.25rem;
        margin-bottom: 1.25rem;
        transition: all 0.2s ease;
    }
    html[data-theme="neon"] .pr-sticky-nav {
        background: rgba(4, 20, 56, 0.9);
        border-color: rgba(0, 160, 255, 0.4);
        box-shadow: 0 0 25px rgba(0, 150, 255, 0.25);
    }
    .pr-hero-title { font-size: clamp(1.15rem, 2.2vw, 1.55rem); font-weight: 800; letter-spacing: -.01em; color: var(--pr-text); }
    .pr-hero-sub { font-size: .78rem; color: var(--pr-text2); }
    .pr-chip {
        display: inline-flex; align-items: center; gap: .35rem;
        padding: .2rem .65rem; border-radius: 999px; font-size: 11px; font-weight: 700; border: 1px solid transparent;
    }
    .pr-chip-indigo { background: rgba(99, 102, 241, .12); color: #4338ca; border-color: rgba(99, 102, 241, .3); }
    .pr-chip-emerald { background: rgba(16, 185, 129, .12); color: #047857; border-color: rgba(16, 185, 129, .3); }
    .pr-chip-amber { background: rgba(245, 158, 11, .14); color: #b45309; border-color: rgba(245, 158, 11, .35); }
    .pr-chip-sky { background: rgba(14, 165, 233, .12); color: #0369a1; border-color: rgba(14, 165, 233, .3); }
    html[data-theme="dark"] .pr-chip-indigo, html[data-theme="neon"] .pr-chip-indigo { color: #a5b4fc; }
    html[data-theme="dark"] .pr-chip-emerald, html[data-theme="neon"] .pr-chip-emerald { color: #6ee7b7; }
    html[data-theme="dark"] .pr-chip-amber, html[data-theme="neon"] .pr-chip-amber { color: #fcd34d; }
    html[data-theme="dark"] .pr-chip-sky, html[data-theme="neon"] .pr-chip-sky { color: #7dd3fc; }

    /* Modern action buttons (Image 3 UI/UX overhaul) */
    .pr-action-btn-weekly {
        display: inline-flex; align-items: center; justify-content: center; gap: .45rem;
        padding: .52rem 1.05rem; border-radius: .85rem; font-size: .78rem; font-weight: 700; color: #ffffff !important;
        background-image: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);
        border: 1px solid rgba(251, 191, 36, .4);
        box-shadow: 0 4px 14px -3px rgba(234, 88, 12, .5);
        transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
        white-space: nowrap; text-decoration: none; cursor: pointer;
    }
    .pr-action-btn-weekly:hover { transform: translateY(-1.5px); filter: brightness(1.08); box-shadow: 0 8px 20px -3px rgba(234, 88, 12, .65); }
    .pr-action-btn-weekly:active { transform: translateY(0); }

    .pr-action-btn-monthly {
        display: inline-flex; align-items: center; justify-content: center; gap: .45rem;
        padding: .52rem 1.05rem; border-radius: .85rem; font-size: .78rem; font-weight: 700; color: #ffffff !important;
        background-image: linear-gradient(135deg, #10b981 0%, #0d9488 100%);
        border: 1px solid rgba(52, 211, 153, .4);
        box-shadow: 0 4px 14px -3px rgba(13, 148, 136, .5);
        transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
        white-space: nowrap; text-decoration: none; cursor: pointer;
    }
    .pr-action-btn-monthly:hover { transform: translateY(-1.5px); filter: brightness(1.08); box-shadow: 0 8px 20px -3px rgba(13, 148, 136, .65); }
    .pr-action-btn-monthly:active { transform: translateY(0); }

    .pr-action-btn-daily {
        display: inline-flex; align-items: center; justify-content: center; gap: .45rem;
        padding: .52rem 1.05rem; border-radius: .85rem; font-size: .78rem; font-weight: 700; color: #ffffff !important;
        background-image: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
        border: 1px solid rgba(165, 180, 252, .35);
        box-shadow: 0 4px 14px -3px rgba(79, 70, 229, .5);
        transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
        white-space: nowrap; text-decoration: none; cursor: pointer;
    }
    .pr-action-btn-daily:hover { transform: translateY(-1.5px); filter: brightness(1.08); box-shadow: 0 8px 20px -3px rgba(79, 70, 229, .65); }
    .pr-action-btn-daily:active { transform: translateY(0); }

    /* Segmented menu bar (Image 2 UI/UX overhaul) */
    .pr-tabs-bar {
        display: flex; align-items: center; gap: .35rem; overflow-x: auto;
        padding: .35rem; border-radius: 1.15rem; background: var(--pr-soft);
        border: 1px solid var(--pr-border); scrollbar-width: none; -webkit-overflow-scrolling: touch;
        box-shadow: inset 0 1px 3px rgba(0, 0, 0, .15);
    }
    .pr-tabs-bar::-webkit-scrollbar { display: none; }
    .pr-tab {
        display: inline-flex; align-items: center; gap: .45rem; flex: 0 0 auto;
        padding: .48rem .95rem; border-radius: .85rem; font-size: .78rem; font-weight: 700;
        color: var(--pr-text2); transition: all .16s ease; text-decoration: none; cursor: pointer;
        border: 1px solid transparent;
    }
    .pr-tab:hover { background: color-mix(in srgb, var(--pr-card) 60%, transparent); color: var(--pr-text); transform: translateY(-0.5px); }
    .pr-tab.is-active {
        background-image: linear-gradient(135deg, #6366f1, #4f46e5);
        color: #ffffff !important;
        border-color: rgba(255, 255, 255, .15);
        box-shadow: 0 6px 16px -4px rgba(79, 70, 229, .6);
    }
    .pr-tab-head {
        border-color: rgba(245, 158, 11, .3);
        background: rgba(245, 158, 11, .08);
        color: #d97706;
    }
    .pr-tab-head:hover {
        background: rgba(245, 158, 11, .16);
        color: #b45309;
    }
    html[data-theme="dark"] .pr-tab-head, html[data-theme="neon"] .pr-tab-head {
        color: #fcd34d;
        background: rgba(245, 158, 11, .12);
        border-color: rgba(245, 158, 11, .35);
    }
    html[data-theme="neon"] .pr-tab.is-active {
        background-image: linear-gradient(135deg, #0066ff, #00c3ff);
        box-shadow: 0 0 16px rgba(0, 150, 255, .55);
    }

    /* Theme-adaptive dropdowns */
    .pr-dropdown {
        background: var(--pr-card);
        border: 1px solid var(--pr-border);
        box-shadow: 0 16px 36px -8px rgba(0, 0, 0, .35), 0 0 0 1px var(--pr-border);
        color: var(--pr-text);
    }
    .pr-dropdown-item {
        color: var(--pr-text);
        transition: background .15s ease, color .15s ease;
    }
    .pr-dropdown-item:hover {
        background: var(--pr-soft);
        color: var(--pr-text);
    }

    .pr-alert { border-radius: 1rem; padding: .85rem 1rem; font-size: .85rem; margin-bottom: 1rem; border: 1px solid; }
    .pr-alert-success { background: rgba(16, 185, 129, .1); border-color: rgba(16, 185, 129, .35); color: #047857; }
    .pr-alert-error { background: rgba(244, 63, 94, .1); border-color: rgba(244, 63, 94, .35); color: #be123c; }
    html[data-theme="dark"] .pr-alert-success, html[data-theme="neon"] .pr-alert-success { color: #6ee7b7; }
    html[data-theme="dark"] .pr-alert-error, html[data-theme="neon"] .pr-alert-error { color: #fda4af; }

    /* ───────────── Tables & Data Grids (Theme Aware + High Contrast) ───────────── */
    .pr-root table {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
    }
    .pr-root table thead tr {
        background-color: var(--pr-soft) !important;
        border-bottom: 1px solid var(--pr-border);
    }
    .pr-root table thead th {
        color: var(--pr-text2) !important;
        font-weight: 700;
        letter-spacing: 0.04em;
    }
    .pr-root table tbody {
        background-color: var(--pr-card) !important;
    }
    .pr-root table tbody tr {
        background-color: var(--pr-card) !important;
        border-bottom: 1px solid var(--pr-border);
        transition: background-color 0.15s ease;
    }
    .pr-root table tbody tr:hover {
        background-color: color-mix(in srgb, var(--pr-soft) 85%, transparent) !important;
    }

    /* ───────────── Action Pills (Open/View, PPTX, PDF, CSV) ───────────── */
    .pr-action-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.28rem 0.65rem;
        border-radius: 0.55rem;
        font-size: 0.72rem;
        font-weight: 700;
        text-decoration: none;
        transition: all 0.15s ease;
        line-height: 1.2;
        border: 1px solid transparent;
        white-space: nowrap;
        cursor: pointer;
    }
    .pr-action-pill:hover {
        transform: translateY(-1px);
        filter: brightness(1.08);
    }
    .pr-action-pill-view {
        background: var(--pr-soft);
        color: var(--pr-text) !important;
        border-color: var(--pr-border);
    }
    .pr-action-pill-view:hover {
        background: color-mix(in srgb, var(--pr-soft) 70%, var(--pr-border));
    }
    .pr-action-pill-pptx {
        background: rgba(245, 158, 11, 0.14);
        color: #d97706 !important;
        border-color: rgba(245, 158, 11, 0.35);
    }
    .pr-action-pill-pptx:hover {
        background: rgba(245, 158, 11, 0.24);
    }
    .pr-action-pill-pdf {
        background: rgba(244, 63, 94, 0.14);
        color: #e11d48 !important;
        border-color: rgba(244, 63, 94, 0.35);
    }
    .pr-action-pill-pdf:hover {
        background: rgba(244, 63, 94, 0.24);
    }
    .pr-action-pill-csv {
        background: rgba(16, 185, 129, 0.14);
        color: #059669 !important;
        border-color: rgba(16, 185, 129, 0.35);
    }
    .pr-action-pill-csv:hover {
        background: rgba(16, 185, 129, 0.24);
    }
    html[data-theme="dark"] .pr-action-pill-pptx, html[data-theme="neon"] .pr-action-pill-pptx {
        color: #fcd34d !important;
    }
    html[data-theme="dark"] .pr-action-pill-pdf, html[data-theme="neon"] .pr-action-pill-pdf {
        color: #fda4af !important;
    }
    html[data-theme="dark"] .pr-action-pill-csv, html[data-theme="neon"] .pr-action-pill-csv {
        color: #6ee7b7 !important;
    }

    /* ───────────── Status badges (replaces dynamic per-status color classes) ───────────── */
    .pr-badge {
        --c: 100, 116, 139; --ct: #475569; --ctd: #cbd5e1;
        display: inline-flex; align-items: center; padding: .15rem .6rem; border-radius: 999px;
        font-size: 11px; font-weight: 700; line-height: 1.4; white-space: nowrap;
        background: rgba(var(--c), .14); border: 1px solid rgba(var(--c), .38); color: var(--ct);
    }
    html[data-theme="dark"] .pr-badge, html[data-theme="neon"] .pr-badge { color: var(--ctd); }
    .pr-badge-indigo  { --c: 99, 102, 241;  --ct: #4338ca; --ctd: #a5b4fc; }
    .pr-badge-emerald { --c: 16, 185, 129;  --ct: #047857; --ctd: #6ee7b7; }
    .pr-badge-amber   { --c: 245, 158, 11;  --ct: #b45309; --ctd: #fcd34d; }
    .pr-badge-rose    { --c: 244, 63, 94;   --ct: #be123c; --ctd: #fda4af; }
    .pr-badge-sky     { --c: 14, 165, 233;  --ct: #0369a1; --ctd: #7dd3fc; }
    .pr-badge-slate   { --c: 100, 116, 139; --ct: #475569; --ctd: #cbd5e1; }

    /* Calendar modern table and tiles */
    .pr-cal-wrapper {
        background: var(--pr-card);
        border: 1px solid var(--pr-border);
        border-radius: 1.4rem;
        box-shadow: var(--pr-shadow);
        overflow: hidden;
    }
    .pr-cal-grid-header {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        background: color-mix(in srgb, var(--pr-soft) 85%, transparent);
        border-bottom: 1px solid var(--pr-border);
    }
    .pr-cal-head-col {
        padding: .75rem .5rem;
        text-align: center;
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--pr-text2);
    }
    .pr-cal-head-col.is-sunday {
        color: #e11d48;
    }
    html[data-theme="dark"] .pr-cal-head-col.is-sunday,
    html[data-theme="neon"] .pr-cal-head-col.is-sunday {
        color: #fb7185;
    }
    .pr-cal-grid-body {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        background: var(--pr-border);
        gap: 1px;
    }
    .pr-cal-day-cell {
        background: var(--pr-card);
        min-height: 124px;
        padding: .65rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        transition: background-color .15s ease, box-shadow .15s ease;
        cursor: pointer;
    }
    .pr-cal-day-cell:hover {
        background: color-mix(in srgb, var(--pr-soft) 55%, var(--pr-card));
        z-index: 2;
    }
    .pr-cal-day-cell.is-outside {
        opacity: .42;
        background: color-mix(in srgb, var(--pr-soft) 25%, var(--pr-card));
    }
    .pr-cal-day-cell.is-today {
        background: color-mix(in srgb, #6366f1 7%, var(--pr-card));
        outline: 2px solid #6366f1;
        outline-offset: -2px;
        z-index: 3;
    }
    html[data-theme="neon"] .pr-cal-day-cell.is-today {
        background: rgba(0, 102, 255, .15);
        outline: 2px solid #00c3ff;
        box-shadow: inset 0 0 16px rgba(0, 195, 255, .25);
    }
    .pr-cal-day-cell.is-sunday {
        background: color-mix(in srgb, var(--pr-soft) 40%, var(--pr-card));
    }
    .pr-cal-day-num {
        font-size: .88rem;
        font-weight: 800;
        color: var(--pr-text);
        width: 1.85rem;
        height: 1.85rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        transition: transform .15s ease;
    }
    .pr-cal-day-num.is-today-badge {
        background-image: linear-gradient(135deg, #6366f1, #4f46e5);
        color: #ffffff !important;
        box-shadow: 0 4px 12px -2px rgba(99, 102, 241, .6);
    }
    html[data-theme="neon"] .pr-cal-day-num.is-today-badge {
        background-image: linear-gradient(135deg, #0066ff, #00c3ff);
        box-shadow: 0 0 14px rgba(0, 195, 255, .6);
    }
    .pr-cal-pill {
        display: flex;
        align-items: center;
        gap: .35rem;
        padding: .25rem .45rem;
        border-radius: .55rem;
        font-size: .68rem;
        font-weight: 700;
        text-decoration: none;
        transition: transform .12s ease, filter .12s ease;
        line-height: 1.25;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .pr-cal-pill:hover {
        transform: translateY(-1px);
        filter: brightness(1.08);
    }
    .pr-cal-pill-emerald {
        background: rgba(16, 185, 129, .14);
        color: #047857;
        border: 1px solid rgba(16, 185, 129, .35);
    }
    .pr-cal-pill-amber {
        background: rgba(245, 158, 11, .15);
        color: #b45309;
        border: 1px solid rgba(245, 158, 11, .38);
    }
    .pr-cal-pill-rose {
        background: rgba(244, 63, 94, .14);
        color: #be123c;
        border: 1px solid rgba(244, 63, 94, .35);
    }
    .pr-cal-pill-indigo {
        background: rgba(99, 102, 241, .14);
        color: #4338ca;
        border: 1px solid rgba(99, 102, 241, .35);
    }
    .pr-cal-pill-sky {
        background: rgba(14, 165, 233, .14);
        color: #0369a1;
        border: 1px solid rgba(14, 165, 233, .35);
    }
    html[data-theme="dark"] .pr-cal-pill-emerald, html[data-theme="neon"] .pr-cal-pill-emerald { color: #6ee7b7; }
    html[data-theme="dark"] .pr-cal-pill-amber, html[data-theme="neon"] .pr-cal-pill-amber { color: #fcd34d; }
    html[data-theme="dark"] .pr-cal-pill-rose, html[data-theme="neon"] .pr-cal-pill-rose { color: #fda4af; }
    html[data-theme="dark"] .pr-cal-pill-indigo, html[data-theme="neon"] .pr-cal-pill-indigo { color: #a5b4fc; }
    html[data-theme="dark"] .pr-cal-pill-sky, html[data-theme="neon"] .pr-cal-pill-sky { color: #7dd3fc; }

    /* Calendar tiles */
    .pr-tile {
        --c: 100, 116, 139; --ct: #475569; --ctd: #e2e8f0;
        display: block; padding: .4rem .5rem; border-radius: .7rem; font-size: 11px;
        background: rgba(var(--c), .12); border: 1px solid rgba(var(--c), .32); color: var(--ct);
        transition: transform .12s ease, background-color .12s ease;
    }
    .pr-tile:hover { background: rgba(var(--c), .22); transform: translateY(-1px); }
    html[data-theme="dark"] .pr-tile, html[data-theme="neon"] .pr-tile { color: var(--ctd); }
    .pr-tile-indigo  { --c: 99, 102, 241;  --ct: #4338ca; --ctd: #c7d2fe; }
    .pr-tile-emerald { --c: 16, 185, 129;  --ct: #047857; --ctd: #a7f3d0; }
    .pr-tile-amber   { --c: 245, 158, 11;  --ct: #b45309; --ctd: #fde68a; }
    .pr-tile-rose    { --c: 244, 63, 94;   --ct: #be123c; --ctd: #fecdd3; }
    .pr-tile-sky     { --c: 14, 165, 233;  --ct: #0369a1; --ctd: #bae6fd; }

    /* Health status radio cards (weekly form) */
    .pr-health { cursor: pointer; border: 1.5px solid var(--pr-border); background: var(--pr-soft); border-radius: 1rem; padding: .8rem 1rem; transition: all .15s ease; display: block; }
    .pr-health:hover { transform: translateY(-1px); }
    .pr-health input { position: absolute; opacity: 0; pointer-events: none; }
    .pr-health.is-on-track.is-selected { border-color: #10b981; background: rgba(16, 185, 129, .12); }
    .pr-health.is-at-risk.is-selected { border-color: #f59e0b; background: rgba(245, 158, 11, .14); }
    .pr-health.is-blocked.is-selected { border-color: #f43f5e; background: rgba(244, 63, 94, .12); }

    /* ───────────── Responsive polish ───────────── */
    @media (max-width: 640px) {
        .pr-hero { padding: .9rem; border-radius: 1.1rem; }
        .pr-root .overflow-x-auto { -webkit-overflow-scrolling: touch; }
        .pr-root table { min-width: 620px; }
        .pr-hero .pr-btn { flex: 1 1 100%; }
    }

    /* ───────────── Daily-report 15-minute reminder (global) ───────────── */
    .pr-remind {
        position: fixed; z-index: 99990; right: 1rem; top: 4.6rem; width: min(26rem, calc(100vw - 2rem));
        border-radius: 1.2rem; padding: .95rem 1rem; display: none;
        background: var(--bg-card, #fff); color: var(--text-primary, #0f172a);
        border: 1.5px solid #f59e0b; box-shadow: 0 18px 40px -12px rgba(245, 158, 11, .55);
        animation: prRemindIn .35s ease;
    }
    html[data-theme="dark"] .pr-remind { background: #1e293b; color: #e8eaf0; }
    html[data-theme="neon"] .pr-remind { background: rgba(4, 20, 56, .96); color: #f0f9ff; border-color: #00c3ff; box-shadow: 0 0 28px rgba(0, 195, 255, .45); }
    .pr-remind.is-open { display: block; }
    .pr-remind.is-urgent { border-color: #f43f5e; box-shadow: 0 18px 40px -12px rgba(244, 63, 94, .55); }
    .pr-remind-title { font-weight: 800; font-size: .92rem; }
    .pr-remind-time { font-variant-numeric: tabular-nums; font-weight: 800; font-size: 1.5rem; color: #d97706; }
    .pr-remind.is-urgent .pr-remind-time { color: #e11d48; }
    @keyframes prRemindIn { from { opacity: 0; transform: translateY(-10px) scale(.98); } to { opacity: 1; transform: none; } }
    @media (max-width: 640px) { .pr-remind { top: auto; bottom: 5.5rem; right: .75rem; left: .75rem; width: auto; } }

    /* ───────────── Day-detail modal (calendar) — dedicated classes so the
       generic .bg-slate-900/NN theme remap can never turn the backdrop white ───────────── */
    [x-cloak] { display: none !important; }
    .pr-modal-overlay {
        position: fixed; inset: 0; z-index: 99980;
        display: flex; align-items: center; justify-content: center;
        padding: 1.25rem;
        background: rgba(8, 15, 35, .62);
        -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px);
    }
    .pr-modal {
        width: 100%; max-width: 44rem; max-height: min(88vh, 46rem);
        display: flex; flex-direction: column; overflow: hidden;
        background: var(--pr-card); color: var(--pr-text);
        border: 1px solid var(--pr-border); border-radius: 1.5rem;
        box-shadow: 0 30px 80px -20px rgba(0, 0, 0, .55);
        animation: prModalIn .22s cubic-bezier(.2, .8, .2, 1);
    }
    .pr-modal-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1.25rem 1.5rem 1rem; border-bottom: 1px solid var(--pr-border); }
    .pr-modal-title { font-size: 1.35rem; font-weight: 800; letter-spacing: -.01em; line-height: 1.25; margin-top: .35rem; color: var(--pr-text); }
    .pr-modal-close { flex: none; width: 2.5rem; height: 2.5rem; display: inline-flex; align-items: center; justify-content: center; border-radius: .85rem; color: var(--pr-muted); background: var(--pr-soft); border: 1px solid var(--pr-border); transition: all .15s ease; }
    .pr-modal-close:hover { color: var(--pr-text); transform: scale(1.05); }
    .pr-modal-body { padding: 1.1rem 1.5rem; overflow-y: auto; -webkit-overflow-scrolling: touch; flex: 1 1 auto; }
    .pr-modal-stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; margin-bottom: 1rem; }
    .pr-modal-stat { padding: .9rem 1rem; border-radius: 1rem; background: var(--pr-soft); border: 1px solid var(--pr-border); text-align: center; }
    .pr-modal-stat-label { font-size: .7rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--pr-muted); }
    .pr-modal-stat-value { font-size: 1.5rem; font-weight: 800; margin-top: .2rem; color: var(--pr-text); }
    .pr-modal-item { display: flex; align-items: center; justify-content: space-between; gap: .85rem; padding: .85rem 1rem; border-radius: 1rem; border: 1px solid var(--pr-border); background: var(--pr-soft); }
    .pr-modal-item + .pr-modal-item { margin-top: .6rem; }
    .pr-modal-empty { text-align: center; padding: 2rem 1rem; border-radius: 1rem; border: 1.5px dashed var(--pr-border); color: var(--pr-muted); font-size: .9rem; }
    .pr-modal-empty-icon { display: block; font-size: 2.2rem; margin-bottom: .35rem; }
    .pr-modal-foot { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: 1rem 1.5rem; border-top: 1px solid var(--pr-border); background: color-mix(in srgb, var(--pr-soft) 55%, var(--pr-card)); }
    .pr-modal-btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; min-height: 2.6rem; padding: 0 1.1rem; border-radius: .9rem; font-size: .85rem; font-weight: 700; transition: all .15s ease; cursor: pointer; }
    .pr-modal-btn-ghost { color: var(--pr-text); background: var(--pr-soft); border: 1px solid var(--pr-border); }
    .pr-modal-btn-ghost:hover { background: color-mix(in srgb, var(--pr-soft) 70%, var(--pr-border)); }
    .pr-modal-btn-primary { color: #fff; background-image: linear-gradient(135deg, #6366f1, #4f46e5); box-shadow: 0 8px 18px -8px rgba(79, 70, 229, .65); border: 0; }
    .pr-modal-btn-primary:hover { transform: translateY(-1px); }
    @keyframes prModalIn { from { opacity: 0; transform: translateY(14px) scale(.97); } to { opacity: 1; transform: none; } }
    /* Mobile: slide-up bottom sheet, thumb-friendly buttons */
    @media (max-width: 640px) {
        .pr-modal-overlay { align-items: flex-end; padding: 0; }
        .pr-modal { max-width: none; max-height: 90vh; border-radius: 1.5rem 1.5rem 0 0; animation-name: prSheetIn; }
        .pr-modal-head, .pr-modal-body, .pr-modal-foot { padding-left: 1.1rem; padding-right: 1.1rem; }
        .pr-modal-title { font-size: 1.15rem; }
        .pr-modal-foot { flex-direction: column-reverse; padding-bottom: calc(1rem + env(safe-area-inset-bottom)); }
        .pr-modal-foot .pr-modal-btn { width: 100%; min-height: 3rem; }
        .pr-modal-item { flex-direction: column; align-items: stretch; }
        .pr-modal-item .pr-modal-btn { width: 100%; }
    }
    @keyframes prSheetIn { from { transform: translateY(100%); } to { transform: none; } }
</style>

