<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Task Needs Revision — DGT System</title>
<style>
  body { margin:0; padding:0; background:#f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
  .email-wrapper { background:#f1f5f9; padding: 32px 16px; }
  .email-container { max-width:600px; margin:0 auto; }
  .email-header { background: linear-gradient(135deg, #dc2626 0%, #9f1239 100%); border-radius: 16px 16px 0 0; padding: 36px 40px; text-align: center; }
  .email-logo-text { color:white; font-size:18px; font-weight:700; display:block; margin-bottom:16px; }
  .email-hero-icon { font-size:40px; margin-bottom:12px; }
  .email-hero-title { color:white; font-size:22px; font-weight:700; margin:0 0 8px; }
  .email-hero-sub { color:rgba(255,255,255,0.8); font-size:14px; margin:0; }
  .email-body { background:white; padding:40px; }
  .greeting { font-size:16px; font-weight:600; color:#1e293b; margin:0 0 16px; }
  .email-p { font-size:14px; color:#475569; line-height:1.65; margin:0 0 20px; }
  .task-card { background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:20px 24px; margin:24px 0; }
  .task-card-title { font-size:16px; font-weight:700; color:#1e293b; margin:0 0 12px; }
  .task-row { display:flex; gap:8px; margin-bottom:8px; font-size:13px; }
  .task-row-label { color:#94a3b8; font-weight:600; min-width:100px; flex-shrink:0; }
  .task-row-value { color:#334155; font-weight:500; }
  .reason-box { background:#fef2f2; border:1px solid #fecaca; border-left:4px solid #ef4444; border-radius:0 10px 10px 0; padding:16px 20px; margin:20px 0; }
  .reason-label { font-size:12px; font-weight:700; color:#dc2626; text-transform:uppercase; letter-spacing:0.05em; margin:0 0 8px; }
  .reason-text { font-size:14px; color:#7f1d1d; line-height:1.6; margin:0; }
  .steps-box { background:#f0f9ff; border-radius:10px; padding:20px 24px; margin:20px 0; }
  .steps-title { font-size:13px; font-weight:700; color:#1e40af; margin:0 0 12px; }
  .step { display:flex; gap:10px; margin-bottom:10px; font-size:13px; color:#334155; }
  .step-num { width:22px; height:22px; background:#6366f1; color:white; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; flex-shrink:0; line-height:22px; text-align:center; }
  .cta-section { text-align:center; margin:28px 0; }
  .cta-button { display:inline-block; background:linear-gradient(135deg,#4f46e5,#7c3aed); color:white !important; text-decoration:none; padding:14px 36px; border-radius:10px; font-size:15px; font-weight:700; }
  .email-footer { background:#f8fafc; border-top:1px solid #e2e8f0; border-radius:0 0 16px 16px; padding:24px 40px; text-align:center; }
  .footer-text { font-size:12px; color:#94a3b8; line-height:1.6; margin:0; }
  .footer-link { color:#6366f1; text-decoration:none; }
</style>
</head>
<body>
<div class="email-wrapper">
<div class="email-container">

  <div class="email-header">
    <span class="email-logo-text">⬡ DGT System</span>
    <div class="email-hero-icon">❌</div>
    <h1 class="email-hero-title">Task Needs Revision</h1>
    <p class="email-hero-sub">Your supervisor has returned this task for changes</p>
  </div>

  <div class="email-body">
    <p class="greeting">Hello {{ $notifiable->name }},</p>

    <p class="email-p">
      Your task has been reviewed and returned for revision. Please read the feedback below carefully,
      make the necessary changes, and resubmit the task for approval.
    </p>

    <div class="task-card">
      <p class="task-card-title">{{ $card->title }}</p>
      <div class="task-row">
        <span class="task-row-label">Reviewed by</span>
        <span class="task-row-value">{{ $rejectedBy->name }}</span>
      </div>
      <div class="task-row">
        <span class="task-row-label">Reviewed at</span>
        <span class="task-row-value">{{ $card->reviewed_at?->format('d M Y, H:i') ?? now()->format('d M Y, H:i') }}</span>
      </div>
      <div class="task-row">
        <span class="task-row-label">Label</span>
        <span class="task-row-value">{{ $card->label }}{{ $card->sub_label ? ' → ' . $card->sub_label : '' }}</span>
      </div>
    </div>

    <!-- Rejection Reason -->
    <div class="reason-box">
      <p class="reason-label">📝 Feedback / Reason for Revision</p>
      <p class="reason-text">{{ $reason }}</p>
    </div>

    <!-- Next Steps -->
    <div class="steps-box">
      <p class="steps-title">💡 What to do next:</p>
      <div class="step">
        <span class="step-num">1</span>
        <span>Open the task on the Kanban board using the button below.</span>
      </div>
      <div class="step">
        <span class="step-num">2</span>
        <span>Review the feedback and update the task details accordingly.</span>
      </div>
      <div class="step">
        <span class="step-num">3</span>
        <span>Move the task back to <strong>In Progress</strong> → make changes → then submit for review again.</span>
      </div>
    </div>

    <div class="cta-section">
      <a href="{{ url('/kanban') }}" class="cta-button">Open Task on Kanban Board →</a>
    </div>

    <p class="email-p" style="font-size:13px; color:#94a3b8; margin:0;">
      This is an automated notification from DGT System. If you have questions, contact your supervisor directly.
    </p>
  </div>

  <div class="email-footer">
    <p class="footer-text">
      © {{ date('Y') }} DGT System — Digital Team & CRM Management<br>
      <a href="{{ url('/') }}" class="footer-link">Open System</a> &bull;
      <a href="{{ url('/kanban') }}" class="footer-link">Kanban Board</a>
    </p>
  </div>

</div>
</div>
</body>
</html>
