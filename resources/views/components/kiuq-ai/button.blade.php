{{-- ── Fixed Header KIUQ AI Button ────────────────────────────────────────── --}}
<button type="button"
        @click="$dispatch('open-kiuq-ai')"
        class="kiuq-ai-header-btn group relative inline-flex items-center justify-center lg:justify-start gap-2 w-9 h-9 lg:w-auto lg:h-9 p-0 lg:px-3 lg:py-1.5 rounded-full lg:rounded-xl border border-indigo-200/80 dark:border-indigo-500/35 bg-white/85 lg:bg-gradient-to-r lg:from-indigo-50/90 lg:via-purple-50/70 lg:to-white/90 dark:bg-slate-800/85 dark:lg:from-indigo-950/60 dark:lg:via-purple-950/50 dark:lg:to-slate-900/80 shadow-xs hover:shadow-md hover:border-indigo-400/80 dark:hover:border-indigo-400/60 transition-all duration-200 active:scale-95 select-none focus:outline-hidden flex-shrink-0"
        title="Open KIUQ AI (⌘K / Ctrl+K)"
        aria-label="Open KIUQ AI Assistant">
    
    {{-- Animated Sparkle AI Icon with Radiant Glow --}}
    <span class="kiuq-ai-icon-badge relative flex-shrink-0 flex items-center justify-center w-6 h-6 lg:w-5 lg:h-5 rounded-full lg:rounded-lg bg-gradient-to-br from-indigo-600 via-purple-600 to-fuchsia-500 text-white shadow-xs group-hover:scale-105 transition-all duration-200">
        <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
            <path d="M12 2L14.4 8.6L21 11L14.4 13.4L12 20L9.6 13.4L3 11L9.6 8.6L12 2Z" />
        </svg>
        {{-- Clean Live Status Dot (No dark ring on ping) --}}
        <span class="kiuq-ai-status-dot absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-emerald-400 border-[1.5px] border-white dark:border-slate-900 shadow-xs"></span>
    </span>

    {{-- Branding Text (Desktop Only) --}}
    <span class="kiuq-ai-header-label hidden lg:inline-flex items-center gap-1.5 text-xs font-black tracking-wide uppercase bg-gradient-to-r from-indigo-700 via-purple-700 to-indigo-900 dark:from-indigo-300 dark:via-purple-200 dark:to-white bg-clip-text text-transparent group-hover:tracking-wider transition-all duration-200 whitespace-nowrap">
        KIUQ AI
    </span>

    {{-- Keyboard Shortcut Badge (Desktop Only) --}}
    <span class="kiuq-ai-shortcut-badge hidden lg:inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold font-mono text-indigo-500/80 dark:text-indigo-300/80 bg-indigo-100/60 dark:bg-indigo-900/40 border border-indigo-200/50 dark:border-indigo-700/40 whitespace-nowrap">
        ⌘K
    </span>
</button>
