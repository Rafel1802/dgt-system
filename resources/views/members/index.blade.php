@extends('layouts.app')

@section('title', 'All Members')
@section('meta_description', 'KIUQ SYSTEM company directory — find and connect with your team members.')

@section('content')
@php
    $allMembers = $members->map(fn($u) => [
        'id'          => $u->id,
        'name'        => $u->name,
        'email'       => $u->email,
        'phone'       => $u->phone,
        'whatsapp'    => $u->whatsapp ?: $u->phone,
        'phone_url'   => $u->phone ? 'tel:' . $u->phone : null,
        'whatsapp_url'=> ($u->whatsapp ?: $u->phone) ? 'https://wa.me/' . preg_replace('/[^0-9]/', '', $u->whatsapp ?: $u->phone) : null,
        'role_display'=> $u->role_display,
        'team_role'   => $u->team_role,
        'avatar_url'  => $u->avatar_url,
        'is_online'   => $u->last_login_at && $u->last_login_at->gte(now()->subMinutes(30)),
        'last_seen'   => $u->last_login_at?->diffForHumans(),
        'joined'      => $u->created_at?->format('F j, Y'),
        'role_slug'   => $u->roles->first()?->name ?? 'user',
    ]);
@endphp

<style>
/* ── Members Directory ───────────────────────────────────────────────── */
.members-hero {
    background: linear-gradient(135deg, #1e3a8a 0%, #2F68ED 45%, #0891b2 100%);
    border-radius: 1.75rem;
    padding: 2.5rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 22px 52px rgba(47, 104, 237, 0.28);
    color: white;
}
.members-hero::before {
    content: '';
    position: absolute;
    top: -50%; right: -10%;
    width: 500px; height: 500px;
    background: radial-gradient(circle, rgba(255,255,255,0.07) 0%, transparent 70%);
    pointer-events: none;
}
.members-hero::after {
    content: '';
    position: absolute;
    bottom: -30%; left: 10%;
    width: 300px; height: 300px;
    background: radial-gradient(circle, rgba(8,145,178,0.16) 0%, transparent 70%);
    pointer-events: none;
}

/* Stat cards */
.stat-card {
    background: rgba(255,255,255,0.82);
    border: 1px solid rgba(226,232,240,0.8);
    border-radius: 1.25rem;
    padding: 1.25rem;
    backdrop-filter: blur(18px);
    box-shadow: 0 8px 32px rgba(15,23,42,0.06);
    transition: transform 160ms ease, box-shadow 160ms ease;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 16px 40px rgba(15,23,42,0.1); }

/* Search bar */
.search-bar {
    background: rgba(255,255,255,0.85);
    border: 1px solid rgba(226,232,240,0.9);
    border-radius: 1.25rem;
    padding: 1.25rem 1.5rem;
    backdrop-filter: blur(18px);
    box-shadow: 0 4px 24px rgba(15,23,42,0.05);
}

/* Member card */
.member-card {
    background: rgba(255,255,255,0.9);
    border: 1px solid rgba(226,232,240,0.8);
    border-radius: 1.5rem;
    overflow: hidden;
    transition: transform 220ms cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 220ms ease, border-color 220ms ease;
    position: relative;
    animation: cardFadeIn 300ms ease both;
}
.member-card:hover {
    transform: translateY(-6px) scale(1.01);
    box-shadow: 0 24px 60px rgba(47,104,237,0.16), 0 8px 20px rgba(15,23,42,0.08);
    border-color: rgba(47,104,237,0.28);
}
.member-card-body {
    padding: 1.5rem 1.25rem 0.75rem;
    text-align: center;
}
.member-avatar-wrap {
    position: relative;
    display: inline-block;
    margin: 0 auto 0.85rem;
    cursor: pointer;
}
.member-avatar {
    width: 86px;
    height: 86px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(255,255,255,0.9);
    box-shadow: 0 8px 24px rgba(15,23,42,0.14);
    display: block;
    margin: 0 auto;
    transition: transform 220ms ease, box-shadow 220ms ease;
}
.member-avatar-wrap:hover .member-avatar {
    transform: scale(1.08);
    box-shadow: 0 12px 32px rgba(47,104,237,0.28);
}
/* Camera overlay on avatar hover */
.member-avatar-wrap::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: rgba(15,23,42,0.38);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 180ms ease;
}
.member-avatar-wrap:hover::after { opacity: 1; }
.avatar-view-hint {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    pointer-events: none;
    transition: opacity 180ms ease;
    z-index: 2;
    color: white;
}
.member-avatar-wrap:hover .avatar-view-hint { opacity: 1; }

