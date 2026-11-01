@extends('layouts.app')
@section('title', 'Blog Posts CMS')
@section('page_title', 'Blog Posts CMS')

@section('content')
<style>
/* ══════════════════════════════════════════════════════════════════
   HAPPY MACHINERY PROFESSIONAL CMS DESIGN SYSTEM (Ported & Unified)
   ══════════════════════════════════════════════════════════════════ */

:root {
    --cms-bg-card: #ffffff;
    --cms-bg-input: #f8fafc;
    --cms-border: #e2e8f0;
    --cms-border-focus: #2563eb;
    --cms-text-primary: #0f172a;
    --cms-text-secondary: #475569;
    --cms-text-muted: #94a3b8;
    --cms-accent-blue: #2563eb;
    --cms-accent-blue-hover: #1d4ed8;
}

.dark, [data-theme="dark"] {
    --cms-bg-card: #0f172a;
    --cms-bg-input: #1e293b;
    --cms-border: #334155;
    --cms-border-focus: #3b82f6;
    --cms-text-primary: #f8fafc;
    --cms-text-secondary: #cbd5e1;
    --cms-text-muted: #64748b;
}

[data-theme="neon"] {
    --cms-bg-card: rgba(8, 24, 60, 0.95);
    --cms-bg-input: rgba(15, 35, 75, 0.85);
    --cms-border: rgba(0, 160, 255, 0.35);
    --cms-border-focus: #00d2ff;
    --cms-text-primary: #f0f9ff;
    --cms-text-secondary: #bae6fd;
    --cms-text-muted: #7dd3fc;
}

/* ══════════════════════════════════════════════════════════════
   FRONT PAGE CMS HUB STYLES (Screen 1)
   ══════════════════════════════════════════════════════════════ */
.cms-hub-hero {
    background: var(--cms-bg-card);
    border: 1px solid var(--cms-border);
    border-radius: 20px;
    padding: 24px 28px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
}

.cms-btn-new-blog {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 22px;
    font-size: 13.5px;
    font-weight: 850;
    border-radius: 12px;
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 50%, #1e40af 100%);
    border: 1px solid #1d4ed8;
    color: #ffffff;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
    transition: all 180ms ease;
    text-decoration: none;
}
.cms-btn-new-blog:hover {
    background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(37, 99, 235, 0.45);
    color: #ffffff;
}

.cms-kpi-card {
    background: var(--cms-bg-card);
    border: 1px solid var(--cms-border);
    border-radius: 16px;
    padding: 18px 20px;
    transition: all 160ms ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
}
.cms-kpi-card:hover {
    border-color: #cbd5e1;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.05);
}
.dark .cms-kpi-card:hover {
    border-color: #475569;
}

.cms-table-container {
    background: var(--cms-bg-card);
    border: 1px solid var(--cms-border);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
}

/* ── Top Header Navigation Bar (Sticky On Top - Image 1 UX) ── */
.cms-top-header-row {
    position: sticky;
    top: 0;
    z-index: 50;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding-top: 12px;
    padding-bottom: 14px;
    margin-bottom: 16px;
    background: var(--cms-bg-card);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--cms-border);
    box-shadow: 0 4px 14px -2px rgba(0, 0, 0, 0.04);
}

.cms-header-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.cms-btn-header-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 12px;
    background: var(--cms-bg-card);
    border: 1px solid var(--cms-border);
    color: var(--cms-text-secondary);
    cursor: pointer;
    transition: all 140ms ease;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}
.cms-btn-header-back:hover {
    background: var(--cms-bg-input);
    color: var(--cms-text-primary);
    transform: translateX(-2px);
}

.cms-header-title-box h1 {
    font-size: 1.35rem;
    font-weight: 850;
    color: var(--cms-text-primary);
    line-height: 1.2;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}

.cms-header-title-box p {
    font-size: 0.78rem;
    color: var(--cms-text-muted);
    margin: 2px 0 0 0;
}

.cms-header-status-badge {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    padding: 2.5px 8.5px;
    border-radius: 9999px;
    display: inline-flex;
    align-items: center;
}
.cms-header-status-badge.status-draft {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}
.cms-header-status-badge.status-published {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}
.cms-header-status-badge.status-scheduled {
    background: #e0e7ff;
    color: #3730a3;
    border: 1px solid #c7d2fe;
}

.dark .cms-header-status-badge.status-draft { background: rgba(245, 158, 11, 0.2); color: #fde68a; border-color: rgba(245, 158, 11, 0.4); }
.dark .cms-header-status-badge.status-published { background: rgba(34, 197, 94, 0.2); color: #86efac; border-color: rgba(34, 197, 94, 0.4); }
.dark .cms-header-status-badge.status-scheduled { background: rgba(99, 102, 241, 0.2); color: #c7d2fe; border-color: rgba(99, 102, 241, 0.4); }

/* Header Action Buttons */
.cms-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* + Add More Button (Rapid Multi-Site Posting) */
.cms-btn-header-add-more {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    font-size: 12.5px;
    font-weight: 800;
    border-radius: 10px;
    background: rgba(37, 99, 235, 0.08);
    border: 1.5px solid rgba(37, 99, 235, 0.35);
    color: #2563eb;
    cursor: pointer;
    transition: all 140ms ease;
    box-shadow: 0 1px 3px rgba(37, 99, 235, 0.08);
}
.cms-btn-header-add-more:hover {
    background: #2563eb;
    border-color: #2563eb;
    color: #ffffff;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
}
.dark .cms-btn-header-add-more {
    background: rgba(37, 99, 235, 0.2);
    border-color: rgba(96, 165, 250, 0.4);
    color: #93c5fd;
}
.dark .cms-btn-header-add-more:hover {
    background: #2563eb;
    color: #ffffff;
}

.cms-btn-header-draft {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    font-size: 12.5px;
    font-weight: 700;
    border-radius: 10px;
    background: var(--cms-bg-card);
    border: 1.5px solid var(--cms-border);
    color: var(--cms-text-primary);
    cursor: pointer;
    transition: all 140ms ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}
.cms-btn-header-draft:hover {
    background: var(--cms-bg-input);
    border-color: #94a3b8;
    transform: translateY(-1px);
}

.cms-btn-header-publish {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 20px;
    font-size: 12.5px;
    font-weight: 800;
    border-radius: 11px;
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 50%, #991b1b 100%);
    border: 1px solid #b91c1c;
    color: #ffffff;
    cursor: pointer;
    box-shadow: 0 3px 12px rgba(220, 38, 38, 0.35);
    transition: all 150ms ease;
}
.cms-btn-header-publish:hover {
    background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
    transform: translateY(-1.5px);
    box-shadow: 0 6px 18px rgba(220, 38, 38, 0.45);
}

/* ── Multi-Article Batch Card Container & Collapse Styling (Image 1 UX) ── */
.cms-article-card-wrapper {
    background: var(--cms-bg-card);
    border: 1.5px solid var(--cms-border);
    border-radius: 24px;
    padding: 24px 26px;
    box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.04);
    transition: all 200ms ease;
    position: relative;
    margin-bottom: 28px;
}
.cms-article-card-wrapper:hover {
    border-color: rgba(37, 99, 235, 0.35);
}
.dark .cms-article-card-wrapper {
    box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.4);
}
.cms-article-header-ribbon {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding-bottom: 16px;
    margin-bottom: 20px;
    border-bottom: 1.5px solid var(--cms-border);
}
.cms-article-index-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 850;
    letter-spacing: 0.03em;
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
}
.cms-btn-collapse-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6.5px 14px;
    font-size: 12px;
    font-weight: 750;
    border-radius: 10px;
    background: var(--cms-bg-card);
    border: 1.5px solid var(--cms-border);
    color: var(--cms-text-primary);
    cursor: pointer;
    transition: all 140ms ease;
}
.cms-btn-collapse-toggle:hover {
    background: var(--cms-bg-input);
    border-color: #94a3b8;
    transform: translateY(-1px);
}
.cms-btn-remove-article {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    font-size: 11.5px;
    font-weight: 750;
    border-radius: 10px;
    background: rgba(239, 68, 68, 0.08);
    border: 1.5px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
    cursor: pointer;
    transition: all 140ms ease;
}
.cms-btn-remove-article:hover {
    background: #ef4444;
    border-color: #ef4444;
    color: #ffffff;
    transform: translateY(-1px);
}
.cms-article-collapsed-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px 20px;
    background: var(--cms-bg-input);
    border: 1.5px dashed var(--cms-border);
    border-radius: 16px;
    cursor: pointer;
    transition: all 150ms ease;
}
.cms-article-collapsed-box:hover {
    background: rgba(37, 99, 235, 0.04);
    border-color: #3b82f6;
}
.cms-batch-top-strip {
    background: var(--cms-bg-card);
    border: 1.5px solid var(--cms-border);
    border-radius: 18px;
    padding: 14px 20px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
}
.cms-batch-floating-bar {
    position: sticky;
    bottom: 20px;
    z-index: 45;
    margin-top: 32px;
    padding: 14px 24px;
    background: var(--cms-bg-card);
    border: 1.5px solid var(--cms-border);
    border-radius: 20px;
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    box-shadow: 0 12px 36px -6px rgba(0, 0, 0, 0.16), 0 4px 12px rgba(0, 0, 0, 0.06);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}

/* ── Sleek Inspector Segmented Navigation Tabs ── */
.cms-inspector-nav {
    display: flex;
    align-items: center;
    background: var(--cms-bg-card);
    border: 1px solid var(--cms-border);
    padding: 4px;
    border-radius: 14px;
    gap: 4px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    margin-bottom: 8px;
}
.cms-inspector-tab-btn {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 8px 6px;
    font-size: 11.5px;
    font-weight: 750;
    border-radius: 10px;
    border: none;
    background: transparent;
    color: var(--cms-text-secondary);
    cursor: pointer;
    transition: all 140ms ease;
    white-space: nowrap;
}
.cms-inspector-tab-btn:hover {
    color: var(--cms-text-primary);
    background: var(--cms-bg-input);
}
.cms-inspector-tab-btn.active {
    background: #2563eb;
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
}
.dark .cms-inspector-tab-btn.active {
    background: #3b82f6;
    color: #ffffff;
}

/* ── Editor Card Design ── */
.cms-editor-card {
    background: var(--cms-bg-card);
    border: 1px solid var(--cms-border);
    border-radius: 16px;
    padding: 20px 24px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    position: relative;
    transition: border-color 150ms ease, box-shadow 150ms ease;
}

.cms-editor-eyebrow {
    display: block;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--cms-text-muted);
    margin-bottom: 8px;
}

/* Article Title & Slug Card (Matches Image 2) */
.cms-title-slug-card {
    padding: 24px 28px !important;
}

.cms-raf-title-input {
    width: 100%;
    font-size: 1.65rem;
    font-weight: 850;
    color: var(--cms-text-primary);
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    border-radius: 0;
    padding: 4px 0 10px 0;
    outline: none;
    transition: all 150ms ease;
    box-sizing: border-box;
    font-family: inherit;
    line-height: 1.25;
}
.cms-raf-title-input:focus {
    border-bottom-color: #2563eb;
}
.cms-raf-title-input::placeholder {
    color: #94a3b8;
    font-weight: 600;
}

.cms-raf-slug-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
    padding: 8px 12px;
    border-radius: 12px;
    background: var(--cms-bg-input);
    border: 1px solid var(--cms-border);
    font-size: 12px;
}

.cms-slug-prefix {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 12px;
    color: var(--cms-text-muted);
    font-weight: 600;
    white-space: nowrap;
}

.cms-raf-slug-input {
    flex: 1;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 12px;
    padding: 5px 10px;
    border-radius: 8px;
    border: 1px solid var(--cms-border);
    background: var(--cms-bg-input);
    color: var(--cms-text-primary);
    outline: none;
    transition: all 140ms ease;
}
.cms-raf-slug-input:focus {
    border-color: var(--cms-border-focus);
    box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.12);
    background: var(--cms-bg-card);
}

/* Article Body Header & Mode Switcher */
.cms-article-content-card {
    padding: 20px 24px !important;
}

.cms-editor-card-header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding-bottom: 14px;
    border-bottom: 1px solid var(--cms-border);
}

.cms-wysiwyg-badge {
    font-size: 10.5px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
    background: rgba(99, 102, 241, 0.1);
    color: #6366f1;
}
.dark .cms-wysiwyg-badge { background: rgba(99, 102, 241, 0.25); color: #a5b4fc; }

.cms-btn-smart-import {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 750;
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    color: #ffffff;
    border: none;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.28);
    transition: all 140ms ease;
}
.cms-btn-smart-import:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
}

.cms-raf-mode-switcher {
    display: inline-flex;
    align-items: center;
    background: var(--cms-bg-input);
    padding: 3px;
    border-radius: 9px;
    border: 1px solid var(--cms-border);
    gap: 2px;
}

.cms-raf-mode-btn {
    border: none;
    background: transparent;
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 650;
    color: var(--cms-text-muted);
    cursor: pointer;
    transition: all 140ms ease;
}
.cms-raf-mode-btn:hover {
    color: var(--cms-text-primary);
}
.cms-raf-mode-btn.active {
    background: var(--cms-bg-card);
    color: #2563eb;
    font-weight: 800;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

/* Rich Toolbar Ribbon */
.cms-toolbar-ribbon {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px;
    padding: 10px 0;
    border-bottom: 1px solid var(--cms-border);
}

.cms-tool-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 28px;
    height: 28px;
    padding: 2px 7px;
    border-radius: 6px;
    border: 1px solid transparent;
    background: transparent;
    color: var(--cms-text-secondary);
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: all 120ms ease;
}
.cms-tool-btn:hover {
    background: var(--cms-bg-input);
    border-color: var(--cms-border);
    color: var(--cms-text-primary);
}

.cms-tool-divider {
    width: 1px;
    height: 18px;
    background: var(--cms-border);
    margin: 0 4px;
}

.cms-tool-pill-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 7px;
    font-size: 11.5px;
    font-weight: 700;
    border: 1px solid var(--cms-border);
    background: var(--cms-bg-input);
    color: var(--cms-text-secondary);
    cursor: pointer;
    transition: all 120ms ease;
}
.cms-tool-pill-btn:hover {
    border-color: #94a3b8;
    color: var(--cms-text-primary);
}

/* ── Right Inspector Cards (Image 2 style) ── */
.cms-editor-sidebar-col {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.cms-card-subhead {
    font-size: 13px;
    font-weight: 800;
    color: var(--cms-text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 7px;
}

.cms-field-label-sm {
    display: block;
    font-size: 11px;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--cms-text-secondary);
    margin-bottom: 6px;
}

.cms-editor-select, .cms-editor-input-sm {
    width: 100%;
    font-size: 12.5px;
    padding: 8px 12px;
    border-radius: 10px;
    border: 1px solid var(--cms-border);
    background: var(--cms-bg-input);
    color: var(--cms-text-primary);
    outline: none;
    transition: all 140ms ease;
    box-sizing: border-box;
}
.cms-editor-select:focus, .cms-editor-input-sm:focus, .cms-editor-textarea:focus {
    border-color: var(--cms-border-focus);
    box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.12);
    background: var(--cms-bg-card);
}

.cms-editor-textarea {
    width: 100%;
    font-size: 12.5px;
    padding: 9px 12px;
    border-radius: 10px;
    border: 1px solid var(--cms-border);
    background: var(--cms-bg-input);
    color: var(--cms-text-primary);
    outline: none;
    transition: all 140ms ease;
    resize: vertical;
    box-sizing: border-box;
    line-height: 1.5;
}

/* Scheduled Post Alert Box */
.cms-scheduled-box {
    padding: 10px 12px;
    border-radius: 10px;
    background: rgba(245, 158, 11, 0.08);
    border: 1px solid rgba(245, 158, 11, 0.3);
}

/* Cover Image Dropzone & Preview */
.cms-cover-dropzone {
    border: 2px dashed var(--cms-border);
    border-radius: 12px;
    padding: 24px 16px;
    text-align: center;
    cursor: pointer;
    background: var(--cms-bg-input);
    transition: all 150ms ease;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
}
.cms-cover-dropzone:hover {
    border-color: #2563eb;
    background: rgba(37, 99, 235, 0.04);
}

.cms-cover-preview-wrapper {
    position: relative;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid var(--cms-border);
    aspect-ratio: 16/9;
    background: #000;
}
.cms-cover-preview-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.cms-cover-preview-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    opacity: 0;
    transition: opacity 150ms ease;
}
.cms-cover-preview-wrapper:hover .cms-cover-preview-overlay {
    opacity: 1;
}
.cms-cover-overlay-btn {
    padding: 5px 12px;
    font-size: 11px;
    font-weight: 700;
    border-radius: 6px;
    background: #ffffff;
    color: #0f172a;
    border: none;
    cursor: pointer;
}
.cms-cover-overlay-btn.danger {
    background: #ef4444;
    color: #ffffff;
}

.cms-cover-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 7px 12px;
    border-radius: 8px;
    border: 1px solid var(--cms-border);
    background: var(--cms-bg-card);
    color: var(--cms-text-primary);
    font-size: 11.5px;
    font-weight: 700;
    cursor: pointer;
    transition: all 140ms ease;
}
.cms-cover-action-btn:hover {
    background: var(--cms-bg-input);
    border-color: #94a3b8;
}

.cms-cover-autodetect-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    font-size: 11px;
    font-weight: 700;
    color: #0284c7;
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-radius: 6px;
    cursor: pointer;
    transition: all 140ms ease;
}
.cms-cover-autodetect-btn:hover {
    background: #e0f2fe;
    border-color: #7dd3fc;
}
.dark .cms-cover-autodetect-btn {
    background: rgba(2, 132, 199, 0.18);
    border-color: rgba(2, 132, 199, 0.4);
    color: #38bdf8;
}

/* Custom Rich Website Dropdown */
.cms-site-select-trigger {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 8px 12px;
    background: var(--cms-bg-input, #f8fafc);
    border: 1px solid var(--cms-border, #e2e8f0);
    border-radius: 12px;
    cursor: pointer;
    transition: all 140ms ease;
    text-align: left;
    color: var(--cms-text-primary, #0f172a);
}
.cms-site-select-trigger:hover,
.cms-site-select-trigger.is-open {
    border-color: #3b82f6;
    background: var(--cms-bg-card, #ffffff);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.12);
}
.dark .cms-site-select-trigger,
[data-theme="dark"] .cms-site-select-trigger,
html.dark .cms-site-select-trigger,
html[data-theme="dark"] .cms-site-select-trigger,
body.dark .cms-site-select-trigger,
body[data-theme="dark"] .cms-site-select-trigger {
    background: #1e293b !important;
    border-color: #334155 !important;
    color: #f8fafc !important;
}
.dark .cms-site-select-trigger:hover,
.dark .cms-site-select-trigger.is-open,
[data-theme="dark"] .cms-site-select-trigger:hover,
[data-theme="dark"] .cms-site-select-trigger.is-open,
html.dark .cms-site-select-trigger:hover,
html.dark .cms-site-select-trigger.is-open,
html[data-theme="dark"] .cms-site-select-trigger:hover,
html[data-theme="dark"] .cms-site-select-trigger.is-open,
body.dark .cms-site-select-trigger:hover,
body.dark .cms-site-select-trigger.is-open,
body[data-theme="dark"] .cms-site-select-trigger:hover,
body[data-theme="dark"] .cms-site-select-trigger.is-open {
    background: #0f172a !important;
    border-color: #60a5fa !important;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.4);
}
[data-theme="neon"] .cms-site-select-trigger,
html[data-theme="neon"] .cms-site-select-trigger,
body[data-theme="neon"] .cms-site-select-trigger {
    background: rgba(15, 35, 75, 0.85) !important;
    border-color: rgba(0, 160, 255, 0.35) !important;
    color: #f0f9ff !important;
}
[data-theme="neon"] .cms-site-select-trigger:hover,
[data-theme="neon"] .cms-site-select-trigger.is-open,
html[data-theme="neon"] .cms-site-select-trigger:hover,
html[data-theme="neon"] .cms-site-select-trigger.is-open,
body[data-theme="neon"] .cms-site-select-trigger:hover,
body[data-theme="neon"] .cms-site-select-trigger.is-open {
    background: rgba(8, 24, 60, 0.95) !important;
    border-color: #00d2ff !important;
    box-shadow: 0 0 16px rgba(0, 210, 255, 0.35);
}

.cms-site-title {
    color: #0f172a;
    font-weight: 700;
}
.dark .cms-site-title,
[data-theme="dark"] .cms-site-title,
html.dark .cms-site-title,
html[data-theme="dark"] .cms-site-title,
body.dark .cms-site-title,
body[data-theme="dark"] .cms-site-title {
    color: #f8fafc !important;
}
[data-theme="neon"] .cms-site-title,
html[data-theme="neon"] .cms-site-title,
body[data-theme="neon"] .cms-site-title {
    color: #f0f9ff !important;
    text-shadow: 0 0 8px rgba(0, 210, 255, 0.25);
}

.cms-site-domain {
    color: #64748b;
}
.dark .cms-site-domain,
[data-theme="dark"] .cms-site-domain,
html.dark .cms-site-domain,
html[data-theme="dark"] .cms-site-domain,
body.dark .cms-site-domain,
body[data-theme="dark"] .cms-site-domain {
    color: #94a3b8 !important;
}
[data-theme="neon"] .cms-site-domain,
html[data-theme="neon"] .cms-site-domain,
body[data-theme="neon"] .cms-site-domain {
    color: #7dd3fc !important;
}

.cms-site-dropdown-popover {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    right: 0;
    z-index: 60;
    background: var(--cms-bg-card, #ffffff);
    border: 1px solid var(--cms-border, #e2e8f0);
    border-radius: 16px;
    box-shadow: 0 16px 36px -4px rgba(15, 23, 42, 0.18), 0 6px 16px -2px rgba(15, 23, 42, 0.08);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    will-change: transform, opacity;
    contain: paint layout;
}
.dark .cms-site-dropdown-popover,
[data-theme="dark"] .cms-site-dropdown-popover,
html.dark .cms-site-dropdown-popover,
html[data-theme="dark"] .cms-site-dropdown-popover,
body.dark .cms-site-dropdown-popover,
body[data-theme="dark"] .cms-site-dropdown-popover {
    background: #0f172a !important;
    border-color: #334155 !important;
    box-shadow: 0 20px 40px -4px rgba(0, 0, 0, 0.7);
}
[data-theme="neon"] .cms-site-dropdown-popover,
html[data-theme="neon"] .cms-site-dropdown-popover,
body[data-theme="neon"] .cms-site-dropdown-popover {
    background: rgba(8, 24, 60, 0.98) !important;
    border-color: rgba(0, 160, 255, 0.45) !important;
    box-shadow: 0 20px 45px -4px rgba(0, 0, 0, 0.8), 0 0 25px rgba(0, 160, 255, 0.2);
}

.cms-site-search-wrap {
    padding: 8px;
    border-bottom: 1px solid var(--cms-border, #f1f5f9);
    background: var(--cms-bg-input, #f8fafc);
}
.dark .cms-site-search-wrap,
[data-theme="dark"] .cms-site-search-wrap,
html.dark .cms-site-search-wrap,
html[data-theme="dark"] .cms-site-search-wrap,
body.dark .cms-site-search-wrap,
body[data-theme="dark"] .cms-site-search-wrap {
    border-bottom-color: #1e293b !important;
    background: #0b1324 !important;
}
[data-theme="neon"] .cms-site-search-wrap,
html[data-theme="neon"] .cms-site-search-wrap,
body[data-theme="neon"] .cms-site-search-wrap {
    border-bottom-color: rgba(0, 160, 255, 0.25) !important;
    background: rgba(5, 18, 45, 0.95) !important;
}

.cms-site-search-input {
    width: 100%;
    padding: 6px 12px 6px 32px;
    font-size: 11.5px;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #0f172a;
    outline: none;
    transition: all 120ms ease;
}
.cms-site-search-input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}
.dark .cms-site-search-input,
[data-theme="dark"] .cms-site-search-input,
html.dark .cms-site-search-input,
html[data-theme="dark"] .cms-site-search-input,
body.dark .cms-site-search-input,
body[data-theme="dark"] .cms-site-search-input {
    border-color: #334155 !important;
    background: #1e293b !important;
    color: #f8fafc !important;
}
.dark .cms-site-search-input:focus,
[data-theme="dark"] .cms-site-search-input:focus,
html.dark .cms-site-search-input:focus,
html[data-theme="dark"] .cms-site-search-input:focus,
body.dark .cms-site-search-input:focus,
body[data-theme="dark"] .cms-site-search-input:focus {
    border-color: #60a5fa !important;
    box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.25);
}
[data-theme="neon"] .cms-site-search-input,
html[data-theme="neon"] .cms-site-search-input,
body[data-theme="neon"] .cms-site-search-input {
    border-color: rgba(0, 160, 255, 0.35) !important;
    background: rgba(12, 32, 70, 0.8) !important;
    color: #f0f9ff !important;
}
[data-theme="neon"] .cms-site-search-input:focus,
html[data-theme="neon"] .cms-site-search-input:focus,
body[data-theme="neon"] .cms-site-search-input:focus {
    border-color: #00d2ff !important;
    box-shadow: 0 0 10px rgba(0, 210, 255, 0.4);
}

.cms-site-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 8px 12px;
    border-bottom: 1px solid var(--cms-border, #f1f5f9);
    cursor: pointer;
    transition: all 120ms ease;
    background: transparent;
}
.cms-site-item:last-child {
    border-bottom: none;
}
.dark .cms-site-item,
[data-theme="dark"] .cms-site-item,
html.dark .cms-site-item,
html[data-theme="dark"] .cms-site-item,
body.dark .cms-site-item,
body[data-theme="dark"] .cms-site-item {
    border-bottom-color: rgba(30, 41, 59, 0.6) !important;
}
[data-theme="neon"] .cms-site-item,
html[data-theme="neon"] .cms-site-item,
body[data-theme="neon"] .cms-site-item {
    border-bottom-color: rgba(0, 160, 255, 0.15) !important;
}
.cms-site-item:hover {
    background: #f1f5f9;
}
.dark .cms-site-item:hover,
[data-theme="dark"] .cms-site-item:hover,
html.dark .cms-site-item:hover,
html[data-theme="dark"] .cms-site-item:hover,
body.dark .cms-site-item:hover,
body[data-theme="dark"] .cms-site-item:hover {
    background: #1e293b !important;
}
[data-theme="neon"] .cms-site-item:hover,
html[data-theme="neon"] .cms-site-item:hover,
body[data-theme="neon"] .cms-site-item:hover {
    background: rgba(0, 160, 255, 0.18) !important;
}
.cms-site-item.is-selected {
    background: #eef2ff;
}
.dark .cms-site-item.is-selected,
[data-theme="dark"] .cms-site-item.is-selected,
html.dark .cms-site-item.is-selected,
html[data-theme="dark"] .cms-site-item.is-selected,
body.dark .cms-site-item.is-selected,
body[data-theme="dark"] .cms-site-item.is-selected {
    background: rgba(99, 102, 241, 0.22) !important;
    border-left: 3px solid #6366f1;
}
[data-theme="neon"] .cms-site-item.is-selected,
html[data-theme="neon"] .cms-site-item.is-selected,
body[data-theme="neon"] .cms-site-item.is-selected {
    background: rgba(0, 210, 255, 0.22) !important;
    border-left: 3px solid #00d2ff;
}

.cms-class-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 7px;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.02em;
    white-space: nowrap;
    border: 1px solid transparent;
}

.cms-handler-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 1.5px 6px 1.5px 2px;
    border-radius: 9999px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    font-size: 10px;
    font-weight: 600;
    color: #475569;
    white-space: nowrap;
}
.dark .cms-handler-chip,
[data-theme="dark"] .cms-handler-chip,
html.dark .cms-handler-chip,
html[data-theme="dark"] .cms-handler-chip,
body.dark .cms-handler-chip,
body[data-theme="dark"] .cms-handler-chip {
    background: #1e293b !important;
    border-color: #334155 !important;
    color: #cbd5e1 !important;
}
[data-theme="neon"] .cms-handler-chip,
html[data-theme="neon"] .cms-handler-chip,
body[data-theme="neon"] .cms-handler-chip {
    background: rgba(14, 40, 85, 0.85) !important;
    border-color: rgba(0, 160, 255, 0.35) !important;
    color: #bae6fd !important;
}

.cms-handler-avatar-img {
    width: 18px;
    height: 18px;
    border-radius: 9999px;
    object-fit: cover;
    background: #cbd5e1;
    border: 1px solid #ffffff;
    flex-shrink: 0;
}
.dark .cms-handler-avatar-img,
[data-theme="dark"] .cms-handler-avatar-img {
    border-color: #0f172a;
}
[data-theme="neon"] .cms-handler-avatar-img {
    border-color: #00d2ff;
}

.cms-handler-avatar-fallback {
    width: 18px;
    height: 18px;
    border-radius: 9999px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #ffffff;
    font-size: 9px;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12);
}

/* Rendered Elements */
.cms-rendered-table {
    width: 100%; border-collapse: collapse; margin: 1.5rem 0;
    border-radius: 0.75rem; overflow: hidden; font-size: 0.875rem;
    border: 1px solid #cbd5e1;
}
.dark .cms-rendered-table, [data-theme="dark"] .cms-rendered-table, [data-theme="neon"] .cms-rendered-table {
    border-color: #334155;
}
.cms-rendered-table th {
    background: #f1f5f9; padding: 0.75rem 1rem; text-align: left;
    font-weight: 700; color: #0f172a; border-bottom: 2px solid #cbd5e1;
}
.dark .cms-rendered-table th, [data-theme="dark"] .cms-rendered-table th, [data-theme="neon"] .cms-rendered-table th {
    background: #1e293b; color: #f8fafc; border-bottom-color: #475569;
}
.cms-rendered-table td {
    padding: 0.7rem 1rem; border-bottom: 1px solid #e2e8f0; color: #334155;
}
.dark .cms-rendered-table td, [data-theme="dark"] .cms-rendered-table td, [data-theme="neon"] .cms-rendered-table td {
    border-bottom-color: #334155; color: #cbd5e1;
}
.cms-rendered-table tr:nth-child(even) { background: #f8fafc; }
.dark .cms-rendered-table tr:nth-child(even), [data-theme="dark"] .cms-rendered-table tr:nth-child(even), [data-theme="neon"] .cms-rendered-table tr:nth-child(even) {
    background: rgba(30, 41, 59, 0.5);
}

.cms-rendered-figure {
    margin: 1.75rem auto; text-align: center; max-width: 100%;
}
.cms-rendered-figure img {
    border-radius: 1rem; box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    display: block; margin: 0 auto; max-width: 100%; height: auto;
}
.cms-rendered-figure figcaption {
    font-size: 0.8rem; color: #64748b; margin-top: 0.5rem;
    font-style: italic; font-weight: 500;
}
.dark .cms-rendered-figure figcaption { color: #94a3b8; }

.cms-youtube-wrapper {
    position: relative; width: 100%; padding-bottom: 56.25%; height: 0;
    margin: 1.75rem 0; border-radius: 1rem; overflow: hidden;
    box-shadow: 0 8px 30px rgba(0,0,0,0.15);
}
.cms-youtube-wrapper iframe {
    position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;
}

/* ══════════════════════════════════════════════════════════════
   100% NATIVE MOBILE APP UX/UI OVERRIDES (PHONES & TABLETS < 1024px)
   ══════════════════════════════════════════════════════════════ */
@media (max-width: 1023px) {
    .cms-hub-hero {
        padding: 12px 14px !important;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 10px !important;
        border-radius: 16px !important;
    }
    .cms-hub-hero > div:last-child {
        width: 100% !important;
    }
    .cms-btn-new-blog {
        width: 100% !important;
        justify-content: center !important;
        padding: 9px 16px !important;
        font-size: 13px !important;
        border-radius: 12px !important;
    }
    .cms-top-header-row {
        position: relative !important;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 8px !important;
        padding: 10px 12px !important;
        margin-bottom: 8px !important;
        background: var(--cms-bg-card) !important;
        border-radius: 16px !important;
        border: 1px solid var(--cms-border) !important;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03) !important;
    }
    .cms-header-left {
        width: 100% !important;
    }
    .cms-header-title-box h1 {
        font-size: 1.15rem !important;
        flex-wrap: wrap !important;
        gap: 6px !important;
    }
    .cms-header-actions {
        width: 100% !important;
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 8px !important;
    }
    .cms-header-actions button {
        padding: 10px 8px !important;
        font-size: 12px !important;
        font-weight: 800 !important;
        justify-content: center !important;
        border-radius: 12px !important;
        min-height: 42px !important;
        touch-action: manipulation !important;
    }
    .cms-studio-workspace {
        display: block !important;
        width: 100% !important;
        overflow: visible !important;
    }
    .cms-studio-col-left,
    .cms-studio-col-right {
        height: auto !important;
        max-height: none !important;
        overflow-y: visible !important;
        overflow-x: hidden !important;
        padding-right: 0 !important;
        padding-bottom: 30px !important;
    }
    .cms-editor-card-header-row {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 10px !important;
    }
    .cms-editor-card-header-row > div {
        width: 100% !important;
        justify-content: space-between !important;
    }
    .cms-title-slug-card {
        padding: 14px 16px !important;
        border-radius: 16px !important;
    }
    .cms-raf-title-input {
        font-size: 1.2rem !important;
        padding: 6px 0 !important;
    }
    .cms-raf-slug-row {
        flex-wrap: wrap !important;
        gap: 6px !important;
        padding: 8px 10px !important;
    }
    .cms-slug-prefix {
        font-size: 11px !important;
        max-width: 100% !important;
        word-break: break-all !important;
    }
    .cms-raf-slug-input {
        font-size: 12px !important;
        min-width: 160px !important;
        flex: 1 1 auto !important;
    }
    .cms-toolbar-ribbon {
        flex-wrap: nowrap !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch !important;
        scrollbar-width: none !important;
        padding: 8px 4px !important;
        gap: 6px !important;
    }
    .cms-toolbar-ribbon::-webkit-scrollbar {
        display: none !important;
    }
    .cms-editor-card {
        padding: 14px 16px !important;
        border-radius: 16px !important;
    }
    #cms-visual-editor {
        font-size: 15px !important;
        line-height: 1.75 !important;
        min-height: 380px !important;
        padding: 14px 12px !important;
    }
    #cms-visual-editor img,
    #cms-visual-editor figure {
        max-width: 100% !important;
        height: auto !important;
        border-radius: 12px !important;
    }
    #cms-visual-editor table {
        display: block !important;
        max-width: 100% !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch !important;
    }
    .cms-kpi-card {
        padding: 12px 14px !important;
        border-radius: 16px !important;
    }
    .cms-filter-feed-bar {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 10px !important;
    }
}

[x-cloak],
[x-cloak] * {
    display: none !important;
}

[style*="display: none"],
[style*="display:none"] {
    display: none !important;
}

/* ══════════════════════════════════════════════════════════════
   STUDIO TWO-COLUMN INDEPENDENT SCROLLING (Image 2 & Image 3 UX)
   Left side (article content) and right side (settings & inspector)
   scroll independently on desktop so user never loses their editing place!
   ══════════════════════════════════════════════════════════════ */
@media (min-width: 1024px) {
    .cms-studio-screen:not([style*="display: none"]):not([style*="display:none"]) {
        display: flex;
        flex-direction: column;
        height: calc(100vh - 125px);
        height: calc(100dvh - 125px);
        max-height: calc(100vh - 125px);
        max-height: calc(100dvh - 125px);
        overflow: hidden;
        margin-top: -6px;
    }
    .cms-studio-screen[style*="display: none"],
    .cms-studio-screen[style*="display:none"] {
        display: none !important;
    }
    .cms-studio-screen .cms-top-header-row {
        flex-shrink: 0 !important;
        margin-bottom: 14px !important;
        padding-bottom: 12px !important;
    }
    .cms-studio-workspace {
        flex: 1 1 0% !important;
        min-height: 0 !important;
        height: 100% !important;
        max-height: 100% !important;
        overflow: hidden !important;
        margin-bottom: 0 !important;
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 1.5rem;
        align-items: stretch;
    }
    .cms-studio-col-left {
        height: 100% !important;
        max-height: 100% !important;
        overflow-y: scroll !important;
        overflow-x: hidden !important;
        padding-right: 14px;
        padding-bottom: 90px;
        overscroll-behavior: contain;
        scrollbar-width: thin;
        scrollbar-color: rgba(203, 213, 225, 0.9) transparent;
        -webkit-overflow-scrolling: touch;
    }
    .dark .cms-studio-col-left {
        scrollbar-color: rgba(71, 85, 105, 0.9) transparent;
    }
    .cms-studio-col-right {
        height: 100% !important;
        max-height: 100% !important;
        overflow-y: scroll !important;
        overflow-x: hidden !important;
        padding-right: 10px;
        padding-bottom: 90px;
        overscroll-behavior: contain;
        scrollbar-width: thin;
        scrollbar-color: rgba(203, 213, 225, 0.9) transparent;
        -webkit-overflow-scrolling: touch;
    }
    .dark .cms-studio-col-right {
        scrollbar-color: rgba(71, 85, 105, 0.9) transparent;
    }
    .cms-studio-col-left::-webkit-scrollbar,
    .cms-studio-col-right::-webkit-scrollbar {
        width: 8px;
    }
    .cms-studio-col-left::-webkit-scrollbar-track,
    .cms-studio-col-right::-webkit-scrollbar-track {
        background: rgba(148, 163, 184, 0.08);
        border-radius: 9999px;
    }
    .cms-studio-col-left::-webkit-scrollbar-thumb,
    .cms-studio-col-right::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 9999px;
    }
    .cms-studio-col-left::-webkit-scrollbar-thumb:hover,
    .cms-studio-col-right::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    .dark .cms-studio-col-left::-webkit-scrollbar-thumb,
    .dark .cms-studio-col-right::-webkit-scrollbar-thumb {
        background: #475569;
    }
    .dark .cms-studio-col-left::-webkit-scrollbar-thumb:hover,
    .dark .cms-studio-col-right::-webkit-scrollbar-thumb:hover {
        background: #64748b;
    }
}

