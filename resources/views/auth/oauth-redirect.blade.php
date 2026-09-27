<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>KIUQ SYSTEM</title>
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      background: #0b1329;
      color: #f8fafc;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      margin: 0;
      overflow: hidden;
    }
    .container {
      text-align: center;
      padding: 24px;
      border-radius: 20px;
      background: rgba(15, 23, 42, 0.7);
      border: 1px solid rgba(255, 255, 255, 0.1);
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
      max-width: 380px;
      width: 90%;
    }
    .spinner {
      width: 42px;
      height: 42px;
      border: 3.5px solid rgba(255, 255, 255, 0.15);
      border-top-color: #38bdf8;
      border-radius: 50%;
      animation: spin 0.75s linear infinite;
      margin: 0 auto 16px;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
    h2 {
      font-size: 15px;
      font-weight: 700;
      margin: 0 0 6px;
      letter-spacing: -0.01em;
    }
    p {
      font-size: 12px;
      color: #94a3b8;
      margin: 0;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="spinner"></div>
    <h2 id="status-heading">Redirecting...</h2>
    <p id="status-desc">Connecting with system secure services.</p>
  </div>

  <script>
    (function() {
      const hash = window.location.hash || '';
      const query = window.location.search || '';
      const params = new URLSearchParams(hash.startsWith('#') ? hash.substring(1) : (query.startsWith('?') ? query.substring(1) : ''));
      const accessToken = params.get('access_token');
      const state = params.get('state');

      // Check for error returned by Google
      const error = params.get('error') || params.get('error_description');
      if (error) {
        document.getElementById('status-heading').textContent = 'Google Authentication Error';
        document.getElementById('status-desc').textContent = decodeURIComponent(error.replace(/\+/g, ' '));
        setTimeout(() => {
          if (window.opener || window.name === 'googleLoginPopup' || window.name === 'googleLinkPopup') {
            window.close();
          } else {
            window.location.replace("{{ route('login') }}");
          }
        }, 2500);
        return;
      }

      if (accessToken) {
        document.getElementById('status-heading').textContent = 'Authenticating with Google...';
        document.getElementById('status-desc').textContent = 'Verifying credentials with KIUQ SYSTEM.';

        // 1. BroadcastChannel (works cross-window/tab without needing window.opener)
        if (typeof BroadcastChannel !== 'undefined') {
          try {
            const authChannel = new BroadcastChannel('kiuq_google_auth');
            authChannel.postMessage({
              type: 'GOOGLE_AUTH_TOKEN',
              accessToken: accessToken,
              state: state
            });
            setTimeout(() => { authChannel.close(); }, 500);
          } catch (e) {
            console.warn('BroadcastChannel error:', e);
          }
        }

        // 2. localStorage storage event (reliable cross-window communication on same origin)
        try {
          localStorage.setItem('kiuq_google_auth_token', JSON.stringify({
            type: 'GOOGLE_AUTH_TOKEN',
            accessToken: accessToken,
            state: state,
            ts: Date.now()
          }));
        } catch (e) {
          console.warn('localStorage error:', e);
        }

        // 3. window.opener postMessage (fallback if opener was preserved)
        if (window.opener && !window.opener.closed) {
          try {
            window.opener.postMessage({
              type: 'GOOGLE_AUTH_TOKEN',
              accessToken: accessToken,
              state: state
            }, '*');
          } catch (e) {
            console.warn('window.opener postMessage error:', e);
          }
        }

        // Check if current window is a popup window
        const isPopup = !!(window.opener && !window.opener.closed) ||
                        window.name === 'googleLoginPopup' ||
                        window.name === 'googleLinkPopup' ||
                        (window.innerWidth > 0 && window.innerWidth < 820 && window.innerHeight < 820);

        if (isPopup) {
          document.getElementById('status-heading').textContent = '✓ Google Authentication Successful';
          document.getElementById('status-desc').innerHTML = 'Returning to KIUQ SYSTEM...';

          setTimeout(() => {
            window.close();
            // In case window.close() is prevented by browser policy:
            setTimeout(() => {
              const desc = document.getElementById('status-desc');
              if (desc) {
                desc.innerHTML = 'Authentication complete! You may now close this window.<br><br><button onclick="window.close()" style="background:#2563eb;color:#ffffff;border:none;border-radius:10px;padding:8px 20px;cursor:pointer;font-weight:700;font-size:12px;box-shadow:0 4px 12px rgba(37,99,235,0.4);">Close Window</button>';
              }
            }, 600);
          }, 200);

          // DO NOT navigate popup window to dashboard! Stop execution here.
          return;
        }

        // Direct navigation flow (only when running in the primary browser window or macOS desktop app)
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        if (state === 'link_profile') {
          fetch("{{ route('profile.google.link') }}", {
            method: 'POST',
            credentials: 'include',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrfToken,
              'Accept': 'application/json'
            },
            body: JSON.stringify({ access_token: accessToken })
          })
          .then(async res => {
            const data = await res.json().catch(() => null);
            if (res.ok && data && data.success) {
              window.location.replace("{{ route('profile.show') }}?google_linked=1");
            } else {
              const msg = data?.message || ('Failed to link Google account (HTTP ' + res.status + ')');
              alert(msg);
              window.location.replace("{{ route('profile.show') }}");
            }
          })
          .catch(err => {
            console.error('Link error:', err);
            alert('Connection error while linking Google account: ' + (err.message || err));
            window.location.replace("{{ route('profile.show') }}");
          });
          return;
        } else if (state === 'login') {
          fetch("{{ route('auth.google.callback') }}", {
            method: 'POST',
            credentials: 'include',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrfToken,
              'Accept': 'application/json'
            },
            body: JSON.stringify({ access_token: accessToken })
          })
          .then(async res => {
            const data = await res.json().catch(() => null);
            if (res.ok && data && data.success) {
              window.location.replace(data.redirect || "{{ route('dashboard') }}");
            } else {
              const msg = data?.message || ('Google sign-in failed (HTTP ' + res.status + ')');
              alert(msg);
              window.location.replace("{{ route('login') }}");
            }
          })
          .catch(err => {
            console.error('Login error:', err);
            alert('Connection error during Google sign-in: ' + (err.message || err));
            window.location.replace("{{ route('login') }}");
          });
          return;
        }
      }

      // Default root redirect if no access_token is present
      @auth
        window.location.replace("{{ route('dashboard') }}");
      @else
        window.location.replace("{{ route('login') }}");
      @endauth
    })();
  </script>
</body>
</html>
