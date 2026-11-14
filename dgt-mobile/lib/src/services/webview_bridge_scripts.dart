import 'dart:convert';

import '../models/shell_state.dart';

/// JavaScript & scoped CSS bridge synchronizing the embedded KIUQ SYSTEM WebView
/// with the native Flutter iOS 26 Liquid Glass shell.
///
/// Key guarantees:
/// - Zero modification to Laravel server files.
/// - Eliminates duplicate competing navigation (`#mobile-bottom-nav` on iPhone,
///   and `#sidebar` when the native iPad Liquid Glass sidebar is active).
/// - Preserves 100% of server-rendered role permissions (`@can`, `@hasanyrole`,
///   `hasWebsiteAccess`, `isChatEnabled`, `canAccessKpi`, dynamic external tools).
/// - Preserves Pusher real-time updates, Alpine.js stores, and Turbo 8 visits.
class WebViewBridgeScripts {
  const WebViewBridgeScripts._();

  /// Injected at document start so web scripts immediately know native `flutter_webrtc` v3 is active.
  static const String earlyNativeWebRtcFlagScript =
      'window.__dgtNativeWebRtcAvailable = true; window.__dgtNativeWebRtcBridgeVersion = 3;';

  /// Builds the idempotent setup & layout-sync script run on page load and safe-area updates.
  static String buildBootstrapScript({
    required double safeTopPx,
    required double safeBottomPx,
    required double safeLeftPx,
    required double safeRightPx,
    required double bottomDockClearancePx,
    required bool isIpadLayout,
  }) {
    return _bootstrapTemplate
        .replaceAll('__SAFE_TOP__', '${safeTopPx.round()}')
        .replaceAll('__SAFE_BOTTOM__', '${safeBottomPx.round()}')
        .replaceAll('__SAFE_LEFT__', '${safeLeftPx.round()}')
        .replaceAll('__SAFE_RIGHT__', '${safeRightPx.round()}')
        .replaceAll('__DOCK_CLEARANCE__', '${bottomDockClearancePx.round()}')
        .replaceAll('__IS_IPAD__', isIpadLayout ? 'true' : 'false');
  }

