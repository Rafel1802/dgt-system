<style>
/* ==========================================================================
   KPI SYSTEM MASTER THEME STYLES
   Full Support for:
     1. Light Mode    (Default, html:not([data-theme="dark"]):not([data-theme="neon"]), [data-theme="light"])
     2. Dark Mode     ([data-theme="dark"], .dark)
     3. Neon UI       ([data-theme="neon"])
   ========================================================================== */


.kpi-modal-overlay {
    z-index: 100 !important;
    backdrop-filter: blur(8px) !important;
    -webkit-backdrop-filter: blur(8px) !important;
    background-color: rgba(8, 18, 42, 0.75) !important;
}
html:not([data-theme="dark"]):not([data-theme="neon"]) .kpi-modal-overlay,
html[data-theme="light"] .kpi-modal-overlay {
    background-color: rgba(15, 23, 42, 0.6) !important;
}


/* ──────────────────────────────────────────────────────────────────────────
   1. LIGHT THEME (Clean, Crisp, Executive Slate & Blue)
   ────────────────────────────────────────────────────────────────────────── */
html:not([data-theme="dark"]):not([data-theme="neon"]) body,
html[data-theme="light"] body {
    --bg-page: #f8fafc;
    --bg-card: #ffffff;
    --text-primary: #0f172a;
    --text-secondary: #475569;
}

html:not([data-theme="dark"]):not([data-theme="neon"]) .kpi-surface-card,
html[data-theme="light"] .kpi-surface-card,
html:not([data-theme="dark"]):not([data-theme="neon"]) .clay-card,
html[data-theme="light"] .clay-card {
    background: #ffffff !important;
    border-radius: 1.25rem;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 1px 3px 0 rgba(15, 23, 42, 0.05), 0 1px 2px -1px rgba(15, 23, 42, 0.05) !important;
    color: #0f172a !important;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

html:not([data-theme="dark"]):not([data-theme="neon"]) .clay-card:hover,
html[data-theme="light"] .clay-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -4px rgba(15, 23, 42, 0.08) !important;
}

html:not([data-theme="dark"]):not([data-theme="neon"]) .kpi-hero-banner,
html[data-theme="light"] .kpi-hero-banner {
    background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 55%, #0284c7 100%) !important;
    box-shadow: 0 8px 24px -4px rgba(37, 99, 235, 0.25) !important;
    color: #ffffff !important;
}

