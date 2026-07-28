@php
    $colorMap = [
        'blue'    => [
            'bg' => 'bg-blue-50/50 dark:bg-blue-950/20',
            'border' => 'border-blue-100 dark:border-blue-900/30',
            'accent' => 'bg-blue-500 text-white',
            'hover' => 'hover:border-blue-300 dark:hover:border-blue-800',
            'iconBg' => 'bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400'
        ],
        'violet'  => [
            'bg' => 'bg-violet-50/50 dark:bg-violet-950/20',
            'border' => 'border-violet-100 dark:border-violet-900/30',
            'accent' => 'bg-violet-500 text-white',
            'hover' => 'hover:border-violet-300 dark:hover:border-violet-800',
            'iconBg' => 'bg-violet-100 dark:bg-violet-900/40 text-violet-600 dark:text-violet-400'
        ],
        'emerald' => [
            'bg' => 'bg-emerald-50/50 dark:bg-emerald-950/20',
            'border' => 'border-emerald-100 dark:border-emerald-900/30',
            'accent' => 'bg-emerald-500 text-white',
            'hover' => 'hover:border-emerald-300 dark:hover:border-emerald-800',
            'iconBg' => 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400'
        ],
        'amber'   => [
            'bg' => 'bg-amber-50/50 dark:bg-amber-950/20',
            'border' => 'border-amber-100 dark:border-amber-900/30',
            'accent' => 'bg-amber-500 text-white',
            'hover' => 'hover:border-amber-300 dark:hover:border-amber-800',
            'iconBg' => 'bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400'
        ],
    ];
    $c = $colorMap[$color ?? 'blue'];
@endphp

<section x-data="dynamicToolsEditor(@js($tools), '{{ $orderInput }}')" x-init="initSortable('{{ $sortableId }}')" class="space-y-4">
    {{-- Hidden inputs to serialize state back to the server --}}
    <input type="hidden" name="{{ $orderInput }}" :value="JSON.stringify(tools.map(t => t._static_key || t.custom_id))">
    <input type="hidden" name="{{ $customInput }}" :value="JSON.stringify(tools.filter(t => !t._static_key).map(t => ({ custom_id: t.custom_id, label: t.label, url: t.url, icon_url: t.icon_url })))">

    {{-- Section Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-500">{{ $title }}</h2>
            <p class="mt-1 text-sm font-semibold text-slate-400">{{ $description }}</p>
        </div>
        <button type="button" @click="addTool()" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700/80 text-slate-700 dark:text-slate-300 text-xs font-bold transition-all border border-slate-200/50 dark:border-slate-700/50">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            {{ $buttonText }}
        </button>
    </div>

    {{-- Cards Grid --}}
    <div id="{{ $sortableId }}" class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <template x-for="(tool, index) in tools" :key="tool._id">
            <div 
                class="card p-5 border-2 transition-all duration-200 hover:shadow-md relative group flex flex-col justify-between"
                :class="tool._static_key ? 'bg-white border-transparent dark:bg-slate-800/40 {{ $c['hover'] }}' : 'bg-slate-50 dark:bg-slate-800 border-dashed border-slate-200 dark:border-slate-700 {{ $c['hover'] }}'"
            >
                <div>
                    {{-- Drag Handle + Remove Button --}}
                    <div class="flex items-center justify-between mb-4">
                        <div class="drag-handle w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 cursor-grab active:cursor-grabbing transition-all select-none" title="Drag to reorder">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5"/>
                            </svg>
                        </div>
                        
                        <template x-if="!tool._static_key">
                            <button type="button" @click="removeTool(index)" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/20 transition-all" title="Remove custom tool">
                                <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </button>
                        </template>
                    </div>

                    {{-- Tool Header: Icon & Label --}}
                    <div class="flex items-center gap-3 mb-4 pl-1">
                        <div class="h-11 w-11 rounded-xl flex items-center justify-center flex-shrink-0 {{ $c['iconBg'] }}">
                            <template x-if="tool.icon_url">
                                <img :src="tool.icon_url" class="h-7 w-7 object-contain rounded" alt="">
                            </template>
                            <template x-if="!tool.icon_url">
                                <span class="flex items-center justify-center text-slate-400 dark:text-slate-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                                    </svg>
                                </span>
                            </template>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-bold text-slate-800 dark:text-slate-100 truncate" x-text="tool.label || (tool._static_key ? 'Static Tool' : 'Custom Tool')"></div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 truncate mt-0.5" x-text="tool.description || 'Custom External Link'"></div>
                        </div>
                    </div>

                    {{-- Inputs --}}
                    <div class="space-y-3">
                        {{-- URL Field --}}
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">URL</label>
                            <input 
                                type="url"
                                :name="tool._static_key ? tool._static_key : null"
                                x-model="tool.url"
                                placeholder="https://example.com"
                                class="form-input w-full text-xs"
                                :required="!tool._static_key"
                            >
                        </div>

                        {{-- Label Field --}}
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">
                                Custom Label <span class="text-slate-400/80 normal-case font-normal" x-show="tool._static_key">(optional)</span>
                            </label>
                            <input 
                                type="text"
                                :name="tool._static_key ? tool._static_key + '_label' : null"
                                x-model="tool.label"
                                placeholder="Label"
                                class="form-input w-full text-xs"
                                :required="!tool._static_key"
                            >
                        </div>

                        {{-- Icon Field --}}
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">
                                Icon URL <span class="text-slate-400/80 normal-case font-normal">(optional)</span>
                            </label>
                            <input 
                                type="url"
                                :name="tool._static_key ? tool._static_key + '_icon' : null"
                                x-model="tool.icon_url"
                                placeholder="https://.../icon.png"
                                class="form-input w-full text-xs"
                            >
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</section>