  static const String _bootstrapTemplate = r'''
(() => {
  window.__dgtNativeWebRtcAvailable = true;
  window.__dgtNativeWebRtcBridgeVersion = 3;
  const root = document.documentElement;
  if (!root) return;

  const isIpadShell = __IS_IPAD__;

  const applyMetrics = () => {
    ['dgt-mobile-app', 'dgt-ios-app', 'dgt-liquid-glass-shell'].forEach((cls) => {
      if (!root.classList.contains(cls)) root.classList.add(cls);
    });
    if (root.classList.contains('dgt-ipad-shell') !== isIpadShell) {
      root.classList.toggle('dgt-ipad-shell', isIpadShell);
    }
    if (root.style.getPropertyValue('--dgt-safe-top') !== '__SAFE_TOP__px') {
      root.style.setProperty('--dgt-safe-top', '__SAFE_TOP__px');
    }
    if (root.style.getPropertyValue('--dgt-safe-bottom') !== '__SAFE_BOTTOM__px') {
      root.style.setProperty('--dgt-safe-bottom', '__SAFE_BOTTOM__px');
    }
    if (root.style.getPropertyValue('--dgt-safe-left') !== '__SAFE_LEFT__px') {
      root.style.setProperty('--dgt-safe-left', '__SAFE_LEFT__px');
    }
    if (root.style.getPropertyValue('--dgt-safe-right') !== '__SAFE_RIGHT__px') {
      root.style.setProperty('--dgt-safe-right', '__SAFE_RIGHT__px');
    }
    if (root.style.getPropertyValue('--dgt-dock-clearance') !== '__DOCK_CLEARANCE__px') {
      root.style.setProperty('--dgt-dock-clearance', '__DOCK_CLEARANCE__px');
    }

    const isAuthed = Boolean(
      document.getElementById('sidebar') ||
      document.getElementById('mobile-bottom-nav')
    );
    if (root.classList.contains('dgt-flutter-nav-managed') !== isAuthed) {
      root.classList.toggle('dgt-flutter-nav-managed', isAuthed);
    }
  };

  if (!document.getElementById('dgt-ios26-liquid-glass-styles')) {
    const style = document.createElement('style');
    style.id = 'dgt-ios26-liquid-glass-styles';
    style.textContent = `
      html.dgt-ios-app {
        -webkit-user-select: none;
        -webkit-touch-callout: none;
        -webkit-tap-highlight-color: transparent !important;
        touch-action: manipulation;
      }

      html.dgt-ios-app input,
      html.dgt-ios-app textarea,
      html.dgt-ios-app select,
      html.dgt-ios-app [contenteditable="true"] {
        -webkit-user-select: text !important;
        user-select: text !important;
        font-size: 16px !important;
      }

      html.dgt-ios-app .page-content,
      html.dgt-ios-app .custom-scrollbar {
        -webkit-overflow-scrolling: touch !important;
      }

      /* Prevent competing navigation bars when native Liquid Glass navigation is active */
      html.dgt-flutter-nav-managed #mobile-bottom-nav {
        display: none !important;
        visibility: hidden !important;
        pointer-events: none !important;
      }

      /* Ensure page content clears the floating Liquid Glass capsule cleanly */
      html.dgt-flutter-nav-managed:not(.dgt-ipad-shell) .page-content {
        padding-bottom: calc(var(--dgt-dock-clearance, 96px) + 18px) !important;
      }

      /* Adaptive iPad shell: native Liquid Glass sidebar replaces duplicate web sidebar */
      html.dgt-flutter-nav-managed.dgt-ipad-shell #sidebar,
      html.dgt-flutter-nav-managed.dgt-ipad-shell .sidebar-backdrop {
        display: none !important;
      }

      html.dgt-flutter-nav-managed.dgt-ipad-shell .main-wrapper {
        margin-left: 0 !important;
        width: 100% !important;
      }

      /* Hide redundant mobile hamburger button on iPad when native sidebar is active */
      html.dgt-flutter-nav-managed.dgt-ipad-shell header button[aria-label="Open menu"],
      html.dgt-flutter-nav-managed.dgt-ipad-shell .topbar button.lg\:hidden {
        display: none !important;
      }

      /* Subtle iOS 26 glass polish on web topbar without hurting content readability */
      html.dgt-liquid-glass-shell .topbar {
        backdrop-filter: saturate(180%) blur(20px);
        -webkit-backdrop-filter: saturate(180%) blur(20px);
      }

      /* Prevent horizontal overflow on compact iPhone viewports */
      html.dgt-ios-app body {
        overflow-x: hidden;
      }

      /* Ensure notification panel never overrides x-show="open" or x-cloak on mobile */
      .notif-panel[x-cloak],
      .notif-panel[hidden],
      .notif-panel[style*="display: none"],
      .notif-panel[style*="display:none"] {
        display: none !important;
        pointer-events: none !important;
      }
    `;
    (document.head || root).appendChild(style);
  }

  applyMetrics();

  const iconKeyFor = (url, label) => {
    const path = (url || '').toLowerCase();
    const text = (label || '').toLowerCase();
    if (path.includes('/dashboard') || text === 'home' || text === 'dashboard') return 'home';
    if (path.includes('/boards/personal-report') || text.includes('personal report')) return 'reports';
    if (path.includes('/smm-boards') || text.includes('smm planning')) return 'smm_boards';
    if (path.includes('/boards') || text.includes('board')) return 'boards';
    if (path.includes('/tasks-count') || text.includes('task')) return 'tasks';
    if (path.includes('/websites/posts') || text.includes('blog cms')) return 'blog_cms';
    if (path.includes('/websites/blogs') || text.includes('blog analytics')) return 'blog_analytics';
    if (path.includes('/websites/google-search-console') || text.includes('search console')) return 'gsc';
    if (path.includes('/websites/integrations') || text.includes('connector')) return 'connectors';
    if (path.includes('follow-up') || text.includes('follow up')) return 'followup';
    if (path.includes('/websites')) return 'websites';
    if (path.includes('/chat')) return 'chat';
    if (path.includes('/notes/team') || text.includes('team note')) return 'team_notes';
    if (path.includes('/notes/private') || text.includes('private note')) return 'private_notes';
    if (path.includes('/notes')) return 'notes';
    if (path.includes('/social-media') || text.includes('reels')) return 'social_media';
    if (path.includes('/kpi') || text.includes('kpi')) return 'kpi';
    if (path.includes('/members') || path.includes('/admin/users') || text.includes('member') || text.includes('user')) return 'members';
    if (path.includes('/admin/labels') || text.includes('label')) return 'labels';
    if (path.includes('/admin/security') || text.includes('security')) return 'security';
    if (path.includes('/admin/meeting-alarms') || text.includes('alarm')) return 'alarms';
    if (path.includes('/admin/maintenance') || text.includes('maintenance')) return 'maintenance';
    if (path.includes('/profile') || text.includes('profile')) return 'profile';
    if (path.includes('/settings') || text.includes('setting')) return 'settings';
    if (text.includes('ai')) return 'sparkles';
    return 'circle_grid';
  };

  const cleanText = (el) => {
    if (!el) return '';
    const clone = el.cloneNode(true);
    clone.querySelectorAll('.nav-badge, .mobile-tab-badge, [data-chat-unread-badge], [data-notification-badge], svg, style, script').forEach((n) => n.remove());
    return (clone.textContent || '').replace(/\s+/g, ' ').trim();
  };

  const parseBadgeCount = (el) => {
    if (!el) return 0;
    const raw = (el.textContent || '').trim();
    if (!raw) return 0;
    if (raw.includes('99')) return 99;
    const n = parseInt(raw.replace(/[^0-9]/g, ''), 10);
    return Number.isFinite(n) ? n : 0;
  };

  const isVisibleBadge = (el) => {
    if (!el) return false;
    if (el.hasAttribute('hidden') || el.classList.contains('hidden')) return false;
    const style = window.getComputedStyle(el);
    if (style.display === 'none' || style.visibility === 'hidden') return false;
    return (el.textContent || '').trim().length > 0;
  };

  const collectShellState = () => {
    applyMetrics();

    const sidebar = document.getElementById('sidebar');
    const mobileNav = document.getElementById('mobile-bottom-nav');
    const isAuthenticated = Boolean(sidebar || mobileNav);
    const theme = root.getAttribute('data-theme') || localStorage.getItem('dgt-theme') || 'light';
    const currentUrl = window.location.href;
    const currentPath = window.location.pathname.replace(/\/+$/, '') || '/';

    let unreadNotifications = 0;
    document.querySelectorAll('[data-notification-badge], #topbar-notification-badge, .notification-badge').forEach((b) => {
      if (isVisibleBadge(b)) {
        unreadNotifications = Math.max(unreadNotifications, parseBadgeCount(b) || 1);
      }
    });

    let unreadChat = 0;
    document.querySelectorAll('[data-chat-unread-badge]').forEach((b) => {
      if (isVisibleBadge(b)) {
        unreadChat = Math.max(unreadChat, parseBadgeCount(b));
      }
    });

    let tasksCount = 0;
    if (sidebar) {
      const taskLink = sidebar.querySelector('a[href*="/tasks-count"] .nav-badge');
      if (taskLink) {
        tasksCount = parseBadgeCount(taskLink);
      }
    }

    const primaryTabs = [];
    const seenPrimaryIds = new Set();

    if (mobileNav) {
      const items = mobileNav.querySelectorAll('a.mobile-tab-item, button.mobile-tab-item');
      items.forEach((item, idx) => {
        const labelSpan = item.querySelector('span:last-child');
        const label = (labelSpan ? labelSpan.textContent : cleanText(item)).replace(/\s+/g, ' ').trim();
        const onclickAttr = item.getAttribute('onclick') || item.getAttribute('@click') || item.getAttribute('x-on:click') || '';
        const href = item.getAttribute('href') || '';

        if (!label) return;
        if (label.toLowerCase() === 'hub' || onclickAttr.includes('mobileHub')) {
          return;
        }

        const isAi = onclickAttr.includes('open-kiuq-ai') || label.toLowerCase().includes('ai');
        let resolvedUrl = '';
        let pathOnly = '';
        if (href && !href.startsWith('javascript:')) {
          try {
            const u = new URL(href, window.location.origin);
            resolvedUrl = u.href;
            pathOnly = u.pathname.replace(/\/+$/, '') || '/';
          } catch (_) {
            resolvedUrl = href;
          }
        }

        const badgeEl = item.querySelector('.mobile-tab-badge, [data-chat-unread-badge]');
        const badgeCount = isVisibleBadge(badgeEl) ? parseBadgeCount(badgeEl) : 0;
        const iconKey = isAi ? 'sparkles' : iconKeyFor(resolvedUrl, label);
        const id = isAi ? 'kiuq_ai' : (pathOnly ? pathOnly.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') : `tab_${idx}`);
        if (seenPrimaryIds.has(id)) return;
        seenPrimaryIds.add(id);

        const isActive = item.classList.contains('is-active') ||
          (!isAi && pathOnly && (currentPath === pathOnly || (pathOnly !== '/dashboard' && pathOnly !== '/websites' && currentPath.startsWith(pathOnly + '/'))));

        primaryTabs.push({
          id,
          label,
          iconKey,
          url: resolvedUrl,
          section: 'Main',
          actionType: isAi ? 'kiuqAi' : 'route',
          useTurbo: item.getAttribute('data-turbo') !== 'false',
          isActive: Boolean(isActive),
          badgeCount: iconKey === 'chat' ? Math.max(badgeCount, unreadChat) : (iconKey === 'tasks' ? Math.max(badgeCount, tasksCount) : badgeCount),
          badgeTone: iconKey === 'tasks' ? 'warning' : 'alert',
        });
      });
    }

    const hubDestinations = [];
    const seenHubUrls = new Set();

    if (sidebar) {
      let currentSection = 'Workspace';
      const navNodes = sidebar.querySelectorAll('.nav-section-label, a.nav-link, button.nav-link');
      navNodes.forEach((node, idx) => {
        if (node.classList.contains('nav-section-label')) {
          const secText = (node.textContent || '').replace(/\s+/g, ' ').trim();
          if (secText) currentSection = secText;
          return;
        }

        const label = cleanText(node);
        if (!label) return;

        const onclickAttr = node.getAttribute('onclick') || node.getAttribute('@click') || node.getAttribute('x-on:click') || '';
        const isAi = onclickAttr.includes('open-kiuq-ai') || label.toLowerCase().includes('kiuq ai');
        const href = node.getAttribute('href') || '';
        const targetBlank = (node.getAttribute('target') || '').toLowerCase() === '_blank';

        let resolvedUrl = '';
        let pathOnly = '';
        let isExternal = false;
        if (href && !href.startsWith('javascript:') && href !== '#') {
          try {
            const u = new URL(href, window.location.origin);
            resolvedUrl = u.href;
            pathOnly = u.pathname.replace(/\/+$/, '') || '/';
            isExternal = targetBlank && u.origin !== window.location.origin;
          } catch (_) {
            resolvedUrl = href;
          }
        }

        const dedupeKey = isAi ? 'action:kiuqAi' : (resolvedUrl || `btn:${label}:${idx}`);
        if (seenHubUrls.has(dedupeKey)) return;
        seenHubUrls.add(dedupeKey);

        const badgeEl = node.querySelector('.nav-badge, [data-chat-unread-badge]');
        const badgeCount = isVisibleBadge(badgeEl) ? parseBadgeCount(badgeEl) : 0;
        const iconKey = isExternal ? 'external' : (isAi ? 'sparkles' : iconKeyFor(resolvedUrl, label));
        const isActive = node.classList.contains('active') ||
          (!isAi && !isExternal && pathOnly && currentPath === pathOnly && (!resolvedUrl.includes('?') || currentUrl.includes(resolvedUrl.split('?')[1])));

        hubDestinations.push({
          id: `hub_${idx}_${iconKey}`,
          label,
          iconKey,
          url: resolvedUrl,
          section: currentSection,
          actionType: isAi ? 'kiuqAi' : (isExternal ? 'externalUrl' : 'route'),
          useTurbo: node.getAttribute('data-turbo') !== 'false',
          isActive: Boolean(isActive),
          badgeCount: iconKey === 'chat' ? Math.max(badgeCount, unreadChat) : (iconKey === 'tasks' ? Math.max(badgeCount, tasksCount) : badgeCount),
          badgeTone: iconKey === 'tasks' ? 'warning' : 'alert',
        });
      });
    }

    // Ensure Profile & Settings are always reachable in Account & System
    if (isAuthenticated) {
      const origin = window.location.origin;
      if (!seenHubUrls.has(origin + '/profile')) {
        hubDestinations.push({
          id: 'account_profile',
          label: 'Account Profile',
          iconKey: 'profile',
          url: origin + '/profile',
          subtitle: 'Profile, notifications, Google & 2FA',
          section: 'Account & System',
          actionType: 'route',
          useTurbo: true,
          isActive: currentPath.startsWith('/profile'),
          badgeCount: 0,
        });
      }
      if (!seenHubUrls.has(origin + '/settings')) {
        hubDestinations.push({
          id: 'account_settings',
          label: 'Account Settings',
          iconKey: 'settings',
          url: origin + '/settings',
          subtitle: 'Password & security preferences',
          section: 'Account & System',
          actionType: 'route',
          useTurbo: true,
          isActive: currentPath === '/settings',
          badgeCount: 0,
        });
      }
    }

    let userName = '';
    let userRole = '';
    let userEmail = '';
    let avatarUrl = '';
    const userMenu = document.getElementById('sidebar-user-menu');
    if (userMenu) {
      const pTags = userMenu.querySelectorAll('p');
      if (pTags.length >= 1) userName = (pTags[0].textContent || '').trim();
      if (pTags.length >= 2) userRole = (pTags[1].textContent || '').trim();
      const img = userMenu.querySelector('img');
      if (img && img.src) avatarUrl = img.src;
    }

    const aiModal = document.querySelector('[aria-labelledby="kiuq-ai-title"]');
    const isAiModalVisible = Boolean(
      aiModal &&
      !aiModal.classList.contains('hidden') &&
      window.getComputedStyle(aiModal).display !== 'none'
    );

    const shiftedChat = document.querySelector('.chat-panel-list.is-shifted');
    const isChatThreadOpen = Boolean(shiftedChat && currentPath.startsWith('/chat'));
    const authUserMeta = document.querySelector('meta[name="kiuq-user-id"]');
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const authUserId = authUserMeta && authUserMeta.content ? parseInt(authUserMeta.content, 10) || 0 : 0;
    const csrfToken = csrfMeta && csrfMeta.content ? csrfMeta.content : '';
    const socketId = (window.__kiuqPusher && window.__kiuqPusher.connection && window.__kiuqPusher.connection.socket_id)
      ? window.__kiuqPusher.connection.socket_id
      : '';

    const payload = {
      isAuthenticated,
      currentUrl,
      pageTitle: (document.title || 'KIUQ SYSTEM').replace(/\s*[-|]\s*KIUQ.*$/i, '').trim() || 'KIUQ SYSTEM',
      themeMode: theme,
      unreadNotifications,
      unreadChat,
      tasksCount,
      primaryTabs,
      hubDestinations,
      authUserId,
      csrfToken,
      socketId,
      user: {
        name: userName,
        role: userRole,
        email: userEmail,
        avatarUrl,
      },
      isFullScreenModalOpen: isAiModalVisible,
      isChatThreadOpen,
    };

    if (window.flutter_inappwebview && window.flutter_inappwebview.callHandler) {
      window.flutter_inappwebview.callHandler('DgtShellState', payload);
    }
  };

  window.__dgtSyncShellState = collectShellState;

  if (!window.__dgtLiquidGlassBridgeInstalled) {
    window.__dgtLiquidGlassBridgeInstalled = true;

    let syncTimer = null;
    const scheduleSync = (delay = 55) => {
      if (syncTimer) clearTimeout(syncTimer);
      syncTimer = setTimeout(collectShellState, delay);
    };

    document.addEventListener('turbo:load', () => {
      bindScrollListeners();
      scheduleSync(30);
    });
    document.addEventListener('turbo:render', () => {
      bindScrollListeners();
      scheduleSync(30);
    });
    window.addEventListener('popstate', () => scheduleSync(40));
    window.addEventListener('hashchange', () => scheduleSync(40));
    window.addEventListener('open-kiuq-ai', () => scheduleSync(90));
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') scheduleSync(90);
    });
    document.addEventListener('click', () => scheduleSync(140), { passive: true });

    const rootObserver = new MutationObserver(() => scheduleSync(80));
    rootObserver.observe(root, { attributes: true, attributeFilter: ['data-theme', 'class'] });

    const blurAccidentalColorPicker = () => {
      const active = document.activeElement;
      if (active && active.matches && active.matches('input[type="color"]')) {
        active.blur();
      }
    };
    window.addEventListener('pageshow', blurAccidentalColorPicker, true);
    window.addEventListener('load', blurAccidentalColorPicker, true);

    document.addEventListener('focusin', (event) => {
      const target = event.target;
      if (!target || !target.matches) return;
      if (target.matches('input:not([type="checkbox"]):not([type="radio"]):not([type="color"]), textarea, select, [contenteditable="true"]')) {
        setTimeout(() => {
          try {
            target.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
          } catch (_) {}
        }, 240);
      }
    }, { passive: true });

    document.addEventListener('submit', (event) => {
      const form = event.target;
      if (!form || form.hasAttribute('data-allow-resubmit') || form.hasAttribute('x-on:submit.prevent') || form.hasAttribute('@submit.prevent')) {
        return;
      }
      const now = Date.now();
      const last = Number(form.dataset.dgtLastSubmitAt || 0);
      if (now - last < 900) {
        event.preventDefault();
        return;
      }
      form.dataset.dgtLastSubmitAt = String(now);
    }, true);

    document.addEventListener('click', (event) => {
      const anchor = event.target && event.target.closest ? event.target.closest('a[download]') : null;
      if (!anchor) return;
      const href = anchor.getAttribute('href') || '';
      if (!href.startsWith('blob:') && !href.startsWith('data:')) return;
      event.preventDefault();
      const downloadName = anchor.getAttribute('download') || 'kiuq-export';
      if (href.startsWith('data:')) {
        window.flutter_inappwebview?.callHandler('DgtNativeDownload', {
          base64: href,
          filename: downloadName,
        });
        return;
      }
      fetch(href)
        .then((r) => r.blob())
        .then((blob) => {
          const reader = new FileReader();
          reader.onloadend = () => {
            window.flutter_inappwebview?.callHandler('DgtNativeDownload', {
              base64: reader.result,
              filename: downloadName,
              mimeType: blob.type || '',
            });
          };
          reader.readAsDataURL(blob);
        })
        .catch(() => {});
    }, true);

    let lastScrollTop = 0;
    let lastReportedDirection = 'top';
    let lastReportTime = 0;

    const handleScrollOffset = (scrollTop) => {
      const now = Date.now();
      if (now - lastReportTime < 90) return;

      const current = Math.max(0, scrollTop);
      if (current <= 28) {
        if (lastReportedDirection !== 'top') {
          lastReportedDirection = 'top';
          lastReportTime = now;
          window.flutter_inappwebview?.callHandler('DgtScrollDirection', 'top');
        }
        lastScrollTop = current;
        return;
      }

      const delta = current - lastScrollTop;
      if (delta > 20 && current > 72) {
        if (lastReportedDirection !== 'down') {
          lastReportedDirection = 'down';
          lastReportTime = now;
          window.flutter_inappwebview?.callHandler('DgtScrollDirection', 'down');
        }
        lastScrollTop = current;
      } else if (delta < -14) {
        if (lastReportedDirection !== 'up') {
          lastReportedDirection = 'up';
          lastReportTime = now;
          window.flutter_inappwebview?.callHandler('DgtScrollDirection', 'up');
        }
        lastScrollTop = current;
      }
    };

    function bindScrollListeners() {
      const pageContent = document.querySelector('.page-content');
      if (pageContent && !pageContent.__dgtScrollBound) {
        pageContent.__dgtScrollBound = true;
        pageContent.addEventListener('scroll', () => {
          handleScrollOffset(pageContent.scrollTop);
        }, { passive: true });
      }
    }

    window.addEventListener('scroll', () => {
      handleScrollOffset(window.scrollY || document.documentElement.scrollTop || 0);
    }, { passive: true });

    bindScrollListeners();
  }

  collectShellState();
})();
''';

