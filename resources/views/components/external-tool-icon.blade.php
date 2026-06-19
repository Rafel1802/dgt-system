@props(['name' => 'link'])

@if(in_array($name, ['google-drive', 'google-docs', 'google-sheets', 'google-translate']))
    @if($name === 'google-drive')
        <svg {{ $attributes->merge(['class' => 'h-4 w-4']) }} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M15.17 2H8.83L1.5 14.81h6.34L15.17 2z" fill="#FFD04B"/>
            <path d="M22.5 14.81L15.17 2l-3.17 5.49L19.34 22h6.33l-3.17-7.19z" fill="#1AA15F"/>
            <path d="M19.34 22H6.66L1.5 14.81 4.67 9.32l5.17 9.87h12.67L19.34 22z" fill="#4C8BF5"/>
        </svg>
    @elseif($name === 'google-docs')
        <svg {{ $attributes->merge(['class' => 'h-4 w-4']) }} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" fill="#4285F4"/>
            <path d="M14.5 2v5.5H20" fill="#2B579A"/>
            <path d="M7 13h10v1.5H7zm0 3h10v1.5H7zm0-6h6v1.5H7z" fill="#FFFFFF"/>
        </svg>
    @elseif($name === 'google-sheets')
        <svg {{ $attributes->merge(['class' => 'h-4 w-4']) }} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" fill="#0F9D58"/>
            <path d="M14.5 2v5.5H20" fill="#0a703f"/>
            <path d="M7 10h3v2H7zm4 0h3v2h-3zm4 0h2v2h-2zM7 13h3v2H7zm4 0h3v2h-3zm4 0h2v2h-2zM7 16h3v2H7zm4 0h3v2h-3zm4 0h2v2h-2z" fill="#FFFFFF"/>
        </svg>
    @elseif($name === 'google-translate')
        <svg {{ $attributes->merge(['class' => 'h-4 w-4']) }} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M12.87 15.07l-2.54-2.51.03-.03c1.74-1.94 2.98-4.17 3.71-6.53H17V4h-7V2H8v2H1v2h11.17C11.5 7.92 10.44 9.75 9 11.35 8.07 10.32 7.3 9.19 6.69 8h-2c.73 1.63 1.73 3.17 2.98 4.56l-5.09 5.02L4 19l5-5 3.11 3.11.76-2.04zM18.5 10h-2L12 22h2l1.12-3h5.75L22 22h2l-4.5-12zm-2.62 7l1.62-4.33L19.12 17h-3.24z" fill="#4285F4"/>
        </svg>
    @endif
@else
    <svg {{ $attributes->merge(['class' => 'h-4 w-4']) }} fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" aria-hidden="true">
        @switch($name)
            @case('image')
                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.16-5.16a2.25 2.25 0 0 1 3.18 0l5.16 5.16m-1.5-1.5 1.41-1.41a2.25 2.25 0 0 1 3.18 0l2.91 2.91M3.75 19.5h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.01v.01h-.01v-.01Z" />
                @break

            @case('server')
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6.75h12M6 12h12M6 17.25h12M4.5 3.75h15A1.5 1.5 0 0 1 21 5.25v3A1.5 1.5 0 0 1 19.5 9.75h-15A1.5 1.5 0 0 1 3 8.25v-3A1.5 1.5 0 0 1 4.5 3.75Zm0 6h15A1.5 1.5 0 0 1 21 11.25v3a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 14.25v-3a1.5 1.5 0 0 1 1.5-1.5Zm0 6h15A1.5 1.5 0 0 1 21 17.25v1.5a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18.75v-1.5a1.5 1.5 0 0 1 1.5-1.5Z" />
                @break

            @case('template')
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.63a3.38 3.38 0 0 0-3.38-3.37h-1.5A1.13 1.13 0 0 1 13.5 7.13v-1.5a3.38 3.38 0 0 0-3.38-3.38H8.25m2.25 12h3m-3 3h6m-9-15H5.63A1.13 1.13 0 0 0 4.5 3.38v17.25c0 .62.5 1.12 1.13 1.12h12.75c.62 0 1.12-.5 1.12-1.12v-9.38a9 9 0 0 0-9-9Z" />
                @break

            @case('sparkles')
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.81 15.9 9 18.75l-.81-2.85a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.85-.81a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.81 2.85a4.5 4.5 0 0 0 3.09 3.09l2.85.81-2.85.81a4.5 4.5 0 0 0-3.09 3.09Zm8.44-9.15L18 8.25l-.25-1.5a2.25 2.25 0 0 0-1.5-1.5L14.75 5l1.5-.25a2.25 2.25 0 0 0 1.5-1.5L18 1.75l.25 1.5a2.25 2.25 0 0 0 1.5 1.5l1.5.25-1.5.25a2.25 2.25 0 0 0-1.5 1.5Z" />
                @break

            @case('target')
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 1 1-1.22-1.22M12 12l7.5-7.5m0 0v5.25m0-5.25h-5.25M15.75 12A3.75 3.75 0 1 1 12 8.25" />
                @break

            @case('play')
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.25 8.75v6.5L15.75 12l-5.5-3.25Z" />
                @break

            @case('convert')
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 7.5h12m0 0-3-3m3 3-3 3m0 6h-12m0 0 3 3m-3-3 3-3" />
                @break

            @case('chat')
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                @break

            @default
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.69a4.5 4.5 0 0 1 1.24 7.24l-4.5 4.5a4.5 4.5 0 0 1-6.36-6.36l1.76-1.76m13.35-.62 1.75-1.76a4.5 4.5 0 0 0-6.36-6.36l-4.5 4.5a4.5 4.5 0 0 0 1.24 7.24" />
        @endswitch
    </svg>
@endif
