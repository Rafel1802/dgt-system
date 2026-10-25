@php
    $templateStyle = \App\Models\Setting::where('key', 'maintenance_template_style')->value('value') ?? 'custom';
    $site_name = \App\Models\Setting::where('key', 'maintenance_site_name')->value('value');
    if (!$site_name || $site_name === 'MyWebsite') {
        $site_name = 'KiuQ.com';
    }

    // Headline / Title selection
    $titlePreset = \App\Models\Setting::where('key', 'maintenance_title_preset')->value('value') ?: 'Under Maintenance';
    $titleCustom = \App\Models\Setting::where('key', 'maintenance_title_custom')->value('value') ?: '';
    $maintenance_title = \App\Models\Setting::where('key', 'maintenance_title')->value('value');
    if (!$maintenance_title) {
        $maintenance_title = ($titlePreset === 'other' && !empty($titleCustom)) ? $titleCustom : $titlePreset;
    }

    $maintenance_message = \App\Models\Setting::where('key', 'maintenance_message')->value('value') 
        ?: "We're currently performing scheduled maintenance to improve system stability and performance.";
    $estimated_time = \App\Models\Setting::where('key', 'maintenance_time')->value('value') 
        ?: "We'll be back shortly!";
    $contact_email = \App\Models\Setting::where('key', 'maintenance_email')->value('value') 
        ?: 'digital@typhonmachinery.com';

    // Intelligent word splitting for dynamic dual-tone headline
    $trimmedTitle = trim($maintenance_title);
    $titleParts = preg_split('/\s+/', $trimmedTitle, 2);
    $firstWord = $titleParts[0] ?? $trimmedTitle;
    $secondPart = $titleParts[1] ?? '';
@endphp

