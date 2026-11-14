// Unified KIUQ / DGT System Service Worker (v6)
// Combines Pusher Beams Web Push + Mobile Asset Caching + Background Notification Delivery
try {
  importScripts("https://js.pusher.com/beams/service-worker.js");
} catch (e) {
  // Offline or CDN blocked — continue with native/local SW handlers
}

const CACHE_NAME = 'kiuq-system-cache-v6';
const CDN_CACHE_NAME = 'kiuq-cdn-cache-v1';
const PRE_CACHE_ASSETS = [
  '/favicon.ico',
  '/manifest.json',
  '/images/logo.png'
];

// Install event - precache core shell assets
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => Promise.allSettled(PRE_CACHE_ASSETS.map(url => cache.add(url))))
      .then(() => self.skipWaiting())
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => {
      const validKeys = new Set([CACHE_NAME, CDN_CACHE_NAME]);
      const staleKeys = keys.filter(key => !validKeys.has(key));
      return Promise.all(staleKeys.map(key => caches.delete(key)));
    }).then(() => self.clients.claim())
  );
});

// Fetch event - fast Cache-First + Stale-While-Revalidate for static & CDN assets
self.addEventListener('fetch', event => {
  const request = event.request;
  if (request.method !== 'GET') return;

  let url;
  try {
    url = new URL(request.url);
  } catch (e) {
    return;
  }

  // 1) Cache trusted immutable/semi-static CDN assets (Google Fonts, jsDelivr) for instant mobile load
  const isTrustedCdn =
    url.hostname === 'fonts.googleapis.com' ||
    url.hostname === 'fonts.gstatic.com' ||
    url.hostname === 'cdn.jsdelivr.net';

  if (isTrustedCdn) {
    event.respondWith(
      caches.open(CDN_CACHE_NAME).then(cache =>
        cache.match(request).then(cachedResponse => {
          const networkFetch = fetch(request).then(networkResponse => {
            if (networkResponse && (networkResponse.status === 200 || networkResponse.type === 'opaque')) {
              cache.put(request, networkResponse.clone()).catch(() => {});
            }
            return networkResponse;
          }).catch(() => cachedResponse);

          return cachedResponse || networkFetch;
        })
      )
    );
    return;
  }

  // Only handle same-origin requests below
  if (url.origin !== self.location.origin) {
    return;
  }

  // Exclude authentication, live broadcasting, notifications, and mutation endpoints
  if (
    url.pathname.startsWith('/login') ||
    url.pathname.startsWith('/logout') ||
    url.pathname.startsWith('/broadcasting') ||
    url.pathname.startsWith('/notifications') ||
    url.pathname.startsWith('/chat') ||
    url.pathname.startsWith('/api') ||
    url.pathname.includes('/reorder') ||
    url.pathname.includes('/move') ||
    url.pathname.includes('/toggle') ||
    url.pathname.includes('/approve') ||
    url.pathname.includes('/reject')
  ) {
    return;
  }

  // 2) Same-origin static assets (Vite build, JS, CSS, images, fonts, sounds): Cache-First + background revalidate
  const isAsset =
    url.pathname.startsWith('/build/') ||
    url.pathname.startsWith('/js/') ||
    url.pathname.startsWith('/css/') ||
    url.pathname.startsWith('/images/') ||
    url.pathname.startsWith('/sounds/') ||
    /\.(png|jpg|jpeg|gif|webp|svg|ico|woff|woff2|ttf|otf|mp3|wav)$/i.test(url.pathname);

  if (isAsset) {
    event.respondWith(
      caches.open(CACHE_NAME).then(cache =>
        cache.match(request).then(cachedResponse => {
          const fetchPromise = fetch(request).then(networkResponse => {
            if (networkResponse && networkResponse.status === 200) {
              cache.put(request, networkResponse.clone()).catch(() => {});
            }
            return networkResponse;
          }).catch(() => cachedResponse);

          return cachedResponse || fetchPromise;
        })
      )
    );
  }
});

// Handle messages from the app (background notifications, badge updates, cache invalidation)
self.addEventListener('message', event => {
  const data = event.data || {};
  if (data.type === 'SHOW_NOTIFICATION' && data.payload) {
    const p = data.payload;
    const title = p.title || 'KIUQ System';
    const options = {
      body: p.body || '',
      icon: p.icon || '/images/logo.png',
      badge: '/images/logo.png',
      tag: p.tag || ('dgt-notif-' + (p.id || Date.now())),
      renotify: true,
      data: {
        link: p.url || p.link || '/dashboard',
        card_id: p.card_id || null
      }
    };
    if (typeof p.badgeCount === 'number' && 'setAppBadge' in self.navigator) {
      if (p.badgeCount > 0) {
        self.navigator.setAppBadge(p.badgeCount).catch(() => {});
      } else if ('clearAppBadge' in self.navigator) {
        self.navigator.clearAppBadge().catch(() => {});
      }
    }
    event.waitUntil(self.registration.showNotification(title, options));
  } else if (data.type === 'SET_BADGE') {
    const count = Number(data.count || 0);
    if ('setAppBadge' in self.navigator) {
      if (count > 0) {
        self.navigator.setAppBadge(count).catch(() => {});
      } else if ('clearAppBadge' in self.navigator) {
        self.navigator.clearAppBadge().catch(() => {});
      }
    }
  }
});

// Handle notification clicks (deep-linking to exact conversation, card, or report)
self.addEventListener('notificationclick', function(event) {
  event.notification.close();
  const notifData = event.notification.data || {};
  const link = notifData.link || notifData.url || (notifData.pusher && notifData.pusher.customerPayload && notifData.pusher.customerPayload.web && notifData.pusher.customerPayload.web.notification && notifData.pusher.customerPayload.web.notification.deep_link) || null;
  const cardId = notifData.card_id || null;

  if (link) {
    event.waitUntil(
      clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
        for (let i = 0; i < clientList.length; i++) {
          const client = clientList[i];
          if ('focus' in client) {
            if (cardId) {
              client.postMessage({ type: 'OPEN_CARD', cardId: cardId });
            }
            if ('navigate' in client && link) {
              client.navigate(link).catch(() => {});
            }
            return client.focus();
          }
        }
        if (clients.openWindow) {
          return clients.openWindow(link);
        }
      })
    );
  }
});
