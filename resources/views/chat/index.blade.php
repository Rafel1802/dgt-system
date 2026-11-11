@extends('layouts.app')

@section('title', 'DGT Chat — Real-Time Messaging')
@section('meta_description', 'DGT System enterprise real-time messaging system with team channels, direct chats, media sharing, and notifications.')

@push('head')
    <meta name="turbo-cache-control" content="no-cache">
@endpush

@section('content')
<div class="dgt-chat-wrapper w-full h-[calc(100vh-3.5rem)] lg:h-[calc(100vh-4.25rem)] overflow-hidden bg-slate-100 dark:bg-slate-950 flex flex-col"
     x-data="dgtChat({
         currentUser: {
             id: {{ $user->id }},
             name: @js($user->name),
             avatar_url: @js($user->avatar_url ?: \App\Models\User::initialsAvatarDataUri($user->name, $user->avatar_color)),
             team_role: @js($user->team_role ?? 'Team Member'),
             can_manage_permissions: {{ $user->canManageChatPermissions() ? 'true' : 'false' }}
         },
         conversations: @js($initialConversations),
         initialMessages: @js($initialMessages ?? (object)[]),
         usersDirectory: @js($usersDirectory),
         settings: @js($settings),
         iceServers: @js($iceServers ?? []),
         activeCall: @js($activeCall ?? null)
     })"
     x-cloak>

    <!-- Custom Chat Styles -->
    <style>
        :root {
            --chat-bubble-outgoing: {{ $settings->bubble_color ?? '#4f46e5' }};
        }
        html.is-chat-page,
        body.is-chat-page {
            height: 100vh !important;
            height: 100dvh !important;
            overflow: hidden !important;
        }
        .is-chat-page #dgt-app-wrapper,
        .is-chat-page .main-wrapper {
            height: 100vh !important;
            height: 100dvh !important;
            min-height: 0 !important;
            overflow: hidden !important;
        }
        .is-chat-page .page-content {
            padding: 0 !important;
            margin: 0 !important;
            height: calc(100vh - 4rem) !important;
            height: calc(100dvh - 4rem) !important;
            min-height: 0 !important;
            overflow: hidden !important;
        }
        body.is-chat-page .mobile-bottom-nav,
        .is-chat-page #mobile-bottom-nav {
            display: none !important;
        }
        html.dgt-mobile-app body.is-chat-page .page-content,
        html.dgt-mobile-app .is-chat-page .page-content,
        body.is-chat-page .mobile-page-content,
        body.is-chat-page .page-content {
            padding: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            margin: 0 !important;
        }
        @media (max-width: 767px) {
            /* Hide system topbar on mobile to eliminate double-header stack and maximize chat viewport */
            .is-chat-page .topbar,
            html.dgt-mobile-app .is-chat-page .topbar {
                display: none !important;
            }
            html.dgt-mobile-app body.is-chat-page .page-content,
            .is-chat-page .page-content {
                height: 100dvh !important;
                height: var(--mobile-chat-vh, 100dvh) !important;
                max-height: var(--mobile-chat-vh, 100dvh) !important;
                min-height: 0 !important;
                padding: 0 !important;
                padding-bottom: 0 !important;
                overflow: hidden !important;
            }
            .chat-mobile-safe-top {
                padding-top: max(0.75rem, var(--dgt-safe-top, env(safe-area-inset-top, 56px))) !important;
                padding-left: max(1rem, calc(env(safe-area-inset-left, 0px) + 1rem)) !important;
                padding-right: max(1rem, calc(env(safe-area-inset-right, 0px) + 1rem)) !important;
            }
            /* Prevent iOS Safari 16px auto-zoom */
            #chat-composer-textarea,
            .is-chat-page input,
            .is-chat-page textarea,
            .is-chat-page select {
                font-size: 16px !important;
            }
            .chat-panel-list {
                padding-bottom: calc(88px + var(--dgt-safe-bottom, env(safe-area-inset-bottom, 24px))) !important;
            }
        }
        .dgt-chat-wrapper {
            height: 100% !important;
            max-height: 100% !important;
            min-height: 0 !important;
            overflow: hidden !important;
            display: flex !important;
            flex-direction: column !important;
        }
        .chat-composer-footer {
            flex-shrink: 0 !important;
            position: sticky !important;
            bottom: 0 !important;
            z-index: 20 !important;
            padding-bottom: max(0.5rem, var(--dgt-safe-bottom, env(safe-area-inset-bottom, 0px))) !important;
        }
        /* ─── Premium Telegram / WhatsApp Rounded Chat Bubbles ──────── */
        .chat-bubble-mine {
            background-color: var(--chat-bubble-outgoing, #4f46e5);
            color: #ffffff;
            border-radius: 18px 18px 4px 18px !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08), 0 2px 6px rgba(79, 70, 229, 0.16);
        }
        .chat-bubble-theirs {
            background-color: #ffffff;
            color: #0f172a;
            border-radius: 18px 18px 18px 4px !important;
            border: 1px solid rgba(226, 232, 240, 0.85);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04), 0 1px 2px rgba(0, 0, 0, 0.02);
        }
        [data-theme="dark"] .chat-bubble-theirs,
        [data-theme="neon"] .chat-bubble-theirs,
        html.dark .chat-bubble-theirs,
        body.dark .chat-bubble-theirs {
            background-color: #1e293b;
            color: #f8fafc;
            border-color: rgba(51, 65, 85, 0.7);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.35);
        }
        .chat-wallpaper-default {
            background-color: #f1f5f9;
            background-image: url('https://img.miniexcavator.org/ebay/Dashboard-Icon/chatbg-light.webp');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        [data-theme="dark"] .chat-wallpaper-default,
        [data-theme="neon"] .chat-wallpaper-default,
        html.dark .chat-wallpaper-default,
        body.dark .chat-wallpaper-default {
            background-color: #0b1120;
            background-image: url('https://img.miniexcavator.org/ebay/Dashboard-Icon/chatbg-dark.webp');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        .chat-wallpaper-mesh {
            background: linear-gradient(135deg, #1e1b4b 0%, #0f172a 50%, #1e293b 100%);
        }
        .chat-wallpaper-indigo {
            background: linear-gradient(180deg, #e0e7ff 0%, #f8fafc 100%);
        }
        [data-theme="dark"] .chat-wallpaper-indigo {
            background: linear-gradient(180deg, #1e1b4b 0%, #090d16 100%);
        }
        .chat-wallpaper-emerald {
            background: radial-gradient(circle at top, #065f46 0%, #064e3b 35%, #022c22 75%, #021a14 100%);
        }
        .chat-wallpaper-doodle {
            background-color: #0e1621;
            background-image: radial-gradient(circle at 20% 30%, rgba(99, 102, 241, 0.15) 0%, transparent 40%),
                              radial-gradient(circle at 80% 70%, rgba(14, 165, 233, 0.12) 0%, transparent 40%),
                              radial-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px);
            background-size: 100% 100%, 100% 100%, 22px 22px;
        }
        /* Hide scrollbars but allow scrolling */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        /* Telegram / WhatsApp Ultra-Smooth Custom Scrollbar */
        .chat-scrollbar {
            scrollbar-width: thin;
            scrollbar-color: rgba(148, 163, 184, 0.3) transparent;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior-y: contain;
        }
        .chat-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
        .chat-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .chat-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.28);
            border-radius: 9999px;
            transition: background-color 0.2s ease;
        }
        .chat-scrollbar:hover::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.55);
        }
        .chat-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(99, 102, 241, 0.75);
        }

        /* Speech Bubble tails (Neutralized for ultra-clean Telegram / WhatsApp rounded aesthetic) */
        .bubble-tail-outgoing::after,
        .bubble-tail-incoming::before {
            display: none !important;
        }
        /* ─── Telegram Conversation Cards & Swipe Actions ──────────── */
        .telegram-swipe-container {
            position: relative;
            overflow: hidden;
            width: 100%;
            user-select: none;
            background-color: #ffffff;
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
        }
        [data-theme="dark"] .telegram-swipe-container,
        [data-theme="neon"] .telegram-swipe-container,
        html.dark .telegram-swipe-container,
        body.dark .telegram-swipe-container {
            background-color: #0b1120;
            border-bottom-color: rgba(30, 41, 59, 0.7);
        }

        /* Solid, 100% Opaque Front Card in all themes */
        .telegram-chat-card {
            position: relative;
            z-index: 10;
            width: 100%;
            background-color: #ffffff !important;
            transition: background-color 0.15s ease;
            user-select: none;
            touch-action: pan-y;
        }
        .telegram-chat-card:hover {
            background-color: #f8fafc !important;
        }
        [data-theme="dark"] .telegram-chat-card,
        html.dark .telegram-chat-card,
        body.dark .telegram-chat-card {
            background-color: #0f172a !important;
        }
        [data-theme="dark"] .telegram-chat-card:hover,
        html.dark .telegram-chat-card:hover,
        body.dark .telegram-chat-card:hover {
            background-color: #1e293b !important;
        }
        [data-theme="neon"] .telegram-chat-card {
            background-color: #071536 !important;
        }
        [data-theme="neon"] .telegram-chat-card:hover {
            background-color: #0c204e !important;
        }

        /* Active / Selected Conversation Card State - 100% Opaque, No Bleed-Through */
        .telegram-chat-card.is-active {
            background-color: #eef2ff !important;
            border-left: 4px solid #4f46e5 !important;
        }
        [data-theme="dark"] .telegram-chat-card.is-active,
        html.dark .telegram-chat-card.is-active,
        body.dark .telegram-chat-card.is-active {
            background-color: #1e293b !important;
            border-left: 4px solid #818cf8 !important;
        }
        [data-theme="neon"] .telegram-chat-card.is-active {
            background-color: #0f275e !important;
            border-left: 4px solid #38bdf8 !important;
        }

        /* Telegram Actions Layer - Hidden unless swiping or open */
        .telegram-actions-layer {
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            display: flex;
            height: 100%;
            align-items: stretch;
            z-index: 1;
            user-select: none;
        }
        .telegram-action-btn {
            width: 70px;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 0 4px;
            cursor: pointer;
            border: none;
            outline: none;
            color: #ffffff !important;
            text-align: center;
            transition: filter 0.15s ease, background-color 0.15s ease;
            user-select: none;
        }
        .telegram-action-btn:hover {
            filter: brightness(1.1);
        }
        .telegram-action-btn:active {
            filter: brightness(0.9);
        }
        .telegram-action-btn-pin {
            background-color: #00c73c !important; /* Telegram Green */
        }
        .telegram-action-btn-delete {
            background-color: #ff3b30 !important; /* Telegram Red */
        }
        .telegram-action-btn-archive {
            background-color: #8e8e93 !important; /* Telegram Grey */
        }
        .telegram-action-btn svg {
            width: 20px;
            height: 20px;
            color: #ffffff !important;
            stroke: #ffffff !important;
        }
        .telegram-action-btn-pin svg {
            fill: #ffffff !important;
        }
        .telegram-action-btn span {
            font-size: 11px;
            font-weight: 700;
            color: #ffffff !important;
            line-height: 1;
            letter-spacing: -0.01em;
        }

        /* Telegram Context Menu Styling */
        .telegram-context-menu {
            position: relative;
            z-index: 50;
            min-width: 220px;
            background-color: rgba(255, 255, 255, 0.98);
            border: 1px solid rgba(226, 232, 240, 0.9);
            border-radius: 14px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.25), 0 8px 10px -6px rgba(0, 0, 0, 0.15);
            padding: 6px 0;
            font-size: 12px;
            font-weight: 600;
            color: #1e293b;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }
        [data-theme="dark"] .telegram-context-menu,
        html.dark .telegram-context-menu,
        body.dark .telegram-context-menu {
            background-color: rgba(15, 23, 42, 0.97) !important;
            border-color: rgba(51, 65, 85, 0.8) !important;
            color: #f1f5f9 !important;
        }
        [data-theme="neon"] .telegram-context-menu {
            background-color: rgba(7, 21, 54, 0.98) !important;
            border-color: rgba(56, 189, 248, 0.3) !important;
            color: #f1f5f9 !important;
        }
        .telegram-context-menu-item {
            width: 100%;
            padding: 8px 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-align: left;
            cursor: pointer;
            transition: background-color 0.15s ease, color 0.15s ease;
            border: none;
            background: transparent;
            font-size: 12px;
            font-weight: 600;
            color: inherit;
        }
        .telegram-context-menu-item:hover {
            background-color: rgba(241, 245, 249, 0.9);
        }
        [data-theme="dark"] .telegram-context-menu-item:hover,
        html.dark .telegram-context-menu-item:hover {
            background-color: rgba(30, 41, 59, 0.9);
        }
        [data-theme="neon"] .telegram-context-menu-item:hover {
            background-color: rgba(14, 38, 92, 0.9);
        }
        .telegram-context-menu-item.danger {
            color: #ef4444 !important;
        }
        .telegram-context-menu-item.danger:hover {
            background-color: rgba(239, 68, 68, 0.15) !important;
        }

        /* ─── Real Native App Slide Navigation for Mobile Phones (< 768px) ───────────── */
        @media (max-width: 767px) {
            .chat-mobile-viewport {
                position: relative !important;
                width: 100% !important;
                height: 100% !important;
                overflow: hidden !important;
            }
            .chat-panel-list {
                position: absolute !important;
                inset: 0 !important;
                width: 100% !important;
                height: 100% !important;
                transform: translateX(0) !important;
                transition: transform 0.28s cubic-bezier(0.32, 0.72, 0, 1), opacity 0.28s ease !important;
                will-change: transform, opacity;
                z-index: 10 !important;
            }
            .chat-panel-list.is-shifted {
                transform: translateX(-28%) !important;
                opacity: 0.55 !important;
                pointer-events: none !important;
            }
            .chat-panel-active {
                position: absolute !important;
                inset: 0 !important;
                width: 100% !important;
                height: 100% !important;
                transform: translateX(100%) !important;
                transition: transform 0.28s cubic-bezier(0.32, 0.72, 0, 1) !important;
                z-index: 20 !important;
                box-shadow: -8px 0 24px rgba(0, 0, 0, 0.2) !important;
                will-change: transform;
                pointer-events: none !important;
            }
            .chat-panel-active.is-visible {
                transform: translateX(0) !important;
                pointer-events: auto !important;
            }
            .chat-panel-active.is-sliding-back {
                transform: translateX(100%) !important;
                pointer-events: none !important;
            }
        }

        /* ─── Standard WhatsApp & Telegram Multi-Panel UI (>= 768px) ───────────── */
        @media (min-width: 768px) {
            .chat-mobile-viewport {
                display: flex !important;
                flex-direction: row !important;
                width: 100% !important;
                height: 100% !important;
                min-height: 0 !important;
                overflow: hidden !important;
            }
            .chat-panel-list {
                position: relative !important;
                inset: auto !important;
                transform: none !important;
                opacity: 1 !important;
                pointer-events: auto !important;
                height: 100% !important;
                min-height: 0 !important;
                flex-shrink: 0 !important;
            }
            .chat-panel-active {
                position: relative !important;
                inset: auto !important;
                transform: none !important;
                flex: 1 1 0% !important;
                min-width: 0 !important;
                height: 100% !important;
                min-height: 0 !important;
                display: flex !important;
                flex-direction: column !important;
                box-shadow: none !important;
            }
        }
    </style>

    <!-- Main 3-Panel Workplace Layout -->
    <div class="flex-1 flex w-full h-full min-h-0 overflow-hidden relative chat-mobile-viewport">

        <!-- ========================================================================= -->
        <!-- PANEL 1: CONVERSATIONS LIST (LEFT PANEL)                                  -->
        <!-- ========================================================================= -->
        <aside class="w-full md:w-80 lg:w-88 xl:w-96 shrink-0 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 flex flex-col h-full min-h-0 z-20 chat-panel-list"
               :class="{
                   'is-shifted': activeConversation && !isSlidingBack && isMobile
               }">

            <!-- Panel 1 Header: Current User & Quick Actions -->
            <div class="p-3 sm:p-3.5 chat-mobile-safe-top border-b border-slate-200/80 dark:border-slate-800 flex items-center justify-between gap-2.5 bg-slate-50/80 dark:bg-slate-900/80 backdrop-blur-md shrink-0">
                <div class="flex items-center gap-2.5 min-w-0">
                    <!-- Mobile Dashboard Exit Button (Instant return to system dashboard) -->
                    <a href="{{ route('dashboard') }}"
                       class="md:hidden p-2 -ml-1 rounded-xl text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors flex items-center gap-1 shrink-0"
                       title="Exit to Dashboard">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                        </svg>
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300">App</span>
                    </a>

                    <div class="relative shrink-0">
                        <img src="{{ $user->avatar_url }}"
                             :src="avatarUrl(currentUser.avatar_url, currentUser.name)"
                             :alt="currentUser.name"
                             x-on:error="handleAvatarError($event, currentUser.name)"
                             onerror="this.onerror=null; this.src='{{ \App\Models\User::initialsAvatarDataUri($user->name, $user->avatar_color) }}';"
                             class="w-10 h-10 rounded-full object-cover ring-2 ring-indigo-500/30">
                        <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-slate-900"></span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 truncate" x-text="currentUser.name"></h2>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate" x-text="userSettings.status_message || currentUser.team_role"></p>
                    </div>
                </div>

                <!-- Action Buttons: Saved Messages, New Chat, New Group, Settings -->
                <div class="flex items-center gap-1">
                    <!-- Telegram-style Saved Messages (Personal Cloud) -->
                    <button type="button"
                            @click="openSavedMessages()"
                            class="p-2 rounded-xl text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                            title="Saved Messages (Personal Cloud Notes)">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                        </svg>
                    </button>

                    <button type="button"
                            @click="newChatModal = true"
                            class="p-2 rounded-xl text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                            title="New Direct Chat">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                        </svg>
                    </button>

                    <button type="button"
                            @click="newGroupModal = true"
                            class="p-2 rounded-xl text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                            title="New Group Chat">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                        </svg>
                    </button>

                    <button type="button"
                            @click="settingsModal = true"
                            class="p-2 rounded-xl text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                            title="Chat Appearance & Settings">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87l.22.127c.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992v.255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124l-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87l-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991v-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124l.22-.128c.332-.183.582-.495.644-.869l.214-1.28Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Panel 1 Search Bar -->
            <div class="px-3 pt-3 pb-2">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </div>
                    <input type="text"
                           x-model="searchQuery"
                           placeholder="Search conversations..."
                           class="w-full pl-9 pr-8 py-2 bg-slate-100 dark:bg-slate-800/80 text-xs sm:text-sm text-slate-900 dark:text-slate-100 rounded-xl border border-transparent focus:border-indigo-500 focus:bg-white dark:focus:bg-slate-800 focus:outline-none transition-all">
                    <button type="button"
                            x-show="searchQuery"
                            @click="searchQuery = ''"
                            class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Panel 1 Filter Tabs (Direct first, then All, Unread, Groups, Pinned, Archive) -->
            <div class="px-3 py-1.5 flex items-center gap-1 overflow-x-auto no-scrollbar border-b border-slate-200/60 dark:border-slate-800/60 text-xs">
                <button type="button"
                        @click="activeTab = 'direct'"
                        :class="activeTab === 'direct' ? 'bg-indigo-600 text-white font-semibold shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'"
                        class="px-2.5 py-1 rounded-lg shrink-0 transition-all">
                    Direct
                </button>
                <button type="button"
                        @click="activeTab = 'all'"
                        :class="activeTab === 'all' ? 'bg-indigo-600 text-white font-semibold shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'"
                        class="px-2.5 py-1 rounded-lg shrink-0 transition-all">
                    All
                </button>
                <button type="button"
                        @click="activeTab = 'unread'"
                        :class="activeTab === 'unread' ? 'bg-indigo-600 text-white font-semibold shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'"
                        class="px-2.5 py-1 rounded-lg shrink-0 transition-all flex items-center gap-1">
                    <span>Unread</span>
                    <span x-show="totalUnreadCount > 0"
                          class="px-1.5 py-0.2 rounded-full text-[10px] font-bold"
                          :class="activeTab === 'unread' ? 'bg-white text-indigo-700' : 'bg-indigo-600 text-white'"
                          x-text="totalUnreadCount"></span>
                </button>
                <button type="button"
                        @click="activeTab = 'groups'"
                        :class="activeTab === 'groups' ? 'bg-indigo-600 text-white font-semibold shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'"
                        class="px-2.5 py-1 rounded-lg shrink-0 transition-all">
                    Groups
                </button>
                <button type="button"
                        @click="activeTab = 'pinned'"
                        :class="activeTab === 'pinned' ? 'bg-indigo-600 text-white font-semibold shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'"
                        class="px-2.5 py-1 rounded-lg shrink-0 transition-all">
                    Pinned
                </button>
                <button type="button"
                        @click="activeTab = 'archived'"
                        :class="activeTab === 'archived' ? 'bg-indigo-600 text-white font-semibold shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'"
                        class="px-2.5 py-1 rounded-lg shrink-0 transition-all">
                    Archive
                </button>
            </div>

            <!-- Panel 1 Conversation Cards List (All users & conversations ready) -->
            <div class="flex-1 min-h-0 overflow-y-auto chat-scrollbar divide-y divide-slate-100 dark:divide-slate-800/60 overscroll-contain">

                <template x-for="conv in filteredConversations" :key="conv.id">
                    <!-- Telegram-Style Swipe Item Container -->
                    <div class="telegram-swipe-container"
                         x-data="telegramSwipeItem(conv)"
                         x-init="initSwipe()">

                        <!-- Telegram Action Buttons Layer (Revealed ONLY when card is swiped/dragged left or opened) -->
                        <div x-show="offset < -2 || isOpen"
                             x-cloak
                             class="telegram-actions-layer"
                             style="display: none;">
                            <!-- 1. Pin / Unpin (Telegram Green matching Image 2) -->
                            <button type="button"
                                    @click.stop="togglePin(conv); closeSwipe()"
                                    class="telegram-action-btn telegram-action-btn-pin"
                                    :title="conv.is_pinned ? 'Unpin chat' : 'Pin chat'">
                                <svg class="w-5 h-5 fill-white text-white" viewBox="0 0 24 24">
                                    <path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/>
                                </svg>
                                <span x-text="conv.is_pinned ? 'Unpin' : 'Pin'">Pin</span>
                            </button>

                            <!-- 2. Delete (Telegram Red matching Image 2) -->
                            <button type="button"
                                    @click.stop="deleteConversation(conv); closeSwipe()"
                                    class="telegram-action-btn telegram-action-btn-delete"
                                    title="Delete chat">
                                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                                <span>Delete</span>
                            </button>

                            <!-- 3. Archive / Unarchive (Telegram Grey matching Image 2) -->
                            <button type="button"
                                    @click.stop="toggleArchive(conv); closeSwipe()"
                                    class="telegram-action-btn telegram-action-btn-archive"
                                    :title="conv.is_archived ? 'Unarchive chat' : 'Archive chat'">
                                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                </svg>
                                <span x-text="conv.is_archived ? 'Unarchive' : 'Archive'">Archive</span>
                            </button>
                        </div>

                        <!-- Sliding Conversation Card (In front, 100% Opaque in all themes) -->
                        <div @click="onCardClick($event, conv, (c) => selectConversation(c))"
                             @mouseenter.once="prefetchMessages(conv.id)"
                             @touchstart.passive.once="prefetchMessages(conv.id)"
                             @pointerdown="onPointerDown($event, (e, c) => openConversationMenu(e, c))"
                             @pointermove="onPointerMove($event)"
                             @pointerup="onPointerUp($event)"
                             @pointercancel="onPointerCancel($event)"
                             @contextmenu.prevent="openConversationMenu($event, conv)"
                             :style="'transform: translateX(' + offset + 'px); ' + (isDragging ? 'transition: none;' : 'transition: transform 0.28s cubic-bezier(0.2, 0.9, 0.3, 1);')"
                             :class="{ 'is-active': activeConversation && activeConversation.id === conv.id }"
                             class="telegram-chat-card p-3 cursor-pointer flex items-center gap-3">

                            <!-- Avatar with online badge -->
                            <div class="relative shrink-0 pointer-events-none">
                                <img :src="avatarUrl(conv.avatar_url, conv.title)"
                                     :alt="conv.title"
                                     x-on:error="handleAvatarError($event, conv.title)"
                                     class="w-12 h-12 rounded-full object-cover">
                                <!-- Direct Chat Online status dot -->
                                <template x-if="conv.type === 'direct' || conv.type === 'private'">
                                    <span class="absolute bottom-0 right-0 w-3.5 h-3.5 rounded-full ring-2 ring-white dark:ring-slate-900"
                                          :class="conv.is_online ? 'bg-emerald-500 shadow-xs ring-emerald-100 dark:ring-emerald-950/80' : 'bg-slate-400'"></span>
                                </template>
                                <!-- Group Icon Badge -->
                                <template x-if="conv.type === 'group'">
                                    <span class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-indigo-600 text-white flex items-center justify-center text-[10px] ring-2 ring-white dark:ring-slate-900">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                        </svg>
                                    </span>
                                </template>
                            </div>

                            <!-- Card Content -->
                            <div class="flex-1 min-w-0 pointer-events-none">
                                <div class="flex items-center justify-between gap-1 mb-0.5">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100 truncate"
                                            x-text="conv.title"></h3>
                                        <template x-if="conv.nickname">
                                            <span class="inline-flex items-center px-1 py-0.2 rounded text-[9px] font-semibold bg-violet-50 dark:bg-violet-950/60 text-violet-600 dark:text-violet-400 border border-violet-200/60 dark:border-violet-800/40 shrink-0"
                                                  :title="'Original: ' + (conv.real_name || '')">
                                                Nick
                                            </span>
                                        </template>
                                    </div>

                                    <!-- Telegram Style Time & Read Checkmark (Matching Image 2) -->
                                    <div class="flex items-center gap-1 shrink-0 text-[11px] text-slate-400 dark:text-slate-500 font-medium">
                                        <!-- Outgoing checkmarks: 1 Blue Tick = Sent, 2 Green Ticks (Side-by-Side) = Seen -->
                                        <template x-if="conv.last_message && Number(conv.last_message.user_id) === Number(currentUser.id)">
                                            <span class="inline-flex items-center">
                                                <!-- Single Blue Tick if Sent and Not Seen -->
                                                <template x-if="!conv.last_message.is_seen && conv.last_message.status !== 'read'">
                                                    <span class="inline-flex items-center text-[#3b82f6] dark:text-[#38bdf8] drop-shadow-[0_0_2px_rgba(56,189,248,0.3)]" title="Sent">
                                                        <svg class="w-3.5 h-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2 8.5L6 12.5L14 3.5" />
                                                        </svg>
                                                    </span>
                                                </template>
                                                <!-- Double Green Ticks (WhatsApp Tight Parallel Style) if Seen -->
                                                <template x-if="conv.last_message.is_seen || conv.last_message.status === 'read'">
                                                    <span class="inline-flex items-center text-[#16a34a] dark:text-[#22c55e] drop-shadow-[0_0_2.5px_rgba(34,197,94,0.4)] font-bold" title="Seen">
                                                        <svg class="w-4 h-3.5" viewBox="0 0 19 16" fill="none" stroke="currentColor" stroke-width="2.1">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M1.5 8.5L5.5 12.5L13 3.5" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.2 11.2L9.5 12.5L17 3.5" />
                                                        </svg>
                                                    </span>
                                                </template>
                                            </span>
                                        </template>
                                        <template x-if="conv.last_message">
                                            <span x-text="formatDateSnippet(conv.last_message_at || conv.created_at)"></span>
                                        </template>
                                        <template x-if="!conv.last_message && (conv.type === 'direct' || conv.type === 'private')">
                                            <span class="text-[10px]" :class="conv.is_online ? 'text-emerald-500 font-semibold' : 'text-slate-400'" x-text="conv.is_online ? 'Online' : (conv.last_seen || 'Offline')"></span>
                                        </template>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between gap-2">
                                    <!-- 1. Live Typing Indicator in Sidebar List (Shows typing... like WhatsApp/Telegram) -->
                                    <template x-if="isConversationTyping(conv)">
                                        <p class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold italic truncate flex items-center gap-1.5 animate-pulse">
                                            <span class="inline-flex items-center gap-0.5">
                                                <span class="w-1 h-1 rounded-full bg-emerald-500 animate-bounce"></span>
                                                <span class="w-1 h-1 rounded-full bg-emerald-500 animate-bounce" style="animation-delay: 150ms"></span>
                                                <span class="w-1 h-1 rounded-full bg-emerald-500 animate-bounce" style="animation-delay: 300ms"></span>
                                            </span>
                                            <span x-text="getConversationTypingText(conv)"></span>
                                        </p>
                                    </template>

                                    <!-- 2. Standard Message Snippet (when not typing) -->
                                    <template x-if="!isConversationTyping(conv)">
                                        <p class="text-xs text-slate-500 dark:text-slate-400 truncate flex items-center gap-1">
                                            <template x-if="conv.last_message && Number(conv.last_message.user_id) === Number(currentUser.id)">
                                                <span class="text-indigo-600 dark:text-indigo-400 font-medium">You: </span>
                                            </template>
                                            <template x-if="conv.last_message && (conv.last_message.type === 'voice' || isVoiceOnly(conv.last_message) || (conv.last_message.attachments && conv.last_message.attachments.some(a => isVoiceAttachment(a, conv.last_message))))">
                                                <span class="inline-flex items-center gap-1 text-slate-700 dark:text-slate-200 font-medium">
                                                    <span>🎤</span>
                                                    <span>Voice message</span>
                                                </span>
                                            </template>
                                            <template x-if="conv.last_message && conv.last_message.type === 'sticker'">
                                                <span class="inline-flex items-center gap-1 text-slate-700 dark:text-slate-200 font-medium">
                                                    <span>🎨</span>
                                                    <span>Sticker</span>
                                                </span>
                                            </template>
                                            <template x-if="conv.last_message && conv.last_message.type === 'gif'">
                                                <span class="inline-flex items-center gap-1 text-slate-700 dark:text-slate-200 font-medium">
                                                    <span class="px-1 py-0.2 rounded bg-indigo-500/20 text-indigo-400 text-[10px] font-bold">GIF</span>
                                                </span>
                                            </template>
                                            <template x-if="conv.last_message && conv.last_message.attachments && conv.last_message.attachments.length > 0 && !conv.last_message.body && conv.last_message.type !== 'voice' && !isVoiceOnly(conv.last_message)">
                                                <span class="inline-flex items-center gap-1 text-slate-600 dark:text-slate-300">
                                                    <template x-if="isImageAttachment(conv.last_message.attachments[0])">
                                                        <span>📷 Photo</span>
                                                    </template>
                                                    <template x-if="isVideoAttachment(conv.last_message.attachments[0], conv.last_message)">
                                                        <span>🎬 Video</span>
                                                    </template>
                                                    <template x-if="isDocAttachment(conv.last_message.attachments[0], conv.last_message)">
                                                        <span>📎 [Attachment]</span>
                                                    </template>
                                                </span>
                                            </template>
                                            <template x-if="conv.last_message && conv.last_message.body && conv.last_message.type !== 'voice' && !isVoiceOnly(conv.last_message) && conv.last_message.type !== 'sticker' && conv.last_message.type !== 'gif'">
                                                <span x-text="conv.last_message.body"></span>
                                            </template>
                                            <template x-if="!conv.last_message">
                                                <span x-text="conv.team_role ? conv.team_role + ' • Tap to chat' : 'Tap to chat'"></span>
                                            </template>
                                        </p>
                                    </template>

                                    <!-- Status Icons & Unread Badge -->
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <!-- Muted Icon -->
                                        <template x-if="conv.is_muted">
                                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 9.75 19.5 12m0 0 2.25 2.25M19.5 12l2.25-2.25M19.5 12l-2.25 2.25m-10.5-6 4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" />
                                            </svg>
                                        </template>
                                        <!-- Pin Icon -->
                                        <template x-if="conv.is_pinned">
                                            <svg class="w-3.5 h-3.5 text-indigo-500 fill-indigo-500" viewBox="0 0 24 24">
                                                <path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/>
                                            </svg>
                                        </template>
                                        <!-- Unread Badge -->
                                        <template x-if="conv.unread_count > 0">
                                            <span class="flex h-5 min-w-[20px] px-1.5 items-center justify-center rounded-full bg-rose-500 text-[10px] font-black text-white shadow-sm shadow-rose-500/40 ring-1 ring-white/30"
                                                  x-text="conv.unread_count > 99 ? '99+' : conv.unread_count"></span>
                                        </template>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </template>

                <!-- Empty State if no conversations match filter -->
                <div x-show="filteredConversations.length === 0"
                     class="py-16 px-6 text-center text-slate-400">
                    <svg class="w-12 h-12 mx-auto mb-3 text-slate-300 dark:text-slate-700" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                    </svg>
                    <p class="text-sm font-semibold text-slate-600 dark:text-slate-300"
                       x-text="activeTab === 'archived' ? 'No archived chats' : (activeTab === 'direct' ? 'No direct conversations yet' : 'No conversations found')"></p>
                    <p class="text-xs text-slate-400 mt-1"
                       x-text="activeTab === 'archived' ? 'Chats you archive will be kept here safely. Swipe left on any chat to archive it.' : (activeTab === 'direct' ? 'Start a 1-on-1 chat anytime with any team member from the All tab' : 'Start a new private or group chat anytime')"></p>
                    <button type="button"
                            x-show="activeTab !== 'archived'"
                            @click="newChatModal = true"
                            class="mt-4 px-3 py-1.5 rounded-xl bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 transition-colors shadow-sm">
                        + Start New Chat
                    </button>
                </div>

                <!-- Matching Team Members from Search -->
                <div x-show="searchQuery && matchingSearchUsers.length > 0"
                     class="p-2 border-t border-slate-200/60 dark:border-slate-800/60 bg-slate-50/50 dark:bg-slate-900/30">
                    <p class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Team Members</p>
                    <template x-for="u in matchingSearchUsers" :key="'search-member-' + u.id">
                        <div @click="startPrivateChat(u.id)"
                             class="p-2 rounded-xl flex items-center justify-between hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer transition-colors group">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="relative shrink-0">
                                    <img :src="avatarUrl(u.avatar_url, u.name)" :alt="u.name" class="w-8 h-8 rounded-full object-cover">
                                    <span class="absolute bottom-0 right-0 w-2 h-2 rounded-full ring-2 ring-white dark:ring-slate-900"
                                          :class="u.is_online ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400" x-text="u.name"></p>
                                    <p class="text-[10px] text-slate-400 truncate flex items-center gap-1">
                                        <span x-text="u.is_online ? 'Online' : (u.last_seen || 'Offline')"></span>
                                        <span>•</span>
                                        <span x-text="u.team_role"></span>
                                    </p>
                                </div>
                            </div>
                            <span class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400">Chat</span>
                        </div>
                    </template>
                </div>

            </div>

            <!-- Telegram-Style Mobile Floating Action Button (New Chat) -->
            <button type="button"
                    @click="newChatModal = true"
                    class="md:hidden fixed bottom-5 right-5 z-30 w-13 h-13 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white shadow-xl shadow-indigo-600/35 flex items-center justify-center active:scale-90 transition-transform cursor-pointer"
                    title="New Message">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                </svg>
            </button>

        </aside>

        <!-- ========================================================================= -->
        <!-- PANEL 2: ACTIVE CONVERSATION (CENTER PANEL)                               -->
        <!-- ========================================================================= -->
        <main class="flex-1 min-w-0 flex flex-col h-full min-h-0 relative overflow-hidden bg-slate-100 dark:bg-slate-950 chat-panel-active"
              @touchstart="handlePanelTouchStart($event)"
              @touchmove="handlePanelTouchMove($event)"
              @touchend="handlePanelTouchEnd($event)"
              :class="{
                  'is-visible': activeConversation && !isSlidingBack,
                  'is-sliding-back': isSlidingBack,
                  'hidden md:flex': !activeConversation && !isSlidingBack && !isMobile
              }">

            <!-- When NO conversation is active -->
            <div x-show="!activeConversation"
                 class="flex-1 flex flex-col items-center justify-center p-8 text-center bg-slate-50 dark:bg-slate-900/40">
                <div class="w-20 h-20 rounded-3xl bg-gradient-to-tr from-indigo-600 to-blue-500 text-white flex items-center justify-center shadow-xl shadow-indigo-500/20 mb-6">
                    <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a.75.75 0 0 1-.974-.948l.783-2.35A7.47 7.47 0 0 1 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100">Welcome to DGT Chat</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-2 max-w-sm">
                    Select a conversation from the left to start messaging, or initiate a new one-to-one or team group chat.
                </p>
                <div class="flex items-center gap-3 mt-6">
                    <button type="button"
                            @click="newChatModal = true"
                            class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-md shadow-indigo-500/20 transition-all">
                        + New Direct Chat
                    </button>
                    <button type="button"
                            @click="newGroupModal = true"
                            class="px-4 py-2 rounded-xl bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-100 text-xs font-semibold transition-all">
                        + Create Team Group
                    </button>
                </div>
            </div>

            <!-- When a conversation IS active -->
            <div x-show="activeConversation" class="flex-1 flex flex-col h-full min-h-0 overflow-hidden">

                <!-- Center Panel Header (Telegram Mobile & Desktop Responsive) -->
                <div class="min-h-[3.5rem] sm:h-16 px-2.5 sm:px-4 py-1.5 chat-mobile-safe-top border-b border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/90 backdrop-blur-md flex items-center justify-between gap-2 sm:gap-3 shrink-0 z-10 select-none">

                    <!-- Mobile Back button (Telegram-style slide back with unread badge) -->
                    <button type="button"
                            @click="backToConversations()"
                            class="md:hidden p-1.5 -ml-1 rounded-xl text-slate-600 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 active:scale-95 transition-all flex items-center gap-0.5 shrink-0 cursor-pointer"
                            title="Back to chats"
                            aria-label="Back to chats">
                        <svg class="w-5 h-5 flex-shrink-0 text-indigo-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                        </svg>
                        <template x-if="totalUnreadCount > 0">
                            <span class="px-1.5 py-0.2 rounded-full bg-indigo-600 text-white text-[10px] font-black leading-tight"
                                  x-text="totalUnreadCount > 99 ? '99+' : totalUnreadCount"></span>
                        </template>
                    </button>

                    <!-- Active Chat Profile Info (Tap anywhere here to open Telegram Contact/Group Profile Sheet) -->
                    <div class="flex items-center gap-2.5 sm:gap-3 min-w-0 flex-1 cursor-pointer group/header"
                         @click="openProfileModal('details')">
                        <div class="relative shrink-0">
                            <img :src="avatarUrl(activeConversation?.avatar_url, activeConversation?.title)"
                                 :alt="activeConversation?.title"
                                 x-on:error="handleAvatarError($event, activeConversation?.title)"
                                 class="w-9 h-9 sm:w-10 sm:h-10 rounded-full object-cover">
                            <!-- Direct Chat Online status dot -->
                            <span x-show="activeConversation?.type !== 'group'"
                                  class="absolute bottom-0 right-0 w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full ring-2 ring-white dark:ring-slate-900"
                                  :class="activeConversation?.is_online ? 'bg-emerald-500 shadow-xs ring-emerald-100 dark:ring-emerald-950/80' : 'bg-slate-400'"></span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 truncate group-hover/header:text-indigo-600 dark:group-hover/header:text-indigo-400 transition-colors"
                                    x-text="activeConversation?.title"></h3>
                                <template x-if="activeConversation?.nickname">
                                    <button type="button"
                                            @click.stop="openNicknameModal(activeConversation)"
                                            class="hidden sm:inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-semibold bg-violet-50 dark:bg-violet-950/60 text-violet-600 dark:text-violet-400 border border-violet-200 dark:border-violet-800/60 shrink-0 hover:bg-violet-100 dark:hover:bg-violet-900/60 cursor-pointer transition-colors"
                                            :title="'Click to edit. Original name: ' + (activeConversation?.real_name || '')">
                                        Nickname
                                    </button>
                                </template>
                                <template x-if="activeConversation?.is_archived">
                                    <span class="hidden sm:inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[10px] font-semibold bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 shrink-0">
                                        <svg class="w-3 h-3 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                        </svg>
                                        Archived
                                    </span>
                                </template>
                            </div>
                            <div class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 truncate flex items-center gap-1.5">
                                <template x-if="activeConversation?.nickname && activeConversation?.real_name">
                                    <span class="hidden sm:inline text-slate-400 dark:text-slate-500" x-text="'(' + activeConversation.real_name + ') • '"></span>
                                </template>

                                <!-- 1. Typing Indicator: Shows 'typing...' like Telegram/WhatsApp -->
                                <div x-show="typingUsers && typingUsers.length > 0"
                                     class="text-emerald-600 dark:text-emerald-400 font-semibold italic flex items-center gap-1.5 transition-all">
                                    <span x-text="activeConversation?.type === 'group' ? (typingUsers.join(', ') + ' typing') : 'typing'"></span>
                                    <span class="inline-flex items-center gap-0.5 ml-0.5">
                                        <span class="w-1 h-1 rounded-full bg-emerald-500 animate-bounce"></span>
                                        <span class="w-1 h-1 rounded-full bg-emerald-500 animate-bounce" style="animation-delay: 150ms"></span>
                                        <span class="w-1 h-1 rounded-full bg-emerald-500 animate-bounce" style="animation-delay: 300ms"></span>
                                    </span>
                                </div>

                                <!-- 2. Group Chat Members Count -->
                                <div x-show="(!typingUsers || typingUsers.length === 0) && activeConversation?.type === 'group'"
                                     class="text-slate-500 dark:text-slate-400">
                                    <span x-text="(activeConversation?.members?.length || 0) + ' members'"></span>
                                </div>

                                <!-- 3. Direct/Colleague Chat: Online Status or Last Seen -->
                                <div x-show="(!typingUsers || typingUsers.length === 0) && activeConversation?.type !== 'group'"
                                     class="inline-flex items-center gap-1.5"
                                     :class="activeConversation?.is_online ? 'text-emerald-600 dark:text-emerald-400 font-medium' : 'text-slate-400 dark:text-slate-500'">
                                    <span x-show="activeConversation?.is_online" class="inline-block w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full bg-emerald-500 shrink-0"></span>
                                    <span x-text="activeConversation?.is_online ? 'online' : (activeConversation?.last_seen || 'offline')"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Header Actions -->
                    <div class="flex items-center gap-0.5 sm:gap-1 shrink-0">
                        <!-- Real-time Voice & Video Call Buttons (Direct chats, excluding Saved Messages) -->
                        <template x-if="activeConversation && activeConversation.type !== 'group' && activeConversation.title !== 'Saved Messages'">
                            <div class="flex items-center gap-0.5">
                                <button type="button"
                                        @click="startVoiceCall(activeConversation?.other_user_id || activeConversation?.other_user?.id)"
                                        class="p-2 rounded-xl text-slate-500 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 transition-all cursor-pointer group"
                                        title="Start Real-Time Voice Call">
                                    <svg class="w-5 h-5 transition-transform group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                                    </svg>
                                </button>
                                <button type="button"
                                        @click="startVideoCall(activeConversation?.other_user_id || activeConversation?.other_user?.id)"
                                        class="p-2 rounded-xl text-slate-500 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 transition-all cursor-pointer group"
                                        title="Start Real-Time Video Call">
                                    <svg class="w-5 h-5 transition-transform group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                                    </svg>
                                </button>
                            </div>
                        </template>

                        <!-- Toggle in-chat search (Desktop/Tablet icon; on mobile inside ⋮ menu to save header width) -->
                        <button type="button"
                                @click="showSearchInChat = !showSearchInChat; if (showSearchInChat) $nextTick(() => document.getElementById('chat-search-input')?.focus());"
                                :class="showSearchInChat ? 'bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800'"
                                class="hidden sm:inline-flex p-2 rounded-xl transition-colors"
                                title="Search messages in this chat">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                        </button>

                        <!-- Toggle Profile & Info Modal (Desktop/Tablet icon; on mobile tap header or ⋮ menu) -->
                        <button type="button"
                                @click="openProfileModal('details')"
                                :class="profileModal ? 'bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800'"
                                class="hidden sm:inline-flex p-2 rounded-xl transition-colors cursor-pointer"
                                title="Contact / Group Profile">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                            </svg>
                        </button>

                        <!-- More Options Dropdown -->
                        <div class="relative" x-data="{ open: false }">
                            <button type="button"
                                    @click="open = !open"
                                    class="p-2 rounded-xl text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" />
                                </svg>
                            </button>
                            <div x-show="open"
                                 @click.away="open = false"
                                 x-transition
                                 class="absolute right-0 mt-2 w-52 bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200/80 dark:border-slate-700 py-1.5 z-40 text-xs text-slate-700 dark:text-slate-200">
                                <!-- Search in Chat (especially handy on mobile) -->
                                <button type="button"
                                        @click="showSearchInChat = true; open = false; $nextTick(() => document.getElementById('chat-search-input')?.focus());"
                                        class="w-full px-3.5 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700/60 flex items-center gap-2.5">
                                    <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                    </svg>
                                    <span>Search in Chat</span>
                                </button>
                                <!-- Contact / Group Info -->
                                <button type="button"
                                        @click="openProfileModal('details'); open = false;"
                                        class="w-full px-3.5 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700/60 flex items-center gap-2.5">
                                    <svg class="w-4 h-4 text-sky-500" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                    </svg>
                                    <span x-text="activeConversation?.type === 'group' ? 'Group Info & Media' : 'Contact Info & Media'"></span>
                                </button>
                                <div class="h-px bg-slate-100 dark:bg-slate-700/60 my-1"></div>
                                <button type="button"
                                        @click="togglePin(activeConversation); open = false"
                                        class="w-full px-3.5 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700/60 flex items-center gap-2.5">
                                    <svg class="w-4 h-4 text-emerald-500 fill-emerald-500" viewBox="0 0 24 24">
                                        <path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/>
                                    </svg>
                                    <span x-text="activeConversation?.is_pinned ? 'Unpin Conversation' : 'Pin to Top'"></span>
                                </button>
                                <button type="button"
                                        @click="toggleMute(activeConversation); open = false"
                                        class="w-full px-3.5 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700/60 flex items-center gap-2.5">
                                    <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                                    </svg>
                                    <span x-text="activeConversation?.is_muted ? 'Unmute Notifications' : 'Mute Notifications'"></span>
                                </button>
                                <button type="button"
                                        @click="openNicknameModal(activeConversation); open = false"
                                        class="w-full px-3.5 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700/60 flex items-center gap-2.5">
                                    <svg class="w-4 h-4 text-violet-500" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                    </svg>
                                    <span x-text="activeConversation?.nickname ? 'Edit Nickname' : 'Set Nickname'"></span>
                                </button>
                                <button type="button"
                                        @click="toggleArchive(activeConversation); open = false"
                                        class="w-full px-3.5 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700/60 flex items-center gap-2.5">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                    </svg>
                                    <span x-text="activeConversation?.is_archived ? 'Unarchive' : 'Archive Conversation'"></span>
                                </button>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Slide-down Search In Chat Bar -->
                <div x-show="showSearchInChat"
                     x-transition
                     class="px-3 sm:px-4 py-2 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 flex items-center gap-2">
                    <input type="text"
                           id="chat-search-input"
                           x-model="searchInChat"
                           placeholder="Search messages in this chat..."
                           class="flex-1 px-3 py-1.5 text-xs bg-white dark:bg-slate-800 rounded-lg border border-slate-300 dark:border-slate-700 focus:outline-none focus:border-indigo-500">
                    <button type="button"
                            @click="showSearchInChat = false; searchInChat = ''"
                            class="text-xs text-slate-500 hover:text-slate-700 font-semibold px-2 py-1">
                        Close
                    </button>
                </div>

                <!-- Archived Banner Notification -->
                <div x-show="activeConversation?.is_archived"
                     x-transition
                     class="px-4 py-2 bg-slate-100/90 dark:bg-slate-900/90 border-b border-slate-200/80 dark:border-slate-800/80 flex items-center justify-between text-xs text-slate-600 dark:text-slate-400 shrink-0">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                        </svg>
                        <span>This chat is archived. You won't receive notifications unless unarchived.</span>
                    </div>
                    <button type="button"
                            @click="toggleArchive(activeConversation)"
                            class="px-2.5 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-[11px] transition-colors shadow-sm">
                        Unarchive
                    </button>
                </div>

                <!-- Pinned Message Banner (Telegram & WhatsApp Style) -->
                <div x-show="getPinnedMessage()"
                     x-transition
                     class="px-4 py-2 bg-white/95 dark:bg-slate-900/95 border-b border-indigo-100 dark:border-slate-800 shadow-xs flex items-center justify-between gap-3 text-xs shrink-0 select-none">
                    <div class="flex items-center gap-2.5 min-w-0 flex-1 cursor-pointer"
                         @click="scrollToMessage(activeConversation?.pinned_message_id)">
                        <div class="w-6 h-6 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m15 11.25 1.5 1.5.75-.75-3.75-3.75-.75.75 1.5 1.5L9.75 15l-3 1.5L8.25 18 3 21l3-5.25 1.5 1.5 1.5-3 4.5-4.5Z" />
                            </svg>
                        </div>
                        <div class="truncate">
                            <span class="font-bold text-indigo-600 dark:text-indigo-400">Pinned Message:</span>
                            <span class="text-slate-600 dark:text-slate-300 ml-1 font-medium" x-text="getPinnedMessageText()"></span>
                        </div>
                    </div>
                    <button type="button"
                            @click="togglePinMessage(getPinnedMessage())"
                            title="Unpin message"
                            class="p-1 rounded-md text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Center Messages Stream Container -->
                <div id="chat-messages-container"
                     @dragenter.prevent="isDraggingOverChat = true"
                     @dragover.prevent="isDraggingOverChat = true"
                     @dragleave="onChatDragLeave($event)"
                     @drop.prevent="onChatDrop($event)"
                     @scroll="handleMessagesScroll($event); closeContextMenu(); closeConversationMenu();"
                     :class="'chat-wallpaper-' + (userSettings.wallpaper || 'default')"
                     :style="userSettings.wallpaper === 'custom' && userSettings.wallpaper_image ? `background-image: url('${userSettings.wallpaper_image}'); background-size: cover; background-position: center;` : ''"
                     class="flex-1 min-h-0 overflow-y-auto chat-scrollbar p-2.5 sm:p-4 space-y-2 sm:space-y-3 relative">

                    <!-- Telegram-Style Full Drag & Drop Overlay -->
                    <div x-show="isDraggingOverChat"
                         x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute inset-3 z-40 rounded-3xl bg-indigo-900/60 dark:bg-slate-900/80 backdrop-blur-md border-2 border-dashed border-indigo-400 dark:border-sky-400 flex flex-col items-center justify-center pointer-events-none p-6 text-center shadow-2xl">
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-indigo-600 to-sky-500 text-white flex items-center justify-center shadow-xl shadow-indigo-500/30 mb-4 animate-bounce">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-white mb-1">Drop files here to send</h3>
                        <p class="text-xs text-indigo-100/80 max-w-xs">Drop images, documents, or media to attach directly to this chat</p>
                    </div>

                    <!-- Loading Indicator -->
                    <div x-show="isLoadingMessages"
                         class="flex justify-center py-6">
                        <div class="px-4 py-1.5 rounded-full bg-white/80 dark:bg-slate-800/80 backdrop-blur-sm text-xs font-semibold text-slate-500 shadow-sm flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin text-indigo-600" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span>Loading messages...</span>
                        </div>
                    </div>

                    <!-- Date Separator Badge Example -->
                    <div class="flex justify-center my-3">
                        <span class="px-3 py-1 rounded-full bg-slate-200/80 dark:bg-slate-800/80 backdrop-blur-sm text-[11px] font-semibold text-slate-600 dark:text-slate-400 shadow-sm">
                            Conversation History
                        </span>
                    </div>

                    <!-- Message Bubble Template -->
                    <template x-for="msg in messages.filter(m => (!searchInChat || (m.body || m.content || '').toLowerCase().includes(searchInChat.toLowerCase())) && (((m.body || m.content || '').trim().length > 0) || (m.attachments && m.attachments.length > 0) || m.is_unsent || m.type === 'call_event' || m.call || m.poll_data || m.type === 'voice' || isVoiceOnly(m)))" :key="msg.id">
                        <div class="flex flex-col group"
                             :class="Number(msg.user_id) === Number(currentUser.id) ? 'items-end' : 'items-start'">

                            <!-- Sender name for group chats -->
                            <template x-if="activeConversation?.type === 'group' && Number(msg.user_id) !== Number(currentUser.id)">
                                <span class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 ml-10 mb-1"
                                      x-text="msg.user?.name"></span>
                            </template>

                            <div class="flex items-end gap-1.5 sm:gap-2 max-w-[88%] sm:max-w-[75%] md:max-w-[65%]">

                                <!-- Selection Checkbox (When selecting messages mode is active) -->
                                <template x-if="isSelectingMessages">
                                    <button type="button"
                                            @click.stop="toggleSelectMessage(msg)"
                                            class="w-6 h-6 rounded-full flex items-center justify-center shrink-0 mb-2 cursor-pointer transition-transform active:scale-90"
                                            :class="selectedMessageIds.includes(msg.id) ? 'bg-indigo-600 text-white shadow-xs' : 'border-2 border-slate-300 dark:border-slate-600 hover:border-indigo-500 bg-white/50 dark:bg-slate-800/50'">
                                        <template x-if="selectedMessageIds.includes(msg.id)">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                            </svg>
                                        </template>
                                    </button>
                                </template>

                                <!-- Other user avatar in group -->
                                <template x-if="activeConversation?.type === 'group' && Number(msg.user_id) !== Number(currentUser.id)">
                                    <img :src="avatarUrl(msg.user?.avatar_url, msg.user?.name)"
                                         :alt="msg.user?.name"
                                         x-on:error="handleAvatarError($event, msg.user?.name)"
                                         class="w-7 h-7 rounded-full object-cover shrink-0 mb-1">
                                </template>

                                <!-- Message Bubble Box -->
                                <div class="relative transition-all text-sm select-text cursor-context-menu"
                                     :id="'msg-' + msg.id"
                                     :data-msg-id="msg.id"
                                     @contextmenu.prevent="openContextMenu($event, msg)"
                                     x-on:touchstart="handleMessageTouchStart($event, msg)"
                                     x-on:touchmove="handleMessageTouchMove($event)"
                                     x-on:touchend="handleMessageTouchEnd($event)"
                                     x-on:touchcancel="handleMessageTouchEnd($event)"
                                     :style="swipeReplyMsgId === msg.id ? `transform: translateX(${swipeReplyOffset}px); transition: none;` : 'transition: transform 0.2s ease-out;'"
                                     :class="{
                                         'bg-transparent shadow-none border-0 p-0 select-none': msg.type === 'sticker',
                                         'chat-bubble-mine rounded-2xl rounded-br-xs shadow-md p-2 sm:p-2.5 select-none': isVoiceOnly(msg) && Number(msg.user_id) === Number(currentUser.id),
                                         'chat-bubble-theirs rounded-2xl rounded-bl-xs shadow-xs p-2 sm:p-2.5 select-none': isVoiceOnly(msg) && Number(msg.user_id) !== Number(currentUser.id),
                                         'chat-bubble-mine rounded-2xl rounded-br-xs px-3 py-1.5 sm:px-4 sm:py-2.5 shadow-sm': !isVoiceOnly(msg) && msg.type !== 'sticker' && Number(msg.user_id) === Number(currentUser.id),
                                         'chat-bubble-theirs rounded-2xl rounded-bl-xs px-3 py-1.5 sm:px-4 sm:py-2.5 shadow-sm': !isVoiceOnly(msg) && msg.type !== 'sticker' && Number(msg.user_id) !== Number(currentUser.id),
                                         'ring-2 ring-indigo-500/80 ring-offset-2': isSelectingMessages && selectedMessageIds.includes(msg.id)
                                     }">

                                    <!-- WhatsApp-Style Dropdown Chevron Trigger -->
                                    <button type="button"
                                            data-chat-chevron="true"
                                            @click.stop="openContextMenu($event, msg)"
                                            title="Message options"
                                            class="absolute top-1.5 right-1.5 opacity-70 md:opacity-0 group-hover:opacity-100 hover:opacity-100 focus:opacity-100 transition-opacity w-6 h-6 flex items-center justify-center rounded-full z-30 cursor-pointer shadow-md pointer-events-auto"
                                            :class="Number(msg.user_id) === Number(currentUser.id)
                                                ? 'text-white/95 hover:text-white bg-black/40 hover:bg-black/60 backdrop-blur-xs'
                                                : 'text-slate-500 hover:text-slate-800 dark:hover:text-white bg-slate-200/90 hover:bg-slate-300 dark:bg-slate-700/90 dark:hover:bg-slate-600 backdrop-blur-xs'">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>

                                    <!-- Forwarded banner -->
                                    <template x-if="msg.forward_from">
                                        <div class="mb-1.5 pb-1 border-b border-black/10 dark:border-white/10 text-[11px] font-medium opacity-85 flex items-center gap-1.5 italic"
                                             :class="Number(msg.user_id) === Number(currentUser.id) ? 'text-indigo-100' : 'text-indigo-600 dark:text-indigo-400'">
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m15 15 6-6m0 0-6-6m6 6H9a6 6 0 0 0 0 12h3" />
                                            </svg>
                                            <span>Forwarded from <strong class="not-italic font-bold" x-text="msg.forward_from.user_name"></strong></span>
                                        </div>
                                    </template>

                                    <!-- Reply preview if replying to another message -->
                                    <template x-if="msg.reply_to && !msg.is_unsent">
                                        <div class="mb-2 p-2 rounded-lg border-l-4 text-xs bg-black/10 dark:bg-white/10"
                                             :class="Number(msg.user_id) === Number(currentUser.id) ? 'border-white text-white/90' : 'border-indigo-600 text-slate-600 dark:text-slate-300'">
                                            <p class="font-bold text-[11px]" x-text="msg.reply_to.user_name || msg.reply_to.name"></p>
                                            <p class="truncate" x-text="msg.reply_to.body || msg.reply_to.content"></p>
                                        </div>
                                    </template>

                                    <!-- Voice Call Event Bubble (Telegram / WhatsApp Style) -->
                                    <template x-if="msg.type === 'call_event' || msg.call">
                                        <div class="flex items-center gap-3 py-1 min-w-[210px] sm:min-w-[250px]">
                                            <!-- Call Icon Indicator -->
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 shadow-xs"
                                                 :class="{
                                                     'bg-rose-500/20 text-rose-500': (msg.call?.status === 'missed' || msg.call?.status === 'declined'),
                                                     'bg-emerald-500/20 text-emerald-500': (msg.call?.status === 'completed' || msg.call?.status === 'answered'),
                                                     'bg-indigo-500/20 text-indigo-400': (msg.call?.status === 'cancelled' || !msg.call?.status)
                                                 }">
                                                <template x-if="msg.call?.status === 'missed' || msg.call?.status === 'declined'">
                                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 9-7.5 7.5m0-7.5 7.5 7.5" />
                                                    </svg>
                                                </template>
                                                <template x-if="msg.call?.status !== 'missed' && msg.call?.status !== 'declined'">
                                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                                                    </svg>
                                                </template>
                                            </div>

                                            <!-- Call Details -->
                                            <div class="flex-1 min-w-0">
                                                <h4 class="text-xs font-bold truncate"
                                                    x-text="msg.call?.status === 'missed' ? 'Missed Voice Call' : (msg.call?.status === 'declined' ? 'Declined Call' : 'Voice Call')"></h4>
                                                <p class="text-[10px] opacity-75 truncate">
                                                    <span x-text="msg.call?.formatted_duration || (msg.call?.duration_seconds ? formatCallDuration(msg.call.duration_seconds) : (msg.call?.status === 'missed' ? 'No answer' : 'Voice call'))"></span>
                                                </p>
                                            </div>

                                            <!-- Call Back Quick Action Button -->
                                            <template x-if="activeConversation?.type === 'direct' || activeConversation?.type === 'private'">
                                                <button type="button"
                                                        @click="startVoiceCall()"
                                                        class="p-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white shrink-0 shadow-sm transition-transform active:scale-95 cursor-pointer"
                                                        title="Call Back">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                                                    </svg>
                                                </button>
                                            </template>
                                        </div>
                                    </template>

                                    <!-- Unsent Message Placeholder (Telegram/WhatsApp style) -->
                                    <template x-if="msg.is_unsent">
                                        <div class="italic text-xs opacity-75 flex items-center gap-1.5 py-1 select-none">
                                            <svg class="w-4 h-4 opacity-70 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                                            </svg>
                                            <span>This message was unsent</span>
                                        </div>
                                    </template>

                                    <!-- Interactive Poll Card (Send poll for vote) -->
                                    <template x-if="msg.poll_data && !msg.is_unsent">
                                        <div class="space-y-2.5 my-1 min-w-[240px] sm:min-w-[280px]">
                                            <!-- Poll Question & Badge -->
                                            <div class="border-b border-black/10 dark:border-white/10 pb-2 pr-8">
                                                <div class="flex items-center gap-1.5 mb-1">
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                                          :class="Number(msg.user_id) === Number(currentUser.id) ? 'bg-white/20 text-white' : 'bg-indigo-100 dark:bg-indigo-950/70 text-indigo-700 dark:text-indigo-300'">
                                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                                        </svg>
                                                        <span x-text="msg.poll_data.is_anonymous ? 'Anonymous Poll' : 'Public Poll'"></span>
                                                    </span>
                                                    <template x-if="msg.poll_data.multiple_answers">
                                                        <span class="text-[10px] opacity-75 font-semibold">• Multi-Choice</span>
                                                    </template>
                                                </div>
                                                <h4 class="font-bold text-sm leading-snug break-words" x-text="msg.poll_data.question"></h4>
                                            </div>

                                            <!-- Poll Options with Live Voting & Percentage Progress Bars -->
                                            <div class="space-y-1.5 pt-0.5">
                                                <template x-for="opt in (msg.poll_data.options || [])" :key="opt.id">
                                                    <button type="button"
                                                            @click="votePoll(msg, opt.id)"
                                                            class="w-full text-left relative overflow-hidden rounded-xl p-2.5 border transition-all text-xs flex items-center justify-between group/opt cursor-pointer"
                                                            :class="(opt.user_voted || opt.voted)
                                                                ? (Number(msg.user_id) === Number(currentUser.id) ? 'border-white bg-white/25 shadow-xs' : 'border-indigo-500 bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 font-semibold shadow-xs')
                                                                : (Number(msg.user_id) === Number(currentUser.id) ? 'border-white/20 bg-white/10 hover:bg-white/15' : 'border-slate-200 dark:border-slate-700/80 hover:bg-slate-50 dark:hover:bg-slate-750')">
                                                        <!-- Percentage Background Fill Bar -->
                                                        <div class="absolute inset-y-0 left-0 transition-all duration-500 opacity-25"
                                                             :class="Number(msg.user_id) === Number(currentUser.id) ? 'bg-white' : 'bg-indigo-600'"
                                                             :style="'width: ' + (opt.percent ?? opt.percentage ?? 0) + '%'"></div>

                                                        <!-- Radio / Checkbox Indicator & Option Text -->
                                                        <div class="relative flex items-center gap-2.5 min-w-0 flex-1 z-10">
                                                            <span class="w-4 h-4 rounded-full border flex items-center justify-center shrink-0 transition-colors"
                                                                  :class="(opt.user_voted || opt.voted)
                                                                      ? (Number(msg.user_id) === Number(currentUser.id) ? 'bg-white text-indigo-700 border-white' : 'bg-indigo-600 text-white border-indigo-600')
                                                                      : (Number(msg.user_id) === Number(currentUser.id) ? 'border-white/40' : 'border-slate-300 dark:border-slate-600')">
                                                                <template x-if="opt.user_voted || opt.voted">
                                                                    <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                                    </svg>
                                                                </template>
                                                            </span>
                                                            <span class="truncate font-medium text-xs" x-text="opt.text"></span>
                                                        </div>

                                                        <!-- Percentage & Votes Display -->
                                                        <div class="relative z-10 shrink-0 font-bold ml-2 text-[11px] flex items-center gap-1"
                                                             :class="(opt.user_voted || opt.voted) ? 'font-black' : ''">
                                                            <span x-text="(opt.percent ?? opt.percentage ?? 0) + '%'"></span>
                                                            <template x-if="opt.votes !== undefined && opt.votes > 0">
                                                                <span class="text-[10px] opacity-70 font-normal" x-text="'(' + opt.votes + ')'"></span>
                                                            </template>
                                                        </div>
                                                    </button>
                                                </template>
                                            </div>

                                            <!-- Poll Footer: Total Votes & Status -->
                                            <div class="flex items-center justify-between text-[11px] opacity-75 pt-1">
                                                <span x-text="(msg.poll_data.total_votes || 0) + ' vote' + ((msg.poll_data.total_votes === 1) ? '' : 's')"></span>
                                                <template x-if="msg.poll_data.has_voted || msg.poll_data.user_has_voted">
                                                    <span class="font-semibold text-emerald-400 dark:text-emerald-300 flex items-center gap-1">
                                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                        </svg>
                                                        <span>You voted</span>
                                                    </span>
                                                </template>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Message Attachments Grid -->
                                    <template x-if="!msg.is_unsent && msg.attachments && msg.attachments.length > 0">
                                        <div class="space-y-2 mb-2">

                                            <!-- Images Grid (Supports all formats: PNG, JPG, JPEG, WEBP, GIF, SVG, BMP, ICO, AVIF, HEIC, TIFF) -->
                                            <div class="grid gap-1.5"
                                                 :class="msg.attachments.filter(a => isImageAttachment(a)).length > 1 ? 'grid-cols-2 max-w-[260px] sm:max-w-sm' : 'grid-cols-1 max-w-[260px] sm:max-w-[520px]'">
                                                <template x-for="att in (msg.attachments || []).filter(a => isImageAttachment(a))" :key="att.id || att.file_name">
                                                    <div class="relative rounded-xl overflow-hidden cursor-pointer group/att bg-black/5"
                                                         :class="msg.attachments.filter(a => isImageAttachment(a)).length > 1 ? 'aspect-square' : 'max-h-[300px] sm:max-h-[420px] flex items-center justify-center'"
                                                         @click.stop="openMediaViewer(att, msg)"
                                                         @contextmenu.prevent.stop="openContextMenu($event, msg)"
                                                         title="Click to view full screen">
                                                        <img :src="att.view_url || ('/chat/attachments/' + att.id + '/view') || att.file_url || att.file_path"
                                                             :alt="att.file_name"
                                                             @load="onMessageMediaLoaded()"
                                                             x-on:error="if (!$event.target.dataset.tried) { $event.target.dataset.tried = '1'; $event.target.src = att.view_url || ('/chat/attachments/' + att.id + '/view'); }"
                                                             :class="msg.attachments.filter(a => isImageAttachment(a)).length > 1 ? 'w-full h-full object-cover' : 'w-auto h-auto max-h-[300px] sm:max-h-[420px] max-w-full object-contain'"
                                                             class="rounded-xl transition-transform duration-200 group-hover/att:scale-[1.01] pointer-events-auto cursor-pointer"
                                                             @click.stop="openMediaViewer(att, msg)">
                                                        <div class="absolute inset-0 bg-black/25 opacity-0 group-hover/att:opacity-100 transition-opacity flex items-center justify-center text-white cursor-pointer"
                                                             @click.stop="openMediaViewer(att, msg)">
                                                            <svg class="w-6 h-6 drop-shadow" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607ZM10.5 7.5v6m3-3h-6" />
                                                            </svg>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>

                                            <!-- Video Players (Strictly excludes voice message recordings) -->
                                            <template x-for="att in (msg.attachments || []).filter(a => isVideoAttachment(a, msg))" :key="att.id || att.file_name">
                                                <div class="rounded-xl overflow-hidden bg-black/80 max-w-sm relative group/vid"
                                                     @contextmenu.prevent.stop="openContextMenu($event, msg)">
                                                    <video controls playsinline preload="metadata" class="w-full max-h-64 rounded-xl">
                                                        <source :src="att.file_url || att.file_path || att.view_url || att.download_url" :type="att.file_type">
                                                        Your browser does not support the video tag.
                                                    </video>
                                                    <!-- Top overlay action toolbar (Always accessible on video) -->
                                                    <div class="absolute top-2 right-2 flex items-center gap-1.5 z-20">
                                                        <button type="button"
                                                                @click.stop="openMediaViewer(att, msg)"
                                                                title="Fullscreen Viewer"
                                                                class="p-1.5 rounded-lg bg-black/60 hover:bg-black/80 text-white opacity-0 group-hover/vid:opacity-100 transition-opacity cursor-pointer">
                                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                                                            </svg>
                                                        </button>
                                                        <button type="button"
                                                                data-chat-chevron="true"
                                                                @click.stop="openContextMenu($event, msg)"
                                                                title="Message options"
                                                                class="p-1.5 rounded-lg bg-black/60 hover:bg-black/80 text-white opacity-0 group-hover/vid:opacity-100 hover:opacity-100 transition-opacity cursor-pointer">
                                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </template>

                                            <!-- Telegram / WhatsApp / Messenger-Style Voice Message / Audio Player (Seamless Single-Pill, Zero Double Boxes) -->
                                            <template x-for="att in (msg.attachments || []).filter(a => isVoiceAttachment(a, msg))" :key="att.id || att.file_name">
                                                <div class="flex items-center gap-2.5 sm:gap-3 w-[216px] xs:w-60 sm:w-72 md:w-80 select-none transition-all"
                                                     :class="isVoiceOnly(msg)
                                                         ? 'p-0.5 bg-transparent border-0 my-0'
                                                         : (Number(msg.user_id) === Number(currentUser.id)
                                                             ? 'p-2 sm:p-2.5 rounded-2xl bg-black/15 my-0.5'
                                                             : 'p-2 sm:p-2.5 rounded-2xl bg-slate-100 dark:bg-slate-750/70 border border-slate-200/60 dark:border-slate-700/60 my-0.5')">
                                                    
                                                    <!-- Play / Pause Circular Button -->
                                                    <button type="button"
                                                            @click="toggleVoiceMessage(msg, att)"
                                                            class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 shadow-md transition-transform active:scale-90 cursor-pointer"
                                                            :class="Number(msg.user_id) === Number(currentUser.id) 
                                                                ? 'bg-white text-indigo-700 hover:bg-slate-100' 
                                                                : 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-indigo-500/25'">
                                                        <!-- Pause Icon -->
                                                        <template x-if="voicePlayer.currentMsgId === msg.id && voicePlayer.isPlaying">
                                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                                                <path fill-rule="evenodd" d="M6.75 5.25a.75.75 0 0 1 .75.75v12a.75.75 0 0 1-1.5 0v-12a.75.75 0 0 1 .75-.75Zm10.5 0a.75.75 0 0 1 .75.75v12a.75.75 0 0 1-1.5 0v-12a.75.75 0 0 1 .75-.75Z" clip-rule="evenodd" />
                                                            </svg>
                                                        </template>
                                                        <!-- Play Icon -->
                                                        <template x-if="!(voicePlayer.currentMsgId === msg.id && voicePlayer.isPlaying)">
                                                            <svg class="w-5 h-5 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                                                                <path fill-rule="evenodd" d="M4.5 5.653c0-1.427 1.529-2.33 2.779-1.643l11.54 6.347c1.295.712 1.295 2.573 0 3.286L7.28 19.99c-1.25.687-2.779-.217-2.779-1.643V5.653Z" clip-rule="evenodd" />
                                                            </svg>
                                                        </template>
                                                    </button>

                                                    <!-- Center: Interactive Amplitude Waveform & Progress -->
                                                    <div class="flex-1 min-w-0">
                                                        <!-- Waveform Bar Container (Click / Scrub to seek) -->
                                                        <div class="h-7 flex items-center gap-[2px] cursor-pointer group/wave py-1"
                                                             @click="seekVoiceMessage(msg, att, ($event.offsetX / $el.clientWidth), $event)">
                                                            <template x-for="(bar, bIdx) in getWaveformBars(att, msg.id)" :key="bIdx">
                                                                <span class="w-[3px] rounded-full transition-colors duration-150"
                                                                      :style="`height: ${bar}%;`"
                                                                      :class="{
                                                                          'bg-white': Number(msg.user_id) === Number(currentUser.id) && (voicePlayer.currentMsgId === msg.id && (voicePlayer.currentTime / (voicePlayer.duration || att.duration_seconds || 1)) >= (bIdx / 36)),
                                                                          'bg-white/40': Number(msg.user_id) === Number(currentUser.id) && !(voicePlayer.currentMsgId === msg.id && (voicePlayer.currentTime / (voicePlayer.duration || att.duration_seconds || 1)) >= (bIdx / 36)),
                                                                          'bg-indigo-600 dark:bg-indigo-400': Number(msg.user_id) !== Number(currentUser.id) && (voicePlayer.currentMsgId === msg.id && (voicePlayer.currentTime / (voicePlayer.duration || att.duration_seconds || 1)) >= (bIdx / 36)),
                                                                          'bg-slate-300 dark:bg-slate-600': Number(msg.user_id) !== Number(currentUser.id) && !(voicePlayer.currentMsgId === msg.id && (voicePlayer.currentTime / (voicePlayer.duration || att.duration_seconds || 1)) >= (bIdx / 36))
                                                                      }"></span>
                                                            </template>
                                                        </div>

                                                        <!-- Waveform Duration & Time Labels -->
                                                        <div class="flex items-center justify-between text-[11px] font-mono mt-0.5"
                                                             :class="Number(msg.user_id) === Number(currentUser.id) ? 'text-white/85' : 'text-slate-500 dark:text-slate-400'">
                                                            <span x-text="voicePlayer.currentMsgId === msg.id ? formatAudioTime(voicePlayer.currentTime) : formatAudioTime(att.duration_seconds || 0)">0:00</span>
                                                            <span x-text="formatAudioTime(att.duration_seconds || (voicePlayer.currentMsgId === msg.id ? voicePlayer.duration : 0))">0:00</span>
                                                        </div>
                                                    </div>

                                                    <!-- Right: 1x / 1.5x / 2x Speed Toggle -->
                                                    <button type="button"
                                                            @click.stop="cyclePlaybackSpeed()"
                                                            class="px-1.5 py-0.5 rounded-md text-[10px] font-bold font-mono transition-colors shrink-0 shadow-2xs cursor-pointer"
                                                            :class="Number(msg.user_id) === Number(currentUser.id) 
                                                                ? 'bg-white/20 hover:bg-white/30 text-white' 
                                                                : 'bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 text-slate-700 dark:text-slate-200'"
                                                            title="Playback Speed">
                                                        <span x-text="voicePlayer.playbackRate + 'x'">1x</span>
                                                    </button>
                                                </div>
                                            </template>

                                            <!-- Documents & Other Files -->
                                            <template x-for="att in (msg.attachments || []).filter(a => msg.type !== 'voice' && !isVoiceOnly(msg) && isDocAttachment(a, msg))" :key="att.id || att.file_name">
                                                <div class="flex items-center gap-2.5 p-2.5 rounded-xl border"
                                                     :class="Number(msg.user_id) === Number(currentUser.id) ? 'bg-white/10 border-white/20 text-white' : 'bg-slate-50 dark:bg-slate-700/50 border-slate-200 dark:border-slate-600 text-slate-800 dark:text-slate-100'">
                                                    <div class="p-2 rounded-lg bg-indigo-600 text-white shrink-0">
                                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                        </svg>
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-xs font-semibold truncate" x-text="att.file_name"></p>
                                                        <p class="text-[10px] opacity-75" x-text="formatFileSize(att.file_size)"></p>
                                                    </div>
                                                    <a :href="att.download_url"
                                                       target="_blank"
                                                       class="p-1.5 rounded-lg hover:bg-black/10 transition-colors"
                                                       title="Download file">
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                                        </svg>
                                                    </a>
                                                </div>
                                            </template>

                                        </div>
                                    </template>

                                    <!-- Sticker Message (WhatsApp Style: Transparent Cutout Floating on Wallpaper) -->
                                    <template x-if="!msg.is_unsent && msg.type === 'sticker'">
                                        <div class="relative inline-flex flex-col items-end group/stk select-none py-1">
                                            <!-- Quick Hover Action: Save Sticker to Personal Drawer (WhatsApp Style) -->
                                            <button type="button"
                                                    @click.stop="isStickerSaved(msg) ? openStickerActionModal(msg) : saveStickerFromMessage(msg)"
                                                    :title="isStickerSaved(msg) ? 'Saved in Stickers ⭐' : 'Save sticker to your drawer'"
                                                    class="absolute top-1 right-1 opacity-0 group-hover/stk:opacity-100 transition-opacity px-2 py-1 rounded-xl text-[10px] font-bold flex items-center gap-1 shadow-lg z-10 cursor-pointer backdrop-blur-xs"
                                                    :class="isStickerSaved(msg) ? 'bg-emerald-600/90 text-white' : 'bg-black/75 hover:bg-emerald-600 text-white'">
                                                <svg class="w-3.5 h-3.5 text-amber-300" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                </svg>
                                                <span x-text="isStickerSaved(msg) ? 'Saved' : 'Save'"></span>
                                            </button>

                                            <img :src="msg.body || msg.content"
                                                 alt="Sticker"
                                                 @load="onMessageMediaLoaded()"
                                                 class="w-32 h-32 sm:w-36 sm:h-36 object-contain drop-shadow-md select-none transition-transform hover:scale-105 pointer-events-auto cursor-pointer"
                                                 @click="openStickerActionModal(msg)">
                                            <div class="mt-0.5 px-2 py-0.5 rounded-full bg-black/45 backdrop-blur-xs text-[10px] text-white/90 flex items-center gap-1 shrink-0 shadow-xs">
                                                <span x-text="formatTime(msg.created_at)"></span>
                                                <template x-if="Number(msg.user_id) === Number(currentUser.id)">
                                                    <span class="inline-flex items-center">
                                                        <template x-if="!msg.is_seen && msg.status !== 'read'">
                                                            <span class="inline-flex items-center text-[#38bdf8] drop-shadow-[0_1px_1.5px_rgba(0,0,0,0.45)]" title="Sent">
                                                                <svg class="w-3.5 h-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2 8.5L6 12.5L14 3.5" />
                                                                </svg>
                                                            </span>
                                                        </template>
                                                        <template x-if="msg.is_seen || msg.status === 'read'">
                                                            <span class="inline-flex items-center text-[#4ade80] drop-shadow-[0_1px_1.5px_rgba(0,0,0,0.5)] font-bold" title="Seen">
                                                                <svg class="w-4 h-3.5" viewBox="0 0 19 16" fill="none" stroke="currentColor" stroke-width="2.1">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M1.5 8.5L5.5 12.5L13 3.5" />
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.2 11.2L9.5 12.5L17 3.5" />
                                                                </svg>
                                                            </span>
                                                        </template>
                                                    </span>
                                                </template>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- GIF Message (Autoplay Looping Animated GIF) -->
                                    <template x-if="!msg.is_unsent && msg.type === 'gif'">
                                        <div class="relative rounded-2xl overflow-hidden max-w-[260px] sm:max-w-[320px] min-h-[120px] bg-black/10 shadow-xs cursor-pointer"
                                             @click.stop="openLightbox(msg.body || msg.content, 'GIF', 'image')"
                                             title="Click to view full screen">
                                            <img :src="msg.body || msg.content"
                                                 alt="GIF"
                                                 @load="onMessageMediaLoaded()"
                                                 class="w-full h-auto rounded-2xl object-cover cursor-pointer">
                                        </div>
                                    </template>

                                    <!-- Message Text Body (with highlighted & clickable links, code formatting, markdown) -->
                                    <template x-if="!msg.is_unsent && (msg.body || msg.content) && (msg.body || msg.content).trim() && !msg.poll_data && msg.type !== 'sticker' && msg.type !== 'gif' && msg.type !== 'voice' && !isVoiceOnly(msg) && (msg.body || msg.content).trim() !== '🎤 Voice Message' && (msg.body || msg.content).trim() !== 'Voice Message'">
                                        <div class="break-words whitespace-pre-wrap leading-relaxed select-text"
                                             x-html="formatMessageHtml(msg.body || msg.content, Number(msg.user_id) === Number(currentUser.id))"></div>
                                    </template>

                                    <!-- Link Preview Card -->
                                    <template x-if="!msg.is_unsent && msg.link_preview && msg.link_preview.url">
                                        <a :href="msg.link_preview.url"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           class="mt-2 block rounded-xl overflow-hidden border border-black/10 dark:border-white/10 bg-black/5 dark:bg-white/5 hover:opacity-90 transition-opacity">
                                            <template x-if="msg.link_preview.image">
                                                <img :src="msg.link_preview.image" @load="onMessageMediaLoaded()" class="w-full h-32 object-cover">
                                            </template>
                                            <div class="p-2.5">
                                                <p class="text-xs font-bold line-clamp-1" x-text="msg.link_preview.title"></p>
                                                <p class="text-[11px] opacity-80 line-clamp-2 mt-0.5" x-text="msg.link_preview.description"></p>
                                                <span class="text-[10px] text-indigo-400 mt-1 block truncate" x-text="msg.link_preview.url"></span>
                                            </div>
                                        </a>
                                    </template>

                                    <!-- Bubble Footer: Timestamp, Edited marker & Status (Hidden for stickers, snug inside single voice pill) -->
                                    <div x-show="msg.type !== 'sticker'"
                                         class="flex items-center justify-end gap-1.5 text-[10px]"
                                         :class="{
                                             '-mt-1 pr-1': isVoiceOnly(msg),
                                             'mt-1': !isVoiceOnly(msg),
                                             'text-white/80': Number(msg.user_id) === Number(currentUser.id),
                                             'text-slate-400': Number(msg.user_id) !== Number(currentUser.id)
                                         }">
                                        <template x-if="msg.is_edited">
                                            <span class="italic font-medium">edited</span>
                                        </template>

                                        <!-- Pinned / Starred Indicators (WhatsApp Style) -->
                                        <template x-if="msg.is_starred">
                                            <svg class="w-3 h-3 text-amber-400 fill-amber-400 inline-block shrink-0" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                        </template>
                                        <template x-if="msg.is_pinned || activeConversation?.pinned_message_id === msg.id">
                                            <svg class="w-3 h-3 text-indigo-400 dark:text-indigo-300 inline-block shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m15 11.25 1.5 1.5.75-.75-3.75-3.75-.75.75 1.5 1.5L9.75 15l-3 1.5L8.25 18 3 21l3-5.25 1.5 1.5 1.5-3 4.5-4.5Z" />
                                            </svg>
                                        </template>

                                        <span x-text="formatTime(msg.created_at)"></span>

                                        <!-- Outgoing Delivery Checkmarks: 1 Blue Tick = Sent, 2 Green Ticks (Side-by-Side) = Seen -->
                                        <template x-if="Number(msg.user_id) === Number(currentUser.id)">
                                            <span class="inline-flex items-center ml-1 shrink-0">
                                                <!-- Sending: Single subtle blue checkmark -->
                                                <template x-if="msg.status === 'sending'">
                                                    <span class="inline-flex items-center text-sky-300/75" title="Sending...">
                                                        <svg class="w-3.5 h-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2 8.5L6 12.5L14 3.5" />
                                                        </svg>
                                                    </span>
                                                </template>
                                                <!-- Sent (Not Yet Seen): 1 Clear Blue Checkmark -->
                                                <template x-if="msg.status !== 'sending' && msg.status !== 'failed' && msg.status !== 'read' && !msg.is_seen">
                                                    <span class="inline-flex items-center text-[#38bdf8] drop-shadow-[0_1px_1.5px_rgba(0,0,0,0.45)]" title="Sent (1 Blue Tick)">
                                                        <svg class="w-3.5 h-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2 8.5L6 12.5L14 3.5" />
                                                        </svg>
                                                    </span>
                                                </template>
                                                <!-- Read / Seen: 2 Clear Bright Green Checkmarks (WhatsApp Tight Parallel Style) -->
                                                <template x-if="msg.status === 'read' || msg.is_seen">
                                                    <span class="inline-flex items-center text-[#4ade80] drop-shadow-[0_1px_1.5px_rgba(0,0,0,0.5)] font-bold" title="Seen (2 Green Ticks)">
                                                        <svg class="w-4 h-3.5" viewBox="0 0 19 16" fill="none" stroke="currentColor" stroke-width="2.1">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M1.5 8.5L5.5 12.5L13 3.5" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.2 11.2L9.5 12.5L17 3.5" />
                                                        </svg>
                                                    </span>
                                                </template>
                                                <!-- Failed -->
                                                <template x-if="msg.status === 'failed'">
                                                    <button type="button"
                                                            @click="retryMessage(msg)"
                                                            class="text-rose-300 font-bold hover:underline flex items-center gap-0.5">
                                                        <span>!</span>
                                                        <span>Retry</span>
                                                    </button>
                                                </template>
                                            </span>
                                        </template>
                                    </div>

                                </div>

                            </div>

                            <!-- Messenger-style "Seen" Indicator with Partner Avatar -->
                            <template x-if="isLastSeenMessage(msg)">
                                <div class="flex items-center justify-end gap-1.5 mt-0.5 pr-1 select-none text-[11px] text-slate-400 dark:text-slate-400 font-medium">
                                    <span>Seen</span>
                                    <template x-if="activeConversation?.other_user">
                                        <img :src="avatarUrl(activeConversation.other_user.avatar_url, activeConversation.other_user.name)"
                                             :alt="activeConversation.other_user.name"
                                             x-on:error="handleAvatarError($event, activeConversation.other_user.name)"
                                             class="w-3.5 h-3.5 rounded-full object-cover ring-1 ring-white/20 shadow-xs"
                                             :title="'Seen by ' + (activeConversation.other_user.name || 'partner')" />
                                    </template>
                                </div>
                            </template>

                            <!-- Reaction Pills below Bubble -->
                            <template x-if="msg.reactions && msg.reactions.length > 0">
                                <div class="flex items-center gap-1 mt-1 px-1"
                                     :class="Number(msg.user_id) === Number(currentUser.id) ? 'justify-end' : 'justify-start ml-9'">
                                    <template x-for="r in msg.reactions" :key="r.emoji">
                                        <button type="button"
                                                @click="toggleReaction(msg, r.emoji)"
                                                :class="(r.user_reacted || r.is_me) ? 'bg-indigo-100 dark:bg-indigo-900/60 border-indigo-300 dark:border-indigo-600' : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700'"
                                                class="px-2 py-0.5 rounded-full text-xs border flex items-center gap-1 hover:scale-105 transition-transform shadow-xs">
                                            <span x-text="r.emoji"></span>
                                            <span class="text-[10px] font-bold" x-text="r.count"></span>
                                        </button>
                                    </template>
                                </div>
                            </template>

                        </div>
                    </template>

                    <!-- Bottom spacer when typing indicator is active so the last message is never covered -->
                    <div x-show="typingUsers && typingUsers.length > 0" class="h-9 shrink-0 select-none"></div>

                </div>

                <!-- Composer Area (Permanently Fixed on Screen at Bottom - Image 2) -->
                <div class="chat-composer-footer p-2 sm:p-3 bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 shrink-0 sticky bottom-0 z-20 shadow-sm relative">

                    <!-- Floating Typing Indicator right on top of the Message Bar (Always visible without scrolling) -->
                    <div x-show="typingUsers && typingUsers.length > 0"
                         x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                         x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                         class="absolute -top-10 left-3 sm:left-4 z-30 flex items-center gap-2 select-none pointer-events-none">
                        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-2xl rounded-bl-xs bg-white/95 dark:bg-slate-800/95 backdrop-blur-md border border-emerald-500/35 dark:border-emerald-400/35 shadow-lg">
                            <span class="inline-flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-bounce"></span>
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-bounce" style="animation-delay: 150ms"></span>
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-bounce" style="animation-delay: 300ms"></span>
                            </span>
                            <span class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400"
                                  x-text="(activeConversation?.type === 'group' ? (typingUsers.join(', ') + ' is typing...') : ((activeConversation?.title || 'Someone') + ' is typing...'))"></span>
                        </div>
                    </div>

                    <!-- Telegram-Style Floating Scroll to Bottom Button -->
                    <button type="button"
                            x-show="showScrollBottomBtn"
                            x-cloak
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 translate-y-2 scale-90"
                            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                            x-transition:leave-end="opacity-0 translate-y-2 scale-90"
                            @click="scrollToBottom(true)"
                            title="Scroll to bottom"
                            class=" -top-12 right-3 sm:right-5 w-10 h-10 rounded-full bg-white/95 dark:bg-slate-800/95 backdrop-blur-md text-slate-700 dark:text-slate-200 border border-slate-200/80 dark:border-slate-700 shadow-lg flex items-center justify-center hover:bg-indigo-600 hover:text-white dark:hover:bg-indigo-600 transition-all active:scale-90 z-30 cursor-pointer absolute">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" />
                        </svg>
                    </button>

                    <!-- Multi-Select Messages Floating Bar (WhatsApp Style) -->
                    <div x-show="isSelectingMessages"
                         x-cloak
                         x-transition
                         class="mb-2.5 p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-between gap-3 text-xs shadow-sm">
                        <div class="flex items-center gap-2">
                            <button type="button" @click="cancelSelectMessages()" class="p-1 rounded-full text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                            <span class="font-bold text-slate-700 dark:text-slate-200" x-text="selectedMessageIds.length + ' selected'"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button"
                                    :disabled="!selectedMessageIds.length"
                                    @click="forwardSelectedMessages()"
                                    class="px-2.5 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium flex items-center gap-1.5 transition-colors disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m15 15 6-6m0 0-6-6m6 6H9a6 6 0 0 0 0 12h3" />
                                </svg>
                                <span>Forward</span>
                            </button>
                            <button type="button"
                                    :disabled="!selectedMessageIds.length"
                                    @click="deleteSelectedMessages()"
                                    class="px-2.5 py-1 rounded-lg bg-rose-500 hover:bg-rose-600 text-white font-medium flex items-center gap-1.5 transition-colors disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                                <span>Delete</span>
                            </button>
                        </div>
                    </div>

                    <!-- Reply Preview Bar -->
                    <div x-show="replyingTo"
                         x-transition
                         class="mb-2 p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 border-l-4 border-indigo-600 flex items-center justify-between text-xs">
                        <div class="min-w-0 pr-2">
                            <span class="font-bold text-indigo-700 dark:text-indigo-300">Replying to <span x-text="replyingTo?.user?.name"></span></span>
                            <p class="text-slate-600 dark:text-slate-300 truncate" x-text="replyingTo?.body"></p>
                        </div>
                        <button type="button" @click="cancelReply()" class="p-1 text-slate-400 hover:text-slate-600">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Edit Preview Bar -->
                    <div x-show="editingMessage"
                         x-transition
                         class="mb-2 p-2 rounded-xl bg-amber-50 dark:bg-amber-950/50 border-l-4 border-amber-500 flex items-center justify-between text-xs">
                        <div class="min-w-0 pr-2">
                            <span class="font-bold text-amber-700 dark:text-amber-300">Editing Message</span>
                            <p class="text-slate-600 dark:text-slate-300 truncate" x-text="editingMessage?.body"></p>
                        </div>
                        <button type="button" @click="cancelEdit()" class="p-1 text-slate-400 hover:text-slate-600">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Staged Attachments Preview Tray -->
                    <div x-show="stagedFiles.length > 0"
                         class="mb-2 p-2 bg-slate-50 dark:bg-slate-800/60 rounded-xl flex items-center gap-2 overflow-x-auto no-scrollbar">
                        <template x-for="(file, idx) in stagedFiles" :key="idx">
                            <div class="relative shrink-0 w-16 h-16 rounded-lg overflow-hidden border border-slate-200 dark:border-slate-700 group/staged">
                                <template x-if="file.previewUrl">
                                    <img :src="file.previewUrl" class="w-full h-full object-cover">
                                </template>
                                <template x-if="!file.previewUrl">
                                    <div class="w-full h-full bg-indigo-600 text-white flex flex-col items-center justify-center p-1 text-[9px] text-center font-bold">
                                        <svg class="w-5 h-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                        </svg>
                                        <span class="truncate w-full" x-text="file.name"></span>
                                    </div>
                                </template>
                                <button type="button"
                                        @click="removeStagedFile(idx)"
                                        class="absolute top-0.5 right-0.5 w-4 h-4 rounded-full bg-rose-600 text-white flex items-center justify-center text-[10px] shadow">
                                    ✕
                                </button>
                            </div>
                        </template>
                    </div>

                    <!-- WhatsApp Web / Telegram Style Drawer (Emoji, GIF, Stickers) -->
                    <div x-show="showEmojiPicker"
                         @click.away="showEmojiPicker = false"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                         x-transition:leave-end="opacity-0 translate-y-4 scale-95"
                         class="absolute bottom-16 sm:bottom-20 inset-x-2 sm:inset-x-auto sm:left-4 w-auto sm:w-96 max-w-md bg-white/95 dark:bg-slate-900/95 backdrop-blur-xl rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 p-3 z-30 flex flex-col h-[52vh] max-h-[380px] sm:h-[420px] sm:max-h-[420px] overflow-hidden"
                         x-cloak>

                        <!-- 1. Top Search Bar (Matches Image 1 & 2) -->
                        <div class="relative mb-2.5 shrink-0">
                            <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80 focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500 transition-all">
                                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                </svg>
                                <input type="text"
                                       x-model="mediaSearchQuery"
                                       @input="mediaDrawerTab === 'gif' ? loadGifs(mediaSearchQuery) : null"
                                       placeholder="Search with text or emoji"
                                       class="w-full bg-transparent border-0 p-0 text-xs focus:ring-0 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none">
                                <template x-if="mediaSearchQuery">
                                    <button type="button"
                                            @click="mediaSearchQuery = ''; if(mediaDrawerTab === 'gif') loadGifs('');"
                                            class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 shrink-0 text-xs">✕</button>
                                </template>
                            </div>
                        </div>

                        <!-- 2. Segmented Navigation Tabs: Emoji, GIF, Stickers (Matches Image 1 & 2) -->
                        <div class="flex items-center justify-center p-1 bg-slate-100 dark:bg-slate-800/80 rounded-2xl mb-2.5 shrink-0">
                            <!-- Emoji Tab -->
                            <button type="button"
                                    @click="mediaDrawerTab = 'emoji'"
                                    class="flex-1 py-1.5 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 transition-all cursor-pointer"
                                    :class="mediaDrawerTab === 'emoji' ? 'bg-white dark:bg-slate-700 text-emerald-600 dark:text-emerald-400 shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'">
                                <span class="text-base leading-none">🙂</span>
                                <span>Emoji</span>
                            </button>

                            <!-- GIF Tab -->
                            <button type="button"
                                    @click="mediaDrawerTab = 'gif'; if(gifs.length === 0) loadGifs();"
                                    class="flex-1 py-1.5 rounded-xl text-xs font-black tracking-wider flex items-center justify-center gap-1.5 transition-all cursor-pointer"
                                    :class="mediaDrawerTab === 'gif' ? 'bg-white dark:bg-slate-700 text-emerald-600 dark:text-emerald-400 shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'">
                                <span class="px-1.5 py-0.2 rounded bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-[10px] font-black border border-emerald-500/20">GIF</span>
                                <span>GIFs</span>
                            </button>

                            <!-- Sticker Tab -->
                            <button type="button"
                                    @click="mediaDrawerTab = 'sticker'; if(stickers.length === 0) loadStickers();"
                                    class="flex-1 py-1.5 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 transition-all cursor-pointer"
                                    :class="mediaDrawerTab === 'sticker' ? 'bg-white dark:bg-slate-700 text-emerald-600 dark:text-emerald-400 shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'">
                                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                                </svg>
                                <span>Stickers</span>
                            </button>
                        </div>

                        <!-- 3. Tab Contents -->
                        <div class="flex-1 min-h-0 overflow-y-auto chat-scrollbar">

                            <!-- TAB 1: EMOJIS (Matches Image 1 with Fast Keyword Search) -->
                            <div x-show="mediaDrawerTab === 'emoji'" class="space-y-3">
                                <template x-for="cat in emojiCategoryList" :key="cat.name">
                                    <div x-show="getMatchingEmojis(cat.name).length > 0">
                                        <h4 class="text-[10px] uppercase font-bold text-slate-400 mb-1.5" x-text="cat.name"></h4>
                                        <div class="grid grid-cols-8 gap-1 text-xl">
                                            <template x-for="e in getMatchingEmojis(cat.name)" :key="e">
                                                <button type="button"
                                                        @click="insertEmoji(e)"
                                                        class="p-1 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg text-center transition-transform hover:scale-125 cursor-pointer"
                                                        x-text="e"></button>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <!-- Empty Emoji Search State -->
                                <div x-show="mediaSearchQuery && !hasAnyEmojiMatches()" class="text-center py-8 text-slate-400 text-xs">
                                    <p>No emoji matching "<span x-text="mediaSearchQuery"></span>"</p>
                                    <p class="text-[10px] text-slate-500 mt-1">Try: cry, laugh, love, heart, fire, ok, clap, party...</p>
                                </div>
                            </div>

                            <!-- TAB 2: WORLDWIDE GIFS (Matches Image 2) -->
                            <div x-show="mediaDrawerTab === 'gif'" class="h-full">
                                <!-- Loading State -->
                                <div x-show="isLoadingGifs" class="grid grid-cols-2 gap-2 p-1">
                                    <template x-for="i in [1,2,3,4,5,6]" :key="i">
                                        <div class="h-28 rounded-xl bg-slate-200 dark:bg-slate-800 animate-pulse"></div>
                                    </template>
                                </div>

                                <!-- GIF Masonry Grid -->
                                <div x-show="!isLoadingGifs" class="grid grid-cols-2 sm:grid-cols-3 gap-1.5 p-0.5">
                                    <template x-for="gif in gifs" :key="gif.id || gif.url">
                                        <button type="button"
                                                @click="sendGif(gif)"
                                                class="relative rounded-xl overflow-hidden group/gif bg-slate-100 dark:bg-slate-800 aspect-[4/3] cursor-pointer hover:ring-2 hover:ring-emerald-500 transition-all">
                                            <img :src="gif.preview_url || gif.url"
                                                 :alt="gif.title"
                                                 loading="lazy"
                                                 class="w-full h-full object-cover group-hover/gif:scale-105 transition-transform duration-200">
                                            <span class="absolute inset-x-0 bottom-0 p-1 bg-gradient-to-t from-black/80 to-transparent text-[9px] text-white truncate text-left opacity-0 group-hover/gif:opacity-100 transition-opacity"
                                                  x-text="gif.title"></span>
                                        </button>
                                    </template>
                                </div>

                                <!-- Empty GIF state -->
                                <div x-show="!isLoadingGifs && (!gifs || gifs.length === 0)" class="text-center py-10 text-slate-400 text-xs">
                                    <p>No GIFs found. Try searching for "hello", "party", or "dance".</p>
                                </div>
                            </div>

                            <!-- TAB 3: STICKERS (Matches Image 1 & 3 with Saved & Custom Stickers) -->
                            <div x-show="mediaDrawerTab === 'sticker'" class="h-full">
                                <div class="grid grid-cols-4 gap-2.5 p-1">
                                    <!-- Card 1: + Create Sticker (Like Image 1) -->
                                    <button type="button"
                                            @click="openStickerCreator()"
                                            class="flex flex-col items-center justify-center p-2 rounded-2xl border-2 border-dashed border-slate-300 dark:border-slate-700 hover:border-emerald-500 dark:hover:border-emerald-400 bg-slate-50 dark:bg-slate-800/60 hover:bg-emerald-50/50 dark:hover:bg-emerald-950/20 text-slate-600 dark:text-slate-300 transition-all aspect-square cursor-pointer group/maker">
                                        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 dark:bg-emerald-400/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-1 group-hover/maker:scale-110 transition-transform">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                            </svg>
                                        </div>
                                        <span class="text-[10px] font-bold text-center leading-tight">Create<br>sticker</span>
                                    </button>

                                    <!-- Sticker Items (Custom, Saved & Preset Stickers) -->
                                    <template x-for="stk in getFilteredStickers()" :key="stk.id || stk.image_url || stk.image_path">
                                        <div class="relative group/stk">
                                            <button type="button"
                                                    @click="sendSticker(stk)"
                                                    :title="stk.name"
                                                    class="w-full h-full flex flex-col items-center justify-center p-1 rounded-2xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-all aspect-square cursor-pointer group-hover/stk:scale-105">
                                                <img :src="stk.image_url || stk.image_path"
                                                     :alt="stk.name"
                                                     loading="lazy"
                                                     class="w-16 h-16 object-contain drop-shadow-sm select-none pointer-events-none">
                                                <span class="text-[9px] text-slate-500 dark:text-slate-400 truncate max-w-full text-center mt-0.5"
                                                      x-text="stk.name"></span>
                                            </button>
                                            <!-- Remove Sticker Button (for User's Saved or Created Stickers) -->
                                            <template x-if="stk.user_id && Number(stk.user_id) === Number(currentUser.id)">
                                                <button type="button"
                                                        @click.stop="deleteCustomSticker(stk, $event)"
                                                        title="Remove sticker"
                                                        class="absolute -top-1 -right-1 opacity-0 group-hover/stk:opacity-100 transition-opacity w-5 h-5 rounded-full bg-rose-500 hover:bg-rose-600 text-white flex items-center justify-center text-[10px] font-bold shadow-md cursor-pointer z-10">✕</button>
                                            </template>
                                        </div>
                                    </template>

                                    <!-- Empty Search State for Stickers with 1-Click Creator -->
                                    <div x-show="mediaSearchQuery && getFilteredStickers().length === 0" class="col-span-4 text-center py-6 text-slate-400 text-xs">
                                        <p>No sticker matching "<span x-text="mediaSearchQuery"></span>"</p>
                                        <button type="button"
                                                @click="openStickerCreator(mediaSearchQuery)"
                                                class="mt-2.5 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold shadow-sm transition-all cursor-pointer">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                            </svg>
                                            <span>Create "<span x-text="mediaSearchQuery"></span>" Sticker</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Upload Progress Bar (Large File & Video Support) -->
                    <div x-show="isUploading"
                         x-transition
                         class="mb-2 p-2.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800 text-xs">
                        <div class="flex items-center justify-between mb-1 font-semibold text-indigo-700 dark:text-indigo-300">
                            <div class="flex items-center gap-1.5 min-w-0 pr-2">
                                <svg class="w-4 h-4 animate-spin shrink-0 text-indigo-600 dark:text-indigo-400" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span class="truncate">Uploading <span x-text="uploadFileName"></span>...</span>
                            </div>
                            <span class="shrink-0 font-bold" x-text="uploadProgress + '%'"></span>
                        </div>
                        <div class="w-full h-1.5 bg-indigo-200 dark:bg-indigo-900 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-indigo-500 to-cyan-500 transition-all duration-150 rounded-full"
                                 :style="'width: ' + uploadProgress + '%'"></div>
                        </div>
                    </div>

                    <!-- Input Controls Row -->
                    <div class="flex items-end gap-2 bg-slate-100 dark:bg-slate-800 rounded-2xl p-1.5 focus-within:ring-2 focus-within:ring-indigo-500/40 focus-within:bg-white dark:focus-within:bg-slate-850 transition-all relative">

                        <!-- Hidden File Inputs -->
                        <input type="file"
                               id="chat-file-input"
                               multiple
                               @change="handleFileSelect($event)"
                               class="hidden">
                        <input type="file"
                               id="chat-media-input"
                               multiple
                               accept="image/*,video/*,.png,.jpg,.jpeg,.webp,.gif,.svg,.bmp,.ico,.tif,.tiff,.avif,.heic,.heif,.mp4,.webm,.mov,.mkv,.avi,.m4v"
                               @change="handleFileSelect($event)"
                               class="hidden">

                        <!-- Telegram Attachment Menu Popover -->
                        <div class="relative">
                            <button type="button"
                                    @click="showAttachMenu = !showAttachMenu"
                                    class="p-2 text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400 rounded-xl transition-colors"
                                    :class="showAttachMenu ? 'bg-slate-200 dark:bg-slate-700 text-indigo-600' : ''"
                                    title="Attach File, Video, or Poll">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />
                                </svg>
                            </button>

                            <!-- Attachment Popover Options -->
                            <div x-show="showAttachMenu"
                                 @click.away="showAttachMenu = false"
                                 x-transition
                                 class="absolute bottom-12 left-0 w-52 bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-700 p-1.5 z-40 text-xs">
                                <button type="button"
                                        @click="document.getElementById('chat-media-input').click(); showAttachMenu = false"
                                        class="w-full flex items-center gap-2.5 p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors">
                                    <span class="p-1.5 rounded-lg bg-sky-100 dark:bg-sky-950 text-sky-600 dark:text-sky-400">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                        </svg>
                                    </span>
                                    <div class="text-left">
                                        <p class="font-semibold">Photo or Video</p>
                                        <p class="text-[10px] text-slate-400">Media gallery preview</p>
                                    </div>
                                </button>

                                <button type="button"
                                        @click="document.getElementById('chat-file-input').click(); showAttachMenu = false"
                                        class="w-full flex items-center gap-2.5 p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors">
                                    <span class="p-1.5 rounded-lg bg-indigo-100 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                        </svg>
                                    </span>
                                    <div class="text-left">
                                        <p class="font-semibold">File / Document</p>
                                        <p class="text-[10px] text-slate-400">Up to 100MB</p>
                                    </div>
                                </button>

                                <button type="button"
                                        @click="openPollModal()"
                                        class="w-full flex items-center gap-2.5 p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors">
                                    <span class="p-1.5 rounded-lg bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                        </svg>
                                    </span>
                                    <div class="text-left">
                                        <p class="font-semibold">Create Poll</p>
                                        <p class="text-[10px] text-slate-400">Vote & gather opinions</p>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Normal Composer Mode (when not recording or reviewing audio) -->
                        <template x-if="!voiceRecording.active && !voiceRecording.audioUrl">
                            <div class="flex items-end gap-2 flex-1 min-w-0">
                                <!-- Emoji Toggle Button -->
                                <button type="button"
                                        @click="showEmojiPicker = !showEmojiPicker"
                                        class="p-2 text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400 rounded-xl transition-colors shrink-0"
                                        title="Emojis & Reactions">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 0 1-6.364 0M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z" />
                                    </svg>
                                </button>

                                <!-- Expanding Multiline Textarea -->
                                <textarea id="chat-composer-textarea"
                                          rows="1"
                                          x-model="composerText"
                                          @input="onComposerInput()"
                                          @keydown="handleComposerKeydown($event)"
                                          placeholder="Message..."
                                          class="flex-1 max-h-32 sm:max-h-36 py-2 px-1 text-sm bg-transparent border-0 focus:ring-0 focus:outline-none text-slate-900 dark:text-slate-100 placeholder-slate-400 resize-none"></textarea>

                                <!-- Microphone Button (Visible when input is empty & no staged files) -->
                                <template x-if="!composerText.trim() && stagedFiles.length === 0 && !editingMessage">
                                    <button type="button"
                                            @click="startVoiceRecording('tap')"
                                            class="p-2.5 rounded-xl bg-slate-200/80 hover:bg-indigo-50 dark:bg-slate-700/80 dark:hover:bg-indigo-950/60 text-slate-700 dark:text-slate-200 hover:text-indigo-600 dark:hover:text-indigo-400 transition-all flex items-center justify-center shrink-0 active:scale-95 shadow-xs cursor-pointer group"
                                            title="Record Voice Message (Telegram Style)">
                                        <svg class="w-5 h-5 transition-transform group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />
                                        </svg>
                                    </button>
                                </template>

                                <!-- Send Button (or Save Edit button) -->
                                <template x-if="composerText.trim() || stagedFiles.length > 0 || editingMessage">
                                    <button type="button"
                                            @click="editingMessage ? saveEdit() : sendMessage()"
                                            class="p-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-md shadow-indigo-500/25 active:scale-95 transition-all flex items-center justify-center shrink-0 cursor-pointer">
                                        <template x-if="editingMessage">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                            </svg>
                                        </template>
                                        <template x-if="!editingMessage">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                                            </svg>
                                        </template>
                                    </button>
                                </template>
                            </div>
                        </template>

                        <!-- Active Voice Recording Dock (Telegram Style) -->
                        <template x-if="voiceRecording.active">
                            <div class="flex items-center justify-between gap-3 flex-1 min-w-0 p-1.5 bg-rose-50/80 dark:bg-rose-950/30 rounded-xl border border-rose-200 dark:border-rose-900/50">
                                <!-- Discard / Trash Button -->
                                <button type="button"
                                        @click="cancelVoiceRecording()"
                                        class="p-2 rounded-lg bg-rose-100 dark:bg-rose-900/60 text-rose-600 dark:text-rose-300 hover:bg-rose-200 dark:hover:bg-rose-800 transition-colors shrink-0"
                                        title="Cancel & Discard Recording">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>

                                <!-- Live Pulse & Waveform Bars & Timer -->
                                <div class="flex items-center gap-2.5 flex-1 min-w-0 px-1">
                                    <!-- Red Live Pulse -->
                                    <span class="relative flex h-2.5 w-2.5 shrink-0">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-rose-500"></span>
                                    </span>

                                    <!-- Duration Clock -->
                                    <span class="text-xs font-mono font-bold text-rose-600 dark:text-rose-400 shrink-0"
                                          x-text="formatRecordingDuration(voiceRecording.duration)">0:00</span>

                                    <!-- Live Waveform Amplitude Bars -->
                                    <div class="flex-1 h-6 flex items-center gap-0.5 overflow-hidden">
                                        <template x-for="(bar, bIdx) in (voiceRecording.waveformData.length ? voiceRecording.waveformData : Array(24).fill(30))" :key="bIdx">
                                            <span class="w-1 bg-gradient-to-t from-rose-500 to-indigo-500 rounded-full transition-all duration-100 shrink-0"
                                                  :style="`height: ${bar}%;`"></span>
                                        </template>
                                    </div>
                                </div>

                                <!-- Review and Send Buttons -->
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <button type="button"
                                            @click="stopVoiceRecording(false)"
                                            class="px-2.5 py-1.5 rounded-lg bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-medium hover:bg-slate-300 dark:hover:bg-slate-600 transition-colors"
                                            title="Stop and preview">
                                        Review
                                    </button>
                                    <button type="button"
                                            @click="stopVoiceRecording(true)"
                                            class="p-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm transition-all"
                                            title="Send Voice Message">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </template>

                        <!-- Voice Review & Preview Mode (Before Sending) -->
                        <template x-if="voiceRecording.audioUrl && !voiceRecording.active">
                            <div class="flex items-center justify-between gap-3 flex-1 min-w-0 p-1.5 bg-indigo-50/80 dark:bg-indigo-950/40 rounded-xl border border-indigo-200 dark:border-indigo-800">
                                <!-- Discard Button -->
                                <button type="button"
                                        @click="cancelVoiceRecording()"
                                        class="p-2 rounded-lg bg-rose-100 dark:bg-rose-900/60 text-rose-600 dark:text-rose-300 hover:bg-rose-200 dark:hover:bg-rose-800 transition-colors shrink-0"
                                        title="Discard recording">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>

                                <!-- Play/Pause Preview Button & Duration -->
                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    <button type="button"
                                            @click="toggleVoicePreview()"
                                            class="w-8 h-8 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white flex items-center justify-center shrink-0 transition-transform active:scale-95 shadow-sm">
                                        <template x-if="voiceRecording.isPlayingPreview">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                <path fill-rule="evenodd" d="M6.75 5.25a.75.75 0 0 1 .75.75v12a.75.75 0 0 1-1.5 0v-12a.75.75 0 0 1 .75-.75Zm10.5 0a.75.75 0 0 1 .75.75v12a.75.75 0 0 1-1.5 0v-12a.75.75 0 0 1 .75-.75Z" clip-rule="evenodd" />
                                            </svg>
                                        </template>
                                        <template x-if="!voiceRecording.isPlayingPreview">
                                            <svg class="w-4 h-4 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                                                <path fill-rule="evenodd" d="M4.5 5.653c0-1.427 1.529-2.33 2.779-1.643l11.54 6.347c1.295.712 1.295 2.573 0 3.286L7.28 19.99c-1.25.687-2.779-.217-2.779-1.643V5.653Z" clip-rule="evenodd" />
                                            </svg>
                                        </template>
                                    </button>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate">Voice note recorded</p>
                                        <p class="text-[10px] text-slate-500 font-mono" x-text="formatRecordingDuration(voiceRecording.duration) + ' duration'"></p>
                                    </div>
                                </div>

                                <!-- Send Final Button -->
                                <button type="button"
                                        @click="sendVoiceRecording()"
                                        class="px-3.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-sm transition-all flex items-center gap-1.5 shrink-0"
                                        title="Send voice note">
                                    <span>Send</span>
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                                    </svg>
                                </button>
                            </div>
                        </template>

                    </div>

                </div>

            </div>

        </main>

        <!-- ========================================================================= -->
        <!-- PANEL 3: CONVERSATION DETAILS (RIGHT PANEL - COLLAPSIBLE)                  -->
        <!-- ========================================================================= -->
        <!-- Backdrop on mobile/tablet for Panel 3 -->
        <div x-show="showInfoPanel && activeConversation"
             x-transition.opacity
             @click="showInfoPanel = false"
             class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs z-30 lg:hidden"
             x-cloak></div>

        <aside x-show="showInfoPanel && activeConversation"
               x-transition:enter="transition ease-in-out duration-200 transform"
               x-transition:enter-start="translate-x-full"
               x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in-out duration-200 transform"
               x-transition:leave-start="translate-x-0"
               x-transition:leave-end="translate-x-full"
               class="fixed inset-y-0 right-0 z-40 w-full sm:w-80 lg:relative lg:inset-auto lg:translate-x-0 lg:z-10 bg-white dark:bg-slate-900 border-l border-slate-200 dark:border-slate-800 flex flex-col h-full shadow-2xl lg:shadow-none overflow-y-auto chat-scrollbar"
               x-cloak>

            <!-- Right Panel Header -->
            <div class="h-16 px-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between shrink-0">
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Conversation Details</h3>
                <button type="button"
                        @click="showInfoPanel = false"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Profile Overview Card -->
            <div class="p-6 text-center border-b border-slate-200/80 dark:border-slate-800">
                <img :src="avatarUrl(activeConversation?.avatar_url, activeConversation?.title)"
                     :alt="activeConversation?.title"
                     x-on:error="handleAvatarError($event, activeConversation?.title)"
                     class="w-20 h-20 rounded-full object-cover mx-auto ring-4 ring-indigo-500/20 shadow-md">
                <h4 class="text-base font-bold text-slate-800 dark:text-slate-100 mt-3 truncate" x-text="activeConversation?.title"></h4>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1" x-text="activeConversation?.description || (activeConversation?.type === 'group' ? 'Team Group Chat' : 'Direct 1-on-1 Chat')"></p>

                <!-- Quick Voice & Video Call Buttons (Direct chats, excluding Saved Messages) -->
                <template x-if="activeConversation && activeConversation.type !== 'group' && activeConversation.title !== 'Saved Messages'">
                    <div class="mt-3 flex items-center justify-center gap-2">
                        <button type="button"
                                @click="startVoiceCall(activeConversation?.other_user_id || activeConversation?.other_user?.id)"
                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold border border-emerald-500/20 transition-all shadow-xs cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                            </svg>
                            <span>Voice Call</span>
                        </button>
                        <button type="button"
                                @click="startVideoCall(activeConversation?.other_user_id || activeConversation?.other_user?.id)"
                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 text-xs font-semibold border border-indigo-500/20 transition-all shadow-xs cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                            </svg>
                            <span>Video Call</span>
                        </button>
                    </div>
                </template>

                <!-- Notifications Toggle -->
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
                    <span class="font-medium text-slate-700 dark:text-slate-300">Notifications</span>
                    <button type="button"
                            @click="toggleMute(activeConversation)"
                            :class="!activeConversation?.is_muted ? 'bg-indigo-600' : 'bg-slate-300 dark:bg-slate-700'"
                            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none">
                        <span :class="!activeConversation?.is_muted ? 'translate-x-4' : 'translate-x-0'"
                              class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                    </button>
                </div>
            </div>

            <!-- Navigation Tabs: Media, Files, Links, Members -->
            <div class="flex items-center gap-1 px-3 py-2 border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 text-xs font-semibold overflow-x-auto no-scrollbar shrink-0">
                <button type="button"
                        @click="profileTab = 'media'"
                        :class="profileTab === 'media' ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-xs border border-slate-200 dark:border-slate-700' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                        class="px-2.5 py-1.5 rounded-xl transition-all whitespace-nowrap flex items-center gap-1.5 cursor-pointer">
                    <span>Media</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full"
                          :class="profileTab === 'media' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300' : 'bg-slate-200 dark:bg-slate-700 text-slate-500'"
                          x-text="sharedMedia.length"></span>
                </button>
                <button type="button"
                        @click="profileTab = 'files'"
                        :class="profileTab === 'files' ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-xs border border-slate-200 dark:border-slate-700' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                        class="px-2.5 py-1.5 rounded-xl transition-all whitespace-nowrap flex items-center gap-1.5 cursor-pointer">
                    <span>Files</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full"
                          :class="profileTab === 'files' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300' : 'bg-slate-200 dark:bg-slate-700 text-slate-500'"
                          x-text="sharedFiles.length"></span>
                </button>
                <button type="button"
                        @click="profileTab = 'links'"
                        :class="profileTab === 'links' ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-xs border border-slate-200 dark:border-slate-700' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                        class="px-2.5 py-1.5 rounded-xl transition-all whitespace-nowrap flex items-center gap-1.5 cursor-pointer">
                    <span>Links</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full"
                          :class="profileTab === 'links' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300' : 'bg-slate-200 dark:bg-slate-700 text-slate-500'"
                          x-text="sharedLinks.length"></span>
                </button>
                <template x-if="activeConversation?.type === 'group'">
                    <button type="button"
                            @click="profileTab = 'members'"
                            :class="profileTab === 'members' ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-xs border border-slate-200 dark:border-slate-700' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                            class="px-2.5 py-1.5 rounded-xl transition-all whitespace-nowrap flex items-center gap-1.5 cursor-pointer">
                        <span>Members</span>
                        <span class="text-[10px] px-1.5 py-0.2 rounded-full"
                              :class="profileTab === 'members' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300' : 'bg-slate-200 dark:bg-slate-700 text-slate-500'"
                              x-text="activeConversation?.members?.length || 0"></span>
                    </button>
                </template>
            </div>

            <!-- TAB 1: SHARED MEDIA (Images & Videos) -->
            <div x-show="profileTab === 'media'" class="p-3">
                <div class="grid grid-cols-3 gap-1.5">
                    <template x-for="m in sharedMedia" :key="m.id">
                        <div class="aspect-square rounded-xl overflow-hidden bg-slate-100 dark:bg-slate-800 cursor-pointer hover:opacity-90 relative group shadow-2xs"
                             @click.stop="openMediaViewer(m)">
                            <!-- If video -->
                            <template x-if="isVideoAttachment(m)">
                                <div class="w-full h-full relative bg-slate-900 flex items-center justify-center">
                                    <video :src="m.file_url || m.file_path || m.view_url || ('/chat/attachments/' + m.id + '/view')"
                                           class="w-full h-full object-cover"></video>
                                    <div class="absolute inset-0 bg-black/30 flex items-center justify-center group-hover:scale-110 transition-transform">
                                        <span class="w-7 h-7 rounded-full bg-black/60 backdrop-blur-xs text-white flex items-center justify-center">
                                            <svg class="w-3.5 h-3.5 fill-current ml-0.5" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                        </span>
                                    </div>
                                </div>
                            </template>
                            <!-- If image -->
                            <template x-if="isImageAttachment(m)">
                                <img :src="m.file_url || m.file_path || m.view_url || ('/chat/attachments/' + m.id + '/view')"
                                     :alt="m.file_name"
                                     x-on:error="if (!$event.target.dataset.tried) { $event.target.dataset.tried = '1'; $event.target.src = m.view_url || ('/chat/attachments/' + m.id + '/view'); }"
                                     class="w-full h-full object-cover">
                            </template>
                        </div>
                    </template>
                </div>
                <template x-if="sharedMedia.length === 0">
                    <div class="py-12 px-4 text-center">
                        <svg class="w-10 h-10 mx-auto mb-2 text-slate-300 dark:text-slate-700" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                        <p class="text-xs text-slate-400 font-medium">No media shared yet</p>
                    </div>
                </template>
            </div>

            <!-- TAB 2: SHARED FILES & DOCS -->
            <div x-show="profileTab === 'files'" class="p-3 space-y-2">
                <template x-for="f in sharedFiles" :key="f.id">
                    <div class="flex items-center gap-3 p-2.5 rounded-xl border border-slate-200/80 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors text-xs">
                        <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-slate-800 dark:text-slate-100 truncate" x-text="f.file_name"></p>
                            <div class="flex items-center gap-2 text-[10px] text-slate-400 mt-0.5">
                                <span x-text="formatFileSize(f.file_size)"></span>
                                <span>•</span>
                                <span x-text="formatDateSnippet(f.time)"></span>
                            </div>
                        </div>
                        <a :href="f.download_url || f.file_url || ('/chat/attachments/' + f.id + '/download')"
                           target="_blank"
                           download
                           title="Download file"
                           class="p-2 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                        </a>
                    </div>
                </template>
                <template x-if="sharedFiles.length === 0">
                    <div class="py-12 px-4 text-center">
                        <svg class="w-10 h-10 mx-auto mb-2 text-slate-300 dark:text-slate-700" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        <p class="text-xs text-slate-400 font-medium">No files or documents shared</p>
                    </div>
                </template>
            </div>

            <!-- TAB 3: SHARED LINKS -->
            <div x-show="profileTab === 'links'" class="p-3 space-y-2">
                <template x-for="(link, idx) in sharedLinks" :key="idx">
                    <div class="p-2.5 rounded-xl border border-slate-200/80 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors text-xs">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 border border-indigo-200/50 dark:border-indigo-800"
                                  x-text="link.domain"></span>
                            <span class="text-[10px] text-slate-400" x-text="formatDateSnippet(link.time)"></span>
                        </div>
                        <a :href="link.url"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium break-all line-clamp-2 block"
                           x-text="link.url"></a>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate mt-1" x-text="'Sent by ' + link.sender_name"></p>
                    </div>
                </template>
                <template x-if="sharedLinks.length === 0">
                    <div class="py-12 px-4 text-center">
                        <svg class="w-10 h-10 mx-auto mb-2 text-slate-300 dark:text-slate-700" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                        </svg>
                        <p class="text-xs text-slate-400 font-medium">No links or websites shared</p>
                    </div>
                </template>
            </div>

            <!-- TAB 4: GROUP MEMBERS (if Group Chat) -->
            <template x-if="activeConversation?.type === 'group'">
                <div x-show="profileTab === 'members'" class="p-3">
                    <div class="flex items-center justify-between mb-3 px-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400">
                            Members (<span x-text="activeConversation?.members?.length || 0"></span>)
                        </span>
                        <button type="button"
                                @click="addMemberModal = true"
                                class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                            + Add Member
                        </button>
                    </div>

                    <div class="space-y-2">
                        <template x-for="m in activeConversation?.members || []" :key="m.id">
                            <div class="flex items-center justify-between gap-2 p-2 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="relative shrink-0">
                                        <img :src="avatarUrl(m.user?.avatar_url, m.user?.name)"
                                             :alt="m.user?.name"
                                             x-on:error="handleAvatarError($event, m.user?.name)"
                                             class="w-9 h-9 rounded-full object-cover">
                                        <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full ring-2 ring-white dark:ring-slate-900"
                                              :class="m.user?.is_online ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate" x-text="m.user?.name"></p>
                                        <p class="text-[10px] text-slate-400 capitalize" x-text="m.role || 'Member'"></p>
                                    </div>
                                </div>
                                <template x-if="m.role === 'admin'">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300">
                                        Admin
                                    </span>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

        </aside>

    </div>

    <!-- ========================================================================= -->
    <!-- MODAL: CREATE POLL (VOTING SYSTEM)                                       -->
    <!-- ========================================================================= -->
    <div x-show="pollModal"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
         x-transition
         x-cloak>
        <div @click.away="pollModal = false"
             class="w-full max-w-lg bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 flex flex-col max-h-[90vh]">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800 shrink-0">
                <div class="flex items-center gap-2">
                    <span class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                        </svg>
                    </span>
                    <div>
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Create New Poll</h3>
                        <p class="text-xs text-slate-400">Ask a question and gather team votes</p>
                    </div>
                </div>
                <button type="button" @click="pollModal = false" class="p-1 text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="space-y-4 my-4 overflow-y-auto chat-scrollbar flex-1 pr-1">
                <!-- Poll Question -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Poll Question</label>
                    <input type="text"
                           x-model="pollQuestion"
                           placeholder="Ask a question (e.g. Which design concept do you prefer?)"
                           class="w-full px-3.5 py-2.5 text-sm bg-slate-100 dark:bg-slate-800 rounded-xl border border-transparent focus:border-indigo-500 focus:outline-none">
                </div>

                <!-- Poll Options -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-2">
                        Poll Options (<span x-text="pollOptions.length"></span>/10)
                    </label>
                    <div class="space-y-2">
                        <template x-for="(opt, idx) in pollOptions" :key="idx">
                            <div class="flex items-center gap-2">
                                <span class="w-6 text-center text-xs font-bold text-slate-400" x-text="(idx + 1) + '.'"></span>
                                <input type="text"
                                       x-model="pollOptions[idx]"
                                       :placeholder="'Option ' + (idx + 1)"
                                       class="flex-1 px-3 py-2 text-xs sm:text-sm bg-slate-100 dark:bg-slate-800 rounded-xl border border-transparent focus:border-indigo-500 focus:outline-none">
                                <button type="button"
                                        x-show="pollOptions.length > 2"
                                        @click="removePollOption(idx)"
                                        class="p-2 text-slate-400 hover:text-rose-500 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                                        title="Remove Option">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>

                    <button type="button"
                            x-show="pollOptions.length < 10"
                            @click="addPollOption()"
                            class="mt-2.5 text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <span>Add an option</span>
                    </button>
                </div>

                <!-- Poll Settings -->
                <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 space-y-2.5 text-xs">
                    <label class="flex items-center justify-between cursor-pointer">
                        <div>
                            <p class="font-semibold text-slate-800 dark:text-slate-200">Multiple Answers</p>
                            <p class="text-[11px] text-slate-400">Allow members to select more than one option</p>
                        </div>
                        <input type="checkbox"
                               x-model="pollMultipleAnswers"
                               class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500">
                    </label>

                    <label class="flex items-center justify-between cursor-pointer pt-2 border-t border-slate-200/60 dark:border-slate-700/60">
                        <div>
                            <p class="font-semibold text-slate-800 dark:text-slate-200">Anonymous Voting</p>
                            <p class="text-[11px] text-slate-400">Keep voter identities private</p>
                        </div>
                        <input type="checkbox"
                               x-model="pollIsAnonymous"
                               class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500">
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800 shrink-0">
                <button type="button"
                        @click="pollModal = false"
                        class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">
                    Cancel
                </button>
                <button type="button"
                        @click="submitPoll()"
                        :disabled="isSubmittingPoll"
                        class="px-5 py-2 rounded-xl text-xs font-semibold bg-indigo-600 hover:bg-indigo-700 text-white shadow-md shadow-indigo-500/20 flex items-center gap-1.5">
                    <template x-if="isSubmittingPoll">
                        <svg class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </template>
                    <span>Send Poll</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL: EDIT / SET NICKNAME (WHATSAPP & TELEGRAM STYLE)                     -->
    <!-- ========================================================================= -->
    <div x-show="isEditingNickname"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         x-cloak>
        <div @click.away="cancelEditingNickname()"
             class="w-full max-w-sm bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 flex flex-col relative overflow-hidden">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-2xl bg-violet-100 dark:bg-violet-950/70 text-violet-600 dark:text-violet-400 flex items-center justify-center shrink-0 shadow-xs">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100 truncate"
                            x-text="(nicknameTargetConv?.nickname) ? 'Edit Nickname' : 'Set Nickname'"></h3>
                        <p class="text-[11px] text-slate-400 truncate max-w-[200px]"
                           x-text="'For: ' + (nicknameTargetConv?.real_name || nicknameTargetConv?.title || 'Contact')"></p>
                    </div>
                </div>
                <button type="button"
                        @click="cancelEditingNickname()"
                        class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Description -->
            <p class="text-xs text-slate-500 dark:text-slate-400 my-3 leading-relaxed">
                Custom nicknames are private and only visible to you. The contact will not be notified.
            </p>

            <!-- Input Form -->
            <form @submit.prevent="saveNickname(nicknameTargetConv)" class="space-y-4">
                <div>
                    <label for="chat-nickname-input" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                        Nickname
                    </label>
                    <div class="relative">
                        <input type="text"
                               id="chat-nickname-input"
                               x-model="nicknameInput"
                               maxlength="60"
                               placeholder="e.g. Dara (Head Digital)"
                               class="w-full px-3.5 py-2.5 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500 transition-all">
                        <template x-if="nicknameInput">
                            <button type="button"
                                    @click="nicknameInput = ''"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1 text-xs cursor-pointer">
                                ✕
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Footer Action Buttons -->
                <div class="flex items-center justify-between pt-1">
                    <!-- Clear / Remove Option -->
                    <div>
                        <template x-if="nicknameTargetConv?.nickname">
                            <button type="button"
                                    @click="nicknameInput = ''; saveNickname(nicknameTargetConv)"
                                    :disabled="isSavingNickname"
                                    class="text-xs font-semibold text-rose-500 hover:text-rose-600 dark:text-rose-400 p-1 rounded-xl hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors cursor-pointer disabled:opacity-50">
                                Remove Nickname
                            </button>
                        </template>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="cancelEditingNickname()"
                                :disabled="isSavingNickname"
                                class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer disabled:opacity-50">
                            Cancel
                        </button>
                        <button type="submit"
                                :disabled="isSavingNickname"
                                class="px-4 py-2 rounded-xl text-xs font-semibold text-white bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-700 hover:to-indigo-700 shadow-md shadow-violet-500/25 transition-all flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
                            <template x-if="isSavingNickname">
                                <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </template>
                            <span x-text="isSavingNickname ? 'Saving...' : 'Save Nickname'"></span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL: FORWARD MESSAGE                                                    -->
    <!-- ========================================================================= -->
    <div x-show="forwardModal"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
         x-transition
         x-cloak>
        <div @click.away="forwardModal = false"
             class="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 flex flex-col max-h-[85vh]">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800 shrink-0">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m15 15 6-6m0 0-6-6m6 6H9a6 6 0 0 0 0 12h3" />
                    </svg>
                    <span>Forward Message</span>
                </h3>
                <button type="button" @click="forwardModal = false" class="p-1 text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Preview of message to forward -->
            <div x-show="messageToForward" class="my-3 p-3 rounded-2xl bg-slate-100 dark:bg-slate-800/80 border-l-4 border-indigo-600 text-xs shrink-0">
                <p class="font-bold text-indigo-700 dark:text-indigo-300" x-text="messageToForward?.user?.name || 'User'"></p>
                <p class="text-slate-600 dark:text-slate-300 line-clamp-2 mt-0.5" x-text="messageToForward?.body || (messageToForward?.attachments?.length ? '[Attachment]' : (messageToForward?.poll_data ? '[Poll: ' + messageToForward.poll_data.question + ']' : 'Message'))"></p>
            </div>

            <!-- Search Conversation -->
            <div class="mb-3 shrink-0">
                <input type="text"
                       x-model="forwardSearch"
                       placeholder="Search recipient or group..."
                       class="w-full px-3.5 py-2 text-xs sm:text-sm bg-slate-100 dark:bg-slate-800 rounded-xl border border-transparent focus:border-indigo-500 focus:outline-none">
            </div>

            <!-- Conversations List to select -->
            <div class="space-y-1 overflow-y-auto chat-scrollbar flex-1">
                <template x-for="c in conversations.filter(c => !forwardSearch || c.title.toLowerCase().includes(forwardSearch.toLowerCase()))" :key="c.id">
                    <div @click="forwardToConversation(c)"
                         class="p-2.5 rounded-2xl flex items-center justify-between hover:bg-indigo-50/70 dark:hover:bg-indigo-950/40 cursor-pointer transition-colors group">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <img :src="avatarUrl(c.avatar_url, c.title)" :alt="c.title" x-on:error="handleAvatarError($event, c.title)" class="w-9 h-9 rounded-full object-cover shrink-0">
                            <div class="min-w-0">
                                <p class="text-xs sm:text-sm font-semibold text-slate-800 dark:text-slate-100 truncate" x-text="c.title"></p>
                                <p class="text-[10px] text-slate-400 capitalize" x-text="c.type"></p>
                            </div>
                        </div>
                        <button type="button"
                                :disabled="isForwarding"
                                class="px-3 py-1 text-xs font-semibold rounded-lg bg-indigo-600 text-white opacity-0 group-hover:opacity-100 transition-opacity">
                            Send
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 1: NEW DIRECT 1-ON-1 CHAT                                           -->
    <!-- ========================================================================= -->
    <div x-show="newChatModal"
         class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-sm"
         x-transition
         x-cloak>
        <div @click.away="newChatModal = false"
             class="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 p-5 sm:p-6 max-h-[90dvh] flex flex-col overflow-hidden">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800 shrink-0">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Start Direct Chat</h3>
                <button type="button" @click="newChatModal = false" class="p-1 text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Search User -->
            <div class="my-3 shrink-0">
                <input type="text"
                       x-model="newChatSearch"
                       placeholder="Search team member by name..."
                       class="w-full px-3.5 py-2 text-sm bg-slate-100 dark:bg-slate-800 rounded-xl border border-transparent focus:border-indigo-500 focus:bg-white dark:focus:bg-slate-800 focus:outline-none">
            </div>

            <!-- Users List -->
            <div class="space-y-1.5 flex-1 overflow-y-auto chat-scrollbar">
                <template x-for="u in usersDirectory.filter(u => !newChatSearch || u.name.toLowerCase().includes(newChatSearch.toLowerCase()))" :key="u.id">
                    <div @click="startPrivateChat(u.id)"
                         class="p-2.5 rounded-2xl flex items-center justify-between hover:bg-indigo-50/70 dark:hover:bg-indigo-950/40 cursor-pointer transition-colors group">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="relative shrink-0">
                                <img :src="avatarUrl(u.avatar_url, u.name)" :alt="u.name" x-on:error="handleAvatarError($event, u.name)" class="w-10 h-10 rounded-full object-cover">
                                <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full ring-2 ring-white dark:ring-slate-900"
                                      :class="u.is_online ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100 truncate" x-text="u.name"></p>
                                <p class="text-xs text-slate-400 truncate" x-text="u.team_role"></p>
                            </div>
                        </div>
                        <button type="button" class="px-3 py-1 text-xs font-semibold rounded-lg bg-indigo-600 text-white opacity-0 group-hover:opacity-100 transition-opacity">
                            Message
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 2: NEW GROUP CHAT                                                   -->
    <!-- ========================================================================= -->
    <div x-show="newGroupModal"
         class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-sm"
         x-transition
         x-cloak>
        <div @click.away="newGroupModal = false"
             class="w-full max-w-lg bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 p-5 sm:p-6 max-h-[90dvh] flex flex-col overflow-hidden">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800 shrink-0">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Create Team Group Chat</h3>
                <button type="button" @click="newGroupModal = false" class="p-1 text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="space-y-4 my-3 flex-1 overflow-y-auto chat-scrollbar pr-1">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Group Name</label>
                    <input type="text"
                           x-model="groupTitle"
                           placeholder="e.g. Digital Production Team, QC Team, Graphics..."
                           class="w-full px-3.5 py-2 text-sm bg-slate-100 dark:bg-slate-800 rounded-xl border border-transparent focus:border-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Description (Optional)</label>
                    <input type="text"
                           x-model="groupDescription"
                           placeholder="What is this group for?"
                           class="w-full px-3.5 py-2 text-sm bg-slate-100 dark:bg-slate-800 rounded-xl border border-transparent focus:border-indigo-500 focus:outline-none">
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase">
                            Select Members (<span x-text="selectedGroupUserIds.length"></span>)
                        </label>
                    </div>

                    <div class="border border-slate-200 dark:border-slate-700 rounded-2xl p-2 max-h-56 overflow-y-auto chat-scrollbar space-y-1">
                        <template x-for="u in usersDirectory" :key="u.id">
                            <label class="p-2 rounded-xl flex items-center justify-between hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer">
                                <div class="flex items-center gap-2.5">
                                    <img :src="avatarUrl(u.avatar_url, u.name)" :alt="u.name" x-on:error="handleAvatarError($event, u.name)" class="w-7 h-7 rounded-full object-cover">
                                    <div>
                                        <p class="text-xs font-semibold text-slate-800 dark:text-slate-100" x-text="u.name"></p>
                                        <p class="text-[10px] text-slate-400" x-text="u.team_role"></p>
                                    </div>
                                </div>
                                <input type="checkbox"
                                       :value="u.id"
                                       x-model="selectedGroupUserIds"
                                       class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500">
                            </label>
                        </template>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800 shrink-0">
                <button type="button"
                        @click="newGroupModal = false"
                        class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">
                    Cancel
                </button>
                <button type="button"
                        @click="createGroup()"
                        class="px-5 py-2 rounded-xl text-xs font-semibold bg-indigo-600 hover:bg-indigo-700 text-white shadow-md shadow-indigo-500/20">
                    Create Group
                </button>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 3: CHAT SETTINGS & APPEARANCE                                       -->
    <!-- ========================================================================= -->
    <div x-show="settingsModal"
         class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-sm"
         x-transition
         x-cloak>
        <div @click.away="settingsModal = false"
             class="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 p-5 sm:p-6 max-h-[90dvh] flex flex-col overflow-hidden">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800 shrink-0">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Chat Settings</h3>
                <button type="button" @click="settingsModal = false" class="p-1 text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Modal Navigation Tabs (if Dara or Super Admin) -->
            <template x-if="currentUser.can_manage_permissions">
                <div class="flex items-center gap-2 mt-3 p-1 bg-slate-100 dark:bg-slate-800/80 rounded-2xl text-xs font-semibold shrink-0">
                    <button type="button"
                            @click="settingsTab = 'appearance'"
                            :class="settingsTab === 'appearance' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-700'"
                            class="flex-1 py-1.5 px-3 rounded-xl transition-all text-center">
                        Appearance & Sound
                    </button>
                    <button type="button"
                            @click="settingsTab = 'permissions'; if (!adminUsers.length) loadAdminUsers();"
                            :class="settingsTab === 'permissions' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-700'"
                            class="flex-1 py-1.5 px-3 rounded-xl transition-all text-center flex items-center justify-center gap-1.5">
                        <span>Access & Storage</span>
                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                    </button>
                </div>
            </template>

            <!-- Scrollable Tab Content Area -->
            <div class="flex-1 overflow-y-auto chat-scrollbar my-2 pr-1 space-y-4">
            <!-- TAB 1: APPEARANCE & SOUND -->
            <div x-show="settingsTab === 'appearance'" class="space-y-4 my-2 text-xs">
                <!-- Status Message -->
                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Your Chat Status</label>
                    <input type="text"
                           x-model="userSettings.status_message"
                           placeholder="Available, Working on SEO, In a meeting..."
                           class="w-full px-3 py-2 bg-slate-100 dark:bg-slate-800 rounded-xl border border-transparent focus:border-indigo-500">
                </div>

                <!-- Bubble Color Presets -->
                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 uppercase mb-1.5">Outgoing Bubble Color</label>
                    <div class="flex items-center gap-2">
                        <template x-for="color in ['#4f46e5', '#0284c7', '#059669', '#7c3aed', '#e11d48', '#d97706', '#475569']" :key="color">
                            <button type="button"
                                    @click="userSettings.bubble_color = color; applyAppearance()"
                                    :style="'background-color: ' + color"
                                    :class="userSettings.bubble_color === color ? 'ring-3 ring-offset-2 ring-indigo-500' : ''"
                                    class="w-8 h-8 rounded-full transition-transform hover:scale-110 shadow-xs"></button>
                        </template>
                    </div>
                </div>

                <!-- Chat Wallpaper Section -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block font-bold text-slate-700 dark:text-slate-300 uppercase">Wallpaper Background</label>
                        <span class="text-[10px] text-slate-400 capitalize" x-text="userSettings.wallpaper || 'default'"></span>
                    </div>

                    <!-- Wallpaper Swatches Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-3">
                        <!-- 1. Telegram Default (Auto Light/Dark) -->
                        <button type="button"
                                @click="resetToDefaultWallpaper()"
                                :class="(userSettings.wallpaper === 'default' && !userSettings.wallpaper_image) ? 'ring-2 ring-indigo-600 ring-offset-2 scale-102 font-bold shadow-md' : 'opacity-85 hover:opacity-100'"
                                class="flex flex-col items-center gap-1.5 p-1.5 rounded-xl border border-slate-200 dark:border-slate-700 transition-all cursor-pointer">
                            <div class="w-full h-10 rounded-lg chat-wallpaper-default border border-slate-300 dark:border-slate-600 shadow-2xs"></div>
                            <span class="text-[11px] text-slate-700 dark:text-slate-300">Telegram (Auto)</span>
                        </button>

                        <!-- 2. Telegram Dark Mode -->
                        <button type="button"
                                @click="applyCustomWallpaperUrl('https://img.miniexcavator.org/ebay/Dashboard-Icon/chatbg-dark.webp')"
                                :class="userSettings.wallpaper_image === 'https://img.miniexcavator.org/ebay/Dashboard-Icon/chatbg-dark.webp' ? 'ring-2 ring-indigo-600 ring-offset-2 scale-102 font-bold shadow-md' : 'opacity-85 hover:opacity-100'"
                                class="flex flex-col items-center gap-1.5 p-1.5 rounded-xl border border-slate-200 dark:border-slate-700 transition-all cursor-pointer">
                            <div class="w-full h-10 rounded-lg border border-slate-300 dark:border-slate-600 shadow-2xs bg-cover bg-center" style="background-image: url('https://img.miniexcavator.org/ebay/Dashboard-Icon/chatbg-dark.webp');"></div>
                            <span class="text-[11px] text-slate-700 dark:text-slate-300">Telegram Dark</span>
                        </button>

                        <!-- 3. Telegram Light Mode -->
                        <button type="button"
                                @click="applyCustomWallpaperUrl('https://img.miniexcavator.org/ebay/Dashboard-Icon/chatbg-light.webp')"
                                :class="userSettings.wallpaper_image === 'https://img.miniexcavator.org/ebay/Dashboard-Icon/chatbg-light.webp' ? 'ring-2 ring-indigo-600 ring-offset-2 scale-102 font-bold shadow-md' : 'opacity-85 hover:opacity-100'"
                                class="flex flex-col items-center gap-1.5 p-1.5 rounded-xl border border-slate-200 dark:border-slate-700 transition-all cursor-pointer">
                            <div class="w-full h-10 rounded-lg border border-slate-300 dark:border-slate-600 shadow-2xs bg-cover bg-center" style="background-image: url('https://img.miniexcavator.org/ebay/Dashboard-Icon/chatbg-light.webp');"></div>
                            <span class="text-[11px] text-slate-700 dark:text-slate-300">Telegram Light</span>
                        </button>

                        <!-- 4. Dark Mesh -->
                        <button type="button"
                                @click="userSettings.wallpaper = 'mesh'; userSettings.wallpaper_image = ''; customWallpaperUrlInput = ''; applyAppearance(); saveSettings();"
                                :class="userSettings.wallpaper === 'mesh' ? 'ring-2 ring-indigo-600 ring-offset-2 scale-102 font-bold shadow-md' : 'opacity-85 hover:opacity-100'"
                                class="flex flex-col items-center gap-1.5 p-1.5 rounded-xl border border-slate-200 dark:border-slate-700 transition-all cursor-pointer">
                            <div class="w-full h-10 rounded-lg chat-wallpaper-mesh border border-slate-300 dark:border-slate-600 shadow-2xs"></div>
                            <span class="text-[11px] text-slate-700 dark:text-slate-300">Mesh</span>
                        </button>
                    </div>

                    <!-- Custom Wallpaper URL Input Box -->
                    <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/70 border border-slate-200 dark:border-slate-700/80 space-y-2 mb-3">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">
                            Custom Wallpaper URL
                        </label>
                        <div class="flex items-center gap-2">
                            <input type="url"
                                   x-model="customWallpaperUrlInput"
                                   @keydown.enter.prevent="applyCustomWallpaperUrl()"
                                   placeholder="https://img.miniexcavator.org/... or any image URL"
                                   class="flex-1 text-xs px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <button type="button"
                                    @click="applyCustomWallpaperUrl()"
                                    class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition-colors cursor-pointer shrink-0">
                                Apply URL
                            </button>
                        </div>
                        <div class="flex items-center gap-2 text-[11px] text-slate-400 flex-wrap">
                            <span>Quick URLs:</span>
                            <button type="button" @click="applyCustomWallpaperUrl('https://img.miniexcavator.org/ebay/Dashboard-Icon/chatbg-dark.webp')" class="text-indigo-600 dark:text-indigo-400 hover:underline">Dark WebP</button>
                            <span>&bull;</span>
                            <button type="button" @click="applyCustomWallpaperUrl('https://img.miniexcavator.org/ebay/Dashboard-Icon/chatbg-light.webp')" class="text-indigo-600 dark:text-indigo-400 hover:underline">Light WebP</button>
                            <span>&bull;</span>
                            <button type="button" @click="resetToDefaultWallpaper()" class="text-slate-500 dark:text-slate-400 hover:underline">Reset Default</button>
                        </div>
                    </div>

                    <!-- Custom Wallpaper Upload & Live Preview -->
                    <div class="p-2.5 rounded-2xl bg-slate-100/70 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/80 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <!-- Custom Wallpaper Preview Box -->
                            <div class="w-10 h-10 rounded-xl overflow-hidden bg-slate-200 dark:bg-slate-700 border border-slate-300 dark:border-slate-600 shrink-0 flex items-center justify-center relative">
                                <template x-if="userSettings.wallpaper_image">
                                    <img :src="userSettings.wallpaper_image" alt="Custom Wallpaper" class="w-full h-full object-cover">
                                </template>
                                <template x-if="!userSettings.wallpaper_image">
                                    <div class="w-full h-full chat-wallpaper-default"></div>
                                </template>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-800 dark:text-slate-200 truncate">Upload Background Photo</p>
                                <p class="text-[10px] text-slate-400 truncate">Choose an image from your computer</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 shrink-0">
                            <!-- Hidden File Input -->
                            <input type="file"
                                   accept="image/*"
                                   x-ref="wallpaperInput"
                                   @change="handleWallpaperUpload($event)"
                                   class="hidden">
                            <button type="button"
                                    @click="$refs.wallpaperInput.click()"
                                    class="px-2.5 py-1.5 rounded-xl bg-white dark:bg-slate-700 hover:bg-slate-50 dark:hover:bg-slate-600 text-indigo-600 dark:text-indigo-400 font-semibold text-xs border border-slate-200 dark:border-slate-600 shadow-2xs transition-colors cursor-pointer">
                                Choose Image
                            </button>
                            <template x-if="userSettings.wallpaper_image">
                                <button type="button"
                                        @click="resetToDefaultWallpaper()"
                                        title="Remove custom wallpaper"
                                        class="p-1.5 rounded-xl text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Notification Sound -->
                <div class="flex items-center justify-between pt-2">
                    <div>
                        <p class="font-bold text-slate-700 dark:text-slate-300">Message Notification Sound</p>
                        <p class="text-slate-400 text-[11px]">Play chime when messages arrive</p>
                    </div>
                    <button type="button"
                            @click="userSettings.notification_sound = !userSettings.notification_sound; if(userSettings.notification_sound) playChime();"
                            :class="userSettings.notification_sound ? 'bg-indigo-600' : 'bg-slate-300 dark:bg-slate-700'"
                            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out">
                        <span :class="userSettings.notification_sound ? 'translate-x-4' : 'translate-x-0'"
                              class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                    </button>
                </div>
            </div>

            <!-- TAB 2: ACCESS & STORAGE (Dara & Super Admin) -->
            <div x-show="settingsTab === 'permissions'" class="space-y-3.5 my-4 text-xs" x-cloak>
                <div class="p-3 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-2xl flex items-center justify-between gap-3">
                    <div>
                        <p class="font-bold text-emerald-900 dark:text-emerald-200">Auto WebP Compression Active</p>
                        <p class="text-[11px] text-emerald-700 dark:text-emerald-400">All chat images auto-convert to WebP after 5 mins to save server disk storage.</p>
                    </div>
                    <button type="button" @click="runConvertWebp()" :disabled="convertingWebp"
                            class="px-2.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-[11px] shrink-0 disabled:opacity-50">
                        <span x-show="!convertingWebp">Run Now</span>
                        <span x-show="convertingWebp" x-cloak>Processing...</span>
                    </button>
                </div>

                <div class="flex items-center justify-between gap-2">
                    <div class="relative flex-1">
                        <input type="text" x-model="adminSearch" placeholder="Search members..."
                               class="w-full pl-8 pr-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-xs border border-transparent focus:border-indigo-500">
                        <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <button type="button" @click="batchToggleAdmin(true)" class="px-2.5 py-1.5 rounded-xl bg-indigo-50 text-indigo-600 font-semibold hover:bg-indigo-100">All ON</button>
                    <button type="button" @click="batchToggleAdmin(false)" class="px-2.5 py-1.5 rounded-xl bg-rose-50 text-rose-600 font-semibold hover:bg-rose-100">All OFF</button>
                </div>

                <div class="max-h-56 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800 pr-1">
                    <template x-if="adminLoading">
                        <p class="text-center py-6 text-slate-400">Loading members...</p>
                    </template>
                    <template x-for="u in adminUsers.filter(u => !adminSearch || (u.name + ' ' + u.username).toLowerCase().includes(adminSearch.toLowerCase()))" :key="u.id">
                        <div class="py-2 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <img :src="avatarUrl(u.avatar_url, u.name)"
                                     :alt="u.name"
                                     x-on:error="handleAvatarError($event, u.name)"
                                     class="w-7 h-7 rounded-full object-cover shrink-0">
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-800 dark:text-slate-100 truncate" x-text="u.name"></p>
                                    <p class="text-[10px] text-slate-400 truncate" x-text="'@' + u.username + ' · ' + u.team_role"></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-[10px] font-semibold"
                                      :class="u.can_use_chat ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'"
                                      x-text="u.can_use_chat ? 'ON' : 'OFF'"></span>
                                <button type="button"
                                        @click="toggleAdminUser(u)"
                                        :disabled="u.is_manager"
                                        class="relative inline-flex h-4 w-8 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out disabled:opacity-40"
                                        :class="u.can_use_chat ? 'bg-indigo-600' : 'bg-slate-300 dark:bg-slate-700'">
                                    <span class="pointer-events-none inline-block h-3 w-3 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                          :class="u.can_use_chat ? 'translate-x-4' : 'translate-x-0'"></span>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            </div>

            <div class="flex justify-between items-center gap-2 pt-3 border-t border-slate-200 dark:border-slate-800 shrink-0">
                <button type="button"
                        x-show="settingsTab === 'appearance'"
                        @click="resetSettings()"
                        class="text-xs text-rose-500 hover:underline">
                    Reset Defaults
                </button>
                <button type="button"
                        x-show="settingsTab === 'appearance'"
                        @click="saveSettings()"
                        class="ml-auto px-5 py-2 rounded-xl text-xs font-semibold bg-indigo-600 hover:bg-indigo-700 text-white shadow-md shadow-indigo-500/20">
                    Save Changes
                </button>
                <button type="button"
                        x-show="settingsTab === 'permissions'"
                        @click="settingsModal = false"
                        class="ml-auto px-5 py-2 rounded-xl text-xs font-semibold bg-slate-800 hover:bg-slate-900 text-white">
                    Done
                </button>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL: WHATSAPP-STYLE STICKER ACTION SHEET                                -->
    <!-- ========================================================================= -->
    <div x-show="stickerActionModal && selectedStickerMessage"
         class="fixed inset-0 z-60 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         x-cloak>
        <div @click.away="closeStickerActionModal()"
             class="w-full max-w-xs bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 flex flex-col items-center text-center">

            <!-- Sticker Preview (Click to view full screen) -->
            <div class="w-40 h-40 flex items-center justify-center mb-3 cursor-zoom-in group/stkp"
                 @click="openLightbox(selectedStickerMessage?.body || selectedStickerMessage?.content, 'Sticker', 'image'); closeStickerActionModal();"
                 title="Click to view full screen">
                <img :src="selectedStickerMessage?.body || selectedStickerMessage?.content"
                     alt="Sticker Preview"
                     class="max-w-full max-h-full object-contain drop-shadow-xl select-none group-hover/stkp:scale-105 transition-transform">
            </div>

            <!-- Author & Subtitle -->
            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-100"
                x-text="selectedStickerMessage?.user?.name ? ('Sticker by ' + selectedStickerMessage.user.name) : 'Sticker'"></h4>
            <p class="text-xs text-slate-400 mt-0.5">DGT WhatsApp Sticker</p>

            <!-- Action Buttons -->
            <div class="w-full space-y-2 mt-5">
                <!-- Save to Personal Stickers -->
                <button type="button"
                        x-show="!isStickerSaved(selectedStickerMessage)"
                        @click="saveStickerFromMessage(selectedStickerMessage); closeStickerActionModal();"
                        class="w-full py-2.5 px-4 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs flex items-center justify-center gap-2 shadow-lg shadow-emerald-500/25 transition-transform active:scale-95 cursor-pointer">
                    <svg class="w-4 h-4 text-amber-300" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                    <span>⭐ Add to Favorites / Stickers</span>
                </button>

                <div x-show="isStickerSaved(selectedStickerMessage)"
                     class="w-full py-2 px-3 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-xs font-bold flex items-center justify-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    <span>Already in your Stickers ⭐</span>
                </div>

                <!-- Re-send in current chat -->
                <button type="button"
                        @click="sendSticker({ image_url: selectedStickerMessage?.body || selectedStickerMessage?.content, name: 'Sticker' }); closeStickerActionModal();"
                        class="w-full py-2 px-4 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs flex items-center justify-center gap-1.5 transition-colors cursor-pointer">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                    </svg>
                    <span>Send Sticker</span>
                </button>

                <!-- Cancel / Close -->
                <button type="button"
                        @click="closeStickerActionModal()"
                        class="w-full py-1.5 text-xs text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 font-medium transition-colors cursor-pointer">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL: STICKER CREATOR STUDIO (WHATSAPP WEB FULLSCREEN - EXACT MATCH TO IMAGE) -->
    <!-- ========================================================================= -->
    <div x-show="stickerCreatorModal"
         class="fixed inset-0 z-50 flex flex-col bg-[#0b141a] text-slate-100 select-none overflow-hidden"
         @mousemove="handleCanvasMouseMove($event)"
         @mouseup="handleCanvasMouseUp()"
         @touchmove="handleCanvasTouchMove($event)"
         @touchend="handleCanvasTouchEnd()"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-cloak>

        <!-- 1. Top Header Bar (WhatsApp Web Style) -->
        <div class="h-14 px-4 sm:px-6 flex items-center justify-between bg-[#0b141a] border-b border-white/5 shrink-0 z-30">
            <!-- Left: Close (X) button -->
            <button type="button"
                    @click="stickerCreatorModal = false"
                    title="Close"
                    class="w-10 h-10 rounded-full flex items-center justify-center text-slate-300 hover:text-white hover:bg-white/10 transition-colors cursor-pointer">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Center: Change Photo Pill -->
            <div class="flex items-center gap-2">
                <label class="px-3.5 py-1.5 rounded-full bg-white/10 hover:bg-white/15 text-xs font-semibold text-slate-200 flex items-center gap-2 cursor-pointer transition-colors shadow-xs">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                    </svg>
                    <span x-text="stickerCreator.previewUrl ? 'Change Photo' : 'Select Photo'"></span>
                    <input type="file" accept="image/*" class="hidden" @change="handleStickerImageSelect($event)">
                </label>
            </div>

            <!-- Right: Action Tool Icons (Emoji, Text 'T', Cutout/Outline, Reset) -->
            <div class="flex items-center gap-1 sm:gap-2">
                <!-- Emoji Tool Button -->
                <button type="button"
                        @click="stickerCreator.activeTool = (stickerCreator.activeTool === 'emoji' ? '' : 'emoji')"
                        :class="stickerCreator.activeTool === 'emoji' ? 'bg-white/20 text-white' : 'text-slate-300 hover:text-white hover:bg-white/10'"
                        class="w-10 h-10 rounded-full flex items-center justify-center transition-colors cursor-pointer"
                        title="Add Emoji / Sticker">
                    <svg class="w-5 h-5 text-amber-300" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 100-2 1 1 0 000 2zm7-1a1 1 0 11-2 0 1 1 0 012 0zm-7.536 5.879a1 1 0 001.415 0 4.978 4.978 0 006.242 0 1 1 0 00-1.414-1.414 2.978 2.978 0 01-3.414 0 1 1 0 00-1.414 0 1 1 0 00-.829 1.414z" clip-rule="evenodd" />
                    </svg>
                </button>

                <!-- Text Tool Button (T) -->
                <button type="button"
                        @click="stickerCreator.activeTool = (stickerCreator.activeTool === 'text' ? '' : 'text')"
                        :class="stickerCreator.activeTool === 'text' ? 'bg-white/20 text-white' : 'text-slate-300 hover:text-white hover:bg-white/10'"
                        class="w-10 h-10 rounded-full flex items-center justify-center font-serif text-lg font-black transition-colors cursor-pointer"
                        title="Text & Multi-Color">
                    T
                </button>

                <!-- Cutout / Outline Tool Button -->
                <button type="button"
                        @click="stickerCreator.activeTool = (stickerCreator.activeTool === 'crop' ? '' : 'crop')"
                        :class="stickerCreator.activeTool === 'crop' ? 'bg-white/20 text-white' : 'text-slate-300 hover:text-white hover:bg-white/10'"
                        class="w-10 h-10 rounded-full flex items-center justify-center transition-colors cursor-pointer"
                        title="Cutout Shape & Outline">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                    </svg>
                </button>

                <!-- Reset Transform Button -->
                <button type="button"
                        @click="resetStickerTransform()"
                        title="Reset Transform"
                        class="w-10 h-10 rounded-full flex items-center justify-center text-slate-400 hover:text-white hover:bg-white/10 transition-colors cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- 2. Tool Sub-Bars (Floating under Header) -->
        <!-- A. Text & Multi-Color Toolbar -->
        <div x-show="stickerCreator.activeTool === 'text'"
             x-transition
             class="px-4 py-3 bg-[#111b21] border-b border-white/10 shrink-0 z-20 space-y-2.5 max-w-4xl mx-auto w-full">
            <div class="flex items-center gap-2">
                <div class="relative flex-1">
                    <input type="text"
                           x-model="stickerCreator.caption"
                           placeholder="Type sticker caption (e.g. Thank... Kim)"
                           maxlength="45"
                           class="w-full px-3.5 py-2 rounded-xl bg-slate-900 border border-slate-700 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 pr-8">
                    <button type="button"
                            x-show="stickerCreator.caption"
                            @click="stickerCreator.caption = ''"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white text-xs cursor-pointer">✕</button>
                </div>
                <!-- Snap Align -->
                <div class="flex items-center gap-1 shrink-0">
                    <button type="button" @click="setStickerTextPosition('top')" class="px-2.5 py-2 rounded-xl bg-slate-800 hover:bg-emerald-600 text-[10px] font-bold text-slate-300 hover:text-white cursor-pointer" title="Move to Top">Top</button>
                    <button type="button" @click="setStickerTextPosition('center')" class="px-2.5 py-2 rounded-xl bg-slate-800 hover:bg-emerald-600 text-[10px] font-bold text-slate-300 hover:text-white cursor-pointer" title="Move to Center">Mid</button>
                    <button type="button" @click="setStickerTextPosition('bottom')" class="px-2.5 py-2 rounded-xl bg-slate-800 hover:bg-emerald-600 text-[10px] font-bold text-slate-300 hover:text-white cursor-pointer" title="Move to Bottom">Bot</button>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <!-- Zoom Text Slider & +/- Buttons (User requirement: Can zoom texts) -->
                <div class="flex items-center gap-2 bg-slate-900 px-3 py-1.5 rounded-xl border border-slate-800">
                    <span class="text-[11px] font-bold text-slate-300 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607ZM10.5 7.5v6m3-3h-6" />
                        </svg>
                        Zoom:
                    </span>
                    <button type="button" @click="zoomStickerText(-3)" class="w-6 h-6 rounded-lg bg-slate-800 hover:bg-emerald-600 text-white font-bold flex items-center justify-center text-xs cursor-pointer">-</button>
                    <input type="range" min="14" max="72" step="2" x-model.number="stickerCreator.fontSize" class="w-24 sm:w-32 accent-emerald-500 cursor-pointer h-1.5 bg-slate-700 rounded-lg">
                    <button type="button" @click="zoomStickerText(3)" class="w-6 h-6 rounded-lg bg-slate-800 hover:bg-emerald-600 text-white font-bold flex items-center justify-center text-xs cursor-pointer">+</button>
                    <span class="font-mono text-emerald-400 font-bold text-xs w-9 text-right" x-text="stickerCreator.fontSize + 'px'"></span>
                </div>

                <!-- Multi-Color Swatches & Native Color Picker (User requirement: Use multi color etc) -->
                <div class="flex items-center gap-1.5 flex-wrap">
                    <template x-for="c in ['#ffffff', '#000000', '#facc15', '#22c55e', '#06b6d4', '#3b82f6', '#8b5cf6', '#ec4899', '#ef4444', '#f97316', '#14b8a6', '#eab308']" :key="c">
                        <button type="button"
                                @click="stickerCreator.textColor = c; if(c === '#000000') stickerCreator.strokeColor = '#ffffff'; else stickerCreator.strokeColor = '#000000';"
                                :style="`background-color: ${c};`"
                                :class="stickerCreator.textColor === c ? 'ring-2 ring-emerald-400 scale-120' : 'hover:scale-110'"
                                class="w-5 h-5 rounded-full border border-white/20 transition-all cursor-pointer shadow-xs"></button>
                    </template>
                    <!-- Custom Color Input -->
                    <label class="w-5 h-5 rounded-full bg-gradient-to-tr from-rose-500 via-emerald-400 to-indigo-500 border border-white/30 flex items-center justify-center cursor-pointer hover:scale-110 transition-transform relative"
                           title="Pick Any Custom Color">
                        <input type="color" x-model="stickerCreator.textColor" class="opacity-0 absolute inset-0 w-full h-full cursor-pointer">
                    </label>
                </div>

                <!-- Background Badge Toggles & Font Style -->
                <div class="flex items-center gap-2">
                    <div class="flex items-center gap-1 bg-slate-900 p-1 rounded-xl border border-slate-800">
                        <button type="button" @click="stickerCreator.textBgStyle = 'none'" :class="stickerCreator.textBgStyle === 'none' ? 'bg-emerald-600 text-white font-bold' : 'text-slate-400 hover:text-white'" class="px-2 py-0.5 rounded-lg text-[10px] cursor-pointer">None</button>
                        <button type="button" @click="stickerCreator.textBgStyle = 'badge_dark'" :class="stickerCreator.textBgStyle === 'badge_dark' ? 'bg-emerald-600 text-white font-bold' : 'text-slate-400 hover:text-white'" class="px-2 py-0.5 rounded-lg text-[10px] cursor-pointer">⬛ Dark</button>
                        <button type="button" @click="stickerCreator.textBgStyle = 'badge_light'" :class="stickerCreator.textBgStyle === 'badge_light' ? 'bg-emerald-600 text-white font-bold' : 'text-slate-400 hover:text-white'" class="px-2 py-0.5 rounded-lg text-[10px] cursor-pointer">⬜ Light</button>
                    </div>

                    <select x-model="stickerCreator.fontFamily" class="px-2 py-1 rounded-xl bg-slate-900 border border-slate-800 text-[11px] text-white focus:outline-none">
                        <option value="Impact">Impact</option>
                        <option value="system-ui">Sans</option>
                        <option value="Comic Sans MS">Comic</option>
                        <option value="Georgia">Serif</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- B. Emoji Suggestions Toolbar -->
        <div x-show="stickerCreator.activeTool === 'emoji'"
             x-transition
             class="px-4 py-2.5 bg-[#111b21] border-b border-white/10 shrink-0 z-20 flex items-center justify-center gap-2 flex-wrap">
            <template x-for="emo in ['😂', '❤️', '🔥', '👍', '🙏', '🎉', '🚫', '👽', '😍', '✨', '👏', '😎', '🤣', '😭', '💀', '💯']" :key="emo">
                <button type="button"
                        @click="appendStickerEmoji(emo)"
                        class="w-8 h-8 rounded-lg hover:bg-white/15 text-lg flex items-center justify-center cursor-pointer transition-transform hover:scale-125"
                        x-text="emo"></button>
            </template>
        </div>

        <!-- C. Cutout Shape & Outline Toolbar -->
        <div x-show="stickerCreator.activeTool === 'crop'"
             x-transition
             class="px-4 py-2.5 bg-[#111b21] border-b border-white/10 shrink-0 z-20 flex items-center justify-center gap-3 flex-wrap">
            <button type="button"
                    @click="toggleStickerOutline()"
                    :class="stickerCreator.hasStickerOutline ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-slate-400'"
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-colors cursor-pointer flex items-center gap-1.5">
                <span x-text="stickerCreator.hasStickerOutline ? '✨ White Contour Outline: ON' : 'Outline: OFF'"></span>
            </button>
            <div class="flex items-center gap-1.5 bg-slate-900 p-1 rounded-xl border border-slate-800">
                <button type="button" @click="stickerCreator.cropShape = 'fit'" :class="stickerCreator.cropShape === 'fit' ? 'bg-emerald-600 text-white' : 'text-slate-400'" class="px-2.5 py-1 rounded-lg text-xs font-semibold cursor-pointer">Fit / Free</button>
                <button type="button" @click="stickerCreator.cropShape = 'circle'" :class="stickerCreator.cropShape === 'circle' ? 'bg-emerald-600 text-white' : 'text-slate-400'" class="px-2.5 py-1 rounded-lg text-xs font-semibold cursor-pointer">Circle</button>
                <button type="button" @click="stickerCreator.cropShape = 'square'" :class="stickerCreator.cropShape === 'square' ? 'bg-emerald-600 text-white' : 'text-slate-400'" class="px-2.5 py-1 rounded-lg text-xs font-semibold cursor-pointer">Square</button>
            </div>
        </div>

        <!-- 3. Full Viewport Canvas Stage (WhatsApp Dark Checkered Transparency Grid) -->
        <div class="flex-1 relative w-full h-full overflow-hidden flex items-center justify-center bg-[radial-gradient(#334155_1.4px,transparent_1.4px)] [background-size:16px_16px] bg-[#0c1317]">
            <template x-if="!stickerCreator.previewUrl">
                <label class="w-80 h-80 rounded-3xl border-2 border-dashed border-slate-700 hover:border-emerald-500 bg-[#0e1626] flex flex-col items-center justify-center p-6 cursor-pointer text-center group transition-all shadow-inner">
                    <div class="w-16 h-16 rounded-3xl bg-emerald-500/15 text-emerald-400 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </div>
                    <span class="text-sm font-bold text-slate-200">Choose Sticker Image</span>
                    <span class="text-xs text-slate-400 mt-1">PNG, JPG, WebP, SVG</span>
                    <input type="file" accept="image/*" class="hidden" @change="handleStickerImageSelect($event)">
                </label>
            </template>

            <template x-if="stickerCreator.previewUrl">
                <div id="sticker-preview-canvas-box"
                     @mousedown="startDragStickerImage($event)"
                     @touchstart="startDragStickerImage($event)"
                     @wheel.prevent="handleCanvasWheel($event)"
                     class="relative w-full h-full max-w-2xl max-h-[75vh] flex items-center justify-center select-none cursor-move">

                    <!-- Movable & Scalable Sticker Image with Solid White Contour Outline -->
                    <img :src="stickerCreator.activePreviewUrl || stickerCreator.previewUrl"
                         :style="`transform: translate(${stickerCreator.panX}px, ${stickerCreator.panY}px) scale(${stickerCreator.zoom});`"
                         :class="{
                             'rounded-3xl w-80 h-80 sm:w-96 sm:h-96 object-cover': stickerCreator.cropShape === 'square',
                             'rounded-full w-80 h-80 sm:w-96 sm:h-96 object-cover': stickerCreator.cropShape === 'circle',
                             'max-w-[85%] max-h-[85%] object-contain': stickerCreator.cropShape === 'fit',
                             'drop-shadow-[3.5px_0_0_#ffffff] drop-shadow-[-3.5px_0_0_#ffffff] drop-shadow-[0_3.5px_0_#ffffff] drop-shadow-[0_-3.5px_0_#ffffff] drop-shadow-[2.5px_2.5px_0_#ffffff] drop-shadow-[-2.5px_-2.5px_0_#ffffff] drop-shadow-[2.5px_-2.5px_0_#ffffff] drop-shadow-[-2.5px_2.5px_0_#ffffff] drop-shadow-[0_0_6px_rgba(255,255,255,0.9)]': stickerCreator.hasStickerOutline
                         }"
                         class="transition-transform duration-75 select-none pointer-events-none drop-shadow-lg">

                    <!-- Draggable & Scalable Text Overlay (Move anywhere & zoom) -->
                    <div :style="`left: ${stickerCreator.textX}%; top: ${stickerCreator.textY}%;`"
                         @mousedown.stop="startDragStickerText($event)"
                         @touchstart.stop="startDragStickerText($event)"
                         class="absolute -translate-x-1/2 -translate-y-1/2 cursor-grab active:cursor-grabbing select-none px-3.5 py-1.5 rounded-2xl border-2 transition-all duration-75 group/drag z-30"
                         :class="{
                             'bg-black/85 border-white/40 shadow-xl': stickerCreator.textBgStyle === 'badge_dark',
                             'bg-white/95 border-black/30 shadow-xl': stickerCreator.textBgStyle === 'badge_light',
                             'border-emerald-400 bg-emerald-600/90 shadow-xl': stickerCreator.textBgStyle === 'badge_color',
                             'border-dashed border-white/60 hover:border-emerald-400 hover:bg-black/25': stickerCreator.textBgStyle === 'none'
                         }"
                         title="Click and drag anywhere to move text">

                        <!-- Rendered Text with Zoom & Multi-Color -->
                        <span :style="`font-family: ${stickerCreator.fontFamily}; font-size: ${stickerCreator.fontSize}px; color: ${stickerCreator.textColor}; -webkit-text-stroke: ${stickerCreator.hasStroke ? (Math.max(1, Math.round(stickerCreator.fontSize * 0.08)) + 'px ' + stickerCreator.strokeColor) : 'none'}; text-shadow: 0 2px 10px rgba(0,0,0,0.85);`" class="font-black whitespace-nowrap block tracking-wide select-none leading-none"
                              x-text="stickerCreator.caption || 'Caption Text'"></span>

                        <!-- Corner Zoom / Resize Handle (Drag to scale text) -->
                        <div @mousedown.stop="startZoomStickerText($event)"
                             @touchstart.stop="startZoomStickerText($event)"
                             title="Drag right/left to scale text"
                             class="absolute -bottom-3 -right-3 w-7 h-7 rounded-full bg-emerald-500 hover:bg-emerald-400 text-white flex items-center justify-center shadow-lg cursor-nwse-resize active:scale-125 transition-transform z-40">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                            </svg>
                        </div>

                        <!-- Drag Label Badge -->
                        <span class="absolute -top-5 left-1/2 -translate-x-1/2 text-[9px] font-bold bg-black/90 text-emerald-400 px-2 py-0.5 rounded-full opacity-0 group-hover/drag:opacity-100 transition-opacity pointer-events-none whitespace-nowrap shadow-sm border border-emerald-500/30">
                            ⠿ Move Text
                        </span>
                    </div>

                    <!-- Floating Zoom Pill (Bottom Left of Canvas) -->
                    <div class="absolute bottom-4 left-4 flex items-center gap-1.5 p-1 rounded-xl bg-black/75 backdrop-blur-md border border-white/15 text-[11px] text-white z-20 pointer-events-auto">
                        <span class="px-1 text-[10px] text-slate-400 font-bold">Image:</span>
                        <button type="button" @click="zoomStickerImage(-0.1)" class="w-6 h-6 rounded-lg bg-white/10 hover:bg-emerald-600 flex items-center justify-center font-bold text-xs cursor-pointer">-</button>
                        <span class="font-mono font-bold text-[11px] px-1" x-text="Math.round(stickerCreator.zoom * 100) + '%'"></span>
                        <button type="button" @click="zoomStickerImage(0.1)" class="w-6 h-6 rounded-lg bg-white/10 hover:bg-emerald-600 flex items-center justify-center font-bold text-xs cursor-pointer">+</button>
                    </div>
                </div>
            </template>
        </div>

        <!-- 4. Bottom Footer Bar (WhatsApp Web Style - Exact Match with Image) -->
        <div class="h-16 px-4 sm:px-6 flex items-center justify-between bg-[#0b141a] border-t border-white/5 shrink-0 z-30 pb-[env(safe-area-inset-bottom,0px)]">
            <!-- Bottom Left: Chat Recipient Name (like 'Kali' in image) -->
            <div class="flex items-center gap-2 max-w-[200px]">
                <span class="text-xs font-semibold text-slate-400 select-none truncate"
                      x-text="activeConversation ? (activeConversation.nickname || activeConversation.title) : 'Kali'"></span>
            </div>

            <!-- Bottom Center: Mode Thumbnails ([1] Original Photo vs [2] Sticker with White Outline) -->
            <div class="flex items-center gap-2">
                <!-- Thumbnail 1: Standard Image -->
                <button type="button"
                        @click="stickerCreator.hasStickerOutline = false; stickerCreator.activePreviewUrl = stickerCreator.originalPreviewUrl || stickerCreator.previewUrl;"
                        class="w-10 h-10 rounded-xl border-2 overflow-hidden p-0.5 transition-all cursor-pointer flex items-center justify-center bg-[#111b21]"
                        :class="!stickerCreator.hasStickerOutline ? 'border-emerald-500 scale-105 shadow-md shadow-emerald-500/20' : 'border-white/15 opacity-50 hover:opacity-100'"
                        title="Standard Photo (No Outline)">
                    <img :src="stickerCreator.originalPreviewUrl || stickerCreator.previewUrl || '/images/default-avatar.png'"
                         class="w-full h-full object-cover rounded-lg">
                </button>

                <!-- Thumbnail 2: Sticker with White Outline (Selected in WhatsApp Screenshot!) -->
                <button type="button"
                        @click="stickerCreator.hasStickerOutline = true; stickerCreator.activePreviewUrl = stickerCreator.cutoutPreviewUrl || stickerCreator.originalPreviewUrl || stickerCreator.previewUrl;"
                        class="w-10 h-10 rounded-xl border-2 overflow-hidden p-0.5 transition-all cursor-pointer relative flex items-center justify-center bg-[#111b21]"
                        :class="stickerCreator.hasStickerOutline ? 'border-emerald-500 scale-105 shadow-md shadow-emerald-500/20' : 'border-white/15 opacity-50 hover:opacity-100'"
                        title="WhatsApp Cutout Sticker (With White Contour Outline)">
                    <img :src="stickerCreator.cutoutPreviewUrl || stickerCreator.originalPreviewUrl || stickerCreator.previewUrl || '/images/default-avatar.png'"
                         class="w-full h-full object-contain rounded-lg drop-shadow-[0_0_2px_#ffffff]">
                    <span class="absolute top-0.5 right-0.5 w-2 h-2 rounded-full bg-emerald-400"></span>
                </button>
            </div>

            <!-- Bottom Right: Save to Pack & WhatsApp Green Circular Send Button -->
            <div class="flex items-center gap-3">
                <!-- Save to Pack Button -->
                <button type="button"
                        @click="saveAndSendCustomSticker(false)"
                        :disabled="!stickerCreator.previewUrl || stickerCreator.isProcessing"
                        class="p-2 rounded-full text-slate-400 hover:text-white hover:bg-white/10 transition-colors cursor-pointer disabled:opacity-30"
                        title="Save to My Private Stickers (Without Sending)">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </button>

                <!-- WhatsApp Green Circular Send Button -->
                <button type="button"
                        @click="saveAndSendCustomSticker(true)"
                        :disabled="!stickerCreator.previewUrl || stickerCreator.isProcessing"
                        class="w-11 h-11 rounded-full bg-[#00a884] hover:bg-[#02906f] text-white flex items-center justify-center shadow-lg active:scale-95 disabled:opacity-40 transition-all cursor-pointer"
                        title="Send Sticker">
                    <template x-if="stickerCreator.isProcessing">
                        <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                    </template>
                    <template x-if="!stickerCreator.isProcessing">
                        <svg class="w-5 h-5 translate-x-0.5 -translate-y-0.5 rotate-45" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
                        </svg>
                    </template>
                </button>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL: CONTACT / GROUP PROFILE CARD (EXACT MATCH TO IMAGE 3)             -->
    <!-- ========================================================================= -->
    <div x-show="profileModal"
         class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-950/65 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         x-cloak>
        <div @click.away="profileModal = false"
             class="w-full max-w-md bg-white dark:bg-slate-900 rounded-t-3xl sm:rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 flex flex-col h-[88dvh] sm:h-[650px] max-h-[92dvh] overflow-hidden">

            <!-- 1. Header Bar: Close [X], Title, Edit [✎] (Image 3) / Back [←] when in Media view -->
            <div class="h-14 px-4 border-b border-slate-100 dark:border-slate-800/80 flex items-center justify-between shrink-0 bg-slate-50/50 dark:bg-slate-900/50">
                <div class="flex items-center gap-2">
                    <!-- If details: Close [X] -->
                    <template x-if="profileTab === 'details'">
                        <button type="button"
                                @click="profileModal = false"
                                class="p-1.5 rounded-full hover:bg-slate-200/70 dark:hover:bg-slate-800 text-slate-500 hover:text-slate-800 dark:hover:text-slate-100 transition-colors cursor-pointer"
                                title="Close">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </template>

                    <!-- If sub-tab (media/docs/links): Back [←] button to return to details -->
                    <template x-if="profileTab !== 'details'">
                        <button type="button"
                                @click="profileTab = 'details'"
                                class="p-1.5 rounded-xl hover:bg-slate-200/70 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition-colors cursor-pointer flex items-center gap-1.5 text-xs font-semibold"
                                title="Back to Contact Info">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                            </svg>
                            <span>Back</span>
                        </button>
                    </template>
                </div>

                <h3 class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider"
                    x-text="profileTab === 'details' ? (activeConversation?.type === 'group' ? 'Group Info' : 'Contact Info') : 'Media, links and docs'"></h3>

                <div class="flex items-center gap-1">
                    <!-- Edit Details Toggle (Group Admin only, details view) -->
                    <template x-if="profileTab === 'details' && activeConversation?.type === 'group' && (activeConversation?.is_admin || Number(activeConversation?.created_by) === Number(currentUser.id) || currentUser.can_manage_permissions)">
                        <button type="button"
                                @click="isEditingGroupDetails = !isEditingGroupDetails"
                                class="p-1.5 rounded-full hover:bg-slate-200/70 dark:hover:bg-slate-800 text-slate-500 hover:text-indigo-600 transition-colors cursor-pointer"
                                title="Edit group name or description">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                            </svg>
                        </button>
                    </template>

                    <!-- Close [X] button when in sub-tab -->
                    <template x-if="profileTab !== 'details'">
                        <button type="button"
                                @click="profileModal = false"
                                class="p-1.5 rounded-full hover:bg-slate-200/70 dark:hover:bg-slate-800 text-slate-500 hover:text-slate-800 dark:hover:text-slate-100 transition-colors cursor-pointer"
                                title="Close">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </template>
                </div>
            </div>

            <!-- VIEW A: Contact / Group Info Details (When profileTab === 'details') -->
            <div x-show="profileTab === 'details'" class="flex-1 min-h-0 overflow-y-auto chat-scrollbar p-5 space-y-4">

                <!-- Hero Section: Centered Big Avatar, Title, Subtitle (Image 3) -->
                <div class="flex flex-col items-center text-center">
                    <div class="relative cursor-pointer group/avatar">
                        <img :src="avatarUrl(activeConversation?.avatar_url, activeConversation?.title)"
                             :alt="activeConversation?.title"
                             x-on:error="handleAvatarError($event, activeConversation?.title)"
                             class="w-24 h-24 rounded-full object-cover ring-4 ring-slate-100 dark:ring-slate-800 shadow-md group-hover/avatar:scale-105 transition-transform">
                        <template x-if="activeConversation?.type !== 'group'">
                            <span class="absolute bottom-1 right-1 w-4 h-4 rounded-full ring-3 ring-white dark:ring-slate-900"
                                  :class="activeConversation?.is_online ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                        </template>
                    </div>

                    <!-- Title & Nickname -->
                    <div class="flex items-center justify-center gap-1.5 mt-3">
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100 truncate max-w-xs"
                            x-text="activeConversation?.title"></h2>
                        <button type="button"
                                @click="openNicknameModal(activeConversation)"
                                class="p-1 rounded-lg text-slate-400 hover:text-violet-600 hover:bg-violet-50 dark:hover:bg-violet-950/50 transition-colors cursor-pointer"
                                title="Edit Nickname">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                            </svg>
                        </button>
                    </div>
                    <template x-if="activeConversation?.nickname && activeConversation?.real_name">
                        <p class="text-[11px] text-slate-400 mt-0.5" x-text="'Real name: ' + activeConversation.real_name"></p>
                    </template>

                    <!-- Subtitle (Image 3: 'Group • 1 member' or role) -->
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        <template x-if="activeConversation?.type === 'group'">
                            <span x-text="'Group · ' + (activeConversation?.members?.length || 0) + ' member' + ((activeConversation?.members?.length === 1) ? '' : 's')"></span>
                        </template>
                        <template x-if="activeConversation?.type !== 'group'">
                            <span x-text="activeConversation?.team_role || (activeConversation?.is_online ? 'Online' : 'Offline')"></span>
                        </template>
                    </p>
                </div>

                <!-- 3. Four Action Buttons (Audio, Video, Add, Search - Matches Image 3) -->
                <div class="grid grid-cols-4 gap-2 pt-1">
                    <!-- Audio Call -->
                    <button type="button"
                            @click="startVoiceCall(activeConversation?.other_user_id || activeConversation?.other_user?.id); profileModal = false;"
                            :disabled="activeConversation?.type === 'group' || activeConversation?.title === 'Saved Messages'"
                            class="flex flex-col items-center justify-center p-2.5 rounded-2xl border border-slate-200/80 dark:border-slate-800 hover:border-emerald-500 hover:bg-emerald-50/50 dark:hover:bg-emerald-950/20 text-slate-700 dark:text-slate-200 transition-all cursor-pointer group disabled:opacity-40">
                        <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 mb-1 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                        </svg>
                        <span class="text-[11px] font-semibold">Audio</span>
                    </button>

                    <!-- Video Call -->
                    <button type="button"
                            @click="startVideoCall(activeConversation?.other_user_id || activeConversation?.other_user?.id); profileModal = false;"
                            :disabled="activeConversation?.type === 'group' || activeConversation?.title === 'Saved Messages'"
                            class="flex flex-col items-center justify-center p-2.5 rounded-2xl border border-slate-200/80 dark:border-slate-800 hover:border-indigo-500 hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 text-slate-700 dark:text-slate-200 transition-all cursor-pointer group disabled:opacity-40">
                        <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400 mb-1 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                        <span class="text-[11px] font-semibold">Video</span>
                    </button>

                    <!-- Add Member (Group only) -->
                    <button type="button"
                            @click="showMembersDropdown = true; profileModal = false;"
                            :disabled="activeConversation?.type !== 'group'"
                            class="flex flex-col items-center justify-center p-2.5 rounded-2xl border border-slate-200/80 dark:border-slate-800 hover:border-sky-500 hover:bg-sky-50/50 dark:hover:bg-sky-950/20 text-slate-700 dark:text-slate-200 transition-all cursor-pointer group disabled:opacity-40">
                        <svg class="w-5 h-5 text-sky-600 dark:text-sky-400 mb-1 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                        </svg>
                        <span class="text-[11px] font-semibold">Add</span>
                    </button>

                    <!-- Search In Chat -->
                    <button type="button"
                            @click="showSearchInChat = true; profileModal = false; $nextTick(() => document.getElementById('chat-search-input')?.focus());"
                            class="flex flex-col items-center justify-center p-2.5 rounded-2xl border border-slate-200/80 dark:border-slate-800 hover:border-violet-500 hover:bg-violet-50/50 dark:hover:bg-violet-950/20 text-slate-700 dark:text-slate-200 transition-all cursor-pointer group">
                        <svg class="w-5 h-5 text-violet-600 dark:text-violet-400 mb-1 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        <span class="text-[11px] font-semibold">Search</span>
                    </button>
                </div>

                <!-- 4. Group Edit Form (if editing is toggled) -->
                <div x-show="isEditingGroupDetails" class="p-3 rounded-2xl bg-indigo-50/60 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-800/60 space-y-2">
                    <input type="text"
                           x-model="groupEditForm.title"
                           placeholder="Group Title"
                           class="w-full px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-900 dark:text-slate-100">
                    <textarea x-model="groupEditForm.description"
                              placeholder="Group description..."
                              rows="2"
                              class="w-full px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-slate-100 resize-none"></textarea>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="isEditingGroupDetails = false" class="px-3 py-1 text-xs text-slate-500">Cancel</button>
                        <button type="button" @click="saveGroupProfile()" :disabled="isSavingGroupProfile" class="px-3 py-1 text-xs font-semibold rounded-lg bg-indigo-600 text-white">Save</button>
                    </div>
                </div>

                <!-- 5. Description / Bio Card (Matches Image 3) -->
                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/70 border border-slate-200/60 dark:border-slate-800">
                    <div class="flex items-center justify-between text-xs text-slate-400 mb-1">
                        <span x-text="activeConversation?.type === 'group' ? 'Group Description' : 'Status / Bio'"></span>
                    </div>
                    <p class="text-xs text-slate-800 dark:text-slate-200 leading-relaxed"
                       x-text="activeConversation?.description || (activeConversation?.type === 'group' ? 'No group description set.' : 'Hey there! I am using DGT Chat.')"></p>
                </div>

                <!-- 6. Media, links and docs Card (Matches WhatsApp 'Media, links and docs  40 >') -->
                <div class="rounded-2xl bg-slate-50 dark:bg-slate-800/70 border border-slate-200/60 dark:border-slate-800 overflow-hidden divide-y divide-slate-100 dark:divide-slate-800">
                    <button type="button"
                            @click="openMediaDocsTab('media')"
                            class="w-full p-3.5 flex items-center justify-between hover:bg-slate-100/70 dark:hover:bg-slate-800 transition-colors text-left cursor-pointer group">
                        <div class="flex items-center gap-2.5">
                            <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                            <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">Media, links and docs</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-xs text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-200">
                            <span x-text="totalSharedMediaCount || 0"></span>
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </div>
                    </button>

                    <!-- WhatsApp-style preview strip of recent 4 thumbnails (if any) -->
                    <template x-if="sharedMedia && sharedMedia.length > 0">
                        <div class="p-2.5 bg-white/50 dark:bg-slate-900/40">
                            <div class="grid grid-cols-4 gap-1.5">
                                <template x-for="item in sharedMedia.slice(0, 4)" :key="item.id">
                                    <div class="aspect-square rounded-xl overflow-hidden bg-slate-100 dark:bg-slate-800 cursor-pointer hover:opacity-90 relative"
                                         @click.stop="openMediaViewer(item, item.message_id)">
                                        <template x-if="isVideoAttachment(item)">
                                            <div class="w-full h-full relative bg-slate-900 flex items-center justify-center">
                                                <video :src="item.file_url || item.file_path || ('/chat/attachments/' + item.id + '/view')" class="w-full h-full object-cover"></video>
                                                <span class="absolute inset-0 bg-black/30 flex items-center justify-center text-white">
                                                    <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                                </span>
                                            </div>
                                        </template>
                                        <template x-if="isImageAttachment(item)">
                                            <img :src="item.file_url || item.file_path || ('/chat/attachments/' + item.id + '/view')" class="w-full h-full object-cover">
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- Mute Notifications Row (Image 3) -->
                    <div class="p-3.5 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <svg class="w-5 h-5 text-slate-500 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                            </svg>
                            <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">Mute notifications</span>
                        </div>
                        <button type="button"
                                @click="toggleMute(activeConversation)"
                                class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200"
                                :class="activeConversation?.is_muted ? 'bg-indigo-600' : 'bg-slate-300 dark:bg-slate-700'">
                            <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200"
                                  :class="activeConversation?.is_muted ? 'translate-x-4' : 'translate-x-0'"></span>
                        </button>
                    </div>

                    <!-- Starred Messages Row -->
                    <div class="p-3.5 flex items-center justify-between text-xs cursor-pointer hover:bg-slate-100/70 dark:hover:bg-slate-800 transition-colors">
                        <div class="flex items-center gap-2.5">
                            <svg class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                            </svg>
                            <span class="font-semibold text-slate-800 dark:text-slate-200">Starred messages</span>
                        </div>
                        <span class="text-slate-400">None &gt;</span>
                    </div>

                    <!-- Custom Nickname Row -->
                    <div class="p-3.5 flex items-center justify-between text-xs cursor-pointer hover:bg-slate-100/70 dark:hover:bg-slate-800 transition-colors"
                         @click="openNicknameModal(activeConversation)">
                        <div class="flex items-center gap-2.5">
                            <svg class="w-5 h-5 text-violet-500" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                            </svg>
                            <span class="font-semibold text-slate-800 dark:text-slate-200">Custom Nickname</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-slate-400">
                            <span class="text-xs max-w-[120px] truncate" x-text="activeConversation?.nickname ? activeConversation.nickname : 'None'"></span>
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </div>
                    </div>

                    <!-- Wallpaper Customization -->
                    <div class="p-3.5 flex items-center justify-between text-xs cursor-pointer hover:bg-slate-100/70 dark:hover:bg-slate-800 transition-colors"
                         @click="settingsModal = true; profileModal = false;">
                        <div class="flex items-center gap-2.5">
                            <svg class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                            <span class="font-semibold text-slate-800 dark:text-slate-200">Chat Wallpaper</span>
                        </div>
                        <span class="text-slate-400">Change &gt;</span>
                    </div>
                </div>

                <!-- 7. Group Members List (Group Chat only) -->
                <template x-if="activeConversation?.type === 'group'">
                    <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/70 border border-slate-200/60 dark:border-slate-800 space-y-2.5">
                        <div class="flex items-center justify-between text-xs text-slate-400 font-bold uppercase tracking-wider">
                            <span x-text="(activeConversation?.members?.length || 0) + ' members'"></span>
                            <button type="button"
                                    @click="showMembersDropdown = true; profileModal = false;"
                                    class="text-emerald-600 dark:text-emerald-400 font-semibold cursor-pointer">
                                + Add Member
                            </button>
                        </div>

                        <div class="space-y-2 max-h-48 overflow-y-auto chat-scrollbar">
                            <template x-for="m in (activeConversation?.members || [])" :key="m.id">
                                <div class="flex items-center justify-between gap-2 p-1.5 rounded-xl hover:bg-white dark:hover:bg-slate-800 transition-colors">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <img :src="avatarUrl(m.avatar_url, m.name)"
                                             :alt="m.name"
                                             class="w-8 h-8 rounded-full object-cover">
                                        <div class="min-w-0">
                                            <p class="text-xs font-bold text-slate-800 dark:text-slate-100 truncate"
                                               x-text="m.name + (Number(m.user_id) === Number(currentUser.id) ? ' (You)' : '')"></p>
                                            <p class="text-[10px] text-slate-400 truncate" x-text="m.team_role || 'Team Member'"></p>
                                        </div>
                                    </div>
                                    <template x-if="m.role === 'admin'">
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-emerald-500/10 text-emerald-600 border border-emerald-500/20">Admin</span>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <!-- 8. Actions / Delete -->
                <div class="pt-2">
                    <button type="button"
                            @click="deleteConversation(activeConversation); profileModal = false;"
                            class="w-full p-3 rounded-2xl bg-rose-50 dark:bg-rose-950/30 hover:bg-rose-100 dark:hover:bg-rose-950/50 text-rose-600 dark:text-rose-400 text-xs font-bold transition-colors flex items-center justify-center gap-2 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                        <span x-text="activeConversation?.type === 'group' ? 'Leave & Delete Group' : 'Delete Chat History'"></span>
                    </button>
                </div>
            </div>

            <!-- VIEW B: Dedicated Media, Links and Docs Tabs View (When profileTab !== 'details') -->
            <div x-show="profileTab !== 'details'" class="flex-1 flex flex-col min-h-0 overflow-hidden" x-cloak>
                <!-- Segmented Tabs: Media | Docs | Links -->
                <div class="flex items-center justify-around px-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/60 shrink-0">
                    <button type="button"
                            @click="profileTab = 'media'"
                            :class="profileTab === 'media' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-medium'"
                            class="py-3 px-3 text-xs border-b-2 transition-all flex items-center gap-1.5 cursor-pointer">
                        <span>Media</span>
                        <span class="text-[10px] px-1.5 py-0.5 rounded-full"
                              :class="profileTab === 'media' ? 'bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' : 'bg-slate-200 dark:bg-slate-800 text-slate-500'"
                              x-text="sharedMedia.length"></span>
                    </button>

                    <button type="button"
                            @click="profileTab = 'docs'"
                            :class="(profileTab === 'docs' || profileTab === 'files') ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-medium'"
                            class="py-3 px-3 text-xs border-b-2 transition-all flex items-center gap-1.5 cursor-pointer">
                        <span>Docs</span>
                        <span class="text-[10px] px-1.5 py-0.5 rounded-full"
                              :class="(profileTab === 'docs' || profileTab === 'files') ? 'bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' : 'bg-slate-200 dark:bg-slate-800 text-slate-500'"
                              x-text="sharedFiles.length"></span>
                    </button>

                    <button type="button"
                            @click="profileTab = 'links'"
                            :class="profileTab === 'links' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-medium'"
                            class="py-3 px-3 text-xs border-b-2 transition-all flex items-center gap-1.5 cursor-pointer">
                        <span>Links</span>
                        <span class="text-[10px] px-1.5 py-0.5 rounded-full"
                              :class="profileTab === 'links' ? 'bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' : 'bg-slate-200 dark:bg-slate-800 text-slate-500'"
                              x-text="sharedLinks.length"></span>
                    </button>
                </div>

                <!-- Scrollable Tab Content Area -->
                <div class="flex-1 min-h-0 overflow-y-auto chat-scrollbar p-4">
                    <!-- Loading State -->
                    <div x-show="isLoadingSharedMedia && sharedMedia.length === 0 && sharedFiles.length === 0 && sharedLinks.length === 0" class="py-16 text-center text-slate-400">
                        <svg class="w-7 h-7 animate-spin mx-auto mb-2 text-indigo-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <p class="text-xs">Loading shared media...</p>
                    </div>

                    <!-- TAB 1: MEDIA (Images & Videos Grid) -->
                    <div x-show="profileTab === 'media'">
                        <template x-if="sharedMedia.length > 0">
                            <div class="grid grid-cols-3 gap-2">
                                <template x-for="m in sharedMedia" :key="m.id">
                                    <div class="aspect-square rounded-xl overflow-hidden bg-slate-100 dark:bg-slate-800 cursor-pointer hover:opacity-90 relative group shadow-2xs"
                                         @click.stop="openMediaViewer(m, m.message_id)">
                                        <!-- If video -->
                                        <template x-if="isVideoAttachment(m)">
                                            <div class="w-full h-full relative bg-slate-900 flex items-center justify-center">
                                                <video :src="m.file_url || m.file_path || m.view_url || ('/chat/attachments/' + m.id + '/view')"
                                                       class="w-full h-full object-cover"></video>
                                                <div class="absolute inset-0 bg-black/30 flex items-center justify-center group-hover:scale-110 transition-transform">
                                                    <span class="w-8 h-8 rounded-full bg-black/60 backdrop-blur-xs text-white flex items-center justify-center shadow-lg">
                                                        <svg class="w-4 h-4 fill-current ml-0.5" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                                    </span>
                                                </div>
                                            </div>
                                        </template>
                                        <!-- If image -->
                                        <template x-if="isImageAttachment(m)">
                                            <img :src="m.file_url || m.file_path || m.view_url || ('/chat/attachments/' + m.id + '/view')"
                                                 :alt="m.file_name"
                                                 x-on:error="if (!$event.target.dataset.tried) { $event.target.dataset.tried = '1'; $event.target.src = m.view_url || ('/chat/attachments/' + m.id + '/view'); }"
                                                 class="w-full h-full object-cover">
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <template x-if="sharedMedia.length === 0 && !isLoadingSharedMedia">
                            <div class="py-16 text-center text-slate-400">
                                <svg class="w-12 h-12 mx-auto mb-2 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                </svg>
                                <p class="text-xs font-semibold">No photos or videos shared yet</p>
                            </div>
                        </template>
                    </div>

                    <!-- TAB 2: DOCS -->
                    <div x-show="profileTab === 'docs' || profileTab === 'files'" class="space-y-2">
                        <template x-if="sharedFiles.length > 0">
                            <div class="space-y-2">
                                <template x-for="f in sharedFiles" :key="f.id">
                                    <div class="p-3 rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/50 hover:bg-white dark:hover:bg-slate-800 transition-colors flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate" x-text="f.file_name"></p>
                                                <p class="text-[11px] text-slate-400">
                                                    <span x-text="f.file_size || f.formatted_size || ''"></span>
                                                    <span x-show="f.created_at || f.time"> · </span>
                                                    <span x-text="f.created_at || formatDateSnippet(f.time)"></span>
                                                </p>
                                            </div>
                                        </div>
                                        <a :href="f.download_url || f.file_url || f.file_path || ('/chat/attachments/' + f.id + '/download')"
                                           :download="f.file_name"
                                           class="p-2 rounded-xl text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-slate-700 transition-colors shrink-0"
                                           title="Download document">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                            </svg>
                                        </a>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <template x-if="sharedFiles.length === 0 && !isLoadingSharedMedia">
                            <div class="py-16 text-center text-slate-400">
                                <svg class="w-12 h-12 mx-auto mb-2 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                                <p class="text-xs font-semibold">No documents shared yet</p>
                            </div>
                        </template>
                    </div>

                    <!-- TAB 3: LINKS -->
                    <div x-show="profileTab === 'links'" class="space-y-2">
                        <template x-if="sharedLinks.length > 0">
                            <div class="space-y-2">
                                <template x-for="(link, idx) in sharedLinks" :key="idx">
                                    <div class="p-3 rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/50 hover:bg-white dark:hover:bg-slate-800 transition-colors">
                                        <div class="flex items-center justify-between gap-2 mb-1">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 border border-indigo-200/50 dark:border-indigo-800"
                                                  x-text="link.domain"></span>
                                            <span class="text-[10px] text-slate-400" x-text="link.created_at || formatDateSnippet(link.time)"></span>
                                        </div>
                                        <a :href="link.url"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-semibold break-all line-clamp-2 block"
                                           x-text="link.title || link.url"></a>
                                        <template x-if="link.description">
                                            <p class="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-2 mt-1" x-text="link.description"></p>
                                        </template>
                                        <template x-if="link.sender_name">
                                            <p class="text-[10px] text-slate-400 mt-1" x-text="'Sent by ' + link.sender_name"></p>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <template x-if="sharedLinks.length === 0 && !isLoadingSharedMedia">
                            <div class="py-16 text-center text-slate-400">
                                <svg class="w-12 h-12 mx-auto mb-2 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                                </svg>
                                <p class="text-xs font-semibold">No links or websites shared</p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TELEGRAM-STYLE CONVERSATION CONTEXT MENU (Pin, Mute, Archive, Unarchive)   -->
    <!-- ========================================================================= -->
    <div x-show="conversationContextMenu.open"
         @keydown.escape.window="closeConversationMenu()"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="fixed z-50 select-none"
         :style="`top: ${conversationContextMenu.y}px; left: ${conversationContextMenu.x}px;`"
         x-cloak>

        <!-- Invisible backdrop to catch outside clicks and right clicks -->
        <div class="fixed inset-0 z-40 bg-black/5"
             @click="closeConversationMenu()"
             @contextmenu.prevent="closeConversationMenu()"></div>

        <!-- Menu Content Container (above backdrop) -->
        <div class="telegram-context-menu"
             @click.stop>

            <!-- Pin / Unpin -->
            <button type="button"
                    @click="togglePin(conversationContextMenu.conv); closeConversationMenu();"
                    class="telegram-context-menu-item">
                <svg class="w-4 h-4 text-emerald-500 fill-emerald-500" viewBox="0 0 24 24">
                    <path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/>
                </svg>
                <span x-text="conversationContextMenu.conv?.is_pinned ? 'Unpin from Top' : 'Pin to Top'"></span>
            </button>

            <!-- Mute / Unmute -->
            <button type="button"
                    @click="toggleMute(conversationContextMenu.conv); closeConversationMenu();"
                    class="telegram-context-menu-item">
                <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 9.75 19.5 12m0 0 2.25 2.25M19.5 12l2.25-2.25M19.5 12l-2.25 2.25m-10.5-6 4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" />
                </svg>
                <span x-text="conversationContextMenu.conv?.is_muted ? 'Unmute Notifications' : 'Mute Notifications'"></span>
            </button>

            <!-- Mark as Read / Unread -->
            <button type="button"
                    @click="toggleMarkUnread(conversationContextMenu.conv); closeConversationMenu();"
                    class="telegram-context-menu-item">
                <svg class="w-4 h-4 text-sky-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                </svg>
                <span x-text="(conversationContextMenu.conv?.unread_count || 0) > 0 ? 'Mark as Read' : 'Mark as Unread'"></span>
            </button>

            <!-- Archive / Unarchive -->
            <button type="button"
                    @click="toggleArchive(conversationContextMenu.conv); closeConversationMenu();"
                    class="telegram-context-menu-item">
                <svg class="w-4 h-4 text-slate-400 dark:text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                </svg>
                <span x-text="conversationContextMenu.conv?.is_archived ? 'Unarchive Chat' : 'Archive Chat'"></span>
            </button>

            <div class="h-px bg-slate-200 dark:bg-slate-800 my-1 mx-2"></div>

            <!-- Edit Nickname -->
            <button type="button"
                    @click="openNicknameModal(conversationContextMenu.conv); closeConversationMenu();"
                    class="telegram-context-menu-item">
                <svg class="w-4 h-4 text-violet-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                </svg>
                <span x-text="conversationContextMenu.conv?.nickname ? 'Edit Nickname' : 'Set Nickname'"></span>
            </button>

            <!-- View Profile / Details -->
            <button type="button"
                    @click="selectConversation(conversationContextMenu.conv); showInfoPanel = true; closeConversationMenu();"
                    class="telegram-context-menu-item">
                <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
                <span>View Profile & Info</span>
            </button>

            <!-- Delete Chat -->
            <button type="button"
                    @click="deleteConversation(conversationContextMenu.conv); closeConversationMenu();"
                    class="telegram-context-menu-item danger">
                <svg class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                </svg>
                <span>Delete Chat</span>
            </button>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- WHATSAPP-STYLE MESSAGE CONTEXT DROPDOWN (Matches WhatsApp Desktop & Web)  -->
    <!-- ========================================================================= -->
    <div x-show="contextMenu.open"
         @keydown.escape.window="closeContextMenu()"
         class="fixed inset-0 z-[9999] select-none pointer-events-auto"
         x-cloak>

        <!-- Invisible Fullscreen Backdrop to catch outside clicks/taps -->
        <div class="fixed inset-0 bg-transparent"
             @click="closeContextMenu()"
             @contextmenu.prevent="closeContextMenu()"></div>

        <!-- Positioned Dropdown Menu Card -->
        <div class="absolute flex flex-col gap-1.5 pointer-events-auto shadow-2xl"
             :style="`top: ${contextMenu.y}px; left: ${contextMenu.x}px;`"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             @click.stop>

            <!-- Quick Emoji Reactions Bar -->
            <div x-show="contextMenu.msg && !contextMenu.msg.is_unsent"
                 class="flex items-center gap-1 p-1.5 rounded-full bg-white/95 dark:bg-slate-900/95 backdrop-blur-xl border border-slate-200/90 dark:border-slate-800/90 shadow-xl self-start">
                <template x-for="emoji in ['👍', '❤️', '😂', '😮', '😢', '🙏', '🔥']" :key="emoji">
                    <button type="button"
                            @click="toggleReaction(contextMenu.msg, emoji); closeContextMenu();"
                            :class="hasReacted(contextMenu.msg, emoji) ? 'scale-115 bg-indigo-50 dark:bg-indigo-900/50 ring-2 ring-indigo-500 rounded-full' : 'hover:scale-130 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-full'"
                            class="w-8 h-8 flex items-center justify-center text-lg transition-all duration-150 active:scale-95 cursor-pointer"
                            :title="'React ' + emoji">
                        <span x-text="emoji"></span>
                    </button>
                </template>
                <button type="button"
                        @click.stop="contextMenu.showEmojiGrid = !contextMenu.showEmojiGrid"
                        class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 text-slate-600 dark:text-slate-300 text-sm font-bold transition-all cursor-pointer"
                        title="More reactions">
                    <span x-text="contextMenu.showEmojiGrid ? '✕' : '+'">+</span>
                </button>
            </div>

            <!-- Expanded Emoji Reactions Grid (When clicking '+' or 'React') -->
            <div x-show="contextMenu.msg && !contextMenu.msg.is_unsent && contextMenu.showEmojiGrid"
                 x-transition
                 class="w-60 p-2 rounded-2xl bg-white/95 dark:bg-slate-900/95 backdrop-blur-xl border border-slate-200/90 dark:border-slate-800/90 shadow-2xl grid grid-cols-6 gap-1">
                <template x-for="emoji in ['👍','❤️','😂','😮','😢','🙏','🔥','🎉','💯','👏','😍','🥰','🤣','😎','🤔','🙌','✨','💪','🥳','🤩','💔','😡','🫡','🤝']" :key="'grid-' + emoji">
                    <button type="button"
                            @click="toggleReaction(contextMenu.msg, emoji); closeContextMenu();"
                            :class="hasReacted(contextMenu.msg, emoji) ? 'bg-indigo-50 dark:bg-indigo-900/50 ring-2 ring-indigo-500 rounded-xl' : 'hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl'"
                            class="w-8 h-8 flex items-center justify-center text-lg transition-transform hover:scale-120 active:scale-95 cursor-pointer">
                        <span x-text="emoji"></span>
                    </button>
                </template>
            </div>

            <!-- WhatsApp Style Vertical Action Menu Card (Exact Match with WhatsApp UI) -->
            <div class="w-60 py-1.5 rounded-2xl bg-white/95 dark:bg-slate-900/95 backdrop-blur-xl border border-slate-200/90 dark:border-slate-800/90 shadow-2xl text-[13px] font-medium text-slate-700 dark:text-slate-200 divide-y divide-slate-100 dark:divide-slate-800/60 overflow-hidden">

                <!-- Group 1: Standard Chat Actions -->
                <div class="py-1">
                    <!-- Save Sticker to Drawer (WhatsApp Style) -->
                    <template x-if="contextMenu.msg && contextMenu.msg.type === 'sticker' && !contextMenu.msg.is_unsent">
                        <button type="button"
                                @click="saveStickerFromMessage(contextMenu.msg); closeContextMenu();"
                                class="w-full px-4 py-2 flex items-center gap-3.5 hover:bg-amber-50 dark:hover:bg-amber-950/40 text-amber-600 dark:text-amber-400 transition-colors text-left font-semibold cursor-pointer">
                            <svg class="w-4 h-4 text-amber-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                            <span>Save to Stickers ⭐</span>
                        </button>
                    </template>

                    <!-- Reply -->
                    <template x-if="contextMenu.msg && !contextMenu.msg.is_unsent">
                        <button type="button"
                                @click="setReply(contextMenu.msg); closeContextMenu();"
                                class="w-full px-4 py-2 flex items-center gap-3.5 hover:bg-slate-100 dark:hover:bg-slate-800/80 transition-colors text-left cursor-pointer">
                            <svg class="w-4 h-4 text-slate-500 dark:text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                            </svg>
                            <span>Reply</span>
                        </button>
                    </template>

                    <!-- React (Smiley face icon - Toggles Emoji Grid) -->
                    <template x-if="contextMenu.msg && !contextMenu.msg.is_unsent">
                        <button type="button"
                                @click.stop="contextMenu.showEmojiGrid = !contextMenu.showEmojiGrid"
                                class="w-full px-4 py-2 flex items-center justify-between hover:bg-slate-100 dark:hover:bg-slate-800/80 transition-colors text-left cursor-pointer">
                            <span class="flex items-center gap-3.5">
                                <svg class="w-4 h-4 text-slate-500 dark:text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 0 1-6.364 0M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm6 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Z" />
                                </svg>
                                <span>React</span>
                            </span>
                            <span class="text-xs text-slate-400">👍 ❤️ 😂 ›</span>
                        </button>
                    </template>

                    <!-- Star / Unstar -->
                    <template x-if="contextMenu.msg && !contextMenu.msg.is_unsent">
                        <button type="button"
                                @click="starMessage(contextMenu.msg); closeContextMenu();"
                                class="w-full px-4 py-2 flex items-center gap-3.5 hover:bg-slate-100 dark:hover:bg-slate-800/80 transition-colors text-left cursor-pointer">
                            <svg class="w-4 h-4 shrink-0" :class="contextMenu.msg?.is_starred ? 'text-amber-500 fill-amber-500' : 'text-slate-500 dark:text-slate-400'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                            </svg>
                            <span x-text="contextMenu.msg?.is_starred ? 'Unstar' : 'Star'"></span>
                        </button>
                    </template>

                    <!-- Pin / Unpin (WhatsApp Style) -->
                    <template x-if="contextMenu.msg && !contextMenu.msg.is_unsent">
                        <button type="button"
                                @click="togglePinMessage(contextMenu.msg); closeContextMenu();"
                                class="w-full px-4 py-2 flex items-center gap-3.5 hover:bg-slate-100 dark:hover:bg-slate-800/80 transition-colors text-left cursor-pointer">
                            <svg class="w-4 h-4 text-slate-500 dark:text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m15 11.25 1.5 1.5.75-.75-3.75-3.75-.75.75 1.5 1.5L9.75 15l-3 1.5L8.25 18 3 21l3-5.25 1.5 1.5 1.5-3 4.5-4.5Z" />
                            </svg>
                            <span x-text="(contextMenu.msg?.is_pinned || activeConversation?.pinned_message_id === contextMenu.msg?.id) ? 'Unpin' : 'Pin'"></span>
                        </button>
                    </template>

                    <!-- Forward -->
                    <template x-if="contextMenu.msg && !contextMenu.msg.is_unsent">
                        <button type="button"
                                @click="openForwardModal(contextMenu.msg); closeContextMenu();"
                                class="w-full px-4 py-2 flex items-center gap-3.5 hover:bg-slate-100 dark:hover:bg-slate-800/80 transition-colors text-left cursor-pointer">
                            <svg class="w-4 h-4 text-slate-500 dark:text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m15 15 6-6m0 0-6-6m6 6H9a6 6 0 0 0 0 12h3" />
                            </svg>
                            <span>Forward</span>
                        </button>
                    </template>

                    <!-- Copy -->
                    <template x-if="contextMenu.msg && !contextMenu.msg.is_unsent">
                        <button type="button"
                                @click="copyMessage(contextMenu.msg); closeContextMenu();"
                                class="w-full px-4 py-2 flex items-center gap-3.5 hover:bg-slate-100 dark:hover:bg-slate-800/80 transition-colors text-left cursor-pointer">
                            <svg class="w-4 h-4 text-slate-500 dark:text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                            </svg>
                            <span>Copy</span>
                        </button>
                    </template>

                    <!-- Edit (Author only, non-poll, not unsent) -->
                    <template x-if="contextMenu.msg && Number(contextMenu.msg.user_id) === Number(currentUser.id) && !contextMenu.msg.is_unsent && !contextMenu.msg.poll_data && (contextMenu.msg.body || contextMenu.msg.content)">
                        <button type="button"
                                @click="startEdit(contextMenu.msg); closeContextMenu();"
                                class="w-full px-4 py-2 flex items-center gap-3.5 hover:bg-slate-100 dark:hover:bg-slate-800/80 transition-colors text-left cursor-pointer">
                            <svg class="w-4 h-4 text-slate-500 dark:text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                            </svg>
                            <span>Edit</span>
                        </button>
                    </template>
                </div>

                <!-- Group 2: Media & Download Actions (View, Save to Downloads, Save as...) -->
                <template x-if="contextMenu.msg && !contextMenu.msg.is_unsent">
                    <div class="py-1">
                        <!-- View -->
                        <template x-if="hasViewableMedia(contextMenu.msg)">
                            <button type="button"
                                    @click.stop="openMediaViewer((contextMenu.msg.attachments && contextMenu.msg.attachments[0]) || contextMenu.msg, contextMenu.msg); closeContextMenu();"
                                    class="w-full px-4 py-2 flex items-center gap-3.5 hover:bg-slate-100 dark:hover:bg-slate-800/80 transition-colors text-left cursor-pointer">
                                <svg class="w-4 h-4 text-slate-500 dark:text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <span>View</span>
                            </button>
                        </template>

                        <!-- Save to Downloads -->
                        <button type="button"
                                @click.stop="downloadMessageMedia(contextMenu.msg, false); closeContextMenu();"
                                class="w-full px-4 py-2 flex items-center gap-3.5 hover:bg-slate-100 dark:hover:bg-slate-800/80 transition-colors text-left cursor-pointer">
                            <svg class="w-4 h-4 text-slate-500 dark:text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            <span>Save to Downloads</span>
                        </button>

                        <!-- Save as... -->
                        <button type="button"
                                @click.stop="downloadMessageMedia(contextMenu.msg, true); closeContextMenu();"
                                class="w-full px-4 py-2 flex items-center gap-3.5 hover:bg-slate-100 dark:hover:bg-slate-800/80 transition-colors text-left cursor-pointer">
                            <svg class="w-4 h-4 text-slate-500 dark:text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                            </svg>
                            <span>Save as...</span>
                        </button>
                    </div>
                </template>

                <!-- Group 3: Report -->
                <div class="py-1">
                    <button type="button"
                            @click="reportMessage(contextMenu.msg); closeContextMenu();"
                            class="w-full px-4 py-2 flex items-center gap-3.5 hover:bg-slate-100 dark:hover:bg-slate-800/80 transition-colors text-left cursor-pointer">
                        <svg class="w-4 h-4 text-slate-500 dark:text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                        <span>Report</span>
                    </button>
                </div>

                <!-- Group 4: Delete & Multi-Select Actions -->
                <div class="py-1">
                    <template x-if="canDeleteMessage(contextMenu.msg)">
                        <div>
                            <!-- Delete for Everyone (WhatsApp & Telegram Standard) -->
                            <template x-if="canDeleteForEveryone(contextMenu.msg)">
                                <button type="button"
                                        @click="deleteMessage(contextMenu.msg, 'everyone'); closeContextMenu();"
                                        class="w-full px-4 py-2 flex items-center gap-3 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors text-left cursor-pointer group">
                                    <svg class="w-4 h-4 shrink-0 transition-transform group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                    <div class="flex flex-col">
                                        <span class="font-semibold text-xs">Delete for everyone</span>
                                        <span class="text-[10px] text-rose-500/80 dark:text-rose-400/70">Remove completely from chat</span>
                                    </div>
                                </button>
                            </template>

                            <!-- Delete for Me -->
                            <button type="button"
                                    @click="deleteMessage(contextMenu.msg, 'me'); closeContextMenu();"
                                    class="w-full px-4 py-2 flex items-center gap-3 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors text-left cursor-pointer group">
                                <svg class="w-4 h-4 shrink-0 transition-transform group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                                <div class="flex flex-col">
                                    <span class="font-medium text-xs">Delete for me</span>
                                    <span class="text-[10px] text-slate-400">Remove from this device</span>
                                </div>
                            </button>
                        </div>
                    </template>

                    <!-- Select messages -->
                    <button type="button"
                            @click="startSelectMessages(contextMenu.msg); closeContextMenu();"
                            class="w-full px-4 py-2 flex items-center gap-3.5 hover:bg-slate-100 dark:hover:bg-slate-800/80 transition-colors text-left cursor-pointer">
                        <svg class="w-4 h-4 text-slate-500 dark:text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span>Select messages</span>
                    </button>
                </div>

            </div>

        </div>

    </div>

    <!-- ========================================================================= -->
    <!-- IN-APP CONFIRMATION MODAL (Replaces browser native alert/confirm)         -->
    <!-- ========================================================================= -->
    <div x-show="confirmModal.open"
         class="fixed inset-0 z-60 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
         x-transition
         x-cloak>
        <div @click.away="cancelConfirm()"
             class="w-full max-w-sm bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 text-center">
            <div class="w-12 h-12 rounded-full mx-auto mb-3 flex items-center justify-center"
                 :class="confirmModal.confirmType === 'danger' ? 'bg-rose-100 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400' : 'bg-indigo-100 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400'">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100" x-text="confirmModal.title"></h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed" x-text="confirmModal.message"></p>
            <div class="flex items-center justify-center gap-3 mt-6">
                <button type="button"
                        @click="cancelConfirm()"
                        class="px-5 py-2.5 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                    Cancel
                </button>
                <button type="button"
                        @click="executeConfirm()"
                        :class="confirmModal.confirmType === 'danger' ? 'bg-rose-600 hover:bg-rose-700 shadow-rose-500/20' : 'bg-indigo-600 hover:bg-indigo-700 shadow-indigo-500/20'"
                        class="px-5 py-2.5 rounded-xl text-xs font-semibold text-white shadow-md transition-colors"
                        x-text="confirmModal.confirmText">
                </button>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- FULLSCREEN MEDIA VIEWER LIGHTBOX (Telegram & Apple Photos Style)           -->
    <!-- ========================================================================= -->
    <div x-show="lightbox.open"
         @keydown.escape.window="closeLightbox()"
         @keydown.arrow-left.window="if (lightbox.open) prevMedia()"
         @keydown.arrow-right.window="if (lightbox.open) nextMedia()"
         class="fixed inset-0 z-[99999] flex flex-col items-center justify-between p-4 bg-black/95 backdrop-blur-xl select-none"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         x-cloak>

        <!-- Top Header Controls -->
        <div class="w-full flex items-center justify-between px-2 sm:px-6 py-3 text-white z-20">
            <!-- Media Counter & Details -->
            <div class="flex items-center gap-3 min-w-0">
                <template x-if="lightbox.items && lightbox.items.length > 1">
                    <span class="px-2.5 py-1 rounded-full bg-white/10 text-xs font-bold text-white/90 backdrop-blur-md"
                          x-text="(lightbox.currentIndex + 1) + ' / ' + lightbox.items.length"></span>
                </template>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-white truncate max-w-[200px] sm:max-w-md" x-text="lightbox.name"></p>
                    <p class="text-[11px] text-white/60 truncate"
                       x-text="'Sent by ' + (currentMediaItem()?.sender_name || 'User') + (currentMediaItem()?.time ? ' · ' + currentMediaItem().time : '')"></p>
                </div>
            </div>

            <!-- Action Buttons (WhatsApp Standard Controls) -->
            <div class="flex items-center gap-2">
                <!-- Zoom & 1:1 Actual Size Controls (for images) -->
                <template x-if="lightbox.type === 'image'">
                    <div class="flex items-center bg-white/10 rounded-xl p-0.5 backdrop-blur-md border border-white/15">
                        <button type="button" @click="zoomOut()" title="Zoom Out (-)"
                                class="p-1.5 hover:bg-white/20 rounded-lg text-white transition-colors cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15" />
                            </svg>
                        </button>
                        <!-- 1:1 Actual Size / Fit Screen Toggle -->
                        <button type="button" @click="resetZoom()" title="Reset to Fit (100%)"
                                class="px-2.5 py-1 text-xs font-bold text-white/90 hover:text-white rounded-lg transition-colors cursor-pointer"
                                :class="lightbox.scale === 1 ? 'bg-white/20 text-white' : ''">Fit</button>
                        <button type="button" @click="toggleClickZoom($event)" title="Toggle 200% Zoom"
                                class="px-2.5 py-1 text-xs font-bold text-white/90 hover:text-white rounded-lg transition-colors cursor-pointer"
                                :class="lightbox.scale > 1 ? 'bg-indigo-600 text-white shadow-xs' : ''"
                                x-text="Math.round(lightbox.scale * 100) + '%'"></button>
                        <button type="button" @click="zoomIn()" title="Zoom In (+)"
                                class="p-1.5 hover:bg-white/20 rounded-lg text-white transition-colors cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </button>
                        <button type="button" @click="resetZoom()" x-show="lightbox.scale !== 1 || lightbox.panX !== 0 || lightbox.panY !== 0"
                                class="text-xs font-bold bg-white/10 hover:bg-white/20 text-cyan-300 border border-cyan-400/30 px-2.5 py-1 rounded-lg transition ml-1 cursor-pointer"
                                aria-label="Reset zoom">Reset</button>
                    </div>
                </template>

                <!-- Download Original -->
                <a :href="lightbox.download_url || lightbox.url"
                   download
                   title="Download Original File"
                   class="p-2 text-white/80 hover:text-white rounded-xl bg-white/10 hover:bg-white/20 backdrop-blur-md transition-colors flex items-center gap-1.5 text-xs font-semibold cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span class="hidden sm:inline">Download</span>
                </a>

                <!-- Close Button -->
                <button type="button"
                        @click="closeLightbox()"
                        title="Close (Esc)"
                        class="p-2 text-white/80 hover:text-white rounded-xl bg-white/10 hover:bg-white/20 backdrop-blur-md transition-colors cursor-pointer">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Main Media Stage (Card-Style Smooth GPU Zoom & Pan) -->
        <div class="flex-1 w-full h-full flex items-center justify-center p-2 sm:p-6 min-h-0 overflow-hidden relative select-none"
             :class="lightbox.isDragging ? '!cursor-grabbing' : (lightbox.scale > 1 ? 'cursor-grab' : 'cursor-zoom-in')"
             @click.self="closeLightbox()"
             @wheel="handleWheel($event)"
             @mousedown="startDrag($event)"
             @mousemove.window="onDrag($event)"
             @mouseup.window="stopDrag($event)"
             @touchstart="handleTouchStart($event)"
             @touchmove="handleTouchMove($event)"
             @touchend="handleTouchEnd($event)"
             @touchcancel="handleTouchEnd($event)">

            <!-- Previous Button (❮) -->
            <button type="button"
                    x-show="lightbox.items && lightbox.items.length > 1 && lightbox.scale <= 1"
                    @mousedown.stop
                    @click.stop="prevMedia()"
                    title="Previous Image (Left Arrow)"
                    class="absolute left-4 top-1/2 -translate-y-1/2 z-50 w-12 h-12 rounded-full bg-slate-900/80 hover:bg-slate-900 text-white border border-white/20 shadow-2xl flex items-center justify-center transition active:scale-90 hover:scale-105 backdrop-blur-md cursor-pointer">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
            </button>

            <!-- Image View (Identical to Card Zoom: Smooth GPU Transform + Drag Pan + Pinch Zoom) -->
            <template x-if="lightbox.type === 'image'">
                <img :src="lightbox.url || lightbox.view_url"
                     :alt="lightbox.name"
                     draggable="false"
                     @click.stop="handleImageClick($event)"
                     @dblclick.stop="toggleClickZoom($event)"
                     x-on:error="if (!$event.target.dataset.tried && currentMediaItem()?.view_url) { $event.target.dataset.tried = '1'; $event.target.src = currentMediaItem().view_url; } else if (!$event.target.dataset.tried2 && currentMediaItem()?.id) { $event.target.dataset.tried2 = '1'; $event.target.src = '/chat/attachments/' + currentMediaItem().id + '/view'; }"
                     class="max-h-full max-w-full w-auto h-auto object-contain select-none shadow-2xl rounded-xl pointer-events-auto"
                     :class="lightbox.isDragging ? '' : 'transition-transform duration-200 ease-out'"
                     :style="`transform: translate(${lightbox.panX}px, ${lightbox.panY}px) scale(${lightbox.scale}); touch-action: none;`">
            </template>

            <!-- Video View -->
            <template x-if="lightbox.type === 'video'">
                <div class="max-w-[95vw] max-h-[84vh] flex items-center justify-center m-auto">
                    <video :src="lightbox.url"
                           controls
                           autoplay
                           playsinline
                           style="max-height: 82vh; max-width: 90vw;"
                           class="w-auto h-auto max-w-full max-h-full rounded-xl shadow-2xl bg-black">
                        Your browser does not support the video tag.
                    </video>
                </div>
            </template>

            <!-- Next Button (❯) -->
            <button type="button"
                    x-show="lightbox.items && lightbox.items.length > 1 && lightbox.scale <= 1"
                    @mousedown.stop
                    @click.stop="nextMedia()"
                    title="Next Image (Right Arrow)"
                    class="absolute right-4 top-1/2 -translate-y-1/2 z-50 w-12 h-12 rounded-full bg-slate-900/80 hover:bg-slate-900 text-white border border-white/20 shadow-2xl flex items-center justify-center transition active:scale-90 hover:scale-105 backdrop-blur-md cursor-pointer">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
            </button>
        </div>

        <!-- Bottom Thumbnail Strip -->
        <div x-show="lightbox.items && lightbox.items.length > 1"
             class="w-full max-w-4xl py-2 flex items-center justify-center gap-2 overflow-x-auto px-4 z-20 scrollbar-none">
            <template x-for="(item, idx) in lightbox.items" :key="item.id || idx">
                <button type="button"
                        @click="lightbox.currentIndex = idx; lightbox.url = item.url; lightbox.name = item.name; lightbox.type = item.type; lightbox.download_url = item.download_url; resetZoom();"
                        :class="lightbox.currentIndex === idx ? 'ring-2 ring-indigo-500 scale-110 opacity-100' : 'opacity-40 hover:opacity-80 scale-95'"
                        class="w-12 h-12 rounded-lg overflow-hidden shrink-0 transition-all bg-slate-800">
                    <template x-if="item.type === 'image'">
                        <img :src="item.url" class="w-full h-full object-cover">
                    </template>
                    <template x-if="item.type === 'video'">
                        <div class="w-full h-full flex items-center justify-center bg-slate-900 text-white text-[9px] font-bold">
                            ▶ VID
                        </div>
                    </template>
                </button>
            </template>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- WEBRTC REAL-TIME ONE-TO-ONE VOICE CALLING OVERLAYS                        -->
    <!-- ========================================================================= -->

    <!-- Resilient Remote Audio Element for WebRTC Voice/Video Playback -->
    <audio id="chat-remote-audio-player" autoplay playsinline style="position: absolute; width: 1px; height: 1px; opacity: 0.01; pointer-events: none;"></audio>

    <!-- 1. INCOMING REAL-TIME VOICE / VIDEO CALL OVERLAY -->
    <div x-show="callState.active && !callState.isCaller && (callState.status === 'ringing' || callState.status === 'calling')"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         style="z-index: 99990;"
         class="fixed inset-0 z-70 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-xl select-none"
         x-cloak>
        <div class="w-full max-w-sm bg-white/10 dark:bg-slate-900/60 border border-white/20 dark:border-white/10 rounded-3xl p-8 text-center shadow-2xl backdrop-blur-2xl flex flex-col items-center relative overflow-hidden">
            <!-- Background Ambient Glow -->
            <div class="absolute -top-24 -left-24 w-48 h-48 bg-emerald-500/20 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-24 -right-24 w-48 h-48 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>

            <!-- Pulsing Radar Caller Avatar -->
            <div class="relative mb-6">
                <span class="animate-ping absolute inset-0 rounded-full bg-emerald-400/40 opacity-75 duration-1000"></span>
                <span class="animate-ping absolute -inset-2 rounded-full bg-emerald-400/20 opacity-50 duration-1500 delay-300"></span>
                <img :src="getCallContactAvatar()"
                     :alt="getCallContactName()"
                     x-on:error="handleAvatarError($event, getCallContactName())"
                     class="w-24 h-24 rounded-full object-cover relative ring-4 ring-emerald-500/60 shadow-xl">
            </div>

            <!-- Caller Name & Incoming Label -->
            <h3 class="text-xl font-bold text-white tracking-tight" x-text="getCallContactName()"></h3>
            <p class="text-xs font-semibold text-emerald-400 uppercase tracking-widest mt-1 flex items-center gap-1.5 animate-pulse">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                <span x-text="callState.isVideo ? 'Incoming Video Call...' : 'Incoming Voice Call...'">Incoming Voice Call...</span>
            </p>

            <!-- Accept & Decline Action Controls -->
            <div class="flex items-center justify-center gap-6 mt-8 w-full">
                <!-- Decline Button -->
                <div class="flex flex-col items-center gap-2">
                    <button type="button"
                            @click="declineIncomingCall()"
                            class="w-14 h-14 rounded-full bg-rose-600 hover:bg-rose-700 text-white flex items-center justify-center shadow-lg shadow-rose-600/30 transition-transform active:scale-90 cursor-pointer">
                        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 9-7.5 7.5m0-7.5 7.5 7.5" />
                        </svg>
                    </button>
                    <span class="text-[11px] font-semibold text-slate-300">Decline</span>
                </div>

                <!-- Accept Voice Button -->
                <div class="flex flex-col items-center gap-2">
                    <button type="button"
                            @click="acceptIncomingCall(false)"
                            class="w-15 h-15 rounded-full bg-emerald-500 hover:bg-emerald-600 text-white flex items-center justify-center shadow-xl shadow-emerald-500/40 transition-transform active:scale-95 cursor-pointer"
                            :class="!callState.isVideo ? 'animate-bounce' : ''">
                        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                        </svg>
                    </button>
                    <span class="text-[11px] font-semibold text-emerald-300" x-text="callState.isVideo ? 'Voice Only' : 'Accept'">Accept</span>
                </div>

                <!-- Accept with Video Button -->
                <div class="flex flex-col items-center gap-2">
                    <button type="button"
                            @click="acceptIncomingCall(true)"
                            class="w-15 h-15 rounded-full bg-indigo-500 hover:bg-indigo-600 text-white flex items-center justify-center shadow-xl shadow-indigo-500/40 transition-transform active:scale-95 cursor-pointer"
                            :class="callState.isVideo ? 'animate-bounce' : ''">
                        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                    </button>
                    <span class="text-[11px] font-semibold text-indigo-300">Video</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. ACTIVE / OUTGOING VOICE & VIDEO CALL OVERLAY -->
    <div x-show="callState.active && !callState.minimized && (callState.isCaller || callState.status === 'connected')"
         @click="if (callState.audioBlocked) unblockRemoteAudio()"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         style="z-index: 99990;"
         class="fixed inset-0 z-70 flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-2xl select-none"
         x-cloak>
        <div :class="(callState.remoteVideoActive || callState.isCameraOn) ? 'max-w-3xl aspect-video sm:min-h-[480px] p-6 justify-between bg-slate-950 border-slate-800' : 'max-w-sm p-8 justify-center bg-white/10 dark:bg-slate-900/70 border-white/20 dark:border-white/10'"
             class="w-full border rounded-3xl text-center shadow-2xl backdrop-blur-2xl flex flex-col items-center relative overflow-hidden transition-all duration-300">

            <!-- Audio Unblock Banner (If browser blocked WebRTC autoplay) -->
            <div x-show="callState.audioBlocked"
                 x-transition
                 class="absolute top-3 inset-x-3 z-30 flex items-center justify-between p-3 rounded-2xl bg-amber-500 text-white shadow-xl text-xs font-semibold backdrop-blur-md">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 animate-bounce" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H9Z" />
                    </svg>
                    <span>Browser silenced call audio. Tap Unmute to listen.</span>
                </div>
                <button type="button"
                        @click="unblockRemoteAudio()"
                        class="px-3 py-1 rounded-xl bg-white text-slate-900 font-bold hover:bg-slate-100 transition-colors cursor-pointer shadow-sm">
                    Unmute 🔊
                </button>
            </div>

            <!-- Remote Video Full-Bleed Canvas (Plays remote user's live video AND unmuted audio) -->
            <video id="chat-remote-video-player"
                   autoplay
                   playsinline
                   webkit-playsinline
                   x-show="callState.remoteVideoActive"
                   class="absolute inset-0 w-full h-full object-cover bg-slate-950 z-0"></video>

            <!-- Main Local Video Player (When remote video is NOT active, but our local camera IS active) -->
            <video id="chat-main-local-video-player"
                   autoplay
                   playsinline
                   webkit-playsinline
                   muted
                   x-show="!callState.remoteVideoActive && callState.isCameraOn"
                   class="absolute inset-0 w-full h-full object-cover bg-slate-950 z-0 transform -scale-x-100"></video>

            <!-- Waiting for Remote Camera / Video Connecting Status Pill -->
            <div x-show="!callState.remoteVideoActive && callState.isCameraOn && callState.status === 'connected'"
                 class="absolute top-20 left-1/2 -translate-x-1/2 z-20 px-4 py-2 rounded-full bg-slate-900/80 border border-white/20 text-white text-xs font-medium backdrop-blur-md flex items-center gap-2 shadow-lg">
                <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                <span>Connecting video / Waiting for other party's camera...</span>
            </div>

            <!-- Dark Gradient Scrim for Video Readability -->
            <div x-show="callState.remoteVideoActive || callState.isCameraOn"
                 class="absolute inset-0 bg-gradient-to-b from-slate-950/70 via-transparent to-slate-950/85 pointer-events-none z-0"></div>

            <!-- Local Camera Picture-in-Picture (PIP) Preview (When BOTH remote video AND local camera are active) -->
            <div x-show="callState.remoteVideoActive && callState.isCameraOn"
                 class="absolute bottom-24 right-4 w-28 sm:w-36 aspect-video rounded-2xl overflow-hidden border-2 border-white/30 shadow-2xl bg-slate-900 z-20">
                <video id="chat-local-video-player"
                       muted
                       autoplay
                       playsinline
                       webkit-playsinline
                       class="w-full h-full object-cover transform -scale-x-100"></video>
            </div>

            <!-- Minimize Floating Capsule Button -->
            <button type="button"
                    @click="toggleMinimizeCall()"
                    class="absolute top-4 right-4 z-20 p-2 rounded-xl bg-white/10 hover:bg-white/20 text-white/80 hover:text-white transition-colors cursor-pointer"
                    title="Minimize Call">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9 3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5 5.25 5.25" />
                </svg>
            </button>

            <!-- Center/Top Contact Info -->
            <div class="relative z-10 flex flex-col items-center">
                <!-- Contact Avatar (hidden when remote video OR local camera stream is active to give clear view) -->
                <div x-show="!callState.remoteVideoActive && !callState.isCameraOn" class="relative mb-5">
                    <template x-if="callState.status === 'connected'">
                        <span class="animate-pulse absolute -inset-1 rounded-full bg-emerald-500/30"></span>
                    </template>
                    <img :src="getCallContactAvatar()"
                         :alt="getCallContactName()"
                         x-on:error="handleAvatarError($event, getCallContactName())"
                         class="w-24 h-24 rounded-full object-cover relative ring-4 ring-indigo-500/50 shadow-xl">
                </div>

                <!-- Contact Name -->
                <h3 class="text-xl font-bold text-white tracking-tight drop-shadow"
                    x-text="getCallContactName()"></h3>

                <!-- Call Status & Duration Counter -->
                <div class="mt-2 flex flex-col items-center">
                    <template x-if="callState.status === 'calling' || (callState.status === 'ringing' && callState.isCaller)">
                        <p class="text-xs font-semibold text-indigo-400 tracking-wider uppercase animate-pulse flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-indigo-400"></span>
                            <span x-text="callState.status === 'ringing' ? 'Ringing...' : (callState.isVideo ? 'Video Calling...' : 'Calling...')">Calling...</span>
                        </p>
                    </template>
                    <template x-if="callState.status === 'connected'">
                        <div class="flex flex-col items-center gap-1">
                            <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 text-[11px] font-semibold flex items-center gap-1.5 backdrop-blur-md">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                <span x-text="(callState.remoteVideoActive || callState.isCameraOn) ? 'HD Video Call' : 'HD Voice Call'">HD Voice Call</span>
                            </span>
                            <span class="text-2xl font-mono font-bold text-white mt-1 tracking-wider drop-shadow"
                                  x-text="formatCallDuration(callState.duration)">00:00</span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Call Controls: Mute Microphone, Camera On/Off & End Call -->
            <div class="relative z-10 flex items-center justify-center gap-6 mt-8 w-full">
                <!-- Mute / Unmute Button -->
                <div class="flex flex-col items-center gap-2">
                    <button type="button"
                            @click="toggleMuteMic()"
                            :class="callState.isMuted ? 'bg-amber-500 hover:bg-amber-600 text-white' : 'bg-white/15 hover:bg-white/25 text-white'"
                            class="w-13 h-13 rounded-full flex items-center justify-center shadow-md backdrop-blur-md transition-all active:scale-90 cursor-pointer">
                        <template x-if="!callState.isMuted">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />
                            </svg>
                        </template>
                        <template x-if="callState.isMuted">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 9.75 19.5 12m0 0 2.25 2.25M19.5 12l2.25-2.25M19.5 12l-2.25 2.25m-10.5-6 4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H9Z" />
                            </svg>
                        </template>
                    </button>
                    <span class="text-[11px] font-medium text-slate-200" x-text="callState.isMuted ? 'Unmute' : 'Mute'"></span>
                </div>

                <!-- Camera On / Off Toggle Button -->
                <div class="flex flex-col items-center gap-2">
                    <button type="button"
                            @click="toggleCamera()"
                            :class="callState.isCameraOn ? 'bg-indigo-500 hover:bg-indigo-600 text-white' : 'bg-white/15 hover:bg-white/25 text-white'"
                            class="w-13 h-13 rounded-full flex items-center justify-center shadow-md backdrop-blur-md transition-all active:scale-90 cursor-pointer"
                            :title="callState.isCameraOn ? 'Turn Camera Off' : 'Turn Camera On'">
                        <template x-if="callState.isCameraOn">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                            </svg>
                        </template>
                        <template x-if="!callState.isCameraOn">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M12 18.75H4.5a2.25 2.25 0 0 1-2.25-2.25V9m12.841 9.091L16.5 19.5m-1.409-1.409c.407-.407.659-.97.659-1.591v-9a2.25 2.25 0 0 0-2.25-2.25h-9c-.621 0-1.184.252-1.591.659m12.182 12.182L2.909 5.909M1.5 4.5l1.409 1.409" />
                            </svg>
                        </template>
                    </button>
                    <span class="text-[11px] font-medium text-slate-200" x-text="callState.isCameraOn ? 'Cam On' : 'Video'"></span>
                </div>

                <!-- End Call Button -->
                <div class="flex flex-col items-center gap-2">
                    <button type="button"
                            @click="endCurrentCall('normal')"
                            class="w-15 h-15 rounded-full bg-rose-600 hover:bg-rose-700 text-white flex items-center justify-center shadow-xl shadow-rose-600/30 transition-transform active:scale-90 cursor-pointer">
                        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9-7.5 7.5m0-7.5 7.5 7.5" />
                        </svg>
                    </button>
                    <span class="text-[11px] font-semibold text-rose-300">End</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. FLOATING MINIMIZED CALL CAPSULE (Allows browsing other chats without interrupting call) -->
    <div x-show="callState.active && callState.minimized"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         style="z-index: 99995;"
         class="fixed bottom-6 right-6 z-60 bg-slate-900/90 dark:bg-slate-900/95 border border-white/20 dark:border-white/10 rounded-2xl p-2.5 shadow-2xl backdrop-blur-xl flex items-center gap-3 select-none"
         x-cloak>
        <!-- Remote Avatar -->
        <img :src="getCallContactAvatar()"
             :alt="getCallContactName()"
             x-on:error="handleAvatarError($event, getCallContactName())"
             class="w-9 h-9 rounded-full object-cover ring-2 ring-emerald-500 shrink-0">

        <!-- Name & Timer -->
        <div class="min-w-0 pr-1 cursor-pointer" @click="toggleMinimizeCall()">
            <h4 class="text-xs font-bold text-white truncate max-w-[120px]"
                x-text="getCallContactName()"></h4>
            <p class="text-[10px] font-mono text-emerald-400 flex items-center gap-1 font-semibold">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                <span x-text="formatCallDuration(callState.duration)">00:00</span>
            </p>
        </div>

        <!-- Quick Mute Button -->
        <button type="button"
                @click="toggleMuteMic()"
                :class="callState.isMuted ? 'bg-amber-500 text-white' : 'bg-white/10 text-white/80 hover:text-white'"
                class="p-2 rounded-xl transition-colors cursor-pointer"
                :title="callState.isMuted ? 'Unmute Mic' : 'Mute Mic'">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />
            </svg>
        </button>

        <!-- Expand Button -->
        <button type="button"
                @click="toggleMinimizeCall()"
                class="p-2 rounded-xl bg-white/10 hover:bg-white/20 text-white transition-colors cursor-pointer"
                title="Expand Call View">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
            </svg>
        </button>

        <!-- End Call Button -->
        <button type="button"
                @click="endCurrentCall('normal')"
                class="p-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white transition-colors cursor-pointer"
                title="End Call">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9-7.5 7.5m0-7.5 7.5 7.5" />
            </svg>
        </button>
    </div>

    <!-- ========================================================================= -->
    <!-- TOAST FEEDBACK                                                            -->
    <!-- ========================================================================= -->
    <div x-show="toast.show"
         x-transition
         style="z-index: 99998;"
         class="fixed bottom-6 right-6 z-50 px-4 py-2.5 rounded-2xl shadow-xl text-xs font-semibold text-white flex items-center gap-2"
         :class="toast.type === 'error' ? 'bg-rose-600' : 'bg-slate-900 dark:bg-white dark:text-slate-900'"
         x-cloak>
        <span x-text="toast.message"></span>
    </div>

</div>
@endsection