  /// Navigates cleanly inside KIUQ SYSTEM using Turbo 8 when supported,
  /// preserving SPA speed, Pusher connections, and back-stack history.
  static String navigateToUrlScript(String targetUrl, {bool useTurbo = true}) {
    final encodedUrl = jsonEncode(targetUrl);
    final turboFlag = useTurbo ? 'true' : 'false';
    return '''
(() => {
  const url = $encodedUrl;
  const allowTurbo = $turboFlag;
  try {
    if (window.Alpine && Alpine.store && Alpine.store('mobileHub')) {
      Alpine.store('mobileHub').close();
    }
  } catch (_) {}

  if (allowTurbo && window.Turbo && typeof window.Turbo.visit === 'function') {
    window.Turbo.visit(url);
  } else {
    window.location.href = url;
  }
})();
''';
  }

  /// Opens the existing KIUQ AI modal workspace via its native `CustomEvent('open-kiuq-ai')`.
  static String openKiuqAiScript() {
    return r'''
(() => {
  try {
    if (window.Alpine && Alpine.store && Alpine.store('mobileHub')) {
      Alpine.store('mobileHub').close();
    }
  } catch (_) {}
  window.dispatchEvent(new CustomEvent('open-kiuq-ai'));
  setTimeout(() => {
    if (window.__dgtSyncShellState) window.__dgtSyncShellState();
  }, 100);
})();
''';
  }

