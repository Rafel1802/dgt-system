@extends('layouts.app')
@section('title', 'Email Accounts')
@section('page_title', 'Email Accounts')

@section('content')
<div class="max-w-3xl mx-auto animate-fade-in space-y-6">

  @if($errors->any())
    <div class="rounded-xl bg-red-50 border border-red-200 p-4 text-red-700 font-medium">
      <div class="flex items-center gap-2 mb-1">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        <span>Please check your connection settings:</span>
      </div>
      <ul class="list-disc list-inside text-sm ml-6">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- Connected Accounts --}}
  <div class="card">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-semibold text-slate-700 dark:text-slate-200">Connected Accounts</h3>
      <a href="{{ route('admin.emails.index') }}" class="btn btn-secondary text-sm">← Back to Inbox</a>
    </div>

    @forelse($accounts as $acct)
      @php
        $colors = ['#6366f1','#0ea5e9','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#14b8a6'];
        $color  = $colors[crc32($acct->email_address) % count($colors)];
        $initials = strtoupper(substr($acct->name, 0, 1));
        $isScript = $acct->provider === 'google_script';
      @endphp
      <div class="flex items-center gap-4 py-3 {{ !$loop->last ? 'border-b border-slate-100 dark:border-slate-700' : '' }}">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-sm font-bold text-white flex-shrink-0"
             style="background: {{ $color }}">
          {{ $initials }}
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $acct->name }}</p>
          <p class="text-xs text-slate-400">
            {{ $acct->email_address }} ·
            <span class="inline-flex items-center gap-1">
              @if($isScript)
                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                Google Script
              @else
                IMAP
              @endif
            </span>
            · {{ $acct->messages_count }} messages
          </p>
          @if($acct->last_synced_at)
            <p class="text-xs text-slate-400">Last synced: {{ $acct->last_synced_at->diffForHumans() }}</p>
          @endif
          @if($isScript && $acct->imap_host)
            <p class="text-xs text-indigo-500 font-mono truncate mt-0.5">Webhook: {{ $acct->imap_host }}</p>
          @endif
        </div>
        <div class="flex gap-2 items-center">
          <span class="badge {{ $acct->is_active ? 'badge-emerald' : 'badge-slate' }}">
            {{ $acct->is_active ? 'Active' : 'Inactive' }}
          </span>
          <form method="POST" action="{{ route('admin.emails.accounts.destroy', $acct) }}"
                data-confirm-title="Delete email account?"
                data-confirm="Delete this account and all its messages?"
                data-confirm-text="Delete account"
                data-confirm-tone="danger">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger text-xs py-1">Delete</button>
          </form>
        </div>
      </div>
    @empty
      <p class="text-center text-slate-400 py-8">No email accounts connected yet.</p>
    @endforelse
  </div>

  {{-- Add New Account —Two-Tab Form --}}
  <div class="card" x-data="{ tab: '{{ old('provider', 'imap') === 'google_script' ? 'script' : 'imap' }}' }">
    <h3 class="font-semibold text-slate-700 dark:text-slate-200 mb-1">Add Email Account</h3>
    <p class="text-sm text-slate-400 mb-5">Choose a connection method below.</p>

    {{-- Tab Switcher --}}
    <div class="flex gap-2 mb-6 border-b border-slate-200 dark:border-slate-700">
      <button type="button" id="tab-imap" @click="tab='imap'"
              :class="tab==='imap' ? 'border-b-2 border-indigo-600 text-indigo-600 font-semibold' : 'text-slate-500 hover:text-slate-700'"
              class="flex items-center gap-2 pb-3 px-1 text-sm transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        IMAP / App Password
      </button>
      <button type="button" id="tab-script" @click="tab='script'"
              :class="tab==='script' ? 'border-b-2 border-indigo-600 text-indigo-600 font-semibold' : 'text-slate-500 hover:text-slate-700'"
              class="flex items-center gap-2 pb-3 px-1 text-sm transition-colors">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
        Google Apps Script
      </button>
    </div>

    {{-- IMAP Form --}}
    <div x-show="tab==='imap'" x-transition>
      <form method="POST" action="{{ route('admin.emails.accounts.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="provider" value="imap">

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="form-label">Account Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}" class="form-input" placeholder="e.g. eBay Store AU" required>
            @error('name')<p class="form-error">{{ $message }}</p>@enderror
          </div>
          <div>
            <label class="form-label">Email Address <span class="text-red-500">*</span></label>
            <input type="email" name="email_address" value="{{ old('email_address') }}" class="form-input" placeholder="store@example.com" required>
            @error('email_address')<p class="form-error">{{ $message }}</p>@enderror
          </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="form-label">IMAP Host</label>
            <input type="text" name="imap_host" value="{{ old('imap_host', 'imap.gmail.com') }}" class="form-input">
          </div>
          <div>
            <label class="form-label">IMAP Port</label>
            <input type="number" name="imap_port" value="{{ old('imap_port', 993) }}" class="form-input">
          </div>
          <div>
            <label class="form-label">Encryption</label>
            <select name="imap_encryption" class="form-input">
              <option value="ssl" @selected(old('imap_encryption', 'ssl') === 'ssl')>SSL</option>
              <option value="tls" @selected(old('imap_encryption') === 'tls')>TLS</option>
              <option value="none" @selected(old('imap_encryption') === 'none')>None</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="form-label">SMTP Host</label>
            <input type="text" name="smtp_host" value="{{ old('smtp_host', 'smtp.gmail.com') }}" class="form-input">
          </div>
          <div>
            <label class="form-label">SMTP Port</label>
            <input type="number" name="smtp_port" value="{{ old('smtp_port', 465) }}" class="form-input">
          </div>
          <div>
            <label class="form-label">SMTP Encryption</label>
            <select name="smtp_encryption" class="form-input">
              <option value="ssl" @selected(old('smtp_encryption', 'ssl') === 'ssl')>SSL</option>
              <option value="tls" @selected(old('smtp_encryption') === 'tls')>TLS</option>
              <option value="none" @selected(old('smtp_encryption') === 'none')>None</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="form-label">Username / Email</label>
            <input type="text" name="username" value="{{ old('username') }}" class="form-input" placeholder="your@gmail.com">
          </div>
          <div>
            <label class="form-label">Password / App Password</label>
            <input type="password" name="password" class="form-input" placeholder="App-specific password">
            <p class="text-xs text-slate-400 mt-1">For Gmail, use an <a href="https://myaccount.google.com/apppasswords" target="_blank" class="text-indigo-500 hover:underline">App Password</a>.</p>
          </div>
        </div>

        <div class="flex justify-end pt-2">
          <button type="submit" class="btn btn-primary">Connect via IMAP</button>
        </div>
      </form>
    </div>

    {{-- Google Apps Script Form --}}
    <div x-show="tab==='script'" x-transition>

      {{-- Instructions panel --}}
      <div class="mb-6 rounded-xl border-l-4 border-indigo-500 bg-white dark:bg-slate-800 shadow-sm p-5 space-y-3">
        <p class="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
          <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          How to set up Google Apps Script (Step by step)
        </p>
        <ol class="text-sm text-slate-700 dark:text-slate-300 space-y-2 list-decimal list-inside leading-relaxed">
          <li>Go to <a href="https://script.google.com" target="_blank" class="text-indigo-600 underline font-semibold hover:text-indigo-700">script.google.com</a> and click <strong>New Project</strong></li>
          <li>Delete all existing code and paste the script below</li>
          <li>Replace <code class="bg-slate-800 text-green-400 text-xs px-1.5 py-0.5 rounded font-mono">YOUR_WEBHOOK_URL</code> with:<br>
              <code class="bg-slate-800 text-yellow-300 text-xs px-1.5 py-0.5 rounded font-mono break-all">{{ url('/webhook/google-script') }}</code></li>
          <li>Replace <code class="bg-slate-800 text-green-400 text-xs px-1.5 py-0.5 rounded font-mono">YOUR_SECRET</code> with any password you choose (you'll enter the same one below)</li>
          <li>Click <strong>Save (💾)</strong>, then go to <strong>Triggers (⏰) → Add Trigger</strong></li>
          <li>Set function: <code class="bg-slate-800 text-green-400 text-xs px-1.5 py-0.5 rounded font-mono">syncNewEmails</code> · Event source: <strong>Time-driven</strong> · Type: <strong>Minutes timer → Every minute</strong></li>
          <li>Add a second trigger for function: <code class="bg-slate-800 text-green-400 text-xs px-1.5 py-0.5 rounded font-mono">syncTrashedEmails</code> · Event source: <strong>Time-driven</strong> · Type: <strong>Minutes timer → Every 5 minutes</strong></li>
          <li>Click <strong>Save</strong> and grant Gmail permissions when prompted</li>
        </ol>
      </div>

      {{-- Script code to copy --}}
      <div class="mb-6">
        <div class="flex items-center justify-between mb-2">
          <label class="form-label mb-0">Google Apps Script Code</label>
          <button type="button" id="copy-script-btn" onclick="copyScript()"
                  class="text-xs text-indigo-600 hover:text-indigo-700 font-medium flex items-center gap-1 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            Copy Code
          </button>
        </div>
        <pre id="apps-script-code" class="text-xs bg-slate-900 text-slate-100 rounded-xl p-4 overflow-x-auto leading-relaxed">{{ "const WEBHOOK_URL = 'YOUR_WEBHOOK_URL'; // Replace with: " . url('/webhook/google-script') . "
const SECRET     = 'YOUR_SECRET';       // Replace with your chosen secret password
const LABEL_NAME = 'dgt-synced';

function syncNewEmails() {
  const label   = getOrCreateLabel(LABEL_NAME);
  const threads = GmailApp.search('in:inbox -label:' + LABEL_NAME, 0, 20);

  threads.forEach(function(thread) {
    const msg = thread.getMessages()[0];
    const payload = {
      secret:    SECRET,
      from:      msg.getFrom(),
      to:        msg.getTo(),
      subject:   msg.getSubject(),
      body_text: msg.getPlainBody().substring(0, 5000),
      body_html: msg.getBody().substring(0, 20000),
      date:      msg.getDate().toISOString(),
      account:   Session.getEffectiveUser().getEmail(),
      message_id: msg.getId(),
    };
    try {
      UrlFetchApp.fetch(WEBHOOK_URL, {
        method:      'post',
        contentType: 'application/json',
        payload:     JSON.stringify(payload),
        muteHttpExceptions: true,
      });
      thread.addLabel(label);
    } catch(e) {
      Logger.log('Error sending email: ' + e.message);
    }
  });
}

function syncTrashedEmails() {
  const trashedIds = [];
  const threads = GmailApp.search('in:trash', 0, 50);
  
  threads.forEach(function(thread) {
    const messages = thread.getMessages();
    messages.forEach(function(msg) {
      trashedIds.push(msg.getId());
    });
  });
  
  if (trashedIds.length > 0) {
    const payload = {
      secret: SECRET,
      account: Session.getEffectiveUser().getEmail(),
      trashed_ids: trashedIds
    };
    
    try {
      UrlFetchApp.fetch(WEBHOOK_URL + '/sync-trash', {
        method: 'post',
        contentType: 'application/json',
        payload: JSON.stringify(payload),
        muteHttpExceptions: true,
      });
    } catch(e) {
      Logger.log('Error syncing trash: ' + e.message);
    }
  }
}

function getOrCreateLabel(name) {
  return GmailApp.getUserLabelByName(name) || GmailApp.createLabel(name);
}" }}</pre>
      </div>

      {{-- Google Script Account Form --}}
      <form method="POST" action="{{ route('admin.emails.accounts.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="provider" value="google_script">

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="form-label">Account Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}" class="form-input" placeholder="e.g. My Gmail" required>
            @error('name')<p class="form-error">{{ $message }}</p>@enderror
          </div>
          <div>
            <label class="form-label">Gmail Address <span class="text-red-500">*</span></label>
            <input type="email" name="email_address" value="{{ old('email_address') }}" class="form-input" placeholder="you@gmail.com" required>
            @error('email_address')<p class="form-error">{{ $message }}</p>@enderror
          </div>
        </div>

        <div>
          <label class="form-label">Webhook Secret <span class="text-red-500">*</span></label>
          <input type="text" name="password" value="{{ old('password') }}" class="form-input font-mono" placeholder="Same secret you put in the Google Script" required>
          <p class="text-xs text-slate-400 mt-1">This must match the <code>SECRET</code> value in your Google Apps Script code above.</p>
          @error('password')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div class="flex justify-end pt-2">
          <button type="submit" class="btn btn-primary flex items-center gap-2">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#fff"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#fff"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#fff"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#fff"/></svg>
            Connect via Google Script
          </button>
        </div>
      </form>
    </div>
  </div>

</div>

<script>
function copyScript() {
  const code = document.getElementById('apps-script-code').innerText;
  navigator.clipboard.writeText(code).then(() => {
    const btn = document.getElementById('copy-script-btn');
    btn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Copied!';
    btn.classList.replace('text-indigo-600', 'text-emerald-600');
    setTimeout(() => {
      btn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg> Copy Code';
      btn.classList.replace('text-emerald-600', 'text-indigo-600');
    }, 2000);
  });
}
</script>
@endsection
