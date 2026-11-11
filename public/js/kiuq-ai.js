/**
 * KIUQ AI - Liquid Glass Interactive Enterprise Suite
 * Inspired by Google Gemini, ChatGPT, Claude AI, and Apple Liquid Glass.
 * Powers multi-turn chat, dedicated studios, model selection, prompt improvement,
 * clipboard screenshot pasting, and voice-over generation.
 */

// ── Bulletproof Clipboard Engine with execCommand Fallback ──────────────────
function kiuqRobustCopy(text) {
    if (!text) return Promise.reject(new Error('Empty text'));

    const fallback = () => new Promise((resolve, reject) => {
        try {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.top = '0';
            ta.style.left = '0';
            ta.style.width = '2em';
            ta.style.height = '2em';
            ta.style.padding = '0';
            ta.style.border = 'none';
            ta.style.outline = 'none';
            ta.style.boxShadow = 'none';
            ta.style.background = 'transparent';
            ta.style.opacity = '0';
            ta.style.pointerEvents = 'none';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            const success = document.execCommand('copy');
            document.body.removeChild(ta);
            if (success) resolve();
            else reject(new Error('execCommand returned false'));
        } catch (e) {
            reject(e);
        }
    });

    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function' && window.isSecureContext) {
        return navigator.clipboard.writeText(text).catch(() => fallback());
    }
    return fallback();
}

// ── Recognized Programming & Scripting Languages ─────────────────────────────
const KIUQ_PROGRAMMING_LANGS = new Set([
    'javascript', 'js', 'typescript', 'ts', 'jsx', 'tsx',
    'html', 'htm', 'xml', 'svg',
    'css', 'scss', 'sass', 'less',
    'php', 'python', 'py', 'ruby', 'rb', 'perl', 'pl',
    'sql', 'mysql', 'pgsql', 'sqlite',
    'bash', 'sh', 'zsh', 'shell', 'terminal', 'cmd', 'powershell', 'ps1',
    'json', 'yaml', 'yml', 'toml', 'ini', 'env',
    'c', 'cpp', 'csharp', 'cs', 'java', 'kotlin', 'kt', 'swift', 'go', 'rust', 'rs',
    'dart', 'scala', 'r', 'lua', 'dockerfile', 'docker', 'makefile', 'graphql'
]);

