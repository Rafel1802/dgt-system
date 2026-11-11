{{-- ── Centered Liquid Glass KIUQ AI Workspace Suite ────────────────────────── --}}
<div x-data="kiuqAiWorkspace()"
     x-show="isOpen"
     x-cloak
     class="fixed inset-0 z-[9999] flex items-center justify-center p-2 sm:p-4 md:p-6"
     style="padding-top: max(0.5rem, var(--dgt-safe-top, env(safe-area-inset-top, 0px))); padding-bottom: max(0.5rem, var(--dgt-safe-bottom, env(safe-area-inset-bottom, 0px)));"
     role="dialog"
     aria-modal="true"
     aria-labelledby="kiuq-ai-title"
     @paste="handlePaste($event)">

    {{-- Dark High-Contrast Backdrop with full opacity to prevent background bleed-through --}}
    <div x-show="isOpen"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="closeModal()"
         class="fixed inset-0 bg-slate-950/92 dark:bg-black/95 backdrop-blur-xl"></div>

    {{-- Large Centered Solid Liquid Glass Workspace Window --}}
    <div x-show="isOpen"
         x-transition:enter="transition-all ease-out duration-300 transform"
         x-transition:enter-start="opacity-0 scale-95 translate-y-3"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition-all ease-in duration-200 transform"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-3"
         :class="isFullscreen ? '!w-screen !h-screen !max-w-none !rounded-none !m-0' : 'w-[96vw] sm:w-[94vw] max-w-[1600px] h-full sm:h-[90vh] rounded-3xl sm:rounded-[2rem]'"
         class="relative flex flex-col md:flex-row overflow-hidden bg-slate-50 dark:bg-[#0b101b] border border-slate-200 dark:border-slate-800 shadow-[0_25px_70px_-15px_rgba(0,0,0,0.7)] ring-1 ring-black/10 z-10 transition-all duration-300">

        {{-- ── LEFT SIDEBAR: Navigation, Studios & Persistent Chat History ───── --}}
        <aside x-show="sidebarOpen"
               x-transition:enter="transition ease-out duration-200"
               x-transition:enter-start="-translate-x-full md:translate-x-0 opacity-0 md:opacity-100"
               x-transition:enter-end="translate-x-0 opacity-100"
               class="w-full md:w-80 lg:w-84 flex-shrink-0 flex flex-col border-b md:border-b-0 md:border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-[#070b13] h-72 md:h-full select-none">
            
            {{-- Sidebar Header: New Chat Button & Search --}}
            <div class="p-3.5 border-b border-slate-200/60 dark:border-slate-800/60 space-y-2.5">
                <div class="flex items-center gap-2">
                    <button type="button"
                            @click="startNewChat(mode)"
                            class="flex-1 inline-flex items-center justify-center gap-2 px-3.5 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 via-indigo-500 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-bold text-xs shadow-md shadow-indigo-500/20 active:scale-98 transition-all duration-150">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <span>New Conversation</span>
                    </button>

                    <button type="button"
                            @click="showArchived = !showArchived; loadConversations()"
                            :title="showArchived ? 'View Active Chats' : 'View Archived Chats'"
                            :class="showArchived ? 'bg-amber-500/20 text-amber-600 dark:text-amber-400 border-amber-500/30' : 'bg-slate-200/60 dark:bg-slate-800/60 text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 border-transparent'"
                            class="p-2.5 rounded-xl border text-xs font-semibold transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                        </svg>
                    </button>
                </div>

                {{-- Search Conversations --}}
                <div class="relative">
                    <input type="text"
                           x-model="searchQuery"
                           @input.debounce.300ms="loadConversations()"
                           placeholder="Search conversations..."
                           class="w-full pl-8 pr-3 py-1.5 rounded-xl bg-white/70 dark:bg-slate-900/70 border border-slate-200/70 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                    <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                </div>
            </div>

            {{-- Dedicated AI Studios Navigation --}}
            <div class="p-3 border-b border-slate-200/60 dark:border-slate-800/60">
                <p class="px-2 pb-1.5 text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">Dedicated AI Studios</p>
                <div class="space-y-1">
                    <button type="button" @click="setMode('general')" :class="mode === 'general' ? 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 font-bold border-indigo-500/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/50 dark:hover:bg-slate-800/50 border-transparent'" class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-xl text-xs transition-colors border">
                        <span>💬</span>
                        <span class="truncate">General Chat</span>
                    </button>
                    <button type="button" @click="setMode('translate')" :class="mode === 'translate' ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 font-bold border-emerald-500/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/50 dark:hover:bg-slate-800/50 border-transparent'" class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-xl text-xs transition-colors border">
                        <span>🌐</span>
                        <span class="truncate">Language Translator</span>
                    </button>
                    <button type="button" @click="setMode('image')" :class="mode === 'image' ? 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 font-bold border-indigo-500/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/50 dark:hover:bg-slate-800/50 border-transparent'" class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-xl text-xs transition-colors border">
                        <span>🎨</span>
                        <span class="truncate">Image Studio</span>
                    </button>
                    <button type="button" @click="setMode('voice')" :class="mode === 'voice' ? 'bg-purple-500/15 text-purple-600 dark:text-purple-400 font-bold border-purple-500/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/50 dark:hover:bg-slate-800/50 border-transparent'" class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-xl text-xs transition-colors border">
                        <span>🎙️</span>
                        <span class="truncate">Voice Studio</span>
                    </button>
                    <button type="button" @click="setMode('seo')" :class="mode === 'seo' ? 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 font-bold border-indigo-500/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/50 dark:hover:bg-slate-800/50 border-transparent'" class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-xl text-xs transition-colors border">
                        <span>🔍</span>
                        <span class="truncate">SEO Optimizer</span>
                    </button>
                </div>
            </div>

            {{-- Persistent Conversation History List --}}
            <div class="flex-1 overflow-y-auto p-3 space-y-1 custom-scrollbar">
                <div class="flex items-center justify-between px-2 pb-1 text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    <span x-text="showArchived ? 'Archived Conversations' : 'Recent Conversations'"></span>
                    <span class="font-normal lowercase" x-text="`${conversations.length} saved`"></span>
                </div>

                <template x-for="conv in conversations" :key="conv.id">
                    <div class="group relative flex items-center justify-between rounded-xl px-3 py-2 text-xs transition-all cursor-pointer border"
                         :class="activeConversationId === conv.id && (mode === 'general' || mode === 'translate') ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-300 font-semibold border-indigo-500/25 shadow-sm' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-200/50 dark:hover:bg-slate-800/50 border-transparent'"
                         @click="selectConversation(conv.id)">
                        <div class="flex items-center gap-2 min-w-0 pr-6">
                            <span x-show="conv.is_pinned" class="text-amber-500 text-[10px]">📌</span>
                            <span class="truncate" x-text="conv.title"></span>
                        </div>

                        {{-- Action Icons (Pin, Rename, Archive, Delete) --}}
                        <div class="absolute right-2 flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity bg-slate-100/90 dark:bg-slate-800/90 px-1 py-0.5 rounded-lg shadow-sm">
                            <button type="button" @click.stop="togglePin(conv)" :title="conv.is_pinned ? 'Unpin' : 'Pin'" class="p-1 hover:text-amber-500 text-slate-400">
                                📌
                            </button>
                            <button type="button" @click.stop="renameConversation(conv)" title="Rename" class="p-1 hover:text-indigo-500 text-slate-400">
                                ✏️
                            </button>
                            <button type="button" @click.stop="toggleArchive(conv)" :title="conv.is_archived ? 'Unarchive' : 'Archive'" class="p-1 hover:text-blue-500 text-slate-400">
                                📁
                            </button>
                            <button type="button" @click.stop="deleteConversation(conv.id)" title="Delete" class="p-1 hover:text-rose-500 text-slate-400">
                                🗑️
                            </button>
                        </div>
                    </div>
                </template>

                <div x-show="conversations.length === 0" class="py-8 text-center text-xs text-slate-400">
                    No conversations found.
                </div>
            </div>

            {{-- Sidebar Footer: Current User Context --}}
            <div class="p-3 border-t border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between text-xs text-slate-500">
                <div class="flex items-center gap-2 truncate">
                    <div class="w-6 h-6 rounded-full bg-gradient-to-tr from-indigo-500 to-purple-600 flex items-center justify-center text-white text-[10px] font-bold">
                        {{ substr(auth()->user()->name ?? 'U', 0, 1) }}
                    </div>
                    <span class="font-medium truncate text-slate-700 dark:text-slate-300">{{ auth()->user()->name ?? 'User' }}</span>
                </div>
                <span class="px-1.5 py-0.5 rounded bg-slate-200/60 dark:bg-slate-800 text-[10px] font-semibold text-slate-600 dark:text-slate-400">DGT</span>
            </div>
        </aside>

        {{-- ── MAIN WORKSPACE PANEL ───────────────────────────────────────────── --}}
        <main class="flex-1 flex flex-col min-w-0 bg-white dark:bg-[#0b101b] h-full overflow-hidden">
            
            {{-- Top Window Navigation Bar --}}
            <header class="flex items-center justify-between px-5 py-3.5 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-[#0d1424]">
                <div class="flex items-center gap-3 min-w-0">
                    {{-- Mobile toggle sidebar button --}}
                    <button type="button"
                            @click="sidebarOpen = !sidebarOpen"
                            class="md:hidden p-1.5 rounded-lg text-slate-500 hover:bg-slate-200 dark:hover:bg-slate-800">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-7 h-7 rounded-xl bg-gradient-to-tr from-indigo-500 to-purple-600 flex items-center justify-center text-white shadow-md shadow-indigo-500/20">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2L14.4 7.2L20 9.6L15.6 13.8L16.8 19.4L12 16.6L7.2 19.4L8.4 13.8L4 9.6L9.6 7.2L12 2Z" />
                            </svg>
                        </div>
                        <h2 id="kiuq-ai-title" class="font-bold text-sm tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                            <span>KIUQ AI</span>
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                                  :class="mode === 'general' ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400' : (mode === 'translate' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold' : 'bg-purple-500/10 text-purple-600 dark:text-purple-400')"
                                  x-text="mode === 'translate' ? 'LANGUAGE TRANSLATOR' : mode.toUpperCase() + ' STUDIO'"></span>
                        </h2>
                    </div>
                </div>

                {{-- Window Controls (Fullscreen, Esc close) --}}
                <div class="flex items-center gap-2">
                    <button type="button"
                            @click="toggleFullscreen()"
                            title="Toggle Fullscreen"
                            class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        <svg x-show="!isFullscreen" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                        </svg>
                        <svg x-show="isFullscreen" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25" />
                        </svg>
                    </button>

                    <button type="button"
                            @click="closeModal()"
                            title="Close (Esc)"
                            class="p-2 rounded-xl text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </header>

            {{-- Mobile iOS Horizontal Scroll-Switch Studio Bar --}}
            <div class="md:hidden flex items-center gap-1.5 px-3 py-2 border-b border-slate-200/80 dark:border-slate-800/80 bg-slate-100/70 dark:bg-slate-900/60 overflow-x-auto no-scrollbar flex-shrink-0">
                <button type="button" @click="setMode('general')" :class="mode === 'general' ? 'bg-indigo-600 text-white font-bold shadow-xs' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200/70 dark:border-slate-700/70'" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs whitespace-nowrap transition-all shrink-0">
                    <span>💬</span><span>Chat</span>
                </button>
                <button type="button" @click="setMode('voice')" :class="mode === 'voice' ? 'bg-purple-600 text-white font-bold shadow-xs' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200/70 dark:border-slate-700/70'" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs whitespace-nowrap transition-all shrink-0">
                    <span>🎙️</span><span>Voice Studio</span>
                </button>
                <button type="button" @click="setMode('translate')" :class="mode === 'translate' ? 'bg-emerald-600 text-white font-bold shadow-xs' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200/70 dark:border-slate-700/70'" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs whitespace-nowrap transition-all shrink-0">
                    <span>🌐</span><span>Translate</span>
                </button>
                <button type="button" @click="setMode('image')" :class="mode === 'image' ? 'bg-indigo-600 text-white font-bold shadow-xs' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200/70 dark:border-slate-700/70'" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs whitespace-nowrap transition-all shrink-0">
                    <span>🎨</span><span>Image Studio</span>
                </button>
                <button type="button" @click="setMode('seo')" :class="mode === 'seo' ? 'bg-indigo-600 text-white font-bold shadow-xs' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200/70 dark:border-slate-700/70'" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs whitespace-nowrap transition-all shrink-0">
                    <span>🔍</span><span>SEO</span>
                </button>
            </div>

            {{-- ── DYNAMIC VIEW AREA ──────────────────────────────────────────── --}}

            {{-- 1. GENERAL ASSISTANT CHAT & TRANSLATOR VIEW ─────────────────── --}}
            <div x-show="mode === 'general' || mode === 'translate'" class="flex-1 flex flex-col min-h-0 overflow-hidden">
                
                {{-- Messages Feed --}}
                <div x-ref="messagesFeed" class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-5 custom-scrollbar">
                    
                    {{-- Welcome Hero if no messages in thread --}}
                    <div x-show="messages.length === 0" class="max-w-2xl mx-auto py-8 text-center space-y-4">
                        <div class="w-14 h-14 mx-auto rounded-3xl bg-gradient-to-tr from-indigo-500 via-purple-500 to-pink-500 flex items-center justify-center text-white shadow-xl shadow-indigo-500/25">
                            <svg class="w-7 h-7" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2L14.4 7.2L20 9.6L15.6 13.8L16.8 19.4L12 16.6L7.2 19.4L8.4 13.8L4 9.6L9.6 7.2L12 2Z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900 dark:text-white" x-text="mode === 'translate' ? 'Universal Language Translator (Khmer, English & More)' : 'How can KIUQ AI empower your workflow today?'"></h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto" x-text="mode === 'translate' ? 'Instant fluent translation across Khmer (ភាសាខ្មែរ), English, Chinese, French, Thai, Vietnamese, and Spanish.' : 'Ask questions, paste screenshots (Cmd+V), translate languages, draft blog posts, or execute card operations.'">
                        </p>

                        {{-- Suggested Prompt Starter Chips --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-3 text-left max-w-xl mx-auto">
                            <button type="button" @click="inputText = 'Translate into Khmer and English: \'Our engineering department delivers high-precision machinery with full warranty.\''; focusInput()" class="p-3 rounded-2xl border border-emerald-500/30 bg-emerald-500/5 hover:border-emerald-500/60 hover:bg-emerald-500/10 transition-all text-xs text-slate-700 dark:text-slate-300">
                                🌐 <span class="font-bold text-emerald-600 dark:text-emerald-400">Translate to Khmer & English</span>
                                <p class="text-[11px] text-slate-400 mt-0.5">Authentic Khmer script & natural phrasing</p>
                            </button>
                            <button type="button" @click="inputText = 'Explain quantum computing in simple terms with 3 clear bullet points.'; focusInput()" class="p-3 rounded-2xl border border-indigo-500/30 bg-indigo-500/5 hover:border-indigo-500/60 hover:bg-indigo-500/10 transition-all text-xs text-slate-700 dark:text-slate-300">
                                ⚡ <span class="font-bold text-indigo-600 dark:text-indigo-400">Instant Q&A (Fast Response)</span>
                                <p class="text-[11px] text-slate-400 mt-0.5">Ask about science, coding, history or math</p>
                            </button>
                            <button type="button" @click="inputText = 'Draft an SEO-optimized blog article about TYPHON KUVUO 4.0 mini excavator with focus keywords and meta description.'; focusInput()" class="p-3 rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white/60 dark:bg-slate-900/60 hover:border-indigo-500/40 hover:bg-indigo-500/5 transition-all text-xs text-slate-700 dark:text-slate-300">
                                📝 <span class="font-semibold">Write Blog Draft</span>
                                <p class="text-[11px] text-slate-400 mt-0.5">SEO article with metadata & headlines</p>
                            </button>
                            <button type="button" @click="inputText = 'Analyze our verified database production metrics and compile an executive report for the last 30 days with PDF download.'; focusInput()" class="p-3 rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white/60 dark:bg-slate-900/60 hover:border-indigo-500/40 hover:bg-indigo-500/5 transition-all text-xs text-slate-700 dark:text-slate-300">
                                📊 <span class="font-semibold">Executive Report</span>
                                <p class="text-[11px] text-slate-400 mt-0.5">Real DB stats & downloadable PDF</p>
                            </button>
                        </div>
                    </div>

                    {{-- Messages List --}}
                    <template x-for="msg in messages" :key="msg.id">
                        <div class="flex gap-3 max-w-4xl mx-auto" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                            {{-- Model Avatar --}}
                            <div x-show="msg.role === 'model'" class="w-8 h-8 rounded-2xl bg-gradient-to-tr from-indigo-600 to-purple-600 flex-shrink-0 flex items-center justify-center text-white shadow-md shadow-indigo-500/20 text-xs font-bold">
                                ✦
                            </div>

                            <div class="space-y-2 max-w-[85%] sm:max-w-[78%]">
                                {{-- Attachments Thumbnail Chips (if user uploaded images) --}}
                                <div x-show="msg.attachments && msg.attachments.filter(a => a.type !== 'audio' && !(a.name && (a.name.endsWith('.wav') || a.name.endsWith('.mp3')))).length > 0" class="flex flex-wrap gap-2">
                                    <template x-for="att in (msg.attachments || []).filter(a => a.type !== 'audio' && !(a.name && (a.name.endsWith('.wav') || a.name.endsWith('.mp3'))))" :key="att.name || att.url">
                                        <div class="relative group rounded-xl overflow-hidden border border-slate-300 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 max-w-[200px]">
                                            <img :src="att.url || att.base64" class="h-28 w-auto object-cover rounded-xl" />
                                            <span class="block px-2 py-0.5 text-[10px] text-slate-500 truncate" x-text="att.name"></span>
                                        </div>
                                    </template>
                                </div>

                                {{-- Main Bubble Content --}}
                                <div class="relative group/msg p-4 rounded-2xl text-xs sm:text-[13px] leading-relaxed break-words"
                                     :class="msg.role === 'user' ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-md shadow-indigo-500/20 rounded-tr-none' : 'bg-white/80 dark:bg-slate-800/80 text-slate-800 dark:text-slate-200 border border-slate-200/80 dark:border-slate-700/80 shadow-sm rounded-tl-none'">

                                    {{-- In-Bubble Active Thinking State while waiting for first tokens --}}
                                    <template x-if="msg.role === 'model' && !msg.content && msg.isStreaming">
                                        <div class="flex items-center gap-2 py-1 text-xs text-indigo-600 dark:text-indigo-400">
                                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-bounce"></span>
                                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-bounce [animation-delay:0.2s]"></span>
                                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-bounce [animation-delay:0.4s]"></span>
                                            <span class="font-medium ml-1">Analyzing workspace data & generating response...</span>
                                        </div>
                                    </template>

                                    <div x-show="msg.content" class="kiuq-markdown-content max-w-none font-sans text-xs sm:text-[13px] leading-relaxed break-words" x-html="formatMarkdown(msg.content)"></div>
                                    <span x-show="msg.isStreaming && msg.content" class="inline-block w-1.5 h-3.5 ml-1 bg-indigo-500 dark:bg-indigo-400 rounded-full animate-pulse align-middle"></span>
                                </div>

                                {{-- Audio Player Card for Voice Synthesis Attachments --}}
                                <template x-for="att in (msg.attachments || []).filter(a => a.type === 'audio' || (a.name && (a.name.endsWith('.wav') || a.name.endsWith('.mp3'))))" :key="att.url">
                                    <div class="p-3 rounded-2xl bg-gradient-to-r from-purple-500/10 via-indigo-500/10 to-blue-500/10 border border-purple-500/25 shadow-sm space-y-2">
                                        <div class="flex items-center justify-between text-xs">
                                            <div class="flex items-center gap-2">
                                                <span class="w-6 h-6 rounded-lg bg-gradient-to-tr from-purple-600 to-indigo-600 text-white flex items-center justify-center text-xs shadow-sm">🎙️</span>
                                                <span class="font-bold text-slate-800 dark:text-slate-100" x-text="att.voice ? `${att.voice} Voice (${att.style || 'Cinematic'})` : (att.name || 'AI Voice-Over')"></span>
                                                <span x-show="att.khmer" class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-500/20 text-amber-600 dark:text-amber-400">Khmer Audio</span>
                                            </div>
                                            <a :href="att.url" download class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white/80 dark:bg-slate-800/80 hover:bg-white dark:hover:bg-slate-800 text-[10px] font-semibold text-purple-600 dark:text-purple-400 border border-purple-200 dark:border-purple-800 shadow-xs transition-colors">
                                                ⬇️ Download Audio
                                            </a>
                                        </div>
                                        <audio controls class="w-full h-8 rounded-xl outline-none" :src="att.url"></audio>
                                    </div>
                                </template>

                                {{-- Quick Action Bar for AI Messages (Bottom: Copy Text, Generate Voice, Voice Studio, Use as Prompt, Word Count) --}}
                                <div x-show="msg.role === 'model' && msg.content" class="flex flex-wrap items-center gap-2 pt-0.5 pl-1 text-[11px] select-none">
                                    {{-- One-Click Copy Button --}}
                                    <button type="button"
                                            @click="copyMessage(msg, $event)"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-white/70 dark:bg-slate-800/70 hover:bg-white dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 font-semibold transition-all border border-slate-200/80 dark:border-slate-700/80 active:scale-95 shadow-xs"
                                            title="Copy prompt, caption or answer">
                                        <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                        <span>Copy Text</span>
                                    </button>

                                    {{-- One-Click Generate Voice Button --}}
                                    <button type="button"
                                            @click="generateVoiceForMessage(msg)"
                                            :disabled="msg.isGeneratingVoice"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-gradient-to-r from-purple-500/10 to-indigo-500/10 hover:from-purple-500/20 hover:to-indigo-500/20 text-purple-700 dark:text-purple-300 hover:text-purple-800 dark:hover:text-purple-200 font-semibold transition-all border border-purple-500/30 active:scale-95 shadow-xs disabled:opacity-50"
                                            title="Synthesize Google AI voice for this text">
                                        <span x-show="!msg.isGeneratingVoice">🎙️</span>
                                        <span x-show="msg.isGeneratingVoice" class="animate-spin text-xs">⏳</span>
                                        <span x-text="msg.isGeneratingVoice ? 'Synthesizing...' : 'Generate Voice'"></span>
                                    </button>

                                    {{-- Send to Voice Studio --}}
                                    <button type="button"
                                            @click="useAsVoiceScript(msg.content)"
                                            class="inline-flex items-center gap-1 px-2 py-1 rounded-xl hover:bg-purple-50/80 dark:hover:bg-purple-950/40 text-slate-500 hover:text-purple-600 dark:hover:text-purple-400 font-medium transition-colors"
                                            title="Open in Voice Studio">
                                        <span>🎛️ Voice Studio</span>
                                    </button>

                                    {{-- Use as Prompt --}}
                                    <button type="button"
                                            @click="useAsPrompt(msg.content)"
                                            class="inline-flex items-center gap-1 px-2 py-1 rounded-xl hover:bg-indigo-50/80 dark:hover:bg-indigo-950/40 text-slate-500 hover:text-indigo-600 dark:hover:text-indigo-400 font-medium transition-colors"
                                            title="Insert into composer">
                                        <span>✏️ Use as Prompt</span>
                                    </button>

                                    <span class="text-[10px] text-slate-400 dark:text-slate-500 ml-auto" x-text="`${(msg.content || '').split(/\s+/).filter(Boolean).length} words`"></span>
                                </div>

                                {{-- Interactive Action Confirmation Preview Card --}}
                                <div x-show="msg.action_status === 'pending_confirmation' || msg.action_status === 'confirmed'"
                                     class="p-4 rounded-2xl border transition-all duration-200 shadow-md backdrop-blur-md space-y-3"
                                     :class="msg.action_status === 'confirmed' ? 'border-emerald-500/40 bg-emerald-50/70 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200' : 'border-indigo-300 dark:border-indigo-800/80 bg-indigo-50/80 dark:bg-indigo-950/50 text-indigo-900 dark:text-indigo-200'">
                                    
                                    <div class="flex items-center justify-between text-xs font-bold">
                                        <div class="flex items-center gap-2">
                                            <span x-show="msg.action_status === 'pending_confirmation'" class="text-amber-500 text-sm">⚡</span>
                                            <span x-show="msg.action_status === 'confirmed'" class="text-emerald-500 text-sm">✅</span>
                                            <span x-text="msg.action_status === 'confirmed' ? 'Action Approved & Executed' : 'UI Approval Required:'"></span>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold uppercase tracking-wider bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300" x-text="msg.action_type || 'System Action'"></span>
                                        </div>
                                        <span class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-md"
                                              :class="msg.action_status === 'confirmed' ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-amber-500/20 text-amber-700 dark:text-amber-400'"
                                              x-text="msg.action_status === 'confirmed' ? 'Executed' : 'Pending Approval'"></span>
                                    </div>

                                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-normal"
                                       x-text="msg.action_status === 'confirmed' ? 'This operation was confirmed and successfully executed in the live DGT System.' : 'Review this DGT System operation before execution. Click \'Confirm & Execute\' to apply changes live.'"></p>

                                    {{-- Execution Buttons --}}
                                    <div x-show="msg.action_status === 'pending_confirmation'" class="flex items-center gap-2 pt-1">
                                        <button type="button" @click="confirmAction(msg)" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-bold text-xs shadow-md shadow-indigo-500/25 active:scale-95 transition-all">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                            <span>Confirm & Execute Live</span>
                                        </button>
                                        <button type="button" @click="cancelAction(msg)" class="px-3.5 py-2 rounded-xl bg-slate-200/80 dark:bg-slate-800 hover:bg-slate-300 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-all">
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- AI Typing Pulse Indicator (visible while waiting for initial stream tokens) --}}
                    <div x-show="isLoading && (!messages.length || !messages[messages.length - 1]?.isStreaming || !messages[messages.length - 1]?.content)" class="flex gap-3 max-w-4xl mx-auto items-center">
                        <div class="w-8 h-8 rounded-2xl bg-gradient-to-tr from-indigo-600 to-purple-600 flex items-center justify-center text-white text-xs font-bold animate-pulse">
                            ✦
                        </div>
                        <div class="px-4 py-3 rounded-2xl bg-white/80 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/80 flex items-center gap-1.5 shadow-sm">
                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-bounce"></span>
                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-bounce [animation-delay:0.2s]"></span>
                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-bounce [animation-delay:0.4s]"></span>
                            <span class="text-xs text-slate-400 ml-2 font-medium" x-text="'Thinking with ' + getSelectedModelBadge() + '...'"></span>
                        </div>
                    </div>
                </div>

                {{-- Improved Prompt Preview Card (if user clicked "Improve Prompt") --}}
                <div x-show="improvedPromptPreview" class="mx-4 sm:mx-6 mb-2 p-3.5 rounded-2xl border border-purple-300 dark:border-purple-800 bg-purple-50/80 dark:bg-purple-950/40 backdrop-blur-md shadow-md space-y-2">
                    <div class="flex items-center justify-between text-xs font-bold text-purple-700 dark:text-purple-300">
                        <span class="flex items-center gap-1.5">✨ AI Improved Prompt:</span>
                        <button type="button" @click="discardImprovedPrompt()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">✕</button>
                    </div>
                    <p class="text-xs text-slate-800 dark:text-slate-200 italic" x-text="improvedPromptPreview?.improved_prompt"></p>
                    <div class="flex items-center gap-2 pt-1">
                        <button type="button" @click="applyImprovedPrompt()" class="px-3 py-1 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs shadow-sm transition-all">
                            Use This Prompt
                        </button>
                        <button type="button" @click="discardImprovedPrompt()" class="px-3 py-1 rounded-xl bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-xs hover:bg-slate-300 dark:hover:bg-slate-700 transition-all">
                            Keep Original
                        </button>
                    </div>
                </div>

                {{-- Attachment Chips Preview Bar (before sending) --}}
                <div x-show="attachments.length > 0" class="mx-4 sm:mx-6 mb-2 flex flex-wrap gap-2 p-2 rounded-2xl bg-slate-100/80 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700">
                    <template x-for="(att, idx) in attachments" :key="idx">
                        <div class="relative group flex items-center gap-2 px-2.5 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs">
                            <img :src="att.base64" class="w-5 h-5 object-cover rounded" />
                            <span class="truncate max-w-[120px]" x-text="att.name"></span>
                            <button type="button" @click="removeAttachment(idx)" class="text-slate-400 hover:text-rose-500 ml-1">✕</button>
                        </div>
                    </template>
                </div>

                {{-- ── BOTTOM MESSAGE COMPOSER ───────────────────────────────── --}}
                <div class="p-3 sm:p-5 border-t border-slate-200/70 dark:border-slate-800/80 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md"
                     @dragover.prevent="isDragging = true"
                     @dragleave.prevent="isDragging = false"
                     @drop.prevent="handleDrop($event)">
                    
                    {{-- Quick Action Starter Pills (Ask, Generate Code, Write Blog, SEO Audit, Translate, Marketing) --}}
                    <div class="mb-2 flex items-center gap-1.5 overflow-x-auto no-scrollbar pb-0.5">
                        <button type="button" @click="applyQuickAction('ask')" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 hover:bg-indigo-50 dark:bg-slate-800/80 dark:hover:bg-indigo-950/60 text-[11px] font-semibold text-slate-700 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 border border-slate-200/80 dark:border-slate-700/80 transition-all flex-shrink-0 shadow-2xs">
                            <span>💡 Ask Anything</span>
                        </button>
                        <button type="button" @click="applyQuickAction('code')" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 hover:bg-indigo-50 dark:bg-slate-800/80 dark:hover:bg-indigo-950/60 text-[11px] font-semibold text-slate-700 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 border border-slate-200/80 dark:border-slate-700/80 transition-all flex-shrink-0 shadow-2xs">
                            <span>💻 Generate Code</span>
                        </button>
                        <button type="button" @click="applyQuickAction('blog')" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 hover:bg-indigo-50 dark:bg-slate-800/80 dark:hover:bg-indigo-950/60 text-[11px] font-semibold text-slate-700 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 border border-slate-200/80 dark:border-slate-700/80 transition-all flex-shrink-0 shadow-2xs">
                            <span>✍️ Write Blog</span>
                        </button>
                        <button type="button" @click="applyQuickAction('seo')" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 hover:bg-indigo-50 dark:bg-slate-800/80 dark:hover:bg-indigo-950/60 text-[11px] font-semibold text-slate-700 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 border border-slate-200/80 dark:border-slate-700/80 transition-all flex-shrink-0 shadow-2xs">
                            <span>🔍 SEO Optimizer</span>
                        </button>
                        <button type="button" @click="applyQuickAction('translate')" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 hover:bg-indigo-50 dark:bg-slate-800/80 dark:hover:bg-indigo-950/60 text-[11px] font-semibold text-slate-700 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 border border-slate-200/80 dark:border-slate-700/80 transition-all flex-shrink-0 shadow-2xs">
                            <span>🌐 Translate</span>
                        </button>
                        <button type="button" @click="applyQuickAction('social')" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 hover:bg-indigo-50 dark:bg-slate-800/80 dark:hover:bg-indigo-950/60 text-[11px] font-semibold text-slate-700 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 border border-slate-200/80 dark:border-slate-700/80 transition-all flex-shrink-0 shadow-2xs">
                            <span>📢 Social Caption</span>
                        </button>
                    </div>

                    <div class="relative rounded-2xl border transition-all duration-200 shadow-sm bg-white/90 dark:bg-slate-950/80"
                         :class="isDragging ? 'border-indigo-500 ring-2 ring-indigo-500/20' : 'border-slate-200/90 dark:border-slate-800 focus-within:border-indigo-500/60 focus-within:ring-2 focus-within:ring-indigo-500/20'">
                        
                        {{-- Multiline Textarea (Enter to send, Shift+Enter for newline) --}}
                        <textarea x-ref="composerInput"
                                  x-model="inputText"
                                  @keydown="handleComposerKeydown($event)"
                                  rows="2"
                                  :placeholder="getInputPlaceholder()"
                                  class="w-full px-4 pt-3.5 pb-2 text-xs sm:text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 bg-transparent resize-none focus:outline-none"></textarea>

                        {{-- Composer Toolbar --}}
                        <div class="flex items-center justify-between px-3 py-2 border-t border-slate-100 dark:border-slate-800/80">
                            
                            {{-- Left Toolbar: Attachments, Screenshot, Model Selector, Improve Prompt --}}
                            <div class="flex items-center gap-1.5 flex-wrap">
                                {{-- File / Screenshot Upload Button --}}
                                <label class="cursor-pointer p-1.5 rounded-xl text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" title="Attach Image or Screenshot">
                                    <input type="file" accept="image/*" multiple class="hidden" @change="handleFileUpload($event)" />
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                    </svg>
                                </label>

                                {{-- AI Model Selector Dropdown --}}
                                <div class="relative">
                                    <button type="button"
                                            @click="modelDropdownOpen = !modelDropdownOpen"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800/90 text-[11px] font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors border border-slate-200/60 dark:border-slate-700/60 shadow-sm">
                                        <span x-text="getSelectedModelBadge()"></span>
                                        <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>

                                    {{-- Enhanced Categorized Model Menu --}}
                                    <div x-show="modelDropdownOpen"
                                         @click.away="modelDropdownOpen = false"
                                         class="absolute left-0 bottom-full mb-2 w-80 sm:w-96 max-h-[460px] overflow-y-auto rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/95 dark:bg-slate-900/95 backdrop-blur-xl shadow-2xl p-2.5 z-50 space-y-2.5 custom-scrollbar">
                                        <div class="flex items-center justify-between px-2 pt-1 pb-1.5 border-b border-slate-100 dark:border-slate-800/80">
                                            <p class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Select AI Model</p>
                                            <span class="text-[9px] px-2 py-0.5 rounded-full bg-gradient-to-r from-indigo-500/15 to-purple-500/15 text-indigo-600 dark:text-indigo-400 font-bold">Groq LPU + Gemini Ultra</span>
                                        </div>

                                        {{-- Ultra Speed & LPU Models --}}
                                        <div class="space-y-1">
                                            <p class="px-2 text-[9px] font-black uppercase tracking-wider text-indigo-600 dark:text-indigo-400 flex items-center gap-1">
                                                <span>⚡ Ultra Speed & LPU Models</span>
                                            </p>
                                            <template x-for="m in availableModels.filter(x => x.tier === 'ultra')" :key="m.id">
                                                <button type="button"
                                                        @click="selectModel(m.id)"
                                                        class="w-full text-left p-2.5 rounded-xl text-xs transition-all border"
                                                        :class="selectedModel === m.id ? 'bg-indigo-500/10 border-indigo-500/30 text-indigo-800 dark:text-indigo-300 font-bold shadow-sm' : 'border-transparent hover:bg-slate-100 dark:hover:bg-slate-800/70 text-slate-700 dark:text-slate-300'">
                                                    <div class="flex items-center justify-between gap-1.5">
                                                        <span class="font-bold truncate text-[12px]" x-text="m.name"></span>
                                                        <span class="text-[9px] px-2 py-0.5 rounded-full font-bold flex-shrink-0 bg-indigo-500/15 text-indigo-600 dark:text-indigo-400" x-text="m.badge"></span>
                                                    </div>
                                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 font-normal mt-1 leading-snug" x-text="m.description"></p>
                                                    <div class="flex items-center gap-2 mt-1.5 text-[10px] text-indigo-600 dark:text-indigo-400 font-semibold" x-show="m.speed">
                                                        <span x-text="`⚡ ${m.speed}`"></span>
                                                    </div>
                                                </button>
                                            </template>
                                        </div>

                                        {{-- Fast & Instant Models --}}
                                        <div class="space-y-1 pt-1.5 border-t border-slate-100 dark:border-slate-800/60">
                                            <p class="px-2 text-[9px] font-black uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                                <span>🚀 Rapid & Low Latency</span>
                                            </p>
                                            <template x-for="m in availableModels.filter(x => x.tier === 'fast')" :key="m.id">
                                                <button type="button"
                                                        @click="selectModel(m.id)"
                                                        class="w-full text-left p-2.5 rounded-xl text-xs transition-all border"
                                                        :class="selectedModel === m.id ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-800 dark:text-emerald-300 font-bold shadow-sm' : 'border-transparent hover:bg-slate-100 dark:hover:bg-slate-800/70 text-slate-700 dark:text-slate-300'">
                                                    <div class="flex items-center justify-between gap-1.5">
                                                        <span class="font-bold truncate text-[12px]" x-text="m.name"></span>
                                                        <span class="text-[9px] px-2 py-0.5 rounded-full font-bold flex-shrink-0 bg-emerald-500/15 text-emerald-600 dark:text-emerald-400" x-text="m.badge"></span>
                                                    </div>
                                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 font-normal mt-1 leading-snug" x-text="m.description"></p>
                                                    <div class="flex items-center gap-2 mt-1.5 text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold" x-show="m.speed">
                                                        <span x-text="`⏱️ ${m.speed}`"></span>
                                                    </div>
                                                </button>
                                            </template>
                                        </div>

                                        {{-- High Intelligence & Reasoning Models --}}
                                        <div class="space-y-1 pt-1.5 border-t border-slate-100 dark:border-slate-800/60">
                                            <p class="px-2 text-[9px] font-black uppercase tracking-wider text-purple-600 dark:text-purple-400 flex items-center gap-1">
                                                <span>🧠 High Intelligence & Deep Reasoning</span>
                                            </p>
                                            <template x-for="m in availableModels.filter(x => x.tier === 'high')" :key="m.id">
                                                <button type="button"
                                                        @click="selectModel(m.id)"
                                                        class="w-full text-left p-2.5 rounded-xl text-xs transition-all border"
                                                        :class="selectedModel === m.id ? 'bg-purple-500/10 border-purple-500/30 text-purple-800 dark:text-purple-300 font-bold shadow-sm' : 'border-transparent hover:bg-slate-100 dark:hover:bg-slate-800/70 text-slate-700 dark:text-slate-300'">
                                                    <div class="flex items-center justify-between gap-1.5">
                                                        <span class="font-bold truncate text-[12px]" x-text="m.name"></span>
                                                        <span class="text-[9px] px-2 py-0.5 rounded-full font-bold flex-shrink-0 bg-purple-500/15 text-purple-600 dark:text-purple-400" x-text="m.badge"></span>
                                                    </div>
                                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 font-normal mt-1 leading-snug" x-text="m.description"></p>
                                                    <div class="flex items-center gap-2 mt-1.5 text-[10px] text-purple-600 dark:text-purple-400 font-semibold" x-show="m.speed">
                                                        <span x-text="`🔬 ${m.speed}`"></span>
                                                    </div>
                                                </button>
                                            </template>
                                        </div>

                                        {{-- Creative Studios --}}
                                        <div class="space-y-1 pt-1.5 border-t border-slate-100 dark:border-slate-800/60">
                                            <p class="px-2 text-[9px] font-black uppercase tracking-wider text-blue-600 dark:text-blue-400 flex items-center gap-1">
                                                <span>🎨 Creative Studios</span>
                                            </p>
                                            <template x-for="m in availableModels.filter(x => x.tier === 'studio')" :key="m.id">
                                                <button type="button"
                                                        @click="selectModel(m.id)"
                                                        class="w-full text-left p-2.5 rounded-xl text-xs transition-all border"
                                                        :class="selectedModel === m.id ? 'bg-blue-500/10 border-blue-500/30 text-blue-800 dark:text-blue-300 font-bold shadow-sm' : 'border-transparent hover:bg-slate-100 dark:hover:bg-slate-800/70 text-slate-700 dark:text-slate-300'">
                                                    <div class="flex items-center justify-between gap-1.5">
                                                        <span class="font-bold truncate text-[12px]" x-text="m.name"></span>
                                                        <span class="text-[9px] px-2 py-0.5 rounded-full font-bold flex-shrink-0 bg-blue-500/15 text-blue-600 dark:text-blue-400" x-text="m.badge"></span>
                                                    </div>
                                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 font-normal mt-1 leading-snug" x-text="m.description"></p>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                {{-- "Improve Prompt" Button --}}
                                <button type="button"
                                        @click="improvePrompt()"
                                        :disabled="!inputText.trim() || improvingPrompt"
                                        title="Enhance prompt clarity & structure with AI"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 hover:bg-purple-500/20 text-[11px] font-semibold transition-all disabled:opacity-40">
                                    <span :class="improvingPrompt ? 'animate-spin' : ''">✨</span>
                                    <span x-text="improvingPrompt ? 'Enhancing...' : 'Improve Prompt'"></span>
                                </button>
                            </div>

                            {{-- Right Toolbar: Send Button --}}
                            <div class="flex items-center gap-2">
                                <button type="button"
                                        @click="sendMessage()"
                                        :disabled="(!inputText.trim() && attachments.length === 0) || isLoading"
                                        class="inline-flex items-center justify-center p-2 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white shadow-md shadow-indigo-500/20 active:scale-95 transition-all disabled:opacity-40 disabled:pointer-events-none">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. DEDICATED BLOG & CMS STUDIO VIEW ──────────────────────────── --}}
            <div x-show="mode === 'blog'" class="flex-1 flex flex-col min-h-0 overflow-y-auto p-4 sm:p-6 custom-scrollbar space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200/70 dark:border-slate-800 pb-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>📝</span>
                            <span>DGT CMS & Blog Studio</span>
                        </h3>
                        <p class="text-xs text-slate-500">Draft, optimize, and publish high-ranking articles directly to connected websites.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="generateBlogWithAi()" :disabled="blogForm.isSubmitting" class="px-3.5 py-2 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-bold text-xs shadow-md shadow-indigo-500/20 hover:opacity-90 transition-all flex items-center gap-1.5">
                            <span>✨ Generate Article with AI</span>
                        </button>
                    </div>
                </div>

                <div x-show="blogForm.feedbackMsg" class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-xs font-semibold" x-text="blogForm.feedbackMsg"></div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    {{-- Form Details --}}
                    <div class="space-y-3 bg-white/60 dark:bg-slate-950/50 p-4 rounded-2xl border border-slate-200/70 dark:border-slate-800">
                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Target Website</label>
                            <select x-model="blogForm.website_id" class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200">
                                <template x-for="w in studioData.websites" :key="w.id">
                                    <option :value="w.id" x-text="`${w.name} (${w.url})`"></option>
                                </template>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Article Title</label>
                            <input type="text" x-model="blogForm.title" placeholder="e.g. Why Contractors Prefer the TYPHON KUVUO 4.0 Mini Excavator" class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200" />
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Focus Keyword</label>
                                <input type="text" x-model="blogForm.focus_keyword" placeholder="e.g. mini excavator" class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200" />
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Tag Keywords</label>
                                <input type="text" x-model="blogForm.tag_keywords" placeholder="machinery, construction, mini digger" class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Meta Description</label>
                            <textarea x-model="blogForm.meta_description" rows="2" placeholder="Compelling 155-character summary for Google search results..." class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200"></textarea>
                        </div>

                        <div class="flex items-center gap-2 pt-2">
                            <button type="button" @click="saveBlogDraft()" :disabled="blogForm.isSubmitting" class="flex-1 py-2.5 rounded-xl bg-slate-900 dark:bg-white text-white dark:text-slate-900 font-bold text-xs shadow hover:opacity-90 transition-all">
                                💾 Save as CMS Draft
                            </button>
                        </div>
                    </div>

                    {{-- HTML Editor & Preview --}}
                    <div class="flex flex-col bg-white/60 dark:bg-slate-950/50 p-4 rounded-2xl border border-slate-200/70 dark:border-slate-800">
                        <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Article Body (HTML / Markdown)</label>
                        <textarea x-model="blogForm.content_html" rows="14" placeholder="Article body content will appear here or can be generated by AI..." class="flex-1 w-full px-3 py-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs font-mono text-slate-800 dark:text-slate-200 resize-none"></textarea>
                    </div>
                </div>
            </div>

            {{-- 3. DEDICATED AI IMAGE STUDIO VIEW ────────────────────────────── --}}
            <div x-show="mode === 'image'" class="flex-1 flex flex-col min-h-0 overflow-y-auto p-4 sm:p-6 custom-scrollbar space-y-4">
                <div class="border-b border-slate-200/70 dark:border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>🎨</span>
                        <span>AI Image Studio (Google Imagen 3)</span>
                    </h3>
                    <p class="text-xs text-slate-500">Generate on-brand marketing graphics, promotional banners, and machinery visuals.</p>
                </div>

                {{-- Image Generation Form --}}
                <div class="bg-slate-50 dark:bg-[#121929] p-4 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Image Prompt</label>
                        <textarea x-model="imageForm.prompt" rows="2" placeholder="e.g. Modern yellow compact mini excavator working on American construction site, golden hour lighting..." class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200"></textarea>
                    </div>

                    <div class="flex items-center gap-4 flex-wrap">
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Aspect Ratio</label>
                            <div class="flex items-center gap-1.5">
                                <button type="button" @click="imageForm.aspect_ratio = '1:1'" :class="imageForm.aspect_ratio === '1:1' ? 'bg-indigo-600 text-white font-bold' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300'" class="px-2.5 py-1 rounded-lg text-xs">1:1 Square</button>
                                <button type="button" @click="imageForm.aspect_ratio = '16:9'" :class="imageForm.aspect_ratio === '16:9' ? 'bg-indigo-600 text-white font-bold' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300'" class="px-2.5 py-1 rounded-lg text-xs">16:9 Banner</button>
                                <button type="button" @click="imageForm.aspect_ratio = '4:3'" :class="imageForm.aspect_ratio === '4:3' ? 'bg-indigo-600 text-white font-bold' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300'" class="px-2.5 py-1 rounded-lg text-xs">4:3 Standard</button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Style Preset</label>
                            <select x-model="imageForm.style_preset" class="px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs text-slate-700 dark:text-slate-300">
                                <option value="photorealistic">Photorealistic Studio</option>
                                <option value="3d_render">3D Isometric Render</option>
                                <option value="banner">Commercial Banner</option>
                            </select>
                        </div>

                        <div class="ml-auto pt-4">
                            <button type="button" @click="generateImageStudio()" :disabled="imageForm.isGenerating" class="px-4 py-2 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold text-xs shadow-md shadow-purple-500/20 active:scale-95 transition-all">
                                <span x-text="imageForm.isGenerating ? 'Generating Image...' : '✨ Generate Image'"></span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Latest Generated Image --}}
                <div x-show="imageForm.latestImage" class="p-4 rounded-2xl bg-slate-50 dark:bg-[#121929] border border-slate-200 dark:border-slate-800 space-y-3">
                    <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200">Latest Generation</h4>
                    <div class="flex flex-col sm:flex-row gap-4 items-center">
                        <img :src="imageForm.latestImage?.image_url" class="max-h-72 rounded-2xl shadow-lg border border-slate-200 dark:border-slate-700 object-contain" />
                        <div class="space-y-2 flex-1">
                            <p class="text-xs text-slate-600 dark:text-slate-300 italic" x-text="imageForm.latestImage?.prompt"></p>
                            <div class="flex items-center gap-2 pt-2">
                                <a :href="imageForm.latestImage?.image_url" download class="px-3 py-1.5 rounded-xl bg-slate-900 dark:bg-white text-white dark:text-slate-900 font-bold text-xs">
                                    ⬇️ Download Image
                                </a>
                                <button type="button" @click="blogForm.featured_image_url = imageForm.latestImage?.image_url; alert('Attached as Blog Featured Image!')" class="px-3 py-1.5 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 font-bold text-xs hover:bg-indigo-500/20">
                                    Attach to Blog Draft
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Gallery of Generated Images --}}
                <div x-show="imageForm.gallery && imageForm.gallery.length > 1" class="p-4 rounded-2xl bg-slate-50 dark:bg-[#121929] border border-slate-200 dark:border-slate-800 space-y-3">
                    <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200">Generated Images Gallery</h4>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                        <template x-for="(img, idx) in imageForm.gallery" :key="idx">
                            <div class="relative group rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 bg-black/10 aspect-square cursor-pointer"
                                 @click="imageForm.latestImage = img">
                                <img :src="img.image_url" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200">
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-1.5 p-1 text-white">
                                    <a :href="img.image_url" download class="p-1 rounded-md bg-white/20 hover:bg-white/40 text-[10px] font-bold">⬇️</a>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- 4. DEDICATED GOOGLE AI STUDIO & KHMER VOICE ENGINE ─────────── --}}
            <div x-show="mode === 'voice'" class="flex-1 flex flex-col min-h-0 overflow-y-auto p-4 sm:p-6 custom-scrollbar space-y-5">
                {{-- Header & Subtitle --}}
                <div class="border-b border-slate-200/70 dark:border-slate-800 pb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span class="w-7 h-7 rounded-xl bg-purple-600/15 text-purple-600 dark:text-purple-400 flex items-center justify-center text-sm shadow-xs">🎙️</span>
                            <span>Google AI Studio Voice Suite & Khmer Engine</span>
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Synthesize broadcast-quality audio powered by Gemini 3.8 Flash TTS, Google Khmer synthesis, and Google multilingual speech.</p>
                    </div>
                    <span class="self-start sm:self-auto px-2.5 py-1 rounded-full bg-purple-500/10 text-purple-600 dark:text-purple-300 font-bold text-[10px] border border-purple-500/20">
                        ⚡ Gemini 3.8 TTS + Khmer AI
                    </span>
                </div>

                {{-- Quick Script Template Starters --}}
                <div class="space-y-1.5">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">Quick Script Starters</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="loadVoiceScriptTemplate('khmer_cinematic')" class="px-3 py-1.5 rounded-xl bg-purple-50 hover:bg-purple-100 dark:bg-purple-950/40 dark:hover:bg-purple-900/60 border border-purple-200 dark:border-purple-800/80 text-purple-700 dark:text-purple-300 text-xs font-semibold transition-all shadow-2xs active:scale-95 flex items-center gap-1.5">
                            <span>🇰🇭</span>
                            <span>Khmer Cinematic (Dara Deep)</span>
                        </button>
                        <button type="button" @click="loadVoiceScriptTemplate('typhon_promo')" class="px-3 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/40 dark:hover:bg-indigo-900/60 border border-indigo-200 dark:border-indigo-800/80 text-indigo-700 dark:text-indigo-300 text-xs font-semibold transition-all shadow-2xs active:scale-95 flex items-center gap-1.5">
                            <span>🚜</span>
                            <span>TYPHON Promo (Deep Charon)</span>
                        </button>
                        <button type="button" @click="loadVoiceScriptTemplate('commercial_koda')" class="px-3 py-1.5 rounded-xl bg-blue-50 hover:bg-blue-100 dark:bg-blue-950/40 dark:hover:bg-blue-900/60 border border-blue-200 dark:border-blue-800/80 text-blue-700 dark:text-blue-300 text-xs font-semibold transition-all shadow-2xs active:scale-95 flex items-center gap-1.5">
                            <span>💼</span>
                            <span>Commercial Promo (Koda)</span>
                        </button>
                        <button type="button" @click="loadVoiceScriptTemplate('whisper_story')" class="px-3 py-1.5 rounded-xl bg-teal-50 hover:bg-teal-100 dark:bg-teal-950/40 dark:hover:bg-teal-900/60 border border-teal-200 dark:border-teal-800/80 text-teal-700 dark:text-teal-300 text-xs font-semibold transition-all shadow-2xs active:scale-95 flex items-center gap-1.5">
                            <span>🤫</span>
                            <span>Whisper Story (Tavi)</span>
                        </button>
                        <button type="button" @click="loadVoiceScriptTemplate('khmer_natural')" class="px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:hover:bg-emerald-900/60 border border-emerald-200 dark:border-emerald-800/80 text-emerald-700 dark:text-emerald-300 text-xs font-semibold transition-all shadow-2xs active:scale-95 flex items-center gap-1.5">
                            <span>🌸</span>
                            <span>Khmer Sweet (Bopha)</span>
                        </button>
                    </div>
                </div>

                {{-- Spoken Script Input Card --}}
                <div class="bg-white/70 dark:bg-slate-900/70 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-2.5">
                    <div class="flex items-center justify-between text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <span class="flex items-center gap-1.5">
                            <span>Spoken Narration Script</span>
                            <span class="text-[10px] font-normal lowercase text-purple-600 dark:text-purple-400 font-mono">(speaks verbatim only)</span>
                        </span>
                        <div class="flex items-center gap-3 font-normal normal-case">
                            <span class="text-[10px] text-slate-400" x-text="`${(voiceForm.script || '').length} characters · ${(voiceForm.script || '').split(/\s+/).filter(Boolean).length} words`"></span>
                            <button type="button" x-show="voiceForm.script" @click="voiceForm.script = ''" class="text-rose-500 hover:text-rose-600 text-xs font-semibold">Clear</button>
                        </div>
                    </div>

                    {{-- Google AI Studio Expression Bursts Toolbar --}}
                    <div class="flex items-center gap-1.5 flex-wrap pb-1 border-b border-slate-100 dark:border-slate-800/60">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mr-1 flex items-center gap-1">
                            <span>&lt; &gt; Expressions:</span>
                        </span>
                        <button type="button" @click="insertVocalExpression('whisper')" class="px-2 py-0.5 rounded-lg bg-slate-100 hover:bg-purple-100 dark:bg-slate-800 dark:hover:bg-purple-900/50 text-[10px] font-mono text-purple-700 dark:text-purple-300 border border-slate-200 dark:border-slate-700 transition-colors" title="Insert whisper tag">+ &lt;whisper&gt;</button>
                        <button type="button" @click="insertVocalExpression('short pause')" class="px-2 py-0.5 rounded-lg bg-slate-100 hover:bg-purple-100 dark:bg-slate-800 dark:hover:bg-purple-900/50 text-[10px] font-mono text-purple-700 dark:text-purple-300 border border-slate-200 dark:border-slate-700 transition-colors" title="Insert short pause tag">+ &lt;short pause&gt;</button>
                        <button type="button" @click="insertVocalExpression('gasp')" class="px-2 py-0.5 rounded-lg bg-slate-100 hover:bg-purple-100 dark:bg-slate-800 dark:hover:bg-purple-900/50 text-[10px] font-mono text-purple-700 dark:text-purple-300 border border-slate-200 dark:border-slate-700 transition-colors" title="Insert gasp tag">+ &lt;gasp&gt;</button>
                        <button type="button" @click="insertVocalExpression('sigh')" class="px-2 py-0.5 rounded-lg bg-slate-100 hover:bg-purple-100 dark:bg-slate-800 dark:hover:bg-purple-900/50 text-[10px] font-mono text-purple-700 dark:text-purple-300 border border-slate-200 dark:border-slate-700 transition-colors" title="Insert sigh tag">+ &lt;sigh&gt;</button>
                        <button type="button" @click="insertVocalExpression('laugh')" class="px-2 py-0.5 rounded-lg bg-slate-100 hover:bg-purple-100 dark:bg-slate-800 dark:hover:bg-purple-900/50 text-[10px] font-mono text-purple-700 dark:text-purple-300 border border-slate-200 dark:border-slate-700 transition-colors" title="Insert laugh tag">+ &lt;laugh&gt;</button>
                        <button type="button" @click="insertVocalExpression('breath')" class="px-2 py-0.5 rounded-lg bg-slate-100 hover:bg-purple-100 dark:bg-slate-800 dark:hover:bg-purple-900/50 text-[10px] font-mono text-purple-700 dark:text-purple-300 border border-slate-200 dark:border-slate-700 transition-colors" title="Insert breath tag">+ &lt;breath&gt;</button>
                    </div>

                    <textarea id="kiuq-voice-script-input"
                              x-model="voiceForm.script"
                              rows="3"
                              placeholder="Enter spoken narration script here in Khmer or English... (The voice engine speaks strictly this text verbatim with 0 introductory words)"
                              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 transition-all"></textarea>
                </div>

                {{-- Google AI Studio Voice Character Selection Grid --}}
                <div class="space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <label class="text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            Select Voice Character (Google AI Studio &amp; Khmer Voices)
                        </label>
                        <span class="text-[10px] text-purple-600 dark:text-purple-400 font-semibold">Click card to select · Press ▶ to listen sample</span>
                    </div>

                    {{-- Category Filter Pills & Search Bar --}}
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pb-0.5">
                            <button type="button"
                                    @click="voiceForm.categoryFilter = 'all'"
                                    :class="voiceForm.categoryFilter === 'all' ? 'bg-purple-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300'"
                                    class="px-3 py-1 rounded-full text-[11px] font-bold transition-all flex-shrink-0">
                                All (17)
                            </button>
                            <button type="button"
                                    @click="voiceForm.categoryFilter = 'commercial'"
                                    :class="voiceForm.categoryFilter === 'commercial' ? 'bg-purple-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300'"
                                    class="px-3 py-1 rounded-full text-[11px] font-bold transition-all flex-shrink-0 flex items-center gap-1">
                                <span>💼</span> Commercial (3)
                            </button>
                            <button type="button"
                                    @click="voiceForm.categoryFilter = 'tutor'"
                                    :class="voiceForm.categoryFilter === 'tutor' ? 'bg-purple-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300'"
                                    class="px-3 py-1 rounded-full text-[11px] font-bold transition-all flex-shrink-0 flex items-center gap-1">
                                <span>📚</span> Tutor (3)
                            </button>
                            <button type="button"
                                    @click="voiceForm.categoryFilter = 'instructional'"
                                    :class="voiceForm.categoryFilter === 'instructional' ? 'bg-purple-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300'"
                                    class="px-3 py-1 rounded-full text-[11px] font-bold transition-all flex-shrink-0 flex items-center gap-1">
                                <span>💡</span> Instructional (4)
                            </button>
                            <button type="button"
                                    @click="voiceForm.categoryFilter = 'cinematic'"
                                    :class="voiceForm.categoryFilter === 'cinematic' ? 'bg-purple-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300'"
                                    class="px-3 py-1 rounded-full text-[11px] font-bold transition-all flex-shrink-0 flex items-center gap-1">
                                <span>🎬</span> Cinematic &amp; Power (5)
                            </button>
                            <button type="button"
                                    @click="voiceForm.categoryFilter = 'khmer'"
                                    :class="voiceForm.categoryFilter === 'khmer' ? 'bg-purple-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300'"
                                    class="px-3 py-1 rounded-full text-[11px] font-bold transition-all flex-shrink-0 flex items-center gap-1">
                                <span>🇰🇭</span> Khmer Native (2)
                            </button>
                        </div>

                        {{-- Voice Search Box --}}
                        <div class="relative w-full sm:w-48">
                            <input type="text"
                                   x-model="voiceForm.searchVoice"
                                   placeholder="Search voices..."
                                   class="w-full pl-7 pr-3 py-1 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-purple-500" />
                            <span class="absolute left-2.5 top-1.5 text-xs text-slate-400">🔍</span>
                        </div>
                    </div>

                    {{-- Voice Cards Grid --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-2.5">
                        <template x-for="v in getFilteredGoogleVoices()" :key="v.id">
                            <div class="relative group p-3 rounded-2xl border transition-all cursor-pointer select-none"
                                 :class="voiceForm.voice === v.id ? 'border-purple-500 bg-purple-500/10 shadow-md ring-2 ring-purple-500/20' : 'border-slate-200 dark:border-slate-800 hover:border-purple-300 dark:hover:border-purple-800/60 bg-white/60 dark:bg-slate-900/60'"
                                 @click="voiceForm.voice = v.id">
                                <div class="flex items-start justify-between gap-1.5">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold text-xs text-slate-900 dark:text-white truncate" x-text="v.name"></span>
                                            <span class="text-[10px] text-slate-400 font-mono" x-text="v.gender === 'female' ? '♀' : '♂'"></span>
                                            <span x-show="voiceForm.voice === v.id" class="w-2 h-2 rounded-full bg-purple-500 animate-pulse"></span>
                                        </div>
                                        <span class="inline-block mt-0.5 px-2 py-0.5 rounded-full text-[9px] font-bold"
                                              :class="v.id.includes('Khmer') ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400' : 'bg-purple-500/15 text-purple-600 dark:text-purple-400'"
                                              x-text="v.badge"></span>
                                    </div>

                                    {{-- Instant Audio Sample Button --}}
                                    <button type="button"
                                            @click.stop="playVoiceSample(v)"
                                            :title="voiceForm.playingSample === v.id ? 'Stop Sample' : 'Listen Sample'"
                                            class="p-1.5 rounded-xl border transition-all active:scale-90 text-[10px] font-bold flex-shrink-0"
                                            :class="voiceForm.playingSample === v.id ? 'bg-purple-600 text-white border-purple-600 animate-pulse' : 'bg-white dark:bg-slate-800 text-purple-600 dark:text-purple-400 border-purple-200 dark:border-purple-800/80 hover:bg-purple-50 dark:hover:bg-purple-950/50'">
                                        <span x-show="voiceForm.playingSample !== v.id">▶ Sample</span>
                                        <span x-show="voiceForm.playingSample === v.id">■ Stop</span>
                                    </button>
                                </div>

                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-2 line-clamp-2 leading-relaxed" x-text="v.description"></p>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Voice Style & Custom Tone Prompt Section (Google AI Studio Experience) --}}
                <div class="p-4 rounded-2xl bg-white/70 dark:bg-slate-900/70 border border-slate-200/80 dark:border-slate-800 space-y-3.5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Voice Style &amp; Custom Tone Prompt (Google AI Studio)
                        </label>
                        <span class="text-[10px] text-purple-600 dark:text-purple-400">Describe style freely or click presets below</span>
                    </div>

                    {{-- Custom Voice Tone Input Box (as seen in Google AI Studio) --}}
                    <div class="relative">
                        <input type="text"
                               x-model="voiceForm.custom_tone"
                               placeholder="Describe the voice style. e.g. Tone, mood, pace... (e.g. Warm and neutral delivery with micro-pauses, or commanding trailer narration)"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 transition-all" />
                        <button type="button"
                                x-show="voiceForm.custom_tone"
                                @click="voiceForm.custom_tone = ''"
                                class="absolute right-3 top-2.5 text-xs text-slate-400 hover:text-slate-600">✕</button>
                    </div>

                    {{-- Quick Style Presets Pills (Whisper, Friendly, Narration, Promote, Cinematic, Professional, etc.) --}}
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <template x-for="p in voiceTonePresets" :key="p.id">
                            <button type="button"
                                    @click="applyVoiceTonePreset(p)"
                                    :class="voiceForm.style === p.id ? 'bg-purple-600 text-white shadow-xs font-bold' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-medium'"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] transition-all active:scale-95 border border-slate-200/60 dark:border-slate-700/60"
                                    :title="p.desc">
                                <span x-text="p.icon"></span>
                                <span x-text="p.label"></span>
                            </button>
                        </template>
                    </div>

                    {{-- Acoustic Sliders: Pace / Speed & Pitch --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-100 dark:border-slate-800/80">
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Pace / Speed</label>
                                <span class="text-xs font-mono font-bold text-purple-600 dark:text-purple-400" x-text="`${voiceForm.speed}x`"></span>
                            </div>
                            <input type="range" min="0.5" max="1.75" step="0.05" x-model.number="voiceForm.speed" class="w-full accent-purple-600 cursor-pointer mt-1" />
                            <div class="flex justify-between text-[9px] text-slate-400 mt-1">
                                <span>0.5x (Slow)</span>
                                <span>1.0x (Normal)</span>
                                <span>1.75x (Fast)</span>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Voice Pitch</label>
                                <span class="text-xs font-mono font-bold text-purple-600 dark:text-purple-400" x-text="`${voiceForm.pitch >= 0 ? '+' : ''}${voiceForm.pitch}`"></span>
                            </div>
                            <input type="range" min="-2.0" max="2.0" step="0.2" x-model.number="voiceForm.pitch" class="w-full accent-purple-600 cursor-pointer mt-1" />
                            <div class="flex justify-between text-[9px] text-slate-400 mt-1">
                                <span>-2.0 (Deep)</span>
                                <span>0.0 (Standard)</span>
                                <span>+2.0 (High)</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Synthesize Action Button --}}
                <div class="flex items-center justify-between pt-1">
                    <div class="text-xs text-slate-500">
                        <span class="font-bold text-slate-700 dark:text-slate-300" x-text="`Selected: ${voiceForm.voice} · ${voiceForm.custom_tone || voiceForm.style} · ${voiceForm.speed}x`"></span>
                    </div>
                    <button type="button"
                            @click="generateVoiceStudio()"
                            :disabled="voiceForm.isSynthesizing || !voiceForm.script.trim()"
                            class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold text-xs shadow-lg shadow-purple-500/25 active:scale-95 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                        <span x-show="!voiceForm.isSynthesizing">🎙️</span>
                        <span x-show="voiceForm.isSynthesizing" class="animate-spin">⏳</span>
                        <span x-text="voiceForm.isSynthesizing ? 'Synthesizing Audio via Google AI...' : 'Synthesize Voice-Over (Google AI)'"></span>
                    </button>
                </div>

                {{-- Interactive Audio Player Card --}}
                <div x-show="voiceForm.currentAudio" class="p-4 rounded-2xl bg-gradient-to-r from-purple-500/10 via-indigo-500/10 to-blue-500/10 border border-purple-500/30 space-y-3 shadow-md">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-purple-600 to-indigo-600 text-white flex items-center justify-center text-sm shadow-sm">
                                🔊
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <span x-text="voiceForm.currentAudio?.voice_name"></span>
                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-purple-500/20 text-purple-600 dark:text-purple-400" x-text="`${voiceForm.currentAudio?.duration}s`"></span>
                                    <span x-show="voiceForm.currentAudio?.is_khmer" class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-amber-500/20 text-amber-600 dark:text-amber-400">Khmer Engine</span>
                                </h4>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate max-w-md" x-text="voiceForm.currentAudio?.title || voiceForm.script"></p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <a :href="voiceForm.currentAudio?.audio_url"
                               download
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs shadow-sm transition-all active:scale-95">
                                <span>⬇️</span>
                                <span>Download Audio</span>
                            </a>
                        </div>
                    </div>

                    <audio id="kiuq-studio-audio-player" :src="voiceForm.currentAudio?.audio_url" controls class="w-full h-9 rounded-xl outline-none"></audio>
                </div>

                {{-- Previous Voice Generations Library --}}
                <div x-show="studioData.voices && studioData.voices.length > 0" class="space-y-2 pt-2 border-t border-slate-200/70 dark:border-slate-800">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">Recent Voice Generations</h4>
                    <div class="space-y-2">
                        <template x-for="(vItem, idx) in studioData.voices.slice(0, 5)" :key="idx">
                            <div class="p-3 rounded-xl bg-white/60 dark:bg-slate-900/60 border border-slate-200/70 dark:border-slate-800 flex items-center justify-between gap-3 text-xs">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="w-6 h-6 rounded-lg bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center text-xs font-bold">🎙️</span>
                                    <div class="min-w-0">
                                        <p class="font-bold text-slate-800 dark:text-slate-200 truncate" x-text="vItem.voice_name || 'AI Voice'"></p>
                                        <p class="text-[10px] text-slate-400 truncate max-w-sm" x-text="vItem.title || vItem.script"></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <audio :src="vItem.audio_url" controls class="h-7 w-44 sm:w-56 rounded-lg"></audio>
                                    <a :href="vItem.audio_url" download class="p-1.5 text-slate-400 hover:text-purple-600 text-xs">⬇️</a>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- 5. DEDICATED SEO OPTIMIZER VIEW ──────────────────────────────── --}}
            <div x-show="mode === 'seo'" class="flex-1 flex flex-col min-h-0 overflow-y-auto p-4 sm:p-6 custom-scrollbar space-y-4">
                <div class="border-b border-slate-200/70 dark:border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>🔍</span>
                        <span>SEO Content Optimizer</span>
                    </h3>
                    <p class="text-xs text-slate-500">Analyze keyword density, heading structure, and readability for higher search rankings.</p>
                </div>

                <div class="bg-white/60 dark:bg-slate-950/50 p-4 rounded-2xl border border-slate-200/70 dark:border-slate-800 space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Target Title</label>
                            <input type="text" x-model="seoForm.title" placeholder="Working title..." class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200" />
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Focus Keyword Phrase</label>
                            <input type="text" x-model="seoForm.focus_keyword" placeholder="e.g. mini excavator" class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Article Content / Body</label>
                        <textarea x-model="seoForm.content" rows="6" placeholder="Paste article draft text here to evaluate..." class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200"></textarea>
                    </div>

                    <div class="flex justify-end pt-1">
                        <button type="button" @click="runSeoAudit()" :disabled="seoForm.isAnalyzing" class="px-4 py-2 rounded-xl bg-indigo-600 text-white font-bold text-xs shadow-md">
                            🔍 Run SEO Audit
                        </button>
                    </div>
                </div>

                {{-- SEO Results Cards --}}
                <div x-show="seoForm.results" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="p-3.5 rounded-2xl bg-white/70 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-800 space-y-1">
                        <span class="text-[10px] font-bold uppercase text-slate-400">SEO Health Score</span>
                        <p class="text-base font-black text-indigo-600 dark:text-indigo-400" x-text="seoForm.results?.score"></p>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-white/70 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-800 space-y-1">
                        <span class="text-[10px] font-bold uppercase text-slate-400">Keyword Density</span>
                        <p class="text-base font-black text-emerald-600 dark:text-emerald-400" x-text="seoForm.results?.density"></p>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-white/70 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-800 space-y-1">
                        <span class="text-[10px] font-bold uppercase text-slate-400">Word Count</span>
                        <p class="text-base font-black text-slate-800 dark:text-slate-200" x-text="`${seoForm.results?.word_count} words`"></p>
                    </div>
                </div>
            </div>

            {{-- 6. DEDICATED TASK & BOARD ASSISTANT VIEW ──────────────────────── --}}
            <div x-show="mode === 'task'" class="flex-1 flex flex-col min-h-0 overflow-y-auto p-4 sm:p-6 custom-scrollbar space-y-4">
                <div class="border-b border-slate-200/70 dark:border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>📋</span>
                        <span>Task & Board Assistant</span>
                    </h3>
                    <p class="text-xs text-slate-500">Manage task cards, board lists, and employee assignments with natural AI assistance.</p>
                </div>

                <div x-show="taskForm.feedbackMsg" class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-xs font-semibold" x-text="taskForm.feedbackMsg"></div>

                <div class="bg-white/60 dark:bg-slate-950/50 p-4 rounded-2xl border border-slate-200/70 dark:border-slate-800 space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Target Board</label>
                            <select x-model="taskForm.board_id" @change="onTaskBoardChange()" class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200">
                                <template x-for="b in studioData.boards" :key="b.id">
                                    <option :value="b.id" x-text="b.name"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Target List</label>
                            <select x-model="taskForm.board_list_id" class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200">
                                <template x-for="l in (studioData.boards.find(x => x.id == taskForm.board_id)?.lists || [])" :key="l.id">
                                    <option :value="l.id" x-text="l.name"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <div class="col-span-2">
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Task Card Title</label>
                            <input type="text" x-model="taskForm.title" placeholder="e.g. Design 3 Banner Visuals for Autumn Machinery Sale" class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200" />
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Due Date</label>
                            <input type="date" x-model="taskForm.due_date" class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Task Description / Acceptance Criteria</label>
                        <textarea x-model="taskForm.description" rows="3" placeholder="Key instructions or task checklist..." class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200"></textarea>
                    </div>

                    <div class="flex justify-end pt-1">
                        <button type="button" @click="createTaskStudio()" :disabled="taskForm.isCreating" class="px-4 py-2 rounded-xl bg-indigo-600 text-white font-bold text-xs shadow-md">
                            📋 Create Task Card
                        </button>
                    </div>
                </div>
            </div>

            {{-- 7. DEDICATED WEBSITE QC ASSISTANT VIEW ────────────────────────── --}}
            <div x-show="mode === 'qc'" class="flex-1 flex flex-col min-h-0 overflow-y-auto p-4 sm:p-6 custom-scrollbar space-y-4">
                <div class="border-b border-slate-200/70 dark:border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>✅</span>
                        <span>Website Quality Control (QC) Studio</span>
                    </h3>
                    <p class="text-xs text-slate-500">Audit connected domains, review checklist items, and submit verified feedback.</p>
                </div>

                <div x-show="qcForm.feedbackMsg" class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-xs font-semibold" x-text="qcForm.feedbackMsg"></div>

                <div class="bg-white/60 dark:bg-slate-950/50 p-4 rounded-2xl border border-slate-200/70 dark:border-slate-800 space-y-3">
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Target Website for QC</label>
                        <select x-model="qcForm.website_id" class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200">
                            <template x-for="w in studioData.websites" :key="w.id">
                                <option :value="w.id" x-text="`${w.name} (${w.url})`"></option>
                            </template>
                        </select>
                    </div>

                    {{-- Checklist Items --}}
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-slate-500 mb-2">Quality Audit Checklist</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <template x-for="(item, idx) in qcForm.checklist" :key="item.key">
                                <label class="flex items-center gap-2.5 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white/70 dark:bg-slate-900/70 cursor-pointer">
                                    <input type="checkbox" x-model="item.checked" class="rounded text-indigo-600 focus:ring-indigo-500 w-4 h-4" />
                                    <span class="text-xs font-medium text-slate-700 dark:text-slate-300" x-text="item.label"></span>
                                </label>
                            </template>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">QC Audit Notes / Feedback</label>
                        <textarea x-model="qcForm.notes" rows="2" placeholder="Document any visual bugs, 404s, or styling discrepancies..." class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200"></textarea>
                    </div>

                    <div class="flex justify-end pt-1">
                        <button type="button" @click="submitQcStudio()" :disabled="qcForm.isSubmitting" class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold text-xs shadow-md">
                            ✅ Submit QC Audit
                        </button>
                    </div>
                </div>
            </div>

            {{-- 8. DEDICATED EXECUTIVE REPORT GENERATOR VIEW ──────────────────── --}}
            <div x-show="mode === 'report'" class="flex-1 flex flex-col min-h-0 overflow-y-auto p-4 sm:p-6 custom-scrollbar space-y-4">
                <div class="border-b border-slate-200/70 dark:border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>📊</span>
                        <span>Executive AI Report Generator</span>
                    </h3>
                    <p class="text-xs text-slate-500">Calculate verified database statistics and compile downloadable executive PDF & CSV reports.</p>
                </div>

                {{-- Report Generator Form --}}
                <div class="bg-white/60 dark:bg-slate-950/50 p-4 rounded-2xl border border-slate-200/70 dark:border-slate-800 space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Report Category</label>
                            <select x-model="reportForm.category" class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200">
                                <option value="monthly_production">Monthly Production Summary</option>
                                <option value="blog_publishing">Blog Publishing & SEO</option>
                                <option value="task_completion">Task Cards & Workflows</option>
                                <option value="website_qc">Website Quality Control</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Date From</label>
                            <input type="date" x-model="reportForm.date_from" class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200" />
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Date To</label>
                            <input type="date" x-model="reportForm.date_to" class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Custom Focus Instructions</label>
                        <input type="text" x-model="reportForm.instructions" placeholder="e.g. Compare video output with blog counts and highlight top performance highlights..." class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200" />
                    </div>

                    <div class="flex justify-end pt-1">
                        <button type="button" @click="generateReportStudio()" :disabled="reportForm.isGenerating" class="px-4 py-2 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-bold text-xs shadow-md">
                            <span x-text="reportForm.isGenerating ? 'Querying Database & Synthesizing...' : '📊 Generate Verified Report'"></span>
                        </button>
                    </div>
                </div>

                {{-- Recent Reports Download Cards --}}
                <div class="space-y-2">
                    <h4 class="text-xs font-bold uppercase text-slate-400">Available Reports</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <template x-for="r in studioData.reports" :key="r.id">
                            <div class="p-3.5 rounded-2xl bg-white/70 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                                <div>
                                    <h5 class="text-xs font-bold text-slate-800 dark:text-slate-200" x-text="r.title"></h5>
                                    <p class="text-[11px] text-slate-400 mt-0.5" x-text="`${r.period} • ${r.date}`"></p>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <a :href="r.pdf_url" target="_blank" class="px-2.5 py-1.5 rounded-xl bg-rose-500/10 text-rose-600 font-bold text-[11px] hover:bg-rose-500/20">
                                        PDF
                                    </a>
                                    <a :href="r.csv_url" download class="px-2.5 py-1.5 rounded-xl bg-emerald-500/10 text-emerald-600 font-bold text-[11px] hover:bg-emerald-500/20">
                                        CSV
                                    </a>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<style>
    .kiuq-markdown-content {
        word-break: break-word;
        line-height: 1.65;
    }
    .kiuq-markdown-content h1,
    .kiuq-markdown-content h2,
    .kiuq-markdown-content h3,
    .kiuq-markdown-content h4 {
        color: inherit;
        font-weight: 700;
        letter-spacing: -0.01em;
        line-height: 1.35;
    }
    .kiuq-markdown-content h1 { font-size: 1.25rem; margin-top: 1rem; margin-bottom: 0.5rem; }
    .kiuq-markdown-content h2 { font-size: 1.1rem; margin-top: 0.9rem; margin-bottom: 0.45rem; }
    .kiuq-markdown-content h3 { font-size: 0.98rem; margin-top: 0.8rem; margin-bottom: 0.35rem; }
    .kiuq-markdown-content h4 { font-size: 0.88rem; margin-top: 0.65rem; margin-bottom: 0.3rem; }
    .kiuq-markdown-content p {
        margin-top: 0.4rem;
        margin-bottom: 0.4rem;
        line-height: 1.65;
    }
    .kiuq-markdown-content p:first-child { margin-top: 0; }
    .kiuq-markdown-content p:last-child { margin-bottom: 0; }
    .kiuq-markdown-content ul,
    .kiuq-markdown-content ol {
        margin-top: 0.35rem;
        margin-bottom: 0.5rem;
        padding-left: 1.25rem;
    }
    .kiuq-markdown-content ul { list-style-type: disc; }
    .kiuq-markdown-content ol { list-style-type: decimal; }
    .kiuq-markdown-content li {
        margin-top: 0.2rem;
        margin-bottom: 0.2rem;
        line-height: 1.55;
    }
    .kiuq-markdown-content strong {
        font-weight: 700;
        color: inherit;
    }
    .kiuq-markdown-content blockquote {
        border-left: 3.5px solid #6366f1;
        padding: 0.4rem 0 0.4rem 0.85rem;
        margin: 0.65rem 0;
        font-style: italic;
        background: rgba(99, 102, 241, 0.06);
        border-radius: 0 0.5rem 0.5rem 0;
    }
    .dark .kiuq-markdown-content blockquote {
        background: rgba(99, 102, 241, 0.12);
        border-left-color: #818cf8;
    }
    .kiuq-markdown-content code:not(pre code) {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.84em;
        font-weight: 600;
        padding: 0.15rem 0.35rem;
        border-radius: 0.375rem;
        background: rgba(99, 102, 241, 0.1);
        color: #4f46e5;
    }
    .dark .kiuq-markdown-content code:not(pre code) {
        background: rgba(99, 102, 241, 0.22);
        color: #a5b4fc;
    }
    .bg-gradient-to-r .kiuq-markdown-content code:not(pre code) {
        background: rgba(255, 255, 255, 0.2) !important;
        color: #ffffff !important;
    }
    .kiuq-normal-text {
        font-family: inherit;
        line-height: 1.65;
        white-space: pre-wrap;
        word-break: break-word;
        margin: 0.5rem 0;
        color: inherit;
    }
    .kiuq-code-block {
        margin: 0.85rem 0 !important;
        border-radius: 0.75rem !important;
        overflow: hidden !important;
        border: 1px solid rgba(51, 65, 85, 0.8) !important;
        background: #020617 !important;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.35) !important;
    }
    .kiuq-code-block pre {
        margin: 0 !important;
        border: none !important;
        border-radius: 0 !important;
        background: #020617 !important;
        padding: 0.85rem !important;
    }
    .kiuq-markdown-content pre {
        background: #0f172a;
        color: #f1f5f9;
        padding: 0.85rem;
        border-radius: 0.75rem;
        overflow-x: auto;
        margin: 0.65rem 0;
        font-size: 0.82em;
        line-height: 1.5;
        border: 1px solid rgba(51, 65, 85, 0.6);
    }
    .kiuq-markdown-content pre code {
        background: transparent !important;
        color: inherit !important;
        padding: 0 !important;
        font-size: inherit !important;
    }
    .kiuq-markdown-content table {
        width: 100%;
        border-collapse: collapse;
        margin: 0.75rem 0;
        font-size: 0.84em;
        border-radius: 0.5rem;
        overflow: hidden;
    }
    .kiuq-markdown-content th,
    .kiuq-markdown-content td {
        border: 1px solid rgba(148, 163, 184, 0.25);
        padding: 0.45rem 0.65rem;
        text-align: left;
    }
    .kiuq-markdown-content th {
        background: rgba(99, 102, 241, 0.08);
        font-weight: 700;
    }
    .dark .kiuq-markdown-content th {
        background: rgba(99, 102, 241, 0.18);
    }
    .kiuq-markdown-content tr:nth-child(even) {
        background: rgba(148, 163, 184, 0.04);
    }
    .kiuq-markdown-content a {
        color: #4f46e5;
        text-decoration: underline;
        font-weight: 500;
    }
    .dark .kiuq-markdown-content a {
        color: #818cf8;
    }
    .kiuq-markdown-content hr {
        border: 0;
        border-top: 1px solid rgba(148, 163, 184, 0.25);
        margin: 1rem 0;
    }
</style>
