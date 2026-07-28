@php
  use App\Enums\CardStatus;
  use App\Enums\CardPriority;
  $priority = CardPriority::tryFrom($card->priority?->value ?? $card->priority ?? 'medium');
  $isOverdue = $card->isOverdue();
  $progress  = $card->checklistProgress();
  $isSoon    = $card->deadline && $card->deadline->isFuture() && $card->deadline->diffInDays(now()) <= 2;
@endphp

<div class="k-card"
     data-card-id="{{ $card->id }}"
     id="card-{{ $card->id }}"
     @click="openCard({{ $card->id }})"
     x-show="isCardVisible({{ $card->toJson() }})"
     role="button"
     tabindex="0"
     @keydown.enter="openCard({{ $card->id }})">

  {{-- Drag handle --}}
  <div class="card-drag-handle" @click.stop title="Drag to move">
    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5"/>
    </svg>
  </div>

  {{-- Label badge --}}
  <div class="k-card-label" style="background: {{ $card->label_bg }}; color: {{ $card->label_color }};">
    {{ $card->label }}
    @if($card->sub_label)
      <span class="opacity-60">→ {{ $card->sub_label }}</span>
    @endif
  </div>

  {{-- Title --}}
  <p class="k-card-title">{{ Str::limit($card->title, 80) }}</p>

  {{-- Priority + Deadline row --}}
  <div class="k-card-meta">
    @if($priority)
    <span class="badge {{ $priority->badgeClass() }}" style="font-size:0.62rem;">
      {{ $priority->label() }}
    </span>
    @endif

    @if($card->deadline)
    <span class="k-card-deadline {{ $isOverdue ? 'overdue' : ($isSoon ? 'soon' : '') }}">
      <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5"/>
      </svg>
      {{ $card->deadline->format('d M') }}
    </span>
    @endif

    {{-- Assignee avatars --}}
    @if($card->assignees->isNotEmpty())
    <div class="k-card-avatars">
      @foreach($card->assignees->take(3) as $assignee)
        <img src="{{ $assignee->avatar_url }}" alt="{{ $assignee->name }}" class="k-card-avatar" title="{{ $assignee->name }}">
      @endforeach
      @if($card->assignees->count() > 3)
        <span class="k-card-avatar flex items-center justify-center text-[9px] font-bold bg-indigo-100 text-indigo-600">+{{ $card->assignees->count() - 3 }}</span>
      @endif
    </div>
    @endif
  </div>

  {{-- Checklist progress bar --}}
  @if($progress['total'] > 0)
  <div class="progress-bar mt-2">
    <div class="progress-bar-fill" style="width: {{ $progress['percent'] }}%"></div>
  </div>
  @endif

  {{-- Footer stats --}}
  <div class="k-card-footer">
    @if($card->comments->count())
    <span class="k-card-footer-item">
      <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z"/>
      </svg>
      {{ $card->comments->count() }}
    </span>
    @endif

    @if($card->files->count())
    <span class="k-card-footer-item">
      <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13"/>
      </svg>
      {{ $card->files->count() }}
    </span>
    @endif

    @if($progress['total'] > 0)
    <span class="k-card-footer-item">
      <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
      </svg>
      {{ $progress['done'] }}/{{ $progress['total'] }}
    </span>
    @endif

    <span class="k-card-footer-item ml-auto text-slate-300 text-[10px]">#{{ $card->id }}</span>
  </div>

</div>