function isProgrammingCode(lang, codeText) {
    const cleanLang = (lang || '').trim().toLowerCase();
    if (KIUQ_PROGRAMMING_LANGS.has(cleanLang)) {
        return true;
    }
    // Explicit non-code tags: always normal text (never terminal code box)
    if (['text', 'txt', 'plain', 'plaintext', 'none', 'caption', 'quote', 'prompt'].includes(cleanLang)) {
        return false;
    }
    // If no language or generic 'code', check if the text contains actual programming syntax
    if (!cleanLang || cleanLang === 'code') {
        const text = String(codeText || '').trim();
        const codePatterns = [
            /^(?:import|export|const|let|var|function|class|def|public|private|protected)\s+/m,
            /(?:=>|\$\w+|===|!==|;|\{\s*\n)/,
            /<\/?(?:div|span|p|a|button|table|tr|td|script|style|html|body)[\s>]/i,
            /<\?php/i,
            /\b(?:SELECT|INSERT|UPDATE|DELETE|FROM|WHERE)\b.*\b(?:FROM|INTO|TABLE|SET)\b/i,
            /^(?:npm|yarn|pnpm|composer|git|docker|curl|php|python|pip|sudo|apt|brew)\s+/m
        ];
        return codePatterns.some(pattern => pattern.test(text));
    }
    return false;
}

function buildKiuqNormalTextBlock(text) {
    if (!text) return '';
    let formatted = String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    
    // Support basic inline markdown highlights in normal text
    formatted = formatted.replace(/\*\*([^*]+)\*\*/g, '<strong class="font-bold text-slate-900 dark:text-white">$1</strong>');
    formatted = formatted.replace(/\*([^*]+)\*/g, '<em class="italic text-slate-800 dark:text-slate-200">$1</em>');
    formatted = formatted.replace(/`([^`]+)`/g, '<code class="px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-mono text-[11px] font-semibold border border-indigo-200/60 dark:border-indigo-800/60">$1</code>');

    return `<div class="kiuq-normal-text my-2.5 text-slate-800 dark:text-slate-100 font-sans text-xs sm:text-[13px] leading-relaxed whitespace-pre-wrap select-text">${formatted}</div>`;
}

function buildKiuqCodeBlock(lang, codeHtml) {
    const cleanLang = (lang || 'code').trim().toUpperCase();
    return `<div class="kiuq-code-block my-3 rounded-xl overflow-hidden border border-slate-700/80 bg-slate-950 shadow-md" data-kiuq-code="true">
        <div class="kiuq-code-header flex items-center justify-between px-3.5 py-1.5 bg-slate-900/95 border-b border-slate-800 text-slate-300 text-xs font-mono select-none">
            <div class="flex items-center gap-2">
                <span class="flex gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-rose-500/80"></span>
                    <span class="w-2 h-2 rounded-full bg-amber-500/80"></span>
                    <span class="w-2 h-2 rounded-full bg-emerald-500/80"></span>
                </span>
                <span class="font-bold text-[10px] text-indigo-400 uppercase tracking-wider">${cleanLang}</span>
            </div>
            <button type="button" onclick="kiuqCopyCode(this)" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-slate-800 hover:bg-slate-700 active:scale-95 text-slate-200 hover:text-white text-xs font-semibold transition-all border border-slate-700 cursor-pointer shadow-xs" title="Copy code">
                <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                <span>Copy Code</span>
            </button>
        </div>
        <pre data-kiuq-pre="true" class="m-0 p-3.5 bg-slate-950 text-slate-100 overflow-x-auto text-xs font-mono leading-relaxed" style="background:#020617 !important; border:none !important; margin:0 !important; border-radius:0 !important;"><code class="language-${(lang || 'code').toLowerCase()}">${codeHtml}</code></pre>
    </div>`;
}

// Register custom code renderer on marked.js if present
if (window.marked && typeof window.marked.use === 'function') {
    try {
        window.marked.use({
            renderer: {
                code(token, langArg) {
                    const codeText = (typeof token === 'object' && token !== null) ? token.text : (token || '');
                    const rawLang = ((typeof token === 'object' && token !== null) ? token.lang : langArg) || '';
                    const cleanLang = (rawLang.split(/\s+/)[0] || '').toLowerCase();

                    // If not actual programming code (e.g. text, caption, quotes, prose), render as normal clean text
                    if (!isProgrammingCode(cleanLang, codeText)) {
                        return buildKiuqNormalTextBlock(codeText);
                    }

                    const escaped = String(codeText)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                    return buildKiuqCodeBlock(cleanLang || 'code', escaped);
                }
            }
        });
    } catch (e) {
        console.warn('marked.use renderer registration warning:', e);
    }
}

// ── Global Copy Helpers for Code Blocks & Message Text ──────────────────
window.kiuqCopyCode = function(button) {
    if (!button) return;
    const block = button.closest('.kiuq-code-block') || button.closest('pre');
    let codeText = '';
    if (block) {
        const codeEl = block.querySelector('code');
        codeText = codeEl ? (codeEl.innerText || codeEl.textContent) : (block.querySelector('pre')?.innerText || block.innerText);
    }
    if (!codeText) return;

    kiuqRobustCopy(codeText.trim()).then(() => {
        const originalHtml = button.innerHTML;
        button.innerHTML = '<span class="text-emerald-400 font-bold inline-flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>Copied!</span>';
        button.classList.add('border-emerald-500/60', 'bg-emerald-950/70', 'text-emerald-300');
        setTimeout(() => {
            button.innerHTML = originalHtml;
            button.classList.remove('border-emerald-500/60', 'bg-emerald-950/70', 'text-emerald-300');
        }, 2200);
    }).catch(err => {
        console.error('Copy code failed:', err);
    });
};

window.kiuqCopyText = function(text, button) {
    if (!text) return;
    kiuqRobustCopy(text).then(() => {
        if (!button) return;
        const originalHtml = button.innerHTML;
        button.innerHTML = '<span class="text-emerald-400 font-bold inline-flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>Copied!</span>';
        button.classList.add('border-emerald-500/60', 'bg-emerald-950/70', 'text-emerald-300');
        setTimeout(() => {
            button.innerHTML = originalHtml;
            button.classList.remove('border-emerald-500/60', 'bg-emerald-950/70', 'text-emerald-300');
        }, 2200);
    }).catch(err => {
        console.error('Copy text failed:', err);
    });
};

document.addEventListener('alpine:init', () => {
    Alpine.data('kiuqAiWorkspace', () => ({
        isOpen: false,
        isFullscreen: false,
        sidebarOpen: typeof window !== 'undefined' ? window.innerWidth >= 768 : true,
        mode: 'general', // 'general' (Chat) or dedicated studios: 'blog', 'image', 'seo', 'task', 'qc', 'report', 'voice'

        // Conversation management
        activeConversationId: null,
        conversations: [],
        messages: [],
        searchQuery: '',
        showArchived: false,

        // Composer & Model Selection
        inputText: '',
        isLoading: false,
        selectedModel: 'llama-3.3-70b-versatile',
        availableModels: [],
        modelDropdownOpen: false,
        attachments: [], // [{ name, type, base64 }]
        isDragging: false,

        // Prompt Improvement
        improvingPrompt: false,
        improvedPromptPreview: null,

        // Studio Data Context (Websites, Boards, Reports, Voice Overs)
        studioData: {
            websites: [],
            boards: [],
            reports: [],
            voices: []
        },

        // Dedicated Studio Forms & States
        blogForm: {
            website_id: '',
            title: '',
            seo_title: '',
            meta_description: '',
            focus_keyword: '',
            tag_keywords: '',
            content_html: '',
            featured_image_url: '',
            isSubmitting: false,
            feedbackMsg: null
        },
        imageForm: {
            prompt: '',
            aspect_ratio: '1:1',
            style_preset: 'photorealistic',
            isGenerating: false,
            latestImage: null,
            gallery: []
        },
        seoForm: {
            title: '',
            content: '',
            focus_keyword: '',
            isAnalyzing: false,
            results: null
        },
        taskForm: {
            board_id: '',
            board_list_id: '',
            title: '',
            description: '',
            due_date: '',
            isCreating: false,
            feedbackMsg: null
        },
        qcForm: {
            website_id: '',
            checklist: [
                { key: 'domain_ssl', label: 'Domain & SSL Certificate Valid', checked: true },
                { key: 'mobile_responsive', label: 'Mobile Viewport & Touch Layouts', checked: true },
                { key: 'seo_meta_tags', label: 'SEO Title, Meta Description & OG Tags', checked: false },
                { key: 'ga4_tracking', label: 'GA4 / Google Search Console Tracking', checked: true },
                { key: 'broken_links', label: 'Zero 404s & Working Hyperlinks', checked: false },
                { key: 'page_speed', label: 'Page Speed & Core Web Vitals (>85)', checked: true }
            ],
            notes: '',
            isSubmitting: false,
            feedbackMsg: null
        },
        reportForm: {
            title: '',
            category: 'monthly_production',
            date_from: '',
            date_to: '',
            instructions: '',
            isGenerating: false,
            latestReport: null
        },
        voiceForm: {
            script: '',
            voice: 'Charon',
            style: 'cinematic',
            custom_tone: '',
            speed: 1.0,
            pitch: 0.0,
            isSynthesizing: false,
            currentAudio: null,
            playingSample: null,
            categoryFilter: 'all',
            searchVoice: '',
        },
        currentSampleAudio: null,

        // Voice Style & Tone Presets matching Google AI Studio
        voiceTonePresets: [
            { id: 'whisper', label: 'Whisper', icon: '🤫', desc: 'whisper quietly, soft breathy delivery, intimate tone' },
            { id: 'friendly', label: 'Friendly', icon: '🌸', desc: 'Warm and neutral delivery with micro-pauses' },
            { id: 'narration', label: 'Narration', icon: '📖', desc: 'commanding, rich vocal modulation, dramatic pauses' },
            { id: 'promote', label: 'Promote', icon: '⚡', desc: 'High energy, elongated vowels, upbeat commercial promotion' },
            { id: 'cinematic', label: 'Cinematic', icon: '🎬', desc: 'Deep, dramatic, slow-paced movie-trailer style with gravitas' },
            { id: 'professional', label: 'Professional', icon: '👔', desc: 'Polished, articulate, confident corporate executive style' },
            { id: 'energetic', label: 'Energetic', icon: '🔥', desc: 'Dynamic, upbeat, fast-paced, high impact promotional style' },
            { id: 'calm', label: 'Calm', icon: '🌿', desc: 'Gentle, soothing, calm, relaxed cadence with smooth breathing' },
            { id: 'dramatic', label: 'Dramatic', icon: '🎭', desc: 'Intense, expressive pauses, heavy emotional resonance' },
            { id: 'luxury', label: 'Luxury', icon: '✨', desc: 'Sophisticated, smooth, prestigious cadence and refined elegance' },
            { id: 'broadcast', label: 'Radio Broadcast', icon: '📻', desc: 'Punchy, crisp radio announcer, high projection and articulate diction' },
            { id: 'educational', label: 'Educational', icon: '🎓', desc: 'Clear, explanatory, patient, encouraging tutor delivery' },
            { id: 'khmer_cinematic', label: 'Khmer Deep', icon: '🇰🇭', desc: 'deep, resonant, cinematic Khmer delivery with commanding gravitas' },
            { id: 'khmer_sweet', label: 'Khmer Sweet', icon: '🇰🇭', desc: 'sweet, polite, gentle, melodious native Khmer delivery' },
        ],

        // Comprehensive Google AI Studio Voice Character Definitions
        googleVoices: [
            // Commercial Voiceover
            { id: 'Koda', name: 'Koda', category: 'commercial', gender: 'male', badge: '💼 Commercial · Med-Low', description: 'Direct, confident, medium-low pitch voice designed for commercial promos and sales pitches.', sampleText: 'Upgrade your productivity today with precision tools engineered for performance.' },
            { id: 'Nika', name: 'Nika', category: 'commercial', gender: 'female', badge: '🌟 Commercial · Med', description: 'Warm, modern, and engaging commercial voiceover. Ideal for brand campaigns and social ads.', sampleText: 'Experience the next generation of smart workflows designed specifically for your team.' },
            { id: 'Tavi', name: 'Tavi', category: 'commercial', gender: 'female', badge: '🎙️ Commercial · Med-High', description: 'Quiet, premium, intimate commercial tone with medium-high pitch for modern brands.', sampleText: 'Crafted for those who appreciate understated elegance and uncompromising quality.' },

            // Tutor
            { id: 'Bodi', name: 'Bodi', category: 'tutor', gender: 'male', badge: '📚 Tutor · Low', description: 'Quiet, thoughtful, intimate low pitch tone. Perfect for tutorials and focused study guides.', sampleText: 'Take a moment to review this step carefully before moving forward.' },
            { id: 'Lumi', name: 'Lumi', category: 'tutor', gender: 'female', badge: '🌸 Tutor · Low', description: 'Warm and approachable low pitch voice. Excellent for step-by-step guidance and onboarding.', sampleText: 'Hello there! Let us walk through this process together, step by step.' },
            { id: 'Sola', name: 'Sola', category: 'tutor', gender: 'female', badge: '✨ Tutor · Very High', description: 'Gentle, soothing, very high pitch tone with friendly pacing. Great for friendly explanations.', sampleText: 'Everything looks clear and ready. Let us proceed with confidence.' },

            // Instructional
            { id: 'Kira', name: 'Kira', category: 'instructional', gender: 'female', badge: '🎯 Instructional · Med', description: 'Cool, confident, medium pitch voice. Clear articulation for procedural walkthroughs.', sampleText: 'Select your preferred layout and confirm the parameters to deploy your changes.' },
            { id: 'Ludo', name: 'Ludo', category: 'instructional', gender: 'male', badge: '💡 Instructional · Low', description: 'Confident, articulate, low pitch tone for technical documentation and instruction.', sampleText: 'All system checks have passed. Your workflow is running at maximum efficiency.' },
            { id: 'Mako', name: 'Mako', category: 'instructional', gender: 'male', badge: '⚡ Instructional · Low', description: 'Energetic, colorful, low pitch voice that brings enthusiasm to educational content.', sampleText: 'Get ready to see how easy it is to automate your daily tasks with just a few clicks!' },
            { id: 'Fola', name: 'Fola', category: 'instructional', gender: 'female', badge: '🤝 Instructional · Med', description: 'Clear, crisp, friendly delivery with medium pitch. Outstanding clarity and balanced cadence.', sampleText: 'Welcome! Everything is set up and ready. Let us explore what you can achieve today.' },

            // Cinematic & Narrative Power
            { id: 'Charon', name: 'Charon', category: 'cinematic', gender: 'male', badge: '🎬 Deep Cinematic · Low', description: 'Deep, resonant narration voice with commanding authority. Perfect for movie trailers & documentaries.', sampleText: 'In a world of relentless innovation, precision defines the masters of the craft.' },
            { id: 'Fenrir', name: 'Fenrir', category: 'cinematic', gender: 'male', badge: '🔥 Power & Athletic · Med-Low', description: 'Strong, impactful tone crafted for high-energy promos and athletic or machinery showcases.', sampleText: 'Engineered for absolute power, built to conquer any challenge.' },
            { id: 'Zephyr', name: 'Zephyr', category: 'cinematic', gender: 'male', badge: '⚡ Resonant Epic · Med', description: 'Epic cinematic storytelling voice with rich depth, gravitas, and theatrical presence.', sampleText: 'From the foundation of great design emerges enduring strength and purpose.' },
            { id: 'Puck', name: 'Puck', category: 'cinematic', gender: 'male', badge: '🚀 Dynamic Upbeat · Med', description: 'Dynamic, modern, and energetic male voice. Ideal for product announcements, commercials, and reels.', sampleText: 'Discover unmatched precision with cutting-edge performance engineered for excellence.' },
            { id: 'Orus', name: 'Orus', category: 'cinematic', gender: 'male', badge: '👔 Corporate Pro · Med-Low', description: 'Polished, articulate business voice suited for executive presentations and technical briefings.', sampleText: 'Our strategic overview indicates continuous growth across all operational metrics.' },
            { id: 'Aoede', name: 'Aoede', category: 'cinematic', gender: 'female', badge: '🎵 Expressive · Med-High', description: 'Warm and expressive female voice with clear diction and subtle resonance.', sampleText: 'Every detail matters when engineering reliability for tomorrow.' },
            { id: 'Kore', name: 'Kore', category: 'cinematic', gender: 'female', badge: '🌸 Warm & Inviting · Med', description: 'Gentle, natural, and inviting female timbre. Great for storytelling, customer demos, and tutorials.', sampleText: 'Welcome to your workspace. Let us create something remarkable together today.' },

            // Native Khmer Voices
            { id: 'Khmer-Male', name: 'Khmer Male (Dara)', category: 'khmer', gender: 'male', badge: '🇰🇭 Khmer Deep (Dara)', description: 'Authentic native Khmer male voice with deep, clear, cinematic delivery.', sampleText: 'សួស្តី ខ្ញុំឈ្មោះដារ៉ា សូមស្វាគមន៍មកកាន់ប្រព័ន្ធ DGT System។' },
            { id: 'Khmer-Female', name: 'Khmer Female (Bopha)', category: 'khmer', gender: 'female', badge: '🇰🇭 Khmer Natural (Bopha)', description: 'Sweet, natural, and expressive native Khmer female speech.', sampleText: 'សូមស្វាគមន៍មកកាន់ KIUQ AI។ តើខ្ញុំអាចជួយអ្វីអ្នកនៅថ្ងៃនេះបាន?' }
        ],

        errorNotice: null,
        csrfToken: '',

        init() {
            this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            // Set default date range for report generator (last 30 days)
            const now = new Date();
            const past30 = new Date(now.getTime() - (30 * 24 * 60 * 60 * 1000));
            this.reportForm.date_to = now.toISOString().split('T')[0];
            this.reportForm.date_from = past30.toISOString().split('T')[0];
            this.reportForm.title = `Executive Production Report - ${now.toLocaleString('default', { month: 'short', year: 'numeric' })}`;

            // Listen for global open events from fixed header button
            window.addEventListener('open-kiuq-ai', (e) => {
                const targetMode = e.detail?.mode || this.mode;
                this.openModal(targetMode);
            });

            // Global keyboard shortcuts: Esc to close, Cmd+K / Ctrl+K to toggle
            window.addEventListener('keydown', (e) => {
                if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    this.toggleModal();
                } else if (e.key === 'Escape' && this.isOpen) {
                    this.closeModal();
                }
            });

            // Auto-load conversations and models when opened
            this.$watch('isOpen', (val) => {
                if (val) {
                    this.loadConversations();
                    this.loadModels();
                    this.loadStudioData();
                    this.$nextTick(() => {
                        this.focusInput();
                        this.scrollToBottom();
                    });
                }
            });
        },

        openModal(initialMode = 'general') {
            this.mode = initialMode;
            this.isOpen = true;
            document.body.classList.add('overflow-hidden');
            if (window.innerWidth < 768) {
                this.sidebarOpen = false;
            }
        },

        closeModal() {
            this.isOpen = false;
            this.modelDropdownOpen = false;
            document.body.classList.remove('overflow-hidden');
        },

        toggleModal() {
            if (this.isOpen) {
                this.closeModal();
            } else {
                this.openModal();
            }
        },

        toggleFullscreen() {
            this.isFullscreen = !this.isFullscreen;
        },

        setMode(newMode) {
            this.mode = newMode;
            this.errorNotice = null;
            if (newMode === 'translate') {
                this.selectedModel = 'qwen/qwen3.8-27b';
            }
            if (window.innerWidth < 768) {
                this.sidebarOpen = false;
            }
            this.$nextTick(() => this.focusInput());
        },

        getInputPlaceholder() {
            if (this.mode === 'translate') {
                return 'Enter text or sentence to translate (e.g., "Translate into Khmer: ..." or "Translate to English: ...")';
            }
            return 'Message KIUQ AI... (Paste screenshot Cmd+V, Enter to send, Shift+Enter for new line)';
        },

        // ── Model Selection ────────────────────────────────────────────────────
        async loadModels() {
            try {
                const res = await fetch('/ai/models', {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const data = await res.json();
                    this.availableModels = data.models || [];
                    if (!this.selectedModel && data.active_model) {
                        this.selectedModel = data.active_model;
                    }
                }
            } catch (err) {
                console.warn('Could not load AI models list:', err);
            }
        },

        selectModel(modelId) {
            this.selectedModel = modelId;
            this.modelDropdownOpen = false;
        },

        getSelectedModelBadge() {
            const m = this.availableModels.find(x => x.id === this.selectedModel);
            return m ? m.badge : '⚡ Fast Chat';
        },

        // ── Conversation Lifecycle ─────────────────────────────────────────────
        async loadConversations() {
            try {
                const url = `/ai/conversations?archived=${this.showArchived ? 1 : 0}&search=${encodeURIComponent(this.searchQuery)}`;
                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const data = await res.json();
                    this.conversations = data.conversations || [];
                    if (!this.activeConversationId && this.conversations.length > 0 && this.mode === 'general') {
                        this.selectConversation(this.conversations[0].id);
                    }
                }
            } catch (err) {
                console.error('Failed to load KIUQ AI conversations:', err);
            }
        },

        async selectConversation(id) {
            this.activeConversationId = id;
            this.isLoading = true;
            this.errorNotice = null;
            this.mode = 'general'; // Switch to general chat feed to display this thread
            if (window.innerWidth < 768) {
                this.sidebarOpen = false;
            }
            try {
                const res = await fetch(`/ai/conversations/${id}/messages`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const data = await res.json();
                    this.messages = data.messages || [];
                    this.$nextTick(() => this.scrollToBottom());
                }
            } catch (err) {
                this.errorNotice = 'Could not load conversation history.';
            } finally {
                this.isLoading = false;
                this.focusInput();
            }
        },

        /**
         * Render Markdown text into rich, safe, beautifully formatted HTML with copy code buttons.
         */
        formatMarkdown(text) {
            if (!text || typeof text !== 'string') return '';

            let html = '';

            // 1. Use marked.js if available on window
            if (window.marked && typeof window.marked.parse === 'function') {
                try {
                    html = window.marked.parse(text, {
                        gfm: true,
                        breaks: true
                    });
                } catch (e) {
                    console.warn('marked.js parse warning:', e);
                }
            }

            // 2. High-fidelity built-in Markdown Parser Fallback
            if (!html) {
                html = text
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');

                // Fenced code blocks ```lang\ncode\n```
                html = html.replace(/```([a-zA-Z0-9_.-]*)\n([\s\S]*?)```/g, (match, lang, code) => {
                    const cleanLang = (lang || '').trim().toLowerCase();
                    if (!isProgrammingCode(cleanLang, code)) {
                        return buildKiuqNormalTextBlock(code.trim());
                    }
                    return buildKiuqCodeBlock(cleanLang || 'code', code.trim());
                });

                // Inline code `code`
                html = html.replace(/`([^`]+)`/g, '<code class="px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-mono text-[11px] font-semibold border border-indigo-200/60 dark:border-indigo-800/60">$1</code>');

                // Headings (###, ##, #)
                html = html.replace(/^### (.*$)/gim, '<h4 class="text-sm font-bold text-slate-900 dark:text-white mt-3.5 mb-1.5">$1</h4>');
                html = html.replace(/^## (.*$)/gim, '<h3 class="text-base font-bold text-slate-900 dark:text-white mt-4 mb-2">$1</h3>');
                html = html.replace(/^# (.*$)/gim, '<h2 class="text-lg font-extrabold text-slate-900 dark:text-white mt-4 mb-2">$1</h2>');

                // Blockquotes > text
                html = html.replace(/^\&gt;\s?(.*$)/gim, '<blockquote class="border-l-4 border-indigo-500 pl-3.5 my-2.5 text-slate-600 dark:text-slate-300 italic bg-indigo-50/50 dark:bg-indigo-950/30 py-1.5 rounded-r-xl">$1</blockquote>');

                // Bold **text**
                html = html.replace(/\*\*([^*]+)\*\*/g, '<strong class="font-bold text-slate-900 dark:text-white">$1</strong>');

                // Italic *text*
                html = html.replace(/\*([^*]+)\*/g, '<em class="italic">$1</em>');

                // Unordered list items - item or * item
                html = html.replace(/^[-*]\s+(.*$)/gim, '<li class="ml-4 list-disc text-slate-700 dark:text-slate-200 my-0.5">$1</li>');

                // Ordered list items 1. item
                html = html.replace(/^\d+\.\s+(.*$)/gim, '<li class="ml-4 list-decimal text-slate-700 dark:text-slate-200 my-0.5">$1</li>');

                // Line breaks
                html = html.replace(/\n/g, '<br>');

                // Clean up extra breaks around block elements
                html = html.replace(/(<\/h[234]>)\s*<br>/gi, '$1');
                html = html.replace(/(<\/pre>)\s*<br>/gi, '$1');
                html = html.replace(/(<\/div>)\s*<br>/gi, '$1');
                html = html.replace(/(<\/blockquote>)\s*<br>/gi, '$1');
                html = html.replace(/(<\/li>)\s*<br>/gi, '$1');
            }

            // Post-process any remaining pre blocks (from marked or raw HTML) that lack our copy header
            html = html.replace(/<pre(?:\s+[^>]*)?>([\s\S]*?)<\/pre>/gi, (match, inner) => {
                if (match.includes('data-kiuq-pre') || match.includes('kiuq-code') || inner.includes('kiuqCopyCode') || inner.includes('kiuq-code-header')) {
                    return match;
                }
                const langMatch = inner.match(/class=["'][^"']*(?:language-|lang-)([a-zA-Z0-9_.-]+)[^"']*["']/i);
                const lang = (langMatch && langMatch[1]) ? langMatch[1].toLowerCase() : '';
                const codeTagMatch = inner.match(/<code(?:\s+[^>]*)?>([\s\S]*?)<\/code>/i);
                const codeHtml = codeTagMatch ? codeTagMatch[1] : inner;

                if (!isProgrammingCode(lang, codeHtml)) {
                    return buildKiuqNormalTextBlock(codeHtml);
                }
                return buildKiuqCodeBlock(lang || 'code', codeHtml);
            });

            // 3. Post-process anchor links: ensure target="_blank" and resolve any card / direct links
            html = html.replace(/<a\s+(?:[^>]*?\s+)?href=["']([^"']*)["'][^>]*>([\s\S]*?)<\/a>/gi, (match, href, text) => {
                let cleanHref = href.trim();
                // If link points to card path like /dgt/boards/card/CRD-101 or /boards/card/CRD-101, rewrite to /cards/101
                const cardMatch = cleanHref.match(/(?:cards?|dgt\/boards\/card)\/(?:CRD-)?(\d+)/i);
                if (cardMatch) {
                    cleanHref = `/cards/${cardMatch[1]}`;
                }
                return `<a href="${cleanHref}" target="_blank" rel="noopener noreferrer" class="font-bold text-sky-600 dark:text-sky-400 underline underline-offset-2 hover:text-sky-700 dark:hover:text-sky-300 transition-colors inline-flex items-center gap-1">${text}<svg class="w-3 h-3 inline-block shrink-0 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>`;
            });

            // 4. Wrap tables with responsive styling container
            html = html.replace(/<table(?:\s+[^>]*)?>([\s\S]*?)<\/table>/gi, (match, tableInner) => {
                return `<div class="overflow-x-auto my-3 rounded-xl border border-slate-200/90 dark:border-slate-700/80 shadow-xs"><table class="w-full text-left text-xs border-collapse">${tableInner}</table></div>`;
            });
            html = html.replace(/<th(?:\s+[^>]*)?>([\s\S]*?)<\/th>/gi, '<th class="p-2.5 font-bold uppercase tracking-wider text-[10px] bg-slate-100 dark:bg-slate-900/90 text-slate-700 dark:text-slate-300 border-b border-slate-200 dark:border-slate-800">$1</th>');
            html = html.replace(/<td(?:\s+[^>]*)?>([\s\S]*?)<\/td>/gi, '<td class="p-2.5 border-b border-slate-100 dark:border-slate-800/60 text-slate-800 dark:text-slate-200">$1</td>');

            return html;
        },

        async startNewChat(targetMode = 'general') {
            this.mode = targetMode;
            this.isLoading = true;
            this.errorNotice = null;
            this.attachments = [];
            try {
                const res = await fetch('/ai/conversations', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ mode: targetMode })
                });
                if (res.ok) {
                    const data = await res.json();
                    const newConv = data.conversation;
                    this.conversations.unshift(newConv);
                    this.activeConversationId = newConv.id;
                    this.messages = [];
                    this.inputText = '';
                    this.focusInput();
                }
            } catch (err) {
                this.errorNotice = 'Failed to create new conversation.';
            } finally {
                this.isLoading = false;
            }
        },

        async togglePin(conv) {
            try {
                const res = await fetch(`/ai/conversations/${conv.id}/pin`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json'
                    }
                });
                if (res.ok) {
                    const data = await res.json();
                    conv.is_pinned = data.is_pinned;
                    this.loadConversations();
                }
            } catch (err) {
                console.error('Toggle pin error:', err);
            }
        },

        async toggleArchive(conv) {
            try {
                const res = await fetch(`/ai/conversations/${conv.id}/archive`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json'
                    }
                });
                if (res.ok) {
                    this.conversations = this.conversations.filter(c => c.id !== conv.id);
                    if (this.activeConversationId === conv.id) {
                        this.startNewChat();
                    }
                }
            } catch (err) {
                console.error('Archive conversation error:', err);
            }
        },

        async renameConversation(conv) {
            const newTitle = prompt('Rename conversation title:', conv.title);
            if (!newTitle || newTitle.trim() === conv.title) return;

            try {
                const res = await fetch(`/ai/conversations/${conv.id}/rename`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ title: newTitle.trim() })
                });
                if (res.ok) {
                    conv.title = newTitle.trim();
                }
            } catch (err) {
                console.error('Rename error:', err);
            }
        },

        async deleteConversation(id) {
            if (!confirm('Are you sure you want to delete this conversation?')) return;
            try {
                const res = await fetch(`/ai/conversations/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json'
                    }
                });
                if (res.ok) {
                    this.conversations = this.conversations.filter(c => c.id !== id);
                    if (this.activeConversationId === id) {
                        if (this.conversations.length > 0) {
                            this.selectConversation(this.conversations[0].id);
                        } else {
                            this.startNewChat();
                        }
                    }
                }
            } catch (err) {
                console.error('Delete conversation error:', err);
            }
        },

        // ── One-Click Copy & Prompt Insertion ──────────────────────────────────
        copyMessage(msg, event) {
            if (!msg || !msg.content) return;
            window.kiuqCopyText(msg.content, event?.currentTarget);
        },

        useAsPrompt(content) {
            if (!content) return;
            this.inputText = content;
            this.focusInput();
        },

        applyQuickAction(type) {
            const prompts = {
                ask: 'Explain in clear, step-by-step detail: ',
                code: 'Write clean, modern, and production-ready code with complete styling for: ',
                blog: 'Write an SEO-optimized, highly engaging blog post with H2 headings, key takeaways, and conclusion about: ',
                seo: 'Perform an in-depth SEO analysis and generate high-ranking keywords, meta title, and description for: ',
                translate: 'Translate the following text accurately into natural, professional English and Khmer:\n\n',
                social: 'Create 3 compelling, viral social media captions with emojis and high-traffic hashtags for: '
            };
            this.inputText = prompts[type] || '';
            this.focusInput();
        },

        // ── Keyboard Behavior (Enter to Send, Shift+Enter for Newline) ─────────
        handleComposerKeydown(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault(); // Do not insert newline
                this.sendMessage();
            }
            // If Shift+Enter, allow default (inserts newline)
        },

        // ── Multimodal Attachments & Clipboard Screenshot Pasting ───────────────
        handlePaste(e) {
            const clipboardData = e.clipboardData || window.clipboardData;
            if (!clipboardData) return;

            const items = clipboardData.items;
            for (let i = 0; i < items.length; i++) {
                if (items[i].type.indexOf('image') !== -1) {
                    const file = items[i].getAsFile();
                    this.processImageFile(file, `screenshot_${Date.now()}.png`);
                }
            }
        },

        handleFileUpload(e) {
            const files = e.target.files;
            if (!files || files.length === 0) return;
            for (let i = 0; i < files.length; i++) {
                this.processImageFile(files[i], files[i].name);
            }
            e.target.value = '';
        },

        handleDrop(e) {
            this.isDragging = false;
            const files = e.dataTransfer.files;
            if (!files || files.length === 0) return;
            for (let i = 0; i < files.length; i++) {
                if (files[i].type.startsWith('image/')) {
                    this.processImageFile(files[i], files[i].name);
                }
            }
        },

        processImageFile(file, filename) {
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                this.attachments.push({
                    name: filename || file.name || 'image.png',
                    type: file.type || 'image/png',
                    base64: e.target.result,
                    size: (file.size / 1024).toFixed(1) + ' KB'
                });
            };
            reader.readAsDataURL(file);
        },

        removeAttachment(index) {
            this.attachments.splice(index, 1);
        },

        // ── AI Prompt Improvement ("Improve Prompt") ───────────────────────────
        async improvePrompt() {
            const text = this.inputText.trim();
            if (!text || this.improvingPrompt) return;

            this.improvingPrompt = true;
            this.errorNotice = null;

            try {
                const res = await fetch('/ai/prompt/improve', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        prompt: text,
                        context: `Current mode: ${this.mode}`
                    })
                });

                if (res.ok) {
                    const data = await res.json();
                    if (data.improved_prompt) {
                        this.improvedPromptPreview = data;
                    }
                }
            } catch (err) {
                console.error('Improve prompt error:', err);
            } finally {
                this.improvingPrompt = false;
            }
        },

        applyImprovedPrompt() {
            if (this.improvedPromptPreview?.improved_prompt) {
                this.inputText = this.improvedPromptPreview.improved_prompt;
                this.improvedPromptPreview = null;
                this.focusInput();
            }
        },

        discardImprovedPrompt() {
            this.improvedPromptPreview = null;
        },

        // ── Send Message (Real-Time SSE Streaming like real Gemini) ──────────
        async sendMessage() {
            const text = this.inputText.trim();
            const hasAttachments = this.attachments.length > 0;

            if ((!text && !hasAttachments) || this.isLoading) return;

            // Push temporary user message immediately to the feed
            const userMsg = {
                id: 'temp-' + Date.now(),
                role: 'user',
                content: text,
                attachments: [...this.attachments],
                created_at: new Date().toISOString()
            };
            this.messages.push(userMsg);

            // Create streaming placeholder for the model response
            const botMsg = {
                id: 'stream-' + Date.now(),
                role: 'model',
                content: '',
                isStreaming: true,
                created_at: new Date().toISOString()
            };
            this.messages.push(botMsg);
            const botIdx = this.messages.length - 1;

            // Clear inputs
            const payloadText = text;
            const payloadAttachments = [...this.attachments];
            this.inputText = '';
            this.attachments = [];
            this.improvedPromptPreview = null;
            this.isLoading = true;
            this.errorNotice = null;

            this.$nextTick(() => this.scrollToBottom());

            // 60s AbortController to allow deep analytics queries without premature timeouts
            const abortController = new AbortController();
            const abortTimeout = setTimeout(() => abortController.abort(), 60000);

            try {
                const response = await fetch('/ai/chat/stream', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'text/event-stream, application/json'
                    },
                    body: JSON.stringify({
                        message: payloadText,
                        conversation_id: this.activeConversationId,
                        mode: this.mode,
                        model: this.selectedModel,
                        attachments: payloadAttachments
                    }),
                    signal: abortController.signal
                });

                clearTimeout(abortTimeout);

                if (!response.ok) {
                    throw new Error(`HTTP error ${response.status}`);
                }

                const contentType = response.headers.get('content-type') || '';
                if (contentType.includes('text/event-stream') && response.body) {
                    const reader = response.body.getReader();
                    const decoder = new TextDecoder('utf-8');
                    let buffer = '';

                    while (true) {
                        const { done, value } = await reader.read();
                        if (done) break;

                        buffer += decoder.decode(value, { stream: true });
                        const lines = buffer.split('\n');
                        buffer = lines.pop(); // keep last incomplete line

                        for (const line of lines) {
                            const trimmed = line.trim();
                            if (trimmed.startsWith('data: ')) {
                                try {
                                    const eventData = JSON.parse(trimmed.slice(6));

                                    // First token arrived -> stop loading indicator
                                    if (this.isLoading) {
                                        this.isLoading = false;
                                    }

                                    if (eventData.conversation_id && eventData.conversation_id !== this.activeConversationId) {
                                        this.activeConversationId = eventData.conversation_id;
                                    }

                                    if (eventData.text) {
                                        if (this.messages[botIdx]) {
                                            this.messages[botIdx].content += eventData.text;
                                            // Trigger Alpine reactivity by assigning array
                                            this.messages = [...this.messages];
                                        }
                                        this.scrollToBottom();
                                    }

                                    if (eventData.done) {
                                        if (this.messages[botIdx]) {
                                            this.messages[botIdx].isStreaming = false;
                                            if (eventData.message) {
                                                this.messages[botIdx].id = eventData.message.id;
                                                if (eventData.message.content) {
                                                    this.messages[botIdx].content = eventData.message.content;
                                                }
                                                if (eventData.message.action_type) {
                                                    this.messages[botIdx].action_type = eventData.message.action_type;
                                                    this.messages[botIdx].action_data = eventData.message.action_data;
                                                    this.messages[botIdx].action_status = eventData.message.action_status;
                                                }
                                            }
                                            this.messages = [...this.messages];
                                        }
                                        this.loadConversations();
                                    }
                                } catch (e) {
                                    console.warn('SSE parse error:', e);
                                }
                            }
                        }
                    }

                    if (this.messages[botIdx]) {
                        this.messages[botIdx].isStreaming = false;
                        this.messages = [...this.messages];
                    }
                } else {
                    // Fallback JSON handling
                    const data = await response.json();
                    this.isLoading = false;
                    if (this.messages[botIdx]) {
                        this.messages[botIdx].isStreaming = false;
                        if (data.success && data.message) {
                            this.messages[botIdx].id = data.message.id;
                            this.messages[botIdx].content = data.message.content;
                            this.messages[botIdx].action_type = data.message.action_type;
                            this.messages[botIdx].action_data = data.message.action_data;
                            this.messages[botIdx].action_status = data.message.action_status;
                            if (data.conversation_id && data.conversation_id !== this.activeConversationId) {
                                this.activeConversationId = data.conversation_id;
                                this.loadConversations();
                            }
                        } else {
                            this.messages[botIdx].content = data.error || 'Failed to receive AI response.';
                        }
                        this.messages = [...this.messages];
                    }
                }
            } catch (err) {
                clearTimeout(abortTimeout);
                this.isLoading = false;
                if (this.messages[botIdx]) {
                    this.messages[botIdx].isStreaming = false;
                }
                console.error('KIUQ AI Chat error:', err);

                // Fallback to standard /ai/chat if streaming endpoint errored out
                try {
                    const fallbackRes = await fetch('/ai/chat', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            message: payloadText,
                            conversation_id: this.activeConversationId,
                            mode: this.mode,
                            model: this.selectedModel,
                            attachments: payloadAttachments
                        })
                    });
                    const fallbackData = await fallbackRes.json();
                    if (fallbackRes.ok && fallbackData.success && fallbackData.message) {
                        if (this.messages[botIdx]) {
                            this.messages[botIdx].id = fallbackData.message.id;
                            this.messages[botIdx].content = fallbackData.message.content;
                            this.messages[botIdx].action_type = fallbackData.message.action_type;
                            this.messages[botIdx].action_data = fallbackData.message.action_data;
                            this.messages[botIdx].action_status = fallbackData.message.action_status;
                            this.messages = [...this.messages];
                        }
                        if (fallbackData.conversation_id && fallbackData.conversation_id !== this.activeConversationId) {
                            this.activeConversationId = fallbackData.conversation_id;
                            this.loadConversations();
                        }
                        return;
                    }
                } catch (fallbackErr) {
                    console.error('Fallback /ai/chat error:', fallbackErr);
                }

                if (this.messages[botIdx]) {
                    if (err.name === 'AbortError') {
                        this.messages[botIdx].content = "⚠️ The request timed out while contacting Gemini. Please retry in a moment.";
                    } else if (!this.messages[botIdx].content) {
                        this.messages[botIdx].content = "⚠️ Network connection to KIUQ AI failed. Please check your connection and try again.";
                    }
                    this.messages = [...this.messages];
                }
            } finally {
                this.isLoading = false;
                if (botMsg) {
                    botMsg.isStreaming = false;
                }
                if (this.messages[botIdx]) {
                    this.messages[botIdx].isStreaming = false;
                }
                this.messages = [...this.messages];
                this.$nextTick(() => {
                    this.scrollToBottom();
                    this.focusInput();
                });
            }
        },

        // ── Action Confirmation Preview Cards ──────────────────────────────────
        async confirmAction(msg) {
            if (!msg || msg.action_status !== 'pending_confirmation') return;
            this.isLoading = true;
            try {
                const res = await fetch('/ai/action/confirm', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ message_id: msg.id })
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    msg.action_status = 'confirmed';
                    msg.content += '\n\n✅ **Action Confirmed**: Operation executed successfully.';
                } else {
                    alert(data.error || 'Could not confirm action.');
                }
            } catch (err) {
                alert('Connection error confirming action.');
            } finally {
                this.isLoading = false;
            }
        },

        async cancelAction(msg) {
            if (!msg || msg.action_status !== 'pending_confirmation') return;
            try {
                const res = await fetch('/ai/action/cancel', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ message_id: msg.id })
                });
                if (res.ok) {
                    msg.action_status = 'cancelled';
                    msg.content += '\n\n❌ **Action Cancelled** by user.';
                }
            } catch (err) {
                console.error('Cancel action error:', err);
            }
        },

        // ── Dedicated Studios Logic ────────────────────────────────────────────

        async loadStudioData() {
            try {
                const res = await fetch('/ai/studio/data', {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const data = await res.json();
                    this.studioData = data;
                    if (data.websites?.length > 0 && !this.blogForm.website_id) {
                        this.blogForm.website_id = data.websites[0].id;
                        this.qcForm.website_id = data.websites[0].id;
                    }
                    if (data.boards?.length > 0 && !this.taskForm.board_id) {
                        this.taskForm.board_id = data.boards[0].id;
                        if (data.boards[0].lists?.length > 0) {
                            this.taskForm.board_list_id = data.boards[0].lists[0].id;
                        }
                    }
                }
            } catch (err) {
                console.warn('Could not load studio context data:', err);
            }
        },

        onTaskBoardChange() {
            const b = this.studioData.boards.find(x => x.id == this.taskForm.board_id);
            if (b && b.lists.length > 0) {
                this.taskForm.board_list_id = b.lists[0].id;
            } else {
                this.taskForm.board_list_id = '';
            }
        },

        // 1. Blog Studio AI Generator
        async generateBlogWithAi() {
            const topic = this.blogForm.title.trim();
            if (!topic) {
                alert('Please enter an article topic or working title first.');
                return;
            }

            this.blogForm.isSubmitting = true;
            this.blogForm.feedbackMsg = null;

            try {
                const prompt = `Write a comprehensive, professional, SEO-optimized blog article about: "${topic}". Include H2 subheadings, bullet points, meta title, meta description, and focus keywords. Format body in clean HTML.`;
                const res = await fetch('/ai/chat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        message: prompt,
                        mode: 'blog',
                        model: 'gemini-2.5-flash'
                    })
                });

                const data = await res.json();
                if (res.ok && data.message?.content) {
                    this.blogForm.content_html = data.message.content;
                    this.blogForm.seo_title = topic;
                    this.blogForm.focus_keyword = topic.split(' ').slice(0, 3).join(' ');
                    this.blogForm.meta_description = `Read our comprehensive guide on ${topic}. Discover key insights, specs, and expert recommendations.`;
                    this.blogForm.feedbackMsg = 'Article generated by Gemini! Review and refine below.';
                }
            } catch (err) {
                this.blogForm.feedbackMsg = 'Error generating article with AI.';
            } finally {
                this.blogForm.isSubmitting = false;
            }
        },

        async saveBlogDraft() {
            if (!this.blogForm.title || !this.blogForm.website_id) {
                alert('Please select a target website and provide an article title.');
                return;
            }

            this.blogForm.isSubmitting = true;
            this.blogForm.feedbackMsg = null;

            try {
                const prompt = `create_blog_draft for website_id ${this.blogForm.website_id}, title: "${this.blogForm.title}", content_html: "${this.blogForm.content_html.replace(/"/g, "'")}", focus_keyword: "${this.blogForm.focus_keyword}", meta_description: "${this.blogForm.meta_description}"`;
                const res = await fetch('/ai/chat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        message: `Please save this blog draft into the DGT CMS for website #${this.blogForm.website_id}: Title: "${this.blogForm.title}". SEO Title: "${this.blogForm.seo_title}". Meta Description: "${this.blogForm.meta_description}". Focus Keyword: "${this.blogForm.focus_keyword}". Body HTML length: ${this.blogForm.content_html.length} chars.`,
                        mode: 'blog'
                    })
                });

                const data = await res.json();
                this.blogForm.feedbackMsg = 'Draft article successfully saved into DGT CMS!';
            } catch (err) {
                this.blogForm.feedbackMsg = 'Failed to save blog draft.';
            } finally {
                this.blogForm.isSubmitting = false;
            }
        },

        // 2. Image Studio Generator
        async generateImageStudio() {
            const prompt = this.imageForm.prompt.trim();
            if (!prompt) {
                alert('Please enter an image prompt.');
                return;
            }

            this.imageForm.isGenerating = true;
            this.errorNotice = null;

            try {
                let enhancedPrompt = prompt;
                if (this.imageForm.style_preset === 'photorealistic') {
                    enhancedPrompt += ', 8k resolution, photorealistic, cinematic lighting, sharp focus';
                } else if (this.imageForm.style_preset === '3d_render') {
                    enhancedPrompt += ', 3D isometric render, octane render, smooth pastel studio lighting';
                } else if (this.imageForm.style_preset === 'banner') {
                    enhancedPrompt += ', commercial advertising banner, sleek composition, professional typography space';
                }

                const res = await fetch('/ai/images/generate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        prompt: enhancedPrompt,
                        aspect_ratio: this.imageForm.aspect_ratio
                    })
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.imageForm.latestImage = data;
                    this.imageForm.gallery.unshift(data);
                } else {
                    this.errorNotice = data.error || 'Failed to generate image.';
                }
            } catch (err) {
                this.errorNotice = 'Connection error generating image.';
            } finally {
                this.imageForm.isGenerating = false;
            }
        },

        // 3. SEO Optimizer
        async runSeoAudit() {
            if (!this.seoForm.content) {
                alert('Please enter content or an article draft to audit.');
                return;
            }

            this.seoForm.isAnalyzing = true;
            try {
                const words = this.seoForm.content.trim().split(/\s+/).length;
                const kw = this.seoForm.focus_keyword.trim();
                const kwCount = kw ? (this.seoForm.content.toLowerCase().match(new RegExp(kw.toLowerCase(), 'g')) || []).length : 0;
                const density = kw && words > 0 ? ((kwCount / words) * 100).toFixed(2) : 0;

                const headings = (this.seoForm.content.match(/<h[1-6]|#\s/gi) || []).length;
                const score = words >= 600 && headings >= 3 ? '94/100 (Excellent)' : (words >= 300 ? '78/100 (Good)' : '55/100 (Needs Expansion)');

                this.seoForm.results = {
                    score: score,
                    word_count: words,
                    headings_count: headings,
                    keyword_count: kwCount,
                    density: density + '%',
                    recommended_title: kw ? `${kw} - Complete 2026 Guide & Specs` : (this.seoForm.title || 'Optimized Machinery Article'),
                    recommended_meta: `Explore our definitive overview of ${kw || 'this machinery'}. Expert reviews, performance metrics, and operator insights.`,
                    recommendations: [
                        words < 600 ? 'Expand body content to at least 600 words for competitive Google ranking.' : 'Comprehensive article length achieved.',
                        headings < 3 ? 'Add more H2 and H3 subheadings to structure your article cleanly.' : 'Solid heading hierarchy present.',
                        density < 0.5 ? 'Increase focus keyword frequency naturally throughout introductory and concluding sections.' : 'Healthy keyword density maintained.'
                    ]
                };
            } finally {
                this.seoForm.isAnalyzing = false;
            }
        },

        // 4. Task Studio
        async createTaskStudio() {
            if (!this.taskForm.title || !this.taskForm.board_list_id) {
                alert('Please select a target board list and enter a task title.');
                return;
            }

            this.taskForm.isCreating = true;
            this.taskForm.feedbackMsg = null;

            try {
                const prompt = `Please create a task card titled "${this.taskForm.title}" in board list #${this.taskForm.board_list_id}. Description: "${this.taskForm.description || ''}". Due date: "${this.taskForm.due_date || ''}".`;
                const res = await fetch('/ai/chat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ message: prompt, mode: 'task' })
                });

                const data = await res.json();
                this.taskForm.feedbackMsg = 'Task card created successfully in board!';
                this.taskForm.title = '';
                this.taskForm.description = '';
            } catch (err) {
                this.taskForm.feedbackMsg = 'Error creating task card.';
            } finally {
                this.taskForm.isCreating = false;
            }
        },

        // 5. Website QC Studio
        async submitQcStudio() {
            this.qcForm.isSubmitting = true;
            this.qcForm.feedbackMsg = null;

            try {
                const prompt = `Record website QC audit results for website #${this.qcForm.website_id}. Checklist items checked: ${this.qcForm.checklist.filter(c => c.checked).map(c => c.label).join(', ')}. Feedback notes: "${this.qcForm.notes || 'Routine check completed.'}".`;
                const res = await fetch('/ai/chat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ message: prompt, mode: 'qc' })
                });

                const data = await res.json();
                this.qcForm.feedbackMsg = 'Website QC check recorded and feedback logged!';
                this.qcForm.notes = '';
            } catch (err) {
                this.qcForm.feedbackMsg = 'Failed to submit QC audit.';
            } finally {
                this.qcForm.isSubmitting = false;
            }
        },

        // 6. Report Studio
        async generateReportStudio() {
            this.reportForm.isGenerating = true;
            this.errorNotice = null;

            try {
                const prompt = `Generate an executive production report titled "${this.reportForm.title}" from ${this.reportForm.date_from} to ${this.reportForm.date_to}. Category: ${this.reportForm.category}. Instructions: "${this.reportForm.instructions || 'Include total blogs, tasks, QC status, and active websites.'}". Compile PDF and CSV downloads.`;
                const res = await fetch('/ai/chat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ message: prompt, mode: 'report' })
                });

                const data = await res.json();
                if (res.ok && data.message) {
                    this.loadStudioData();
                    this.reportForm.latestReport = data.message;
                }
            } catch (err) {
                this.errorNotice = 'Error generating executive report.';
            } finally {
                this.reportForm.isGenerating = false;
            }
        },

        // 7. Google AI Studio Voice Generator & Voice-Over Studio
        getFilteredGoogleVoices() {
            let list = this.googleVoices || [];
            if (this.voiceForm.categoryFilter && this.voiceForm.categoryFilter !== 'all') {
                list = list.filter(v => v.category === this.voiceForm.categoryFilter);
            }
            if (this.voiceForm.searchVoice && this.voiceForm.searchVoice.trim()) {
                const q = this.voiceForm.searchVoice.trim().toLowerCase();
                list = list.filter(v =>
                    v.name.toLowerCase().includes(q) ||
                    (v.badge && v.badge.toLowerCase().includes(q)) ||
                    (v.description && v.description.toLowerCase().includes(q))
                );
            }
            return list;
        },

        applyVoiceTonePreset(preset) {
            this.voiceForm.style = preset.id;
            this.voiceForm.custom_tone = preset.desc;
        },

        insertVocalExpression(tag) {
            const el = document.getElementById('kiuq-voice-script-input');
            const insertText = `<${tag}> `;
            if (el) {
                const start = el.selectionStart || 0;
                const end = el.selectionEnd || 0;
                const text = this.voiceForm.script || '';
                this.voiceForm.script = text.substring(0, start) + insertText + text.substring(end);
                this.$nextTick(() => {
                    el.focus();
                    el.setSelectionRange(start + insertText.length, start + insertText.length);
                });
            } else {
                this.voiceForm.script = (this.voiceForm.script ? this.voiceForm.script + ' ' : '') + insertText;
            }
        },

        async generateVoiceStudio() {
            const script = this.voiceForm.script.trim();
            if (!script) {
                alert('Please enter a voice-over narration script.');
                return;
            }

            this.voiceForm.isSynthesizing = true;
            this.errorNotice = null;

            try {
                const res = await fetch('/ai/voice/generate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        script: script,
                        voice: this.voiceForm.voice,
                        style: this.voiceForm.style,
                        custom_tone: this.voiceForm.custom_tone ? this.voiceForm.custom_tone.trim() : null,
                        speed: this.voiceForm.speed,
                        pitch: this.voiceForm.pitch || 0.0
                    })
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.voiceForm.currentAudio = data.voice_over;
                    this.studioData.voices.unshift(data.voice_over);
                    this.$nextTick(() => {
                        const player = document.getElementById('kiuq-studio-audio-player');
                        if (player) {
                            player.load();
                            player.play().catch(() => {});
                        }
                    });
                } else {
                    this.errorNotice = data.error || 'Failed to synthesize voice-over.';
                }
            } catch (err) {
                this.errorNotice = 'Voice generation connection error.';
            } finally {
                this.voiceForm.isSynthesizing = false;
            }
        },

        /**
         * Synthesize voice for a specific chat message with 1 click
         */
        async generateVoiceForMessage(msg) {
            if (!msg || !msg.content || msg.isGeneratingVoice) return;

            // If message already has an audio attachment, toggle playback
            if (msg.attachments && msg.attachments.some(a => a.type === 'audio')) {
                const audioAtt = msg.attachments.find(a => a.type === 'audio');
                const audEl = document.querySelector(`audio[src="${audioAtt.url}"]`);
                if (audEl) {
                    if (audEl.paused) {
                        audEl.play().catch(() => {});
                    } else {
                        audEl.pause();
                    }
                    return;
                }
            }

            msg.isGeneratingVoice = true;
            try {
                const res = await fetch(`/ai/messages/${msg.id}/voice`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        voice: this.voiceForm.voice || 'Charon',
                        style: this.voiceForm.style || 'cinematic',
                        custom_tone: this.voiceForm.custom_tone ? this.voiceForm.custom_tone.trim() : null,
                        speed: this.voiceForm.speed || 1.0
                    })
                });
                const data = await res.json();
                if (res.ok && data.success && data.voice_over) {
                    if (!msg.attachments) msg.attachments = [];
                    msg.attachments.push({
                        type: 'audio',
                        url: data.voice_over.audio_url,
                        voice: data.voice_over.voice_name,
                        duration: data.voice_over.duration,
                        title: data.voice_over.title
                    });
                    this.studioData.voices.unshift(data.voice_over);
                    this.$nextTick(() => {
                        const audEl = document.querySelector(`audio[src="${data.voice_over.audio_url}"]`);
                        if (audEl) audEl.play().catch(() => {});
                    });
                } else {
                    alert(data.error || 'Could not synthesize voice for this message.');
                }
            } catch (err) {
                console.error('generateVoiceForMessage error:', err);
                alert('Voice synthesis network error.');
            } finally {
                msg.isGeneratingVoice = false;
            }
        },

        /**
         * Quick preview audio timbre for each voice character
         */
        playVoiceSample(v) {
            if (this.voiceForm.playingSample === v.id) {
                if (this.currentSampleAudio) {
                    this.currentSampleAudio.pause();
                    this.currentSampleAudio.currentTime = 0;
                }
                if (window.speechSynthesis) window.speechSynthesis.cancel();
                this.voiceForm.playingSample = null;
                return;
            }

            if (this.currentSampleAudio) {
                this.currentSampleAudio.pause();
                this.currentSampleAudio.currentTime = 0;
            }
            if (window.speechSynthesis) window.speechSynthesis.cancel();

            this.voiceForm.playingSample = v.id;
            this.voiceForm.voice = v.id;

            // Play authentic pre-rendered high-fidelity audio sample from Google AI Studio
            const audioUrl = `/audio/samples/sample_${v.id}.wav`;
            const aud = new Audio(audioUrl);
            this.currentSampleAudio = aud;

            aud.onended = () => {
                if (this.voiceForm.playingSample === v.id) {
                    this.voiceForm.playingSample = null;
                }
            };
            aud.onerror = () => {
                // If sample audio file fails to load, gracefully fallback to browser speech synthesis
                if ('speechSynthesis' in window) {
                    const utter = new SpeechSynthesisUtterance(v.sampleText || 'Sample voice preview.');
                    utter.rate = this.voiceForm.speed || 1.0;
                    if (v.id.includes('Khmer') || v.id.includes('km')) {
                        utter.lang = 'km-KH';
                    } else {
                        utter.lang = 'en-US';
                        utter.pitch = v.id === 'Charon' || v.id === 'Fenrir' ? 0.75 : (v.gender === 'female' ? 1.25 : 1.0);
                    }
                    utter.onend = () => { this.voiceForm.playingSample = null; };
                    utter.onerror = () => { this.voiceForm.playingSample = null; };
                    window.speechSynthesis.speak(utter);
                } else {
                    this.voiceForm.playingSample = null;
                }
            };

            aud.play().catch(e => {
                console.warn('Sample audio play blocked or error:', e);
                this.voiceForm.playingSample = null;
            });
        },

        /**
         * Insert quick script template
         */
        loadVoiceScriptTemplate(type) {
            if (type === 'khmer_cinematic') {
                this.voiceForm.voice = 'Khmer-Male';
                this.voiceForm.style = 'cinematic';
                this.voiceForm.speed = 0.85;
                this.voiceForm.script = 'សួស្តី! ខ្ញុំឈ្មោះ ដារ៉ា។ ស្វាគមន៍មកកាន់ទីក្រុងញូវយ៉ក។ ទីក្រុងដែលមិនដែលដេកលក់ ប៉ុន្តែបេះដូង និងការងាររបស់ខ្ញុំ គឺនៅតែភ្ជាប់ជានិច្ចជាមួយអ្នក។ ភាពច្នៃប្រឌិត និងភាពជាក់លាក់ គឺជាអនាគតរបស់យើង។';
            } else if (type === 'typhon_promo') {
                this.voiceForm.voice = 'Charon';
                this.voiceForm.style = 'cinematic';
                this.voiceForm.speed = 0.9;
                this.voiceForm.script = 'Discover unmatched precision with the all-new TYPHON mini excavator. Engineered for relentless power, built for maximum reliability, and designed to conquer every job site. Experience peak performance today.';
            } else if (type === 'commercial_puck') {
                this.voiceForm.voice = 'Puck';
                this.voiceForm.style = 'energetic';
                this.voiceForm.speed = 1.05;
                this.voiceForm.script = 'Looking to elevate your workflow and achieve more in less time? Welcome to DGT System — where intelligent task management meets real-time collaboration. Get started now and feel the difference.';
            } else if (type === 'khmer_natural') {
                this.voiceForm.voice = 'Khmer-Female';
                this.voiceForm.style = 'khmer_sweet';
                this.voiceForm.custom_tone = 'sweet, polite, gentle, melodious native Khmer delivery';
                this.voiceForm.speed = 1.0;
                this.voiceForm.script = 'សូមស្វាគមន៍មកកាន់ប្រព័ន្ធគ្រប់គ្រងការងារ DGT System! យើងខ្ញុំរីករាយក្នុងការជួយសម្រួលដល់ដំណើរការការងារ និងការគ្រប់គ្រងគម្រោងរបស់អ្នកឱ្យកាន់តែមានប្រសិទ្ធភាពខ្ពស់។';
            } else if (type === 'whisper_story') {
                this.voiceForm.voice = 'Tavi';
                this.voiceForm.style = 'whisper';
                this.voiceForm.custom_tone = 'whisper quietly, soft breathy delivery, intimate tone';
                this.voiceForm.speed = 0.9;
                this.voiceForm.script = 'Listen closely... <short pause> deep within the silent corridors, the secret of true innovation was quietly unfolding.';
            } else if (type === 'commercial_koda') {
                this.voiceForm.voice = 'Koda';
                this.voiceForm.style = 'promote';
                this.voiceForm.custom_tone = 'High energy, elongated vowels, upbeat commercial promotion';
                this.voiceForm.speed = 1.05;
                this.voiceForm.script = 'Ready to supercharge your team? Discover the ultimate productivity engine with DGT System today!';
            }
        },

        useAsVoiceScript(text) {
            let clean = (text || '').trim();
            if (clean.includes('[Voice-Over Script]')) {
                const parts = clean.split('[Voice-Over Script]');
                clean = parts[1] || parts[0];
            }
            clean = clean.replace(/["“”'']/g, '').trim();
            this.voiceForm.script = clean;
            this.setMode('voice');
        },

        // ── Helpers ────────────────────────────────────────────────────────────
        focusInput() {
            const el = this.$refs.composerInput;
            if (el) el.focus();
        },

        scrollToBottom() {
            const container = this.$refs.messagesFeed;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },

        formatDate(iso) {
            if (!iso) return '';
            const d = new Date(iso);
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
    }));
});
