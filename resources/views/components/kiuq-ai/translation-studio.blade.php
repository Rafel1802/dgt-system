{{-- ── 1B. MULTI-LANGUAGE TRANSLATION STUDIO ────────────────────────────── --}}
<div x-show="mode === 'translate'"
     x-cloak
     @paste="handleTranslationPaste($event)"
     class="flex-1 flex flex-col min-h-0 overflow-hidden bg-slate-50/70 dark:bg-[#0b101b] relative">

    {{-- Top Studio Controls: Modes, Advanced Options Toggle & History Toggle --}}
    <div class="px-3.5 sm:px-6 py-2.5 border-b border-slate-200/80 dark:border-slate-800/80 bg-white/90 dark:bg-[#0d1424]/90 backdrop-blur-md flex flex-wrap items-center justify-between gap-2 flex-shrink-0">
        {{-- 8 Translation Mode Pills --}}
        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5 max-w-full">
            <template x-for="m in translationModesList" :key="m.id">
                <button type="button"
                        @click="setTranslationMode(m.id)"
                        :class="translator.mode === m.id
                            ? 'bg-emerald-600 text-white font-bold shadow-sm shadow-emerald-500/25 border-emerald-600'
                            : 'bg-slate-100/90 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 hover:bg-slate-200/70 dark:hover:bg-slate-700/70 border-slate-200/70 dark:border-slate-700/70'"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs border transition-all whitespace-nowrap shrink-0 cursor-pointer">
                    <span x-text="m.icon"></span>
                    <span x-text="m.label"></span>
                </button>
            </template>
        </div>

        {{-- Right Actions: Advanced Options & Saved History --}}
        <div class="flex items-center gap-2 ml-auto">
            <button type="button"
                    @click="translator.advancedOpen = !translator.advancedOpen"
                    :class="translator.advancedOpen || hasActiveAdvancedTranslationOptions()
                        ? 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-300 border-indigo-500/30 font-bold'
                        : 'bg-white dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs border transition-all cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                </svg>
                <span>Options</span>
                <span x-show="hasActiveAdvancedTranslationOptions()" class="w-2 h-2 rounded-full bg-indigo-500"></span>
            </button>

            <button type="button"
                    @click="toggleTranslationHistory()"
                    :class="translator.historyOpen
                        ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30 font-bold'
                        : 'bg-white dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs border transition-all cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span>History</span>
                <span x-show="translator.history.length > 0"
                      class="px-1.5 py-0.2 rounded-full bg-slate-200/80 dark:bg-slate-700 text-[10px] font-extrabold"
                      x-text="translator.history.length"></span>
            </button>
        </div>
    </div>

    {{-- Collapsible Advanced Translation Context Drawer --}}
    <div x-show="translator.advancedOpen"
         x-collapse
         class="px-4 sm:px-6 py-3 bg-indigo-50/50 dark:bg-slate-900/80 border-b border-slate-200/80 dark:border-slate-800 flex-shrink-0">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2.5">
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Tone Nuance</label>
                <input type="text"
                       x-model="translator.advanced.tone"
                       @input="onTranslatorSourceInput()"
                       placeholder="e.g. Warm, diplomatic, concise"
                       class="w-full px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
            </div>
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Target Audience</label>
                <input type="text"
                       x-model="translator.advanced.audience"
                       @input="onTranslatorSourceInput()"
                       placeholder="e.g. Executive clients, engineers"
                       class="w-full px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
            </div>
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Regional Variant</label>
                <input type="text"
                       x-model="translator.advanced.variant"
                       @input="onTranslatorSourceInput()"
                       placeholder="e.g. Phnom Penh Khmer, UK English"
                       class="w-full px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
            </div>
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Preferred Terminology</label>
                <input type="text"
                       x-model="translator.advanced.glossary"
                       @input="onTranslatorSourceInput()"
                       placeholder="e.g. Keep brand names untranslated"
                       class="w-full px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
            </div>
            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Extra Instructions</label>
                    <input type="text"
                           x-model="translator.advanced.notes"
                           @input="onTranslatorSourceInput()"
                           placeholder="e.g. Preserve bullet points"
                           class="w-full px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                </div>
                <button type="button"
                        x-show="hasActiveAdvancedTranslationOptions()"
                        @click="resetAdvancedTranslationOptions()"
                        class="px-2.5 py-1.5 rounded-xl text-[11px] font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors">
                    Reset
                </button>
            </div>
        </div>
    </div>

    {{-- Main Scrollable Studio Body --}}
    <div class="flex-1 overflow-y-auto p-3.5 sm:p-6 space-y-4 custom-scrollbar">

        {{-- Error / Permission Feedback Banner --}}
        <div x-show="translator.error"
             x-cloak
             class="flex items-center justify-between gap-3 px-4 py-2.5 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/70 text-rose-700 dark:text-rose-300 text-xs font-semibold">
            <div class="flex items-center gap-2">
                <span>⚠️</span>
                <span x-text="translator.error"></span>
            </div>
            <button type="button" @click="translator.error = null" class="text-rose-500 hover:text-rose-700 font-bold px-1.5">✕</button>
        </div>

        {{-- Language Selector Bar (Source ↔ Swap ↔ Target) --}}
        <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2 sm:gap-3 bg-white dark:bg-[#111827] p-2 sm:p-2.5 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-sm relative z-20">

            {{-- SOURCE LANGUAGE SELECTOR --}}
            <div class="relative min-w-0" @click.outside="translator.sourceDropdownOpen = false">
                <button type="button"
                        @click="openLanguageDropdown('source')"
                        class="w-full flex items-center justify-between gap-2 px-3 py-2 sm:px-3.5 sm:py-2.5 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/70 transition-colors text-left cursor-pointer">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="text-lg sm:text-xl leading-none shrink-0" x-text="getLanguageFlag(translator.sourceLang, true)"></span>
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white truncate" x-text="getLanguageName(translator.sourceLang, true)"></span>
                                <template x-if="translator.sourceLang === 'auto' && translator.detectedName">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/25">
                                        <span x-text="translator.detectedFlag || '✨'"></span>
                                        <span x-text="translator.detectedName"></span>
                                    </span>
                                </template>
                            </div>
                            <p class="text-[10px] text-slate-400 dark:text-slate-500 truncate hidden sm:block" x-text="getLanguageNative(translator.sourceLang, true)"></p>
                        </div>
                    </div>
                    <svg class="w-4 h-4 text-slate-400 shrink-0 transition-transform" :class="translator.sourceDropdownOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                </button>

                {{-- Source Searchable Dropdown --}}
                <div x-show="translator.sourceDropdownOpen"
                     x-transition.opacity.duration.150ms
                     x-cloak
                     class="absolute left-0 top-full mt-2 w-[min(90vw,360px)] rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-2xl overflow-hidden z-40">
                    <div class="p-2.5 border-b border-slate-100 dark:border-slate-800">
                        <input type="search"
                               x-ref="sourceLangSearch"
                               x-model="translator.sourceSearch"
                               placeholder="Search language, native script, or code..."
                               class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/40">
                    </div>
                    <div class="max-h-72 overflow-y-auto p-1.5 space-y-1 custom-scrollbar">
                        {{-- Auto-Detect Option --}}
                        <button type="button"
                                x-show="!translator.sourceSearch.trim() || 'detect language auto'.includes(translator.sourceSearch.trim().toLowerCase())"
                                @click="selectTranslationLanguage('source', 'auto')"
                                :class="translator.sourceLang === 'auto' ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 font-bold' : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'"
                                class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs transition-colors cursor-pointer">
                            <span class="flex items-center gap-2.5">
                                <span class="text-base">✨</span>
                                <span>Detect Language <span class="text-[11px] text-slate-400 font-normal">(Auto)</span></span>
                            </span>
                            <span x-show="translator.sourceLang === 'auto'" class="text-emerald-600 font-black">✓</span>
                        </button>

                        {{-- Filtered Language Sections --}}
                        <template x-for="group in getGroupedLanguages('source')" :key="group.title">
                            <div class="pt-1">
                                <p class="px-3 py-1 text-[10px] font-black uppercase tracking-wider text-slate-400" x-text="group.title"></p>
                                <template x-for="lang in group.items" :key="group.title + '_' + lang.code">
                                    <button type="button"
                                            @click="selectTranslationLanguage('source', lang.code)"
                                            :class="translator.sourceLang === lang.code ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 font-bold' : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'"
                                            class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-xl text-xs transition-colors cursor-pointer">
                                        <div class="flex items-center gap-2.5 min-w-0 text-left">
                                            <span class="text-base shrink-0" x-text="lang.flag"></span>
                                            <div class="min-w-0">
                                                <div class="truncate" x-text="lang.name"></div>
                                                <div class="text-[10px] text-slate-400 truncate" x-text="lang.native"></div>
                                            </div>
                                        </div>
                                        <span class="text-[10px] font-mono uppercase px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-500 shrink-0" x-text="lang.code"></span>
                                    </button>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- CENTER ANIMATED SWAP BUTTON --}}
            <button type="button"
                    @click="swapTranslationLanguages()"
                    :title="translator.sourceLang === 'auto' && !translator.detectedCode ? 'Select or detect a source language to swap' : 'Swap languages & text'"
                    class="w-10 h-10 sm:w-11 sm:h-11 rounded-full bg-slate-100 hover:bg-emerald-500 hover:text-white dark:bg-slate-800 dark:hover:bg-emerald-600 text-slate-600 dark:text-slate-300 flex items-center justify-center transition-all duration-300 shadow-xs active:scale-90 cursor-pointer"
                    :class="translator.swapAnimating ? 'rotate-180 bg-emerald-500 text-white' : ''">
                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                </svg>
            </button>

            {{-- TARGET LANGUAGE SELECTOR --}}
            <div class="relative min-w-0" @click.outside="translator.targetDropdownOpen = false">
                <button type="button"
                        @click="openLanguageDropdown('target')"
                        class="w-full flex items-center justify-between gap-2 px-3 py-2 sm:px-3.5 sm:py-2.5 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/70 transition-colors text-left cursor-pointer">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="text-lg sm:text-xl leading-none shrink-0" x-text="getLanguageFlag(translator.targetLang, false)"></span>
                        <div class="min-w-0">
                            <span class="block text-xs sm:text-sm font-bold text-slate-900 dark:text-white truncate" x-text="getLanguageName(translator.targetLang, false)"></span>
                            <p class="text-[10px] text-slate-400 dark:text-slate-500 truncate hidden sm:block" x-text="getLanguageNative(translator.targetLang, false)"></p>
                        </div>
                    </div>
                    <svg class="w-4 h-4 text-slate-400 shrink-0 transition-transform" :class="translator.targetDropdownOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                </button>

                {{-- Target Searchable Dropdown --}}
                <div x-show="translator.targetDropdownOpen"
                     x-transition.opacity.duration.150ms
                     x-cloak
                     class="absolute right-0 top-full mt-2 w-[min(90vw,360px)] rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-2xl overflow-hidden z-40">
                    <div class="p-2.5 border-b border-slate-100 dark:border-slate-800">
                        <input type="search"
                               x-ref="targetLangSearch"
                               x-model="translator.targetSearch"
                               placeholder="Search target language or code..."
                               class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/40">
                    </div>
                    <div class="max-h-72 overflow-y-auto p-1.5 space-y-1 custom-scrollbar">
                        <template x-for="group in getGroupedLanguages('target')" :key="group.title">
                            <div class="pt-1">
                                <p class="px-3 py-1 text-[10px] font-black uppercase tracking-wider text-slate-400" x-text="group.title"></p>
                                <template x-for="lang in group.items" :key="group.title + '_' + lang.code">
                                    <button type="button"
                                            @click="selectTranslationLanguage('target', lang.code)"
                                            :class="translator.targetLang === lang.code ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 font-bold' : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'"
                                            class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-xl text-xs transition-colors cursor-pointer">
                                        <div class="flex items-center gap-2.5 min-w-0 text-left">
                                            <span class="text-base shrink-0" x-text="lang.flag"></span>
                                            <div class="min-w-0">
                                                <div class="truncate" x-text="lang.name"></div>
                                                <div class="text-[10px] text-slate-400 truncate" x-text="lang.native"></div>
                                            </div>
                                        </div>
                                        <span class="text-[10px] font-mono uppercase px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-500 shrink-0" x-text="lang.code"></span>
                                    </button>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        {{-- Dual Translation Workspace Panels (Side-by-side Desktop / Stacked Mobile) --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-stretch">

            {{-- LEFT: SOURCE INPUT & MULTIMODAL OCR PANEL --}}
            <div class="flex flex-col rounded-3xl bg-white dark:bg-[#111827] border border-slate-200/90 dark:border-slate-800 shadow-sm overflow-hidden min-h-[290px] sm:min-h-[360px]"
                 @dragover.prevent="translator.isDraggingImage = true"
                 @dragleave.prevent="translator.isDraggingImage = false"
                 @drop.prevent="handleTranslationDrop($event)"
                 :class="translator.isDraggingImage ? 'ring-2 ring-emerald-500 border-emerald-500' : ''">

                {{-- Source Top Subheader --}}
                <div class="px-4 py-2.5 border-b border-slate-100 dark:border-slate-800/80 flex items-center justify-between gap-2 text-xs">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="font-bold text-slate-600 dark:text-slate-300">Source Text</span>
                        <template x-if="translator.sourceLang === 'auto' && translator.detectedName && translator.confidence === 'ambiguous'">
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">Short text · Auto</span>
                        </template>
                    </div>
                    <div class="flex items-center gap-1">
                        {{-- Upload Image for OCR --}}
                        <input type="file"
                               x-ref="translateImageInput"
                               accept="image/png,image/jpeg,image/webp,image/gif"
                               class="hidden"
                               @change="handleTranslationImageSelect($event)">
                        <button type="button"
                                @click="$refs.translateImageInput.click()"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                                title="Upload or paste an image to extract & translate text">
                            <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/></svg>
                            <span>Image OCR</span>
                        </button>
                        <button type="button"
                                @click="pasteIntoTranslator()"
                                class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] font-semibold text-slate-500 hover:text-slate-800 dark:hover:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                                title="Paste from clipboard">
                            <span>Paste</span>
                        </button>
                        <button type="button"
                                x-show="translator.sourceText.length > 0 || translator.ocrPreviewUrl"
                                @click="clearTranslationStudio()"
                                class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] font-bold text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition-colors cursor-pointer"
                                title="Clear input">
                            <span>Clear</span>
                        </button>
                    </div>
                </div>

                {{-- Attached OCR Image Preview Card --}}
                <div x-show="translator.ocrPreviewUrl"
                     x-cloak
                     class="mx-4 mt-3 p-3 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/25 border border-emerald-200/80 dark:border-emerald-800/60 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <img :src="translator.ocrPreviewUrl" alt="OCR Preview" class="w-12 h-12 rounded-xl object-cover border border-emerald-300/60 shrink-0">
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-slate-800 dark:text-slate-100 truncate" x-text="translator.ocrFileName || 'Image ready for OCR'"></p>
                            <p class="text-[11px] text-emerald-700 dark:text-emerald-400">Extracts visible text & translates into <span class="font-bold" x-text="getLanguageName(translator.targetLang, false)"></span></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <button type="button"
                                @click="runImageOcrTranslation()"
                                :disabled="translator.isOcrLoading"
                                class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white text-xs font-bold shadow-sm transition-all cursor-pointer">
                            <span x-text="translator.isOcrLoading ? 'Extracting...' : 'Translate Image'"></span>
                        </button>
                        <button type="button"
                                @click="removeOcrImage()"
                                class="w-7 h-7 rounded-full bg-white/80 dark:bg-slate-800 text-slate-500 hover:text-rose-600 flex items-center justify-center text-xs font-bold cursor-pointer"
                                title="Remove image">✕</button>
                    </div>
                </div>

                {{-- Source Textarea --}}
                <div class="flex-1 flex flex-col p-4">
                    <textarea x-ref="translatorSourceInput"
                              x-model="translator.sourceText"
                              @input="onTranslatorSourceInput()"
                              @paste="$nextTick(() => onTranslatorSourceInput(true))"
                              @keydown.meta.enter.prevent="runTranslation()"
                              @keydown.ctrl.enter.prevent="runTranslation()"
                              :dir="isLanguageRtl(translator.detectedCode || translator.sourceLang) ? 'rtl' : 'ltr'"
                              maxlength="12000"
                              placeholder="Type, paste text, dictate by voice, or paste a screenshot (Cmd+V) to translate..."
                              style="font-family: 'Inter', 'Kantumruy Pro', 'Noto Sans Khmer', 'Noto Sans Thai', 'Noto Sans SC', 'Noto Sans JP', 'Noto Naskh Arabic', system-ui, sans-serif;"
                              class="flex-1 w-full resize-none border-0 bg-transparent p-0 text-base sm:text-[17px] leading-relaxed text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-0 min-h-[170px]"></textarea>
                </div>

                {{-- Source Bottom Action Bar --}}
                <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-800/80 bg-slate-50/60 dark:bg-slate-900/40 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-1.5">
                        {{-- Voice Input (Microphone STT) --}}
                        <button type="button"
                                @click="toggleTranslationVoiceInput()"
                                :class="translator.isRecording
                                    ? 'bg-rose-600 text-white ring-4 ring-rose-500/25 animate-pulse'
                                    : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-emerald-600 border border-slate-200 dark:border-slate-700'"
                                class="inline-flex items-center gap-1.5 px-3 h-9 rounded-xl text-xs font-bold transition-all cursor-pointer"
                                :title="translator.isRecording ? 'Stop voice dictation' : 'Speak to input text'">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />
                            </svg>
                            <span x-text="translator.isRecording ? 'Listening...' : (translator.isTranscribing ? 'Transcribing...' : 'Voice')"></span>
                        </button>

                        {{-- Listen to Source Text (TTS) --}}
                        <button type="button"
                                @click="toggleTranslationTts('source')"
                                :disabled="!translator.sourceText.trim()"
                                :class="translator.ttsPlayingSide === 'source'
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-indigo-600 border border-slate-200 dark:border-slate-700 disabled:opacity-40'"
                                class="w-9 h-9 rounded-xl flex items-center justify-center transition-all cursor-pointer"
                                :title="translator.ttsPlayingSide === 'source' ? 'Stop audio' : 'Listen to source text'">
                            <svg x-show="translator.ttsPlayingSide !== 'source'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" />
                            </svg>
                            <svg x-show="translator.ttsPlayingSide === 'source'" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <rect x="6" y="6" width="12" height="12" rx="2" />
                            </svg>
                        </button>

                        {{-- Copy Source Text --}}
                        <button type="button"
                                @click="copyTranslatorText(translator.sourceText, 'source')"
                                :disabled="!translator.sourceText.trim()"
                                class="w-9 h-9 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-slate-900 border border-slate-200 dark:border-slate-700 disabled:opacity-40 flex items-center justify-center transition-all cursor-pointer"
                                title="Copy source text">
                            <span x-show="translator.copiedSide === 'source'" class="text-emerald-600 text-xs font-black">✓</span>
                            <svg x-show="translator.copiedSide !== 'source'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex items-center gap-2 sm:gap-2.5 ml-auto">
                        <span class="text-[11px] font-mono text-slate-400" x-text="`${translator.sourceText.length.toLocaleString()} / 12,000`"></span>

                        {{-- Subtle Auto-Translate indicator --}}
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold transition-all"
                             :class="translator.isTranslating
                                ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30'
                                : (translator.sourceText.trim() ? 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200/60 dark:border-slate-700/60' : 'text-slate-400')">
                            <span class="w-1.5 h-1.5 rounded-full shrink-0"
                                  :class="translator.isTranslating ? 'bg-emerald-500 animate-ping' : (translator.sourceText.trim() ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600')"></span>
                            <span x-text="translator.isTranslating ? 'Translating live...' : '⚡ Auto-translate on'"></span>
                        </div>

                        <button type="button"
                                @click="translator.ocrFile && !translator.sourceText.trim() ? runImageOcrTranslation() : runTranslation()"
                                :disabled="translator.isTranslating || translator.isOcrLoading || (!translator.sourceText.trim() && !translator.ocrFile)"
                                class="inline-flex items-center gap-2 px-4 h-9 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 disabled:opacity-45 text-white text-xs font-extrabold shadow-md shadow-emerald-600/20 active:scale-95 transition-all cursor-pointer">
                            <svg x-show="translator.isTranslating || translator.isOcrLoading" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span x-text="(translator.isTranslating || translator.isOcrLoading) ? 'Translating...' : 'Translate'"></span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- RIGHT: TRANSLATED OUTPUT PANEL --}}
            <div class="flex flex-col rounded-3xl bg-white dark:bg-[#111827] border border-slate-200/90 dark:border-slate-800 shadow-sm overflow-hidden min-h-[290px] sm:min-h-[360px]">

                {{-- Target Top Subheader --}}
                <div class="px-4 py-2.5 border-b border-slate-100 dark:border-slate-800/80 flex items-center justify-between gap-2 text-xs">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="font-bold text-emerald-700 dark:text-emerald-400" x-text="getLanguageName(translator.targetLang, false)"></span>
                        <span class="px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-[10px] font-semibold text-slate-500 dark:text-slate-400 capitalize" x-text="translator.mode"></span>
                    </div>
                    <div class="flex items-center gap-1" x-show="translator.translatedText">
                        <button type="button"
                                @click="translator.fullscreenResultOpen = true"
                                class="px-2 py-1 rounded-lg text-[11px] font-semibold text-slate-500 hover:text-slate-800 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                                title="Expand translation full screen">
                            Expand
                        </button>
                    </div>
                </div>

                {{-- Output Content Body --}}
                <div class="flex-1 flex flex-col p-4 relative">
                    {{-- Loading Shimmer (only when translating without existing text) --}}
                    <div x-show="(translator.isTranslating || translator.isOcrLoading) && !translator.translatedText" class="space-y-3 py-2 animate-pulse">
                        <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded-lg w-11/12"></div>
                        <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded-lg w-4/5"></div>
                        <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded-lg w-3/5"></div>
                    </div>

                    {{-- Empty State with Quick Pair Shortcuts --}}
                    <div x-show="!translator.isTranslating && !translator.isOcrLoading && !translator.translatedText"
                         class="flex-1 flex flex-col items-center justify-center text-center py-6 px-3">
                        <div class="w-11 h-11 rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl mb-2.5">🌐</div>
                        <p class="text-xs font-bold text-slate-700 dark:text-slate-200">Natural, context-aware translation</p>
                        <p class="text-[11px] text-slate-400 mt-1 max-w-xs">Preserves formatting, tone, technical terms, and native script accuracy across 45 languages.</p>
                        <div class="mt-4 flex flex-wrap items-center justify-center gap-1.5">
                            <button type="button" @click="applyQuickLanguagePair('en-US', 'km')" class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-slate-100 hover:bg-emerald-50 dark:bg-slate-800 dark:hover:bg-emerald-950/40 text-slate-600 dark:text-slate-300 transition-colors cursor-pointer">🇺🇸 EN → 🇰🇭 KM</button>
                            <button type="button" @click="applyQuickLanguagePair('km', 'en-US')" class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-slate-100 hover:bg-emerald-50 dark:bg-slate-800 dark:hover:bg-emerald-950/40 text-slate-600 dark:text-slate-300 transition-colors cursor-pointer">🇰🇭 KM → 🇺🇸 EN</button>
                            <button type="button" @click="applyQuickLanguagePair('en-US', 'zh-CN')" class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-slate-100 hover:bg-emerald-50 dark:bg-slate-800 dark:hover:bg-emerald-950/40 text-slate-600 dark:text-slate-300 transition-colors cursor-pointer">🇺🇸 EN → 🇨🇳 ZH</button>
                            <button type="button" @click="applyQuickLanguagePair('km', 'th')" class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-slate-100 hover:bg-emerald-50 dark:bg-slate-800 dark:hover:bg-emerald-950/40 text-slate-600 dark:text-slate-300 transition-colors cursor-pointer">🇰🇭 KM → 🇹🇭 TH</button>
                        </div>
                    </div>

                    {{-- Translated Text Result (seamless real-time display, slightly dimmed during live re-translation) --}}
                    <div x-show="translator.translatedText"
                         :class="(translator.isTranslating || translator.isOcrLoading) ? 'opacity-60 transition-opacity' : 'opacity-100 transition-opacity'"
                         :dir="isLanguageRtl(translator.targetLang) ? 'rtl' : 'ltr'"
                         style="font-family: 'Inter', 'Kantumruy Pro', 'Noto Sans Khmer', 'Noto Sans Thai', 'Noto Sans SC', 'Noto Sans JP', 'Noto Naskh Arabic', system-ui, sans-serif;"
                         class="flex-1 text-base sm:text-[17px] leading-relaxed text-slate-900 dark:text-slate-100 whitespace-pre-wrap break-words select-text overflow-y-auto"
                         x-text="translator.translatedText"></div>
                </div>

                {{-- Target Bottom Action Bar --}}
                <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-800/80 bg-slate-50/60 dark:bg-slate-900/40 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-1.5">
                        {{-- Copy Translation --}}
                        <button type="button"
                                @click="copyTranslatorText(translator.translatedText, 'target')"
                                :disabled="!translator.translatedText"
                                :class="translator.copiedSide === 'target'
                                    ? 'bg-emerald-600 text-white border-emerald-600'
                                    : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-emerald-50 dark:hover:bg-slate-700 border-slate-200 dark:border-slate-700'"
                                class="inline-flex items-center gap-1.5 px-3 h-9 rounded-xl text-xs font-bold border disabled:opacity-40 transition-all cursor-pointer">
                            <span x-text="translator.copiedSide === 'target' ? '✓ Copied' : 'Copy'"></span>
                        </button>

                        {{-- Listen to Translation (TTS Play / Pause / Stop) --}}
                        <button type="button"
                                @click="toggleTranslationTts('target')"
                                :disabled="!translator.translatedText"
                                :class="translator.ttsPlayingSide === 'target'
                                    ? 'bg-indigo-600 text-white border-indigo-600'
                                    : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-indigo-600 border-slate-200 dark:border-slate-700'"
                                class="inline-flex items-center gap-1.5 px-3 h-9 rounded-xl text-xs font-bold border disabled:opacity-40 transition-all cursor-pointer"
                                :title="translator.ttsPlayingSide === 'target' ? 'Stop audio playback' : 'Listen to translation'">
                            <svg x-show="translator.ttsPlayingSide !== 'target'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" />
                            </svg>
                            <svg x-show="translator.ttsPlayingSide === 'target'" class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                <rect x="6" y="6" width="12" height="12" rx="2" />
                            </svg>
                            <span x-text="translator.ttsPlayingSide === 'target' ? 'Stop' : 'Listen'"></span>
                        </button>

                        {{-- Regenerate --}}
                        <button type="button"
                                @click="runTranslation()"
                                :disabled="!translator.sourceText.trim() || translator.isTranslating"
                                class="w-9 h-9 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 disabled:opacity-40 flex items-center justify-center transition-all cursor-pointer"
                                title="Regenerate translation">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                        </button>
                    </div>

                    <div class="flex items-center gap-1.5 ml-auto">
                        {{-- Share --}}
                        <button type="button"
                                @click="shareTranslationResult()"
                                :disabled="!translator.translatedText"
                                class="w-9 h-9 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-indigo-600 border border-slate-200 dark:border-slate-700 disabled:opacity-40 flex items-center justify-center transition-all cursor-pointer"
                                title="Share translation">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z"/></svg>
                        </button>

                        {{-- Download .txt --}}
                        <button type="button"
                                @click="downloadTranslationTxt()"
                                :disabled="!translator.translatedText"
                                class="w-9 h-9 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-indigo-600 border border-slate-200 dark:border-slate-700 disabled:opacity-40 flex items-center justify-center transition-all cursor-pointer"
                                title="Download .txt">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Translation History Drawer / Section --}}
        <div x-show="translator.historyOpen"
             x-collapse
             class="rounded-3xl bg-white dark:bg-[#111827] border border-slate-200/90 dark:border-slate-800 shadow-sm overflow-hidden">
            <div class="px-4 sm:px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-black text-slate-800 dark:text-white">Saved Translations</span>
                    <div class="inline-flex rounded-xl bg-slate-100 dark:bg-slate-800 p-0.5 text-[11px] font-bold">
                        <button type="button"
                                @click="translator.historyFavoritesOnly = false; loadTranslationHistory()"
                                :class="!translator.historyFavoritesOnly ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500'"
                                class="px-2.5 py-1 rounded-lg transition-all cursor-pointer">All</button>
                        <button type="button"
                                @click="translator.historyFavoritesOnly = true; loadTranslationHistory()"
                                :class="translator.historyFavoritesOnly ? 'bg-white dark:bg-slate-900 text-amber-600 dark:text-amber-400 shadow-xs' : 'text-slate-500'"
                                class="px-2.5 py-1 rounded-lg transition-all cursor-pointer">★ Favorites</button>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-1 sm:flex-initial sm:w-64">
                    <input type="search"
                           x-model="translator.historySearch"
                           @input.debounce.250ms="loadTranslationHistory()"
                           placeholder="Search translation history..."
                           class="w-full px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                </div>
            </div>

            <div class="max-h-80 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/80 custom-scrollbar">
                <template x-for="item in translator.history" :key="item.id">
                    <div class="p-4 hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1 cursor-pointer" @click="restoreTranslationHistoryItem(item)">
                            <div class="flex items-center gap-2 text-[11px] font-bold text-slate-500 dark:text-slate-400 mb-1 flex-wrap">
                                <span x-text="`${item.source_flag || '🌐'} ${item.source_label}`"></span>
                                <span>→</span>
                                <span class="text-emerald-600 dark:text-emerald-400" x-text="`${item.target_flag || '🌐'} ${item.target_label}`"></span>
                                <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-[10px] capitalize" x-text="item.translation_mode"></span>
                                <span class="text-[10px] font-normal text-slate-400 ml-auto" x-text="item.time_label"></span>
                            </div>
                            <p class="text-xs text-slate-600 dark:text-slate-300 line-clamp-1" x-text="item.source_text"></p>
                            <p class="text-xs font-bold text-slate-900 dark:text-white line-clamp-2 mt-0.5" x-text="item.translated_text"></p>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <button type="button"
                                    @click.stop="toggleFavoriteTranslationItem(item)"
                                    :class="item.is_favorite ? 'text-amber-500' : 'text-slate-300 hover:text-amber-400'"
                                    class="w-8 h-8 rounded-lg flex items-center justify-center text-sm transition-colors cursor-pointer"
                                    title="Favorite">★</button>
                            <button type="button"
                                    @click.stop="copyTranslatorText(item.translated_text, 'history')"
                                    class="w-8 h-8 rounded-lg text-slate-400 hover:text-emerald-600 hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center justify-center text-xs transition-colors cursor-pointer"
                                    title="Copy translation">📋</button>
                            <button type="button"
                                    @click.stop="deleteTranslationHistoryItem(item.id)"
                                    class="w-8 h-8 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 flex items-center justify-center text-xs transition-colors cursor-pointer"
                                    title="Delete">🗑️</button>
                        </div>
                    </div>
                </template>
                <div x-show="translator.history.length === 0" class="py-8 text-center text-xs text-slate-400">
                    No saved translations found.
                </div>
            </div>
        </div>
    </div>

    {{-- Full-Screen Reader Overlay for Long Translations --}}
    <div x-show="translator.fullscreenResultOpen"
         x-cloak
         class="fixed inset-0 z-[100005] bg-slate-950/90 backdrop-blur-xl flex flex-col p-4 sm:p-8"
         style="padding-top: max(1.25rem, calc(var(--dgt-safe-top, env(safe-area-inset-top, 0px)) + 0.75rem)); padding-bottom: max(1.25rem, var(--dgt-safe-bottom, env(safe-area-inset-bottom, 0px)));">
        <div class="max-w-4xl w-full mx-auto flex-1 flex flex-col bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-2xl">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-lg" x-text="getLanguageFlag(translator.targetLang, false)"></span>
                    <h3 class="text-sm font-black text-slate-900 dark:text-white" x-text="getLanguageName(translator.targetLang, false) + ' Translation'"></h3>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button"
                            @click="copyTranslatorText(translator.translatedText, 'target')"
                            class="px-3 py-1.5 rounded-xl bg-emerald-600 text-white text-xs font-bold cursor-pointer">
                        <span x-text="translator.copiedSide === 'target' ? '✓ Copied' : 'Copy'"></span>
                    </button>
                    <button type="button"
                            @click="translator.fullscreenResultOpen = false"
                            class="w-9 h-9 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center font-bold cursor-pointer">✕</button>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto p-6 sm:p-8 text-lg sm:text-2xl leading-relaxed text-slate-900 dark:text-slate-100 whitespace-pre-wrap select-text"
                 :dir="isLanguageRtl(translator.targetLang) ? 'rtl' : 'ltr'"
                 style="font-family: 'Inter', 'Kantumruy Pro', 'Noto Sans Khmer', 'Noto Sans Thai', 'Noto Sans SC', 'Noto Sans JP', 'Noto Naskh Arabic', system-ui, sans-serif;"
                 x-text="translator.translatedText"></div>
        </div>
    </div>
</div>
