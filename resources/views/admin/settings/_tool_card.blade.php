{{-- Reusable sortable tool card partial --}}
{{-- Variables: $tool, $settings, $color ('blue'|'violet'|'emerald'|'amber'), $hasLabel (bool) --}}
@php
    $colorMap = [
        'blue'    => ['bg' => 'bg-blue-50 dark:bg-blue-900/30',     'icon' => 'text-blue-500',    'ring' => 'hover:border-blue-300 dark:hover:border-blue-600'],
        'violet'  => ['bg' => 'bg-violet-50 dark:bg-violet-900/30', 'icon' => 'text-violet-500',  'ring' => 'hover:border-violet-300 dark:hover:border-violet-600'],
        'emerald' => ['bg' => 'bg-emerald-50 dark:bg-emerald-900/30','icon'=> 'text-emerald-500', 'ring' => 'hover:border-emerald-300 dark:hover:border-emerald-600'],
        'amber'   => ['bg' => 'bg-amber-50 dark:bg-amber-900/30',   'icon' => 'text-amber-500',   'ring' => 'hover:border-amber-300 dark:hover:border-amber-600'],
    ];
    $c = $colorMap[$color ?? 'blue'];
    $hasCustomIcon = isset($settings[$tool['key'] . '_icon']) && $settings[$tool['key'] . '_icon'];
@endphp

<div class="card p-5 border-2 border-transparent {{ $c['ring'] }} transition-all duration-200 hover:shadow-md relative group" data-key="{{ $tool['key'] }}">

    {{-- Drag handle (appears on hover) --}}
    <div class="drag-handle absolute top-3 left-3 w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 cursor-grab active:cursor-grabbing transition-all select-none" title="Drag to reorder">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5"/>
        </svg>
    </div>

    {{-- Icon + Label --}}
    <div class="flex items-center gap-3 mb-4 pt-1 pl-1">
        <div class="h-11 w-11 rounded-xl {{ $c['bg'] }} flex items-center justify-center flex-shrink-0">
            @if($hasCustomIcon)
                <img src="{{ $settings[$tool['key'] . '_icon'] }}" class="h-7 w-7 object-contain" alt="">
            @else
                <span class="{{ $c['icon'] }}">
                    <x-external-tool-icon :name="$tool['icon']" />
                </span>
            @endif
        </div>
        <div class="flex-1 min-w-0">
            <div class="text-sm font-bold text-slate-800 dark:text-slate-100 truncate">{{ $tool['label'] }}</div>
            <div class="text-xs text-slate-500 dark:text-slate-400 truncate mt-0.5">{{ $tool['description'] }}</div>
        </div>
    </div>

    {{-- Fields --}}
    <div class="space-y-2">
        <div>
            <label for="{{ $tool['key'] }}" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">URL</label>
            <input
                id="{{ $tool['key'] }}"
                type="url"
                name="{{ $tool['key'] }}"
                value="{{ old($tool['key'], $settings[$tool['key']] ?? $tool['url']) }}"
                placeholder="https://your-system.example.com"
                class="form-input w-full text-sm"
            >
            @error($tool['key'])
                <span class="mt-1 block text-xs font-bold text-rose-600">{{ $message }}</span>
            @enderror
        </div>

        @if($hasLabel ?? false)
        <div>
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Custom Label</label>
            <input
                type="text"
                name="{{ $tool['key'] }}_label"
                value="{{ old($tool['key'] . '_label', $settings[$tool['key'] . '_label'] ?? '') }}"
                placeholder="e.g. ChatGPT"
                class="form-input w-full text-sm"
            >
        </div>
        @endif

        <div>
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">
                Icon URL <span class="text-slate-400 normal-case font-normal">(optional)</span>
            </label>
            <input
                type="url"
                name="{{ $tool['key'] }}_icon"
                value="{{ old($tool['key'] . '_icon', $settings[$tool['key'] . '_icon'] ?? $tool['icon_url'] ?? '') }}"
                placeholder="https://..."
                class="form-input w-full text-sm"
            >
            @error($tool['key'] . '_icon')
                <span class="mt-1 block text-xs font-bold text-rose-600">{{ $message }}</span>
            @enderror
        </div>
    </div>
</div>
