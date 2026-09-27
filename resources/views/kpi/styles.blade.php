<style>
/* ==========================================================================
   KPI SYSTEM MASTER THEME STYLES
   Full Support for:
     1. Neon UI       ([data-theme="neon"])
     2. Dark Mode     ([data-theme="dark"], .dark)
     3. Light Mode    (Default, [data-theme="light"])
   ========================================================================== */

/* ──────────────────────────────────────────────────────────────────────────
   1. LIGHT MODE (Default)
   ────────────────────────────────────────────────────────────────────────── */
.clay-card {
    background: #ffffff !important;
    border-radius: 1.5rem;
    border: 1px solid rgba(226, 232, 240, 0.9) !important;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05), 0 8px 10px -6px rgba(15, 23, 42, 0.03) !important;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.clay-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 16px 32px -4px rgba(15, 23, 42, 0.09), 0 8px 16px -4px rgba(15, 23, 42, 0.04) !important;
}

.kpi-hero-banner {
    background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 55%, #0284c7 100%) !important;
    box-shadow: 0 12px 30px -4px rgba(37, 99, 235, 0.35) !important;
}

.clay-pill-blue {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%) !important;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35) !important;
}
.clay-pill-mint {
    background: linear-gradient(135deg, #10b981 0%, #047857 100%) !important;
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35) !important;
}
.clay-pill-purple {
    background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%) !important;
    box-shadow: 0 4px 14px rgba(139, 92, 246, 0.35) !important;
}
.clay-pill-amber {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
    box-shadow: 0 4px 14px rgba(245, 158, 11, 0.35) !important;
}

