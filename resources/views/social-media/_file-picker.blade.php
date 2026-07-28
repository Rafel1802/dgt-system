{{--
    Reusable custom file picker partial.
    Variables: $name (string), $accept (string, optional), $placeholder (string, optional)
    Usage: @include('social-media._file-picker', ['name' => 'icon_file', 'accept' => 'image/*', 'placeholder' => 'Choose icon…'])
--}}
@php
    $accept      = $accept ?? 'image/*';
    $placeholder = $placeholder ?? 'Choose file…';
    $pickerId    = 'fp-' . uniqid();
@endphp

<div x-data="{ fileName: '' }" class="relative inline-flex items-center">
    {{-- Hidden native input --}}
    <input
        type="file"
        id="{{ $pickerId }}"
        name="{{ $name }}"
        accept="{{ $accept }}"
        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
        @change="fileName = $event.target.files[0]?.name ?? ''"
    >
    {{-- Visual button --}}
    <label for="{{ $pickerId }}"
           class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-indigo-50 hover:border-indigo-300 text-slate-500 hover:text-indigo-600 cursor-pointer text-xs font-medium transition-all duration-200 shadow-sm select-none whitespace-nowrap"
           :class="fileName ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : ''">
        {{-- Upload cloud icon --}}
        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
        </svg>
        <span x-text="fileName || '{{ $placeholder }}'"></span>
        {{-- Clear button --}}
        <template x-if="fileName">
            <span @click.prevent="fileName = ''; document.getElementById('{{ $pickerId }}').value = ''"
                  class="ml-0.5 text-indigo-400 hover:text-rose-500 transition-colors">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </span>
        </template>
    </label>
</div>
