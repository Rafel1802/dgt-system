import 'dart:convert';
import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:flutter_inappwebview/flutter_inappwebview.dart';

const configuredAppBaseUrl = String.fromEnvironment('APP_BASE_URL');
const configuredApiBaseUrl = String.fromEnvironment('API_BASE_URL');
const nativeNotificationTest = bool.fromEnvironment('NATIVE_NOTIFICATION_TEST');

final String appBaseUrl = _resolveAppBaseUrl();

String _resolveAppBaseUrl() {
  final rawUrl = configuredAppBaseUrl.isNotEmpty
      ? configuredAppBaseUrl
      : configuredApiBaseUrl;

  if (rawUrl.isEmpty) {
    return 'https://rosybrown-baboon-228003.hostingersite.com';
  }

  final uri = Uri.tryParse(rawUrl);
  if (uri == null || !uri.hasScheme) {
    return 'https://rosybrown-baboon-228003.hostingersite.com';
  }

  final normalizedPath = uri.path.replaceFirst(RegExp(r'/api/?$'), '');
  final appUri = uri.replace(path: normalizedPath, query: '', fragment: '');

  return appUri.toString().replaceFirst(RegExp(r'/$'), '');
}

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await NativeNotificationService.requestPermission();
  if (nativeNotificationTest) {
    Future<void>.delayed(const Duration(seconds: 3), () {
      NativeNotificationService.show(
        id: 'dgt-native-startup-test-${DateTime.now().millisecondsSinceEpoch}',
        title: 'KIUQ SYSTEM',
        subtitle: 'Native macOS notification',
        body: 'This is a real app notification test.',
      );
    });
  }
  runApp(const DgtDesktopApp());
}

class NativeNotificationService {
  static const MethodChannel _channel = MethodChannel(
    'dgt_system/native_notifications',
  );

  static Future<bool> requestPermission() async {
    try {
      return await _channel.invokeMethod<bool>('requestPermission') ?? false;
    } catch (_) {
      return false;
    }
  }

  static Future<bool> show({
    required String id,
    required String title,
    required String body,
    String? subtitle,
    String? link,
    String? avatarUrl,
  }) async {
    try {
      return await _channel.invokeMethod<bool>('showNotification', {
            'id': id,
            'title': title,
            'subtitle': subtitle ?? '',
            'body': body,
            'link': link ?? '',
            'avatarUrl': avatarUrl ?? '',
          }) ??
          false;
    } catch (_) {
      return false;
    }
  }
}

class DgtDesktopApp extends StatelessWidget {
  const DgtDesktopApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'KIUQ SYSTEM',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF2F68ED)),
      ),
      home: const DgtWebsiteShell(),
    );
  }
}

class DgtWebsiteShell extends StatefulWidget {
  const DgtWebsiteShell({super.key});

  @override
  State<DgtWebsiteShell> createState() => _DgtWebsiteShellState();
}