@if($templateStyle === 'custom')
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $site_name }} — {{ $maintenance_title }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    
    :root {
      --bg-base:      #030712;
      --bg-dark:      #061126;
      --navy:         #071b3b;
      --blue:         #1d4ed8;
      --sky:          #38bdf8;
      --cyan:         #22d3ee;
      --indigo:       #6366f1;
      --purple:       #a855f7;
      --crimson:      #e11d48;
      --rose:         #f43f5e;
      --coral:        #fb7185;
      --amber:        #f59e0b;
      --emerald:      #10b981;
      --white:        #ffffff;
      --text-dim:     #94a3b8;
      --text-light:   #e2e8f0;
      --glass-bg:     rgba(15, 23, 42, 0.65);
      --glass-border: rgba(56, 189, 248, 0.2);
    }

    html, body {
      min-height: 100%;
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
      background: var(--bg-base);
      color: var(--white);
      overflow-x: hidden;
      position: relative;
    }

    /* ── Cosmic Ambient Background ── */
    .cosmic-canvas {
      position: fixed; inset: 0; z-index: 0; pointer-events: none;
      background: radial-gradient(circle at 50% 20%, #0c2554 0%, transparent 60%),
                  radial-gradient(ellipse 80% 50% at 20% 80%, rgba(225, 29, 72, 0.15) 0%, transparent 55%),
                  radial-gradient(ellipse 60% 40% at 80% 85%, rgba(99, 102, 241, 0.18) 0%, transparent 50%),
                  var(--bg-base);
    }

    .cyber-grid {
      position: fixed; inset: 0; z-index: 0; pointer-events: none;
      background-image: 
        linear-gradient(rgba(56, 189, 248, 0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(56, 189, 248, 0.04) 1px, transparent 1px);
      background-size: 50px 50px;
      mask-image: radial-gradient(ellipse 80% 70% at 50% 50%, black 20%, transparent 80%);
      -webkit-mask-image: radial-gradient(ellipse 80% 70% at 50% 50%, black 20%, transparent 80%);
    }

    /* ── Glowing Aurora Light Orbs ── */
    .aurora-orb {
      position: fixed; border-radius: 50%; filter: blur(90px); pointer-events: none; z-index: 0;
      animation: float-drift 16s ease-in-out infinite alternate;
    }
    .orb-1 { width: 500px; height: 500px; background: rgba(30, 64, 175, 0.35); top: -120px; left: -100px; animation-duration: 18s; }
    .orb-2 { width: 420px; height: 420px; background: rgba(225, 29, 72, 0.25); top: 35%; right: -120px; animation-duration: 22s; animation-delay: -6s; }
    .orb-3 { width: 340px; height: 340px; background: rgba(34, 211, 238, 0.2); bottom: -80px; left: 25%; animation-duration: 15s; animation-delay: -10s; }
    .orb-4 { width: 380px; height: 380px; background: rgba(168, 85, 247, 0.2); bottom: 10%; right: 20%; animation-duration: 20s; animation-delay: -4s; }

    @keyframes float-drift {
      0%   { transform: translate(0, 0) scale(1) rotate(0deg); }
      50%  { transform: translate(40px, 35px) scale(1.15) rotate(10deg); }
      100% { transform: translate(-30px, 50px) scale(0.95) rotate(-5deg); }
    }

    /* ── Floating Stardust Particles ── */
    .particles-layer { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
    .stardust {
      position: absolute; border-radius: 50%;
      animation: particle-rise linear infinite;
      box-shadow: 0 0 10px currentColor;
    }
    @keyframes particle-rise {
      0%   { transform: translateY(0) scale(0.6); opacity: 0; }
      15%  { opacity: 0.8; }
      85%  { opacity: 0.5; }
      100% { transform: translateY(-115vh) scale(1.2); opacity: 0; }
    }

    /* ── Content Layout ── */
    .main-container {
      position: relative; z-index: 1; min-height: 100vh;
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      padding: 3rem 1.5rem 2rem; text-align: center;
      max-width: 900px; margin: 0 auto;
    }

    /* ── Central Emote & Mascot Animation Section ── */
    .mascot-stage {
      position: relative;
      width: 220px; height: 180px;
      margin: 0 auto 1.5rem;
      display: flex; align-items: center; justify-content: center;
    }

    /* Glowing Pulsing Rings */
    .halo-ring {
      position: absolute; border-radius: 50%; pointer-events: none;
    }
    .halo-outer {
      width: 200px; height: 200px;
      border: 2px dashed rgba(56, 189, 248, 0.35);
      animation: spin-clockwise 24s linear infinite;
    }
    .halo-mid {
      width: 160px; height: 160px;
      border: 1.5px solid transparent;
      border-top-color: var(--rose);
      border-bottom-color: var(--sky);
      animation: spin-counter 8s linear infinite;
    }
    .halo-glow {
      width: 140px; height: 140px;
      background: radial-gradient(circle, rgba(56, 189, 248, 0.25) 0%, rgba(225, 29, 72, 0.15) 50%, transparent 75%);
      filter: blur(12px);
      animation: pulse-aura 3s ease-in-out infinite alternate;
    }

    @keyframes spin-clockwise { to { transform: rotate(360deg); } }
    @keyframes spin-counter   { to { transform: rotate(-360deg); } }
    @keyframes pulse-aura {
      0%   { transform: scale(0.9); opacity: 0.5; }
      100% { transform: scale(1.25); opacity: 0.9; }
    }

    /* Mascot Bot Body */
    .mascot-bot {
      position: relative; z-index: 2;
      width: 105px; height: 105px;
      background: linear-gradient(145deg, #0f274a, #09152b);
      border: 2.5px solid rgba(56, 189, 248, 0.5);
      border-radius: 28px;
      box-shadow: 
        0 15px 35px rgba(0, 0, 0, 0.6),
        0 0 25px rgba(56, 189, 248, 0.35),
        inset 0 2px 4px rgba(255, 255, 255, 0.2);
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      cursor: pointer;
      user-select: none;
      transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s ease;
      animation: bot-hover 4s ease-in-out infinite;
    }
    .mascot-bot:hover {
      transform: scale(1.08) translateY(-4px);
      box-shadow: 0 20px 45px rgba(56, 189, 248, 0.5), 0 0 35px rgba(244, 63, 94, 0.4);
    }
    .mascot-bot:active {
      transform: scale(0.95);
    }

    @keyframes bot-hover {
      0%, 100% { transform: translateY(0px) rotate(0deg); }
      50%      { transform: translateY(-8px) rotate(1.5deg); }
    }

    /* Antenna with Signal Wave */
    .bot-antenna {
      position: absolute; top: -18px; left: 50%; transform: translateX(-50%);
      width: 4px; height: 18px; background: linear-gradient(to top, rgba(56, 189, 248, 0.7), var(--sky));
      border-radius: 2px;
    }
    .antenna-orb {
      position: absolute; top: -9px; left: 50%; transform: translateX(-50%);
      width: 12px; height: 12px; border-radius: 50%;
      background: var(--sky);
      box-shadow: 0 0 12px var(--sky);
      animation: antenna-flash 2s ease-in-out infinite;
    }
    .antenna-ring {
      position: absolute; top: -14px; left: 50%; transform: translateX(-50%);
      width: 22px; height: 22px; border-radius: 50%;
      border: 1.5px solid var(--sky);
      opacity: 0;
      animation: antenna-radar 2s ease-out infinite;
    }
    @keyframes antenna-flash {
      0%, 100% { background: var(--sky); box-shadow: 0 0 8px var(--sky); }
      50%      { background: var(--rose); box-shadow: 0 0 16px var(--rose); }
    }
    @keyframes antenna-radar {
      0%   { transform: translateX(-50%) scale(0.4); opacity: 0.9; }
      100% { transform: translateX(-50%) scale(2.2); opacity: 0; }
    }

    /* Screen & Animated Face Emotes */
    .bot-screen {
      width: 76px; height: 50px;
      background: #020814;
      border: 1.5px solid rgba(56, 189, 248, 0.3);
      border-radius: 14px;
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      padding: 4px;
      position: relative;
      overflow: hidden;
    }
    .bot-screen::after {
      content: ''; position: absolute; inset: 0;
      background: linear-gradient(180deg, transparent 50%, rgba(34, 211, 238, 0.08) 51%);
      background-size: 100% 4px; pointer-events: none;
    }

    /* Digital Emote Eyes */
    .eyes-row {
      display: flex; align-items: center; justify-content: center; gap: 14px;
      margin-bottom: 3px;
    }
    .eye {
      width: 11px; height: 11px;
      background: var(--sky);
      border-radius: 50%;
      box-shadow: 0 0 8px var(--sky);
      animation: eye-blink 4s infinite;
      transition: all 0.2s ease;
    }
    @keyframes eye-blink {
      0%, 94%, 98%, 100% { transform: scaleY(1); opacity: 1; }
      96%                { transform: scaleY(0.1); opacity: 0.7; }
    }

    /* Cheeks */
    .cheeks-row {
      display: flex; justify-content: space-between; width: 44px;
      position: absolute; bottom: 7px;
    }
    .cheek {
      width: 6px; height: 4px;
      background: rgba(244, 63, 94, 0.7);
      border-radius: 50%;
      box-shadow: 0 0 4px rgba(244, 63, 94, 0.6);
      animation: cheek-pulse 2s ease-in-out infinite alternate;
    }
    @keyframes cheek-pulse {
      0%   { opacity: 0.5; transform: scale(0.9); }
      100% { opacity: 1; transform: scale(1.1); }
    }

    /* Smile mouth */
    .bot-mouth {
      width: 12px; height: 5px;
      border-bottom: 2px solid var(--sky);
      border-radius: 0 0 8px 8px;
      margin-top: 1px;
    }

    /* Floating Orbiting Emotes around Mascot */
    .orbit-emote {
      position: absolute; z-index: 3;
      padding: 6px 9px;
      background: rgba(15, 23, 42, 0.75);
      border: 1px solid rgba(255, 255, 255, 0.15);
      border-radius: 999px;
      font-size: 1.05rem;
      backdrop-filter: blur(8px);
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.4);
      user-select: none;
      animation: emote-bob 4s ease-in-out infinite alternate;
      cursor: pointer;
      transition: transform 0.2s ease;
    }
    .orbit-emote:hover { transform: scale(1.3) rotate(8deg); }
    .emote-1 { top: -10px;  left: 0px;   animation-delay: 0s; }
    .emote-2 { top: 15px;   right: -10px; animation-delay: -1.2s; }
    .emote-3 { bottom: 0px; left: -15px;  animation-delay: -2.4s; }
    .emote-4 { bottom: -10px; right: 5px; animation-delay: -3.5s; }

    @keyframes emote-bob {
      0%   { transform: translateY(0px) rotate(-3deg); }
      100% { transform: translateY(-10px) rotate(4deg); }
    }

    /* Live Mascot Speech / Emote Bubble */
    .speech-bubble {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 7px 18px;
      background: rgba(15, 23, 42, 0.85);
      border: 1px solid rgba(56, 189, 248, 0.35);
      border-radius: 999px;
      font-size: 0.82rem;
      font-weight: 600;
      color: var(--sky);
      margin-bottom: 1.5rem;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4), inset 0 1px 1px rgba(255,255,255,0.1);
      backdrop-filter: blur(10px);
      animation: fade-slide-up 0.7s ease both;
    }
    .speech-bubble .live-dot {
      width: 8px; height: 8px; border-radius: 50%;
      background: var(--sky);
      box-shadow: 0 0 8px var(--sky);
      animation: pulse-dot 1.5s infinite;
    }
    @keyframes pulse-dot {
      0%, 100% { opacity: 1; transform: scale(1); }
      50%      { opacity: 0.3; transform: scale(0.7); }
    }

    /* ── Eyebrow / Brand Pill ── */
    .brand-eyebrow {
      font-family: 'JetBrains Mono', monospace;
      font-size: 0.8rem;
      font-weight: 700;
      letter-spacing: 0.3em;
      text-transform: uppercase;
      color: var(--sky);
      margin-bottom: 0.85rem;
      display: inline-flex; align-items: center; gap: 8px;
      padding: 4px 14px;
      border-radius: 999px;
      background: rgba(56, 189, 248, 0.08);
      border: 1px solid rgba(56, 189, 248, 0.25);
      animation: fade-slide-up 0.8s 0.1s ease both;
    }

    /* ── Breathtaking Dynamic Hero Title ── */
    h1.hero-title {
      font-family: 'Syne', sans-serif;
      font-size: clamp(2.6rem, 8vw, 5.8rem);
      font-weight: 900;
      line-height: 1.02;
      letter-spacing: -0.03em;
      margin-bottom: 1.25rem;
      text-transform: uppercase;
      animation: fade-slide-up 0.8s 0.2s ease both;
      position: relative;
    }
    
    /* Word 1: Radiant Electric Cyan / Sapphire Gradient with animated sheen */
    .word-primary {
      display: inline-block;
      background: linear-gradient(135deg, #a5f3fc 0%, #38bdf8 35%, #2563eb 85%, #6366f1 100%);
      background-size: 200% auto;
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      filter: drop-shadow(0 0 25px rgba(56, 189, 248, 0.45));
      animation: shine-sweep 6s linear infinite;
    }

    /* Word 2: Radiant Coral / Rose / Amber Gradient with glowing shadow */
    .word-secondary {
      display: inline-block;
      background: linear-gradient(135deg, #fda4af 0%, #fb7185 30%, #f43f5e 65%, #e11d48 100%);
      background-size: 200% auto;
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      filter: drop-shadow(0 0 30px rgba(244, 63, 94, 0.45));
      animation: shine-sweep 6s linear infinite reverse;
    }

    @keyframes shine-sweep {
      0%   { background-position: 0% 50%; }
      50%  { background-position: 100% 50%; }
      100% { background-position: 0% 50%; }
    }

    /* Neon Divider Bar */
    .neon-divider {
      width: 90px; height: 4px;
      background: linear-gradient(90deg, var(--sky), var(--rose), var(--purple));
      border-radius: 999px;
      margin: 0 auto 1.75rem;
      box-shadow: 0 0 16px rgba(56, 189, 248, 0.6);
      animation: fade-slide-up 0.9s 0.3s ease both;
    }

    /* ── Subtitle / Maintenance Message Card ── */
    .message-card {
      max-width: 620px;
      margin: 0 auto 2.2rem;
      padding: 1.25rem 1.75rem;
      background: rgba(15, 23, 42, 0.55);
      border: 1px solid rgba(148, 163, 184, 0.18);
      border-radius: 18px;
      backdrop-filter: blur(14px);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
      animation: fade-slide-up 1s 0.4s ease both;
    }
    .message-text {
      font-size: 1.05rem;
      line-height: 1.75;
      color: var(--text-light);
      font-weight: 400;
      margin-bottom: 0.5rem;
    }
    .time-badge {
      display: inline-flex; align-items: center; gap: 6px;
      font-size: 0.88rem;
      font-weight: 600;
      color: #38bdf8;
      background: rgba(56, 189, 248, 0.1);
      padding: 4px 12px;
      border-radius: 8px;
    }

    /* ── Status Pills ── */
    .status-group {
      display: flex; gap: 10px; flex-wrap: wrap; justify-content: center;
      margin-bottom: 2.2rem;
      animation: fade-slide-up 1.1s 0.5s ease both;
    }
    .status-pill {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 7px 16px;
      border-radius: 999px;
      font-size: 0.8rem;
      font-weight: 600;
      letter-spacing: 0.02em;
      backdrop-filter: blur(10px);
      transition: transform 0.2s ease;
    }
    .status-pill:hover { transform: translateY(-2px); }
    
    .pill-sky {
      background: rgba(56, 189, 248, 0.12);
      border: 1px solid rgba(56, 189, 248, 0.35);
      color: #bae6fd;
    }
    .pill-rose {
      background: rgba(244, 63, 94, 0.12);
      border: 1px solid rgba(244, 63, 94, 0.35);
      color: #fecdd3;
    }
    .pill-amber {
      background: rgba(245, 158, 11, 0.12);
      border: 1px solid rgba(245, 158, 11, 0.35);
      color: #fde68a;
    }
    .pill-emerald {
      background: rgba(16, 185, 129, 0.12);
      border: 1px solid rgba(16, 185, 129, 0.35);
      color: #a7f3d0;
    }

    .status-dot {
      width: 7px; height: 7px; border-radius: 50%;
      animation: status-pulse 2s infinite;
    }
    .dot-sky     { background: var(--sky); box-shadow: 0 0 6px var(--sky); }
    .dot-rose    { background: var(--rose); box-shadow: 0 0 6px var(--rose); }
    .dot-amber   { background: var(--amber); box-shadow: 0 0 6px var(--amber); }
    .dot-emerald { background: var(--emerald); box-shadow: 0 0 6px var(--emerald); }

    @keyframes status-pulse {
      0%, 100% { transform: scale(1); opacity: 1; }
      50%      { transform: scale(0.6); opacity: 0.4; }
    }

    /* ── Action Buttons Row ── */
    .actions-group {
      display: flex; gap: 14px; flex-wrap: wrap; justify-content: center;
      margin-bottom: 2.5rem;
      animation: fade-slide-up 1.2s 0.6s ease both;
    }

    .btn-action {
      display: inline-flex; align-items: center; gap: 10px;
      padding: 13px 26px; border-radius: 14px;
      font-size: 0.92rem; font-weight: 700; text-decoration: none;
      transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
      cursor: pointer; position: relative; overflow: hidden;
    }

    .btn-dash {
      background: linear-gradient(135deg, #0284c7 0%, #2563eb 50%, #4f46e5 100%);
      color: #ffffff;
      border: 1px solid rgba(255, 255, 255, 0.25);
      box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4);
    }
    .btn-dash:hover {
      transform: translateY(-3px) scale(1.02);
      box-shadow: 0 12px 32px rgba(37, 99, 235, 0.55);
      border-color: rgba(255, 255, 255, 0.45);
    }

    .btn-contact {
      background: rgba(255, 255, 255, 0.05);
      color: var(--text-light);
      border: 1px solid rgba(148, 163, 184, 0.22);
      backdrop-filter: blur(10px);
    }
    .btn-contact:hover {
      background: rgba(56, 189, 248, 0.12);
      border-color: rgba(56, 189, 248, 0.4);
      color: #ffffff;
      transform: translateY(-3px);
    }

    .btn-refresh {
      background: rgba(255, 255, 255, 0.04);
      color: var(--text-dim);
      border: 1px solid rgba(148, 163, 184, 0.18);
      padding: 13px 20px;
    }
    .btn-refresh:hover {
      background: rgba(255, 255, 255, 0.09);
      color: var(--white);
      transform: translateY(-3px);
    }
    .btn-refresh svg { transition: transform 0.4s ease; }
    .btn-refresh:hover svg { transform: rotate(180deg); }

    /* ── Footer Section ── */
    footer.page-footer {
      position: relative; z-index: 1; padding: 2rem 1.5rem;
      font-size: 0.8rem; color: rgba(148, 163, 184, 0.6);
      text-align: center;
      animation: fade-slide-up 1.3s 0.7s ease both;
    }
    .copyright-brand {
      color: #38bdf8;
      font-weight: 700;
      text-decoration: none;
      transition: color 0.2s ease;
    }
    .copyright-brand:hover {
      color: #ffffff;
      text-decoration: underline;
    }
    .server-timestamp {
      margin-top: 0.45rem;
      font-family: 'JetBrains Mono', monospace;
      font-size: 0.72rem;
      color: rgba(148, 163, 184, 0.4);
    }

    @keyframes fade-slide-up {
      from { opacity: 0; transform: translateY(22px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* Click Burst Confetti / Hearts */
    .burst-particle {
      position: absolute; pointer-events: none; z-index: 50;
      font-size: 1.2rem;
      animation: burst-fly 1.2s cubic-bezier(0.25, 1, 0.5, 1) forwards;
    }
    @keyframes burst-fly {
      0%   { opacity: 1; transform: translate(0, 0) scale(0.6) rotate(0deg); }
      100% { opacity: 0; transform: translate(var(--tx), var(--ty)) scale(1.4) rotate(var(--tr)); }
    }

    @media (max-width: 640px) {
      h1.hero-title { font-size: 2.3rem; }
      .actions-group { flex-direction: column; width: 100%; max-width: 320px; }
      .btn-action { width: 100%; justify-content: center; }
    }
  </style>
</head>
<body>

  {{-- Cosmic Dynamic Background Canvas --}}
  <div class="cosmic-canvas"></div>
  <div class="cyber-grid"></div>
  
  {{-- Floating Ambient Aurora Orbs --}}
  <div class="aurora-orb orb-1"></div>
  <div class="aurora-orb orb-2"></div>
  <div class="aurora-orb orb-3"></div>
  <div class="aurora-orb orb-4"></div>

  {{-- Stardust Floating Particles --}}
  <div class="particles-layer" id="particlesLayer"></div>

  <div class="main-container">

    {{-- Interactive Mascot & Emote Animation Stage --}}
    <div class="mascot-stage" id="mascotStage">
      {{-- Glowing Orbit Rings --}}
      <div class="halo-ring halo-outer"></div>
      <div class="halo-ring halo-mid"></div>
      <div class="halo-ring halo-glow"></div>

      {{-- Floating Orbiting Emotes --}}
      <div class="orbit-emote emote-1" title="Tinkering & Maintenance" onclick="burstLove(event)">🛠️</div>
      <div class="orbit-emote emote-2" title="High Energy Upgrade" onclick="burstLove(event)">⚡</div>
      <div class="orbit-emote emote-3" title="Speed & Performance Boost" onclick="burstLove(event)">🚀</div>
      <div class="orbit-emote emote-4" title="AI & Sync Optimization" onclick="burstLove(event)">✨</div>

      {{-- Interactive Cyber-Bot Mascot --}}
      <div class="mascot-bot" id="mascotBot" onclick="triggerMascotReaction(event)" title="Click me! (づ｡◕‿‿◕｡)づ">
        {{-- Antenna with Radar Waves --}}
        <div class="bot-antenna">
          <div class="antenna-orb"></div>
          <div class="antenna-ring"></div>
        </div>

        {{-- Visor Screen --}}
        <div class="bot-screen">
          <div class="eyes-row">
            <div class="eye" id="leftEye"></div>
            <div class="eye" id="rightEye"></div>
          </div>
          <div class="cheeks-row">
            <div class="cheek"></div>
            <div class="cheek"></div>
          </div>
          <div class="bot-mouth" id="botMouth"></div>
        </div>
      </div>
    </div>

    {{-- Live Cycling Emote Speech Bubble --}}
    <div class="speech-bubble" id="speechBubble">
      <span class="live-dot"></span>
      <span id="speechText">(づ｡◕‿‿◕｡)づ Engineers optimizing system cores...</span>
    </div>

    {{-- Site Brand Eyebrow --}}
    <div class="brand-eyebrow">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
      </svg>
      <span>{{ $site_name }} @if(!empty($module)) &bull; {{ $module }} @endif</span>
    </div>

    {{-- Breathtaking Hero Title (Selectable: Under Maintenance, Under Integrate, or Custom) --}}
    <h1 class="hero-title">
      <span class="word-primary">{{ $firstWord }}</span>
      @if(!empty($secondPart))
        <br>
        <span class="word-secondary">{{ $secondPart }}</span>
      @endif
    </h1>

    {{-- Glowing Neon Divider --}}
    <div class="neon-divider"></div>

    {{-- Maintenance Message & Estimated Time Card --}}
    <div class="message-card">
      @if(!empty($module))
        <div style="display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem; font-weight: 700; color: #fb7185; margin-bottom: 0.6rem; padding: 4px 12px; border-radius: 8px; background: rgba(244, 63, 94, 0.12); border: 1px solid rgba(244, 63, 94, 0.25);">
          <span>🔒</span>
          <span>Module: <strong>{{ $module }}</strong></span>
        </div>
      @endif
      <p class="message-text">
        {{ $maintenance_message }}
      </p>
      @if(!empty($estimated_time))
        <div class="time-badge">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
          </svg>
          <span>{{ $estimated_time }}</span>
        </div>
      @endif
    </div>

    {{-- Diagnostics & Status Badges --}}
    <div class="status-group">
      <span class="status-pill pill-sky">
        <span class="status-dot dot-sky"></span>
        <span>System Upgrade</span>
      </span>
      <span class="status-pill pill-rose">
        <span class="status-dot dot-rose"></span>
        <span>Temporarily Offline</span>
      </span>
      <span class="status-pill pill-emerald">
        <span class="status-dot dot-emerald"></span>
        <span>Auto-Recovery Online</span>
      </span>
      <span class="status-pill pill-amber">
        <span class="status-dot dot-amber"></span>
        <span>Back Soon</span>
      </span>
    </div>

    {{-- Action Controls --}}
    <div class="actions-group">
      <a href="{{ route('dashboard') }}" onclick="window.location.href='{{ route('dashboard') }}'; return false;" class="btn-action btn-dash">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
          <polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
        <span>Back to Dashboard</span>
      </a>

      <a href="mailto:{{ $contact_email }}" class="btn-action btn-contact">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="2" y="4" width="20" height="16" rx="2"/>
          <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
        </svg>
        <span>{{ $contact_email }}</span>
      </a>

      <button type="button" onclick="handleRefresh(this)" class="btn-action btn-refresh" title="Check connection status">
        <svg id="refreshIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        <span>Check Status</span>
      </button>
    </div>

  </div>

  {{-- Page Footer: Strictly KiuQ.com All rights reserved as requested --}}
  <footer class="page-footer">
    <div>
      &copy; {{ date('Y') }} <a href="https://kiuq.com" target="_blank" rel="noopener noreferrer" class="copyright-brand">KiuQ.com</a>. All rights reserved.
    </div>
    <div class="server-timestamp">
      System Heartbeat: {{ date('D, d M Y H:i:s T') }}
    </div>
  </footer>

  <script>
    // ── Generate Stardust Floating Particles ──
    const particlesContainer = document.getElementById('particlesLayer');
    const particleColors = ['#38bdf8', '#60a5fa', '#f43f5e', '#a855f7', '#34d399', '#fde047'];
    if (particlesContainer) {
      for (let i = 0; i < 35; i++) {
        const star = document.createElement('div');
        star.className = 'stardust';
        const size = Math.random() * 4 + 2;
        const color = particleColors[Math.floor(Math.random() * particleColors.length)];
        star.style.width = size + 'px';
        star.style.height = size + 'px';
        star.style.left = (Math.random() * 100) + '%';
        star.style.top = (85 + Math.random() * 25) + '%';
        star.style.background = color;
        star.style.color = color;
        star.style.animationDuration = (8 + Math.random() * 12) + 's';
        star.style.animationDelay = (Math.random() * 10) + 's';
        particlesContainer.appendChild(star);
      }
    }

    // ── Mascot Speech Bubble Phrases ──
    const speechPhrases = [
      "(づ｡◕‿‿◕｡)づ Engineers optimizing system cores...",
      "🛠️ Upgrading database pipelines & speeds...",
      "🔄 Syncing Google Search Console with member records...",
      "✨ Polishing UI aesthetics & performance...",
      "🤖 (•̀ᴗ•́)و Working round the clock for you!",
      "⚡ Almost ready! Hang tight..."
    ];
    let phraseIdx = 0;
    const speechEl = document.getElementById('speechText');
    if (speechEl) {
      setInterval(() => {
        phraseIdx = (phraseIdx + 1) % speechPhrases.length;
        speechEl.style.opacity = '0';
        speechEl.style.transform = 'translateY(5px)';
        speechEl.style.transition = 'all 0.3s ease';
        setTimeout(() => {
          speechEl.textContent = speechPhrases[phraseIdx];
          speechEl.style.opacity = '1';
          speechEl.style.transform = 'translateY(0)';
        }, 300);
      }, 4200);
    }

    // ── Mascot Click Celebration & Emote Reaction ──
    function triggerMascotReaction(e) {
      burstLove(e);
      const bot = document.getElementById('mascotBot');
      const leftEye = document.getElementById('leftEye');
      const rightEye = document.getElementById('rightEye');
      const mouth = document.getElementById('botMouth');

      // Wiggle jump
      bot.style.transform = 'scale(1.2) translateY(-14px)';
      if (leftEye && rightEye) {
        leftEye.style.borderRadius = '2px';
        rightEye.style.borderRadius = '2px';
        leftEye.style.background = '#f43f5e';
        rightEye.style.background = '#f43f5e';
      }
      if (mouth) {
        mouth.style.width = '18px';
        mouth.style.height = '8px';
        mouth.style.borderBottomColor = '#f43f5e';
      }

      setTimeout(() => {
        bot.style.transform = '';
        if (leftEye && rightEye) {
          leftEye.style.borderRadius = '50%';
          rightEye.style.borderRadius = '50%';
          leftEye.style.background = '';
          rightEye.style.background = '';
        }
        if (mouth) {
          mouth.style.width = '';
          mouth.style.height = '';
          mouth.style.borderBottomColor = '';
        }
      }, 700);
    }

    // ── Particle Burst on Click ──
    function burstLove(e) {
      const items = ['💖', '✨', '⚡', '🛠️', '🚀', '⭐', '🤖', '🔮'];
      const rect = e.currentTarget.getBoundingClientRect();
      const originX = rect.left + rect.width / 2;
      const originY = rect.top + rect.height / 2;

      for (let i = 0; i < 10; i++) {
        const p = document.createElement('div');
        p.className = 'burst-particle';
        p.textContent = items[Math.floor(Math.random() * items.length)];
        p.style.left = originX + 'px';
        p.style.top = originY + 'px';

        const angle = (Math.PI * 2 * i) / 10 + (Math.random() - 0.5);
        const distance = 60 + Math.random() * 80;
        const tx = Math.cos(angle) * distance + 'px';
        const ty = Math.sin(angle) * distance - 20 + 'px';
        const tr = (Math.random() * 360 - 180) + 'deg';

        p.style.setProperty('--tx', tx);
        p.style.setProperty('--ty', ty);
        p.style.setProperty('--tr', tr);

        document.body.appendChild(p);
        setTimeout(() => p.remove(), 1200);
      }
    }

    // ── Check Status Button Spinner ──
    function handleRefresh(btn) {
      const icon = document.getElementById('refreshIcon');
      if (icon) {
        icon.style.animation = 'spin-clockwise 0.8s linear infinite';
      }
      btn.disabled = true;
      setTimeout(() => {
        window.location.reload();
      }, 750);
    }
  </script>
</body>
</html>
@else
{{-- ── Original Theme Fallback ── --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $site_name }} — {{ $maintenance_title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-display { font-family: 'Outfit', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-900 min-h-screen flex items-center justify-center p-6 antialiased transition-colors duration-200">
    
    <div class="max-w-2xl w-full text-center">
        <!-- Animated Icon Container -->
        <div class="relative mx-auto w-32 h-32 mb-8">
            <div class="absolute inset-0 bg-sky-200 dark:bg-sky-500/30 rounded-full animate-ping opacity-75" style="animation-duration: 3s;"></div>
            <div class="relative bg-gradient-to-br from-sky-400 to-indigo-600 rounded-full w-full h-full flex items-center justify-center shadow-xl shadow-sky-500/30 dark:shadow-sky-900/40 border-4 border-white dark:border-slate-800">
                <span class="text-4xl animate-bounce">🛠️</span>
            </div>
        </div>

        <h1 class="font-display font-black text-5xl text-slate-800 dark:text-white tracking-tight mb-4">
            {{ $maintenance_title }}
        </h1>
        <p class="text-slate-500 dark:text-slate-400 text-lg mb-8 max-w-lg mx-auto leading-relaxed">
            {{ $maintenance_message }}
        </p>

        @if(!empty($estimated_time))
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-300 font-semibold text-sm mb-8 border border-sky-200 dark:border-sky-800">
                <span>⏱️</span>
                <span>{{ $estimated_time }}</span>
            </div>
        @endif

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <button onclick="window.location.reload()" class="inline-flex items-center justify-center px-6 py-3 rounded-full bg-slate-800 dark:bg-slate-700 text-white font-semibold hover:bg-slate-700 dark:hover:bg-slate-600 hover:shadow-lg transition-all active:scale-95">
                <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Try Again
            </button>
            <a href="{{ route('dashboard') }}" onclick="window.location.href='{{ route('dashboard') }}'; return false;" class="inline-flex items-center justify-center px-6 py-3 rounded-full bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold border border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 hover:shadow-sm transition-all active:scale-95">
                Back to Dashboard
            </a>
        </div>

        <div class="mt-12 text-xs text-slate-400">
            &copy; {{ date('Y') }} <a href="https://kiuq.com" target="_blank" class="font-bold text-sky-500 hover:underline">KiuQ.com</a>. All rights reserved.
        </div>
    </div>
</body>
</html>
@endif
