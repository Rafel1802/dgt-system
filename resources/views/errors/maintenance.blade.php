@php
    $templateStyle = \App\Models\Setting::where('key', 'maintenance_template_style')->value('value') ?? 'original';
@endphp

@if($templateStyle === 'custom')
@php
    $site_name = \App\Models\Setting::where('key', 'maintenance_site_name')->value('value') ?: 'MyWebsite';
    $maintenance_message = \App\Models\Setting::where('key', 'maintenance_message')->value('value') ?: "We're currently performing scheduled maintenance.";
    $estimated_time = \App\Models\Setting::where('key', 'maintenance_time')->value('value') ?: "We'll be back shortly!";
    $contact_email = \App\Models\Setting::where('key', 'maintenance_email')->value('value') ?: 'support@example.com';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $site_name }} — Under Maintenance</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;800&family=DM+Sans:ital,wght@0,300;0,400;1,300&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --navy:    #042C53;
      --blue:    #185FA5;
      --sky:     #378ADD;
      --mist:    #B5D4F4;
      --ice:     #E6F1FB;
      --crimson: #A32D2D;
      --red:     #E24B4A;
      --blush:   #F7C1C1;
      --white:   #FAFBFF;
    }
    html, body {
      min-height: 100%;
      font-family: 'DM Sans', sans-serif;
      background: var(--navy);
      color: var(--white);
      overflow-x: hidden;
    }
    .bg {
      position: fixed; inset: 0; z-index: 0;
      background: radial-gradient(ellipse 80% 60% at 20% 80%, #0c447c55 0%, transparent 60%),
                  radial-gradient(ellipse 60% 50% at 80% 20%, #79202055 0%, transparent 55%),
                  var(--navy);
    }
    .orb {
      position: fixed; border-radius: 50%; filter: blur(70px); opacity: 0.35; pointer-events: none; z-index: 0;
      animation: drift 14s ease-in-out infinite alternate;
    }
    .orb-1 { width: 420px; height: 420px; background: var(--blue);   top: -80px;  left: -80px;  animation-duration: 13s; }
    .orb-2 { width: 320px; height: 320px; background: var(--crimson); top: 30%;    right: -60px; animation-duration: 17s; animation-delay: -5s; }
    .orb-3 { width: 260px; height: 260px; background: var(--sky);     bottom: -60px; left: 30%;  animation-duration: 11s; animation-delay: -8s; }
    .orb-4 { width: 200px; height: 200px; background: var(--red);     bottom: 10%;  right: 20%; animation-duration: 15s; animation-delay: -3s; }
    @keyframes drift { from { transform: translate(0, 0) scale(1); } to { transform: translate(30px, 40px) scale(1.12); } }
    .grid-overlay {
      position: fixed; inset: 0; z-index: 0; pointer-events: none;
      background-image: linear-gradient(rgba(53,138,221,0.06) 1px, transparent 1px), linear-gradient(90deg, rgba(53,138,221,0.06) 1px, transparent 1px);
      background-size: 60px 60px;
    }
    .particles { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
    .particle { position: absolute; border-radius: 50%; animation: float-up linear infinite; opacity: 0; }
    @keyframes float-up { 0% { transform: translateY(0) rotate(0deg); opacity: 0; } 10% { opacity: 0.6; } 90% { opacity: 0.3; } 100% { transform: translateY(-110vh) rotate(720deg); opacity: 0; } }
    .wrapper { position: relative; z-index: 1; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem; text-align: center; }
    .icon-ring { position: relative; width: 120px; height: 120px; margin: 0 auto 2.5rem; }
    .ring { position: absolute; inset: 0; border-radius: 50%; border: 2px solid transparent; }
    .ring-outer { border-color: var(--sky); border-top-color: transparent; animation: spin 3s linear infinite; }
    .ring-inner { inset: 14px; border-color: var(--red); border-bottom-color: transparent; animation: spin 2s linear infinite reverse; }
    .ring-core { inset: 28px; background: rgba(24, 95, 165, 0.25); border: 1.5px solid var(--mist); display: flex; align-items: center; justify-content: center; }
    .gear-svg { width: 36px; height: 36px; animation: spin 8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .eyebrow { font-family: 'DM Sans', sans-serif; font-size: 0.75rem; font-weight: 300; letter-spacing: 0.25em; text-transform: uppercase; color: var(--sky); margin-bottom: 0.75rem; animation: fade-up 0.6s ease both; }
    h1 { font-family: 'Syne', sans-serif; font-size: clamp(2.4rem, 7vw, 5rem); font-weight: 800; line-height: 1.05; letter-spacing: -0.02em; margin-bottom: 1rem; animation: fade-up 0.7s 0.1s ease both; }
    h1 span.red-word { color: var(--red); }
    h1 span.blue-word { color: var(--sky); }
    .subtitle { font-size: 1.1rem; font-weight: 300; color: var(--mist); max-width: 440px; line-height: 1.7; margin: 0 auto 2.5rem; animation: fade-up 0.8s 0.2s ease both; }
    @keyframes fade-up { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }
    .divider { width: 60px; height: 3px; background: linear-gradient(90deg, var(--sky), var(--red)); border-radius: 2px; margin: 0 auto 2.5rem; animation: fade-up 0.9s 0.3s ease both; }
    .status-row { display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; margin-bottom: 3rem; animation: fade-up 1s 0.4s ease both; }
    .pill { display: inline-flex; align-items: center; gap: 7px; padding: 7px 16px; border-radius: 999px; font-size: 0.82rem; font-weight: 400; letter-spacing: 0.03em; backdrop-filter: blur(8px); }
    .pill-blue { background: rgba(55,138,221,0.15); border: 1px solid rgba(55,138,221,0.35); color: var(--mist); }
    .pill-red { background: rgba(226,75,74,0.12); border: 1px solid rgba(226,75,74,0.3); color: var(--blush); }
    .dot { width: 7px; height: 7px; border-radius: 50%; animation: pulse 2s ease-in-out infinite; }
    .dot-blue { background: var(--sky); }
    .dot-red  { background: var(--red); }
    @keyframes pulse { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.4; transform: scale(0.7); } }
    .contact-card { display: inline-flex; align-items: center; gap: 12px; padding: 14px 24px; background: rgba(255,255,255,0.04); border: 1px solid rgba(181,212,244,0.18); border-radius: 12px; font-size: 0.9rem; color: var(--mist); text-decoration: none; transition: background 0.2s, border-color 0.2s, transform 0.2s; animation: fade-up 1.3s 0.7s ease both; backdrop-filter: blur(10px); }
    .contact-card:hover { background: rgba(55,138,221,0.1); border-color: rgba(55,138,221,0.4); transform: translateY(-2px); color: var(--white); }
    .contact-card svg { flex-shrink: 0; opacity: 0.7; }
    
    /* ── Action Buttons ── */
    .action-row {
      display: flex; gap: 16px; flex-wrap: wrap; justify-content: center;
      margin-top: 1.5rem;
      animation: fade-up 1.4s 0.8s ease both;
    }
    .btn {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 14px 26px; border-radius: 12px;
      font-size: 0.95rem; font-weight: 600; text-decoration: none;
      transition: all 0.2s ease;
      border: 1px solid transparent;
      cursor: pointer;
    }
    .btn-primary {
      background: linear-gradient(135deg, var(--sky), var(--blue));
      color: var(--white);
      box-shadow: 0 4px 15px rgba(55, 138, 221, 0.3);
    }
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 22px rgba(55, 138, 221, 0.45);
      border-color: rgba(255,255,255,0.2);
    }

    footer { position: relative; z-index: 1; padding: 1.5rem; font-size: 0.75rem; color: rgba(181,212,244,0.3); letter-spacing: 0.05em; animation: fade-up 1.5s 0.9s ease both; }
    footer strong { color: rgba(181,212,244,0.5); font-weight: 400; }
    .timestamp { margin-top: 0.4rem; font-size: 0.7rem; color: rgba(181,212,244,0.2); }
  </style>
</head>
<body>
  <div class="bg"></div>
  <div class="grid-overlay"></div>
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="orb orb-3"></div>
  <div class="orb orb-4"></div>
  <div class="particles" id="particles"></div>
  <div class="wrapper">
    <div class="icon-ring">
      <div class="ring ring-outer"></div>
      <div class="ring ring-inner"></div>
      <div class="ring ring-core">
        <svg class="gear-svg" viewBox="0 0 24 24" fill="none" stroke="#B5D4F4" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/>
          <path d="M19.622 10.395l-1.097-2.65L20 6l-2-2-1.735 1.483-2.707-1.113L12.935 2h-1.954l-.632 2.401-2.645 1.115L6 4 4 6l1.453 1.789-1.08 2.657L2 11v2l2.373.554 1.098 2.651L4 18l2 2 1.735-1.483 2.707 1.113L11.065 22h1.954l.624-2.401 2.651-1.108L18 20l2-2-1.453-1.789 1.08-2.657L22 13v-2l-2.378-.605Z"/>
        </svg>
      </div>
    </div>
    <p class="eyebrow">{{ $site_name }}</p>
    <h1>
      <span class="blue-word">Under</span><br>
      <span class="red-word">Maintenance</span>
    </h1>
    <div class="divider"></div>
    <p class="subtitle">
      {{ $maintenance_message }}<br>
      {{ $estimated_time }}
    </p>
    <div class="status-row">
      <span class="pill pill-blue"><span class="dot dot-blue"></span>System Upgrade</span>
      <span class="pill pill-red"><span class="dot dot-red"></span>Temporarily Offline</span>
      <span class="pill pill-blue"><span class="dot dot-blue"></span>Back Soon</span>
    </div>
    <div class="action-row">
      <a href="mailto:{{ $contact_email }}" class="contact-card">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <rect x="2" y="4" width="20" height="16" rx="2"/>
          <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
        </svg>
        {{ $contact_email }}
      </a>

      <a href="{{ route('dashboard') }}" class="btn btn-primary">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
          <polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
        Back to Dashboard
      </a>
    </div>
  </div>
  <footer>
    <div>&copy; {{ date('Y') }} <strong>{{ $site_name }}</strong>. All rights reserved.</div>
    <div class="timestamp">Page served: {{ date('D, d M Y H:i:s T') }}</div>
  </footer>
  <script>
    const container = document.getElementById('particles');
    const colors = ['#378ADD','#185FA5','#E24B4A','#A32D2D','#B5D4F4','#F7C1C1'];
    for (let i = 0; i < 28; i++) {
      const p = document.createElement('div');
      p.className = 'particle';
      const size = Math.random() * 5 + 2;
      p.style.cssText = [
        `width:${size}px`, `height:${size}px`,
        `left:${Math.random()*100}%`,
        `top:${90 + Math.random()*20}%`,
        `background:${colors[Math.floor(Math.random()*colors.length)]}`,
        `animation-duration:${6 + Math.random()*10}s`,
        `animation-delay:${Math.random()*8}s`
      ].join(';');
      container.appendChild(p);
    }
  </script>
</body>
</html>
@else
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Maintenance</title>
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
            <div class="absolute inset-0 bg-orange-200 dark:bg-orange-500/30 rounded-full animate-ping opacity-75" style="animation-duration: 3s;"></div>
            <div class="relative bg-gradient-to-br from-orange-400 to-red-500 dark:from-orange-500 dark:to-red-600 rounded-full w-full h-full flex items-center justify-center shadow-xl shadow-orange-500/30 dark:shadow-orange-900/40 border-4 border-white dark:border-slate-800">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-14 h-14 text-white">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.829M11.42 15.17l-3.976-3.976c-.845-.845-2.023-1.12-3.136-.788l-.513.153a.75.75 0 01-.933-.933l.153-.513c.332-1.113.057-2.291-.788-3.136l-3.976-3.976a2.652 2.652 0 013.75-3.75l3.976 3.976c.845.845 2.023 1.12 3.136.788l.513-.153a.75.75 0 01.933.933l-.153.513c-.332 1.113-.057 2.291.788 3.136l3.976 3.976A2.652 2.652 0 0111.42 15.17z" />
                </svg>
            </div>
        </div>

        <h1 class="font-display font-black text-5xl text-slate-800 dark:text-white tracking-tight mb-4">Under Maintenance</h1>
        <p class="text-slate-500 dark:text-slate-400 text-lg mb-10 max-w-lg mx-auto leading-relaxed">
            We're currently performing some scheduled updates to the <span class="font-bold text-slate-700 dark:text-slate-200 capitalize">{{ str_replace('-', ' ', $module ?? 'system') }}</span> module to improve your experience. 
            We'll be back online shortly!
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <button onclick="window.location.reload()" class="inline-flex items-center justify-center px-6 py-3 rounded-full bg-slate-800 dark:bg-slate-700 text-white font-semibold hover:bg-slate-700 dark:hover:bg-slate-600 hover:shadow-lg transition-all active:scale-95">
                <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Try Again
            </button>
            <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center px-6 py-3 rounded-full bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold border border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 hover:shadow-sm transition-all active:scale-95">
                Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>
@endif
