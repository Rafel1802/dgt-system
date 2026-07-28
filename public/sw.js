// Bumped to v2: the old v1 cache may hold personalized HTML pages cached
// under the previous (unsafe) strategy — this forces every client to drop
// it on next activation (see the activate handler below).
const CACHE_NAME = 'kiuq-system-cache-v3';
const PRE_CACHE_ASSETS = [
  '/favicon.ico',
  '/manifest.json'
];

// Install event - precache app shell
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(PRE_CACHE_ASSETS))
      .then(() => self.skipWaiting())
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => {
      const staleKeys = keys.filter(key => key !== CACHE_NAME);
      return Promise.all(staleKeys.map(key => caches.delete(key))).then(() => staleKeys.length > 0);
    }).then(hadStaleCache => {
      return self.clients.claim().then(() => {
        // Only force a reload of already-open tabs when we actually cleared
        // an old (potentially cross-user-poisoned) cache — not on a brand
        // new install where there's nothing to fix. This is what makes the
        // fix take effect without staff needing to know to hard-refresh.
        if (!hadStaleCache) return;
        return self.clients.matchAll({ type: 'window' }).then(clients => {
          clients.forEach(client => client.navigate(client.url));
        });
      });
    })
  );
});

// Fetch event - caching strategies
self.addEventListener('fetch', event => {
  const request = event.request;
  const url = new URL(request.url);

  // Only handle GET requests from the same origin
  if (request.method !== 'GET' || url.origin !== self.location.origin) {
    return;
  }

  // Exclude administrative, authentication, dynamic live polling, and action endpoints from cache
  if (
    url.pathname.startsWith('/login') ||
    url.pathname.startsWith('/logout') ||
    url.pathname.startsWith('/broadcasting') ||
    url.pathname.startsWith('/notifications') ||
    url.pathname.startsWith('/api') ||
    url.pathname.includes('/reorder') ||
    url.pathname.includes('/move') ||
    url.pathname.includes('/toggle') ||
    url.pathname.includes('/approve') ||
    url.pathname.includes('/reject')
  ) {
    return;
  }

  // For static assets (JS, CSS, images, fonts): Cache-First strategy
  const isAsset = 
    url.pathname.startsWith('/build/') || 
    url.pathname.startsWith('/js/') || 
    url.pathname.startsWith('/css/') || 
    url.pathname.match(/\.(png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|otf|mp3)$/i);

  if (isAsset) {
    event.respondWith(
      caches.match(request).then(cachedResponse => {
        if (cachedResponse) {
          // Fetch update in background to keep cache fresh
          fetch(request).then(networkResponse => {
            if (networkResponse.status === 200) {
              caches.open(CACHE_NAME).then(cache => cache.put(request, networkResponse));
            }
          }).catch(() => {});
          return cachedResponse;
        }
        return fetch(request).then(networkResponse => {
          if (networkResponse.status === 200) {
            const responseClone = networkResponse.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(request, responseClone));
          }
          return networkResponse;
        });
      })
    );
    return;
  }

  // HTML pages are intentionally NEVER cached-and-replayed: every page in
  // this app is personalized/authenticated (user name, role, CSRF token,
  // and — critically — the per-user meta tag the notification bell's
  // Pusher client reads to pick which private channel to subscribe to).
  // The previous Network-First-with-cache-fallback strategy cached these
  // by URL only, with no per-session/per-user variance, so on a shared
  // device a slow (>200ms) page load could silently serve a DIFFERENT
  // staff member's cached HTML — including their user ID — causing this
  // browser to subscribe to that other user's live notification channel.
  // Always hit the network for HTML; let the browser's own default
  // handling take over instead of intercepting.
});
