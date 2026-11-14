/**
 * KIUQ AI — Multi-Language Translation Studio Mixin
 * Provides reactive state & methods for the dedicated Translation Studio inside KIUQ AI.
 */
window.kiuqTranslationStudioMixin = function () {
    const FALLBACK_LANGUAGES = [
        { code: 'en-US', name: 'English (United States)', native: 'English (US)', flag: '🇺🇸', tts: 'en-us', speech: 'en-US', popular: true, rtl: false },
        { code: 'en-GB', name: 'English (United Kingdom)', native: 'English (UK)', flag: '🇬🇧', tts: 'en-gb', speech: 'en-GB', popular: false, rtl: false },
        { code: 'km', name: 'Khmer', native: 'ភាសាខ្មែរ', flag: '🇰🇭', tts: 'km', speech: 'km-KH', popular: true, rtl: false },
        { code: 'th', name: 'Thai', native: 'ภาษาไทย', flag: '🇹🇭', tts: 'th', speech: 'th-TH', popular: true, rtl: false },
        { code: 'zh-CN', name: 'Chinese (Simplified)', native: '简体中文', flag: '🇨🇳', tts: 'zh-CN', speech: 'zh-CN', popular: true, rtl: false },
        { code: 'zh-TW', name: 'Chinese (Traditional)', native: '繁體中文', flag: '🇹🇼', tts: 'zh-TW', speech: 'zh-TW', popular: true, rtl: false },
        { code: 'ja', name: 'Japanese', native: '日本語', flag: '🇯🇵', tts: 'ja', speech: 'ja-JP', popular: true, rtl: false },
        { code: 'ko', name: 'Korean', native: '한국어', flag: '🇰🇷', tts: 'ko', speech: 'ko-KR', popular: true, rtl: false },
        { code: 'vi', name: 'Vietnamese', native: 'Tiếng Việt', flag: '🇻🇳', tts: 'vi', speech: 'vi-VN', popular: true, rtl: false },
        { code: 'lo', name: 'Lao', native: 'ພາສາລາວ', flag: '🇱🇦', tts: 'lo', speech: 'lo-LA', popular: true, rtl: false },
        { code: 'fr-FR', name: 'French', native: 'Français', flag: '🇫🇷', tts: 'fr', speech: 'fr-FR', popular: true, rtl: false },
        { code: 'de', name: 'German', native: 'Deutsch', flag: '🇩🇪', tts: 'de', speech: 'de-DE', popular: true, rtl: false },
        { code: 'es-ES', name: 'Spanish (Spain)', native: 'Español (España)', flag: '🇪🇸', tts: 'es', speech: 'es-ES', popular: true, rtl: false },
        { code: 'id', name: 'Indonesian', native: 'Bahasa Indonesia', flag: '🇮🇩', tts: 'id', speech: 'id-ID', popular: true, rtl: false },
        { code: 'ms', name: 'Malay', native: 'Bahasa Melayu', flag: '🇲🇾', tts: 'ms', speech: 'ms-MY', popular: true, rtl: false },
        { code: 'ar', name: 'Arabic', native: 'العربية', flag: '🇸🇦', tts: 'ar', speech: 'ar-SA', popular: false, rtl: true },
        { code: 'ru', name: 'Russian', native: 'Русский', flag: '🇷🇺', tts: 'ru', speech: 'ru-RU', popular: false, rtl: false },
        { code: 'pt-BR', name: 'Portuguese (Brazil)', native: 'Português (Brasil)', flag: '🇧🇷', tts: 'pt-br', speech: 'pt-BR', popular: false, rtl: false },
        { code: 'it', name: 'Italian', native: 'Italiano', flag: '🇮🇹', tts: 'it', speech: 'it-IT', popular: false, rtl: false },
        { code: 'hi', name: 'Hindi', native: 'हिन्दी', flag: '🇮🇳', tts: 'hi', speech: 'hi-IN', popular: false, rtl: false }
    ];

    let recentCodes = ['en-US', 'km', 'zh-CN', 'th'];
    try {
        const savedRecent = JSON.parse(localStorage.getItem('kiuq_translate_recent_langs') || 'null');
        if (Array.isArray(savedRecent) && savedRecent.length > 0) {
            recentCodes = savedRecent.slice(0, 6);
        }
    } catch (e) {}

    return {
        translationModesList: [
            { id: 'standard', label: 'Standard', icon: '✨' },
            { id: 'professional', label: 'Professional', icon: '💼' },
            { id: 'formal', label: 'Formal', icon: '🎩' },
            { id: 'casual', label: 'Casual', icon: '💬' },
            { id: 'technical', label: 'Technical', icon: '⚙️' },
            { id: 'marketing', label: 'Marketing', icon: '🚀' },
            { id: 'academic', label: 'Academic', icon: '🎓' },
            { id: 'literal', label: 'Literal', icon: '📏' },
        ],

        translator: {
            languages: FALLBACK_LANGUAGES,
            recentCodes: recentCodes,
            sourceLang: localStorage.getItem('kiuq_translate_source_lang') || 'auto',
            targetLang: localStorage.getItem('kiuq_translate_target_lang') || 'km',
            mode: localStorage.getItem('kiuq_translate_mode') || 'standard',
            sourceText: '',
            translatedText: '',
            detectedCode: null,
            detectedName: null,
            detectedFlag: null,
            confidence: null,
            isTranslating: false,
            isOcrLoading: false,
            isDraggingImage: false,
            ocrFile: null,
            ocrFileName: '',
            ocrPreviewUrl: null,
            sourceDropdownOpen: false,
            targetDropdownOpen: false,
            sourceSearch: '',
            targetSearch: '',
            swapAnimating: false,
            advancedOpen: false,
            advanced: {
                tone: '',
                audience: '',
                variant: '',
                glossary: '',
                notes: '',
            },
            historyOpen: false,
            historySearch: '',
            historyFavoritesOnly: false,
            history: [],
            copiedSide: null,
            fullscreenResultOpen: false,
            isRecording: false,
            isTranscribing: false,
            ttsPlayingSide: null,
            error: null,
            _autoTranslateTimer: null,
            _abortController: null,
            _lastRequestId: 0,
            _detectTimer: null,
            _recognition: null,
            _mediaRecorder: null,
            _mediaChunks: [],
            _ttsAudio: null,
        },

        initTranslationStudio() {
            if (this.studioData && Array.isArray(this.studioData.translate_languages) && this.studioData.translate_languages.length > 0) {
                this.translator.languages = this.studioData.translate_languages;
            } else {
                fetch('/ai/translate/languages', { headers: { 'Accept': 'application/json' } })
                    .then(r => r.ok ? r.json() : null)
                    .then(d => {
                        if (d && Array.isArray(d.languages) && d.languages.length > 0) {
                            this.translator.languages = d.languages;
                        }
                    })
                    .catch(() => {});
            }
            this.loadTranslationHistory();

            // Auto-translate if text is present and not yet translated
            if (this.translator.sourceText && this.translator.sourceText.trim() && !this.translator.translatedText) {
                this.$nextTick(() => this.runTranslation());
            }
        },

        findTranslatorLang(code, allowAuto = true) {
            if (allowAuto && code === 'auto') {
                return { code: 'auto', name: 'Detect Language', native: 'Auto-Detect', flag: '✨', speech: 'en-US', rtl: false };
            }
            const norm = String(code || '').toLowerCase();
            const exact = this.translator.languages.find(l => l.code.toLowerCase() === norm);
            if (exact) return exact;
            const prefix = this.translator.languages.find(l => l.code.toLowerCase().startsWith(norm + '-'));
            return prefix || { code: code || 'en-US', name: code || 'English', native: code || 'English', flag: '🌐', speech: 'en-US', rtl: false };
        },

        getLanguageFlag(code, allowAuto = true) {
            return this.findTranslatorLang(code, allowAuto).flag || '🌐';
        },

        getLanguageName(code, allowAuto = true) {
            return this.findTranslatorLang(code, allowAuto).name || code;
        },

        getLanguageNative(code, allowAuto = true) {
            return this.findTranslatorLang(code, allowAuto).native || '';
        },

        isLanguageRtl(code) {
            if (!code || code === 'auto') return false;
            return Boolean(this.findTranslatorLang(code, false).rtl);
        },

        openLanguageDropdown(side) {
            if (side === 'source') {
                this.translator.targetDropdownOpen = false;
                this.translator.sourceDropdownOpen = !this.translator.sourceDropdownOpen;
                this.translator.sourceSearch = '';
                if (this.translator.sourceDropdownOpen) {
                    this.$nextTick(() => this.$refs.sourceLangSearch?.focus());
                }
            } else {
                this.translator.sourceDropdownOpen = false;
                this.translator.targetDropdownOpen = !this.translator.targetDropdownOpen;
                this.translator.targetSearch = '';
                if (this.translator.targetDropdownOpen) {
                    this.$nextTick(() => this.$refs.targetLangSearch?.focus());
                }
            }
        },

        getGroupedLanguages(side) {
            const query = (side === 'source' ? this.translator.sourceSearch : this.translator.targetSearch).trim().toLowerCase();
            const all = this.translator.languages || [];

            if (query) {
                const filtered = all.filter(l =>
                    l.name.toLowerCase().includes(query) ||
                    (l.native && l.native.toLowerCase().includes(query)) ||
                    l.code.toLowerCase().includes(query)
                );
                return filtered.length ? [{ title: 'Matching Languages', items: filtered }] : [];
            }

            const recent = this.translator.recentCodes
                .map(c => all.find(l => l.code === c))
                .filter(Boolean);
            const popular = all.filter(l => l.popular);
            return [
                ...(recent.length ? [{ title: 'Recently Used', items: recent }] : []),
                { title: 'Popular Languages', items: popular },
                { title: 'All Languages', items: all },
            ];
        },

        rememberRecentLanguage(code) {
            if (!code || code === 'auto') return;
            const next = [code, ...this.translator.recentCodes.filter(c => c !== code)].slice(0, 6);
            this.translator.recentCodes = next;
            try {
                localStorage.setItem('kiuq_translate_recent_langs', JSON.stringify(next));
            } catch (e) {}
        },

        selectTranslationLanguage(side, code) {
            if (side === 'source') {
                this.translator.sourceLang = code;
                this.translator.sourceDropdownOpen = false;
                try { localStorage.setItem('kiuq_translate_source_lang', code); } catch (e) {}
                if (code !== 'auto') {
                    this.rememberRecentLanguage(code);
                    if (code === this.translator.targetLang) {
                        this.translator.targetLang = code === 'en-US' ? 'km' : 'en-US';
                    }
                }
            } else {
                if (code === 'auto') return;
                this.translator.targetLang = code;
                this.translator.targetDropdownOpen = false;
                try { localStorage.setItem('kiuq_translate_target_lang', code); } catch (e) {}
                this.rememberRecentLanguage(code);
            }
            if (this.translator.sourceText.trim()) {
                this.runTranslation();
            }
        },

        applyQuickLanguagePair(src, tgt) {
            this.translator.sourceLang = src;
            this.translator.targetLang = tgt;
            try {
                localStorage.setItem('kiuq_translate_source_lang', src);
                localStorage.setItem('kiuq_translate_target_lang', tgt);
            } catch (e) {}
            this.$nextTick(() => {
                this.$refs.translatorSourceInput?.focus();
                if (this.translator.sourceText && this.translator.sourceText.trim()) {
                    this.runTranslation();
                }
            });
        },

        swapTranslationLanguages() {
            const effectiveSource = this.translator.sourceLang === 'auto'
                ? this.translator.detectedCode
                : this.translator.sourceLang;

            if (!effectiveSource || effectiveSource === 'auto') {
                this.translator.sourceLang = this.translator.targetLang;
                this.translator.targetLang = this.translator.sourceLang === 'en-US' ? 'km' : 'en-US';
            } else {
                const prevTarget = this.translator.targetLang;
                this.translator.sourceLang = prevTarget;
                this.translator.targetLang = effectiveSource;
            }

            this.translator.swapAnimating = true;
            setTimeout(() => { this.translator.swapAnimating = false; }, 320);

            try {
                localStorage.setItem('kiuq_translate_source_lang', this.translator.sourceLang);
                localStorage.setItem('kiuq_translate_target_lang', this.translator.targetLang);
            } catch (e) {}

            if (this.translator.translatedText && this.translator.translatedText.trim()) {
                this.translator.sourceText = this.translator.translatedText;
                this.translator.translatedText = '';
                this.runTranslation();
            } else if (this.translator.sourceText && this.translator.sourceText.trim()) {
                this.runTranslation();
            }
        },

        setTranslationMode(modeId) {
            this.translator.mode = modeId;
            try { localStorage.setItem('kiuq_translate_mode', modeId); } catch (e) {}
            if (this.translator.sourceText && this.translator.sourceText.trim()) {
                this.runTranslation();
            }
        },

        hasActiveAdvancedTranslationOptions() {
            const a = this.translator.advanced;
            return Boolean((a.tone || a.audience || a.variant || a.glossary || a.notes).trim());
        },

        resetAdvancedTranslationOptions() {
            this.translator.advanced = { tone: '', audience: '', variant: '', glossary: '', notes: '' };
            if (this.translator.sourceText && this.translator.sourceText.trim()) {
                this.runTranslation();
            }
        },

        onTranslatorSourceInput(immediate = false) {
            this.translator.error = null;
            const text = this.translator.sourceText ? this.translator.sourceText.trim() : '';

            // Clear any pending debounced auto-translate timer
            if (this.translator._autoTranslateTimer) {
                clearTimeout(this.translator._autoTranslateTimer);
                this.translator._autoTranslateTimer = null;
            }

            // If empty, reset output & metadata immediately and abort pending in-flight request
            if (!text) {
                if (this.translator._abortController) {
                    try { this.translator._abortController.abort(); } catch (e) {}
                    this.translator._abortController = null;
                }
                this.translator.translatedText = '';
                this.translator.detectedCode = null;
                this.translator.detectedName = null;
                this.translator.detectedFlag = null;
                this.translator.confidence = null;
                this.translator.isTranslating = false;
                return;
            }

            // Quick live language detection if source is set to 'auto'
            if (this.translator.sourceLang === 'auto') {
                if (this.translator._detectTimer) clearTimeout(this.translator._detectTimer);
                this.translator._detectTimer = setTimeout(() => {
                    this.detectSourceLanguageLive();
                }, 220);
            }

            // Debounce auto-translation:
            // - Immediate (paste/voice/shortcut): 50ms
            // - Short typing (< 4 chars): 600ms
            // - Standard typing: 450ms
            const delay = immediate ? 50 : (text.length < 4 ? 600 : 450);

            this.translator._autoTranslateTimer = setTimeout(() => {
                this.runTranslation();
            }, delay);
        },

        async detectSourceLanguageLive() {
            const sample = this.translator.sourceText.trim();
            if (!sample || this.translator.sourceLang !== 'auto') return;
            try {
                const res = await fetch('/ai/translate/detect', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ text: sample.slice(0, 1200) }),
                });
                if (!res.ok) return;
                const data = await res.json();
                if (data && data.detection) {
                    this.translator.detectedCode = data.detection.detected_code || null;
                    this.translator.detectedName = data.detection.detected_name || null;
                    this.translator.detectedFlag = data.detection.flag || null;
                    this.translator.confidence = data.detection.confidence || null;
                }
            } catch (e) {}
        },

        async runTranslation() {
            const text = this.translator.sourceText ? this.translator.sourceText.trim() : '';
            if (!text) {
                this.translator.translatedText = '';
                this.translator.isTranslating = false;
                return;
            }

            // Cancel any pending debounced auto-translate timer
            if (this.translator._autoTranslateTimer) {
                clearTimeout(this.translator._autoTranslateTimer);
                this.translator._autoTranslateTimer = null;
            }

            // Abort previous in-flight request if still running
            if (this.translator._abortController) {
                try { this.translator._abortController.abort(); } catch (e) {}
                this.translator._abortController = null;
            }
            const controller = new AbortController();
            this.translator._abortController = controller;

            const requestId = ++(this.translator._lastRequestId || (this.translator._lastRequestId = 0));

            this.translator.isTranslating = true;
            this.translator.error = null;
            this.stopTranslationTts();

            try {
                const res = await fetch('/ai/translate', {
                    method: 'POST',
                    signal: controller.signal,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        text: text,
                        source_language: this.translator.sourceLang,
                        target_language: this.translator.targetLang,
                        mode: this.translator.mode,
                        advanced_options: this.translator.advanced,
                    }),
                });

                // If a newer request was dispatched while this was in-flight, discard this result
                if (requestId !== this.translator._lastRequestId) return;

                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw new Error(data.error || data.message || 'Translation failed. Please try again.');
                }

                this.translator.translatedText = data.translated_text || '';
                if (data.detected_language) {
                    this.translator.detectedCode = data.detected_language;
                    this.translator.detectedName = data.detected_name || data.detected_language;
                    this.translator.detectedFlag = data.detected_flag || '✨';
                    this.translator.confidence = data.confidence || 'high';
                }
                if (data.history_item) {
                    this.translator.history = [
                        data.history_item,
                        ...this.translator.history.filter(h => h.id !== data.history_item.id),
                    ].slice(0, 60);
                }
            } catch (err) {
                if (err.name === 'AbortError') return;

                if (requestId === this.translator._lastRequestId) {
                    this.translator.error = err.message || 'Network error while translating.';
                }
            } finally {
                if (requestId === this.translator._lastRequestId) {
                    this.translator.isTranslating = false;
                }
            }
        },

        // ── Multimodal Image OCR Upload / Drag-Drop / Clipboard Paste ──────────
        handleTranslationImageSelect(event) {
            const file = event.target.files?.[0];
            if (file) this.attachOcrImageFile(file);
            if (event.target) event.target.value = '';
        },

        handleTranslationDrop(event) {
            this.translator.isDraggingImage = false;
            const file = event.dataTransfer?.files?.[0];
            if (file && file.type.startsWith('image/')) {
                this.attachOcrImageFile(file);
            }
        },

        handleTranslationPaste(event) {
            const items = event.clipboardData?.items;
            if (!items) return;
            for (const item of items) {
                if (item.type && item.type.startsWith('image/')) {
                    const file = item.getAsFile();
                    if (file) {
                        event.preventDefault();
                        this.attachOcrImageFile(file);
                        return;
                    }
                }
            }
        },

        attachOcrImageFile(file) {
            if (!['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type)) {
                this.translator.error = 'Unsupported image format. Please upload PNG, JPG, WEBP, or GIF.';
                return;
            }
            if (file.size > 8 * 1024 * 1024) {
                this.translator.error = 'Image exceeds the 8 MB limit.';
                return;
            }
            this.removeOcrImage();
            this.translator.ocrFile = file;
            this.translator.ocrFileName = file.name || 'clipboard-screenshot.png';
            this.translator.ocrPreviewUrl = URL.createObjectURL(file);
            this.translator.error = null;
        },

        removeOcrImage() {
            if (this.translator.ocrPreviewUrl) {
                try { URL.revokeObjectURL(this.translator.ocrPreviewUrl); } catch (e) {}
            }
            this.translator.ocrFile = null;
            this.translator.ocrFileName = '';
            this.translator.ocrPreviewUrl = null;
        },

        async runImageOcrTranslation() {
            if (!this.translator.ocrFile) return;
            this.translator.isOcrLoading = true;
            this.translator.error = null;

            try {
                const formData = new FormData();
                formData.append('image', this.translator.ocrFile);
                formData.append('source_language', this.translator.sourceLang);
                formData.append('target_language', this.translator.targetLang);
                formData.append('mode', this.translator.mode);
                formData.append('advanced_options', JSON.stringify(this.translator.advanced));

                const res = await fetch('/ai/translate/ocr', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw new Error(data.error || data.message || 'Could not extract text from image.');
                }

                this.translator.sourceText = data.extracted_text || '';
                this.translator.translatedText = data.translated_text || '';
                if (data.detected_language) {
                    this.translator.detectedCode = data.detected_language;
                    this.translator.detectedName = data.detected_name || data.detected_language;
                    this.translator.detectedFlag = data.detected_flag || '✨';
                }
                if (data.history_item) {
                    this.translator.history = [
                        data.history_item,
                        ...this.translator.history.filter(h => h.id !== data.history_item.id),
                    ].slice(0, 60);
                }
            } catch (err) {
                this.translator.error = err.message || 'Image OCR failed.';
            } finally {
                this.translator.isOcrLoading = false;
            }
        },

        // ── Voice Input (Web Speech API + MediaRecorder Server Fallback) ───────
        async toggleTranslationVoiceInput() {
            if (this.translator.isRecording) {
                this.stopTranslationVoiceInput();
                return;
            }

            this.translator.error = null;
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            const isFlutterWebView = Boolean(window.flutter_inappwebview);

            if (SpeechRecognition && !isFlutterWebView) {
                try {
                    const recognition = new SpeechRecognition();
                    const langMeta = this.findTranslatorLang(
                        this.translator.sourceLang === 'auto' ? 'en-US' : this.translator.sourceLang,
                        false
                    );
                    recognition.lang = langMeta.speech || 'en-US';
                    recognition.interimResults = true;
                    recognition.continuous = false;

                    const baseText = this.translator.sourceText ? this.translator.sourceText.trimEnd() + ' ' : '';
                    recognition.onstart = () => {
                        this.translator.isRecording = true;
                    };
                    recognition.onresult = (event) => {
                        let transcript = '';
                        for (let i = 0; i < event.results.length; i++) {
                            transcript += event.results[i][0].transcript;
                        }
                        this.translator.sourceText = baseText + transcript;
                    };
                    recognition.onerror = (event) => {
                        this.translator.isRecording = false;
                        if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
                            this.translator.error = 'Microphone permission was denied. Please enable microphone access in your browser or device settings.';
                        } else if (event.error !== 'aborted') {
                            this.startMediaRecorderVoiceFallback();
                        }
                    };
                    recognition.onend = () => {
                        this.translator.isRecording = false;
                        if (this.translator.sourceText.trim()) {
                            this.onTranslatorSourceInput();
                            this.runTranslation();
                        }
                    };
                    this.translator._recognition = recognition;
                    recognition.start();
                    return;
                } catch (e) {}
            }

            await this.startMediaRecorderVoiceFallback();
        },

        async startMediaRecorderVoiceFallback() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.translator.error = 'Voice input is not supported in this browser context.';
                return;
            }
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                const mimeType = MediaRecorder.isTypeSupported('audio/webm')
                    ? 'audio/webm'
                    : (MediaRecorder.isTypeSupported('audio/mp4') ? 'audio/mp4' : '');
                const recorder = mimeType ? new MediaRecorder(stream, { mimeType }) : new MediaRecorder(stream);
                this.translator._mediaChunks = [];
                recorder.ondataavailable = (e) => {
                    if (e.data && e.data.size > 0) this.translator._mediaChunks.push(e.data);
                };
                recorder.onstop = async () => {
                    stream.getTracks().forEach(t => t.stop());
                    this.translator.isRecording = false;
                    if (!this.translator._mediaChunks.length) return;
                    const ext = (recorder.mimeType || '').includes('mp4') ? 'm4a' : 'webm';
                    const blob = new Blob(this.translator._mediaChunks, { type: recorder.mimeType || 'audio/webm' });
                    await this.uploadVoiceInputAudio(blob, `dictation.${ext}`);
                };
                this.translator._mediaRecorder = recorder;
                this.translator.isRecording = true;
                recorder.start();
            } catch (err) {
                this.translator.isRecording = false;
                this.translator.error = 'Microphone access denied or unavailable. Please check device permissions.';
            }
        },

        stopTranslationVoiceInput() {
            if (this.translator._recognition) {
                try { this.translator._recognition.stop(); } catch (e) {}
                this.translator._recognition = null;
            }
            if (this.translator._mediaRecorder && this.translator._mediaRecorder.state !== 'inactive') {
                try { this.translator._mediaRecorder.stop(); } catch (e) {}
            }
            this.translator.isRecording = false;
        },

        async uploadVoiceInputAudio(blob, filename) {
            this.translator.isTranscribing = true;
            try {
                const fd = new FormData();
                fd.append('audio', blob, filename);
                if (this.translator.sourceLang !== 'auto') {
                    fd.append('language_hint', this.translator.sourceLang);
                }
                const res = await fetch('/ai/translate/voice-input', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: fd,
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw new Error(data.error || 'Speech transcription failed.');
                }
                if (data.text) {
                    this.translator.sourceText = (this.translator.sourceText ? this.translator.sourceText.trimEnd() + ' ' : '') + data.text;
                    this.onTranslatorSourceInput();
                    this.runTranslation();
                }
            } catch (err) {
                this.translator.error = err.message || 'Could not transcribe voice input.';
            } finally {
                this.translator.isTranscribing = false;
            }
        },

        // ── Audio Playback (TTS) ───────────────────────────────────────────────
        stopTranslationTts() {
            if (this.translator._ttsAudio) {
                try {
                    this.translator._ttsAudio.pause();
                    this.translator._ttsAudio.currentTime = 0;
                } catch (e) {}
                this.translator._ttsAudio = null;
            }
            if ('speechSynthesis' in window) {
                try { window.speechSynthesis.cancel(); } catch (e) {}
            }
            this.translator.ttsPlayingSide = null;
        },

        async toggleTranslationTts(side) {
            if (this.translator.ttsPlayingSide === side) {
                this.stopTranslationTts();
                return;
            }

            const text = side === 'source' ? this.translator.sourceText.trim() : this.translator.translatedText.trim();
            if (!text) return;

            const langCode = side === 'source'
                ? (this.translator.sourceLang === 'auto' ? (this.translator.detectedCode || 'en-US') : this.translator.sourceLang)
                : this.translator.targetLang;

            this.stopTranslationTts();
            this.translator.ttsPlayingSide = side;

            try {
                const res = await fetch('/ai/translate/tts', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ text: text.slice(0, 4800), language: langCode }),
                });
                const data = await res.json();
                if (res.ok && data.success && data.audio_url) {
                    const audio = new Audio(data.audio_url);
                    this.translator._ttsAudio = audio;
                    audio.onended = () => {
                        if (this.translator.ttsPlayingSide === side) {
                            this.translator.ttsPlayingSide = null;
                        }
                    };
                    audio.onerror = () => this.speakFallbackUtterance(text, langCode, side);
                    await audio.play();
                    return;
                }
            } catch (e) {}

            this.speakFallbackUtterance(text, langCode, side);
        },

        speakFallbackUtterance(text, langCode, side) {
            if (!('speechSynthesis' in window)) {
                this.translator.ttsPlayingSide = null;
                this.translator.error = 'Audio playback is unavailable for this language on your device.';
                return;
            }
            const meta = this.findTranslatorLang(langCode, false);
            const utter = new SpeechSynthesisUtterance(text);
            utter.lang = meta.speech || langCode || 'en-US';
            utter.onend = () => { this.translator.ttsPlayingSide = null; };
            utter.onerror = () => { this.translator.ttsPlayingSide = null; };
            window.speechSynthesis.speak(utter);
        },

        // ── Output Actions & History Management ────────────────────────────────
        copyTranslatorText(text, side) {
            if (!text) return;
            kiuqRobustCopy(text).then(() => {
                this.translator.copiedSide = side;
                setTimeout(() => {
                    if (this.translator.copiedSide === side) {
                        this.translator.copiedSide = null;
                    }
                }, 1800);
            }).catch(() => {});
        },

        async pasteIntoTranslator() {
            try {
                if (navigator.clipboard && navigator.clipboard.readText) {
                    const txt = await navigator.clipboard.readText();
                    if (txt) {
                        this.translator.sourceText = txt;
                        this.onTranslatorSourceInput(true);
                    }
                }
            } catch (e) {
                this.$refs.translatorSourceInput?.focus();
            }
        },

        clearTranslationStudio() {
            if (this.translator._autoTranslateTimer) {
                clearTimeout(this.translator._autoTranslateTimer);
                this.translator._autoTranslateTimer = null;
            }
            if (this.translator._abortController) {
                try { this.translator._abortController.abort(); } catch (e) {}
                this.translator._abortController = null;
            }
            this.stopTranslationTts();
            this.stopTranslationVoiceInput();
            this.removeOcrImage();
            this.translator.sourceText = '';
            this.translator.translatedText = '';
            this.translator.detectedCode = null;
            this.translator.detectedName = null;
            this.translator.detectedFlag = null;
            this.translator.error = null;
            this.translator.isTranslating = false;
            this.$nextTick(() => this.$refs.translatorSourceInput?.focus());
        },

        async shareTranslationResult() {
            const text = this.translator.translatedText.trim();
            if (!text) return;
            if (navigator.share) {
                try {
                    await navigator.share({
                        title: `Translation (${this.getLanguageName(this.translator.targetLang, false)})`,
                        text: text,
                    });
                    return;
                } catch (e) {}
            }
            this.copyTranslatorText(text, 'target');
        },

        downloadTranslationTxt() {
            const text = this.translator.translatedText.trim();
            if (!text) return;
            const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `translation-${this.translator.targetLang}-${Date.now()}.txt`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            setTimeout(() => URL.revokeObjectURL(url), 1000);
        },

        toggleTranslationHistory() {
            this.translator.historyOpen = !this.translator.historyOpen;
            if (this.translator.historyOpen) {
                this.loadTranslationHistory();
            }
        },

        async loadTranslationHistory() {
            try {
                const params = new URLSearchParams();
                if (this.translator.historySearch.trim()) {
                    params.set('q', this.translator.historySearch.trim());
                }
                if (this.translator.historyFavoritesOnly) {
                    params.set('favorites', '1');
                }
                const res = await fetch(`/ai/translate/history?${params.toString()}`, {
                    headers: { 'Accept': 'application/json' },
                });
                if (!res.ok) return;
                const data = await res.json();
                if (data && Array.isArray(data.history)) {
                    this.translator.history = data.history;
                }
            } catch (e) {}
        },

        restoreTranslationHistoryItem(item) {
            this.translator.sourceLang = item.source_language || 'auto';
            this.translator.targetLang = item.target_language || 'km';
            this.translator.mode = item.translation_mode || 'standard';
            this.translator.sourceText = item.source_text || '';
            this.translator.translatedText = item.translated_text || '';
            this.translator.detectedCode = item.detected_language || null;
            this.translator.detectedName = item.detected_language ? item.source_label : null;
            this.translator.detectedFlag = item.source_flag || null;
        },

        async toggleFavoriteTranslationItem(item) {
            try {
                const res = await fetch(`/ai/translate/history/${item.id}/favorite`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                    },
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    item.is_favorite = data.is_favorite;
                    if (this.translator.historyFavoritesOnly && !item.is_favorite) {
                        this.translator.history = this.translator.history.filter(h => h.id !== item.id);
                    }
                }
            } catch (e) {}
        },

        async deleteTranslationHistoryItem(id) {
            try {
                const res = await fetch(`/ai/translate/history/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                    },
                });
                if (res.ok) {
                    this.translator.history = this.translator.history.filter(h => h.id !== id);
                }
            } catch (e) {}
        },
    };
};