html:not([data-theme="dark"]):not([data-theme="neon"]) .kpi-stat-title,
html[data-theme="light"] .kpi-stat-title {
    color: #64748b !important;
    font-size: 0.825rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
html:not([data-theme="dark"]):not([data-theme="neon"]) .kpi-stat-value,
html[data-theme="light"] .kpi-stat-value {
    color: #0f172a !important;
    font-size: 1.5rem;
    font-weight: 800;
    line-height: 1.2;
}
html:not([data-theme="dark"]):not([data-theme="neon"]) .kpi-stat-sub,
html[data-theme="light"] .kpi-stat-sub {
    color: #64748b !important;
    font-size: 0.8125rem;
}

html:not([data-theme="dark"]):not([data-theme="neon"]) .kpi-table-head,
html[data-theme="light"] .kpi-table-head,
.kpi-table-head {
    background: linear-gradient(135deg, #1d4ed8 0%, #0284c7 100%) !important;
    color: #ffffff !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
}
.kpi-table-head th {
    color: #ffffff !important;
    font-weight: 800 !important;
}
html:not([data-theme="dark"]):not([data-theme="neon"]) .kpi-table-row,
html[data-theme="light"] .kpi-table-row {
    border-bottom: 1px solid #f1f5f9 !important;
    color: #334155 !important;
    transition: background-color 0.15s ease;
}
html:not([data-theme="dark"]):not([data-theme="neon"]) .kpi-table-row:hover,
html[data-theme="light"] .kpi-table-row:hover {
    background-color: #f8fafc !important;
}

html:not([data-theme="dark"]):not([data-theme="neon"]) .kpi-modal-card,
html[data-theme="light"] .kpi-modal-card {
    background-color: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25) !important;
    color: #0f172a !important;
}
html:not([data-theme="dark"]):not([data-theme="neon"]) .kpi-modal-box,
html[data-theme="light"] .kpi-modal-box {
    background-color: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
    color: #1e293b !important;
}
html:not([data-theme="dark"]):not([data-theme="neon"]) .kpi-modal-input,
html:not([data-theme="dark"]):not([data-theme="neon"]) .kpi-modal-textarea,
html:not([data-theme="dark"]):not([data-theme="neon"]) .kpi-modal-select,
html[data-theme="light"] .kpi-modal-input,
html[data-theme="light"] .kpi-modal-textarea,
html[data-theme="light"] .kpi-modal-select {
    background-color: #ffffff !important;
    border: 1px solid #cbd5e1 !important;
    color: #0f172a !important;
}

/* ──────────────────────────────────────────────────────────────────────────
   2. DARK THEME ([data-theme="dark"], .dark)
   ────────────────────────────────────────────────────────────────────────── */
[data-theme="dark"] .clay-card,
.dark .clay-card {
    background: #1e293b !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.45) !important;
    color: #f8fafc !important;
}
[data-theme="dark"] .clay-card:hover,
.dark .clay-card:hover {
    border-color: rgba(255, 255, 255, 0.18) !important;
    box-shadow: 0 16px 36px -4px rgba(0, 0, 0, 0.6) !important;
    transform: translateY(-2px);
}

[data-theme="dark"] .kpi-hero-banner,
.dark .kpi-hero-banner {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #1e3a8a 100%) !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.5) !important;
}

[data-theme="dark"] .kpi-stat-title,
.dark .kpi-stat-title {
    color: #94a3b8 !important;
}
[data-theme="dark"] .kpi-stat-value,
.dark .kpi-stat-value {
    color: #f8fafc !important;
}
[data-theme="dark"] .kpi-stat-sub,
.dark .kpi-stat-sub {
    color: #64748b !important;
}

[data-theme="dark"] .kpi-table-head,
.dark .kpi-table-head {
    background: linear-gradient(135deg, #1e3a8a 0%, #0369a1 100%) !important;
    color: #ffffff !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
}
[data-theme="dark"] .kpi-table-row,
.dark .kpi-table-row {
    border-bottom: 1px solid #334155 !important;
    color: #cbd5e1 !important;
}
[data-theme="dark"] .kpi-table-row:hover,
.dark .kpi-table-row:hover {
    background-color: rgba(255, 255, 255, 0.03) !important;
}

[data-theme="dark"] .kpi-modal-card,
.dark .kpi-modal-card {
    background: #18284e !important;
    border: 1px solid rgba(56, 189, 248, 0.45) !important;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.45), 0 0 35px rgba(14, 165, 233, 0.22) !important;
    color: #f8fafc !important;
}
[data-theme="dark"] .kpi-modal-box,
.dark .kpi-modal-box {
    background-color: #1e3566 !important;
    border: 1px solid rgba(56, 189, 248, 0.3) !important;
    color: #f8fafc !important;
}
[data-theme="dark"] .kpi-modal-input,
[data-theme="dark"] .kpi-modal-textarea,
[data-theme="dark"] .kpi-modal-select,
.dark .kpi-modal-input,
.dark .kpi-modal-textarea,
.dark .kpi-modal-select {
    background-color: #14244a !important;
    border: 1px solid rgba(56, 189, 248, 0.3) !important;
    color: #ffffff !important;
}

/* ──────────────────────────────────────────────────────────────────────────
   3. NEON UI ([data-theme="neon"]) ── Electric Cyber Glassmorphism
   ────────────────────────────────────────────────────────────────────────── */
[data-theme="neon"] .clay-card {
    background: rgba(4, 20, 56, 0.94) !important;
    border: 1.5px solid rgba(0, 170, 255, 0.45) !important;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6), 0 0 20px rgba(0, 140, 255, 0.22), inset 0 1px 1px rgba(255, 255, 255, 0.12) !important;
    color: #ffffff !important;
    transform: translateZ(0);
}
[data-theme="neon"] .clay-card:hover {
    border-color: rgba(0, 230, 255, 0.85) !important;
    box-shadow: 0 16px 45px rgba(0, 0, 0, 0.7), 0 0 30px rgba(0, 200, 255, 0.5) !important;
    transform: translateY(-3px) translateZ(0);
}

