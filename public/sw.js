const CACHE_NAME = 'kiuq-system-cache-v1';
const PRE_CACHE_ASSETS = [
  '/',
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
      return Promise.all(
        keys.map(key => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    }).then(() => self.clients.claim())
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

  // For HTML pages: Network-First strategy with a quick timeout fallback to Cache
  // This ensures freshness but falls back instantly (0ms load) if network is slow/offline.
  event.respondWith(
    caches.open(CACHE_NAME).then(cache => {
      const fetchPromise = fetch(request).then(networkResponse => {
        if (networkResponse.status === 200) {
          cache.put(request, networkResponse.clone());
        }
        return networkResponse;
      });

      // Create a promise that rejects after 200ms
      const timeoutPromise = new Promise((_, reject) => {
        setTimeout(() => reject(new Error('Timeout')), 200);
      });

      // Race the network fetch against the 200ms timeout
      return Promise.race([fetchPromise, timeoutPromise])
        .catch(() => {
          // If network fails, errors, or timeouts (takes >200ms), return cached version instantly
          return cache.match(request).then(cachedResponse => {
            if (cachedResponse) {
              return cachedResponse;
            }
            // If nothing in cache, fall back to the raw fetch promise
            return fetchPromise;
          });
        });
    })
  );
});