.online-dot {
    position: absolute;
    bottom: 4px;
    right: 4px;
    width: 13px;
    height: 13px;
    border-radius: 50%;
    border: 2.5px solid white;
    background: #10b981;
    animation: pulse-dot 2s infinite;
    z-index: 3;
}
@keyframes pulse-dot {
    0%, 100% { box-shadow: 0 0 0 0 rgba(16,185,129,0.5); }
    50%       { box-shadow: 0 0 0 5px rgba(16,185,129,0); }
}

.member-name { font-weight: 900; font-size: 0.875rem; color: #0f172a; line-height: 1.3; margin-bottom: 0.15rem; }
.member-role { font-size: 0.7rem; font-weight: 700; color: #64748b; line-height: 1.4; margin-bottom: 0.1rem; }
.member-team-role { font-size: 0.66rem; font-weight: 600; color: #94a3b8; font-style: italic; margin-bottom: 0.5rem; }
.role-badge {
    display: inline-block;
    padding: 0.18rem 0.6rem;
    border-radius: 999px;
    font-size: 0.63rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.75rem;
}

/* ── Contact info row on card ── */
.card-contact-row {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    padding: 0 1.25rem 0.75rem;
    border-top: 1px solid rgba(226,232,240,0.7);
    margin-top: 0.25rem;
    padding-top: 0.65rem;
}
.card-contact-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: #475569;
    text-align: left;
    min-width: 0;
}
.card-contact-item svg { flex-shrink: 0; }
.card-contact-item span { truncate: true; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.card-contact-item.no-info { color: #cbd5e1; font-style: italic; font-weight: 500; }

/* Action buttons */
.member-actions {
    display: flex;
    gap: 0.5rem;
    padding: 0 1.25rem 1.25rem;
}
.btn-whatsapp {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.55rem 0.4rem;
    border-radius: 0.75rem;
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: white;
    font-size: 0.68rem;
    font-weight: 800;
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: all 160ms ease;
    box-shadow: 0 4px 12px rgba(34,197,94,0.28);
}
.btn-whatsapp:hover { background: linear-gradient(135deg, #16a34a, #15803d); box-shadow: 0 6px 20px rgba(34,197,94,0.48); transform: translateY(-1px); color: white; }
.btn-whatsapp.is-disabled { opacity: 0.32; cursor: not-allowed; pointer-events: none; }
.btn-phone {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.55rem 0.4rem;
    border-radius: 0.75rem;
    background: rgba(248,250,252,0.9);
    color: #475569;
    font-size: 0.68rem;
    font-weight: 800;
    border: 1px solid rgba(203,213,225,0.8);
    cursor: pointer;
    text-decoration: none;
    transition: all 160ms ease;
}
.btn-phone:hover { background: rgba(239,246,255,0.95); color: #2F68ED; border-color: rgba(47,104,237,0.4); box-shadow: 0 4px 12px rgba(47,104,237,0.14); transform: translateY(-1px); }
.btn-phone.is-disabled { opacity: 0.32; cursor: not-allowed; pointer-events: none; }

/* Grid */
.members-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1.25rem; }
@media (max-width: 1400px) { .members-grid { grid-template-columns: repeat(4, 1fr); } }
@media (max-width: 1100px) { .members-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 700px)  { .members-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 420px)  { .members-grid { grid-template-columns: 1fr; } }

/* Empty state */
.empty-members { text-align: center; padding: 5rem 2rem; background: rgba(255,255,255,0.6); border: 2px dashed rgba(203,213,225,0.8); border-radius: 1.5rem; }
.empty-icon { width: 4.5rem; height: 4.5rem; margin: 0 auto 1rem; background: rgba(226,232,240,0.6); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #94a3b8; }

/* ── Full-screen photo viewer (Facebook style) ───────────────────── */
.photo-viewer {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(0, 0, 0, 0.96);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}
.photo-viewer-inner {
    position: relative;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
}
/* Full-screen photo */
.photo-viewer-img {
    height: 85vh;
    width: auto;
    max-width: 95vw;
    border-radius: 1rem;
    object-fit: contain;
    box-shadow: 0 32px 80px rgba(0,0,0,0.8);
    animation: photoZoomIn 240ms cubic-bezier(0.34, 1.56, 0.64, 1);
}
@keyframes photoZoomIn {
    from { opacity: 0; transform: scale(0.84); }
    to   { opacity: 1; transform: scale(1); }
}
/* Info panel below photo */
.photo-viewer-info {
    margin-top: 1.5rem;
    text-align: center;
    animation: photoZoomIn 280ms ease;
}
.photo-viewer-name {
    font-size: 1.35rem;
    font-weight: 900;
    color: white;
    line-height: 1.2;
}
.photo-viewer-role {
    font-size: 0.85rem;
    font-weight: 600;
    color: rgba(255,255,255,0.6);
    margin-top: 0.3rem;
}
/* Contact row in viewer */
.photo-viewer-contacts {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    margin-top: 1.25rem;
    flex-wrap: wrap;
}
.pv-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.65rem 1.25rem;
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 800;
    text-decoration: none;
    transition: all 160ms ease;
    cursor: pointer;
    border: none;
}
.pv-btn-whatsapp {
    background: #22c55e;
    color: white;
    box-shadow: 0 4px 18px rgba(34,197,94,0.45);
}
.pv-btn-whatsapp:hover { background: #16a34a; box-shadow: 0 6px 24px rgba(34,197,94,0.65); transform: translateY(-1px); color: white; }
.pv-btn-phone {
    background: rgba(255,255,255,0.12);
    color: white;
    border: 1px solid rgba(255,255,255,0.2);
}
.pv-btn-phone:hover { background: rgba(255,255,255,0.22); transform: translateY(-1px); color: white; }
.pv-btn-email {
    background: rgba(99,102,241,0.85);
    color: white;
}
.pv-btn-email:hover { background: rgba(99,102,241,1); transform: translateY(-1px); color: white; }

/* Close button */
.photo-viewer-close {
    position: fixed;
    top: 1.25rem;
    right: 1.25rem;
    width: 3rem;
    height: 3rem;
    border-radius: 50%;
    background: rgba(255,255,255,0.12);
    border: 1.5px solid rgba(255,255,255,0.2);
    color: white;
    font-size: 1.25rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 140ms, transform 140ms;
    z-index: 10000;
    backdrop-filter: blur(8px);
}
.photo-viewer-close:hover { background: rgba(255,255,255,0.24); transform: scale(1.08); }

/* Online badge in viewer */
.pv-online-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(16,185,129,0.15);
    border: 1px solid rgba(16,185,129,0.4);
    border-radius: 999px;
    padding: 0.3rem 0.85rem;
    font-size: 0.72rem;
    font-weight: 800;
    color: #34d399;
    margin-top: 0.5rem;
}
.pv-last-seen {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    color: rgba(255,255,255,0.38);
    font-size: 0.72rem;
    font-weight: 600;
    margin-top: 0.5rem;
}

/* Role badge colors */
.role-super-admin  { background: rgba(239,68,68,0.1);   color: #dc2626; }
.role-admin        { background: rgba(245,158,11,0.1);  color: #d97706; }
.role-boss         { background: rgba(139,92,246,0.1);  color: #7c3aed; }
.role-digital      { background: rgba(99,102,241,0.1);  color: #4f46e5; }
.role-crm          { background: rgba(14,165,233,0.1);  color: #0284c7; }
.role-sales        { background: rgba(16,185,129,0.1);  color: #059669; }
.role-default      { background: rgba(100,116,139,0.1); color: #475569; }

@keyframes cardFadeIn {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── Dark Mode ─────────────────────────────────────────────────────── */
[data-theme="dark"] .stat-card,
[data-theme="dark"] .search-bar   { background: rgba(15,23,42,0.85); border-color: rgba(51,65,85,0.9); }
[data-theme="dark"] .member-card  { background: rgba(15,23,42,0.85); border-color: rgba(51,65,85,0.9); }
[data-theme="dark"] .member-card:hover { border-color: rgba(96,165,250,0.32); box-shadow: 0 24px 60px rgba(0,0,0,0.4); }
[data-theme="dark"] .member-name  { color: #f1f5f9; }
[data-theme="dark"] .member-role  { color: #94a3b8; }
[data-theme="dark"] .member-team-role { color: #64748b; }
[data-theme="dark"] .card-contact-row { border-color: rgba(51,65,85,0.7); }
[data-theme="dark"] .card-contact-item { color: #94a3b8; }
[data-theme="dark"] .btn-phone    { background: rgba(30,41,59,0.9); color: #94a3b8; border-color: rgba(51,65,85,0.9); }
[data-theme="dark"] .btn-phone:hover { background: rgba(30,41,59,1); color: #93c5fd; border-color: rgba(96,165,250,0.4); }
[data-theme="dark"] .empty-members { background: rgba(15,23,42,0.6); border-color: rgba(51,65,85,0.8); }
[data-theme="dark"] .empty-icon   { background: rgba(30,41,59,0.8); }
[data-theme="dark"] .stat-card p.text-slate-950 { color: #f1f5f9 !important; }
[data-theme="dark"] .stat-card p.text-slate-500 { color: #94a3b8 !important; }
</style>

<div class="space-y-6 animate-fade-in" x-data="membersApp()" x-init="init()">

    {{-- ── Hero ────────────────────────────────────────────────────────── --}}
    <section class="members-hero">
        <div class="relative z-10">
            <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-black uppercase tracking-wider text-blue-100 mb-4">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
                </svg>
                Company Directory
            </div>
            <h1 class="text-3xl font-black leading-tight sm:text-4xl">All Members</h1>
            <p class="mt-2 max-w-xl text-sm font-medium text-blue-100 leading-relaxed">
                Find, connect, and learn more about team members across the organization.
            </p>
        </div>
    </section>

    {{-- ── Stats ───────────────────────────────────────────────────────── --}}
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <article class="stat-card">
            <div class="flex items-center justify-between">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/></svg>
                </div>
                <span class="rounded-full bg-indigo-50 px-2 py-1 text-[10px] font-black uppercase text-indigo-700">Team</span>
            </div>
            <p class="mt-5 font-display text-3xl font-black text-slate-950" x-text="totalFiltered"></p>
            <p class="mt-1 text-sm font-bold text-slate-500">Total Members</p>
        </article>
        <article class="stat-card">
            <div class="flex items-center justify-between">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </div>
                <span class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-black uppercase text-emerald-700">Live</span>
            </div>
            <p class="mt-5 font-display text-3xl font-black text-slate-950">{{ $stats['online_now'] }}</p>
            <p class="mt-1 text-sm font-bold text-slate-500">Online Now</p>
        </article>
        <article class="stat-card">
            <div class="flex items-center justify-between">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-sky-50 text-sky-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"/></svg>
                </div>
                <span class="rounded-full bg-sky-50 px-2 py-1 text-[10px] font-black uppercase text-sky-700">Today</span>
            </div>
            <p class="mt-5 font-display text-3xl font-black text-slate-950">{{ $stats['online_today'] }}</p>
            <p class="mt-1 text-sm font-bold text-slate-500">Active Today</p>
        </article>
    </section>

    {{-- ── Search & Filter ─────────────────────────────────────────────── --}}
    <section class="search-bar">
        <div class="flex flex-col gap-3 sm:flex-row">
            <div class="relative flex-1">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                </svg>
                <input type="text" id="member-search" x-model="search" @input="filterMembers()"
                       placeholder="Search by name, role, or position…"
                       class="w-full rounded-xl border border-slate-200 bg-slate-50/80 py-2.5 pl-10 pr-10 text-sm font-semibold text-slate-700 placeholder-slate-400 outline-none focus:border-indigo-400 focus:bg-white focus:ring-2 focus:ring-indigo-100 transition">
                <button type="button" x-show="search" @click="search=''; filterMembers()"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600" x-cloak>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Role filter --}}
            <div class="relative flex-shrink-0">
                <select x-model="selectedRole" @change="filterMembers()"
                        class="h-full w-full sm:w-auto min-w-[140px] appearance-none rounded-xl border border-slate-200 bg-slate-50/80 py-2.5 pl-4 pr-10 text-sm font-semibold text-slate-700 outline-none focus:border-indigo-400 focus:bg-white focus:ring-2 focus:ring-indigo-100 transition cursor-pointer">
                    <option value="">All Roles</option>
                    <template x-for="role in availableRoles" :key="role">
                        <option :value="role" x-text="role"></option>
                    </template>
                </select>
                <svg class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9"/>
                </svg>
            </div>

            <button type="button"
                    @click="onlineOnly = !onlineOnly; filterMembers()"
                    :class="onlineOnly ? 'bg-emerald-500 text-white border-emerald-500' : 'bg-white text-slate-600 border-slate-200'"
                    class="flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-black transition hover:border-emerald-400 flex-shrink-0">
                <span class="h-2 w-2 rounded-full" :class="onlineOnly ? 'bg-white' : 'bg-emerald-500'"></span>
                Online Only
            </button>
        </div>
        <p class="mt-3 text-xs font-bold text-slate-400" x-show="search || onlineOnly || selectedRole" x-cloak>
            Showing <span class="font-black text-slate-700" x-text="totalFiltered"></span> members
        </p>
    </section>

    {{-- ── Member Grid ─────────────────────────────────────────────────── --}}
    <section>
        <div class="empty-members" x-show="filteredMembers.length === 0" x-cloak>
            <div class="empty-icon">
                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/></svg>
            </div>
            <p class="text-lg font-black text-slate-700">No members found</p>
            <p class="mt-1 text-sm font-semibold text-slate-400">Try adjusting your search criteria.</p>
            <button type="button" x-show="search || onlineOnly || selectedRole" x-cloak
                    @click="search=''; onlineOnly=false; selectedRole=''; filterMembers()"
                    class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-indigo-50 px-4 py-2 text-sm font-black text-indigo-700 hover:bg-indigo-100 transition">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                Clear filters
            </button>
        </div>

        <div class="members-grid" x-show="filteredMembers.length > 0">
            <template x-for="(member, index) in filteredMembers" :key="member.id">
                <div class="member-card" :style="`animation-delay: ${Math.min(index * 35, 420)}ms`">

                    <div class="member-card-body">
                        {{-- Double-Clickable Avatar → opens full-screen viewer --}}
                        <div class="member-avatar-wrap" @dblclick="openPhotoViewer(member)" title="Double-click to view photo">
                            <img :src="member.avatar_url" :alt="member.name" class="member-avatar" loading="lazy">
                            {{-- Hover eye hint --}}
                            <div class="avatar-view-hint">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                </svg>
                            </div>
                            <span class="online-dot" x-show="member.is_online" title="Online now"></span>
                        </div>

                        <p class="member-name" x-text="member.name"></p>
                        <p class="member-role" x-text="member.role_display"></p>
                        <p class="member-team-role" x-show="member.team_role" x-text="member.team_role" x-cloak></p>
                        <span class="role-badge" :class="roleBadgeClass(member.role_slug)" x-text="member.role_display"></span>
                    </div>

                    {{-- ── Contact info block ── --}}
                    <div class="card-contact-row">
                        <template x-if="member.phone">
                            <div class="card-contact-item">
                                <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/>
                                </svg>
                                <span x-text="member.phone"></span>
                            </div>
                        </template>
                        <template x-if="member.whatsapp">
                            <div class="card-contact-item" style="color: #16a34a;">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                                    <path d="M12 0C5.373 0 0 5.373 0 12c0 2.127.557 4.12 1.527 5.848L.057 23.404l5.703-1.493A11.937 11.937 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.818 9.818 0 0 1-5.006-1.371l-.357-.212-3.706.97.99-3.618-.233-.372A9.781 9.781 0 0 1 2.182 12C2.182 6.58 6.58 2.182 12 2.182S21.818 6.58 21.818 12 17.42 21.818 12 21.818z"/>
                                </svg>
                                <span x-text="member.whatsapp"></span>
                            </div>
                        </template>
                        <template x-if="!member.phone && !member.whatsapp">
                            <div class="card-contact-item no-info">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                <span>No contact info</span>
                            </div>
                        </template>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="member-actions">
                        <a :href="member.whatsapp_url || '#'"
                           :class="{ 'is-disabled': !member.whatsapp_url }"
                           :target="member.whatsapp_url ? '_blank' : '_self'"
                           rel="noopener noreferrer"
                           class="btn-whatsapp">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                                <path d="M12 0C5.373 0 0 5.373 0 12c0 2.127.557 4.12 1.527 5.848L.057 23.404l5.703-1.493A11.937 11.937 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.818 9.818 0 0 1-5.006-1.371l-.357-.212-3.706.97.99-3.618-.233-.372A9.781 9.781 0 0 1 2.182 12C2.182 6.58 6.58 2.182 12 2.182S21.818 6.58 21.818 12 17.42 21.818 12 21.818z"/>
                            </svg>
                            WhatsApp
                        </a>
                        <a :href="member.phone_url || '#'"
                           :class="{ 'is-disabled': !member.phone_url }"
                           class="btn-phone">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/>
                            </svg>
                            Call
                        </a>
                    </div>
                </div>
            </template>
        </div>
    </section>

    {{-- ── Full-Screen Photo Viewer (Facebook-style) — image only ── --}}
    <div class="photo-viewer"
         x-show="viewerOpen"
         x-transition:enter="transition ease-out duration-180"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="closeViewer"
         @keydown.escape.window="closeViewer"
         x-cloak>

        {{-- Close Button --}}
        <button type="button"
                class="photo-viewer-close"
                @click.stop="closeViewer"
                aria-label="Close viewer">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
        </button>

        <div class="photo-viewer-inner">
            {{-- Large photo ONLY --}}
            <img :src="viewerMember?.avatar_url"
                 :alt="viewerMember?.name"
                 class="photo-viewer-img"
                 x-show="viewerMember"
                 @click.stop>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
const MEMBERS_DATA = @json($allMembers);

function membersApp() {
    return {
        allMembers: MEMBERS_DATA,
        filteredMembers: [],
        search: '',
        onlineOnly: false,
        selectedRole: '',
        // Full-screen photo viewer
        viewerOpen: false,
        viewerMember: null,

        init() {
            this.filteredMembers = this.allMembers;
            this.$watch('viewerOpen', value => {
                if (value) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            });
        },

        get availableRoles() {
            const roles = this.allMembers.map(m => m.role_display).filter(Boolean);
            return [...new Set(roles)].sort();
        },

        get totalFiltered() {
            return this.filteredMembers.length;
        },

        filterMembers() {
            const q = this.search.toLowerCase().trim();
            this.filteredMembers = this.allMembers.filter(m => {
                const matchSearch = !q || [m.name, m.role_display, m.team_role]
                    .some(f => (f || '').toLowerCase().includes(q));
                const matchOnline = !this.onlineOnly || m.is_online;
                const matchRole   = !this.selectedRole || m.role_display === this.selectedRole;
                return matchSearch && matchOnline && matchRole;
            });
        },

        openPhotoViewer(member) {
            this.viewerMember = member;
            this.viewerOpen   = true;
            document.body.style.overflow = 'hidden';
        },

        closeViewer() {
            this.viewerOpen = false;
            document.body.style.overflow = '';
        },

        roleBadgeClass(slug) {
            if (!slug) return 'role-default';
            if (slug.includes('super-admin'))   return 'role-super-admin';
            if (slug.includes('admin-digital')) return 'role-admin';
            if (slug.includes('admin-crm'))     return 'role-crm';
            if (slug.includes('boss'))          return 'role-boss';
            if (slug.includes('digital'))       return 'role-digital';
            if (slug.includes('sales'))         return 'role-sales';
            if (slug.includes('crm'))           return 'role-crm';
            return 'role-default';
        },
    };
}
</script>
@endpush