[data-theme="neon"] .kpi-hero-banner {
    background: linear-gradient(135deg, #020b24 0%, #051d54 40%, #004ecc 85%, #00a2ff 100%) !important;
    border: 1.5px solid rgba(0, 210, 255, 0.6) !important;
    box-shadow: 0 16px 45px rgba(0, 0, 0, 0.7), 0 0 32px rgba(0, 160, 255, 0.4) !important;
}

[data-theme="neon"] .kpi-stat-title {
    color: #7dd3fc !important;
    text-shadow: 0 0 8px rgba(0, 210, 255, 0.4);
}
[data-theme="neon"] .kpi-stat-value {
    color: #ffffff !important;
    text-shadow: 0 0 16px rgba(0, 240, 255, 0.7) !important;
}
[data-theme="neon"] .kpi-stat-sub {
    color: #93c5fd !important;
}

[data-theme="neon"] .kpi-table-head {
    background-color: rgba(2, 12, 38, 0.95) !important;
    color: #7dd3fc !important;
    border-bottom: 1.5px solid rgba(0, 160, 255, 0.32) !important;
}
[data-theme="neon"] .kpi-table-row {
    border-bottom: 1px solid rgba(0, 160, 255, 0.18) !important;
    color: #e0f2fe !important;
}
[data-theme="neon"] .kpi-table-row:hover {
    background-color: rgba(0, 160, 255, 0.12) !important;
}

[data-theme="neon"] .kpi-modal-card {
    background-color: rgba(14, 38, 92, 0.98) !important;
    transform: translateZ(0);
    border: 1.5px solid rgba(0, 210, 255, 0.7) !important;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5), 0 0 35px rgba(0, 180, 255, 0.4) !important;
    color: #ffffff !important;
}
[data-theme="neon"] .kpi-modal-box {
    background-color: rgba(20, 52, 120, 0.85) !important;
    border: 1.5px solid rgba(0, 160, 255, 0.45) !important;
}
[data-theme="neon"] .kpi-modal-input,
[data-theme="neon"] .kpi-modal-textarea,
[data-theme="neon"] .kpi-modal-select {
    background-color: rgba(16, 44, 102, 0.9) !important;
    border: 1.5px solid rgba(0, 160, 255, 0.4) !important;
    color: #ffffff !important;
}
[data-theme="neon"] .kpi-modal-input:focus,
[data-theme="neon"] .kpi-modal-textarea:focus,
[data-theme="neon"] .kpi-modal-select:focus {
    border-color: #00f0ff !important;
    box-shadow: 0 0 14px rgba(0, 240, 255, 0.45) !important;
}

/* ──────────────────────────────────────────────────────────────────────────
   Universal Shared Utilities & Badges
   ────────────────────────────────────────────────────────────────────────── */
.kpi-badge-success {
    background-color: #dcfce7 !important;
    color: #15803d !important;
    border: 1px solid #bbf7d0 !important;
}
[data-theme="dark"] .kpi-badge-success, .dark .kpi-badge-success {
    background-color: rgba(16, 185, 129, 0.15) !important;
    color: #6ee7b7 !important;
    border: 1px solid rgba(16, 185, 129, 0.3) !important;
}
[data-theme="neon"] .kpi-badge-success {
    background-color: rgba(0, 240, 255, 0.15) !important;
    color: #38bdf8 !important;
    border: 1px solid rgba(0, 210, 255, 0.5) !important;
    box-shadow: 0 0 10px rgba(0, 210, 255, 0.3) !important;
}

.kpi-badge-amber {
    background-color: #fef3c7 !important;
    color: #b45309 !important;
    border: 1px solid #fde68a !important;
}
[data-theme="dark"] .kpi-badge-amber, .dark .kpi-badge-amber {
    background-color: rgba(245, 158, 11, 0.15) !important;
    color: #fcd34d !important;
    border: 1px solid rgba(245, 158, 11, 0.3) !important;
}
[data-theme="neon"] .kpi-badge-amber {
    background-color: rgba(245, 158, 11, 0.18) !important;
    color: #fde047 !important;
    border: 1px solid rgba(245, 158, 11, 0.5) !important;
    box-shadow: 0 0 10px rgba(245, 158, 11, 0.3) !important;
}

.kpi-badge-purple {
    background-color: #f3e8ff !important;
    color: #7e22ce !important;
    border: 1px solid #e9d5ff !important;
}
[data-theme="dark"] .kpi-badge-purple, .dark .kpi-badge-purple {
    background-color: rgba(139, 92, 246, 0.15) !important;
    color: #c4b5fd !important;
    border: 1px solid rgba(139, 92, 246, 0.3) !important;
}
[data-theme="neon"] .kpi-badge-purple {
    background-color: rgba(168, 85, 247, 0.18) !important;
    color: #e9d5ff !important;
    border: 1px solid rgba(168, 85, 247, 0.5) !important;
    box-shadow: 0 0 10px rgba(168, 85, 247, 0.3) !important;
}

