{{-- ── Fixed Header KIUQ AI Button ────────────────────────────────────────── --}}
<button type="button"
        @click="$dispatch('open-kiuq-ai')"
        class="kiuq-ai-header-btn group relative inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-indigo-200/80 dark:border-indigo-500/30 bg-gradient-to-r from-indigo-50/90 via-purple-50/70 to-white/90 dark:from-indigo-950/40 dark:via-purple-950/30 dark:to-slate-900/60 shadow-xs hover:shadow-md hover:border-indigo-400/80 dark:hover:border-indigo-400/60 transition-all duration-200 active:scale-95 select-none focus:outline-hidden"
        title="Open KIUQ AI (⌘K / Ctrl+K)"
        aria-label="Open KIUQ AI Assistant">
    
    {{-- Animated Sparkle AI Icon with Radiant Glow --}}
    <span class="relative flex items-center justify-center w-5 h-5 rounded-lg bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-500 text-white shadow-xs group-hover:scale-110 group-hover:shadow-indigo-500/30 transition-all duration-200">
        <svg class="w-3.5 h-3.5 fill-current animate-pulse" viewBox="0 0 24 24">
            <path d="M12 2L14.4 8.6L21 11L14.4 13.4L12 20L9.6 13.4L3 11L9.6 8.6L12 2Z" />
        </svg>
        {{-- Subtle Live Pulse Ring --}}
        <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-slate-900 animate-ping opacity-75"></span>
        <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-slate-900"></span>
    </span>

    {{-- Branding Text (Desktop) --}}
    <span class="hidden sm:inline-flex items-center gap-1.5 text-xs font-black tracking-wide uppercase bg-gradient-to-r from-indigo-700 via-purple-700 to-indigo-900 dark:from-indigo-300 dark:via-purple-200 dark:to-white bg-clip-text text-transparent group-hover:tracking-wider transition-all duration-200">
        KIUQ AI
    </span>

    {{-- Keyboard Shortcut Badge (Desktop) --}}
    <span class="hidden md:inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold font-mono text-indigo-500/80 dark:text-indigo-300/80 bg-indigo-100/60 dark:bg-indigo-900/40 border border-indigo-200/50 dark:border-indigo-700/40">
        ⌘K
    </span>
</button>