/* Ensure rich paragraph text styling inside visual editor */
#cms-visual-editor {
    font-size: 15px;
    line-height: 1.8;
    min-height: 480px;
}
#cms-visual-editor:empty:before {
    content: attr(data-placeholder);
    color: #94a3b8;
    font-style: italic;
    cursor: text;
    pointer-events: none;
}
#cms-visual-editor p {
    color: var(--cms-text-primary);
    margin-bottom: 1.25rem;
    line-height: 1.8;
    font-size: 15px;
}
#cms-visual-editor h1 {
    color: var(--cms-text-primary);
    font-weight: 900;
    font-size: 1.85rem;
    margin-top: 2.25rem;
    margin-bottom: 1rem;
    line-height: 1.25;
    border-bottom: 2px solid #cbd5e1;
    padding-bottom: 0.6rem;
    clear: both;
}
#cms-visual-editor h2 {
    color: var(--cms-text-primary);
    font-weight: 800;
    font-size: 1.5rem;
    margin-top: 2rem;
    margin-bottom: 0.85rem;
    line-height: 1.3;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 0.5rem;
    clear: both;
}
#cms-visual-editor h3 {
    color: var(--cms-text-primary);
    font-weight: 700;
    font-size: 1.25rem;
    margin-top: 1.6rem;
    margin-bottom: 0.65rem;
    line-height: 1.35;
    clear: both;
}
#cms-visual-editor h4 {
    color: var(--cms-text-secondary);
    font-weight: 700;
    font-size: 1.1rem;
    margin-top: 1.25rem;
    margin-bottom: 0.5rem;
    line-height: 1.4;
    clear: both;
}
.dark #cms-visual-editor h1 {
    border-bottom-color: #334155;
}
.dark #cms-visual-editor h2 {
    border-bottom-color: #334155;
}
#cms-visual-editor figure.cms-rendered-figure {
    position: relative;
    margin: 1.75rem auto;
    text-align: center;
    max-width: 100%;
    transition: all 0.2s ease;
}
#cms-visual-editor figure.cms-rendered-figure[data-align="center"] {
    margin: 1.75rem auto;
    text-align: center;
    clear: both;
}
#cms-visual-editor figure.cms-rendered-figure[data-align="left"] {
    float: left;
    margin: 0.5rem 1.5rem 1rem 0;
    max-width: 50%;
    text-align: left;
}
#cms-visual-editor figure.cms-rendered-figure[data-align="right"] {
    float: right;
    margin: 0.5rem 0 1rem 1.5rem;
    max-width: 50%;
    text-align: right;
}
#cms-visual-editor figure.cms-rendered-figure[data-align="full"] {
    width: 100% !important;
    max-width: 100% !important;
    margin: 1.75rem 0;
    clear: both;
    text-align: center;
}
#cms-visual-editor figure.cms-rendered-figure[data-align="full"] img {
    width: 100%;
    max-height: 540px;
}
#cms-visual-editor figure.cms-rendered-figure img {
    max-height: 480px;
    border-radius: 14px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    display: inline-block;
    object-fit: cover;
    cursor: pointer;
    transition: outline 0.15s ease, box-shadow 0.15s ease;
}
#cms-visual-editor figure.cms-rendered-figure figcaption {
    display: inline-block;
    margin-top: 10px;
    padding: 7px 16px;
    font-size: 12px;
    color: #64748b;
    border: 1.5px dashed #cbd5e1;
    border-radius: 8px;
    background: rgba(248, 250, 252, 0.9);
    max-width: 90%;
    min-width: 280px;
    outline: none;
    cursor: text;
    transition: all 0.15s ease;
    font-style: italic;
    font-weight: 500;
}
#cms-visual-editor figure.cms-rendered-figure figcaption:empty:before {
    content: attr(data-placeholder);
    color: #94a3b8;
    font-style: italic;
}
#cms-visual-editor figure.cms-rendered-figure figcaption:hover,
#cms-visual-editor figure.cms-rendered-figure figcaption:focus {
    border-color: #3b82f6;
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}
.dark #cms-visual-editor figure.cms-rendered-figure figcaption {
    border-color: #334155;
    background: rgba(15, 23, 42, 0.8);
    color: #94a3b8;
}
.dark #cms-visual-editor figure.cms-rendered-figure figcaption:hover,
.dark #cms-visual-editor figure.cms-rendered-figure figcaption:focus {
    border-color: #60a5fa;
    background: #0f172a;
}
#cms-visual-editor table.cms-rendered-table {
    width: 100%;
    margin: 1.5rem 0;
    border-collapse: collapse;
    font-size: 13px;
}
#cms-visual-editor table.cms-rendered-table th,
#cms-visual-editor table.cms-rendered-table td {
    padding: 8px 12px;
    border: 1px solid var(--cms-border);
}
#cms-visual-editor table.cms-rendered-table th {
    background: var(--cms-bg-input);
    font-weight: 800;
}

/* Floating Image Interactive Overlay Styles (Image 1 UX/UI) */
.cms-image-floating-toolbar {
    position: absolute;
    pointer-events: none;
    z-index: 50;
    transition: opacity 0.15s ease;
}
.cms-image-selection-outline {
    position: absolute;
    inset: 0;
    border: 2px solid #ef4444;
    border-radius: 14px;
    pointer-events: none;
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.18);
}
.cms-image-badge-top-left {
    position: absolute;
    top: 12px;
    left: 12px;
    pointer-events: auto;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: rgba(15, 23, 42, 0.88);
    backdrop-filter: blur(8px);
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    padding: 4px 9px;
    border-radius: 6px;
    border: 1px solid rgba(255, 255, 255, 0.15);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35);
    user-select: none;
}
.cms-image-badge-bottom-right {
    position: absolute;
    bottom: 12px;
    right: 12px;
    pointer-events: auto;
    display: inline-flex;
    align-items: center;
    background: rgba(15, 23, 42, 0.88);
    backdrop-filter: blur(8px);
    color: #cbd5e1;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.05em;
    padding: 3px 9px;
    border-radius: 6px;
    border: 1px solid rgba(255, 255, 255, 0.15);
    text-transform: uppercase;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35);
    user-select: none;
}
.cms-image-top-bar {
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    pointer-events: auto;
    display: flex;
    align-items: center;
    gap: 4px;
    background: #0f172a;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    padding: 5px 8px;
    box-shadow: 0 12px 28px -4px rgba(0, 0, 0, 0.6), 0 4px 10px rgba(0, 0, 0, 0.4);
    white-space: nowrap;
    z-index: 55;
}
.cms-img-btn-drag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    padding: 4px 8px;
    border-radius: 6px;
    border: none;
    cursor: grab;
    transition: background 0.15s ease;
}
.cms-img-btn-drag:hover {
    background: rgba(255, 255, 255, 0.18);
}
.cms-img-tool-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    color: #94a3b8;
    background: transparent;
    border: none;
    cursor: pointer;
    transition: all 0.15s ease;
}
.cms-img-tool-btn:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.12);
}
.cms-img-tool-btn.cms-img-tool-active {
    background: #ef4444 !important;
    color: #ffffff !important;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.45);
}
.cms-img-divider {
    width: 1px;
    height: 16px;
    background: rgba(255, 255, 255, 0.18);
    margin: 0 4px;
}
.cms-img-pill-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: #cbd5e1;
    font-size: 11.5px;
    font-weight: 700;
    padding: 4px 9px;
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.07);
    border: 1px solid rgba(255, 255, 255, 0.09);
    cursor: pointer;
    transition: all 0.15s ease;
}
.cms-img-pill-action-btn:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.16);
    border-color: rgba(255, 255, 255, 0.22);
}
.cms-image-popover {
    position: absolute;
    bottom: calc(100% + 56px);
    left: 50%;
    transform: translateX(-50%);
    width: 320px;
    background: #0f172a;
    border: 1px solid #334155;
    border-radius: 12px;
    padding: 14px;
    box-shadow: 0 20px 35px -5px rgba(0, 0, 0, 0.6);
    pointer-events: auto;
    z-index: 60;
}
.cms-popover-input {
    width: 100%;
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: 8px;
    padding: 7px 10px;
    font-size: 12.5px;
    color: #ffffff;
    outline: none;
    transition: border-color 0.15s ease;
}
.cms-popover-input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
}
.cms-popover-btn-apply {
    background: #ef4444;
    color: #ffffff;
    font-size: 11.5px;
    font-weight: 700;
    padding: 5px 12px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    transition: background 0.15s ease;
}
.cms-popover-btn-apply:hover {
    background: #dc2626;
}
.cms-popover-btn-cancel {
    background: transparent;
    color: #94a3b8;
    font-size: 11.5px;
    font-weight: 600;
    padding: 5px 10px;
    border-radius: 6px;
    border: 1px solid #334155;
    cursor: pointer;
    transition: all 0.15s ease;
}
.cms-popover-btn-cancel:hover {
    color: #ffffff;
    background: #1e293b;
}
</style>

<script>
    window.DGT_WEBSITES = @json($websites);
    window.DGT_WEBSITE_PROFILES = @json($websiteProfiles);
    window.DGT_CURRENT_PROFILE = @json($currentProfile);
    window.DGT_SELECTED_SITE_ID = @json($selectedWebsiteId);
</script>

