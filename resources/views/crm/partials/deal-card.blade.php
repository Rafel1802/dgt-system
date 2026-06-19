@php
  $stage = $deal->stage;
  $isOverdue = $deal->expected_close_date?->isPast() && $deal->isActive();
@endphp
<div class="k-card"
     data-deal-id="{{ $deal->id }}"
     id="deal-{{ $deal->id }}">

  <div class="card-drag-handle" title="Drag">
    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5"/></svg>
  </div>

  {{-- Customer --}}
  <div class="flex items-center gap-2 mb-2">
    <img src="{{ $deal->customer?->avatar_url }}" alt="{{ $deal->customer?->name }}" class="avatar" style="width:22px;height:22px;">
    <div class="flex-1 min-w-0">
      <p class="text-xs font-semibold text-slate-700 truncate">{{ $deal->customer?->name }}</p>
      @if($deal->customer?->company)
        <p class="text-[10px] text-slate-400 truncate">{{ $deal->customer->company }}</p>
      @endif
    </div>
  </div>

  {{-- Title --}}
  <p class="text-sm font-semibold text-slate-800 leading-snug mb-2">{{ Str::limit($deal->title, 60) }}</p>

  {{-- Value --}}
  <div class="flex items-center justify-between mb-2">
    <span class="text-base font-bold" style="color: {{ $stage?->color() }}">${{ number_format($deal->value, 0) }}</span>
    <span class="text-xs text-slate-400">{{ $deal->probability }}% likely</span>
  </div>

  {{-- Weighted value --}}
  <div class="progress-bar mb-2">
    <div class="progress-bar-fill" style="width:{{ $deal->probability }}%; background: {{ $stage?->color() }}88"></div>
  </div>

  <div class="k-card-footer">
    @if($deal->expected_close_date)
      <span class="k-card-footer-item {{ $isOverdue ? 'text-red-500 font-semibold' : '' }}">
        📅 {{ $deal->expected_close_date->format('d M') }}
      </span>
    @endif
    @if($deal->assignee)
      <img src="{{ $deal->assignee->avatar_url }}" class="k-card-avatar ml-auto" title="{{ $deal->assignee->name }}" style="width:18px;height:18px;">
    @endif
  </div>
</div>