class _DgtWebsiteShellState extends State<DgtWebsiteShell>
    with WidgetsBindingObserver {
  InAppWebViewController? controller;
  late final NativeNotificationPoller notificationPoller;
  int loadingProgress = 0;
  bool hasLoadedFirstPage = false;
  String? loadError;
  final Set<String> shownNativeNotificationIds = <String>{};
  final Map<String, DateTime> recentNativeNotificationFingerprints =
      <String, DateTime>{};

  Uri get appUri => Uri.parse(appBaseUrl);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    notificationPoller = NativeNotificationPoller(
      appUri: appUri,
      onNotification: _showNativeNotificationPayload,
    );
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    notificationPoller.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    notificationPoller.setBackgroundMode(
      state == AppLifecycleState.hidden ||
          state == AppLifecycleState.inactive ||
          state == AppLifecycleState.paused ||
          state == AppLifecycleState.detached,
    );
  }

  bool _isInternalUrl(Uri uri) {
    if (uri.scheme == 'about' || uri.scheme == 'data') {
      return true;
    }

    return uri.host == appUri.host && uri.port == appUri.port;
  }

  Future<void> _reload() async {
    setState(() => loadError = null);
    await controller?.reload();
  }

  Future<void> _prepareOfficialAppSurface() async {
    await controller?.evaluateJavascript(source: '''
      (() => {
        if (!window.__dgtOfficialAppReady) {
          window.__dgtOfficialAppReady = true;
          document.addEventListener('contextmenu', event => {
            event.preventDefault();
          }, { capture: true });
          document.documentElement.classList.add('dgt-macos-app');
        }

        if (window.__dgtNativeNotificationBridgeReady) {
          window.__dgtNativeConnectPusher?.();
          return;
        }
        window.__dgtNativeNotificationBridgeReady = true;
        window.__dgtNativeNotificationBridgeStartedAt = Date.now();

        const postNativeNotification = notification => {
          try {
            if (window.flutter_inappwebview) {
               window.flutter_inappwebview.callHandler('DgtNativeNotifications', JSON.stringify(notification));
            } else if (window.DgtNativeNotifications) {
               window.DgtNativeNotifications.postMessage(JSON.stringify(notification));
            }
          } catch (error) {
            console.error('DGT native notification bridge error', error);
          }
        };

        const notificationId = notification => String(notification?.id || '');
        const notificationCreatedAt = notification => notification?.created_at || notification?.data?.created_at || '';
        const notificationTimestamp = notification => Date.parse(notificationCreatedAt(notification)) || 0;
        const latestUnread = notifications => [...notifications]
          .filter(notification => notification && !notification.read_at && notificationId(notification))
          .sort((a, b) => notificationTimestamp(b) - notificationTimestamp(a))[0];

        const normalizeNotification = notification => {
          const data = notification?.data || {};
          const actorName = data.actor_name || data.sender_name || notification?.sender?.name || 'KIUQ SYSTEM';
          const title = notification?.title || data.title || data.card_title || data.board_name || 'KIUQ SYSTEM';
          const message = notification?.message || data.message || data.description || data.action || 'New KIUQ SYSTEM notification';
          const rawAvatar = data.actor_avatar || data.sender_avatar || notification?.sender?.avatar_url || '';
          const avatarUrl = rawAvatar && String(rawAvatar).startsWith('/')
            ? window.location.origin + rawAvatar
            : rawAvatar;
          return {
            id: notificationId(notification),
            title: String(title).replace(/\\*\\*/g, ''),
            subtitle: String(actorName).replace(/\\*\\*/g, ''),
            message: String(message).replace(/\\*\\*/g, ''),
            link: data.link || notification?.link || '',
            avatarUrl: avatarUrl || ''
          };
        };

        const loadPusherScript = () => new Promise(resolve => {
          if (window.Pusher) {
            resolve(true);
            return;
          }

          if (window.__dgtPusherScriptLoading) {
            window.__dgtPusherScriptCallbacks.push(resolve);
            return;
          }

          window.__dgtPusherScriptLoading = true;
          window.__dgtPusherScriptCallbacks = [resolve];
          const script = document.createElement('script');
          script.src = 'https://js.pusher.com/8.4.0/pusher.min.js';
          script.async = true;
          script.onload = () => {
            window.__dgtPusherScriptLoading = false;
            window.__dgtPusherScriptCallbacks.splice(0).forEach(callback => callback(true));
          };
          script.onerror = () => {
            window.__dgtPusherScriptLoading = false;
            window.__dgtPusherScriptCallbacks.splice(0).forEach(callback => callback(false));
          };
          document.head.appendChild(script);
        });

        window.__dgtNativeConnectPusher = async () => {
          const userId = document.querySelector('meta[name="kiuq-user-id"]')?.content;
          const key = document.querySelector('meta[name="kiuq-pusher-key"]')?.content;
          const cluster = document.querySelector('meta[name="kiuq-pusher-cluster"]')?.content || 'ap1';
          const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

          if (!userId || !key || !csrf) return;
          if (window.__dgtNativePusherUserId === userId && window.__dgtNativePusher) return;

          const loaded = await loadPusherScript();
          if (!loaded || !window.Pusher) return;

          window.__dgtNativePusherUserId = userId;
          window.__dgtNativePusher = new Pusher(key, {
            cluster,
            forceTLS: true,
            authEndpoint: '/broadcasting/auth',
            auth: {
              headers: {
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
              }
            }
          });

          const channel = window.__dgtNativePusher.subscribe('private-App.Models.User.' + userId);
          const handleBroadcast = notification => {
            if (!notification) return;
            const normalized = normalizeNotification(notification);
            if (!normalized.id) return;
            localStorage.setItem('dgt_native_last_seen_notification_id', normalized.id);
            localStorage.setItem(`dgt_native_notification_shown_\${normalized.id}`, 'true');
            postNativeNotification(normalized);
          };

          channel.bind('Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated', handleBroadcast);
          channel.bind('Illuminate\\\\\\\\Notifications\\\\\\\\Events\\\\\\\\BroadcastNotificationCreated', handleBroadcast);
          channel.bind_global((eventName, notification) => {
            if (String(eventName).includes('BroadcastNotificationCreated')) {
              handleBroadcast(notification);
            }
          });
        };

        window.__dgtNativeNotificationPoll = async () => {
          try {
            const response = await fetch('/notifications?limit=10', {
              credentials: 'same-origin',
              headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
              }
            });

            if (!response.ok) return;

            const payload = await response.json();
            const notifications = Array.isArray(payload.notifications) ? payload.notifications : [];
            const newest = latestUnread(notifications);
            if (!newest) return;

            const newestId = notificationId(newest);
            const newestTimestamp = notificationTimestamp(newest);
            const lastSeenKey = 'dgt_native_last_seen_notification_id';
            const shownKey = `dgt_native_notification_shown_\${newestId}`;
            const lastSeenId = localStorage.getItem(lastSeenKey);

            if (!lastSeenId) {
              localStorage.setItem(lastSeenKey, newestId);
              if (newestTimestamp <= window.__dgtNativeNotificationBridgeStartedAt) {
                return;
              }
            }

            if (newestId === lastSeenId || localStorage.getItem(shownKey) === 'true') {
              return;
            }

            localStorage.setItem(lastSeenKey, newestId);
            localStorage.setItem(shownKey, 'true');
            postNativeNotification(normalizeNotification(newest));
          } catch (error) {
            console.error('DGT native notification polling error', error);
          }
        };

        window.__dgtNativeConnectPusher();
        window.__dgtNativeNotificationPoll();
        window.__dgtNativeNotificationTimer = window.setInterval(() => {
          window.__dgtNativeNotificationPoll();
        }, 2000);

        document.addEventListener('visibilitychange', () => {
          window.clearInterval(window.__dgtNativeNotificationTimer);
          window.__dgtNativeNotificationTimer = window.setInterval(() => {
            window.__dgtNativeNotificationPoll();
          }, 2000);
        });
      })();
    ''');
  }

  Future<void> _handleNativeNotificationMessage(String message) async {
    final payload = jsonDecode(message);
    if (payload is! Map<String, dynamic>) {
      return;
    }

    await _showNativeNotificationPayload(payload);
  }

  Future<void> _showNativeNotificationPayload(
    Map<String, dynamic> payload,
  ) async {
    final id = (payload['id'] ?? '').toString();
    if (id.isEmpty) {
      return;
    }

    final title = _cleanNotificationText(payload['title'] ?? 'KIUQ SYSTEM');
    final subtitle = _cleanNotificationText(payload['subtitle'] ?? '');
    final body = _cleanNotificationText(
      payload['message'] ?? 'New notification',
    );
    final link = (payload['link'] ?? '').toString();
    final fingerprint = _notificationFingerprint(
      title: title,
      subtitle: subtitle,
      body: body,
      link: link,
    );

    _forgetOldNotificationFingerprints();
    if (shownNativeNotificationIds.contains(id) ||
        recentNativeNotificationFingerprints.containsKey(fingerprint)) {
      return;
    }

    shownNativeNotificationIds.add(id);
    recentNativeNotificationFingerprints[fingerprint] = DateTime.now();
    final avatarUrl = (payload['avatarUrl'] ?? '').toString();

    await NativeNotificationService.show(
      id: id,
      title: title.isEmpty ? 'KIUQ SYSTEM' : title,
      subtitle: subtitle,
      body: body,
      link: link,
      avatarUrl: avatarUrl,
    );
  }

  String _cleanNotificationText(Object? value) {
    return value
        .toString()
        .replaceAll(RegExp(r'<[^>]*>'), '')
        .replaceAll(RegExp(r'\s+'), ' ')
        .trim();
  }

  String _notificationFingerprint({
    required String title,
    required String subtitle,
    required String body,
    required String link,
  }) {
    return '$title|$subtitle|$body|$link'.toLowerCase();
  }

  void _forgetOldNotificationFingerprints() {
    final cutoff = DateTime.now().subtract(const Duration(minutes: 3));
    recentNativeNotificationFingerprints.removeWhere(
      (_, shownAt) => shownAt.isBefore(cutoff),
    );
  }

  @override
  Widget build(BuildContext context) {
    return CallbackShortcuts(
      bindings: <ShortcutActivator, VoidCallback>{
        const SingleActivator(LogicalKeyboardKey.keyR, meta: true): _reload,
        const SingleActivator(LogicalKeyboardKey.keyR, control: true): _reload,
      },
      child: Focus(
        autofocus: true,
        child: Scaffold(
          backgroundColor: const Color(0xFFF4F7FB),
          body: Stack(
        children: [
          Positioned.fill(
            child: InAppWebView(
              initialUrlRequest: URLRequest(url: WebUri(appUri.toString())),
              initialSettings: InAppWebViewSettings(
                userAgent: 'DGTSystemMacOSApp/1.0',
                javaScriptEnabled: true,
                transparentBackground: true,
              ),
              onWebViewCreated: (webViewController) {
                controller = webViewController;
                controller?.addJavaScriptHandler(
                  handlerName: 'DgtNativeNotifications',
                  callback: (args) {
                    if (args.isNotEmpty) {
                      _handleNativeNotificationMessage(args[0].toString());
                    }
                  },
                );
              },
              onProgressChanged: (controller, progress) {
                if (!hasLoadedFirstPage) {
                  setState(() => loadingProgress = progress);
                }
              },
              onLoadStart: (controller, url) {
                setState(() {
                  loadError = null;
                  if (!hasLoadedFirstPage) {
                    loadingProgress = 0;
                  }
                });
              },
              onLoadStop: (controller, url) async {
                setState(() {
                  loadingProgress = 100;
                  hasLoadedFirstPage = true;
                });
                await _prepareOfficialAppSurface();
                notificationPoller.start();
              },
              onReceivedError: (controller, request, error) {
                if (request.isForMainFrame ?? false) {
                  final desc = error.description.toLowerCase();
                  // Ignore harmless cancellation errors caused by Turbo routing/back navigation
                  if (desc.contains('-999') || desc.contains('cancelled') || desc.contains('aborted')) {
                    return;
                  }
                  setState(() => loadError = error.description);
                }
              },
              shouldOverrideUrlLoading: (controller, navigationAction) async {
                final uri = navigationAction.request.url;
                if (uri != null) {
                  if (_isInternalUrl(uri)) {
                    return NavigationActionPolicy.ALLOW;
                  }
                  launchUrl(uri, mode: LaunchMode.externalApplication);
                  return NavigationActionPolicy.CANCEL;
                }
                return NavigationActionPolicy.ALLOW;
              },
            ),
          ),
          if (!hasLoadedFirstPage && loadingProgress < 100 && loadError == null)
            Positioned(
              top: 0,
              left: 0,
              right: 0,
              child: LinearProgressIndicator(
                value: loadingProgress / 100,
                minHeight: 2,
                color: const Color(0xFF2F68ED),
                backgroundColor: const Color(0xFFEAF2FF),
              ),
            ),
          if (loadError != null)
            _LoadErrorPanel(
              message: loadError!,
              url: appBaseUrl,
              onRetry: _reload,
            ),
        ],
      ),
        ),
      ),
    );
  }
}