.clay-btn {
    border-radius: 9999px;
    font-weight: 600;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.clay-btn:active {
    transform: scale(0.97);
}

.kpi-stat-title {
    color: #64748b !important;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.kpi-stat-value {
    color: #0f172a !important;
    font-size: 1.75rem;
    font-weight: 900;
    line-height: 1.1;
}
.kpi-stat-sub {
    color: #64748b !important;
    font-size: 0.75rem;
}

.kpi-table-head {
    background-color: #f8fafc !important;
    color: #64748b !important;
    border-bottom: 1px solid #e2e8f0 !important;
}
.kpi-table-row {
    border-bottom: 1px solid #f1f5f9 !important;
    color: #334155 !important;
    transition: background-color 0.15s ease;
}
.kpi-table-row:hover {
    background-color: #f8fafc !important;
}

.kpi-badge-success {
    background-color: #dcfce7 !important;
    color: #15803d !important;
    border: 1px solid #bbf7d0 !important;
}
.kpi-badge-amber {
    background-color: #fef3c7 !important;
    color: #b45309 !important;
    border: 1px solid #fde68a !important;
}
.kpi-badge-purple {
    background-color: #f3e8ff !important;
    color: #7e22ce !important;
    border: 1px solid #e9d5ff !important;
}
.kpi-badge-blue {
    background-color: #e0f2fe !important;
    color: #0369a1 !important;
    border: 1px solid #bae6fd !important;
}
.kpi-badge-pending {
    background-color: #f1f5f9 !important;
    color: #64748b !important;
    border: 1px solid #e2e8f0 !important;
}

/* ──────────────────────────────────────────────────────────────────────────
   2. DARK MODE ([data-theme="dark"], .dark)
   ────────────────────────────────────────────────────────────────────────── */
[data-theme="dark"] .clay-card,
.dark .clay-card {
    background: #1e293b !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.45) !important;
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
    background-color: #0f172a !important;
    color: #94a3b8 !important;
    border-bottom: 1px solid #334155 !important;
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

[data-theme="dark"] .kpi-badge-success,
.dark .kpi-badge-success {
    background-color: rgba(16, 185, 129, 0.15) !important;
    color: #6ee7b7 !important;
    border: 1px solid rgba(16, 185, 129, 0.3) !important;
}
[data-theme="dark"] .kpi-badge-amber,
.dark .kpi-badge-amber {
    background-color: rgba(245, 158, 11, 0.15) !important;
    color: #fcd34d !important;
    border: 1px solid rgba(245, 158, 11, 0.3) !important;
}
[data-theme="dark"] .kpi-badge-purple,
.dark .kpi-badge-purple {
    background-color: rgba(139, 92, 246, 0.15) !important;
    color: #c4b5fd !important;
    border: 1px solid rgba(139, 92, 246, 0.3) !important;
}
[data-theme="dark"] .kpi-badge-blue,
.dark .kpi-badge-blue {
    background-color: rgba(59, 130, 246, 0.15) !important;
    color: #93c5fd !important;
    border: 1px solid rgba(59, 130, 246, 0.3) !important;
}
[data-theme="dark"] .kpi-badge-pending,
.dark .kpi-badge-pending {
    background-color: rgba(51, 65, 85, 0.4) !important;
    color: #94a3b8 !important;
    border: 1px solid #334155 !important;
}

[data-theme="dark"] .kpi-card-header,
.dark .kpi-card-header {
    border-bottom: 1px solid #334155 !important;
}
[data-theme="dark"] .kpi-card-title,
.dark .kpi-card-title {
    color: #f8fafc !important;
}
[data-theme="dark"] .kpi-card-desc,
.dark .kpi-card-desc {
    color: #94a3b8 !important;
}

/* Modal Dark */
[data-theme="dark"] .kpi-modal-card,
.dark .kpi-modal-card {
    background-color: #1e293b !important;
    border: 1px solid #334155 !important;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.7) !important;
}
[data-theme="dark"] .kpi-modal-box,
.dark .kpi-modal-box {
    background-color: #0f172a !important;
    border: 1px solid #334155 !important;
}
[data-theme="dark"] .kpi-modal-calc,
.dark .kpi-modal-calc {
    background: #0f172a !important;
    border: 1px solid #334155 !important;
}

/* ──────────────────────────────────────────────────────────────────────────
   3. NEON UI ([data-theme="neon"]) ── Electric Cyber Glassmorphism
   ────────────────────────────────────────────────────────────────────────── */
[data-theme="neon"] .clay-card {
    background: rgba(4, 20, 56, 0.90) !important;
    backdrop-filter: blur(20px) !important;
    -webkit-backdrop-filter: blur(20px) !important;
    border: 1.5px solid rgba(0, 170, 255, 0.45) !important;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6), 0 0 20px rgba(0, 140, 255, 0.22), inset 0 1px 1px rgba(255, 255, 255, 0.12) !important;
}
[data-theme="neon"] .clay-card:hover {
    border-color: rgba(0, 230, 255, 0.85) !important;
    box-shadow: 0 16px 45px rgba(0, 0, 0, 0.7), 0 0 30px rgba(0, 200, 255, 0.5) !important;
    transform: translateY(-3px);
}

[data-theme="neon"] .kpi-hero-banner {
    background: linear-gradient(135deg, #020b24 0%, #051d54 40%, #004ecc 85%, #00a2ff 100%) !important;
    border: 1.5px solid rgba(0, 210, 255, 0.6) !important;
    box-shadow: 0 16px 45px rgba(0, 0, 0, 0.7), 0 0 32px rgba(0, 160, 255, 0.4) !important;
}

[data-theme="neon"] .clay-pill-blue {
    background: linear-gradient(135deg, #0055ff 0%, #00d2ff 100%) !important;
    box-shadow: 0 0 18px rgba(0, 180, 255, 0.6), inset 0 1px 2px rgba(255, 255, 255, 0.4) !important;
}
[data-theme="neon"] .clay-pill-mint {
    background: linear-gradient(135deg, #059669 0%, #00f0ff 100%) !important;
    box-shadow: 0 0 18px rgba(0, 240, 255, 0.55), inset 0 1px 2px rgba(255, 255, 255, 0.4) !important;
}
[data-theme="neon"] .clay-pill-purple {
    background: linear-gradient(135deg, #7c3aed 0%, #c084fc 100%) !important;
    box-shadow: 0 0 18px rgba(168, 85, 247, 0.6), inset 0 1px 2px rgba(255, 255, 255, 0.4) !important;
}
[data-theme="neon"] .clay-pill-amber {
    background: linear-gradient(135deg, #d97706 0%, #fbbf24 100%) !important;
    box-shadow: 0 0 18px rgba(245, 158, 11, 0.55), inset 0 1px 2px rgba(255, 255, 255, 0.4) !important;
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

[data-theme="neon"] .kpi-card-header {
    border-bottom: 1.5px solid rgba(0, 160, 255, 0.3) !important;
}
[data-theme="neon"] .kpi-card-title {
    color: #ffffff !important;
    text-shadow: 0 0 12px rgba(0, 180, 255, 0.35) !important;
}
[data-theme="neon"] .kpi-card-desc {
    color: #7dd3fc !important;
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

[data-theme="neon"] .kpi-badge-success {
    background-color: rgba(0, 240, 255, 0.15) !important;
    color: #38bdf8 !important;
    border: 1px solid rgba(0, 210, 255, 0.5) !important;
    box-shadow: 0 0 10px rgba(0, 210, 255, 0.3) !important;
}
[data-theme="neon"] .kpi-badge-amber {
    background-color: rgba(245, 158, 11, 0.18) !important;
    color: #fde047 !important;
    border: 1px solid rgba(245, 158, 11, 0.5) !important;
    box-shadow: 0 0 10px rgba(245, 158, 11, 0.3) !important;
}
[data-theme="neon"] .kpi-badge-purple {
    background-color: rgba(168, 85, 247, 0.18) !important;
    color: #e9d5ff !important;
    border: 1px solid rgba(168, 85, 247, 0.5) !important;
    box-shadow: 0 0 10px rgba(168, 85, 247, 0.3) !important;
}
[data-theme="neon"] .kpi-badge-blue {
    background-color: rgba(0, 140, 255, 0.18) !important;
    color: #93c5fd !important;
    border: 1px solid rgba(0, 140, 255, 0.5) !important;
    box-shadow: 0 0 10px rgba(0, 140, 255, 0.3) !important;
}
[data-theme="neon"] .kpi-badge-pending {
    background-color: rgba(3, 16, 48, 0.6) !important;
    color: #7dd3fc !important;
    border: 1px solid rgba(0, 160, 255, 0.3) !important;
}

/* Modal Neon */
[data-theme="neon"] .kpi-modal-card {
    background-color: rgba(3, 14, 42, 0.98) !important;
    backdrop-filter: blur(24px) !important;
    -webkit-backdrop-filter: blur(24px) !important;
    border: 1.5px solid rgba(0, 210, 255, 0.65) !important;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.9), 0 0 35px rgba(0, 180, 255, 0.4) !important;
}
[data-theme="neon"] .kpi-modal-box {
    background-color: rgba(2, 8, 25, 0.88) !important;
    border: 1.5px solid rgba(0, 160, 255, 0.4) !important;
}
[data-theme="neon"] .kpi-modal-calc {
    background: linear-gradient(135deg, rgba(0, 50, 150, 0.45) 0%, rgba(0, 170, 255, 0.22) 100%) !important;
    border: 1.5px solid rgba(0, 220, 255, 0.65) !important;
    box-shadow: 0 0 22px rgba(0, 160, 255, 0.3) !important;
}
[data-theme="neon"] .kpi-modal-input,
[data-theme="neon"] .kpi-modal-textarea,
[data-theme="neon"] .kpi-modal-select {
    background-color: rgba(2, 8, 25, 0.92) !important;
    border: 1.5px solid rgba(0, 160, 255, 0.45) !important;
    color: #ffffff !important;
}
[data-theme="neon"] .kpi-modal-input:focus,
[data-theme="neon"] .kpi-modal-textarea:focus,
[data-theme="neon"] .kpi-modal-select:focus {
    border-color: #00f0ff !important;
    box-shadow: 0 0 14px rgba(0, 240, 255, 0.45) !important;
}

/* Squad Selector Tabs */
[data-theme="neon"] .kpi-tab-container {
    background-color: rgba(2, 10, 32, 0.92) !important;
    border: 1.5px solid rgba(0, 160, 255, 0.4) !important;
}
[data-theme="neon"] .kpi-tab-active {
    background: linear-gradient(135deg, #0066ff 0%, #00d2ff 100%) !important;
    color: #ffffff !important;
    box-shadow: 0 0 18px rgba(0, 210, 255, 0.55) !important;
}
[data-theme="neon"] .kpi-tab-inactive {
    color: #7dd3fc !important;
}
[data-theme="neon"] .kpi-tab-inactive:hover {
    color: #ffffff !important;
}

/* Action Buttons */
[data-theme="neon"] .kpi-btn-rate {
    background: linear-gradient(135deg, rgba(0, 102, 255, 0.25), rgba(0, 210, 255, 0.25)) !important;
    border: 1.5px solid rgba(0, 210, 255, 0.6) !important;
    color: #00f0ff !important;
    box-shadow: 0 0 12px rgba(0, 210, 255, 0.25) !important;
}
[data-theme="neon"] .kpi-btn-rate:hover {
    background: linear-gradient(135deg, #0077ff, #00f0ff) !important;
    color: #020819 !important;
    font-weight: 800 !important;
    box-shadow: 0 0 20px rgba(0, 240, 255, 0.6) !important;
}
[data-theme="neon"] .kpi-btn-pdf {
    background: rgba(255, 255, 255, 0.08) !important;
    border: 1px solid rgba(0, 160, 255, 0.4) !important;
    color: #bae6fd !important;
}
[data-theme="neon"] .kpi-btn-pdf:hover {
    background: rgba(0, 160, 255, 0.25) !important;
    color: #ffffff !important;
    box-shadow: 0 0 14px rgba(0, 160, 255, 0.35) !important;
}
</style>


.kpi-modal-card {
    background-color: #0f172a !important;
    background: #0f172a !important;
    border: 1.5px solid rgba(56, 189, 248, 0.35) !important;
    border-radius: 1.5rem !important;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.9), 0 0 35px rgba(14, 165, 233, 0.2) !important;
    color: #f8fafc !important;
}
.kpi-modal-box {
    background-color: #162036 !important;
    border: 1px solid rgba(51, 65, 85, 0.8) !important;
    color: #f8fafc !important;
}
