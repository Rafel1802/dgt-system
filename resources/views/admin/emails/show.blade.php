@extends('layouts.app')
@section('title', 'View Email')
@section('page_title', 'View Email')

@section('content')
<div class="max-w-4xl mx-auto animate-fade-in space-y-4">

  {{-- Actions --}}
  <div class="flex items-center gap-3">
    <a href="{{ route('admin.emails.index') }}" class="btn btn-secondary text-sm">← Back to Inbox</a>
    <div class="ml-auto flex gap-2" x-data="{ showDeleteModal: false }">
      <button class="btn btn-secondary text-sm py-1.5"
              @click="fetch('/admin/emails/{{ $email->id }}/toggle-read', {method:'PATCH', headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content, Accept:'application/json'}}).then(()=>location.reload())">
        {{ $email->is_read ? '📩 Mark Unread' : '📧 Mark Read' }}
      </button>
      <button class="btn btn-secondary text-sm py-1.5"
              @click="fetch('/admin/emails/{{ $email->id }}/toggle-star', {method:'PATCH', headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content, Accept:'application/json'}}).then(()=>location.reload())">
        {{ $email->is_starred ? '⭐ Unstar' : '☆ Star' }}
      </button>
      @if($email->from_email)
        <a href="mailto:{{ $email->from_email }}?subject=Re: {{ rawurlencode($email->subject) }}"
           class="btn btn-primary text-sm py-1.5">↩ Reply in Mail Client</a>
      @endif
      <button class="btn btn-danger text-sm py-1.5"
              @click="showDeleteModal = true">
        🗑 Delete
      </button>

      {{-- Delete Confirmation Modal --}}
      <div x-cloak x-show="showDeleteModal" class="relative z-[100]" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div x-show="showDeleteModal" 
             x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" 
             x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" 
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
        <div class="fixed inset-0 z-10 overflow-y-auto">
          <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div x-show="showDeleteModal" @click.away="showDeleteModal = false"
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-200">
              <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                  <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                    <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                  </div>
                  <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                    <h3 class="text-lg font-semibold leading-6 text-slate-900" id="modal-title">Delete Email</h3>
                    <div class="mt-2">
                      <p class="text-sm text-slate-500">Are you sure you want to delete this email? This action cannot be undone.</p>
                    </div>
                  </div>
                </div>
              </div>
              <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                <button type="button" @click="fetch('/admin/emails/{{ $email->id }}', {method:'DELETE', headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content, Accept:'application/json'}}).then(()=>window.location.href='/admin/emails')" class="btn btn-danger w-full justify-center sm:ml-3 sm:w-auto">Remove</button>
                <button type="button" @click="showDeleteModal = false" class="btn btn-secondary mt-3 w-full justify-center sm:mt-0 sm:w-auto">Cancel</button>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  {{-- Email Card --}}
  <div class="card">
    {{-- Header --}}
    <div class="border-b border-slate-100 pb-4 mb-4">
      <h2 class="text-xl font-bold text-slate-800 font-display mb-2">{{ $email->subject }}</h2>
      <div class="flex items-start gap-3">
        <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold text-white flex-shrink-0"
             style="background: linear-gradient(135deg, #6366f1, #8b5cf6)">
          {{ strtoupper(substr($email->from_name ?: $email->from_email, 0, 1)) }}
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold text-slate-700">
            {{ $email->from_name ?: $email->from_email }}
            <span class="font-normal text-slate-400">&lt;{{ $email->from_email }}&gt;</span>
          </p>
          <p class="text-xs text-slate-400 mt-0.5">
            To: {{ implode(', ', $email->to_emails ?? []) }}
            @if($email->cc_emails)
              · Cc: {{ implode(', ', $email->cc_emails) }}
            @endif
          </p>
          <p class="text-xs text-slate-400 mt-0.5">
            {{ $email->received_at?->format('D, d M Y H:i') ?? $email->created_at->format('D, d M Y H:i') }}
            · via <strong>{{ $email->account->name ?? 'Unknown' }}</strong>
          </p>
        </div>
        @if($email->has_attachments)
          <span class="badge badge-slate">📎 Has Attachments</span>
        @endif
      </div>
    </div>

    {{-- Body --}}
    <div class="prose prose-sm max-w-none text-slate-700">
      @if($email->body_html)
        <div class="email-body-html bg-white p-0 rounded-lg border border-slate-100 relative overflow-hidden" style="background-color: #ffffff !important;">
          @php
            $htmlContent = $email->body_html;
            if (!str_contains($htmlContent, '<base target="_blank"')) {
                if (str_contains(strtolower($htmlContent), '<head>')) {
                    $htmlContent = preg_replace('/<head>/i', '<head><base target="_blank">', $htmlContent, 1);
                } else {
                    $htmlContent = '<head><base target="_blank"></head>' . $htmlContent;
                }
            }
          @endphp
          <iframe id="email-iframe" 
                  sandbox="allow-popups allow-popups-to-escape-sandbox allow-same-origin"
                  class="w-full border-0 min-h-[500px]"
                  srcdoc="{{ $htmlContent }}"
                  onload="resizeEmailIframe(this)">
          </iframe>
          <script>
            function resizeEmailIframe(iframe) {
                try {
                    const doc = iframe.contentWindow.document;
                    const updateHeight = () => {
                        iframe.style.height = doc.documentElement.scrollHeight + 20 + 'px';
                    };
                    updateHeight();
                    // Observe DOM changes (images loading, etc.)
                    const observer = new MutationObserver(updateHeight);
                    observer.observe(doc.body, { childList: true, subtree: true, attributes: true });
                } catch(e) {
                    console.error("Could not resize email iframe", e);
                }
            }
          </script>
        </div>
      @elseif($email->body_text)
        <pre class="whitespace-pre-wrap text-sm text-slate-600 font-sans">{{ $email->body_text }}</pre>
      @else
        <p class="text-slate-400 italic">No content.</p>
      @endif
    </div>
  </div>

</div>
@endsection