class NativeNotificationPoller {
  NativeNotificationPoller({
    required this.appUri,
    required this.onNotification,
  });

  final Uri appUri;
  final Future<void> Function(Map<String, dynamic> payload) onNotification;
  final CookieManager cookieManager = CookieManager.instance();
  final Set<String> shownIds = <String>{};
  final DateTime startedAt = DateTime.now();

  Timer? _timer;
  bool _isPolling = false;
  bool _isBackground = false;

  void start() {
    _timer ??= Timer.periodic(_pollInterval, (_) => poll());
    poll();
  }

  void setBackgroundMode(bool isBackground) {
    if (_isBackground == isBackground) {
      return;
    }

    _isBackground = isBackground;
    if (_timer != null) {
      _timer?.cancel();
      _timer = Timer.periodic(_pollInterval, (_) => poll());
    }
  }

  Duration get _pollInterval => const Duration(seconds: 2);

  Future<void> poll() async {
    if (_isPolling) {
      return;
    }

    _isPolling = true;
    try {
      final payload = await _fetchNotifications();
      final notifications = _readNotifications(payload);
      final newest = _latestUnread(notifications);
      if (newest == null) {
        return;
      }

      final id = _notificationId(newest);
      if (id.isEmpty || shownIds.contains(id)) {
        return;
      }

      final preferences = await SharedPreferences.getInstance();
      final lastSeenKey = 'dgt_native_last_seen_notification_id';
      final shownKey = 'dgt_native_notification_shown_$id';
      final lastSeenId = preferences.getString(lastSeenKey);
      final alreadyShown = preferences.getBool(shownKey) ?? false;

      if (lastSeenId == null) {
        await preferences.setString(lastSeenKey, id);
        if (!_wasCreatedAfterAppStart(newest)) {
          return;
        }
      }

      if (id == lastSeenId || alreadyShown) {
        return;
      }

      shownIds.add(id);
      await preferences.setString(lastSeenKey, id);
      await preferences.setBool(shownKey, true);
      await onNotification(_normalizeNotification(newest));
    } catch (_) {
      // Polling is best-effort; the next timer tick will retry.
    } finally {
      _isPolling = false;
    }
  }

