{{-- Dark mode overrides for email rows --}}
<style>
  /* Row backgrounds */
  [data-theme="dark"] .email-row-item { background-color: #0f172a !important; border-color: #1e293b !important; }
  [data-theme="dark"] .email-row-item:hover { background-color: #1e293b !important; }
  [data-theme="dark"] .email-row-item.email-unread { background-color: #131c35 !important; }
  [data-theme="dark"] .email-row-item.email-unread:hover { background-color: #1a2540 !important; }
  /* Text colors */
  [data-theme="dark"] .email-row-text { color: #ffffff !important; }
  [data-theme="dark"] .email-row-sub { color: #94a3b8 !important; }
  [data-theme="dark"] .email-row-meta { color: #64748b !important; }
</style>

<a href="{{ route('admin.emails.show', $msg) }}"
   class="group email-row-item flex items-center gap-4 px-5 py-3.5 border-b transition-all duration-300 {{ !$msg->is_read ? 'email-unread' : '' }}"
   style="border-color: #e2e8f0; {{ !$msg->is_read ? 'background-color: #f0f4ff;' : 'background-color: #ffffff;' }}"
   onmouseenter="this.style.backgroundColor='#eef2ff'"
   onmouseleave="this.style.backgroundColor='{{ !$msg->is_read ? '#f0f4ff' : '#ffffff' }}'"
   id="email-row-{{ $msg->id }}">

  {{-- Checkbox --}}
  <div class="flex-shrink-0" x-cloak x-show="showCheckboxes" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-90" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-90" @click.stop>
    <input type="checkbox" x-model="selected" value="{{ $msg->id }}" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
  </div>

  {{-- Unread dot --}}
  <div class="w-2.5 flex-shrink-0">
    @if(!$msg->is_read)
      <span class="block w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
    @endif
  </div>

  {{-- Sender avatar --}}
  @php
    $avatarColors = ['#ef4444','#f97316','#eab308','#22c55e','#14b8a6','#3b82f6','#6366f1','#8b5cf6','#ec4899','#06b6d4','#10b981','#f59e0b'];
    $avatarSeed   = crc32(strtolower($msg->from_email));
    $avatarColor  = $avatarColors[abs($avatarSeed) % count($avatarColors)];
    $avatarLetter = strtoupper(substr($msg->from_name ?: $msg->from_email, 0, 1));
  @endphp
  <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0 shadow-sm"
       style="background-color: {{ $avatarColor }}">
    {{ $avatarLetter }}
  </div>

  {{-- Content --}}
  <div class="flex-1 min-w-0">
    <div class="flex items-center gap-2 mb-0.5">
      <span class="email-row-text text-sm truncate" style="color: #000000; font-weight: {{ $msg->is_read ? '400' : '700' }};">
        {{ $msg->from_name ?: $msg->from_email }}
      </span>
      @if($msg->has_attachments)
        <span class="email-row-meta" style="color: #9ca3af; font-size: 12px;">📎</span>
      @endif
      @if($msg->is_starred)
        <span style="font-size: 12px;">⭐</span>
      @endif
    </div>
    <p class="email-row-text text-sm truncate" style="color: #000000; font-weight: {{ $msg->is_read ? '400' : '700' }};">
      {{ $msg->subject }}
    </p>
    <p class="email-row-sub text-xs truncate" style="color: #666666; margin-top: 2px;">
      {{ Str::limit(strip_tags($msg->body_text ?? $msg->body_html), 100) }}
    </p>
  </div>

  {{-- Account + Date + Actions --}}
  <div class="text-right flex-shrink-0 flex flex-col items-end" @click.stop>
    <div class="flex items-center gap-2 mb-1">
      <button @click.prevent="confirmDelete({{ $msg->id }})"
              class="text-rose-500 hover:text-rose-700 opacity-0 group-hover:opacity-100 transition-opacity bg-rose-50 hover:bg-rose-100 p-1 rounded"
              title="Delete Email">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
      </button>
      <span class="email-row-text badge badge-slate" style="font-size: 10px; color: #000000;">{{ $msg->account->name ?? '' }}</span>
    </div>
    <p class="email-row-meta" style="font-size: 12px; color: #888888;">{{ $msg->received_at?->diffForHumans() ?? $msg->created_at->diffForHumans() }}</p>
  </div>
</a>