<div class="space-y-3.5 sm:space-y-6" :class="currentScreen === 'editor' ? 'pb-2' : 'pb-4 sm:pb-8'" x-data="blogPostCmsApp()">

    <!-- ══════════════════════════════════════════════════════════════
         SCREEN 1: FRONT PAGE (LATEST 10 BLOG POSTS HUB)
         Default view when entering "Blog post". Image 2 is hidden until user clicks "New Blog" or "Edit".
         ══════════════════════════════════════════════════════════════ -->
    <div x-show="currentScreen === 'list'" x-cloak class="space-y-3.5 sm:space-y-6" :style="currentScreen !== 'list' ? 'display: none !important;' : ''">

        <!-- ── Top Hero Header with [New Blog] Action ── -->
        <div class="cms-hub-hero">
            <div class="flex items-center gap-3 sm:gap-4">
                <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 text-white flex items-center justify-center font-black text-lg sm:text-xl shadow-md sm:shadow-lg shadow-blue-500/25 shrink-0">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-lg sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">Blog Posts</h1>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] sm:text-[10.5px] font-black uppercase tracking-wider bg-blue-100 text-blue-700 dark:bg-blue-950/70 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-600 animate-ping"></span>
                            Multi-Website CMS
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Showing the latest 10 blog posts across integrated websites. High-speed multi-user architecture with zero lag.
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <!-- Primary NEW BLOG Button (Requested by User) -->
                <button type="button" @click="startNewPost()" class="cms-btn-new-blog" id="btn-new-blog" title="Launch New Blog Post Studio">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                    <span>New Blog</span>
                </button>
            </div>
        </div>

        <!-- ── Executive Summary KPI Cards (5 Cards - Click to Filter) ── -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5">
            <!-- Total Posts KPI Card -->
            <div @click="setLatestStatusFilter('all')"
                 class="cms-kpi-card flex items-center gap-3 cursor-pointer hover:shadow-md transition-all active:scale-[0.98]"
                 :class="{ 'ring-2 ring-blue-500 bg-blue-50/40 dark:bg-blue-950/30': latestStatusFilter === 'all' }"
                 title="Click to view All articles">
                <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 flex items-center justify-center shrink-0">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                </div>
                <div class="min-w-0">
                    <div class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Total Posts</div>
                    <div class="text-xl font-black text-slate-900 dark:text-white" x-text="stats.total || {{ $stats['total'] ?? 0 }}">
                        {{ $stats['total'] ?? 0 }}
                    </div>
                </div>
            </div>

            <!-- Published Live KPI Card -->
            <div @click="setLatestStatusFilter('published')"
                 class="cms-kpi-card flex items-center gap-3 cursor-pointer hover:shadow-md transition-all active:scale-[0.98]"
                 :class="{ 'ring-2 ring-emerald-500 bg-emerald-50/40 dark:bg-emerald-950/30': latestStatusFilter === 'published' }"
                 title="Click to view Published articles">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                </div>
                <div class="min-w-0">
                    <div class="text-[11px] font-extrabold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Published Live</div>
                    <div class="text-xl font-black text-slate-900 dark:text-white" x-text="stats.published || {{ $stats['published'] ?? 0 }}">
                        {{ $stats['published'] ?? 0 }}
                    </div>
                </div>
            </div>

            <!-- Drafts KPI Card (Click to View Drafts) -->
            <div @click="setLatestStatusFilter('draft')"
                 class="cms-kpi-card flex items-center gap-3 cursor-pointer hover:shadow-md transition-all active:scale-[0.98]"
                 :class="{ 'ring-2 ring-amber-500 bg-amber-50/40 dark:bg-amber-950/30': latestStatusFilter === 'draft' }"
                 title="Click to view Draft articles immediately">
                <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                </div>
                <div class="min-w-0">
                    <div class="text-[11px] font-extrabold uppercase tracking-wider text-amber-600 dark:text-amber-400">Drafts</div>
                    <div class="text-xl font-black text-slate-900 dark:text-white" x-text="stats.draft || {{ $stats['draft'] ?? 0 }}">
                        {{ $stats['draft'] ?? 0 }}
                    </div>
                </div>
            </div>

            <!-- Scheduled KPI Card -->
            <div @click="setLatestStatusFilter('scheduled')"
                 class="cms-kpi-card flex items-center gap-3 cursor-pointer hover:shadow-md transition-all active:scale-[0.98]"
                 :class="{ 'ring-2 ring-indigo-500 bg-indigo-50/40 dark:bg-indigo-950/30': latestStatusFilter === 'scheduled' }"
                 title="Click to view Scheduled articles">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
                </div>
                <div class="min-w-0">
                    <div class="text-[11px] font-extrabold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Scheduled</div>
                    <div class="text-xl font-black text-slate-900 dark:text-white" x-text="stats.scheduled || {{ $stats['scheduled'] ?? 0 }}">
                        {{ $stats['scheduled'] ?? 0 }}
                    </div>
                </div>
            </div>

            <!-- Fleet Websites KPI Card -->
            <div class="cms-kpi-card flex items-center gap-3 col-span-2 sm:col-span-1">
                <div class="w-10 h-10 rounded-xl bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1 4-10z"/></svg>
                </div>
                <div class="min-w-0">
                    <div class="text-[11px] font-extrabold uppercase tracking-wider text-sky-600 dark:text-sky-400">Fleet Websites</div>
                    <div class="text-xl font-black text-slate-900 dark:text-white" x-text="stats.sites_count || {{ $stats['sites_count'] ?? count($websites) }}">
                        {{ $stats['sites_count'] ?? count($websites) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Search, Filter & Tabs Toolbar ── -->
        <div class="flex flex-wrap items-center justify-between gap-2.5 sm:gap-3 p-2.5 sm:p-3.5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs">
            <div class="flex flex-wrap items-center gap-2 sm:gap-2.5 flex-1 w-full min-w-0 sm:min-w-[280px]">
                <!-- Search Input -->
                <div class="relative flex-1 w-full min-w-0 sm:min-w-[220px]">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="text"
                           x-model="latestSearchQuery"
                           placeholder="Filter posts by title, slug, author, or keyword..."
                           class="w-full pl-9 pr-8 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500/50" />
                    <button x-show="latestSearchQuery" @click="latestSearchQuery = ''" type="button" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs cursor-pointer">✕</button>
                </div>

                <!-- Custom Searchable Website Filter Dropdown with Icon, Handler Profile & Class -->
                <div class="relative w-full sm:w-auto" @click.away="siteFilterDropdownOpen = false">
                    <!-- Trigger Button -->
                    <button type="button"
                            @click="siteFilterDropdownOpen = !siteFilterDropdownOpen; if (siteFilterDropdownOpen) { $nextTick(() => $refs.siteFilterSearchInput && $refs.siteFilterSearchInput.focus()); }"
                            class="inline-flex items-center justify-between gap-2 px-3 py-2 text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 hover:border-slate-300 dark:hover:border-slate-600 transition-all cursor-pointer shadow-xs w-full sm:w-auto min-w-0 sm:min-w-[210px] max-w-full sm:max-w-[360px]"
                            title="Filter posts by website">
                        <!-- All Integrated Websites Selected -->
                        <div x-show="latestWebsiteFilter === 'all'" class="flex items-center gap-2 truncate">
                            <span class="w-5 h-5 rounded-md bg-blue-100 dark:bg-blue-900/60 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20M2 12h20"/></svg>
                            </span>
                            <span class="truncate font-bold">All Integrated Websites</span>
                            <span class="px-1.5 py-0.5 rounded-md text-[10px] font-extrabold bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-300" x-text="websites.length"></span>
                        </div>

                        <!-- Specific Website Selected -->
                        <div x-show="latestWebsiteFilter !== 'all'" class="flex items-center gap-1.5 truncate">
                            <!-- Website Icon -->
                            <img :src="selectedWebsiteFilterObj()?.logo || selectedWebsiteFilterObj()?.favicon || 'https://www.google.com/s2/favicons?domain=' + selectedWebsiteFilterObj()?.clean_domain + '&sz=64'"
                                 x-on:error="$el.src = 'https://www.google.com/s2/favicons?domain=' + selectedWebsiteFilterObj()?.clean_domain + '&sz=64'"
                                 class="w-4 h-4 rounded object-contain shrink-0 border border-slate-200 dark:border-slate-700 bg-white p-0.5 shadow-2xs" />
                            
                            <!-- Website Name -->
                            <span class="truncate font-bold text-slate-900 dark:text-white" x-text="selectedWebsiteFilterObj()?.name"></span>

                            <!-- Class Badge -->
                            <span x-show="selectedWebsiteFilterObj()?.category"
                                  class="px-1.5 py-0.5 rounded text-[10px] font-extrabold bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300 border border-blue-200/50 dark:border-blue-800/50 shrink-0"
                                  x-text="selectedWebsiteFilterObj()?.category"></span>

                            <!-- Handler Profile (Name & Avatar) -->
                            <span x-show="selectedWebsiteFilterObj()?.handler_name"
                                  class="hidden sm:inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-200/70 dark:bg-slate-800 text-slate-700 dark:text-slate-300 shrink-0">
                                <img :src="selectedWebsiteFilterObj()?.handler_avatar" class="w-3 h-3 rounded-full object-cover shrink-0" x-on:error="$el.style.display='none'">
                                <span class="max-w-[70px] truncate" x-text="selectedWebsiteFilterObj()?.handler_name"></span>
                            </span>
                        </div>

                        <!-- Controls -->
                        <div class="flex items-center gap-1 shrink-0 ml-1">
                            <span x-show="latestWebsiteFilter !== 'all'"
                                  @click.stop="selectWebsiteFilter('all')"
                                  class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-0.5 rounded hover:bg-slate-200 dark:hover:bg-slate-800 cursor-pointer text-xs"
                                  title="Clear filter">✕</span>
                            <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200" :class="siteFilterDropdownOpen ? 'rotate-180 text-blue-600 dark:text-blue-400' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m19 9-7 7-7-7"/></svg>
                        </div>
                    </button>

                    <!-- Searchable Dropdown Popup Menu -->
                    <div x-show="siteFilterDropdownOpen"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
                         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                         x-transition:leave-end="opacity-0 scale-95 -translate-y-2"
                         class="absolute left-0 mt-1.5 w-[calc(100vw-2.5rem)] sm:w-96 max-w-sm rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-2xl p-2.5 z-50 backdrop-blur-xl"
                         style="display: none;">
                        
                        <!-- Search Box inside Dropdown -->
                        <div class="relative mb-2">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                            <input x-ref="siteFilterSearchInput"
                                   type="text"
                                   x-model="siteFilterSearchQuery"
                                   placeholder="Search website, class, or handler..."
                                   class="w-full pl-8 pr-7 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/50" />
                            <button x-show="siteFilterSearchQuery"
                                    @click="siteFilterSearchQuery = ''"
                                    type="button"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xs cursor-pointer">✕</button>
                        </div>

                        <!-- All Integrated Websites Option -->
                        <button type="button"
                                @click="selectWebsiteFilter('all')"
                                class="w-full flex items-center justify-between p-2 rounded-xl text-left transition-colors cursor-pointer group"
                                :class="latestWebsiteFilter === 'all' ? 'bg-blue-50 dark:bg-blue-950/50 text-blue-700 dark:text-blue-300' : 'hover:bg-slate-100 dark:hover:bg-slate-800/70 text-slate-700 dark:text-slate-200'">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="w-7 h-7 rounded-lg bg-blue-100 dark:bg-blue-900/60 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20M2 12h20"/></svg>
                                </span>
                                <div class="min-w-0">
                                    <div class="text-xs font-bold truncate">All Integrated Websites</div>
                                    <div class="text-[10px] text-slate-400">View posts across entire fleet (<span x-text="websites.length"></span> sites)</div>
                                </div>
                            </div>
                            <svg x-show="latestWebsiteFilter === 'all'" class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </button>

                        <div class="my-1.5 border-t border-slate-100 dark:border-slate-800"></div>

                        <!-- Section Header -->
                        <div class="flex items-center justify-between px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                            <span>Websites (<span x-text="filteredWebsitesList().length"></span>)</span>
                            <span>Class & Handler</span>
                        </div>

                        <!-- Scrollable Websites List -->
                        <div class="max-h-64 overflow-y-auto space-y-1 pr-1 custom-scrollbar">
                            <template x-for="site in filteredWebsitesList()" :key="site.id">
                                <button type="button"
                                        @click="selectWebsiteFilter(site.id)"
                                        class="w-full flex items-center justify-between gap-2 p-2 rounded-xl text-left transition-all group cursor-pointer"
                                        :class="latestWebsiteFilter == site.id ? 'bg-blue-50 dark:bg-blue-950/50 border border-blue-200/60 dark:border-blue-800/60' : 'hover:bg-slate-100 dark:hover:bg-slate-800/70 border border-transparent'">
                                    
                                    <!-- Left: Website Icon & Name & Handler -->
                                    <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                        <!-- Website Icon / Favicon / Logo -->
                                        <img :src="site.logo || site.favicon || 'https://www.google.com/s2/favicons?domain=' + site.clean_domain + '&sz=64'"
                                             x-on:error="$el.src = 'https://www.google.com/s2/favicons?domain=' + site.clean_domain + '&sz=64'"
                                             class="w-7 h-7 rounded-lg object-contain border border-slate-200 dark:border-slate-700 bg-white p-0.5 shrink-0 shadow-2xs" />
                                        
                                        <div class="min-w-0 flex-1">
                                            <!-- Website Name -->
                                            <div class="text-xs font-bold text-slate-900 dark:text-white truncate group-hover:text-blue-600 dark:group-hover:text-blue-400" x-text="site.name"></div>
                                            
                                            <!-- Handler Profile Info (pf name & avatar) -->
                                            <div class="flex items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                                <img :src="site.handler_avatar" class="w-3.5 h-3.5 rounded-full object-cover shrink-0" x-on:error="$el.style.display='none'">
                                                <span class="truncate" x-text="site.handler_name || 'Unassigned'"></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Right: Class Badge & Active Checkmark -->
                                    <div class="flex items-center gap-2 shrink-0">
                                        <!-- Class Badge (e.g. 1st Class, 6th Class) -->
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-extrabold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-200/60 dark:border-slate-700/60 group-hover:bg-blue-100 group-hover:text-blue-800 dark:group-hover:bg-blue-900/60 dark:group-hover:text-blue-300 transition-colors"
                                              x-text="site.category || 'General'"></span>
                                        
                                        <svg x-show="latestWebsiteFilter == site.id" class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                </button>
                            </template>

                            <!-- Empty Search Result State -->
                            <div x-show="filteredWebsitesList().length === 0" class="py-6 px-3 text-center text-xs text-slate-400">
                                No websites match "<span class="font-bold text-slate-600 dark:text-slate-300" x-text="siteFilterSearchQuery"></span>"
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 w-full sm:w-auto">
                <!-- Status Filter Tabs (Pub / Draft / Scheduled / All) -->
                <div class="inline-flex items-center p-1 rounded-xl bg-slate-100 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-[11px] sm:text-xs font-bold w-full sm:w-auto justify-between sm:justify-start">
                    <button type="button" @click="setLatestStatusFilter('all')"
                            class="px-2.5 sm:px-3 py-1 flex-1 sm:flex-none text-center rounded-lg transition-all"
                            :class="latestStatusFilter === 'all' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'">
                        All
                    </button>
                    <button type="button" @click="setLatestStatusFilter('published')"
                            class="px-3 py-1 rounded-lg transition-all"
                            :class="latestStatusFilter === 'published' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-500 hover:text-emerald-600'">
                        Published
                    </button>
                    <button type="button" @click="setLatestStatusFilter('draft')"
                            class="px-3 py-1 rounded-lg transition-all"
                            :class="latestStatusFilter === 'draft' ? 'bg-amber-500 text-white shadow-xs' : 'text-slate-500 hover:text-amber-600'">
                        Drafts
                    </button>
                    <button type="button" @click="setLatestStatusFilter('scheduled')"
                            class="px-3 py-1 rounded-lg transition-all"
                            :class="latestStatusFilter === 'scheduled' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-500 hover:text-indigo-600'">
                        Scheduled
                    </button>
                </div>

                <!-- Per-Page Limit Selector (10, 20, 30, 50, All) -->
                <div class="inline-flex items-center p-1 rounded-xl bg-slate-100 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs font-bold">
                    <span class="text-[10px] text-slate-400 uppercase tracking-wider px-2 hidden xl:inline">Show:</span>
                    <template x-for="lim in ['10', '20', '30', '50', 'all']" :key="lim">
                        <button type="button"
                                @click="changeFeedLimit(lim)"
                                class="px-2.5 py-1 rounded-lg transition-all"
                                :class="feedLimit === lim ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-500 hover:text-blue-600 dark:hover:text-blue-400'"
                                x-text="lim.toUpperCase()">
                        </button>
                    </template>
                </div>

                <!-- Refresh Feed Button -->
                <button type="button" @click="fetchLatestPosts()" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors cursor-pointer shadow-xs" title="Refresh Blog Posts Feed">
                    <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': isRefreshingLatest }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span class="hidden md:inline">Refresh</span>
                </button>
            </div>
        </div>

        <!-- ── Latest Blog Posts Fleet Feed Table ── -->
        <div class="cms-table-container">
            <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800/80 flex flex-wrap items-center justify-between gap-3 bg-slate-50/50 dark:bg-slate-900/50">
                <div class="flex items-center gap-2.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-wider"
                        x-text="(feedLimit === 'all' ? 'ALL' : 'LATEST ' + feedLimit) + ' BLOG POSTS ACROSS INTEGRATED FLEET'">
                        Latest Blog Posts Across Integrated Fleet
                    </h3>
                </div>
                <div class="text-[11px] text-slate-400 font-medium hidden sm:block">
                    Real-time multi-user concurrency safe feed
                </div>
            </div>

            <!-- Desktop Table View -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 uppercase font-black tracking-wider text-[10px] border-b border-slate-100 dark:border-slate-800">
                        <tr>
                            <th class="py-3 px-4">Article</th>
                            <th class="py-3 px-4">Target Website &amp; Class</th>
                            <th class="py-3 px-4">Author</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Published / Created</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        <template x-for="post in filteredLatestPosts()" :key="post.id">
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                <!-- Article Title & Thumb -->
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-3">
                                        <template x-if="post.featured_image_url">
                                            <img :src="post.featured_image_url" alt="" class="w-11 h-11 rounded-xl object-cover border border-slate-200 dark:border-slate-700 shrink-0 shadow-xs">
                                        </template>
                                        <template x-if="!post.featured_image_url">
                                            <div class="w-11 h-11 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-400 shrink-0">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                                            </div>
                                        </template>
                                        <div class="min-w-0 max-w-[320px]">
                                            <div class="font-black text-slate-900 dark:text-white truncate hover:text-blue-600 transition-colors cursor-pointer"
                                                 @click="editPost(post.id)"
                                                 x-text="post.title"></div>
                                            <div class="flex items-center gap-2 mt-0.5">
                                                <span class="text-[10px] text-slate-400 font-mono truncate max-w-[180px]" x-text="'/blog/' + post.slug"></span>
                                                <template x-if="post.category">
                                                    <span class="text-[9.5px] px-1.5 py-0.2 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-semibold truncate max-w-[100px]" x-text="post.category"></span>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Target Website & Class -->
                                <td class="py-3 px-4">
                                    <div class="flex flex-col gap-1 items-start">
                                        <span class="font-extrabold text-slate-900 dark:text-slate-100 truncate" x-text="post.website_name"></span>
                                        <div class="flex items-center gap-1.5">
                                            <!-- Class Badge -->
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[9.5px] font-black uppercase border tracking-wider"
                                                  :class="getClassBadgeColor(post.website_category)"
                                                  x-text="post.website_category || 'General'"></span>
                                            <span class="text-[10px] text-slate-400 font-mono" x-text="post.website_url ? (new URL(post.website_url).hostname.replace(/^www\./, '')) : ''"></span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Website Author (Only 1 clean author per website, no handler names) -->
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                        </svg>
                                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200 block truncate" x-text="post.author_name || post.website_name || 'Website Team'"></span>
                                    </div>
                                </td>

                                <!-- Status (Strictly 3 clean normalized statuses: PUBLISHED, DRAFT, SCHEDULED) -->
                                <td class="py-3 px-4">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider"
                                          :class="statusBadgeClass(post.status)">
                                        <span class="w-1.5 h-1.5 rounded-full" :class="statusDotClass(post.status)"></span>
                                        <span x-text="statusLabel(post.status)"></span>
                                    </span>
                                </td>

                                <!-- Published / Created Date -->
                                <td class="py-3 px-4 text-slate-500 dark:text-slate-400 text-[11px]" x-text="post.published_at || post.created_at"></td>

                                <!-- Actions -->
                                <td class="py-3 px-4 text-right">
                                    <div class="inline-flex items-center justify-end gap-1.5">
                                        <button type="button" @click="editPost(post.id)"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-all shadow-xs cursor-pointer">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                            <span>Edit</span>
                                        </button>
                                        <a :href="post.canonical_url" target="_blank"
                                           class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 dark:hover:bg-indigo-900 border border-indigo-200 dark:border-indigo-800 transition-all shadow-xs">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                            <span>Visit</span>
                                        </a>
                                        <button type="button" @click="deletePost(post.id, post.title)"
                                                class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 transition-colors cursor-pointer"
                                                title="Delete article">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Native Mobile Feed Cards (100% Native App Feel on iOS & Android) -->
            <div class="md:hidden divide-y divide-slate-100 dark:divide-slate-800/60">
                <template x-for="post in filteredLatestPosts()" :key="post.id">
                    <div class="p-4 space-y-3 hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition-colors">
                        <div class="flex items-start gap-3">
                            <template x-if="post.featured_image_url">
                                <img :src="post.featured_image_url" alt="" class="w-16 h-16 rounded-2xl object-cover border border-slate-200 dark:border-slate-700 shrink-0 shadow-xs">
                            </template>
                            <template x-if="!post.featured_image_url">
                                <div class="w-16 h-16 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-400 shrink-0">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                                </div>
                            </template>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-1.5 mb-1">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[9.5px] font-black uppercase tracking-wider border"
                                          :class="getClassBadgeColor(post.website_category)"
                                          x-text="post.website_category || 'General'"></span>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9.5px] font-black uppercase tracking-wider"
                                          :class="statusBadgeClass(post.status)">
                                        <span class="w-1.5 h-1.5 rounded-full" :class="statusDotClass(post.status)"></span>
                                        <span x-text="statusLabel(post.status)"></span>
                                    </span>
                                </div>
                                <h4 class="font-black text-sm text-slate-900 dark:text-white leading-snug line-clamp-2 cursor-pointer active:text-blue-600"
                                    @click="editPost(post.id)"
                                    x-text="post.title"></h4>
                                <div class="flex items-center gap-1.5 mt-1 text-[11px] text-slate-400">
                                    <span class="font-bold text-slate-700 dark:text-slate-300 truncate" x-text="post.website_name"></span>
                                    <span>•</span>
                                    <span x-text="post.published_at || post.created_at"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Mobile Action Buttons & Author Bar -->
                        <div class="flex items-center justify-between pt-1 gap-2">
                            <div class="flex items-center gap-1 min-w-0">
                                <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                                <span class="text-[11px] text-slate-600 dark:text-slate-300 font-semibold truncate" x-text="post.author_name || post.website_name || 'Website Team'"></span>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <button type="button" @click="editPost(post.id)"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-black rounded-xl bg-blue-600 hover:bg-blue-700 text-white active:scale-95 transition-all shadow-xs cursor-pointer">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                    <span>Edit</span>
                                </button>
                                <a :href="post.canonical_url" target="_blank"
                                   class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800 active:scale-95 transition-all shadow-xs">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                    <span>Visit</span>
                                </a>
                                <button type="button" @click="deletePost(post.id, post.title)"
                                        class="w-7 h-7 flex items-center justify-center rounded-xl text-slate-400 hover:text-red-600 active:scale-95 transition-all cursor-pointer"
                                        title="Delete article">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Empty State -->
            <div x-show="filteredLatestPosts().length === 0" class="py-12 px-4 text-center">
                <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">No blog posts found</h4>
                <p class="text-xs text-slate-400 max-w-sm mx-auto mt-1 mb-4">No articles match your search or filter criteria. Clear filters or create a new article.</p>
                <button type="button" @click="startNewPost()" class="cms-btn-new-blog inline-flex">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                    <span>Write New Blog</span>
                </button>
            </div>
        </div>

    </div>

    <!-- ══════════════════════════════════════════════════════════════
         SCREEN 2: CMS STUDIO EDITOR (IMAGE 2 UX/UI)
         Opens when user clicks "New Blog" or "Edit" on any post.
         Has [← All Blogs] back button to return to Screen 1.
         ══════════════════════════════════════════════════════════════ -->
    <div x-show="currentScreen === 'editor'" x-cloak class="cms-studio-screen" :style="currentScreen !== 'editor' ? 'display: none !important;' : ''">

        <!-- ── Top Header Navigation Bar (Matches Image 1 UX) ── -->
        <div class="cms-top-header-row">
            <div class="cms-header-left">
                <button type="button" @click="backToListScreen()" class="cms-btn-header-back" title="Back to All Blogs">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                </button>
                <div class="cms-header-title-box">
                    <h1>
                        <span x-text="editingPostId ? 'Edit Article' : 'New Article'">New Article</span>
                        <span class="cms-header-status-badge uppercase tracking-wider"
                              :class="normalizeStatus(postStatus) === 'published' ? 'status-published' : (normalizeStatus(postStatus) === 'scheduled' ? 'status-scheduled' : 'status-draft')"
                              x-text="statusLabel(postStatus)">
                            PUBLISHED
                        </span>
                    </h1>
                    <p class="flex items-center gap-1.5 flex-wrap">
                        <button type="button" @click="backToListScreen()" class="text-blue-600 dark:text-blue-400 hover:underline font-bold">← All Blogs</button>
                        <span class="text-slate-300 dark:text-slate-700">•</span>
                        <span x-text="editingPostId ? ('ID #' + editingPostId + ' • ') : 'ID #NEW • '"></span>
                        <span x-text="'Targeting: ' + (selectedWebsite ? (selectedWebsite.clean_domain || selectedWebsite.name) : 'Choose Website')"></span>
                    </p>
                </div>
            </div>

            <div class="cms-header-actions">
                <!-- Mode / Preview Button (Opens Authentic Reader View in New Tab) -->
                <button type="button" @click="openPreviewInNewTab()"
                        class="cms-btn-header-draft group"
                        title="Open authentic live blog preview in a new tab">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    <span>Preview</span>
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="opacity-70 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-transform"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                </button>

                <!-- Save as Draft Button -->
                <button type="button" @click="saveBlogPost('draft')" :disabled="isSaving"
                        class="cms-btn-header-draft" title="Save changes as private draft">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    <span>Save as Draft</span>
                </button>

                <!-- Publish Main Action Button (Crimson Red) -->
                <button type="button" @click="saveBlogPost('published')" :disabled="isSaving"
                        class="cms-btn-header-publish" title="Publish live">
                    <svg x-show="!isSaving" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m5 12 7-7 7 7"/><path d="M12 19V5"/></svg>
                    <svg x-show="isSaving" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span x-text="isSaving ? 'Publishing...' : 'Publish Article'">Publish Article</span>
                </button>
            </div>
        </div>

        <!-- ── Mobile Screen Segmented Tab Switcher (Phones & Tablets) ── -->
        <div class="lg:hidden flex items-center p-1 rounded-2xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/80 mb-2.5 sm:mb-4 shadow-xs">
            <button type="button" @click="mobileStudioTab = 'content'"
                    class="flex-1 py-2.5 px-3 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-2 cursor-pointer"
                    :class="mobileStudioTab === 'content' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                <span>Article Content</span>
            </button>
            <button type="button" @click="mobileStudioTab = 'settings'"
                    class="flex-1 py-2.5 px-3 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-2 cursor-pointer"
                    :class="mobileStudioTab === 'settings' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                <span>Settings &amp; SEO</span>
                <span class="inline-block w-2 h-2 rounded-full" :class="normalizeStatus(postStatus) === 'published' ? 'bg-emerald-500' : (normalizeStatus(postStatus) === 'scheduled' ? 'bg-indigo-500' : 'bg-amber-500')"></span>
            </button>
        </div>

        <!-- ── Main Grid Layout: Left 68% (Canvas) / Right 32% (Inspector) ── -->
        <div class="cms-studio-workspace grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <!-- ══════════════════════════════════════════════════════════════
                 LEFT COLUMN (SLIDE): Title, Slug, Rich Editor Canvas & Preview (8 Cols)
                 Independently scrollable on desktop!
                 ══════════════════════════════════════════════════════════════ -->
            <div class="cms-studio-col-left space-y-5"
                 :class="{ 'hidden lg:block': mobileStudioTab !== 'content', 'lg:col-span-12 max-w-4xl mx-auto': editorMode === 'preview', 'lg:col-span-8': editorMode !== 'preview' }">

                <!-- Card 1: Article Title & Slug (Image 1 Style) -->
                <div class="cms-editor-card cms-title-slug-card" x-show="editorMode !== 'preview'">
                    <label class="cms-editor-eyebrow">ARTICLE TITLE</label>
                    <input type="text"
                           class="cms-raf-title-input"
                           placeholder="Give your article a descriptive, captivating title..."
                           x-model="postTitle"
                           @input="onTitleInput()"
                           @paste="handleTitlePaste($event)"
                           required />

                    <!-- Slug Row -->
                    <div class="cms-raf-slug-row">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--cms-text-muted); flex-shrink: 0;">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="2" y1="12" x2="22" y2="12"/>
                            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                        </svg>
                        <span class="cms-slug-prefix" x-text="currentWebsiteProfile?.slug_prefix || (selectedWebsite?.url ? (selectedWebsite.url.replace(/\/$/, '') + '/blog/') : 'happymachinery.com/blog/')">happymachinery.com/blog/</span>
                        <input type="text"
                               class="cms-raf-slug-input"
                               placeholder="custom-article-slug"
                               x-model="postSlug"
                               @input="slugManuallyEdited = true" />
                    </div>
                </div>

                <!-- Card 2: Article Content (WYSIWYG & Media - Image 1) -->
                <div class="cms-editor-card cms-article-content-card">
                    <div class="cms-editor-card-header-row">
                        <div class="flex items-center gap-2">
                            <label class="cms-editor-eyebrow" style="margin: 0;" x-text="editorMode === 'preview' ? 'PUBLIC ARTICLE PREVIEW' : 'ARTICLE CONTENT'"></label>
                            <span class="cms-wysiwyg-badge" x-text="editorMode === 'visual' ? 'Rich WYSIWYG & Media' : (editorMode === 'code' ? 'Code (Markdown)' : 'Live Reader Preview')">
                                Rich WYSIWYG & Media
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <!-- Smart Import Blog Button -->
                            <button type="button" @click="smartPasteModalOpen = true; smartPasteBuffer = ''"
                                    class="cms-btn-smart-import"
                                    title="Auto-detect blog: extracts title, cover image, YouTube, and formats tables">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/>
                                </svg>
                                <span>Smart Import Blog</span>
                            </button>

                            <!-- Mode Switcher Tabs -->
                            <div class="cms-raf-mode-switcher">
                                <button type="button" class="cms-raf-mode-btn"
                                        :class="{ 'active': editorMode === 'visual' }"
                                        @click="setEditorMode('visual')">
                                    Visual (WYSIWYG)
                                </button>
                                <button type="button" class="cms-raf-mode-btn"
                                        :class="{ 'active': editorMode === 'code' }"
                                        @click="setEditorMode('code')">
                                    Code (Markdown)
                                </button>
                                <button type="button" class="cms-raf-mode-btn"
                                        :class="{ 'active': editorMode === 'preview' }"
                                        @click="setEditorMode('preview')">
                                    Preview
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Rich Toolbar Ribbon -->
                    <div class="cms-toolbar-ribbon" x-show="editorMode !== 'preview'">
                        <button type="button" @click="insertFormat('bold')" class="cms-tool-btn" title="Bold (Ctrl+B)">B</button>
                        <button type="button" @click="insertFormat('italic')" class="cms-tool-btn" style="font-style: italic;" title="Italic (Ctrl+I)">I</button>
                        <button type="button" @click="insertFormat('underline')" class="cms-tool-btn" style="text-decoration: underline;" title="Underline">U</button>
                        <button type="button" @click="insertFormat('strikethrough')" class="cms-tool-btn" style="text-decoration: line-through;" title="Strikethrough">S</button>
                        <button type="button" @click="insertFormat('code')" class="cms-tool-btn font-mono" title="Inline Code">&lt;&gt;</button>

                        <span class="cms-tool-divider"></span>

                        <button type="button" @click="insertHeading(1)" class="cms-tool-btn" title="Heading 1">H1</button>
                        <button type="button" @click="insertHeading(2)" class="cms-tool-btn" title="Heading 2">H2</button>
                        <button type="button" @click="insertHeading(3)" class="cms-tool-btn" title="Heading 3">H3</button>

                        <span class="cms-tool-divider"></span>

                        <button type="button" @click="insertFormat('bullet')" class="cms-tool-btn" title="Bullet List">• List</button>
                        <button type="button" @click="insertFormat('number')" class="cms-tool-btn" title="Numbered List">1. List</button>
                        <button type="button" @click="insertFormat('quote')" class="cms-tool-btn" title="Blockquote">” Quote</button>
                        <button type="button" @click="insertFormat('divider')" class="cms-tool-btn" title="Horizontal Divider">—</button>

                        <span class="cms-tool-divider"></span>

                        <button type="button" @click="promptInsertImage()" class="cms-tool-pill-btn" title="Insert Image Link">
                            <span>🖼️ Image</span>
                        </button>
                        <button type="button" @click="promptInsertYoutube()" class="cms-tool-pill-btn" title="Insert YouTube Video Embed">
                            <span>▶ YouTube</span>
                        </button>
                        <button type="button" @click="insertSampleTable()" class="cms-tool-pill-btn" title="Insert Specification Table">
                            <span>⊞ Table</span>
                        </button>

                        <div class="ml-auto flex items-center gap-2 text-xs text-slate-400 font-medium">
                            <span x-text="wordCountText()"></span>
                        </div>
                    </div>

                    <!-- 1. Visual WYSIWYG Mode Canvas -->
                    <div x-show="editorMode === 'visual'" class="pt-4 space-y-4">
                        <div class="relative" id="cms-visual-editor-wrapper">

                            <!-- Floating Image Toolbar & Selection Overlay (Image 1 UX/UI) -->
                            <div class="cms-image-floating-toolbar"
                                 x-show="imageToolbarOpen"
                                 x-cloak
                                 :style="`top: ${imageOverlayRect.top}px; left: ${imageOverlayRect.left}px; width: ${imageOverlayRect.width}px; height: ${imageOverlayRect.height}px;`"
                                 @mouseenter="cancelHideImageToolbar()"
                                 @mouseleave="scheduleHideImageToolbar()">
                                
                                <!-- Glowing Selection Outline -->
                                <div class="cms-image-selection-outline"></div>

                                <!-- Top-Left Badge: :: Drag Image -->
                                <div class="cms-image-badge-top-left">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <circle cx="9" cy="5" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="9" cy="19" r="1.5"/>
                                        <circle cx="15" cy="5" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="15" cy="19" r="1.5"/>
                                    </svg>
                                    <span>Drag Image</span>
                                </div>

                                <!-- Bottom-Right Badge: CENTER • IN-PARAGRAPH -->
                                <div class="cms-image-badge-bottom-right" x-text="`${(imageToolbarAlign || 'center').toUpperCase()} • IN-PARAGRAPH`">
                                    CENTER • IN-PARAGRAPH
                                </div>

                                <!-- Floating Toolbar Above Image -->
                                <div class="cms-image-top-bar"
                                     :style="imageOverlayRect.top < 70 ? 'top: 10px; bottom: auto;' : 'bottom: calc(100% + 10px); top: auto;'"
                                     @click.stop>
                                    
                                    <!-- Drag Button -->
                                    <button type="button" class="cms-img-btn-drag" title="Drag Image">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <circle cx="9" cy="5" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="9" cy="19" r="1.5"/>
                                            <circle cx="15" cy="5" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="15" cy="19" r="1.5"/>
                                        </svg>
                                        <span>Drag</span>
                                    </button>

                                    <!-- Move Up -->
                                    <button type="button" @click="moveImageUp()" class="cms-img-tool-btn" title="Move Image Up">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m18 15-6-6-6 6"/></svg>
                                    </button>

                                    <!-- Move Down -->
                                    <button type="button" @click="moveImageDown()" class="cms-img-tool-btn" title="Move Image Down">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m6 9 6 6 6-6"/></svg>
                                    </button>

                                    <span class="cms-img-divider"></span>

                                    <!-- Align Left -->
                                    <button type="button" @click="setImageAlignment('left')"
                                            class="cms-img-tool-btn"
                                            :class="{ 'cms-img-tool-active': imageToolbarAlign === 'left' }"
                                            title="Align Left">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="21" y1="6" x2="3" y2="6"/><line x1="15" y1="12" x2="3" y2="12"/><line x1="17" y1="18" x2="3" y2="18"/></svg>
                                    </button>

                                    <!-- Align Center (Red Highlight when active as in screenshot) -->
                                    <button type="button" @click="setImageAlignment('center')"
                                            class="cms-img-tool-btn"
                                            :class="{ 'cms-img-tool-active': imageToolbarAlign === 'center' }"
                                            title="Align Center">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="21" y1="6" x2="3" y2="6"/><line x1="19" y1="12" x2="5" y2="12"/><line x1="21" y1="18" x2="3" y2="18"/></svg>
                                    </button>

                                    <!-- Align Right -->
                                    <button type="button" @click="setImageAlignment('right')"
                                            class="cms-img-tool-btn"
                                            :class="{ 'cms-img-tool-active': imageToolbarAlign === 'right' }"
                                            title="Align Right">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="12" x2="9" y2="12"/><line x1="21" y1="18" x2="7" y2="18"/></svg>
                                    </button>

                                    <!-- Full Width -->
                                    <button type="button" @click="setImageAlignment('full')"
                                            class="cms-img-tool-btn"
                                            :class="{ 'cms-img-tool-active': imageToolbarAlign === 'full' }"
                                            title="Full Width">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                                    </button>

                                    <span class="cms-img-divider"></span>

                                    <!-- Alt SEO Button -->
                                    <button type="button" @click="toggleImagePopover('alt')"
                                            class="cms-img-pill-action-btn"
                                            :class="{ 'ring-1 ring-blue-400 text-white bg-slate-800': imagePopoverMode === 'alt' }"
                                            title="Edit Image Alt Text & SEO">
                                        <span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span>
                                        <span>Alt SEO</span>
                                    </button>

                                    <!-- Link Button -->
                                    <button type="button" @click="toggleImagePopover('link')"
                                            class="cms-img-pill-action-btn"
                                            :class="{ 'ring-1 ring-blue-400 text-white bg-slate-800': imagePopoverMode === 'link' }"
                                            title="Attach or Edit Hyperlink">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                        <span>Link</span>
                                    </button>

                                    <!-- Replace Button -->
                                    <button type="button" @click="toggleImagePopover('replace')"
                                            class="cms-img-pill-action-btn"
                                            :class="{ 'ring-1 ring-blue-400 text-white bg-slate-800': imagePopoverMode === 'replace' }"
                                            title="Replace Image URL">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M3 21v-5h5"/></svg>
                                        <span>Replace</span>
                                    </button>

                                    <!-- Delete Button -->
                                    <button type="button" @click="deleteActiveImage()" class="cms-img-tool-btn text-red-400 hover:text-red-300 hover:bg-red-500/20" title="Delete Image">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                </div>

                                <!-- Alt SEO Popover -->
                                <div class="cms-image-popover" x-show="imagePopoverMode === 'alt'" x-cloak @click.stop>
                                    <div class="flex items-center justify-between pb-2 mb-2.5 border-b border-slate-700">
                                        <span class="text-xs font-bold text-white flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full bg-blue-400"></span>
                                            Image SEO & Alt Text
                                        </span>
                                        <button type="button" @click="imagePopoverMode = null" class="text-slate-400 hover:text-white text-xs">✕</button>
                                    </div>
                                    <div class="space-y-2.5">
                                        <div>
                                            <label class="text-[11px] text-slate-300 font-semibold mb-1 block">Image Alt Attribute (SEO)</label>
                                            <input type="text" x-model="activeImageAlt" class="cms-popover-input" placeholder="e.g. Kubota D1703 Diesel 25 HP Engine" />
                                            <p class="text-[10px] text-slate-400 mt-1">Google uses this text for Image Search indexing & accessibility.</p>
                                        </div>
                                        <div>
                                            <label class="text-[11px] text-slate-300 font-semibold mb-1 block">Title / Hover Tooltip (Optional)</label>
                                            <input type="text" x-model="activeImageTitle" class="cms-popover-input" placeholder="e.g. Kubota Engine Specifications" />
                                        </div>
                                        <div class="flex items-center justify-end gap-2 pt-1">
                                            <button type="button" @click="imagePopoverMode = null" class="cms-popover-btn-cancel">Cancel</button>
                                            <button type="button" @click="applyImageAlt()" class="cms-popover-btn-apply">Save Alt SEO</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Link Popover -->
                                <div class="cms-image-popover" x-show="imagePopoverMode === 'link'" x-cloak @click.stop>
                                    <div class="flex items-center justify-between pb-2 mb-2.5 border-b border-slate-700">
                                        <span class="text-xs font-bold text-white flex items-center gap-1.5">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                            Image Hyperlink
                                        </span>
                                        <button type="button" @click="imagePopoverMode = null" class="text-slate-400 hover:text-white text-xs">✕</button>
                                    </div>
                                    <div class="space-y-2.5">
                                        <div>
                                            <label class="text-[11px] text-slate-300 font-semibold mb-1 block">Target URL</label>
                                            <input type="url" x-model="activeImageLink" class="cms-popover-input" placeholder="https://example.com/spec-sheet" />
                                        </div>
                                        <label class="flex items-center gap-2 text-[11px] text-slate-300 cursor-pointer pt-0.5">
                                            <input type="checkbox" x-model="activeImageLinkNewTab" class="rounded border-slate-600 bg-slate-800 text-blue-500" />
                                            <span>Open in new window / tab (target="_blank")</span>
                                        </label>
                                        <div class="flex items-center justify-between pt-1">
                                            <button type="button" x-show="activeImageHasLink" @click="removeImageLink()" class="text-red-400 hover:text-red-300 text-xs font-semibold">Remove Link</button>
                                            <div class="flex gap-2 ml-auto">
                                                <button type="button" @click="imagePopoverMode = null" class="cms-popover-btn-cancel">Cancel</button>
                                                <button type="button" @click="applyImageLink()" class="cms-popover-btn-apply">Apply Link</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Replace Popover -->
                                <div class="cms-image-popover" x-show="imagePopoverMode === 'replace'" x-cloak @click.stop>
                                    <div class="flex items-center justify-between pb-2 mb-2.5 border-b border-slate-700">
                                        <span class="text-xs font-bold text-white flex items-center gap-1.5">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M3 21v-5h5"/></svg>
                                            Replace Image
                                        </span>
                                        <button type="button" @click="imagePopoverMode = null" class="text-slate-400 hover:text-white text-xs">✕</button>
                                    </div>
                                    <div class="space-y-2.5">
                                        <div>
                                            <label class="text-[11px] text-slate-300 font-semibold mb-1 block">New Image URL (WebP, PNG, JPG)</label>
                                            <input type="url" x-model="activeImageSrc" class="cms-popover-input" placeholder="https://..." />
                                        </div>
                                        <div class="flex items-center justify-end gap-2 pt-1">
                                            <button type="button" @click="imagePopoverMode = null" class="cms-popover-btn-cancel">Cancel</button>
                                            <button type="button" @click="applyImageReplace()" class="cms-popover-btn-apply">Replace Image</button>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <!-- Visual Contenteditable Canvas -->
                            <div class="cms-visual-editor p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 min-h-[440px] prose dark:prose-invert max-w-none text-slate-800 dark:text-slate-100 leading-relaxed outline-none"
                                 contenteditable="true"
                                 id="cms-visual-editor"
                                 data-placeholder="Write or paste your article here... Paste formatted blog drafts, headings, tables, or image links..."
                                 @input="onVisualInput($event)"
                                 @paste="handleVisualPaste($event)">
                            </div>
                        </div>
                        <p class="text-[11px] text-slate-400">
                            💡 <strong>Visual Canvas Active</strong>: Hover on any image to align, replace, link, or edit Alt SEO. Drag, resize, or type captions directly.
                        </p>
                    </div>

                    <!-- 2. Code (Markdown) Mode Canvas -->
                    <div x-show="editorMode === 'code'" class="pt-4 space-y-2">
                        <div class="flex items-center justify-between text-xs text-slate-400 pb-1">
                            <span>Direct Markdown / HTML source. You can paste images or links directly into this area.</span>
                            <span class="font-mono" x-text="(contentRaw || '').length + ' chars'"></span>
                        </div>
                        <textarea id="cms-main-textarea"
                                  x-model="contentRaw"
                                  @paste="handleRawPaste($event)"
                                  @input="onCodeInput()"
                                  rows="22"
                                  placeholder="Write or edit article content here... Paste text, Google Docs copy, image URLs (https://img.miniexcavator.org/...), YouTube links, and tables..."
                                  class="w-full p-4 text-[13.5px] font-mono rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 leading-relaxed cms-scrollbar"></textarea>
                    </div>

                    <!-- 3. Preview Tab Mode (Authentic Public Blog Replica) -->
                    <div x-show="editorMode === 'preview'" class="pt-2 space-y-6">

                        <!-- Pre-Publish Staging Watermark Banner -->
                        <div class="cms-preview-staging-banner flex flex-wrap items-center justify-between gap-3 p-4 rounded-2xl bg-gradient-to-r from-blue-500/10 via-indigo-500/10 to-sky-500/10 border border-blue-200/80 dark:border-blue-800/60 shadow-xs">
                            <div class="flex items-center gap-3">
                                <span class="flex h-3 w-3 relative shrink-0">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
                                </span>
                                <div>
                                    <div class="text-xs font-black text-slate-800 dark:text-slate-100 flex items-center gap-2">
                                        <span>LIVE BLOG PREVIEW</span>
                                        <span class="text-[10.5px] px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-950/80 text-amber-800 dark:text-amber-300 font-extrabold uppercase tracking-wider border border-amber-200 dark:border-amber-800">
                                            Unpublished Staging • Not Public Yet
                                        </span>
                                    </div>
                                    <p class="text-[11.5px] text-slate-500 dark:text-slate-400 mt-0.5">
                                        This is an exact preview of how readers will experience this article once published on
                                        <strong class="text-blue-600 dark:text-blue-400" x-text="selectedWebsite ? (selectedWebsite.clean_domain || selectedWebsite.name) : 'target website'"></strong>.
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <button type="button" @click="setEditorMode('visual')"
                                        class="px-3.5 py-2 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition shadow-xs flex items-center gap-1.5 cursor-pointer">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m11 17-5-5 5-5"/><path d="M18 19V5"/></svg>
                                    <span>← Back to Editor</span>
                                </button>
                                <button type="button" @click="openPreviewInNewTab()"
                                        class="px-3.5 py-2 text-xs font-bold rounded-xl border border-blue-500/30 bg-blue-600 hover:bg-blue-700 text-white shadow-xs transition flex items-center gap-1.5 cursor-pointer"
                                        title="Open authentic live blog preview in a new browser tab">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <span>Open in New Tab ↗</span>
                                </button>
                                <button type="button" @click="saveBlogPost('published')"
                                        class="px-4 py-2 text-xs font-black rounded-xl bg-red-600 hover:bg-red-700 text-white shadow-xs transition flex items-center gap-1.5 cursor-pointer">
                                    <span>Publish Live</span>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m5 12 7-7 7 7"/><path d="M12 19V5"/></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Simulated Public Website Breadcrumb Header -->
                        <div class="flex items-center justify-between text-xs text-slate-400 pb-3 border-b border-slate-200/80 dark:border-slate-800">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="font-bold text-slate-700 dark:text-slate-300" x-text="selectedWebsite ? (selectedWebsite.clean_domain || selectedWebsite.name) : 'Home'"></span>
                                <span class="text-slate-300 dark:text-slate-700">/</span>
                                <span class="text-blue-600 dark:text-blue-400 font-bold">Blog</span>
                                <span class="text-slate-300 dark:text-slate-700">/</span>
                                <span class="text-slate-600 dark:text-slate-400 font-semibold" x-text="postCategory || 'General'"></span>
                                <span class="text-slate-300 dark:text-slate-700">/</span>
                                <span class="text-slate-400 dark:text-slate-500 truncate max-w-[240px]" x-text="postTitle || 'Untitled Article'"></span>
                            </div>
                            <div class="flex items-center gap-1.5 text-[11px] font-mono text-slate-400">
                                <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span x-text="selectedWebsite ? (selectedWebsite.clean_domain || selectedWebsite.url) : 'Website Staging'"></span>
                            </div>
                        </div>

                        <!-- Article Header / Meta Area -->
                        <div class="space-y-4 pt-2">
                            <!-- Category & Taxonomy Tags -->
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs font-black uppercase tracking-wider px-3 py-1 rounded-full bg-blue-50 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400 border border-blue-200/60 dark:border-blue-900/60"
                                      x-text="postCategory || 'General'"></span>
                                <template x-if="postSeries && postSeries !== 'None (Standalone Article)'">
                                    <span class="text-xs font-bold px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300" x-text="postSeries"></span>
                                </template>
                                <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-900/60">
                                    Verified Blog Guide
                                </span>
                            </div>

                            <!-- Big Bold Public Title -->
                            <h1 class="text-2xl sm:text-3xl md:text-4xl lg:text-[40px] font-black text-slate-900 dark:text-white leading-[1.2] tracking-tight"
                                x-text="postTitle || 'Untitled Blog Post'"></h1>

                            <!-- Author Byline & Reading Metadata Bar -->
                            <div class="flex flex-wrap items-center gap-4 pt-1 pb-4 text-xs text-slate-500 dark:text-slate-400 border-b border-slate-200/80 dark:border-slate-800">
                                <!-- Author Avatar & Name -->
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-blue-600 text-white font-black text-xs flex items-center justify-center shadow-xs uppercase">
                                        <span x-text="(authorName || (selectedWebsite ? selectedWebsite.name : 'A')).charAt(0)"></span>
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-800 dark:text-slate-200 text-xs" x-text="authorName || 'American Compactors'"></div>
                                        <div class="text-[10.5px] text-slate-400" x-text="selectedWebsite ? (selectedWebsite.clean_domain || selectedWebsite.name) : 'Fleet Website'"></div>
                                    </div>
                                </div>

                                <span class="text-slate-300 dark:text-slate-700">•</span>

                                <!-- Publication Date -->
                                <div class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300 font-medium">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    <span x-text="getFormattedPreviewDate()"></span>
                                </div>

                                <span class="text-slate-300 dark:text-slate-700">•</span>

                                <!-- Reading Time & Word Count -->
                                <div class="flex items-center gap-1.5 text-slate-500">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                                    <span x-text="calculateReadingTimeText(contentRaw)"></span>
                                </div>

                                <span class="text-slate-300 dark:text-slate-700">•</span>

                                <!-- Status Badge -->
                                <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-[11px] font-bold text-slate-600 dark:text-slate-300">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    <span>Preview Staging</span>
                                </div>
                            </div>
                        </div>

                        <!-- Featured Hero Cover Image -->
                        <template x-if="featuredImageUrl">
                            <div class="my-6 rounded-2xl overflow-hidden shadow-lg border border-slate-200/80 dark:border-slate-800 max-h-[500px] aspect-video w-full relative bg-slate-100 dark:bg-slate-900">
                                <img :src="featuredImageUrl" :alt="featuredImageAlt || postTitle || 'Featured Cover Image'" class="w-full h-full object-cover">
                                <template x-if="featuredImageAlt">
                                    <div class="absolute bottom-0 inset-x-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent p-3 text-center text-xs text-white/95 italic font-medium" x-text="featuredImageAlt"></div>
                                </template>
                            </div>
                        </template>

                        <!-- Article Content Body (Rich Styled Public Article) -->
                        <div class="cms-preview-article-body prose prose-slate dark:prose-invert max-w-none text-slate-800 dark:text-slate-100 leading-relaxed font-normal text-[15.5px] space-y-4 pt-2"
                             x-html="renderPreviewContentHtml(contentRaw)"></div>

                        <!-- Tailored Website CTA & Equipment Card -->
                        <template x-if="productUrl || productSku || (currentWebsiteProfile && currentWebsiteProfile.store_url)">
                            <div class="cms-equipment-cta my-8 p-6 rounded-2xl bg-gradient-to-br from-blue-50/90 via-slate-50 to-white dark:from-slate-900 dark:via-slate-900 dark:to-blue-950/40 border border-blue-200/80 dark:border-blue-900/60 shadow-sm">
                                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                                    <div class="space-y-1.5">
                                        <div class="inline-flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wider text-blue-600 dark:text-blue-400 bg-blue-100/60 dark:bg-blue-950/80 px-2.5 py-0.5 rounded-full">
                                            <span>★ Featured Machinery &amp; Equipment</span>
                                        </div>
                                        <h4 class="text-lg font-black text-slate-900 dark:text-white" x-text="productSku ? ('Model / Series: ' + productSku) : (selectedWebsite ? (selectedWebsite.name + ' Heavy Equipment') : 'Featured Machinery')"></h4>
                                        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed max-w-xl">Looking for top-performing heavy equipment, authentic attachments, and dependable jobsite power? Explore verified models backed by manufacturer warranty and nationwide delivery.</p>
                                    </div>
                                    <a :href="productUrl || currentWebsiteProfile?.store_url || '#'" target="_blank" class="px-5 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-md transition-all flex items-center gap-2 shrink-0">
                                        <span>View Specifications &amp; Pricing</span>
                                        <span>&rarr;</span>
                                    </a>
                                </div>
                            </div>
                        </template>

                        <!-- Branded Author Profile Box -->
                        <div class="mt-8 p-5 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200/80 dark:border-slate-800 flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-600 to-indigo-700 text-white font-black text-base flex items-center justify-center shrink-0 uppercase shadow-xs">
                                <span x-text="(authorName || (selectedWebsite ? selectedWebsite.name : 'A')).charAt(0)"></span>
                            </div>
                            <div class="space-y-1">
                                <div class="text-[10.5px] font-black text-slate-400 uppercase tracking-wider">About the Author</div>
                                <div class="text-sm font-black text-slate-900 dark:text-white" x-text="authorName || 'Editorial Team'"></div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                    Published for readers on <strong class="text-slate-700 dark:text-slate-300" x-text="selectedWebsite ? (selectedWebsite.clean_domain || selectedWebsite.name) : 'our network'"></strong>. Professional insights, machinery specifications, and heavy equipment operation guides.
                                </p>
                            </div>
                        </div>

                        <!-- Bottom Return & Action Bar -->
                        <div class="mt-8 pt-5 border-t border-slate-200/80 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3">
                            <button type="button" @click="setEditorMode('visual')"
                                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 transition shadow-xs cursor-pointer">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m11 17-5-5 5-5"/><path d="M18 19V5"/></svg>
                                <span>← Return to Visual Editor</span>
                            </button>
                            <button type="button" @click="saveBlogPost('published')"
                                    class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl text-xs font-black text-white bg-red-600 hover:bg-red-700 shadow-md transition cursor-pointer">
                                <span>Publish Live to Website</span>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m5 12 7-7 7 7"/><path d="M12 19V5"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ══════════════════════════════════════════════════════════════
                 RIGHT COLUMN (SLIDE): Inspector Cards (Image 1 UX/UI)
                 Independently scrollable on desktop!
                 ══════════════════════════════════════════════════════════════ -->
            <div class="lg:col-span-4 cms-editor-sidebar-col cms-studio-col-right space-y-4"
                 x-show="editorMode !== 'preview'"
                 :class="{ 'hidden lg:block': mobileStudioTab !== 'settings' }">

                <!-- Sleek Segmented Inspector Navigation Tabs -->
                <div class="cms-inspector-nav">
                    <button type="button" 
                            @click="activeSidebarTab = 'publish'" 
                            class="cms-inspector-tab-btn" 
                            :class="{ 'active': activeSidebarTab === 'publish' }"
                            title="Publishing, Target Site & Taxonomy">
                        <span>⚙️ Publish</span>
                    </button>
                    <button type="button" 
                            @click="activeSidebarTab = 'cover'" 
                            class="cms-inspector-tab-btn" 
                            :class="{ 'active': activeSidebarTab === 'cover' }"
                            title="Featured Cover Image & Excerpt">
                        <span>🖼️ Media</span>
                    </button>
                    <button type="button" 
                            @click="activeSidebarTab = 'seo'" 
                            class="cms-inspector-tab-btn" 
                            :class="{ 'active': activeSidebarTab === 'seo' }"
                            title="SEO Meta, Keywords & SERP Preview">
                        <span>🚀 SEO</span>
                    </button>
                    <button type="button" 
                            @click="activeSidebarTab = 'all'" 
                            class="cms-inspector-tab-btn" 
                            :class="{ 'active': activeSidebarTab === 'all' }"
                            title="View All Inspector Cards">
                        <span>📑 All</span>
                    </button>
                </div>

                <!-- Card 1: Publishing (Target Website Switcher - Image 1 Dropdown) -->
                <div class="cms-editor-card" x-show="activeSidebarTab === 'publish' || activeSidebarTab === 'all'">
                    <div class="cms-editor-card-header-row" style="margin-bottom: 12px; padding-bottom: 10px;">
                        <h3 class="cms-card-subhead">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--cms-text-secondary);">
                                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                            <span>Publishing</span>
                        </h3>
                        <span class="cms-header-status-badge uppercase tracking-wider"
                              :class="normalizeStatus(postStatus) === 'published' ? 'status-published' : (normalizeStatus(postStatus) === 'scheduled' ? 'status-scheduled' : 'status-draft')"
                              x-text="statusLabel(postStatus)">
                            DRAFT
                        </span>
                    </div>

                    <div class="space-y-3.5">
                        <!-- Target Website Switcher (Rich Custom Dropdown with Class Badge) -->
                        <div class="relative" @click.outside="siteDropdownOpen = false">
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="cms-field-label-sm" style="margin: 0;">Target Website <span class="text-red-500">*</span></label>
                                <span class="text-[10px] font-bold text-slate-400" x-text="websites.length + ' sites connected'"></span>
                            </div>

                            <!-- Trigger Button -->
                            <button type="button"
                                    @click="siteDropdownOpen = !siteDropdownOpen"
                                    class="cms-site-select-trigger"
                                    :class="siteDropdownOpen ? 'is-open ring-2 ring-blue-500 border-blue-500 shadow-md' : ''">
                                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                    <!-- Website Logo / Favicon -->
                                    <template x-if="selectedWebsite">
                                        <img :src="selectedWebsite.logo || selectedWebsite.favicon || ('https://www.google.com/s2/favicons?domain=' + selectedWebsite.clean_domain + '&sz=64')"
                                             x-on:error="$el.src = 'https://www.google.com/s2/favicons?domain=' + selectedWebsite.clean_domain + '&sz=64'"
                                             class="w-7 h-7 rounded-lg object-contain border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-0.5 shrink-0 shadow-xs" />
                                    </template>
                                    <template x-if="!selectedWebsite">
                                        <div class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xs font-bold shrink-0 border border-blue-100 dark:border-blue-900/50">
                                            🌐
                                        </div>
                                    </template>

                                    <div class="min-w-0 flex-1 text-left">
                                        <!-- Top row: Website Name + Class Badge -->
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="cms-site-title text-xs truncate" x-text="selectedWebsite ? selectedWebsite.name : 'Select a website...'"></span>
                                            <template x-if="selectedWebsite && selectedWebsite.category">
                                                <span class="cms-class-badge" :class="getClassBadgeColor(selectedWebsite.category)" x-text="selectedWebsite.category"></span>
                                            </template>
                                        </div>
                                        <!-- Bottom row: Domain + Handler PF chip -->
                                        <div class="flex items-center gap-2 text-[10.5px] mt-0.5 truncate">
                                            <span class="cms-site-domain font-mono truncate" x-text="selectedWebsite ? (selectedWebsite.clean_domain || selectedWebsite.url) : 'No website selected'"></span>
                                            <template x-if="selectedWebsite && (selectedWebsite.handler_name || selectedWebsite.handler_avatar)">
                                                <span class="cms-handler-chip shrink-0">
                                                    <template x-if="selectedWebsite.handler_avatar">
                                                        <img :src="selectedWebsite.handler_avatar" class="cms-handler-avatar-img" x-on:error="$el.style.display='none'">
                                                    </template>
                                                    <template x-if="!selectedWebsite.handler_avatar && selectedWebsite.handler_initial">
                                                        <span class="cms-handler-avatar-fallback" x-text="selectedWebsite.handler_initial"></span>
                                                    </template>
                                                    <span class="max-w-[85px] truncate" x-text="selectedWebsite.handler_name || 'Unassigned'"></span>
                                                </span>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <!-- Chevron -->
                                <div class="flex items-center gap-1.5 shrink-0 pl-1">
                                    <svg class="w-4 h-4 text-slate-400 transition-transform duration-150" :class="siteDropdownOpen ? 'rotate-180 text-blue-500' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </div>
                            </button>

                            <!-- Dropdown Popover Menu -->
                            <div x-show="siteDropdownOpen"
                                 x-transition
                                 class="cms-site-dropdown-popover"
                                 style="display: none;">
                                
                                <!-- Search filter inside dropdown -->
                                <div class="cms-site-search-wrap">
                                    <div class="relative">
                                        <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                                        </svg>
                                        <input type="text"
                                               x-model="websiteSearch"
                                               placeholder="Search website, domain, class, or handler..."
                                               class="cms-site-search-input"
                                               @click.stop="" />
                                    </div>
                                </div>

                                <!-- Scrollable fleet websites list -->
                                <div class="overflow-y-auto max-h-[260px] cms-scrollbar p-1">
                                    <template x-for="site in filteredWebsites()" :key="site.id">
                                        <div @click="selectWebsiteFromDropdown(site); siteDropdownOpen = false;"
                                             class="cms-site-item"
                                             :class="site.id == selectedWebsiteId ? 'is-selected' : ''">
                                            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                                <!-- Website Icon / Logo -->
                                                <img :src="site.logo || site.favicon || ('https://www.google.com/s2/favicons?domain=' + site.clean_domain + '&sz=64')"
                                                     x-on:error="$el.src = 'https://www.google.com/s2/favicons?domain=' + site.clean_domain + '&sz=64'"
                                                     class="w-6 h-6 rounded-md object-contain border border-slate-200 dark:border-slate-700 bg-white p-0.5 shrink-0 shadow-2xs" />

                                                <div class="min-w-0 flex-1">
                                                    <!-- Website Name -->
                                                    <div class="cms-site-title text-xs truncate" x-text="site.name"></div>
                                                    
                                                    <!-- Domain + Handler PF -->
                                                    <div class="flex items-center gap-1.5 text-[10px] mt-0.5">
                                                        <span class="cms-site-domain font-mono truncate max-w-[120px]" x-text="site.clean_domain || site.url"></span>
                                                        <template x-if="site.handler_name || site.handler_avatar">
                                                            <span class="cms-handler-chip shrink-0">
                                                                <template x-if="site.handler_avatar">
                                                                    <img :src="site.handler_avatar" class="cms-handler-avatar-img" x-on:error="$el.style.display='none'">
                                                                </template>
                                                                <template x-if="!site.handler_avatar && site.handler_initial">
                                                                    <span class="cms-handler-avatar-fallback" x-text="site.handler_initial"></span>
                                                                </template>
                                                                <span class="max-w-[70px] truncate" x-text="site.handler_name || 'Unassigned'"></span>
                                                            </span>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Right: Class Badge & Active Checkmark -->
                                            <div class="flex items-center gap-1.5 shrink-0 pl-1">
                                                <span class="cms-class-badge" :class="getClassBadgeColor(site.category)" x-text="site.category || 'General'"></span>
                                                <template x-if="site.id == selectedWebsiteId">
                                                    <span class="text-blue-600 dark:text-blue-400 font-black text-xs">✓</span>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Status line below dropdown -->
                            <div class="flex items-center justify-between text-[10.5px] text-slate-400 mt-1.5 px-0.5">
                                <span>Author: <strong class="text-slate-700 dark:text-slate-200" x-text="authorName || 'Happy Machinery'"></strong></span>
                                <span class="text-emerald-600 dark:text-emerald-400 font-bold" x-text="editingPostId ? ('ID #' + editingPostId) : (normalizeStatus(postStatus) === 'published' ? 'Live' : 'Draft')">Live</span>
                            </div>
                        </div>

                        <!-- Visibility Status Dropdown -->
                        <div>
                            <label class="cms-field-label-sm">Visibility Status</label>
                            <select class="cms-editor-select" x-model="postStatus">
                                <option value="draft">Draft (Work in Progress)</option>
                                <option value="published">Published (Live to Website)</option>
                                <option value="scheduled">Scheduled (Auto-Publish Later)</option>
                            </select>
                        </div>

                        <!-- Scheduled Post Date/Time Box -->
                        <div x-show="postStatus === 'scheduled'" x-transition class="cms-scheduled-box space-y-1">
                            <label class="cms-field-label-sm" style="color: #b45309;">Schedule Date &amp; Time</label>
                            <input type="datetime-local" x-model="scheduledAt" class="cms-editor-select" style="font-size: 12px;" />
                        </div>

                        <!-- Author Field -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="cms-field-label-sm" style="margin: 0;">Author</label>
                                <button type="button" @click="customAuthorPrompt()" class="text-[11px] font-bold text-blue-600 dark:text-blue-400 hover:underline">
                                    + Custom
                                </button>
                            </div>
                            <select class="cms-editor-select" x-model="authorName">
                                <template x-for="auth in availableAuthors" :key="auth">
                                    <option :value="auth" x-text="auth"></option>
                                </template>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Featured Cover Image (Directly Under Publishing - Matches Image 1) -->
                <div class="cms-editor-card" x-show="activeSidebarTab === 'cover' || activeSidebarTab === 'all'">
                    <div class="cms-editor-card-header-row" style="margin-bottom: 12px; padding-bottom: 10px;">
                        <h3 class="cms-card-subhead">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--cms-text-secondary);">
                                <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
                                <circle cx="9" cy="9" r="2"/>
                                <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                            </svg>
                            <span>Featured Cover Image</span>
                        </h3>

                        <div class="flex items-center gap-1.5">
                            <button type="button" @click="autoDetectCoverImage()" class="cms-cover-autodetect-btn" title="Auto-detect 1st image from article content">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 21h5v-5"/></svg>
                                Auto-detect
                            </button>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <!-- Hidden file input -->
                        <input type="file" id="cms-cover-file-input" accept="image/*" class="hidden" @change="handleCoverFileUpload($event)" />

                        <!-- Quick Action Buttons -->
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" @click="document.getElementById('cms-cover-file-input').click()" class="cms-cover-action-btn">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                <span>Upload File</span>
                            </button>
                            <button type="button" @click="openMediaLibraryModal()" class="cms-cover-action-btn">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                <span>Media Library</span>
                            </button>
                        </div>

                        <!-- Dropzone OR Rich Image Preview -->
                        <template x-if="featuredImageUrl">
                            <div class="cms-cover-preview-wrapper group">
                                <img :src="featuredImageUrl" :alt="featuredImageAlt || 'Cover preview'" class="cms-cover-preview-img"
                                     onerror="this.src='https://placehold.co/600x400?text=Invalid+Image+Link'" />
                                <div class="cms-cover-preview-overlay">
                                    <button type="button" @click="document.getElementById('cms-cover-file-input').click()" class="cms-cover-overlay-btn">Replace</button>
                                    <button type="button" @click="featuredImageUrl = ''; featuredImageAlt = ''" class="cms-cover-overlay-btn danger">Remove</button>
                                </div>
                            </div>
                        </template>

                        <template x-if="!featuredImageUrl">
                            <div class="cms-cover-dropzone"
                                 @click="document.getElementById('cms-cover-file-input').click()"
                                 @dragover.prevent=""
                                 @drop.prevent="handleCoverFileDrop($event)">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="color: #94a3b8;">
                                    <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
                                    <circle cx="9" cy="9" r="2"/>
                                    <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                                </svg>
                                <span style="font-size: 11.5px; font-weight: 700; color: var(--cms-text-secondary);">
                                    Drag &amp; drop or click to upload
                                </span>
                                <span style="font-size: 10px; color: var(--cms-text-muted);">
                                    Auto-compressed to WebP &amp; saved to library • Auto-inserts 1st article image
                                </span>
                            </div>
                        </template>

                        <!-- Image URL input -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="cms-field-label-sm" style="margin: 0;">IMAGE URL</label>
                                <button type="button" @click="autoDetectCoverImage()" class="text-[11px] font-bold text-sky-600 dark:text-sky-400 hover:underline">
                                    Auto-insert 1st image
                                </button>
                            </div>
                            <input type="url"
                                   class="cms-editor-input-sm"
                                   placeholder="https://images.unsplash.com/... or /images/..."
                                   x-model="featuredImageUrl" />
                        </div>

                        <!-- Alt Description input -->
                        <div>
                            <label class="cms-field-label-sm">ALT DESCRIPTION (SEO &amp; ACCESSIBILITY)</label>
                            <input type="text"
                                   class="cms-editor-input-sm"
                                   placeholder="e.g. Modern excavator loading gravel on highway construction site"
                                   x-model="featuredImageAlt" />
                        </div>
                    </div>
                </div>

                <!-- Card 3: Organization & Taxonomy (Matches Image 1) -->
                <div class="cms-editor-card" x-show="activeSidebarTab === 'publish' || activeSidebarTab === 'all'">
                    <div class="cms-editor-card-header-row" style="margin-bottom: 12px; padding-bottom: 10px;">
                        <h3 class="cms-card-subhead">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2">
                                <polygon points="12 2 2 7 12 12 22 7 12 2"/>
                                <polyline points="2 17 12 22 22 17"/>
                                <polyline points="2 12 12 17 22 12"/>
                            </svg>
                            <span>Organization &amp; Taxonomy</span>
                        </h3>
                    </div>

                    <div class="space-y-3.5">
                        <!-- Primary Category -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="cms-field-label-sm" style="margin: 0;">Primary Category</label>
                                <button type="button" @click="customCategoryPrompt()" class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                                    + New Category
                                </button>
                            </div>
                            <select class="cms-editor-select font-medium" x-model="postCategory">
                                <template x-for="cat in availableCategories" :key="cat">
                                    <option :value="cat" x-text="cat"></option>
                                </template>
                            </select>
                        </div>

                        <!-- Topic Series / Playlist -->
                        <div x-show="currentWebsiteProfile?.has_series">
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="cms-field-label-sm" style="margin: 0;">Topic Series / Playlist</label>
                                <button type="button" @click="customSeriesPrompt()" class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                                    + New Series
                                </button>
                            </div>
                            <select class="cms-editor-select" x-model="postSeries">
                                <template x-for="s in availableSeries" :key="s">
                                    <option :value="s" x-text="s"></option>
                                </template>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Article Excerpt -->
                <div class="cms-editor-card" x-show="activeSidebarTab === 'cover' || activeSidebarTab === 'all'">
                    <div class="cms-editor-card-header-row" style="margin-bottom: 10px; padding-bottom: 8px;">
                        <h3 class="cms-card-subhead">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--cms-text-secondary);">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                            </svg>
                            <span>Article Excerpt</span>
                        </h3>
                        <span class="text-[11px] font-mono" :class="(postExcerpt || '').length > 250 ? 'text-amber-500 font-bold' : 'text-slate-400'" x-text="(postExcerpt || '').length + ' / 300'">
                            0 / 300
                        </span>
                    </div>
                    <textarea rows="3"
                              class="cms-editor-textarea"
                              placeholder="A concise, engaging 1-2 sentence preview shown on category cards, homepage feeds, and SEO snippets..."
                              maxlength="300"
                              x-model="postExcerpt"></textarea>
                </div>

                <!-- Card 5: Attached Video (Optional) -->
                <div class="cms-editor-card" x-show="activeSidebarTab === 'seo' || activeSidebarTab === 'all'">
                    <div class="cms-editor-card-header-row" style="margin-bottom: 10px; padding-bottom: 8px;">
                        <h3 class="cms-card-subhead">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2">
                                <polygon points="23 7 16 12 23 17 23 7"/>
                                <rect width="14" height="14" x="1" y="5" rx="2" ry="2"/>
                            </svg>
                            <span>Attached Video (Optional)</span>
                        </h3>
                    </div>
                    <input type="url"
                           class="cms-editor-input-sm"
                           placeholder="https://www.youtube.com/watch?v=... or https://youtu.be/..."
                           x-model="postVideoUrl" />

                    <div x-show="postVideoUrl" class="mt-3">
                        <div class="cms-youtube-wrapper" style="margin: 0;">
                            <iframe :src="computeEmbedVideoUrl(postVideoUrl)" title="Attached Video Preview" allowfullscreen></iframe>
                        </div>
                    </div>
                </div>

                <!-- Card 6: SEO Meta & SERP Preview -->
                <div class="cms-editor-card" x-show="activeSidebarTab === 'seo' || activeSidebarTab === 'all' || activeSidebarTab === 'publish'">
                    <div class="cms-editor-card-header-row" style="margin-bottom: 10px; padding-bottom: 8px;">
                        <h3 class="cms-card-subhead">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                <polyline points="15 3 21 3 21 9"/>
                                <line x1="10" y1="14" x2="21" y2="3"/>
                            </svg>
                            <span>SEO &amp; Google Snippet</span>
                        </h3>
                    </div>
                    <div class="space-y-3.5">
                        <!-- Meta Title -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="cms-field-label-sm" style="margin: 0;">Meta Title (SEO)</label>
                                <div class="flex items-center gap-1.5">
                                    <button type="button" 
                                            x-show="(metaTitle || postTitle || '').length > 60"
                                            @click="metaTitle = autoCutSeoGrammar(metaTitle || postTitle, 60, true); if (articles[0]) articles[0].meta_title = metaTitle; showToast('Meta Title auto-cut to 60 characters following English grammar!')"
                                            class="text-[9.5px] px-2 py-0.5 rounded font-bold bg-amber-500/10 hover:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30 transition-all cursor-pointer flex items-center gap-1 shadow-sm"
                                            title="Auto-cut title cleanly following English grammar rules">
                                        <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><line x1="20" y1="4" x2="8.12" y2="15.88"/><line x1="14.47" y1="14.48" x2="20" y2="20"/><line x1="8.12" y1="8.12" x2="12" y2="12"/></svg>
                                        Auto-Cut (60)
                                    </button>
                                    <span class="text-[10px] font-mono" :class="(metaTitle || postTitle || '').length > 60 ? 'text-amber-500 font-bold' : 'text-slate-400'" x-text="(metaTitle || postTitle || '').length + ' / 60'"></span>
                                </div>
                            </div>
                            <input type="text" 
                                   class="cms-editor-input-sm" 
                                   placeholder="Title for Google Search results..." 
                                   x-model="metaTitle" 
                                   maxlength="150" 
                                   @input="if (articles[0]) articles[0].meta_title = metaTitle"
                                   @blur="if (metaTitle && metaTitle.length > 60) { metaTitle = autoCutSeoGrammar(metaTitle, 60, true); if (articles[0]) articles[0].meta_title = metaTitle; }" />
                        </div>

                        <!-- Meta Description -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="cms-field-label-sm" style="margin: 0;">Meta Description</label>
                                <div class="flex items-center gap-1.5">
                                    <button type="button" 
                                            x-show="(metaDescription || postExcerpt || '').length > 160"
                                            @click="metaDescription = autoCutSeoGrammar(metaDescription || postExcerpt, 160, false); if (articles[0]) articles[0].meta_description = metaDescription; showToast('Meta Description auto-cut to 160 characters following English grammar!')"
                                            class="text-[9.5px] px-2 py-0.5 rounded font-bold bg-amber-500/10 hover:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30 transition-all cursor-pointer flex items-center gap-1 shadow-sm"
                                            title="Auto-cut description cleanly following English grammar rules">
                                        <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><line x1="20" y1="4" x2="8.12" y2="15.88"/><line x1="14.47" y1="14.48" x2="20" y2="20"/><line x1="8.12" y1="8.12" x2="12" y2="12"/></svg>
                                        Auto-Cut (160)
                                    </button>
                                    <span class="text-[10px] font-mono" :class="(metaDescription || postExcerpt || '').length > 160 ? 'text-amber-500 font-bold' : 'text-slate-400'" x-text="(metaDescription || postExcerpt || '').length + ' / 160'"></span>
                                </div>
                            </div>
                            <textarea rows="2" 
                                      class="cms-editor-textarea" 
                                      placeholder="Google snippet summary shown in search results..." 
                                      x-model="metaDescription" 
                                      maxlength="300" 
                                      @input="if (articles[0]) articles[0].meta_description = metaDescription"
                                      @blur="if (metaDescription && metaDescription.length > 160) { metaDescription = autoCutSeoGrammar(metaDescription, 160, false); if (articles[0]) articles[0].meta_description = metaDescription; }"></textarea>
                        </div>

                        <!-- Tag Keywords -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="cms-field-label-sm" style="margin: 0;">Tag Keywords</label>
                                <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400">SEO &amp; Tags</span>
                            </div>
                            <input type="text" class="cms-editor-input-sm" placeholder="e.g. mini excavator, machinery parts, maintenance (comma separated)" x-model="tagKeywords" />
                            
                            <!-- Dynamic Tag Pills Preview -->
                            <div class="flex flex-wrap gap-1.5 mt-2" x-show="tagKeywords && tagKeywords.trim()">
                                <template x-for="kw in tagKeywords.split(',').map(k => k.trim()).filter(Boolean)" :key="kw">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10.5px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                        #<span x-text="kw"></span>
                                    </span>
                                </template>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-1">Separate keywords with commas. Used for meta keywords, WordPress post tags, and search index discovery.</p>
                        </div>

                        <!-- Google Search SERP Preview Box -->
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 space-y-1">
                            <span class="text-[9.5px] font-black uppercase tracking-wider text-slate-400 block mb-0.5">Google Search Preview</span>
                            <div class="text-[10px] text-slate-400 truncate" x-text="computeCanonicalUrl()"></div>
                            <h4 class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 truncate" x-text="metaTitle || postTitle || 'Sample Title'"></h4>
                            <p class="text-[11px] text-slate-600 dark:text-slate-400 line-clamp-2" x-text="autoCutSeoGrammar(metaDescription || postExcerpt || '', 160, false) || 'Article summary preview...'"></p>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>

    <!-- ── Fast Multi-Site Post Modal (Pops when "+ Add More" is clicked) ── -->
    <div x-show="addMoreModalOpen" x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
         @keydown.escape.window="addMoreModalOpen = false" style="display: none;">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-xl w-full p-6 space-y-4 shadow-2xl relative"
             @click.outside="addMoreModalOpen = false">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-lg shadow-xs">
                        ✍️
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-900 dark:text-white">Start Another Blog Post</h3>
                        <p class="text-xs text-slate-400">Choose target website to immediately begin drafting a new article:</p>
                    </div>
                </div>
                <button type="button" @click="addMoreModalOpen = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1.5 rounded-lg text-sm">✕</button>
            </div>

            <!-- Search input -->
            <div class="relative">
                <input type="text" x-model="addMoreSearch" placeholder="Search target websites by domain or name..."
                       class="w-full pl-9 pr-4 py-2.5 text-xs rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <svg class="absolute left-3 top-3 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <!-- Website cards list -->
            <div class="max-h-72 overflow-y-auto space-y-2 pr-1 cms-scrollbar">
                <template x-for="site in filteredAddMoreWebsites()" :key="site.id">
                    <button type="button" @click="selectWebsiteForNewPost(site)"
                            class="w-full flex items-center justify-between p-3 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-blue-500 dark:hover:border-blue-500 hover:bg-blue-50/50 dark:hover:bg-blue-950/30 transition-all text-left group">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs flex items-center justify-center shrink-0 uppercase" x-text="(site.name || site.clean_domain || 'W').substring(0, 2)"></span>
                            <div class="min-w-0">
                                <div class="text-xs font-bold text-slate-900 dark:text-white truncate group-hover:text-blue-600 dark:group-hover:text-blue-400" x-text="site.name || site.clean_domain"></div>
                                <div class="text-[11px] text-slate-400 truncate" x-text="site.clean_domain || site.url"></div>
                            </div>
                        </div>
                        <span class="text-xs font-bold text-blue-600 dark:text-blue-400 opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-1 shrink-0">
                            Create Draft &rarr;
                        </span>
                    </button>
                </template>
            </div>
        </div>
    </div>

    <!-- ── SMART IMPORT BLOG MODAL ── -->
    <div x-show="smartPasteModalOpen" x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
         @keydown.escape.window="smartPasteModalOpen = false" style="display: none;">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-2xl w-full p-6 space-y-4 shadow-2xl relative"
             @click.outside="smartPasteModalOpen = false">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-blue-100 dark:bg-blue-950/80 text-blue-600 flex items-center justify-center font-black">
                        ⚡
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-900 dark:text-white">Smart Import Blog &amp; Smart Paste (Multi-Website Parser)</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Auto-extracts Title, Cover Image, Meta Description, YouTube, and Tables.</p>
                    </div>
                </div>
                <button type="button" @click="smartPasteModalOpen = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-base">✕</button>
            </div>

            <div>
                <textarea x-model="smartPasteBuffer" rows="12"
                          placeholder="Paste your raw blog post draft here with Google Docs copy, title, images (https://img.miniexcavator.org/...), YouTube videos, and tables..."
                          class="w-full p-4 text-xs font-sans rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 leading-relaxed cms-scrollbar"></textarea>
            </div>

            <div class="flex items-center justify-between pt-2">
                <span class="text-[11px] text-slate-400">Pasted draft will auto-populate Title, Featured Cover, YouTube, and Tables.</span>
                <div class="flex items-center gap-2">
                    <button type="button" @click="smartPasteModalOpen = false" class="px-4 py-2 text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">Cancel</button>
                    <button type="button" @click="applySmartPaste()" class="px-5 py-2 text-xs font-black rounded-xl bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-500/25 cursor-pointer">Import &amp; Auto-Format</button>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function blogPostCmsApp() {
    return {
        websites: window.DGT_WEBSITES || [],
        websiteProfiles: window.DGT_WEBSITE_PROFILES || {},
        currentWebsiteProfile: window.DGT_CURRENT_PROFILE || null,
        selectedWebsiteId: window.DGT_SELECTED_SITE_ID || (window.DGT_WEBSITES && window.DGT_WEBSITES[0] ? window.DGT_WEBSITES[0].id : null),
        selectedWebsite: null,

        // Modes & Layout
        editorMode: 'visual',
        activeViewTab: 'editor',
        activeSidebarTab: 'all',

        // Multi-Article State: Each article is an independent reactive object in this array
        articles: [],
        smartPasteTargetArtUid: null,

        // Backward-Compatibility / Single-Article Legacy Properties (kept synced with articles[0])
        editingPostId: null,
        postTitle: '',
        postSlug: '',
        slugManuallyEdited: false,
        contentRaw: '',
        postStatus: 'published',
        scheduledAt: '',
        authorName: '',
        postCategory: '',
        postSeries: 'None (Standalone Article)',
        postExcerpt: '',
        featuredImageUrl: '',
        featuredImageAlt: '',
        postVideoUrl: '',
        productSku: '',
        productUrl: '',
        metaTitle: '',
        metaDescription: '',
        tagKeywords: '',
        focusKeyword: '',

        // Dynamic Lists
        availableAuthors: [],
        availableCategories: [],
        availableSeries: [],

        // Screen View Controller ('list' = Front Page Hub, 'editor' = Multi-Article Studio Editor)
        currentScreen: 'list',
        mobileStudioTab: 'content',

        // Front Page Hub Filter & Search State
        latestSearchQuery: '',
        latestWebsiteFilter: 'all',
        siteFilterDropdownOpen: false,
        siteFilterSearchQuery: '',
        latestStatusFilter: 'all',
        feedLimit: '10',
        addMoreModalOpen: false,
        addMoreSearch: '',
        realtimeClockString: '',
        stats: @json($stats),

        // State Flags
        siteDropdownOpen: false,
        websiteSearch: '',
        isSaving: false,
        isRefreshingLatest: false,
        smartPasteModalOpen: false,
        smartPasteBuffer: '',
        latestPosts: @json($latestPosts),

        // Floating Image Toolbar State (Image 1 UX/UI)
        imageToolbarOpen: false,
        imageOverlayRect: { top: 0, left: 0, width: 0, height: 0 },
        imageToolbarAlign: 'center',
        activeImageFigure: null,
        activeImageEl: null,
        activeImageAlt: '',
        activeImageTitle: '',
        activeImageLink: '',
        activeImageHasLink: false,
        activeImageLinkNewTab: true,
        activeImageSrc: '',
        imagePopoverMode: null,
        imageHideTimeout: null,

        // ════════════════════════════════════════════════════════════════════════
        // MULTI-ARTICLE MANAGEMENT & FACTORY
        // ════════════════════════════════════════════════════════════════════════

        createArticleObject(websiteId = null, initialData = {}) {
            const uid = 'art_' + Date.now() + '_' + Math.random().toString(36).substring(2, 8);
            const targetSiteId = websiteId || this.selectedWebsiteId || (this.websites[0] ? this.websites[0].id : null);
            const profile = this.getWebsiteProfile(targetSiteId);

            const defaultAuthor = initialData.author_name || (profile && (profile.default_author || (profile.authors && profile.authors[0]))) || 'Editorial Team';
            const defaultCategory = initialData.category || (profile && profile.categories && profile.categories[0]) || 'General';
            let initialContent = initialData.contentRaw !== undefined 
                ? initialData.contentRaw 
                : (initialData.raw_content !== undefined ? initialData.raw_content : (initialData.content_html || ''));

            return {
                uid: uid,
                id: initialData.id || null,
                website_id: targetSiteId,
                title: initialData.title || '',
                slug: initialData.slug || '',
                slugManuallyEdited: Boolean(initialData.slug),
                contentRaw: initialContent,
                status: initialData.status ? this.normalizeStatus(initialData.status) : 'published',
                scheduled_at: initialData.scheduled_at || '',
                author_name: defaultAuthor,
                category: defaultCategory,
                playlist: initialData.playlist || 'None (Standalone Article)',
                excerpt: initialData.excerpt || '',
                featured_image_url: initialData.featured_image_url || '',
                featured_image_alt: initialData.featured_image_alt || initialData.cover_alt || '',
                video_url: initialData.video_url || '',
                product_sku: initialData.product_sku || '',
                product_url: initialData.product_url || (profile && profile.store_url) || '',
                meta_title: initialData.meta_title || '',
                meta_description: initialData.meta_description || '',
                tag_keywords: initialData.tag_keywords || initialData.focus_keyword || '',
                focus_keyword: initialData.focus_keyword || initialData.tag_keywords || '',
                editorMode: 'visual',
                activeSidebarTab: 'all',
                isCollapsed: false,
                mobileTab: 'content',
                siteDropdownOpen: false,
                websiteSearch: ''
            };
        },

        addMoreArticle(targetWebsiteId = null) {
            let nextSiteId = targetWebsiteId;
            if (!nextSiteId) {
                if (this.websites.length > 1) {
                    const lastSiteId = this.articles.length > 0 ? this.articles[this.articles.length - 1].website_id : this.selectedWebsiteId;
                    const currentIndex = this.websites.findIndex(w => w.id == lastSiteId);
                    const nextIndex = (currentIndex + 1) % this.websites.length;
                    nextSiteId = this.websites[nextIndex].id;
                } else if (this.websites.length === 1) {
                    nextSiteId = this.websites[0].id;
                }
            }

            const newArt = this.createArticleObject(nextSiteId);
            this.articles.push(newArt);

            if (this.currentScreen !== 'editor') {
                this.currentScreen = 'editor';
            }

            this.$nextTick(() => {
                this.syncVisualCanvasForArticle(newArt, true);
                const blockEl = document.getElementById('article-block-' + newArt.uid);
                if (blockEl) {
                    blockEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    const titleInput = blockEl.querySelector('.cms-raf-title-input');
                    if (titleInput) {
                        setTimeout(() => titleInput.focus(), 300);
                    }
                }
            });

            const siteName = this.getWebsiteName(nextSiteId) || 'selected site';
            this.showToast(`Added Article #${this.articles.length} targeting ${siteName}!`);
        },

        removeArticle(index) {
            if (this.articles.length <= 1) {
                alert('Cannot remove the only article. At least one article draft is required in the studio.');
                return;
            }
            const art = this.articles[index];
            const titlePreview = art.title ? `"${art.title}"` : `Article #${index + 1}`;
            if (!confirm(`Are you sure you want to remove ${titlePreview} from this batch?`)) {
                return;
            }
            this.articles.splice(index, 1);
            this.syncLegacyFieldsFromArticle0();
            this.showToast('Article removed from batch.');
        },

        expandAllArticles() {
            this.articles.forEach(a => { a.isCollapsed = false; });
            this.$nextTick(() => {
                this.syncAllVisualCanvases();
            });
            this.showToast('All articles expanded for editing.');
        },

        collapseAllArticles() {
            this.articles.forEach(a => { a.isCollapsed = true; });
            this.showToast('All articles collapsed to compact view.');
        },

        syncLegacyFieldsFromArticle0() {
            if (!this.articles || this.articles.length === 0) return;
            const art = this.articles[0];
            this.editingPostId = art.id;
            this.postTitle = art.title;
            this.postSlug = art.slug;
            this.slugManuallyEdited = art.slugManuallyEdited;
            this.contentRaw = art.contentRaw;
            this.postStatus = art.status;
            this.scheduledAt = art.scheduled_at;
            this.authorName = art.author_name;
            this.postCategory = art.category;
            this.postSeries = art.playlist || 'None (Standalone Article)';
            this.postExcerpt = art.excerpt;
            this.featuredImageUrl = art.featured_image_url;
            this.featuredImageAlt = art.featured_image_alt;
            this.postVideoUrl = art.video_url;
            this.productSku = art.product_sku;
            this.productUrl = art.product_url;
            this.metaTitle = art.meta_title;
            this.metaDescription = art.meta_description;
            this.tagKeywords = art.tag_keywords || art.focus_keyword || '';
            this.focusKeyword = art.focus_keyword || art.tag_keywords || '';
            this.selectedWebsiteId = art.website_id;
            this.selectedWebsite = this.getWebsite(art.website_id);
            this.updateWebsiteProfile();
        },

        // ════════════════════════════════════════════════════════════════════════
        // PER-ARTICLE EVENT HANDLERS & HELPERS
        // ════════════════════════════════════════════════════════════════════════

        onArticleTitleInput(art) {
            if (!art.slugManuallyEdited && !art.id) {
                art.slug = this.slugify(art.title);
            }
            if (!art.meta_title) {
                art.meta_title = art.title;
            }
            if (this.articles[0] && this.articles[0].uid === art.uid) {
                this.postTitle = art.title;
                this.postSlug = art.slug;
            }
        },

        onArticleCodeInput(art) {
            if (!art.featured_image_url) {
                const first = this.extractFirstImage(art.contentRaw);
                if (first) {
                    art.featured_image_url = first;
                }
            }
            if (this.articles[0] && this.articles[0].uid === art.uid) {
                this.contentRaw = art.contentRaw;
            }
        },

        onArticleVisualInput(event, art) {
            art.contentRaw = event.target.innerHTML;
            if (this.articles[0] && this.articles[0].uid === art.uid) {
                this.contentRaw = art.contentRaw;
            }
        },

        handleArticleRawPaste(event, art) {
            const text = (event.clipboardData || window.clipboardData)?.getData('text') || '';
            const html = (event.clipboardData || window.clipboardData)?.getData('text/html') || '';
            if (!text && !html) return;

            const hasDraftMarkers = /(?:Meta\s*Title|SEO\s*Title|Blog\s*Title|Article\s*Title|Post\s*Title|H1\s*Title|Focus\s*Keywords?|Tag\s*Keywords?|Keywords?|Tags?|Meta\s*Description|SEO\s*Description)\s*[\*_\s]*[:：\-]/i.test(text) ||
                                   /^[\s\-*]*#{1,3}\s+/m.test(text) ||
                                   /<h[1-3][^>]*>/i.test(text) ||
                                   /(?:^|\n)\s*[\*_\s]*(?:Title|Description)\s*[\*_\s]*[:：\-]/i.test(text);
            const hasTable = (html && html.includes('<table')) || text.includes('\t') || (text.includes('|') && text.includes('\n')) || /(?:specification|specifications|features?|parameters?|dimensions?|specifications?\s*table|summary\s*comparison|comparison\s*table)/i.test(text);
            const isMultiLine = text.includes('\n');
            const isArticleEmpty = !art.title || !art.title.trim() || !art.contentRaw || art.contentRaw.trim().length < 50;

            if (hasDraftMarkers || isArticleEmpty || (hasTable && isMultiLine) || (isMultiLine && text.length > 80)) {
                event.preventDefault();
                this.processPastedDraftForArticle(text, art, html);
                this.syncVisualCanvasForArticle(art, true);
                if (this.articles[0] && this.articles[0].uid === art.uid) {
                    this.syncLegacyFieldsFromArticle0();
                }
                this.showToast('Article draft & tables auto-formatted with title, meta & content!');
            }
        },

        handleArticleVisualPaste(event, art) {
            const text = (event.clipboardData || window.clipboardData)?.getData('text') || '';
            const html = (event.clipboardData || window.clipboardData)?.getData('text/html') || '';
            if (!text && !html) return;

            const hasDraftMarkers = /(?:Meta\s*Title|SEO\s*Title|Blog\s*Title|Article\s*Title|Post\s*Title|H1\s*Title|Focus\s*Keywords?|Tag\s*Keywords?|Keywords?|Tags?|Meta\s*Description|SEO\s*Description)\s*[\*_\s]*[:：\-]/i.test(text) ||
                                   /^[\s\-*]*#{1,3}\s+/m.test(text) ||
                                   /<h[1-3][^>]*>/i.test(text) ||
                                   /(?:^|\n)\s*[\*_\s]*(?:Title|Description)\s*[\*_\s]*[:：\-]/i.test(text);
            const hasTable = (html && html.includes('<table')) || text.includes('\t') || (text.includes('|') && text.includes('\n')) || /(?:specification|specifications|features?|parameters?|dimensions?|specifications?\s*table|summary\s*comparison|comparison\s*table)/i.test(text);
            const isMultiLine = text.includes('\n');
            const isArticleEmpty = !art.title || !art.title.trim() || !art.contentRaw || art.contentRaw.trim().length < 50;

            if (hasDraftMarkers || isArticleEmpty || (hasTable && isMultiLine) || (isMultiLine && text.length > 80)) {
                event.preventDefault();
                this.processPastedDraftForArticle(text, art, html);
                this.syncVisualCanvasForArticle(art, true);
                if (this.articles[0] && this.articles[0].uid === art.uid) {
                    this.syncLegacyFieldsFromArticle0();
                }
                this.showToast('Pasted blog & tables auto-formatted with title, meta & visual content!');
            } else {
                // If user pasted inside existing content, check if pasted HTML has a table
                if (html && html.includes('<table')) {
                    event.preventDefault();
                    const mdTable = this.convertHtmlTableToMarkdown(html);
                    if (mdTable) {
                        document.execCommand('insertHTML', false, this.renderContentToHtml(mdTable));
                        setTimeout(() => {
                            const visualEl = document.getElementById('cms-visual-editor-' + art.uid) || document.getElementById('cms-visual-editor');
                            if (visualEl) {
                                art.contentRaw = visualEl.innerHTML;
                                if (this.articles[0] && this.articles[0].uid === art.uid) {
                                    this.contentRaw = art.contentRaw;
                                }
                            }
                        }, 50);
                        return;
                    }
                }
                setTimeout(() => {
                    const visualEl = document.getElementById('cms-visual-editor-' + art.uid) || document.getElementById('cms-visual-editor');
                    if (visualEl) {
                        art.contentRaw = visualEl.innerHTML;
                        if (this.articles[0] && this.articles[0].uid === art.uid) {
                            this.contentRaw = art.contentRaw;
                        }
                    }
                }, 50);
            }
        },

        setArticleEditorMode(mode, art) {
            art.editorMode = mode;
            if (mode === 'visual') {
                this.$nextTick(() => {
                    this.syncVisualCanvasForArticle(art, true);
                });
            } else if (mode === 'code') {
                this.$nextTick(() => {
                    const ta = document.getElementById('cms-main-textarea-' + art.uid) || document.getElementById('cms-main-textarea');
                    if (ta) ta.focus();
                });
            }
        },

        syncVisualCanvasForArticle(art, force = false) {
            const visualEl = document.getElementById('cms-visual-editor-' + art.uid) || 
                             (this.articles[0] && this.articles[0].uid === art.uid ? document.getElementById('cms-visual-editor') : null);
            if (visualEl) {
                if (force || document.activeElement !== visualEl) {
                    visualEl.innerHTML = this.renderContentToHtml(art.contentRaw);
                }
                this.$nextTick(() => {
                    this.setupVisualEditorImageInteractions();
                });
            }
        },

        syncAllVisualCanvases(force = false) {
            this.articles.forEach(art => {
                this.syncVisualCanvasForArticle(art, force);
            });
        },

        setupVisualEditorImageInteractions() {
            const editor = document.getElementById('cms-visual-editor');
            if (!editor || editor._hasImageListeners) return;
            editor._hasImageListeners = true;

            const handleTarget = (target) => {
                const img = target.tagName === 'IMG' ? target : target.querySelector('img');
                if (img) {
                    this.onImageHover(img);
                }
            };

            editor.addEventListener('mouseover', (e) => {
                const target = e.target;
                if (target.tagName === 'IMG' || target.closest('figure.cms-rendered-figure')) {
                    handleTarget(target);
                }
            });

            editor.addEventListener('click', (e) => {
                const target = e.target;
                if (target.tagName === 'IMG' || target.closest('figure.cms-rendered-figure')) {
                    handleTarget(target);
                }
            });

            editor.addEventListener('mouseleave', () => {
                this.scheduleHideImageToolbar();
            });

            editor.addEventListener('input', (e) => {
                if (e.target.tagName === 'FIGCAPTION') {
                    this.syncVisualCanvasToRaw();
                }
            });

            window.addEventListener('scroll', () => {
                if (this.imageToolbarOpen && this.activeImageEl) {
                    this.refreshImageOverlayPosition();
                }
            }, true);
            editor.addEventListener('scroll', () => {
                if (this.imageToolbarOpen && this.activeImageEl) {
                    this.refreshImageOverlayPosition();
                }
            });
        },

        onImageHover(img) {
            if (!img) return;
            let figure = img.closest('figure.cms-rendered-figure') || img.closest('figure');
            if (!figure) {
                figure = document.createElement('figure');
                figure.className = 'cms-rendered-figure my-6';
                figure.setAttribute('data-align', 'center');
                img.parentNode.insertBefore(figure, img);
                figure.appendChild(img);
                const caption = document.createElement('figcaption');
                caption.className = 'cms-rendered-caption text-center text-xs mt-2';
                caption.contentEditable = 'true';
                caption.setAttribute('data-placeholder', 'CAPTION: Write caption or attribution (e.g. Photo courtesy of TechLab)');
                caption.innerText = img.getAttribute('alt') || '';
                figure.appendChild(caption);
                this.syncVisualCanvasToRaw();
            }

            this.cancelHideImageToolbar();
            this.activeImageFigure = figure;
            this.activeImageEl = img;

            this.activeImageSrc = img.getAttribute('src') || '';
            this.activeImageAlt = img.getAttribute('alt') || '';
            this.activeImageTitle = img.getAttribute('title') || '';
            
            const linkEl = img.closest('a');
            this.activeImageLink = linkEl ? linkEl.getAttribute('href') : '';
            this.activeImageHasLink = Boolean(this.activeImageLink);
            this.activeImageLinkNewTab = linkEl ? linkEl.getAttribute('target') === '_blank' : true;

            const currentAlign = figure.getAttribute('data-align');
            if (currentAlign) {
                this.imageToolbarAlign = currentAlign;
            } else if (figure.style.float === 'left') {
                this.imageToolbarAlign = 'left';
            } else if (figure.style.float === 'right') {
                this.imageToolbarAlign = 'right';
            } else if (figure.style.width === '100%') {
                this.imageToolbarAlign = 'full';
            } else {
                this.imageToolbarAlign = 'center';
            }

            this.refreshImageOverlayPosition();
            this.imageToolbarOpen = true;
        },

        refreshImageOverlayPosition() {
            if (!this.activeImageEl) return;
            const wrapper = document.getElementById('cms-visual-editor-wrapper');
            if (!wrapper) return;
            const wrapperRect = wrapper.getBoundingClientRect();
            const imgRect = this.activeImageEl.getBoundingClientRect();

            this.imageOverlayRect = {
                top: imgRect.top - wrapperRect.top,
                left: imgRect.left - wrapperRect.left,
                width: imgRect.width,
                height: imgRect.height
            };
        },

        scheduleHideImageToolbar() {
            if (this.imagePopoverMode !== null) return;
            this.imageHideTimeout = setTimeout(() => {
                this.imageToolbarOpen = false;
            }, 350);
        },

        cancelHideImageToolbar() {
            if (this.imageHideTimeout) {
                clearTimeout(this.imageHideTimeout);
                this.imageHideTimeout = null;
            }
        },

        toggleImagePopover(mode) {
            this.imagePopoverMode = this.imagePopoverMode === mode ? null : mode;
        },

        applyImageAlt() {
            if (!this.activeImageEl) return;
            this.activeImageEl.setAttribute('alt', this.activeImageAlt || '');
            if (this.activeImageTitle) {
                this.activeImageEl.setAttribute('title', this.activeImageTitle);
            } else {
                this.activeImageEl.removeAttribute('title');
            }
            this.imagePopoverMode = null;
            this.syncVisualCanvasToRaw();
            this.showToast('Image Alt SEO updated!');
        },

        applyImageLink() {
            if (!this.activeImageEl) return;
            const url = (this.activeImageLink || '').trim();
            if (!url) {
                this.removeImageLink();
                return;
            }
            let linkEl = this.activeImageEl.closest('a');
            if (!linkEl) {
                linkEl = document.createElement('a');
                this.activeImageEl.parentNode.insertBefore(linkEl, this.activeImageEl);
                linkEl.appendChild(this.activeImageEl);
            }
            linkEl.setAttribute('href', url);
            if (this.activeImageLinkNewTab) {
                linkEl.setAttribute('target', '_blank');
                linkEl.setAttribute('rel', 'noopener noreferrer');
            } else {
                linkEl.removeAttribute('target');
                linkEl.removeAttribute('rel');
            }
            this.activeImageHasLink = true;
            this.imagePopoverMode = null;
            this.syncVisualCanvasToRaw();
            this.showToast('Image hyperlink applied!');
        },

        removeImageLink() {
            if (!this.activeImageEl) return;
            const linkEl = this.activeImageEl.closest('a');
            if (linkEl && linkEl.parentNode) {
                linkEl.parentNode.insertBefore(this.activeImageEl, linkEl);
                linkEl.parentNode.removeChild(linkEl);
            }
            this.activeImageLink = '';
            this.activeImageHasLink = false;
            this.imagePopoverMode = null;
            this.syncVisualCanvasToRaw();
            this.showToast('Image link removed!');
        },

        applyImageReplace() {
            if (!this.activeImageEl) return;
            const newSrc = (this.activeImageSrc || '').trim();
            if (!newSrc) return;
            this.activeImageEl.setAttribute('src', newSrc);
            this.imagePopoverMode = null;
            this.syncVisualCanvasToRaw();
            setTimeout(() => this.refreshImageOverlayPosition(), 50);
            this.showToast('Image replaced!');
        },

        setImageAlignment(align) {
            if (!this.activeImageFigure) return;
            this.imageToolbarAlign = align;
            this.activeImageFigure.setAttribute('data-align', align);

            if (align === 'left') {
                this.activeImageFigure.style.float = 'left';
                this.activeImageFigure.style.margin = '0.5rem 1.5rem 1rem 0';
                this.activeImageFigure.style.maxWidth = '50%';
                this.activeImageFigure.style.width = 'auto';
                this.activeImageFigure.style.textAlign = 'left';
            } else if (align === 'right') {
                this.activeImageFigure.style.float = 'right';
                this.activeImageFigure.style.margin = '0.5rem 0 1rem 1.5rem';
                this.activeImageFigure.style.maxWidth = '50%';
                this.activeImageFigure.style.width = 'auto';
                this.activeImageFigure.style.textAlign = 'right';
            } else if (align === 'full') {
                this.activeImageFigure.style.float = 'none';
                this.activeImageFigure.style.margin = '1.75rem 0';
                this.activeImageFigure.style.maxWidth = '100%';
                this.activeImageFigure.style.width = '100%';
                this.activeImageFigure.style.textAlign = 'center';
            } else { // center
                this.activeImageFigure.style.float = 'none';
                this.activeImageFigure.style.margin = '1.75rem auto';
                this.activeImageFigure.style.maxWidth = '100%';
                this.activeImageFigure.style.width = 'auto';
                this.activeImageFigure.style.textAlign = 'center';
            }

            this.syncVisualCanvasToRaw();
            setTimeout(() => this.refreshImageOverlayPosition(), 50);
            this.showToast(`Image aligned to ${align}!`);
        },

        moveImageUp() {
            if (!this.activeImageFigure) return;
            const prev = this.activeImageFigure.previousElementSibling;
            if (prev) {
                this.activeImageFigure.parentNode.insertBefore(this.activeImageFigure, prev);
                this.syncVisualCanvasToRaw();
                setTimeout(() => this.refreshImageOverlayPosition(), 50);
            }
        },

        moveImageDown() {
            if (!this.activeImageFigure) return;
            const next = this.activeImageFigure.nextElementSibling;
            if (next) {
                this.activeImageFigure.parentNode.insertBefore(next, this.activeImageFigure);
                this.syncVisualCanvasToRaw();
                setTimeout(() => this.refreshImageOverlayPosition(), 50);
            }
        },

        deleteActiveImage() {
            if (!this.activeImageFigure) return;
            if (confirm('Delete this image from the article?')) {
                this.activeImageFigure.remove();
                this.imageToolbarOpen = false;
                this.activeImageFigure = null;
                this.activeImageEl = null;
                this.syncVisualCanvasToRaw();
                this.showToast('Image deleted from article');
            }
        },

        syncVisualCanvasToRaw() {
            const visualEl = document.getElementById('cms-visual-editor');
            if (visualEl && this.articles[0]) {
                this.articles[0].contentRaw = visualEl.innerHTML;
                this.contentRaw = visualEl.innerHTML;
            }
        },

        insertArticleFormat(type, art) {
            const textarea = document.getElementById('cms-main-textarea-' + art.uid) || document.getElementById('cms-main-textarea');
            if (!textarea) return;
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const sel = textarea.value.substring(start, end) || 'text';
            let wrap = '';
            if (type === 'bold') wrap = `**${sel}**`;
            if (type === 'italic') wrap = `*${sel}*`;
            if (type === 'underline') wrap = `<u>${sel}</u>`;
            if (type === 'strikethrough') wrap = `~~${sel}~~`;
            if (type === 'code') wrap = `\`${sel}\``;
            if (type === 'quote') wrap = `\n> ${sel}\n`;
            if (type === 'bullet') wrap = `\n- ${sel}\n`;
            if (type === 'number') wrap = `\n1. ${sel}\n`;
            if (type === 'divider') wrap = `\n---\n`;

            textarea.value = textarea.value.substring(0, start) + wrap + textarea.value.substring(end);
            art.contentRaw = textarea.value;
            if (this.articles[0] && this.articles[0].uid === art.uid) {
                this.contentRaw = art.contentRaw;
            }
        },

        insertArticleHeading(level, art) {
            if (this.editorMode === 'visual') {
                const visualEl = document.getElementById('cms-visual-editor-' + art.uid) || document.getElementById('cms-visual-editor');
                if (visualEl) {
                    visualEl.focus();
                    document.execCommand('formatBlock', false, '<h' + level + '>');
                    const sel = window.getSelection();
                    if (sel && sel.anchorNode) {
                        let node = sel.anchorNode;
                        while (node && node !== visualEl) {
                            if (node.nodeName && node.nodeName.toLowerCase() === 'h' + level) {
                                const style = level === 1 ? this.H1_STYLE : (level === 2 ? this.H2_STYLE : (level === 3 ? this.H3_STYLE : this.H4_STYLE));
                                node.setAttribute('style', style);
                                break;
                            }
                            node = node.parentNode;
                        }
                    }
                    this.syncVisualCanvasToRaw();
                    return;
                }
            }
            const textarea = document.getElementById('cms-main-textarea-' + art.uid) || document.getElementById('cms-main-textarea');
            if (!textarea) return;
            const hashes = '#'.repeat(level) + ' ';
            const start = textarea.selectionStart;
            textarea.value = textarea.value.substring(0, start) + '\n\n' + hashes + 'Heading\n\n' + textarea.value.substring(start);
            art.contentRaw = textarea.value;
            if (this.articles[0] && this.articles[0].uid === art.uid) {
                this.contentRaw = art.contentRaw;
            }
            this.syncVisualCanvasForArticle(art, true);
        },

        promptInsertArticleImage(art) {
            const url = prompt('Enter image URL (WebP, PNG, JPG):');
            if (url && url.trim()) {
                const textarea = document.getElementById('cms-main-textarea-' + art.uid) || document.getElementById('cms-main-textarea');
                const start = textarea ? textarea.selectionStart : 0;
                art.contentRaw = art.contentRaw.substring(0, start) + `\n\n${url.trim()}\n\n` + art.contentRaw.substring(start);
                if (!art.featured_image_url) art.featured_image_url = url.trim();
                this.syncVisualCanvasForArticle(art, true);
            }
        },

        promptInsertArticleYoutube(art) {
            const url = prompt('Enter YouTube Video URL:');
            if (url && url.trim()) {
                const textarea = document.getElementById('cms-main-textarea-' + art.uid) || document.getElementById('cms-main-textarea');
                const start = textarea ? textarea.selectionStart : 0;
                art.contentRaw = art.contentRaw.substring(0, start) + `\n\n${url.trim()}\n\n` + art.contentRaw.substring(start);
                this.syncVisualCanvasForArticle(art, true);
            }
        },

        insertArticleSampleTable(art) {
            const table = `\n\n| Specification | TYPHON KUVUO 4.0 | TYPHON KUVUO 2.7 |\n| --- | --- | --- |\n| Operating Class | 4-Ton (~8,800 lbs) | 2.7-Ton (5,300 lbs) |\n| Engine | Kubota D1703 Diesel | Kubota D1105 Diesel |\n| Horsepower | 25 HP @ 2,200 RPM | 19 HP @ 2,200 RPM |\n\n`;
            const textarea = document.getElementById('cms-main-textarea-' + art.uid) || document.getElementById('cms-main-textarea');
            const start = textarea ? textarea.selectionStart : 0;
            art.contentRaw = art.contentRaw.substring(0, start) + table + art.contentRaw.substring(start);
            this.syncVisualCanvasForArticle(art, true);
        },

        autoDetectArticleCover(art) {
            const first = this.extractFirstImage(art.contentRaw);
            if (first) {
                art.featured_image_url = first;
                this.deduplicateLeadingImage(art, first);
                this.showToast('First image set as Featured Cover Image (duplicate removed from body)!');
            } else {
                alert('No image URL found in article content yet.');
            }
        },

        deduplicateLeadingImage(art, imgUrl) {
            if (!art || !art.contentRaw || !imgUrl) return;
            const file = imgUrl.split('/').pop().split('?')[0].toLowerCase();
            if (file.length <= 3) return;
            const leadImgRegex = new RegExp('^\\s*(?:#(?:[^\\n]+)\\n+)?(?:<(?:div|figure|p)[^>]*>\\s*)*(?:<img[^>]+src=["\']([^"\']+)["\'][^>]*>|!\\[[^\\]]*\\]\\((https?:\\/\\/[^\\s\\)]+)\\)|(https?:\\/\\/[^\\s<>]+\\.(?:webp|png|jpe?g|gif|avif)))(?:[\\s\\S]*?<\\/(?:figure|p|div)>)?(?:\\s*<figcaption[^>]*>.*?<\\/figcaption>)?', 'i');
            const m = art.contentRaw.match(leadImgRegex);
            if (m) {
                const matchedSrc = m[1] || m[2] || m[3] || '';
                const mFile = matchedSrc.split('/').pop().split('?')[0].toLowerCase();
                if (mFile === file || matchedSrc.includes(file) || imgUrl.includes(mFile)) {
                    const headingMatch = art.contentRaw.match(/^\s*(#[^\n]+\n+)/);
                    const headingPrefix = headingMatch ? headingMatch[1] : '';
                    art.contentRaw = headingPrefix + art.contentRaw.replace(leadImgRegex, '').trim();
                    this.syncVisualCanvasForArticle(art, true);
                    if (this.articles[0] && this.articles[0].uid === art.uid) {
                        this.syncLegacyFieldsFromArticle0();
                    }
                }
            }
        },

        handleArticleCoverUpload(event, art) {
            const file = event.target.files && event.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                art.featured_image_url = e.target.result;
                this.showToast('Cover photo loaded.');
            };
            reader.readAsDataURL(file);
        },

        promptArticleMediaLibrary(art) {
            const url = prompt('Enter media image URL from library:');
            if (url && url.trim()) {
                art.featured_image_url = url.trim();
            }
        },

        promptNewCategory(art) {
            const cat = prompt('Enter new category:');
            if (cat && cat.trim()) {
                const clean = cat.trim();
                const profile = this.getWebsiteProfile(art.website_id);
                if (profile) {
                    if (!profile.categories) profile.categories = [];
                    if (!profile.categories.includes(clean)) {
                        profile.categories.unshift(clean);
                    }
                }
                art.category = clean;
            }
        },

        promptCustomAuthor(art) {
            const name = prompt('Enter custom author name:');
            if (name && name.trim()) {
                const clean = name.trim();
                const profile = this.getWebsiteProfile(art.website_id);
                if (profile) {
                    if (!profile.authors) profile.authors = [];
                    if (!profile.authors.includes(clean)) {
                        profile.authors.unshift(clean);
                    }
                }
                art.author_name = clean;
            }
        },

        promptNewSeries(art) {
            const s = prompt('Enter topic series / playlist name:');
            if (s && s.trim()) {
                const clean = s.trim();
                const profile = this.getWebsiteProfile(art.website_id);
                if (profile) {
                    if (!profile.series) profile.series = [];
                    if (!profile.series.includes(clean)) {
                        profile.series.unshift(clean);
                    }
                }
                art.playlist = clean;
            }
        },

        getArticleWordCount(art) {
            const text = (art.contentRaw || '').replace(/<[^>]+>/g, ' ').trim();
            const words = text ? text.split(/\s+/).filter(Boolean).length : 0;
            const mins = Math.max(1, Math.round(words / 200));
            return words + ' words • ~' + mins + ' min read';
        },

        openSmartPasteForArticle(art) {
            this.smartPasteTargetArtUid = art.uid;
            this.smartPasteBuffer = '';
            this.smartPasteModalOpen = true;
        },

        applySmartPaste() {
            if (!this.smartPasteBuffer || !this.smartPasteBuffer.trim()) {
                alert('Please paste blog content first.');
                return;
            }
            const targetArt = this.articles.find(a => a.uid === this.smartPasteTargetArtUid) || (this.articles[0] || null);
            if (targetArt) {
                this.processPastedDraftForArticle(this.smartPasteBuffer, targetArt);
                this.syncVisualCanvasForArticle(targetArt, true);
                if (this.articles[0] && this.articles[0].uid === targetArt.uid) {
                    this.syncLegacyFieldsFromArticle0();
                }
            }
            this.smartPasteModalOpen = false;
            this.smartPasteBuffer = '';
            this.showToast('Article parsed successfully!');
        },

        processPastedDraftForArticle(text, art, htmlContent = '') {
            if (!text || !art) return;
            let raw = String(text).replace(/\r\n/g, '\n').replace(/\r/g, '\n').trim();
            if (!raw) return;

            // Automatically recognize and format tables (Google Docs rich HTML, tab-separated, sequential lines, etc.)
            raw = this.detectAndFormatTables(raw, htmlContent);

            let detectedMetaTitle = '';
            let detectedMetaDesc = '';
            let detectedKeywords = '';
            let explicitTitle = '';
            let detectedCover = '';
            let detectedVideo = '';

            const lines = raw.split('\n');

            const isMetaLabelLine = (line) => {
                const clean = line.replace(/<[^>]+>/g, '').replace(/^[\s\-*#>\d\.]+\s*/, '').trim().replace(/^\*+|\*+$/g, '').trim();
                return /^(?:Meta\s*Title|SEO\s*Title|Page\s*Title)\s*[\*_\s]*[:：\-]/i.test(clean) ||
                       /^(?:Meta\s*Description|SEO\s*Description|SEO\s*Desc|Description)\s*[\*_\s]*[:：\-]/i.test(clean) ||
                       /^(?:Tag\s*Keywords|Focus\s*Keywords?|Keywords?|Tags?)\s*[\*_\s]*[:：\-]/i.test(clean) ||
                       /^(?:Blog\s*Title|Article\s*Title|Post\s*Title|H1\s*Title|H1|Title)\s*[\*_\s]*[:：\-]/i.test(clean);
            };

            // Pass 1: Extract explicitly labelled metadata anywhere in the text
            for (let l of lines) {
                const clean = l.replace(/<[^>]+>/g, '').replace(/^[\s\-*#>\d\.]+\s*/, '').trim().replace(/^\*+|\*+$/g, '').trim();
                let m;
                if (!detectedMetaTitle && (m = clean.match(/^(?:Meta\s*Title|SEO\s*Title|Page\s*Title)\s*[\*_\s]*[:：\-]\s*[\*_\s]*(.+)/i))) {
                    detectedMetaTitle = m[1].replace(/[\*_\s]+$/, '').trim();
                } else if (!detectedMetaDesc && (m = clean.match(/^(?:Meta\s*Description|SEO\s*Description|SEO\s*Desc|Description)\s*[\*_\s]*[:：\-]\s*[\*_\s]*(.+)/i))) {
                    detectedMetaDesc = m[1].replace(/[\*_\s]+$/, '').trim();
                } else if (!detectedKeywords && (m = clean.match(/^(?:Tag\s*Keywords|Focus\s*Keywords?|Keywords?|Tags?)\s*[\*_\s]*[:：\-]\s*[\*_\s]*(.+)/i))) {
                    detectedKeywords = m[1].replace(/[\*_\s]+$/, '').trim();
                } else if (!explicitTitle && (m = clean.match(/^(?:Blog\s*Title|Article\s*Title|Post\s*Title|H1\s*Title|H1|Title)\s*[\*_\s]*[:：\-]\s*[\*_\s]*(.+)/i))) {
                    explicitTitle = m[1].replace(/[\*_\s]+$/, '').trim();
                }
            }

            // Pass 2: Detect HTML <h1> tag anywhere
            const htmlH1Match = raw.match(/<h1[^>]*>([\s\S]*?)<\/h1>/i);
            let htmlH1Title = '';
            if (htmlH1Match) {
                htmlH1Title = htmlH1Match[1].replace(/<[^>]+>/g, '').trim();
            }

            // Pass 3: Detect Cover Image URL anywhere in draft (works across ALL websites)
            const imgMatch = raw.match(/(https?:\/\/[^\s"'<>]+\.(?:webp|png|jpe?g|avif|gif)(?:\?[^\s"'<>]*)?)/i) ||
                             raw.match(/(https?:\/\/[^\s"'<>]*(?:img|images|uploads|media|storage|photos|wp-content)[^\s"'<>]*\.(?:webp|png|jpe?g|avif|gif))/i) ||
                             raw.match(/<img[^>]+src=["'](https?:\/\/[^"']+)["']/i) ||
                             raw.match(/!\[.*?\]\((https?:\/\/[^\s\)]+)\)/i);
            if (imgMatch) {
                detectedCover = imgMatch[1];
            }

            // Pass 4: Detect YouTube Video Link
            const ytMatch = raw.match(/(https?:\/\/(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)[\w\-]+)/i);
            if (ytMatch) {
                detectedVideo = ytMatch[1];
            }

            // Pass 5: Line-by-line filtering & Large Heading / Title Extraction
            let cleanedLines = [];
            let mainTitle = explicitTitle || htmlH1Title || '';
            let foundTitle = Boolean(mainTitle);
            let foundCover = Boolean(detectedCover);

            for (let i = 0; i < lines.length; i++) {
                const rawLine = lines[i];
                const line = rawLine.trim();

                if (!line) {
                    cleanedLines.push('');
                    continue;
                }
                if (/^[-*_]{3,}$/.test(line)) {
                    continue; // Skip horizontal divider rules
                }

                // If line is metadata label, strip it from content body
                if (isMetaLabelLine(line)) {
                    continue;
                }

                // If line matches the detected HTML H1 tag, strip it from content body
                if (htmlH1Title && /<h1[^>]*>.*?<\/h1>/i.test(line)) {
                    continue;
                }

                // If we don't have a title yet, detect the large heading / top title
                if (!foundTitle) {
                    // Check Markdown H1: # Title
                    if (/^#\s+([^#].*)$/.test(line)) {
                        mainTitle = line.replace(/^#\s+/, '').replace(/\*+/g, '').replace(/<[^>]+>/g, '').trim();
                        foundTitle = true;
                        continue;
                    }

                    // Check Markdown H2 on the very first text line: ## Title
                    if (/^##\s+([^#].*)$/.test(line) && cleanedLines.filter(l => l.trim()).length === 0) {
                        mainTitle = line.replace(/^##\s+/, '').replace(/\*+/g, '').replace(/<[^>]+>/g, '').trim();
                        foundTitle = true;
                        continue;
                    }

                    // Check Markdown bold title: **Title**
                    if (/^\*\*(.+?)\*\*$/.test(line) && line.length > 5 && line.length < 250) {
                        mainTitle = line.replace(/^\*\*|\*\*$/g, '').replace(/<[^>]+>/g, '').trim();
                        foundTitle = true;
                        continue;
                    }

                    // Check prominent text heading at top (below metadata, before body paragraphs)
                    const isUrl = line.startsWith('http://') || line.startsWith('https://');
                    const isImage = line.startsWith('<img') || line.startsWith('![');
                    const isBullet = line.startsWith('- ') || line.startsWith('* ') || /^\d+\.\s/.test(line);
                    const isTableLine = line.startsWith('|') || line.startsWith('<table') || line.startsWith('</table') || line.startsWith('<tr') || line.startsWith('<td') || line.startsWith('<th');

                    if (!isUrl && !isImage && !isBullet && !isTableLine && line.length >= 4 && line.length <= 250) {
                        mainTitle = line.replace(/^#+\s*/, '').replace(/^\*\*|\*\*$/g, '').replace(/<[^>]+>/g, '').trim();
                        foundTitle = true;
                        continue;
                    }
                }

                // If this line matches the main title exactly, don't duplicate it at the top of the body
                if (mainTitle) {
                    const cleanL = line.replace(/^#+\s*/, '').replace(/^\*\*|\*\*$/g, '').replace(/<[^>]+>/g, '').trim();
                    if (cleanL === mainTitle && cleanedLines.filter(l => l.trim()).length === 0) {
                        continue;
                    }
                }

                // Standalone cover image URL on its own line near the top
                if (!foundCover && (line.startsWith('http://') || line.startsWith('https://')) && /\.(webp|png|jpe?g|avif|gif)/i.test(line)) {
                    detectedCover = line.trim();
                    foundCover = true;
                    continue;
                }

                cleanedLines.push(rawLine);
            }

            // Fallback & English Grammar Auto-Cut: Meta Title (max 60)
            if (!detectedMetaTitle && mainTitle) {
                detectedMetaTitle = this.autoCutSeoGrammar(mainTitle, 60, true);
            } else if (detectedMetaTitle) {
                detectedMetaTitle = this.autoCutSeoGrammar(detectedMetaTitle, 60, true);
            }

            // Fallback & English Grammar Auto-Cut: Meta Description (max 160)
            if (!detectedMetaDesc) {
                const firstPara = cleanedLines.find(l => {
                    const trimmed = l.trim();
                    return trimmed.length > 25 && !trimmed.startsWith('#') && !trimmed.startsWith('http') && !trimmed.startsWith('![') && !trimmed.startsWith('|') && !trimmed.startsWith('<table');
                });
                if (firstPara) {
                    const cleanPara = firstPara.replace(/<[^>]+>/g, '').trim();
                    detectedMetaDesc = this.autoCutSeoGrammar(cleanPara, 160, false);
                }
            } else {
                detectedMetaDesc = this.autoCutSeoGrammar(detectedMetaDesc, 160, false);
            }

            // Assign Title & Slug
            if (mainTitle) {
                art.title = mainTitle;
                if (!art.slugManuallyEdited || !art.slug) {
                    art.slug = this.slugify(mainTitle);
                }
            }

            // Assign Meta Title
            if (detectedMetaTitle) {
                art.meta_title = detectedMetaTitle;
            }

            // Assign Meta Description & Excerpt
            if (detectedMetaDesc) {
                art.meta_description = detectedMetaDesc;
                if (!art.excerpt) {
                    art.excerpt = detectedMetaDesc.slice(0, 280);
                }
            }

            // Assign Tag Keywords / Focus Keyword
            if (detectedKeywords) {
                art.tag_keywords = detectedKeywords;
                art.focus_keyword = detectedKeywords;
            }

            // Assign Cover Image & Video
            if (detectedCover && !art.featured_image_url) {
                art.featured_image_url = detectedCover;
            }
            if (detectedVideo && !art.video_url) {
                art.video_url = detectedVideo;
            }

            // Assign Cleaned Content & Deduplicate First Image
            let finalContent = cleanedLines.join('\n').trim();
            const coverToDedupe = art.featured_image_url || detectedCover;
            if (coverToDedupe) {
                const dFile = coverToDedupe.split('/').pop().split('?')[0].toLowerCase();
                if (dFile.length > 3) {
                    const leadImgRegex = new RegExp('^\\s*(?:#(?:[^\\n]+)\\n+)?(?:<(?:div|figure|p)[^>]*>\\s*)*(?:<img[^>]+src=["\']([^"\']+)["\'][^>]*>|!\\[[^\\]]*\\]\\((https?:\\/\\/[^\\s\\)]+)\\)|(https?:\\/\\/[^\\s<>]+\\.(?:webp|png|jpe?g|gif|avif)))(?:[\\s\\S]*?<\\/(?:figure|p|div)>)?(?:\\s*<figcaption[^>]*>.*?<\\/figcaption>)?', 'i');
                    const m = finalContent.match(leadImgRegex);
                    if (m) {
                        const matchedSrc = m[1] || m[2] || m[3] || '';
                        const mFile = matchedSrc.split('/').pop().split('?')[0].toLowerCase();
                        if (mFile === dFile || matchedSrc.includes(dFile) || coverToDedupe.includes(mFile)) {
                            const headingMatch = finalContent.match(/^\s*(#[^\n]+\n+)/);
                            const headingPrefix = headingMatch ? headingMatch[1] : '';
                            finalContent = headingPrefix + finalContent.replace(leadImgRegex, '').trim();
                        }
                    }
                }
            }

            art.contentRaw = finalContent;

            // Ensure author & category profiles for website
            const profile = this.getWebsiteProfile(art.website_id);
            if (profile) {
                if (profile.categories && profile.categories.length > 0 && !art.category) {
                    art.category = profile.categories[0];
                }
                if (profile.default_author && !art.author_name) {
                    art.author_name = profile.default_author;
                }
            }

            // Synchronize legacy fields and visual canvas
            if (this.articles[0] && this.articles[0].uid === art.uid) {
                this.syncLegacyFieldsFromArticle0();
            }
            this.syncVisualCanvasForArticle(art, true);
        },

        // ════════════════════════════════════════════════════════════════════════
        // 1-CLICK MULTI-ARTICLE BATCH SUBMISSION
        // ════════════════════════════════════════════════════════════════════════

        saveAllArticles(statusToSave = 'published') {
            if (!this.articles || this.articles.length === 0) {
                alert('No articles to save. Click "+ Add More" to add an article.');
                return;
            }

            // Client-Side Validation across all articles
            for (let i = 0; i < this.articles.length; i++) {
                const art = this.articles[i];
                const artNum = i + 1;

                if (!art.website_id) {
                    art.isCollapsed = false;
                    this.$nextTick(() => {
                        const el = document.getElementById('article-block-' + art.uid);
                        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    });
                    alert(`Article #${artNum}: Please select a Target Website.`);
                    return;
                }

                if (!art.title || !art.title.trim()) {
                    art.isCollapsed = false;
                    this.$nextTick(() => {
                        const el = document.getElementById('article-block-' + art.uid);
                        if (el) {
                            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            const input = el.querySelector('.cms-raf-title-input');
                            if (input) input.focus();
                        }
                    });
                    alert(`Article #${artNum}: Please enter an article title.`);
                    return;
                }

                if (!art.contentRaw || !art.contentRaw.trim()) {
                    art.isCollapsed = false;
                    this.$nextTick(() => {
                        const el = document.getElementById('article-block-' + art.uid);
                        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    });
                    alert(`Article #${artNum} ("${art.title}"): Please write article content before saving.`);
                    return;
                }
            }

            this.isSaving = true;

            // Sync current single studio inputs to article 0
            if (this.articles[0]) {
                const a0 = this.articles[0];
                a0.title = this.postTitle;
                a0.slug = this.postSlug;
                a0.contentRaw = this.contentRaw;
                a0.status = statusToSave || this.postStatus;
                a0.scheduled_at = this.scheduledAt;
                a0.author_name = this.authorName;
                a0.category = this.postCategory;
                a0.playlist = this.postSeries;
                a0.excerpt = this.postExcerpt;
                a0.featured_image_url = this.featuredImageUrl;
                a0.featured_image_alt = this.featuredImageAlt;
                a0.video_url = this.postVideoUrl;
                a0.product_sku = this.productSku;
                a0.product_url = this.productUrl;
                a0.meta_title = this.metaTitle;
                a0.meta_description = this.metaDescription;
                a0.tag_keywords = this.tagKeywords;
                a0.focus_keyword = this.tagKeywords;
                a0.website_id = this.selectedWebsiteId;
            }

            const postsPayload = this.articles.map(art => ({
                post_id: art.id || null,
                website_id: art.website_id,
                title: (art.title || this.postTitle || '').trim(),
                slug: (art.slug || this.postSlug || this.slugify(art.title || this.postTitle || '')).trim(),
                content_html: this.renderContentToHtml(art.contentRaw || this.contentRaw),
                raw_content: art.contentRaw || this.contentRaw,
                status: statusToSave || art.status || this.postStatus || 'published',
                scheduled_at: (statusToSave === 'scheduled' || art.status === 'scheduled') ? (art.scheduled_at || this.scheduledAt) : null,
                author_name: art.author_name || this.authorName || (this.getWebsiteProfile(art.website_id)?.default_author || 'Editorial Team'),
                category: art.category || this.postCategory || 'General',
                playlist: (art.playlist && art.playlist !== 'None (Standalone Article)') ? art.playlist : null,
                featured_image_url: art.featured_image_url || this.featuredImageUrl || null,
                cover_alt: art.featured_image_alt || this.featuredImageAlt || null,
                excerpt: art.excerpt || this.postExcerpt || null,
                meta_title: this.autoCutSeoGrammar(art.meta_title || this.metaTitle || art.title || this.postTitle, 60, true),
                meta_description: this.autoCutSeoGrammar(art.meta_description || this.metaDescription || art.excerpt || this.postExcerpt || null, 160, false),
                tag_keywords: art.tag_keywords || this.tagKeywords || null,
                focus_keyword: art.focus_keyword || this.tagKeywords || null,
                video_url: art.video_url || this.postVideoUrl || null,
                product_sku: art.product_sku || this.productSku || null,
                product_url: art.product_url || this.productUrl || null,
            }));

            fetch('{{ route('websites.posts.batch') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ posts: postsPayload })
            })
            .then(res => res.json())
            .then(data => {
                this.isSaving = false;
                if (data.success) {
                    let firstLiveUrl = null;
                    let allSynced = true;
                    let syncIssues = [];
                    if (data.posts && Array.isArray(data.posts)) {
                        data.posts.forEach((savedP, idx) => {
                            if (this.articles[idx]) {
                                this.articles[idx].id = savedP.id;
                                this.articles[idx].status = savedP.status;
                                if (savedP.remote_url) {
                                    this.articles[idx].canonical_url = savedP.remote_url;
                                }
                            }
                            if (savedP.remote_synced === false) {
                                allSynced = false;
                                if (savedP.remote_message) {
                                    syncIssues.push(savedP.remote_message);
                                }
                            }
                            if (idx === 0 && savedP.remote_url) {
                                firstLiveUrl = savedP.remote_url;
                            }
                        });
                    }
                    if (data.latest_posts) {
                        this.latestPosts = data.latest_posts;
                    }
                    if (data.stats) {
                        this.stats = data.stats;
                    }
                    this.syncLegacyFieldsFromArticle0();
                    const count = data.posts ? data.posts.length : this.articles.length;
                    const actionLabel = (statusToSave === 'published' ? 'published live' : 'saved as draft');
                    if (allSynced || firstLiveUrl) {
                        this.showToast(data.message || `Success! ${count} article(s) ${actionLabel} in 1 click!`, firstLiveUrl, 'success');
                    } else {
                        const note = syncIssues.length > 0 ? syncIssues[0] : 'Saved in system.';
                        this.showToast(`Saved in Central Hub! Note: ${note}`, null, 'warning');
                    }

                    // Auto-close studio screen back to Blogs Post main hub on publish
                    const isPublished = (statusToSave === 'published' || this.normalizeStatus(this.postStatus) === 'published');
                    if (isPublished) {
                        setTimeout(() => {
                            this.backToListScreen();
                            this.latestStatusFilter = 'all';
                            this.latestSearchQuery = '';
                            this.fetchLatestPosts(false);
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        }, 700);
                    }
                } else {
                    alert('Error saving batch: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => {
                this.isSaving = false;
                console.error(err);
                alert('An error occurred during 1-click batch publishing.');
            });
        },

        saveBlogPost(overrideStatus) {
            this.saveAllArticles(overrideStatus);
        },

        // ════════════════════════════════════════════════════════════════════════
        // FLEET WEBSITES & TAXONOMY HELPERS
        // ════════════════════════════════════════════════════════════════════════

        getWebsite(siteId) {
            return this.websites.find(w => w.id == siteId) || null;
        },

        getWebsiteProfile(siteId) {
            return this.websiteProfiles[siteId] || null;
        },

        getWebsiteName(siteId) {
            const w = this.getWebsite(siteId);
            return w ? (w.name || w.clean_domain) : '';
        },

        getWebsiteDomain(siteId) {
            const w = this.getWebsite(siteId);
            if (!w) return '';
            if (w.clean_domain) return w.clean_domain;
            if (w.url) {
                return w.url.replace(/^https?:\/\//, '').replace(/\/.*$/, '');
            }
            return '';
        },

        getWebsiteCategory(siteId) {
            const w = this.getWebsite(siteId);
            return w ? (w.category || '') : '';
        },

        getWebsiteSlugPrefix(siteId) {
            const profile = this.getWebsiteProfile(siteId);
            if (profile && profile.slug_prefix) return profile.slug_prefix;
            const domain = this.getWebsiteDomain(siteId);
            return (domain || 'happymachinery.com') + '/blog/';
        },

        getAvailableAuthors(siteId) {
            const profile = this.getWebsiteProfile(siteId);
            if (profile && profile.authors && profile.authors.length) return profile.authors;
            if (profile && profile.default_author) return [profile.default_author];
            return ['Editorial Team'];
        },

        getAvailableCategories(siteId) {
            const profile = this.getWebsiteProfile(siteId);
            if (profile && profile.categories && profile.categories.length) return profile.categories;
            return ['General'];
        },

        getAvailableSeries(siteId) {
            const profile = this.getWebsiteProfile(siteId);
            if (profile && profile.series && profile.series.length) return profile.series;
            return ['None (Standalone Article)'];
        },

        filterWebsitesForArticle(art) {
            if (!art.websiteSearch || !art.websiteSearch.trim()) return this.websites;
            const q = art.websiteSearch.toLowerCase().trim();
            return this.websites.filter(w => 
                (w.name && w.name.toLowerCase().includes(q)) || 
                (w.url && w.url.toLowerCase().includes(q)) || 
                (w.clean_domain && w.clean_domain.toLowerCase().includes(q)) ||
                (w.category && w.category.toLowerCase().includes(q))
            );
        },

        setArticleWebsite(art, site) {
            if (!site) return;
            art.website_id = site.id;
            const profile = this.getWebsiteProfile(site.id);
            if (profile) {
                if (profile.default_author) {
                    art.author_name = profile.default_author;
                } else if (profile.authors && profile.authors.length) {
                    art.author_name = profile.authors[0];
                }
                if (profile.default_category) {
                    art.category = profile.default_category;
                } else if (profile.categories && profile.categories.length) {
                    art.category = profile.categories[0];
                }
                if (profile.series && profile.series.length) {
                    art.playlist = profile.series[0];
                }
                if (profile.store_url && !art.product_url) {
                    art.product_url = profile.store_url;
                }
            }
            if (this.articles[0] && this.articles[0].uid === art.uid) {
                this.selectedWebsiteId = site.id;
                this.selectedWebsite = site;
                this.updateWebsiteProfile();
            }
        },

        selectWebsiteFromDropdown(site) {
            this.selectedWebsiteId = site.id;
            this.selectedWebsite = site;
            this.updateWebsiteProfile();
            if (this.articles[0]) {
                this.setArticleWebsite(this.articles[0], site);
                this.syncLegacyFieldsFromArticle0();
            }
        },

        getClassBadgeColor(cat) {
            if (!cat) return 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700';
            const c = cat.toLowerCase();
            if (c.includes('1') || c.includes('first')) {
                return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800';
            }
            if (c.includes('2') || c.includes('second')) {
                return 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-800';
            }
            if (c.includes('3') || c.includes('third')) {
                return 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-800';
            }
            if (c.includes('4') || c.includes('fourth')) {
                return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800';
            }
            if (c.includes('5') || c.includes('fifth')) {
                return 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/40 dark:text-purple-300 dark:border-purple-800';
            }
            if (c.includes('6') || c.includes('sixth')) {
                return 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800';
            }
            if (c.includes('7') || c.includes('seventh')) {
                return 'bg-cyan-50 text-cyan-700 border-cyan-200 dark:bg-cyan-950/40 dark:text-cyan-300 dark:border-cyan-800';
            }
            if (c.includes('8') || c.includes('eighth')) {
                return 'bg-pink-50 text-pink-700 border-pink-200 dark:bg-pink-950/40 dark:text-pink-300 dark:border-pink-800';
            }
            if (c.includes('9') || c.includes('ninth')) {
                return 'bg-teal-50 text-teal-700 border-teal-200 dark:bg-teal-950/40 dark:text-teal-300 dark:border-teal-800';
            }
            return 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800';
        },

        filteredWebsites() {
            if (!this.websiteSearch || !this.websiteSearch.trim()) return this.websites;
            const q = this.websiteSearch.toLowerCase().trim();
            return this.websites.filter(w => 
                (w.name && w.name.toLowerCase().includes(q)) || 
                (w.url && w.url.toLowerCase().includes(q)) || 
                (w.clean_domain && w.clean_domain.toLowerCase().includes(q)) ||
                (w.category && w.category.toLowerCase().includes(q)) ||
                (w.handler_name && w.handler_name.toLowerCase().includes(q))
            );
        },

        // Status Normalization Helpers (Strictly 3 Clean Statuses)
        normalizeStatus(status) {
            if (!status) return 'draft';
            const s = status.toString().toLowerCase().trim();
            if (s === 'publish' || s === 'published' || s === 'public') return 'published';
            if (s === 'scheduled' || s === 'future') return 'scheduled';
            return 'draft';
        },

        statusLabel(status) {
            const s = this.normalizeStatus(status);
            if (s === 'published') return 'PUBLISHED';
            if (s === 'scheduled') return 'SCHEDULED';
            return 'DRAFT';
        },

        statusBadgeClass(status) {
            const s = this.normalizeStatus(status);
            if (s === 'published') return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800';
            if (s === 'scheduled') return 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/70 dark:text-indigo-300 border border-indigo-300 dark:border-indigo-800';
            return 'bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-300 border border-amber-300 dark:border-amber-800';
        },

        statusDotClass(status) {
            const s = this.normalizeStatus(status);
            if (s === 'published') return 'bg-emerald-500 animate-pulse';
            if (s === 'scheduled') return 'bg-indigo-500';
            return 'bg-amber-500';
        },

        selectedWebsiteFilterObj() {
            if (!this.latestWebsiteFilter || this.latestWebsiteFilter === 'all') return null;
            return this.websites.find(w => w.id == this.latestWebsiteFilter) || null;
        },

        filteredWebsitesList() {
            if (!this.siteFilterSearchQuery || !this.siteFilterSearchQuery.trim()) {
                return this.websites;
            }
            const q = this.siteFilterSearchQuery.toLowerCase().trim();
            return this.websites.filter(w => {
                const nameMatch = w.name && w.name.toLowerCase().includes(q);
                const domainMatch = w.clean_domain && w.clean_domain.toLowerCase().includes(q);
                const catMatch = w.category && w.category.toLowerCase().includes(q);
                const handlerMatch = w.handler_name && w.handler_name.toLowerCase().includes(q);
                return nameMatch || domainMatch || catMatch || handlerMatch;
            });
        },

        selectWebsiteFilter(siteId) {
            this.latestWebsiteFilter = siteId;
            this.siteFilterDropdownOpen = false;
            this.siteFilterSearchQuery = '';
            this.fetchLatestPosts();
        },

        filteredLatestPosts() {
            if (!this.latestPosts || !Array.isArray(this.latestPosts)) return [];
            let list = this.latestPosts;

            // Absolute Anti-Spam Security Shield: Never render any third-party casino, spam bot, or dummy test articles
            list = list.filter(p => {
                const t = (p.title || '').toLowerCase();
                const a = (p.author_name || '').toLowerCase();
                const img = (p.featured_image_url || '').toLowerCase();
                if (t.includes('casino') || t.includes('free spins') || t.includes('spinstar') || t.includes('slott') || t.includes('bettyspin') || t.includes('gambling')) return false;
                if (t.includes('universal heavy machinery') || t.includes('fleet integration verification') || t.includes('diagnostic fleet connectivity')) return false;
                if (img.includes('1579829366248')) return false;
                if (a === 'hansa hood' || a === 'me nas' || a === 'michael cerrick' || a === 'monita en' || a === 'john vicas' || a === 'fleet safety board') return false;
                return true;
            });

            // Status Filter
            if (this.latestStatusFilter !== 'all') {
                list = list.filter(p => this.normalizeStatus(p.status) === this.latestStatusFilter);
            }

            // Website Filter
            if (this.latestWebsiteFilter !== 'all') {
                list = list.filter(p => p.website_id == this.latestWebsiteFilter);
            }

            // Search Query Filter
            if (this.latestSearchQuery && this.latestSearchQuery.trim()) {
                const q = this.latestSearchQuery.toLowerCase().trim();
                list = list.filter(p => 
                    (p.title && p.title.toLowerCase().includes(q)) || 
                    (p.slug && p.slug.toLowerCase().includes(q)) || 
                    (p.website_name && p.website_name.toLowerCase().includes(q)) || 
                    (p.author_name && p.author_name.toLowerCase().includes(q)) ||
                    (p.category && p.category.toLowerCase().includes(q))
                );
            }

            if (this.feedLimit === 'all') {
                return list;
            }
            return list.slice(0, parseInt(this.feedLimit, 10));
        },

        setLatestStatusFilter(status) {
            this.latestStatusFilter = status;

            if (status !== 'all' && this.latestWebsiteFilter !== 'all') {
                const matchInCurrentSite = this.latestPosts.some(p => this.normalizeStatus(p.status) === status && p.website_id == this.latestWebsiteFilter);
                if (!matchInCurrentSite) {
                    this.latestWebsiteFilter = 'all';
                }
            }

            const matchingInMemory = this.latestPosts.filter(p => this.normalizeStatus(p.status) === status);
            if (status !== 'all' && matchingInMemory.length === 0) {
                this.fetchLatestPosts(false);
            }

            this.$nextTick(() => {
                const tableEl = document.querySelector('.cms-table-container');
                if (tableEl && window.scrollY < 200) {
                    tableEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });
        },

        changeFeedLimit(limit) {
            this.feedLimit = limit;
            if ((limit === 'all' || parseInt(limit, 10) > this.latestPosts.length) && this.latestPosts.length < (this.stats.total || 500)) {
                this.fetchLatestPosts(true);
            }
        },

        fetchLatestPosts(silent = false) {
            if (!silent) {
                this.isRefreshingLatest = true;
            }
            const params = new URLSearchParams();
            params.set('limit', this.feedLimit === 'all' ? 'all' : '500');
            if (this.latestStatusFilter && this.latestStatusFilter !== 'all') {
                params.set('status', this.latestStatusFilter);
            }
            if (this.latestWebsiteFilter && this.latestWebsiteFilter !== 'all') {
                params.set('website_id', this.latestWebsiteFilter);
            }
            if (this.latestSearchQuery && this.latestSearchQuery.trim()) {
                params.set('search', this.latestSearchQuery.trim());
            }

            fetch(`{{ route('websites.posts.latest10') }}?${params.toString()}`)
            .then(res => res.json())
            .then(data => {
                this.isRefreshingLatest = false;
                if (data.success && data.posts) {
                    if (this.latestStatusFilter !== 'all') {
                        // Merge retrieved posts into latestPosts so we keep both
                        const map = new Map();
                        data.posts.forEach(p => map.set(p.id, p));
                        this.latestPosts.forEach(p => {
                            if (!map.has(p.id)) {
                                map.set(p.id, p);
                            }
                        });
                        this.latestPosts = Array.from(map.values());
                    } else {
                        this.latestPosts = data.posts;
                    }
                }
            })
            .catch(() => { this.isRefreshingLatest = false; });
        },

        fetchLatest10Posts() {
            this.fetchLatestPosts();
        },

        togglePreviewTab() {
            if (this.editorMode === 'preview') {
                this.setEditorMode('visual');
            } else {
                this.setEditorMode('preview');
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        },

        async openPreviewInNewTab() {
            this.syncArticle0FromLegacyFields();
            const art = this.articles[0] || {};

            const title = (this.postTitle || art.title || 'Untitled Article').trim();
            const slug = (this.postSlug || art.slug || this.slugify(title)).trim();
            const contentRaw = this.contentRaw || art.contentRaw || '';
            const contentHtml = this.renderContentToHtml(contentRaw);
            const websiteId = this.selectedWebsiteId || art.websiteId || '';
            const category = this.postCategory || art.category || 'General';
            const author = this.postAuthorName || art.author || (this.selectedWebsite ? (this.selectedWebsite.name || this.selectedWebsite.clean_domain) : 'DGT Media');
            const featuredImage = this.featuredImageUrl || art.featuredImageUrl || '';
            const metaDescription = this.metaDescription || art.metaDescription || this.postExcerpt || '';
            const metaTitle = this.metaTitle || art.metaTitle || title;
            const tags = this.tagKeywords || art.tagKeywords || '';

            // Open window synchronously to avoid popup blockers
            const previewWin = window.open('about:blank', '_blank');
            if (previewWin) {
                previewWin.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head><title>Loading 10-Minute Blog Preview...</title></head>
                    <body style="margin:0;height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;background:#0b0f17;color:#f8fafc;font-family:system-ui,-apple-system,sans-serif;">
                        <div style="text-align:center;padding:20px;max-width:400px;">
                            <div style="font-size:36px;margin-bottom:12px;">⏳</div>
                            <h3 style="margin:0 0 8px 0;font-size:18px;font-weight:700;">Generating 10-Minute Preview</h3>
                            <p style="margin:0;font-size:13px;color:#94a3b8;">Creating secure temporary preview link...</p>
                        </div>
                    </body>
                    </html>
                `);
            }

            try {
                const response = await fetch('{{ route("websites.posts.preview-token") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        website_id: websiteId,
                        title: title,
                        slug: slug,
                        content_html: contentHtml,
                        raw_content: contentRaw,
                        featured_image_url: featuredImage,
                        category: category,
                        author_name: author,
                        meta_title: metaTitle,
                        meta_description: metaDescription,
                        tag_keywords: tags,
                    })
                });

                const data = await response.json();
                if (data.success && data.preview_url) {
                    if (previewWin && !previewWin.closed) {
                        previewWin.location.href = data.preview_url;
                    } else {
                        window.open(data.preview_url, '_blank');
                    }
                    if (this.showToast) {
                        this.showToast('10-Minute preview link generated successfully!', 'success');
                    }
                } else {
                    if (previewWin && !previewWin.closed) previewWin.close();
                    alert(data.message || 'Could not generate preview link.');
                }
            } catch (err) {
                console.error('Preview token error:', err);
                if (previewWin && !previewWin.closed) previewWin.close();
                alert('Failed to generate preview: ' + err.message);
            }
        },

        getFormattedPreviewDate() {
            const d = new Date();
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        },

        calculateReadingTimeText(text) {
            if (!text || typeof text !== 'string') return '~1 min read • 0 words';
            const clean = text.replace(/<[^>]+>/g, ' ').trim();
            const words = clean ? clean.split(/\s+/).filter(Boolean).length : 0;
            const mins = Math.max(1, Math.ceil(words / 200));
            return `~${mins} min read • ${words} words`;
        },

        renderPreviewContentHtml(raw) {
            let html = this.renderContentToHtml(raw || this.contentRaw || '');
            if (this.featuredImageUrl && html) {
                const fFile = this.featuredImageUrl.split('/').pop().split('?')[0].toLowerCase();
                if (fFile.length > 3) {
                    const leadImgRegex = new RegExp('^\\s*(?:<p>(?:&nbsp;|\\s|<br\\s*\\/?>)*<\\/p>\\s*)*(?:<div[^>]*>\\s*)*(?:<figure[^>]*>|<p[^>]*>|<div[^>]*>)?\\s*(?:<a[^>]*>\\s*)?<img[^>]+src=["\']([^"\']+)["\'][^>]*>(?:\\s*<\\/a>)?\\s*(?:<figcaption[^>]*>.*?<\\/figcaption>)?\\s*(?:<\\/figure>|<\\/p>|<\\/div>)?(?:\\s*<\\/div>)?', 'is');
                    const m = html.match(leadImgRegex);
                    if (m) {
                        const matchedSrc = m[1] || '';
                        const mFile = matchedSrc.split('/').pop().split('?')[0].toLowerCase();
                        if (mFile === fFile || matchedSrc.includes(fFile) || this.featuredImageUrl.includes(mFile)) {
                            html = html.replace(leadImgRegex, '').trim();
                        }
                    }
                }
            }
            return html;
        },

        openAddMoreModal() {
            this.addMoreSearch = '';
            this.addMoreModalOpen = true;
        },

        filteredAddMoreWebsites() {
            if (!this.websites || !Array.isArray(this.websites)) return [];
            if (!this.addMoreSearch || !this.addMoreSearch.trim()) return this.websites;
            const q = this.addMoreSearch.toLowerCase().trim();
            return this.websites.filter(s => 
                (s.name && s.name.toLowerCase().includes(q)) ||
                (s.clean_domain && s.clean_domain.toLowerCase().includes(q)) ||
                (s.category && s.category.toLowerCase().includes(q))
            );
        },

        selectWebsiteForNewPost(site) {
            if (!site) return;
            this.selectedWebsiteId = site.id;
            this.selectedWebsite = site;
            this.updateWebsiteProfile();

            this.startNewPost();
            this.addMoreModalOpen = false;

            this.$nextTick(() => {
                const titleInput = document.querySelector('.cms-raf-title-input');
                if (titleInput) {
                    titleInput.focus();
                }
            });

            this.showToast(`Ready! New article started targeting ${site.clean_domain || site.name}.`);
        },

        updateRealtimeClock() {
            const d = new Date();
            const days = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];
            const months = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
            const dayName = days[d.getDay()];
            const monthName = months[d.getMonth()];
            const dateNum = d.getDate();
            const year = d.getFullYear();
            let hours = d.getHours();
            const minutes = String(d.getMinutes()).padStart(2, '0');
            const seconds = String(d.getSeconds()).padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            this.realtimeClockString = `${dayName}, ${monthName} ${dateNum}, ${year} | ${hours}:${minutes}:${seconds} ${ampm}`;
        },

        getDefaultStarterContent() {
            const siteName = (this.selectedWebsite && this.selectedWebsite.name) ? this.selectedWebsite.name : 'our integrated fleet';
            return `Write your captivating introductory paragraph here. Introduce the core topic, primary challenges, or equipment capabilities, and highlight the practical takeaways readers will discover in this article.\n\n## Key Operational Overview & Specifications\nDetail the main mechanical specifications, operational advantages, and performance parameters here. Explain how these capabilities streamline workflows and enhance job-site productivity.\n\n## Best Practices & Troubleshooting\nProvide step-by-step operational advice, regular maintenance tips, and practical recommendations for site operators to prevent downtime and ensure safety.\n\n## Summary & Key Takeaways\nConclude with a concise recap of essential points and recommend next steps or equipment solutions available through ${siteName}.`;
        },

        getStarterContentForSite(siteId) {
            const site = this.getWebsite(siteId);
            const siteName = site ? (site.name || site.clean_domain) : 'our integrated fleet';
            return `Write your captivating introductory paragraph here. Introduce the core topic, primary challenges, or equipment capabilities, and highlight the practical takeaways readers will discover in this article.\n\n## Key Operational Overview & Specifications\nDetail the main mechanical specifications, operational advantages, and performance parameters here. Explain how these capabilities streamline workflows and enhance job-site productivity.\n\n## Best Practices & Troubleshooting\nProvide step-by-step operational advice, regular maintenance tips, and practical recommendations for site operators to prevent downtime and ensure safety.\n\n## Summary & Key Takeaways\nConclude with a concise recap of essential points and recommend next steps or equipment solutions available through ${siteName}.`;
        },

        startNewPost() {
            const firstArt = this.createArticleObject(this.selectedWebsiteId, {
                title: '',
                slug: '',
                contentRaw: '',
                meta_title: '',
                meta_description: '',
                tag_keywords: '',
                focus_keyword: '',
                featured_image_url: '',
                featured_image_alt: '',
                video_url: '',
                excerpt: '',
                product_sku: '',
                product_url: ''
            });
            this.articles = [ firstArt ];
            this.syncLegacyFieldsFromArticle0();

            // Explicitly clear legacy state properties
            this.postTitle = '';
            this.postSlug = '';
            this.slugManuallyEdited = false;
            this.contentRaw = '';
            this.editingPostId = null;
            this.metaTitle = '';
            this.metaDescription = '';
            this.tagKeywords = '';
            this.focusKeyword = '';
            this.postExcerpt = '';
            this.featuredImageUrl = '';
            this.featuredImageAlt = '';
            this.postVideoUrl = '';
            this.productSku = '';
            this.productUrl = '';

            this.activeViewTab = 'editor';
            this.editorMode = 'visual';
            this.activeSidebarTab = 'all';
            this.currentScreen = 'editor';
            this.mobileStudioTab = 'content';

            // Clean DOM visual canvas and textarea immediately
            const visualEl = document.getElementById('cms-visual-editor');
            if (visualEl) {
                visualEl.innerHTML = '';
            }
            const rawTa = document.getElementById('cms-main-textarea');
            if (rawTa) {
                rawTa.value = '';
            }

            this.$nextTick(() => {
                this.syncAllVisualCanvases(true);
                const vEl = document.getElementById('cms-visual-editor');
                if (vEl) {
                    vEl.innerHTML = '';
                }
            });

            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        backToListScreen() {
            this.currentScreen = 'list';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        editPost(postId) {
            this.activeSidebarTab = 'publish';
            this.loadPostIntoEditor(postId);
            this.currentScreen = 'editor';
            this.mobileStudioTab = 'content';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        deletePost(postId, postTitle) {
            if (!confirm(`Are you sure you want to permanently delete article: "${postTitle}"?`)) {
                return;
            }
            fetch(`/websites/posts/${postId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (data.latest_posts) {
                        this.latestPosts = data.latest_posts;
                    } else {
                        this.latestPosts = this.latestPosts.filter(p => p.id != postId);
                    }
                    if (data.stats) {
                        this.stats = data.stats;
                    } else if (this.stats && this.stats.total > 0) {
                        this.stats.total--;
                    }
                    this.showToast(data.message || 'Article deleted successfully.');
                } else {
                    alert('Error deleting post: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('An error occurred while deleting the article.');
            });
        },

        init() {
            // Setup real-time clock
            this.updateRealtimeClock();
            setInterval(() => this.updateRealtimeClock(), 1000);

            // Pick initial website
            if (this.selectedWebsiteId) {
                this.selectedWebsite = this.websites.find(w => w.id == this.selectedWebsiteId) || this.websites[0];
            } else if (this.websites.length > 0) {
                this.selectedWebsite = this.websites[0];
                this.selectedWebsiteId = this.selectedWebsite.id;
            }

            this.updateWebsiteProfile();

            // Initialize article repeater with 1 clean starter article
            this.articles = [ this.createArticleObject(this.selectedWebsiteId) ];
            this.syncLegacyFieldsFromArticle0();

            // Determine initial screen based on URL query parameters
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('new') === '1' || urlParams.get('action') === 'new') {
                this.startNewPost();
            } else if (urlParams.get('edit')) {
                this.editPost(urlParams.get('edit'));
            } else {
                this.currentScreen = 'list';
            }

            // Sync visual canvas when first article content changes
            this.$watch('contentRaw', (val) => {
                if (this.articles[0] && this.articles[0].contentRaw !== val) {
                    this.articles[0].contentRaw = val;
                }
                const visualEl = document.getElementById('cms-visual-editor');
                if (visualEl && document.activeElement !== visualEl) {
                    visualEl.innerHTML = this.renderContentToHtml(val);
                    this.$nextTick(() => { this.setupVisualEditorImageInteractions(); });
                }
            });

            this.$nextTick(() => {
                this.setupVisualEditorImageInteractions();
            });
        },

        onWebsiteChange(newId) {
            this.selectedWebsiteId = parseInt(newId);
            this.selectedWebsite = this.websites.find(w => w.id == this.selectedWebsiteId) || null;
            this.updateWebsiteProfile();
            if (this.articles[0] && this.selectedWebsite) {
                this.setArticleWebsite(this.articles[0], this.selectedWebsite);
                this.syncLegacyFieldsFromArticle0();
            }
        },

        updateWebsiteProfile() {
            if (!this.selectedWebsiteId) return;
            const profile = this.websiteProfiles[this.selectedWebsiteId] || null;
            this.currentWebsiteProfile = profile;

            if (profile) {
                const siteAuthor = profile.default_author || (profile.authors && profile.authors[0]) || 'Editorial Team';
                this.availableAuthors = [siteAuthor];
                this.authorName = siteAuthor;

                this.availableCategories = profile.categories || ['General'];
                if (!this.postCategory || !this.availableCategories.includes(this.postCategory)) {
                    this.postCategory = this.availableCategories[0] || 'General';
                }

                this.availableSeries = profile.series || ['None (Standalone Article)'];
                if (!this.postSeries || !this.availableSeries.includes(this.postSeries)) {
                    this.postSeries = this.availableSeries[0] || 'None (Standalone Article)';
                }

                if (profile.store_url) {
                    this.productUrl = profile.store_url;
                }
            }
        },

        handleTitlePaste(event) {
            const text = (event.clipboardData || window.clipboardData)?.getData('text') || '';
            const html = (event.clipboardData || window.clipboardData)?.getData('text/html') || '';
            if (text && (text.includes('\n') || text.length > 120)) {
                event.preventDefault();
                const targetArt = this.articles[0];
                if (targetArt) {
                    this.processPastedDraftForArticle(text, targetArt, html);
                    this.syncVisualCanvasForArticle(targetArt, true);
                    this.syncLegacyFieldsFromArticle0();
                    this.showToast('Article draft & tables auto-formatted with title, meta & content!');
                }
            }
        },

        onTitleInput() {
            if (this.articles[0]) {
                this.articles[0].title = this.postTitle;
                this.onArticleTitleInput(this.articles[0]);
            }
        },

        onCodeInput() {
            if (this.articles[0]) {
                this.onArticleCodeInput(this.articles[0]);
            }
        },

        onVisualInput(event) {
            if (this.articles[0]) {
                this.onArticleVisualInput(event, this.articles[0]);
            }
        },

        slugify(text) {
            return (text || '').toString().toLowerCase()
                .trim()
                .replace(/\s+/g, '-')
                .replace(/[^\w\-]+/g, '')
                .replace(/\-\–+/g, '-')
                .replace(/^-+/, '')
                .replace(/-+$/, '');
        },

        computeCanonicalUrl() {
            const prefix = this.currentWebsiteProfile?.slug_prefix || (this.selectedWebsite?.url ? (this.selectedWebsite.url.replace(/\/$/, '') + '/blog/') : 'happymachinery.com/blog/');
            return 'https://' + prefix.replace(/^https?:\/\//, '') + (this.postSlug || 'your-article-slug');
        },

        computeEmbedVideoUrl(url) {
            if (!url) return '';
            let videoId = '';
            const ytMatch = url.match(/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([\w-]{11})/i);
            if (ytMatch) {
                videoId = ytMatch[1];
                return 'https://www.youtube.com/embed/' + videoId;
            }
            return url;
        },

        wordCountText() {
            const text = (this.contentRaw || '').replace(/<[^>]+>/g, ' ').trim();
            const words = text ? text.split(/\s+/).filter(Boolean).length : 0;
            const mins = Math.max(1, Math.round(words / 200));
            return words + ' words • ~' + mins + ' min read';
        },

        extractFirstImage(text) {
            const match = (text || '').match(/https?:\/\/[^\s"'<>]+\.(?:webp|png|jpe?g|gif)/i);
            return match ? match[0] : null;
        },

        autoDetectCoverImage() {
            if (this.articles[0]) {
                this.autoDetectArticleCover(this.articles[0]);
            }
        },

        autoDetectSeoMeta() {
            const text = this.contentRaw || '';
            const tMatch = text.match(/(?:meta\s*title|seo\s*title)\s*:\s*([^\n\r<]+)/i);
            if (tMatch) this.metaTitle = this.autoCutSeoGrammar(tMatch[1].trim(), 60, true);

            const dMatch = text.match(/(?:meta\s*description|seo\s*description)\s*:\s*([^\n\r<]+)/i);
            if (dMatch) this.metaDescription = this.autoCutSeoGrammar(dMatch[1].trim(), 160, false);

            if (tMatch || dMatch) {
                this.showToast('Meta title & description auto-detected and trimmed to SEO grammar standard!');
            } else {
                if (this.postTitle && !this.metaTitle) this.metaTitle = this.autoCutSeoGrammar(this.postTitle, 60, true);
                if (this.postExcerpt && !this.metaDescription) this.metaDescription = this.autoCutSeoGrammar(this.postExcerpt, 160, false);
                this.showToast('SEO Meta filled from article title & excerpt with English grammar rules.');
            }

            if (this.articles && this.articles[0]) {
                this.articles[0].meta_title = this.metaTitle;
                this.articles[0].meta_description = this.metaDescription;
            }
        },

        autoCutSeoGrammar(text, maxLen = 160, isTitle = false) {
            if (!text || typeof text !== 'string') return '';
            let clean = text.replace(/<[^>]+>/g, ' ').replace(/[\r\n\t]+/g, ' ').replace(/\s{2,}/g, ' ').trim();
            if (!clean) return '';

            const danglingWords = new Set([
                'and', 'or', 'but', 'nor', 'so', 'yet', 'for',
                'the', 'a', 'an',
                'of', 'in', 'to', 'with', 'on', 'at', 'from', 'by', 'about', 'as', 'into', 'through', 'after', 'over', 'between', 'under', 'above', 'against', 'among', 'off', 'out', 'without', 'within', 'upon',
                'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'shall', 'should', 'can', 'could', 'may', 'might', 'must',
                'that', 'which', 'who', 'whom', 'whose', 'where', 'when', 'why', 'how',
                'this', 'these', 'those', 'your', 'our', 'their', 'my', 'its', 'his', 'her',
                'because', 'although', 'since', 'unless', 'while', 'whereas'
            ]);

            const trimDanglingEnd = (str) => {
                let changed = true;
                let s = str;
                while (changed) {
                    changed = false;
                    s = s.replace(/[\s,;:–—\-\/\\&|+~'"()\[\]{}]+$/g, '').trim();
                    const words = s.split(/\s+/);
                    if (words.length > 1) {
                        const lastWord = words[words.length - 1].toLowerCase().replace(/[^a-z0-9]/g, '');
                        if (danglingWords.has(lastWord)) {
                            words.pop();
                            s = words.join(' ').trim();
                            changed = true;
                        }
                    }
                }
                return s;
            };

            // If already within limit:
            if (clean.length <= maxLen) {
                if (isTitle) {
                    return clean.replace(/[\s,;:–—\-\/\\&|+~'"()\[\]{}.!]+$/g, '').trim();
                }
                return clean;
            }

            // Exceeds maxLen: Check if a natural complete sentence boundary exists within 70% to 100% of maxLen
            const minSentenceCut = Math.floor(maxLen * 0.70);
            const subForSentence = clean.substring(0, maxLen);
            const sentenceEndMatches = [...subForSentence.matchAll(/[.!?](?=\s|$)/g)];
            if (sentenceEndMatches.length > 0) {
                const lastSentenceEnd = sentenceEndMatches[sentenceEndMatches.length - 1];
                const endPos = lastSentenceEnd.index + 1;
                if (endPos >= minSentenceCut && endPos <= maxLen) {
                    return clean.substring(0, endPos).trim();
                }
            }

            const budget = isTitle ? maxLen : (maxLen - 1);
            let cut = clean.substring(0, budget);

            const lastSpace = cut.lastIndexOf(' ');
            if (lastSpace > 15) {
                cut = cut.substring(0, lastSpace);
            }

            cut = trimDanglingEnd(cut);
            if (!cut) return '';

            if (!isTitle) {
                if (!/[.!?]$/.test(cut)) {
                    cut += '.';
                }
            } else {
                cut = cut.replace(/[\s,;:–—\-\/\\&|+~'"()\[\]{}.!]+$/g, '').trim();
            }

            return cut;
        },

        customAuthorPrompt() {
            if (this.articles[0]) {
                this.promptCustomAuthor(this.articles[0]);
            }
        },

        customCategoryPrompt() {
            if (this.articles[0]) {
                this.promptNewCategory(this.articles[0]);
            }
        },

        customSeriesPrompt() {
            if (this.articles[0]) {
                this.promptNewSeries(this.articles[0]);
            }
        },

        openMediaLibraryModal() {
            if (this.articles[0]) {
                this.promptArticleMediaLibrary(this.articles[0]);
            }
        },

        handleCoverFileUpload(event) {
            if (this.articles[0]) {
                this.handleArticleCoverUpload(event, this.articles[0]);
            }
        },

        handleCoverFileDrop(event) {
            const file = event.dataTransfer.files && event.dataTransfer.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                if (this.articles[0]) {
                    this.articles[0].featured_image_url = e.target.result;
                }
                this.featuredImageUrl = e.target.result;
                this.showToast('Cover photo dropped.');
            };
            reader.readAsDataURL(file);
        },

        setEditorMode(mode) {
            this.editorMode = mode;
            if (this.articles && this.articles[0]) {
                this.articles[0].editorMode = mode;
            }
            if (mode === 'preview') {
                this.activeViewTab = 'preview';
                const visualEl = document.getElementById('cms-visual-editor');
                if (visualEl && visualEl.innerHTML.trim()) {
                    this.contentRaw = visualEl.innerHTML;
                    if (this.articles[0]) {
                        this.articles[0].contentRaw = this.contentRaw;
                    }
                }
            } else {
                this.activeViewTab = 'editor';
                if (this.articles && this.articles[0]) {
                    this.setArticleEditorMode(mode, this.articles[0]);
                }
                if (mode === 'visual') {
                    this.$nextTick(() => {
                        this.setupVisualEditorImageInteractions();
                    });
                }
            }
        },

        syncVisualCanvasFromRaw(force = false) {
            this.syncAllVisualCanvases(force);
        },

        handleRawPaste(event) {
            if (this.articles[0]) {
                this.handleArticleRawPaste(event, this.articles[0]);
            }
        },

        handleVisualPaste(event) {
            if (this.articles[0]) {
                this.handleArticleVisualPaste(event, this.articles[0]);
            }
        },

        processPastedDraft(text) {
            if (this.articles[0]) {
                this.processPastedDraftForArticle(text, this.articles[0]);
                this.syncLegacyFieldsFromArticle0();
            }
        },

        escapeRegex(string) {
            return (string || '').replace(/[/\-\\^$*+?.()|[\]{}]/g, '\\$&');
        },

        convertHtmlTableToMarkdown(tableHtml) {
            if (!tableHtml || !tableHtml.includes('<table')) return null;
            const trMatches = tableHtml.match(/<tr[^>]*>[\s\S]*?<\/tr>/gi);
            if (!trMatches || trMatches.length === 0) return null;

            let rows = [];
            for (let tr of trMatches) {
                const cellMatches = tr.match(/<(?:th|td)[^>]*>([\s\S]*?)<\/(?:th|td)>/gi);
                if (!cellMatches || cellMatches.length === 0) continue;
                
                let cells = cellMatches.map(c => {
                    return c.replace(/<[^>]+>/g, '')
                            .replace(/&nbsp;/g, ' ')
                            .replace(/&amp;/g, '&')
                            .replace(/&lt;/g, '<')
                            .replace(/&gt;/g, '>')
                            .replace(/&quot;/g, '"')
                            .replace(/\r?\n/g, ' ')
                            .trim();
                });
                rows.push(cells);
            }

            if (rows.length < 1) return null;
            const colCount = Math.max(...rows.map(r => r.length));
            if (colCount < 1) return null;

            let md = '\n';
            const headerRow = rows[0];
            md += '| ' + Array.from({length: colCount}, (_, c) => headerRow[c] || '').join(' | ') + ' |\n';
            md += '| ' + Array.from({length: colCount}, () => '---').join(' | ') + ' |\n';
            for (let r = 1; r < rows.length; r++) {
                const row = rows[r];
                md += '| ' + Array.from({length: colCount}, (_, c) => row[c] || '').join(' | ') + ' |\n';
            }
            md += '\n';
            return md;
        },

        detectAndFormatTables(rawText, htmlText = '') {
            if (!rawText && !htmlText) return '';
            let text = String(rawText || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');

            // 1. If HTML contains table tags (e.g. Google Docs rich clipboard, Word, web tables)
            if (htmlText && htmlText.includes('<table')) {
                const htmlTableMatches = htmlText.match(/<table[^>]*>[\s\S]*?<\/table>/gi);
                if (htmlTableMatches && htmlTableMatches.length > 0) {
                    htmlTableMatches.forEach(tblHtml => {
                        const md = this.convertHtmlTableToMarkdown(tblHtml);
                        if (md) {
                            const tr1 = tblHtml.match(/<tr[^>]*>[\s\S]*?<\/tr>/i);
                            const td1 = tr1 ? tr1[0].match(/<(?:th|td)[^>]*>([\s\S]*?)<\/(?:th|td)>/i) : null;
                            const firstHeader = td1 ? td1[1].replace(/<[^>]+>/g, '').trim() : '';

                            if (firstHeader && text.includes(firstHeader)) {
                                const escapedH = this.escapeRegex(firstHeader);
                                const blockRegex = new RegExp('(?:^|\\n)\\s*' + escapedH + '[\\s\\S]*?(?:\\n{2,}|$)', 'i');
                                if (blockRegex.test(text)) {
                                    text = text.replace(blockRegex, '\n\n' + md.trim() + '\n\n');
                                }
                            }
                        }
                    });
                }
            }

            // 2. Parse sequential or tab-separated tables from plain text
            let lines = text.split('\n');
            let result = [];
            let i = 0;

            const isTableTitleOrCaption = (str) => {
                const clean = str.trim();
                return /(?:^|\s)(?:table|comparison\s*table|specifications?\s*table|overview\s*table)$/i.test(clean) ||
                       /^(?:summary\s+comparison\s+table|key\s+specifications|technical\s+specifications|specifications\s+overview|specifications?\s+summary|quick\s+specifications?|comparison\s+table)$/i.test(clean);
            };

            const isSpecHeaderWord = (str) => {
                const clean = str.trim().toLowerCase();
                return /^(?:specification|specifications|specs?|feature|features|parameter|parameters|dimensions|item|items|attributes?|components?|properties|property|metric|metrics|part\s*number|model|models)$/i.test(clean);
            };

            const isSpecRowLabel = (str) => {
                const clean = str.trim();
                if (!clean || clean.length > 55 || clean.endsWith('.')) return false;
                if (/^(?:operating\s+class|engine|horsepower|hp|digging\s+depth|max\s+digging|reach|hydraulic|hydraulic\s+flow|hydraulic\s+system|track|tracks|track\s+type|track\s+configuration|travel\s+speed|speed|cab|cab\s+type|tail\s+swing|swing|boom|bucket|bucket\s+capacity|ground\s+pressure|operating\s+weight|weight|dimensions|overall\s+width|width|height|length|fuel\s+capacity|fuel\s+tank|displacement|net\s+power|cooling|transmission|blade|dozer\s+blade|warranty|price|rating|standard|optional)/i.test(clean)) {
                    return true;
                }
                return /^[A-Z][A-Za-z0-9\s\/\-_()]{2,45}$/.test(clean) && !/[!?;]$/.test(clean);
            };

            while (i < lines.length) {
                const line = lines[i].trim();

                // Already a pipe table row
                if (line.startsWith('|') && line.endsWith('|')) {
                    result.push(lines[i]);
                    i++;
                    continue;
                }

                // Table heading / section line like "### Summary Comparison Table"
                if (isTableTitleOrCaption(line) && i + 1 < lines.length && (isSpecHeaderWord(lines[i + 1]) || lines[i + 1].includes('\t') || isTableTitleOrCaption(lines[i + 1]))) {
                    result.push('\n### ' + line + '\n');
                    i++;
                    continue;
                }

                // Tab-delimited rows (TSV from Google Sheets or Docs)
                if (line.includes('\t')) {
                    let tsvRows = [];
                    while (i < lines.length && lines[i].trim().includes('\t')) {
                        tsvRows.push(lines[i].split('\t').map(c => c.trim()));
                        i++;
                    }
                    if (tsvRows.length >= 2) {
                        const colCount = Math.max(...tsvRows.map(r => r.length));
                        let md = '\n';
                        const hRow = tsvRows[0];
                        md += '| ' + Array.from({length: colCount}, (_, c) => hRow[c] || '').join(' | ') + ' |\n';
                        md += '| ' + Array.from({length: colCount}, () => '---').join(' | ') + ' |\n';
                        for (let r = 1; r < tsvRows.length; r++) {
                            const row = tsvRows[r];
                            md += '| ' + Array.from({length: colCount}, (_, c) => row[c] || '').join(' | ') + ' |\n';
                        }
                        md += '\n';
                        result.push(md);
                        continue;
                    } else {
                        result.push(lines[i - 1]);
                        continue;
                    }
                }

                // Sequential lines table (Google Docs plain text export)
                if (isSpecHeaderWord(line)) {
                    let bestColCount = 0;
                    let bestScore = -1;

                    for (let candidateCols of [2, 3, 4, 5]) {
                        if (i + candidateCols * 2 <= lines.length) {
                            let score = 0;
                            let checkSteps = 0;
                            for (let step = 1; step <= 5; step++) {
                                const checkIdx = i + candidateCols * step;
                                if (checkIdx < lines.length) {
                                    const candidateLabel = lines[checkIdx].trim();
                                    if (!candidateLabel) break;
                                    checkSteps++;
                                    if (isSpecRowLabel(candidateLabel)) {
                                        score += 2;
                                    } else if (/^[A-Z]/.test(candidateLabel) && candidateLabel.length < 50 && !candidateLabel.endsWith('.')) {
                                        score += 1;
                                    }
                                }
                            }
                            if (checkSteps >= 2 && score > bestScore) {
                                bestScore = score;
                                bestColCount = candidateCols;
                            }
                        }
                    }

                    let colCount = bestColCount;

                    if (colCount >= 2) {
                        let headers = [];
                        for (let c = 0; c < colCount; c++) {
                            headers.push(lines[i + c].trim());
                        }
                        i += colCount;

                        let rows = [];
                        while (i < lines.length) {
                            const currentCell = lines[i].trim();
                            if (!currentCell) {
                                i++;
                                break;
                            }
                            if (lines[i].startsWith('#') || lines[i].startsWith('http') || currentCell.length > 250) {
                                break;
                            }
                            let rowCells = [];
                            for (let c = 0; c < colCount; c++) {
                                if (i < lines.length && lines[i].trim()) {
                                    rowCells.push(lines[i].trim());
                                    i++;
                                } else {
                                    break;
                                }
                            }
                            if (rowCells.length === colCount) {
                                rows.push(rowCells);
                            } else {
                                if (rowCells.length > 0) rows.push(rowCells);
                                break;
                            }
                        }

                        if (rows.length >= 1) {
                            let md = '\n';
                            md += '| ' + headers.join(' | ') + ' |\n';
                            md += '| ' + Array.from({length: colCount}, () => '---').join(' | ') + ' |\n';
                            for (let r of rows) {
                                while (r.length < colCount) r.push('');
                                md += '| ' + r.join(' | ') + ' |\n';
                            }
                            md += '\n';
                            result.push(md);
                            continue;
                        }
                    }
                }

                result.push(lines[i]);
                i++;
            }

            return result.join('\n');
        },

        formatPipeTableToHtml(lines) {
            let html = '<div class="cms-table-container overflow-x-auto my-6" style="margin: 1.5rem 0; overflow-x: auto; -webkit-overflow-scrolling: touch;">';
            html += '<table class="cms-rendered-table w-full text-xs" border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse; margin: 0; border: 1px solid #cbd5e1; font-size: 14px; text-align: left; background: #ffffff;">';
            html += '<thead><tr style="background: #f1f5f9; border-bottom: 2px solid #cbd5e1;">';
            
            const headers = lines[0].split('|').map(c => c.trim()).filter(c => c !== '');
            headers.forEach(h => {
                html += '<th style="padding: 10px 14px; border: 1px solid #cbd5e1; font-weight: 700; text-align: left; color: #0f172a; font-size: 13.5px;">' + this.formatInlineMarkdown(h) + '</th>';
            });
            html += '</tr></thead><tbody>';

            const startIdx = (lines[1] && lines[1].includes('---')) ? 2 : 1;
            for (let i = startIdx; i < lines.length; i++) {
                const cells = lines[i].split('|').map(c => c.trim()).filter(c => c !== '');
                if (cells.length) {
                    const isEven = (i % 2 === 0);
                    const bg = isEven ? '#ffffff' : '#f8fafc';
                    html += '<tr style="background: ' + bg + '; border-bottom: 1px solid #e2e8f0;">';
                    cells.forEach((c, idx) => {
                        const isFirstCol = (idx === 0);
                        const weight = isFirstCol ? 'font-weight: 600; color: #0f172a;' : 'color: #334155;';
                        html += '<td style="padding: 9px 14px; border: 1px solid #e2e8f0; vertical-align: top; font-size: 13px; ' + weight + '">' + this.formatInlineMarkdown(c) + '</td>';
                    });
                    html += '</tr>';
                }
            }
            html += '</tbody></table></div>';
            return html;
        },

        formatHtmlTableBlock(tableHtml) {
            let styled = tableHtml;
            if (!styled.includes('cms-rendered-table')) {
                styled = styled.replace(/<table/i, '<table class="cms-rendered-table w-full text-xs" border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse; margin: 0; border: 1px solid #cbd5e1; font-size: 14px; text-align: left; background: #ffffff;"');
            }
            styled = styled.replace(/<th(?![^>]*style=)/gi, '<th style="padding: 10px 14px; border: 1px solid #cbd5e1; font-weight: 700; text-align: left; background: #f1f5f9; color: #0f172a;"');
            styled = styled.replace(/<td(?![^>]*style=)/gi, '<td style="padding: 9px 14px; border: 1px solid #e2e8f0; color: #334155; vertical-align: top;"');
            
            if (!styled.includes('cms-table-container')) {
                return '<div class="cms-table-container overflow-x-auto my-6" style="margin: 1.5rem 0; overflow-x: auto; -webkit-overflow-scrolling: touch;">' + styled + '</div>';
            }
            return styled;
        },

        H1_STYLE: 'font-size: 1.85rem; font-weight: 900; color: #0f172a; margin-top: 2.25rem; margin-bottom: 1rem; line-height: 1.25; border-bottom: 2px solid #cbd5e1; padding-bottom: 0.6rem; letter-spacing: -0.02em; clear: both; display: block;',
        H2_STYLE: 'font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 2rem; margin-bottom: 0.85rem; line-height: 1.3; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem; letter-spacing: -0.015em; clear: both; display: block;',
        H3_STYLE: 'font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-top: 1.6rem; margin-bottom: 0.65rem; line-height: 1.35; letter-spacing: -0.01em; clear: both; display: block;',
        H4_STYLE: 'font-size: 1.1rem; font-weight: 700; color: #334155; margin-top: 1.25rem; margin-bottom: 0.5rem; line-height: 1.4; clear: both; display: block;',

        isHeadingLine(line) {
            const l = (line || '').trim();
            if (!l || l.length > 95 || l.length < 3) return false;
            if (l.startsWith('http://') || l.startsWith('https://') || l.startsWith('![')) return false;
            if (l.startsWith('|') || l.startsWith('- ') || l.startsWith('* ') || l.startsWith('1. ') || l.startsWith('> ')) return false;
            if (l.endsWith('.') || l.endsWith(',') || l.endsWith(';')) return false;

            // Has markdown hashes: # ## ### ####
            if (/^#{1,4}\s+/.test(l)) return true;

            // Is wrapped in bold: **Title**
            if (/^\*\*([^*]+)\*\*$/.test(l)) return true;

            // Common structural keywords
            if (/^(?:Executive Summary|Technical Spotlight|Powertrain|Summary Comparison|Key Differences?|Specifications?|Features?|Maintenance|Hydraulic System|Overview|Performance|Conclusion|FAQ|Safety Guide|Section \d+|Step \d+):?/i.test(l)) {
                return true;
            }

            // Title Case detection for headings without hashes
            const words = l.split(/\s+/).filter(Boolean);
            if (words.length >= 1 && words.length <= 15) {
                const ignoreList = new Set(["of","in","vs","vs.","on","for","and","the","a","an","to","with","at","by","from","as"]);
                let titleWords = 0;
                let totalNonIgnore = 0;
                for (const w of words) {
                    const cleanW = w.toLowerCase().replace(/[^a-z0-9]/g, "");
                    if (ignoreList.has(cleanW)) continue;
                    totalNonIgnore++;
                    if (/^[A-Z0-9]/.test(w)) {
                        titleWords++;
                    }
                }
                if (totalNonIgnore > 0 && (titleWords / totalNonIgnore >= 0.65)) {
                    return true;
                }
            }
            return false;
        },

        ensureHtmlTablesStyled(html) {
            if (!html || !html.includes('<table')) return html;
            return html.replace(/<table[^>]*>[\s\S]*?<\/table>/gi, (match) => {
                return this.formatHtmlTableBlock(match);
            });
        },

        ensureHtmlContentStyled(html) {
            if (!html || !html.trim()) return '';
            let styled = html;

            // 1. Ensure Table Styling
            styled = this.ensureHtmlTablesStyled(styled);

            // 2. Promote standalone paragraph headings: <p><strong>Heading Title</strong></p>
            styled = styled.replace(/<p(\s+[^>]*)?>\s*<(?:strong|b)>\s*(#{1,4}\s+)?([^<>\n]{3,95}?)\s*<\/(?:strong|b)>\s*<\/p>/gi, (match, pAttrs, hashes, titleText) => {
                const clean = titleText.trim();
                if (clean.endsWith('.') || clean.endsWith(',') || clean.endsWith(';') || clean.startsWith('http://') || clean.startsWith('https://')) {
                    return match;
                }
                let level = 2;
                if (hashes) {
                    level = hashes.trim().length;
                } else if (/^(?:fixed vs|mini excavator|key difference|pros and cons|vs\b)/i.test(clean) || clean.length < 35) {
                    level = 3;
                }
                const style = level === 1 ? this.H1_STYLE : (level === 3 ? this.H3_STYLE : (level === 4 ? this.H4_STYLE : this.H2_STYLE));
                const cls = level === 1 ? 'text-2xl font-black text-slate-900 dark:text-white mt-8 mb-4 border-b border-slate-200 dark:border-slate-800 pb-2' : (level === 3 ? 'text-lg font-bold text-slate-900 dark:text-white mt-6 mb-3' : (level === 4 ? 'text-base font-bold text-slate-800 dark:text-slate-200 mt-5 mb-2' : 'text-xl font-black text-slate-900 dark:text-white mt-8 mb-4 border-b border-slate-100 dark:border-slate-800 pb-2'));
                return `<h${level} class="${cls}" style="${style}">${clean}</h${level}>`;
            });

            // 3. Promote standalone paragraph headings with hashes: <p>## Heading</p>
            styled = styled.replace(/<p(\s+[^>]*)?>\s*(#{1,4})\s+([^<>\n]{3,95}?)\s*<\/p>/gi, (match, pAttrs, hashes, titleText) => {
                const clean = titleText.trim();
                const level = hashes.length;
                const style = level === 1 ? this.H1_STYLE : (level === 3 ? this.H3_STYLE : (level === 4 ? this.H4_STYLE : this.H2_STYLE));
                const cls = level === 1 ? 'text-2xl font-black text-slate-900 dark:text-white mt-8 mb-4 border-b border-slate-200 dark:border-slate-800 pb-2' : (level === 3 ? 'text-lg font-bold text-slate-900 dark:text-white mt-6 mb-3' : (level === 4 ? 'text-base font-bold text-slate-800 dark:text-slate-200 mt-5 mb-2' : 'text-xl font-black text-slate-900 dark:text-white mt-8 mb-4 border-b border-slate-100 dark:border-slate-800 pb-2'));
                return `<h${level} class="${cls}" style="${style}">${clean}</h${level}>`;
            });

            // 4. Style existing <h1-h4> tags that lack inline styles or border
            styled = styled.replace(/<h1(\s+[^>]*)?>([\s\S]*?)<\/h1>/gi, (match, attrs, content) => {
                attrs = attrs || '';
                if (!attrs.includes('style=')) {
                    return `<h1${attrs} style="${this.H1_STYLE}">${content}</h1>`;
                } else if (!attrs.includes('border-bottom') || !attrs.includes('font-weight')) {
                    return match.replace(/style="([^"]*)"/i, `style="$1; ${this.H1_STYLE}"`);
                }
                return match;
            });

            styled = styled.replace(/<h2(\s+[^>]*)?>([\s\S]*?)<\/h2>/gi, (match, attrs, content) => {
                attrs = attrs || '';
                if (!attrs.includes('style=')) {
                    return `<h2${attrs} style="${this.H2_STYLE}">${content}</h2>`;
                } else if (!attrs.includes('border-bottom') || !attrs.includes('font-weight')) {
                    return match.replace(/style="([^"]*)"/i, `style="$1; ${this.H2_STYLE}"`);
                }
                return match;
            });

            styled = styled.replace(/<h3(\s+[^>]*)?>([\s\S]*?)<\/h3>/gi, (match, attrs, content) => {
                attrs = attrs || '';
                if (!attrs.includes('style=')) {
                    return `<h3${attrs} style="${this.H3_STYLE}">${content}</h3>`;
                } else if (!attrs.includes('font-size') || !attrs.includes('font-weight')) {
                    return match.replace(/style="([^"]*)"/i, `style="$1; ${this.H3_STYLE}"`);
                }
                return match;
            });

            styled = styled.replace(/<h4(\s+[^>]*)?>([\s\S]*?)<\/h4>/gi, (match, attrs, content) => {
                attrs = attrs || '';
                if (!attrs.includes('style=')) {
                    return `<h4${attrs} style="${this.H4_STYLE}">${content}</h4>`;
                }
                return match;
            });

            return styled;
        },

        renderContentToHtml(raw) {
            if (!raw || !raw.trim()) return '';
            if ((raw.includes('<p>') || raw.includes('<p ')) && raw.includes('</p>')) {
                return this.ensureHtmlContentStyled(raw);
            }

            // Ensure any unformatted sequential or TSV tables are converted into pipe tables
            let text = this.detectAndFormatTables(raw);
            text = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');

            text = text.replace(/([^\n])\n(!?\[[^\]]*\]\(https?:\/\/[^\)]+\))/gi, '$1\n\n$2');
            text = text.replace(/(!?\[[^\]]*\]\(https?:\/\/[^\)]+\))\n([^\n])/gi, '$1\n\n$2');
            text = text.replace(/([^\n])\n(https?:\/\/[^\s<>]+\.(?:webp|png|jpe?g|gif|avif))/gi, '$1\n\n$2');
            text = text.replace(/(https?:\/\/[^\s<>]+\.(?:webp|png|jpe?g|gif|avif))\n([^\n])/gi, '$1\n\n$2');
            text = text.replace(/([^\n])\n(https?:\/\/(?:youtu\.be\/|www\.youtube\.com\/)[^\s]+)/gi, '$1\n\n$2');
            text = text.replace(/(https?:\/\/(?:youtu\.be\/|www\.youtube\.com\/)[^\s]+)\n([^\n])/gi, '$1\n\n$2');
            text = text.replace(/([^\n])\n(#{1,4}\s+[^\n]+)/g, '$1\n\n$2');
            text = text.replace(/(#{1,4}\s+[^\n]+)\n([^\n])/g, '$1\n\n$2');
            // Isolate standalone bold heading lines: **Title**
            text = text.replace(/([^\n])\n(\*\*[^*]{3,95}\*\*)\n/g, '$1\n\n$2\n\n');
            // Isolate tables with \n\n
            text = text.replace(/([^\n])\n(\|[\s\S]*?\|\n\|[\s\-:|]+\|)/g, '$1\n\n$2');
            text = text.replace(/(\|(?:\r?\n)+)(#{1,4}\s+|[^\n|])/g, '$1\n\n$2');
            text = text.replace(/([^\n])\n(<table[\s\S]*?<\/table>)/gi, '$1\n\n$2');
            text = text.replace(/(<\/table>)\n([^\n])/gi, '$1\n\n$2');

            // Scan line-by-line to isolate standalone title lines
            const rawLines = text.split('\n');
            const processedLines = [];
            for (let i = 0; i < rawLines.length; i++) {
                const line = rawLines[i];
                if (this.isHeadingLine(line)) {
                    if (processedLines.length > 0 && processedLines[processedLines.length - 1] !== '') {
                        processedLines.push('');
                    }
                    processedLines.push(line.trim());
                    if (i + 1 < rawLines.length && rawLines[i + 1].trim() !== '') {
                        processedLines.push('');
                    }
                } else {
                    processedLines.push(line);
                }
            }
            text = processedLines.join('\n');

            const blocks = text.split(/\n{2,}/);
            let html = '';

            blocks.forEach(block => {
                const b = block.trim();
                if (!b) return;

                // 1. YouTube markdown link: [Caption](https://youtu.be/...)
                const mdYt = b.match(/^\[([^\]]+)\]\((https?:\/\/(?:youtu\.be\/|www\.youtube\.com\/)[^\s\)]+)\)$/i);
                if (mdYt) {
                    const caption = mdYt[1];
                    const url = mdYt[2];
                    const ytId = url.match(/(?:youtu\.be\/|v=)([\w-]{11})/i);
                    if (ytId) {
                        html += '<div class="cms-youtube-wrapper my-6"><iframe src="https://www.youtube.com/embed/' + ytId[1] + '" title="' + this.escapeHtml(caption) + '" allowfullscreen></iframe></div>';
                        html += '<div class="text-center text-xs text-slate-500 italic -mt-4 mb-6">' + this.escapeHtml(caption) + '</div>';
                        return;
                    }
                }

                // 2. Bare YouTube link
                const ytMatch = b.match(/^https?:\/\/(?:youtu\.be\/|www\.youtube\.com\/(?:watch\?v=|embed\/))([\w-]{11})/i);
                if (ytMatch) {
                    html += '<div class="cms-youtube-wrapper my-6"><iframe src="https://www.youtube.com/embed/' + ytMatch[1] + '" allowfullscreen></iframe></div>';
                    return;
                }

                // 3a. Markdown image link: ![Alt Text](https://... "Optional Title")
                const mdImg = b.match(/^!\[([^\]]*)\]\((https?:\/\/[^\s\)]+)(?:\s+["']([^"']*)["'])?\)$/i);
                if (mdImg) {
                    const altText = mdImg[1] || '';
                    const imgSrc = mdImg[2];
                    const titleText = mdImg[3] || altText;
                    html += `<figure class="cms-rendered-figure my-6" data-align="center" style="margin: 1.75rem auto; text-align: center; max-width: 100%; clear: both;"><img src="${imgSrc}" alt="${this.escapeHtml(altText)}" title="${this.escapeHtml(titleText)}" loading="lazy" class="rounded-xl shadow-md mx-auto max-h-[480px] object-cover" style="max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); display: block; margin: 0 auto;"><figcaption class="cms-rendered-caption text-center text-xs mt-2" style="text-align: center; font-size: 12px; color: #64748b; margin-top: 8px; font-style: italic;" contenteditable="true" data-placeholder="CAPTION: Write caption or attribution (e.g. Photo courtesy of TechLab)">${this.escapeHtml(altText)}</figcaption></figure>`;
                    return;
                }

                // 3b. Bare Image URL
                if (/^https?:\/\/[^\s<>]+\.(?:webp|png|jpe?g|gif|avif)/i.test(b)) {
                    const filename = b.split('/').pop().replace(/\.[^/.]+$/, '').replace(/[-_]/g, ' ');
                    html += `<figure class="cms-rendered-figure my-6" data-align="center" style="margin: 1.75rem auto; text-align: center; max-width: 100%; clear: both;"><img src="${b}" alt="${this.escapeHtml(filename)}" loading="lazy" class="rounded-xl shadow-md mx-auto max-h-[480px] object-cover" style="max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); display: block; margin: 0 auto;"><figcaption class="cms-rendered-caption text-center text-xs mt-2" style="text-align: center; font-size: 12px; color: #64748b; margin-top: 8px; font-style: italic;" contenteditable="true" data-placeholder="CAPTION: Write caption or attribution (e.g. Photo courtesy of TechLab)">${this.escapeHtml(filename)}</figcaption></figure>`;
                    return;
                }

                // 4. HTML Table
                if (b.startsWith('<table') || (b.includes('<table') && b.includes('</table>'))) {
                    html += this.formatHtmlTableBlock(b);
                    return;
                }

                // 5. Pipe Table
                if (b.includes('|') && b.includes('\n')) {
                    const lines = b.split('\n').filter(l => l.trim().startsWith('|'));
                    if (lines.length >= 2) {
                        html += this.formatPipeTableToHtml(lines);
                        return;
                    }
                }

                // 6. Sequential Table Fallback
                if (this.isSequentialTable(b)) {
                    html += this.convertSequentialTableToHtml(b);
                    return;
                }

                // 7. Section Headings: ####
                if (b.startsWith('#### ')) {
                    const headingText = b.slice(5).replace(/^\*\*(.*)\*\*$/, '$1').trim();
                    html += `<h4 class="text-base font-bold text-slate-800 dark:text-slate-200 mt-5 mb-2" style="${this.H4_STYLE}">` + this.formatInlineMarkdown(headingText) + '</h4>';
                    return;
                }

                // Section Headings: ###
                if (b.startsWith('### ')) {
                    const headingText = b.slice(4).replace(/^\*\*(.*)\*\*$/, '$1').trim();
                    html += `<h3 class="text-lg font-bold text-slate-900 dark:text-white mt-6 mb-3" style="${this.H3_STYLE}">` + this.formatInlineMarkdown(headingText) + '</h3>';
                    return;
                }

                // Section Headings: ##
                if (b.startsWith('## ')) {
                    const headingText = b.slice(3).replace(/^\*\*(.*)\*\*$/, '$1').trim();
                    html += `<h2 class="text-xl font-black text-slate-900 dark:text-white mt-8 mb-4 border-b border-slate-100 dark:border-slate-800 pb-2" style="${this.H2_STYLE}">` + this.formatInlineMarkdown(headingText) + '</h2>';
                    return;
                }

                // Section Headings: #
                if (b.startsWith('# ')) {
                    const headingText = b.slice(2).replace(/^\*\*(.*)\*\*$/, '$1').trim();
                    html += `<h1 class="text-2xl font-black text-slate-900 dark:text-white mt-8 mb-4 border-b border-slate-200 dark:border-slate-800 pb-2" style="${this.H1_STYLE}">` + this.formatInlineMarkdown(headingText) + '</h1>';
                    return;
                }

                // Standalone bold heading line: **Title**
                const boldHeadingMatch = b.match(/^\*\*([^*]+)\*\*$/);
                if (boldHeadingMatch && b.length < 95 && !b.endsWith('.') && !b.endsWith(',')) {
                    const cleanTitle = boldHeadingMatch[1].trim();
                    const isSub = cleanTitle.length < 35 || /^(?:fixed vs|mini excavator|key differences|vs\b)/i.test(cleanTitle);
                    const tag = isSub ? 'h3' : 'h2';
                    const style = isSub ? this.H3_STYLE : this.H2_STYLE;
                    const cls = isSub ? 'text-lg font-bold text-slate-900 dark:text-white mt-6 mb-3' : 'text-xl font-black text-slate-900 dark:text-white mt-8 mb-4 border-b border-slate-100 dark:border-slate-800 pb-2';
                    html += `<${tag} class="${cls}" style="${style}">` + this.formatInlineMarkdown(cleanTitle) + `</${tag}>`;
                    return;
                }

                // Standalone heading lines without markdown hashes
                if (!b.includes('\n') && b.length < 90 && !b.endsWith('.') && !b.endsWith(',') && !b.endsWith(';') && !b.startsWith('http') && !b.startsWith('![')) {
                    if (this.isHeadingLine(b)) {
                        const isSub = b.length < 35 || /^(?:fixed vs|mini excavator|key differences|vs\b)/i.test(b);
                        const tag = isSub ? 'h3' : 'h2';
                        const style = isSub ? this.H3_STYLE : this.H2_STYLE;
                        const cls = isSub ? 'text-lg font-bold text-slate-900 dark:text-white mt-6 mb-3' : 'text-xl font-black text-slate-900 dark:text-white mt-8 mb-4 border-b border-slate-100 dark:border-slate-800 pb-2';
                        html += `<${tag} class="${cls}" style="${style}">` + this.formatInlineMarkdown(b) + `</${tag}>`;
                        return;
                    }
                }

                // Normal Paragraph
                const paragraphContent = b.split('\n').map(line => this.formatInlineMarkdown(line.trim())).filter(Boolean).join('<br>');
                html += '<p class="text-slate-800 dark:text-slate-100 mb-4 leading-relaxed font-normal text-[15px]" style="margin-bottom: 1.15rem; line-height: 1.75; font-size: 15px; color: #334155;">' + paragraphContent + '</p>';
            });

            return html;
        },

        isSequentialTable(block) {
            const lines = block.split('\n').map(l => l.trim()).filter(Boolean);
            if (lines.length < 4) return false;
            const clean0 = lines[0].toLowerCase();
            return /^(?:specification|specifications|specs?|feature|features|parameter|parameters|dimensions|item|items|properties)$/i.test(clean0);
        },

        convertSequentialTableToHtml(block) {
            const lines = block.split('\n').map(l => l.trim()).filter(Boolean);
            if (lines.length < 4) return '<p>' + block + '</p>';

            let colCount = 3;
            if (lines.length % 2 === 0 && lines.length % 3 !== 0) colCount = 2;
            
            let html = '<div class="cms-table-container overflow-x-auto my-6" style="margin: 1.5rem 0; overflow-x: auto; -webkit-overflow-scrolling: touch;">';
            html += '<table class="cms-rendered-table w-full text-xs" border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse; margin: 0; border: 1px solid #cbd5e1; font-size: 14px; text-align: left; background: #ffffff;">';
            html += '<thead><tr style="background: #f1f5f9; border-bottom: 2px solid #cbd5e1;">';
            for (let c = 0; c < colCount; c++) {
                html += '<th style="padding: 10px 14px; border: 1px solid #cbd5e1; font-weight: 700; text-align: left; color: #0f172a; font-size: 13.5px;">' + this.escapeHtml(lines[c] || '') + '</th>';
            }
            html += '</tr></thead><tbody>';

            let rowIdx = 0;
            for (let i = colCount; i < lines.length; i += colCount) {
                const isEven = (rowIdx % 2 === 0);
                const bg = isEven ? '#ffffff' : '#f8fafc';
                html += '<tr style="background: ' + bg + '; border-bottom: 1px solid #e2e8f0;">';
                for (let c = 0; c < colCount; c++) {
                    const val = lines[i + c] || '';
                    const isFirst = (c === 0);
                    const weight = isFirst ? 'font-weight: 600; color: #0f172a;' : 'color: #334155;';
                    html += '<td style="padding: 9px 14px; border: 1px solid #e2e8f0; vertical-align: top; font-size: 13px; ' + weight + '">' + this.escapeHtml(val) + '</td>';
                }
                html += '</tr>';
                rowIdx++;
            }
            html += '</tbody></table></div>';
            return html;
        },

        formatInlineMarkdown(text) {
            return text
                .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" class="text-blue-600 dark:text-blue-400 font-bold underline">$1</a>')
                .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                .replace(/\*([^*]+)\*/g, '<em>$1</em>')
                .replace(/`([^`]+)`/g, '<code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-xs font-mono">$1</code>');
        },

        escapeHtml(str) {
            return (str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        },

        insertFormat(type) {
            if (this.articles[0]) {
                this.insertArticleFormat(type, this.articles[0]);
            }
        },

        insertHeading(level) {
            if (this.articles[0]) {
                this.insertArticleHeading(level, this.articles[0]);
            }
        },

        promptInsertImage() {
            if (this.articles[0]) {
                this.promptInsertArticleImage(this.articles[0]);
            }
        },

        promptInsertYoutube() {
            if (this.articles[0]) {
                this.promptInsertArticleYoutube(this.articles[0]);
            }
        },

        insertSampleTable() {
            if (this.articles[0]) {
                this.insertArticleSampleTable(this.articles[0]);
            }
        },

        loadPostIntoEditor(postId) {
            fetch(`/websites/posts/${postId}`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.post) {
                    const p = data.post;
                    let content = (p.raw_content && p.raw_content.trim()) ? p.raw_content : ((p.content_html && p.content_html.trim()) ? p.content_html : (p.excerpt || ''));

                    const loadedArt = this.createArticleObject(p.website_id, {
                        id: p.id,
                        title: p.title || '',
                        slug: p.slug || '',
                        contentRaw: content,
                        status: this.normalizeStatus(p.status || 'draft'),
                        scheduled_at: p.scheduled_at || '',
                        author_name: p.author_name || '',
                        category: p.category || '',
                        playlist: p.playlist || 'None (Standalone Article)',
                        excerpt: p.excerpt || '',
                        featured_image_url: p.featured_image_url || '',
                        featured_image_alt: p.cover_alt || '',
                        video_url: p.video_url || '',
                        product_sku: p.product_sku || '',
                        product_url: p.product_url || '',
                        meta_title: p.meta_title || '',
                        meta_description: p.meta_description || '',
                        tag_keywords: p.tag_keywords || p.focus_keyword || '',
                        focus_keyword: p.focus_keyword || p.tag_keywords || ''
                    });

                    this.articles = [ loadedArt ];
                    this.selectedWebsiteId = p.website_id;
                    this.selectedWebsite = this.getWebsite(p.website_id);
                    this.updateWebsiteProfile();
                    this.syncLegacyFieldsFromArticle0();

                    this.activeViewTab = 'editor';
                    this.editorMode = 'visual';
                    this.activeSidebarTab = 'all';
                    this.currentScreen = 'editor';
                    this.mobileStudioTab = 'content';

                    this.$nextTick(() => {
                        this.syncAllVisualCanvases(true);
                    });

                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    this.showToast(`Loaded article #${p.id} into CMS editor.`);
                }
            });
        },

        showToast(msg, liveUrl = null, type = 'success') {
            const div = document.createElement('div');
            const isWarn = type === 'warning';
            div.className = `fixed bottom-6 right-6 z-50 px-4 py-3 rounded-xl ${isWarn ? 'bg-amber-950/95 text-amber-200 border-amber-600/60' : 'bg-slate-900 text-white border-slate-700'} font-bold text-xs shadow-2xl flex items-center gap-3 border animate-in`;
            let liveBtn = '';
            if (liveUrl && liveUrl !== '#' && !liveUrl.includes('localhost') && !liveUrl.includes('127.0.0.1')) {
                liveBtn = `<a href="${this.escapeHtml(liveUrl)}" target="_blank" rel="noopener noreferrer" class="px-2.5 py-1 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-[11px] transition-all inline-flex items-center gap-1 shadow-sm shrink-0"><span>View Live on Website ↗</span></a>`;
            }
            const icon = isWarn ? `<span class="text-amber-400 font-black text-sm">⚠</span>` : `<span class="text-emerald-400 font-black text-sm">✓</span>`;
            div.innerHTML = `${icon} <span class="leading-snug">${this.escapeHtml(msg)}</span> ${liveBtn}`;
            document.body.appendChild(div);
            setTimeout(() => { div.remove(); }, liveUrl ? 7500 : 4500);
        }
    };
}
</script>
@endsection