  Future<Map<String, dynamic>?> _fetchNotifications() async {
    final cookies = await cookieManager.getCookies(url: WebUri(appUri.toString()));
    if (cookies.isEmpty) {
      return null;
    }

    final notificationUri = appUri.replace(
      path: '/notifications',
      queryParameters: const {'limit': '10'},
    );
    final client = HttpClient()..connectionTimeout = const Duration(seconds: 8);

    try {
      final request = await client.getUrl(notificationUri);
      request.headers.set(HttpHeaders.acceptHeader, 'application/json');
      request.headers.set('X-Requested-With', 'XMLHttpRequest');
      request.headers.set(
        HttpHeaders.cookieHeader,
        cookies.map((cookie) => '${cookie.name}=${cookie.value}').join('; '),
      );

      final response = await request.close();
      if (response.statusCode < 200 || response.statusCode >= 300) {
        await response.drain<void>();
        return null;
      }

      final body = await response.transform(utf8.decoder).join();
      final decoded = jsonDecode(body);
      return decoded is Map<String, dynamic> ? decoded : null;
    } finally {
      client.close(force: true);
    }
  }

  List<Map<String, dynamic>> _readNotifications(Map<String, dynamic>? payload) {
    final rawNotifications = payload?['notifications'];
    if (rawNotifications is! List) {
      return const <Map<String, dynamic>>[];
    }

    return rawNotifications
        .whereType<Map>()
        .map((notification) => Map<String, dynamic>.from(notification))
        .toList();
  }