  /// Synchronizes theme selection (`light`, `dark`, `neon`) with KIUQ SYSTEM's `themeSystem()`.
  static String setWebThemeScript(KiuqThemeMode mode) {
    final encoded = jsonEncode(mode.wireValue);
    return '''
(() => {
  const nextTheme = $encoded;
  localStorage.setItem('dgt-theme', nextTheme);
  document.documentElement.setAttribute('data-theme', nextTheme);
  window.dispatchEvent(new CustomEvent('dgt-theme-change', { detail: { theme: nextTheme } }));
  if (window.__dgtSyncShellState) window.__dgtSyncShellState();
})();
''';
  }

  /// Submits the existing CSRF-protected logout form on the page if present.
  static String triggerLogoutScript(String fallbackLogoutUrl) {
    final encodedFallback = jsonEncode(fallbackLogoutUrl);
    return '''
(() => {
  const existingForm = document.querySelector('form[action*="/logout"]');
  if (existingForm) {
    existingForm.submit();
    return;
  }
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  if (csrfMeta && csrfMeta.content) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = $encodedFallback;
    const tokenInput = document.createElement('input');
    tokenInput.type = 'hidden';
    tokenInput.name = '_token';
    tokenInput.value = csrfMeta.content;
    form.appendChild(tokenInput);
    document.body.appendChild(form);
    form.submit();
  } else {
    window.location.href = '/login';
  }
})();
''';
  }

  /// Opens the existing topbar notification dropdown or scrolls topbar into view.
  static String openNotificationsDropdownScript() {
    return r'''
(() => {
  const bellBtn = document.querySelector('[data-notification-trigger], button[aria-label*="Notification" i], #topbar-notification-btn');
  if (bellBtn && typeof bellBtn.click === 'function') {
    bellBtn.click();
    return true;
  }
  return false;
})();
''';
  }

  /// Notifies the embedded web workspace that a native WebRTC call finished so Call History & Chat refresh.
  static String notifyWebCallEndedScript() {
    return r'''
(() => {
  window.dispatchEvent(new CustomEvent('dgt:call-ended'));
})();
''';
  }
}
