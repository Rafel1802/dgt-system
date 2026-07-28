<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>Task Approved — KIUQ SYSTEM</title>
<style>
  body { margin:0; padding:0; background:#f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; -webkit-font-smoothing: antialiased; }
  .email-wrapper { background:#f1f5f9; padding: 32px 16px; }
  .email-container { max-width: 600px; margin: 0 auto; }
  .email-header { background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #2563eb 100%); border-radius: 16px 16px 0 0; padding: 36px 40px; text-align: center; }
  .email-logo { display: inline-flex; align-items: center; gap: 10px; margin-bottom: 16px; }
  .email-logo-icon { width: 42px; height: 42px; background: rgba(255,255,255,0.2); border-radius: 10px; display: inline-block; line-height: 42px; font-size: 20px; }
  .email-logo-text { color: white; font-size: 18px; font-weight: 700; letter-spacing: -0.02em; }
  .email-hero-icon { width: 64px; height: 64px; background: rgba(255,255,255,0.15); border-radius: 50%; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; font-size: 28px; line-height: 64px; text-align: center; }
  .email-hero-title { color: white; font-size: 22px; font-weight: 700; margin: 0 0 8px; }
  .email-hero-sub { color: rgba(255,255,255,0.75); font-size: 14px; margin: 0; }

  .email-body { background: white; padding: 40px; }
  .greeting { font-size: 16px; font-weight: 600; color: #1e293b; margin: 0 0 16px; }
  .email-p { font-size: 14px; color: #475569; line-height: 1.65; margin: 0 0 20px; }

  .task-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px 24px; margin: 24px 0; }
  .task-card-title { font-size: 16px; font-weight: 700; color: #1e293b; margin: 0 0 12px; }
  .task-meta { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
  .task-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
  .badge-label   { background: #e0e7ff; color: #4338ca; }
  .badge-urgent  { background: #fee2e2; color: #dc2626; }
  .badge-high    { background: #fef3c7; color: #b45309; }
  .badge-medium  { background: #e0f2fe; color: #0369a1; }
  .badge-low     { background: #f1f5f9; color: #64748b; }
  .badge-approved{ background: #d1fae5; color: #065f46; }

  .task-row { display: flex; gap: 8px; align-items: flex-start; margin-bottom: 8px; font-size: 13px; }
  .task-row-label { color: #94a3b8; font-weight: 600; min-width: 100px; flex-shrink: 0; }
  .task-row-value { color: #334155; font-weight: 500; }

  .desc-box { background: #f0f9ff; border-left: 3px solid #6366f1; border-radius: 0 8px 8px 0; padding: 12px 16px; margin: 16px 0; font-size: 13px; color: #475569; line-height: 1.6; }

  .cta-section { text-align: center; margin: 32px 0; }
  .cta-button { display: inline-block; background: linear-gradient(135deg, #4f46e5, #7c3aed); color: white !important; text-decoration: none; padding: 14px 36px; border-radius: 10px; font-size: 15px; font-weight: 700; letter-spacing: -0.01em; box-shadow: 0 4px 12px rgba(99,102,241,0.4); }

  .divider { border: none; border-top: 1px solid #e2e8f0; margin: 28px 0; }

  .email-footer { background: #f8fafc; border-top: 1px solid #e2e8f0; border-radius: 0 0 16px 16px; padding: 24px 40px; text-align: center; }
  .footer-text { font-size: 12px; color: #94a3b8; line-height: 1.6; margin: 0; }
  .footer-link { color: #6366f1; text-decoration: none; }
</style>
</head>
<body>
<div class="email-wrapper">
<div class="email-container">

  <!-- Header -->
  <div class="email-header">
    <div class="email-logo">
      <span class="email-logo-icon">⬡</span>
      <span class="email-logo-text">KIUQ SYSTEM</span>
    </div>
    <div class="email-hero-icon">✅</div>
    <h1 class="email-hero-title">Task Approved!</h1>
    <p class="email-hero-sub">A task has been approved and is ready for review</p>
  </div>

  <!-- Body -->
  <div class="email-body">
    <p class="greeting">Hello {{ $notifiable->name }},</p>

    <p class="email-p">
      A task in the <strong>KIUQ SYSTEM</strong> has been approved by your supervisor and is ready for your review.
      Here are the full details:
    </p>

    <!-- Task Card -->
    <div class="task-card">
      <p class="task-card-title">{{ $card->title }}</p>

      <div class="task-meta">
        <span class="task-badge badge-approved">✓ Approved</span>
        <span class="task-badge badge-label">{{ $card->label }}{{ $card->sub_label ? ' → ' . $card->sub_label : '' }}</span>
        @php $priorityClass = match($card->priority?->value ?? $card->priority) { 'urgent' => 'urgent', 'high' => 'high', 'low' => 'low', default => 'medium' }; @endphp
        <span class="task-badge badge-{{ $priorityClass }}">{{ ucfirst($card->priority?->value ?? $card->priority ?? 'medium') }} Priority</span>
      </div>

      <div class="task-row">
        <span class="task-row-label">Approved by</span>
        <span class="task-row-value">{{ $approvedBy->name }}</span>
      </div>
      <div class="task-row">
        <span class="task-row-label">Approved at</span>
        <span class="task-row-value">{{ $card->approved_at?->format('d M Y, H:i') ?? now()->format('d M Y, H:i') }}</span>
      </div>
      <div class="task-row">
        <span class="task-row-label">Created by</span>
        <span class="task-row-value">{{ $card->creator?->name ?? '—' }}</span>
      </div>
      @if($card->deadline)
      <div class="task-row">
        <span class="task-row-label">Deadline</span>
        <span class="task-row-value" style="{{ $card->isOverdue() ? 'color:#dc2626;font-weight:700;' : '' }}">
          {{ $card->deadline->format('d M Y') }}{{ $card->isOverdue() ? ' ⚠ OVERDUE' : '' }}
        </span>
      </div>
      @endif
      @if($card->assignees->isNotEmpty())
      <div class="task-row">
        <span class="task-row-label">Assignees</span>
        <span class="task-row-value">{{ $card->assignees->pluck('name')->join(', ') }}</span>
      </div>
      @endif

      @if($card->description)
      <div class="desc-box">{{ Str::limit($card->description, 300) }}</div>
      @endif
    </div>

    <div class="cta-section">
      <a href="{{ url('/kanban') }}" class="cta-button">View on Kanban Board →</a>
    </div>

    <hr class="divider">

    <p class="email-p" style="font-size:13px; color:#94a3b8; margin:0;">
      This is an automated notification from KIUQ SYSTEM. The task status has been updated to
      <strong style="color:#059669;">Approved</strong>. No action required from you unless further follow-up is needed.
    </p>
  </div>

  <!-- Footer -->
  <div class="email-footer">
    <p class="footer-text">
      © {{ date('Y') }} KIUQ SYSTEM — Digital & CRM Management<br>
      <a href="{{ url('/') }}" class="footer-link">Open System</a> &bull;
      <a href="{{ url('/kanban') }}" class="footer-link">Kanban Board</a>
    </p>
  </div>

</div>
</div>
</body>
</html>