  Map<String, dynamic>? _latestUnread(
    List<Map<String, dynamic>> notifications,
  ) {
    final unread =
        notifications
            .where(
              (notification) =>
                  _notificationId(notification).isNotEmpty &&
                  (notification['read_at'] == null ||
                      notification['read_at'].toString().isEmpty),
            )
            .toList()
          ..sort(
            (a, b) =>
                _notificationCreatedAt(b).compareTo(_notificationCreatedAt(a)),
          );

    return unread.isEmpty ? null : unread.first;
  }

  String _notificationId(Map<String, dynamic> notification) =>
      (notification['id'] ?? '').toString();

  DateTime _notificationCreatedAt(Map<String, dynamic> notification) {
    final data = notification['data'];
    final createdAt =
        notification['created_at'] ??
        (data is Map ? data['created_at'] : null) ??
        '';
    return DateTime.tryParse(createdAt.toString()) ??
        DateTime.fromMillisecondsSinceEpoch(0);
  }

  bool _wasCreatedAfterAppStart(Map<String, dynamic> notification) {
    return _notificationCreatedAt(notification).isAfter(startedAt);
  }

  Map<String, dynamic> _normalizeNotification(
    Map<String, dynamic> notification,
  ) {
    final data = notification['data'];
    final notificationData = data is Map ? Map<String, dynamic>.from(data) : {};
    final sender = notification['sender'];
    final senderData = sender is Map ? sender : const {};

    final actorName =
        notificationData['actor_name'] ??
        notificationData['sender_name'] ??
        senderData['name'] ??
        'KIUQ SYSTEM';
    final avatarUrl =
        notificationData['actor_avatar'] ??
        notificationData['sender_avatar'] ??
        senderData['avatar_url'] ??
        '';
    final title =
        notification['title'] ??
        notificationData['title'] ??
        notificationData['card_title'] ??
        notificationData['board_name'] ??
        'KIUQ SYSTEM';
    final message =
        notification['message'] ??
        notificationData['message'] ??
        notificationData['description'] ??
        notificationData['action'] ??
        'New KIUQ SYSTEM notification';

    return {
      'id': _notificationId(notification),
      'title': title.toString().replaceAll('**', ''),
      'subtitle': actorName.toString().replaceAll('**', ''),
      'message': message.toString().replaceAll('**', ''),
      'link': notificationData['link'] ?? notification['link'] ?? '',
      'avatarUrl': avatarUrl.toString(),
    };
  }

  void dispose() {
    _timer?.cancel();
    _timer = null;
  }
}

class _LoadErrorPanel extends StatelessWidget {
  const _LoadErrorPanel({
    required this.message,
    required this.url,
    required this.onRetry,
  });

  final String message;
  final String url;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.white,
      alignment: Alignment.center,
      padding: const EdgeInsets.all(32),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 520),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 64,
              height: 64,
              decoration: BoxDecoration(
                color: const Color(0xFFEAF2FF),
                borderRadius: BorderRadius.circular(14),
              ),
              alignment: Alignment.center,
              child: const Text(
                'KQ',
                style: TextStyle(
                  color: Color(0xFF2F68ED),
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
            const SizedBox(height: 18),
            const Text(
              'Cannot open KIUQ SYSTEM',
              style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            Text(url, style: const TextStyle(color: Color(0xFF64748B))),
            const SizedBox(height: 12),
            Text(
              message,
              textAlign: TextAlign.center,
              style: const TextStyle(color: Color(0xFFDC2626)),
            ),
            const SizedBox(height: 22),
            FilledButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Try Again'),
            ),
          ],
        ),
      ),
    );
  }
}