.kpi-badge-blue {
    background-color: #e0f2fe !important;
    color: #0369a1 !important;
    border: 1px solid #bae6fd !important;
}
[data-theme="dark"] .kpi-badge-blue, .dark .kpi-badge-blue {
    background-color: rgba(59, 130, 246, 0.15) !important;
    color: #93c5fd !important;
    border: 1px solid rgba(59, 130, 246, 0.3) !important;
}
[data-theme="neon"] .kpi-badge-blue {
    background-color: rgba(0, 140, 255, 0.18) !important;
    color: #93c5fd !important;
    border: 1px solid rgba(0, 140, 255, 0.5) !important;
    box-shadow: 0 0 10px rgba(0, 140, 255, 0.3) !important;
}

.kpi-badge-pending {
    background-color: #f1f5f9 !important;
    color: #64748b !important;
    border: 1px solid #e2e8f0 !important;
}
[data-theme="dark"] .kpi-badge-pending, .dark .kpi-badge-pending {
    background-color: rgba(51, 65, 85, 0.4) !important;
    color: #94a3b8 !important;
    border: 1px solid #334155 !important;
}
[data-theme="neon"] .kpi-badge-pending {
    background-color: rgba(3, 16, 48, 0.6) !important;
    color: #7dd3fc !important;
    border: 1px solid rgba(0, 160, 255, 0.3) !important;
}

/* ──────────────────────────────────────────────────────────────────────────
   Work Type Pills (Parts of Role) ── Fully Theme-Responsive (Light, Dark, Neon)
   ────────────────────────────────────────────────────────────────────────── */
.kpi-work-pill {
    padding: 0.625rem 0.875rem;
    border-radius: 0.75rem;
    font-size: 0.75rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    user-select: none;
    transition: all 0.15s ease-in-out;
}
/* Light Mode Work Pill */
html:not([data-theme="dark"]):not([data-theme="neon"]) .kpi-work-pill,
html[data-theme="light"] .kpi-work-pill {
    background-color: #f8fafc;
    border: 1px solid #cbd5e1;
    color: #334155;
    font-weight: 600;
}
html:not([data-theme="dark"]):not([data-theme="neon"]) .kpi-work-pill:hover,
html[data-theme="light"] .kpi-work-pill:hover {
    background-color: #f1f5f9;
    border-color: #94a3b8;
    color: #0f172a;
}
html:not([data-theme="dark"]):not([data-theme="neon"]) .kpi-work-pill.active,
html[data-theme="light"] .kpi-work-pill.active {
    background: linear-gradient(135deg, #0284c7 0%, #2563eb 100%) !important;
    border-color: #0284c7 !important;
    color: #ffffff !important;
    font-weight: 700 !important;
    box-shadow: 0 4px 14px rgba(2, 132, 199, 0.35) !important;
}

/* Dark Mode Work Pill */
[data-theme="dark"] .kpi-work-pill,
.dark .kpi-work-pill {
    background-color: rgba(15, 23, 42, 0.85);
    border: 1px solid rgba(51, 65, 85, 0.8);
    color: #cbd5e1;
    font-weight: 600;
}
[data-theme="dark"] .kpi-work-pill:hover,
.dark .kpi-work-pill:hover {
    background-color: rgba(30, 41, 59, 0.95);
    border-color: rgba(148, 163, 184, 0.5);
    color: #ffffff;
}
[data-theme="dark"] .kpi-work-pill.active,
.dark .kpi-work-pill.active {
    background: linear-gradient(135deg, #0284c7 0%, #3b82f6 100%) !important;
    border-color: #38bdf8 !important;
    color: #ffffff !important;
    font-weight: 700 !important;
    box-shadow: 0 4px 16px rgba(14, 165, 233, 0.4) !important;
}

/* Neon Mode Work Pill */
[data-theme="neon"] .kpi-work-pill {
    background-color: rgba(4, 20, 56, 0.8) !important;
    border: 1.5px solid rgba(0, 170, 255, 0.45) !important;
    color: #7dd3fc !important;
    transform: translateZ(0);
    font-weight: 600;
}
[data-theme="neon"] .kpi-work-pill:hover {
    background-color: rgba(6, 32, 85, 0.9) !important;
    border-color: #00f0ff !important;
    color: #ffffff !important;
    box-shadow: 0 0 14px rgba(0, 240, 255, 0.45) !important;
}
[data-theme="neon"] .kpi-work-pill.active {
    background: linear-gradient(135deg, #00d2ff 0%, #0077ff 100%) !important;
    border-color: #00f0ff !important;
    color: #020b24 !important;
    font-weight: 800 !important;
    box-shadow: 0 0 20px rgba(0, 210, 255, 0.75) !important;
}

/* ──────────────────────────────────────────────────────────────────────────
   Squad Summary Action Button (Executive Blue Accent matching Image 4)
   ────────────────────────────────────────────────────────────────────────── */
.kpi-squad-summary-btn {
    padding: 0.5rem 0.875rem;
    border-radius: 0.75rem;
    font-size: 0.75rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    cursor: pointer;
    transition: all 0.15s ease-in-out;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}
html:not([data-theme="dark"]):not([data-theme="neon"]) .kpi-squad-summary-btn,
html[data-theme="light"] .kpi-squad-summary-btn {
    background-color: #eff6ff !important;
    color: #1d4ed8 !important;
    border: 1px solid #bfdbfe !important;
}
html:not([data-theme="dark"]):not([data-theme="neon"]) .kpi-squad-summary-btn:hover,
html[data-theme="light"] .kpi-squad-summary-btn:hover {
    background-color: #dbeafe !important;
    color: #1e40af !important;
    border-color: #93c5fd !important;
    box-shadow: 0 2px 6px rgba(29, 78, 216, 0.15) !important;
}
[data-theme="dark"] .kpi-squad-summary-btn,
.dark .kpi-squad-summary-btn {
    background-color: rgba(30, 58, 138, 0.28) !important;
    color: #93c5fd !important;
    border: 1px solid rgba(59, 130, 246, 0.4) !important;
}
[data-theme="dark"] .kpi-squad-summary-btn:hover,
.dark .kpi-squad-summary-btn:hover {
    background-color: rgba(30, 58, 138, 0.45) !important;
    color: #bfdbfe !important;
    border-color: rgba(96, 165, 250, 0.65) !important;
}
[data-theme="neon"] .kpi-squad-summary-btn {
    background-color: rgba(0, 140, 255, 0.16) !important;
    color: #38bdf8 !important;
    border: 1.5px solid rgba(0, 210, 255, 0.6) !important;
    box-shadow: 0 0 14px rgba(0, 210, 255, 0.3) !important;
}
[data-theme="neon"] .kpi-squad-summary-btn:hover {
    background-color: rgba(0, 170, 255, 0.32) !important;
    color: #ffffff !important;
    box-shadow: 0 0 22px rgba(0, 240, 255, 0.6) !important;
}

/* ──────────────────────────────────────────────────────────────────────────
   Export KPI Scope Radio Selector (Light, Dark, Neon)
   ────────────────────────────────────────────────────────────────────────── */
.kpi-scope-radio {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.875rem 1rem;
    border-radius: 0.875rem;
    cursor: pointer;
    transition: all 0.15s ease-in-out;
    font-size: 0.8125rem;
}
/* Light Mode */
html:not([data-theme="dark"]):not([data-theme="neon"]) .kpi-scope-radio,
html[data-theme="light"] .kpi-scope-radio {
    background-color: #f8fafc;
    border: 1px solid #cbd5e1;
    color: #334155;
    font-weight: 600;
}
html:not([data-theme="dark"]):not([data-theme="neon"]) .kpi-scope-radio:hover,
html[data-theme="light"] .kpi-scope-radio:hover {
    background-color: #f1f5f9;
    border-color: #94a3b8;
}
html:not([data-theme="dark"]):not([data-theme="neon"]) .kpi-scope-radio.active,
html[data-theme="light"] .kpi-scope-radio.active {
    background-color: #f0f9ff !important;
    border: 2px solid #0284c7 !important;
    color: #0369a1 !important;
    font-weight: 800 !important;
    box-shadow: 0 2px 8px rgba(2, 132, 199, 0.15) !important;
}

/* Dark Mode */
[data-theme="dark"] .kpi-scope-radio,
.dark .kpi-scope-radio {
    background-color: #16264c !important;
    border: 1.5px solid rgba(56, 189, 248, 0.3) !important;
    color: #cbd5e1 !important;
    font-weight: 600;
}
[data-theme="dark"] .kpi-scope-radio:hover,
.dark .kpi-scope-radio:hover {
    background-color: rgba(30, 41, 59, 0.9);
    border-color: rgba(148, 163, 184, 0.5);
    color: #ffffff;
}
[data-theme="dark"] .kpi-scope-radio.active,
.dark .kpi-scope-radio.active {
    background: linear-gradient(135deg, rgba(2, 132, 199, 0.45) 0%, rgba(14, 165, 233, 0.35) 100%) !important;
    border: 2px solid #38bdf8 !important;
    color: #ffffff !important;
    font-weight: 800 !important;
    box-shadow: 0 0 16px rgba(56, 189, 248, 0.4) !important;
}

/* Neon Mode */
[data-theme="neon"] .kpi-scope-radio {
    background-color: rgba(16, 44, 102, 0.85) !important;
    border: 1.5px solid rgba(0, 160, 255, 0.45) !important;
    color: #bae6fd !important;
    font-weight: 600;
}
[data-theme="neon"] .kpi-scope-radio:hover {
    background-color: rgba(6, 24, 70, 0.9) !important;
    border-color: #00f0ff !important;
    color: #ffffff !important;
    box-shadow: 0 0 12px rgba(0, 240, 255, 0.35) !important;
}
[data-theme="neon"] .kpi-scope-radio.active {
    background-color: rgba(0, 210, 255, 0.28) !important;
    border: 2px solid #00f0ff !important;
    color: #ffffff !important;
    font-weight: 900 !important;
    box-shadow: 0 0 20px rgba(0, 210, 255, 0.55) !important;
}

/* ──────────────────────────────────────────────────────────────────────────
   Interactive Member Selector (Card/Board Style with Real Avatar)
   ────────────────────────────────────────────────────────────────────────── */
.kpi-member-btn {
    width: 100%;
    min-height: 3.125rem;
    padding: 0.5rem 0.875rem;
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: all 0.15s ease-in-out;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}
html:not([data-theme="dark"]):not([data-theme="neon"]) .kpi-member-btn,
html[data-theme="light"] .kpi-member-btn {
    background-color: #f8fafc;
    border: 1px solid #cbd5e1;
    color: #0f172a;
}
html:not([data-theme="dark"]):not([data-theme="neon"]) .kpi-member-btn:hover,
html[data-theme="light"] .kpi-member-btn:hover {
    border-color: #0284c7;
    background-color: #ffffff;
}
[data-theme="dark"] .kpi-member-btn,
.dark .kpi-member-btn {
    background-color: #14244a !important;
    border: 1.5px solid rgba(56, 189, 248, 0.35) !important;
    color: #f8fafc !important;
}
[data-theme="dark"] .kpi-member-btn:hover,
.dark .kpi-member-btn:hover {
    border-color: #38bdf8 !important;
    background-color: #1a2f5a !important;
}
[data-theme="neon"] .kpi-member-btn {
    background-color: rgba(16, 44, 102, 0.9) !important;
    border: 1.5px solid rgba(0, 180, 255, 0.5) !important;
    color: #ffffff !important;
}
[data-theme="neon"] .kpi-member-btn:hover {
    border-color: #00f0ff;
    box-shadow: 0 0 12px rgba(0, 240, 255, 0.4);
}

.kpi-member-dropdown {
    position: absolute;
    top: calc(100% + 0.375rem);
    left: 0;
    right: 0;
    border-radius: 1rem;
    overflow: hidden;
    z-index: 60;
    transform: translateZ(0);
}
html:not([data-theme="dark"]):not([data-theme="neon"]) .kpi-member-dropdown,
html[data-theme="light"] .kpi-member-dropdown {
    background-color: #ffffff;
    border: 1px solid #cbd5e1;
    box-shadow: 0 16px 36px -6px rgba(15, 23, 42, 0.2);
}
[data-theme="dark"] .kpi-member-dropdown,
.dark .kpi-member-dropdown {
    background-color: #162a52 !important;
    border: 1.5px solid rgba(56, 189, 248, 0.4) !important;
    box-shadow: 0 20px 45px rgba(0, 0, 0, 0.6) !important;
}
[data-theme="neon"] .kpi-member-dropdown {
    background-color: rgba(14, 38, 92, 0.98) !important;
    border: 1.5px solid rgba(0, 210, 255, 0.7) !important;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6), 0 0 25px rgba(0, 180, 255, 0.35) !important;
}

</style>
