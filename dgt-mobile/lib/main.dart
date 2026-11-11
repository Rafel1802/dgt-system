import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/cupertino.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_inappwebview/flutter_inappwebview.dart';
import 'package:url_launcher/url_launcher.dart';

import 'src/config/app_config.dart';
import 'src/models/shell_state.dart';
import 'src/services/ios_native_bridge.dart';
import 'src/services/session_storage_service.dart';
import 'src/services/webview_bridge_scripts.dart';
import 'src/theme/liquid_glass_theme.dart';
import 'src/widgets/liquid_glass_bottom_nav.dart';
import 'src/widgets/liquid_glass_hub_sheet.dart';
import 'src/widgets/liquid_glass_ipad_sidebar.dart';
import 'src/widgets/liquid_glass_overlays.dart';
import 'src/widgets/liquid_glass_quick_menu.dart';
import 'src/widgets/liquid_glass_surface.dart';

export 'src/config/app_config.dart'
    show appBaseUrl, configuredAppBaseUrl, resolveAppBaseUrl;

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
  SystemChrome.setSystemUIOverlayStyle(
    LiquidGlassPalette.of(KiuqThemeMode.light).systemOverlayStyle,
  );
  runApp(const DgtMobileApp());
}

/// Root KIUQ SYSTEM iOS & iPadOS application widget.
class DgtMobileApp extends StatelessWidget {
  const DgtMobileApp({
    super.key,
    this.enableWebView = true,
    this.initialShellState,
  });

  /// Allows widget tests to verify the Liquid Glass shell deterministically
  /// without instantiating platform `WKWebView` views when set to `false`.
  final bool enableWebView;
  final KiuqShellState? initialShellState;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'KIUQ SYSTEM',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF2563EB)),
        scaffoldBackgroundColor: const Color(0xFFF4F7FB),
      ),
      home: DgtWebsiteShell(
        enableWebView: enableWebView,
        initialShellState: initialShellState,
      ),
    );
  }
}

/// Primary hybrid shell combining the KIUQ SYSTEM WebView with native
/// Apple iOS 26 Liquid Glass navigation, adaptive iPadOS sidebar, offline
/// connection recovery, and native iOS QuickLook / Share / Notification bridges.
class DgtWebsiteShell extends StatefulWidget {
  const DgtWebsiteShell({
    super.key,
    this.enableWebView = true,
    this.initialShellState,
  });

  final bool enableWebView;
  final KiuqShellState? initialShellState;

  @override
  State<DgtWebsiteShell> createState() => _DgtWebsiteShellState();
}

class _DgtWebsiteShellState extends State<DgtWebsiteShell>
    with WidgetsBindingObserver {
  final SessionStorageService _storage = SessionStorageService();
  late final IosNativeBridge _nativeBridge;

  InAppWebViewController? _controller;
  PullToRefreshController? _pullToRefreshController;
  Timer? _loadWatchdogTimer;

  KiuqAppPreferences _prefs = const KiuqAppPreferences();
  late KiuqShellState _shellState;

  String _initialTargetUrl = appBaseUrl;
  bool _preferencesLoaded = false;
  bool _hasLoadedFirstPage = false;
  bool _splashMinimumDone = false;
  bool _isDockMinimized = false;
  bool _isOffline = false;
  bool _isRetryingConnection = false;
  bool _isDownloadingFile = false;
  String? _downloadingLabel;
  int _loadingProgress = 0;
  String? _loadError;

  Uri get _appUri => Uri.parse(appBaseUrl);

  String get _applicationUserAgentToken =>
      defaultTargetPlatform == TargetPlatform.android
      ? 'DGTSystemAndroidApp/1.1'
      : 'DGTSystemiOSApp/1.1';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);

    _shellState =
        widget.initialShellState ??
        KiuqShellState(
          currentUrl: appBaseUrl,
          primaryTabs: KiuqDefaultNavigation.primaryTabsForPath('/dashboard'),
          hubDestinations: KiuqDefaultNavigation.defaultHubDestinations(
            '/dashboard',
          ),
        );

    _nativeBridge = IosNativeBridge(
      onConnectivityChanged: _handleNativeConnectivityChange,
      onNotificationTap: (url) {
        final target = KiuqUrlPolicy.resolveInternalUrl(url);
        _navigateToUrl(target, useTurbo: true);
      },
    );

    if (widget.enableWebView && !kIsWeb) {
      _pullToRefreshController = PullToRefreshController(
        settings: PullToRefreshSettings(color: const Color(0xFF2563EB)),
        onRefresh: _reloadWorkspace,
      );
    }

    _initializeShell();
  }

  Future<void> _initializeShell() async {
    final loadedPrefs = await _storage.loadPreferences();
    final netStatus = await _nativeBridge.checkConnectivity();

    if (!mounted) return;
    setState(() {
      _prefs = loadedPrefs;
      _initialTargetUrl = loadedPrefs.lastRestorableUrl ?? appBaseUrl;
      if (widget.initialShellState == null) {
        _shellState = _shellState.copyWith(themeMode: loadedPrefs.themeMode);
      }
      _isOffline = !netStatus.isOnline;
      _preferencesLoaded = true;
      if (!widget.enableWebView) {
        _hasLoadedFirstPage = true;
      }
    });

    _applySystemOverlayStyle();

    Future<void>.delayed(const Duration(milliseconds: 260), () {
      if (mounted) {
        setState(() => _splashMinimumDone = true);
      }
    });
  }

  @override
  void dispose() {
    _loadWatchdogTimer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  // ─── App Lifecycle Management ─────────────────────────────────────────────

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.inactive ||
        state == AppLifecycleState.paused) {
      _storage.flushSessionCookies();
      if (_shellState.isAuthenticated) {
        _storage.saveLastRestorableUrl(_shellState.currentUrl);
      }
    } else if (state == AppLifecycleState.resumed) {
      _handleAppResumed();
    }
  }

  Future<void> _handleAppResumed() async {
    final status = await _nativeBridge.checkConnectivity();
    if (!mounted) return;

    if (!status.isOnline) {
      setState(() => _isOffline = true);
      return;
    }

    if (_isOffline || _loadError != null) {
      await _attemptConnectionRecovery(reloadIfNeeded: !_hasLoadedFirstPage);
    } else {
      // Gently synchronize live badges & theme without reloading the WebView.
      await _controller?.evaluateJavascript(
        source: 'window.__dgtSyncShellState && window.__dgtSyncShellState();',
      );
    }
  }

  void _applySystemOverlayStyle() {
    final palette = LiquidGlassPalette.of(_shellState.themeMode);
    SystemChrome.setSystemUIOverlayStyle(palette.systemOverlayStyle);
  }

  // ─── Connectivity & Recovery ──────────────────────────────────────────────

  Future<void> _handleNativeConnectivityChange(
    KiuqConnectivityStatus status,
  ) async {
    if (!mounted) return;
    if (!status.isOnline) {
      setState(() => _isOffline = true);
      return;
    }

    await _attemptConnectionRecovery(
      reloadIfNeeded: !_hasLoadedFirstPage || _loadError != null,
    );
  }

  Future<void> _attemptConnectionRecovery({bool reloadIfNeeded = true}) async {
    if (_isRetryingConnection) return;
    setState(() => _isRetryingConnection = true);

    final ping = await _nativeBridge.pingServer();
    if (!mounted) return;

    if (!ping.isOnline) {
      setState(() {
        _isOffline = true;
        _isRetryingConnection = false;
      });
      return;
    }

    setState(() {
      _isOffline = false;
      _loadError = null;
      _isRetryingConnection = false;
    });

    if (reloadIfNeeded && _controller != null) {
      final currentUrl = await _controller!.getUrl();
      if (currentUrl == null || currentUrl.toString() == 'about:blank') {
        await _controller!.loadUrl(
          urlRequest: URLRequest(url: WebUri(_initialTargetUrl)),
        );
      } else {
        await _controller!.reload();
      }
    } else {
      await _controller?.evaluateJavascript(
        source: 'window.__dgtSyncShellState && window.__dgtSyncShellState();',
      );
    }
  }

  Future<void> _reloadWorkspace() async {
    setState(() => _loadError = null);
    if (_isOffline) {
      await _attemptConnectionRecovery(reloadIfNeeded: true);
      return;
    }
    await _controller?.reload();
  }

  void _startLoadWatchdog() {
    _loadWatchdogTimer?.cancel();
    _loadWatchdogTimer = Timer(const Duration(seconds: 22), () async {
      if (!mounted) return;
      if (_loadingProgress < 100 && !_hasLoadedFirstPage) {
        final ping = await _nativeBridge.pingServer();
        if (!mounted) return;
        setState(() {
          _loadingProgress = 100;
          if (!ping.isOnline) {
            _isOffline = true;
            _loadError = 'The server took too long to respond.';
          } else {
            _hasLoadedFirstPage = true;
          }
        });
      }
    });
  }

  // ─── WebView JS Bridges & Navigation ──────────────────────────────────────

  void _registerJavaScriptBridges(InAppWebViewController webViewController) {
    // 1. Live DOM Shell State Sync
    webViewController.addJavaScriptHandler(
      handlerName: 'DgtShellState',
      callback: (args) async {
        if (args.isEmpty || args.first is! Map || !mounted) return null;
        final raw = Map<String, dynamic>.from(args.first as Map);
        final isAuthed = raw['isAuthenticated'] == true;
        final currentUrl = (raw['currentUrl'] ?? '').toString();
        final pageTitle = (raw['pageTitle'] ?? 'KIUQ SYSTEM').toString();
        final themeMode = KiuqThemeMode.fromString(
          (raw['themeMode'] ?? 'light').toString(),
        );

        final parsedPrimary = <KiuqNavDestination>[];
        if (raw['primaryTabs'] is List) {
          for (final item in raw['primaryTabs'] as List) {
            if (item is Map) {
              parsedPrimary.add(
                KiuqNavDestination.fromJson(Map<String, dynamic>.from(item)),
              );
            }
          }
        }

        final parsedHub = <KiuqNavDestination>[];
        if (raw['hubDestinations'] is List) {
          for (final item in raw['hubDestinations'] as List) {
            if (item is Map) {
              parsedHub.add(
                KiuqNavDestination.fromJson(Map<String, dynamic>.from(item)),
              );
            }
          }
        }

        final userMap = raw['user'] is Map
            ? Map<String, dynamic>.from(raw['user'] as Map)
            : null;
        final canBack = await webViewController.canGoBack();

        if (!mounted) return null;

        final themeChanged = themeMode != _shellState.themeMode;
        setState(() {
          _shellState = _shellState.copyWith(
            isAuthenticated: isAuthed,
            currentUrl: currentUrl.isNotEmpty
                ? currentUrl
                : _shellState.currentUrl,
            pageTitle: pageTitle,
            canGoBack: canBack,
            themeMode: themeMode,
            unreadNotifications:
                int.tryParse('${raw['unreadNotifications'] ?? 0}') ?? 0,
            unreadChat: int.tryParse('${raw['unreadChat'] ?? 0}') ?? 0,
            tasksCount: int.tryParse('${raw['tasksCount'] ?? 0}') ?? 0,
            primaryTabs: parsedPrimary.isNotEmpty
                ? parsedPrimary
                : KiuqDefaultNavigation.primaryTabsForPath(currentUrl),
            hubDestinations: parsedHub.isNotEmpty
                ? parsedHub
                : KiuqDefaultNavigation.defaultHubDestinations(currentUrl),
            user: KiuqUserProfile.fromJson(userMap),
            isFullScreenModalOpen: raw['isFullScreenModalOpen'] == true,
            isChatThreadOpen: raw['isChatThreadOpen'] == true,
          );
          if (!isAuthed) {
            _isDockMinimized = false;
          }
        });

        if (themeChanged) {
          _applySystemOverlayStyle();
          await _storage.saveThemeMode(themeMode);
        }

        if (isAuthed && KiuqUrlPolicy.isRestorableAppUrl(currentUrl)) {
          await _storage.saveLastRestorableUrl(currentUrl);
        } else if (!isAuthed && currentUrl.contains('/login')) {
          await _storage.clearLastRestorableUrl();
        }
        return null;
      },
    );

    // 2. Apple Music iOS 26 Scroll-Minimize Observer
    webViewController.addJavaScriptHandler(
      handlerName: 'DgtScrollDirection',
      callback: (args) {
        if (!_prefs.scrollMinimizeEnabled || args.isEmpty || !mounted) {
          return null;
        }
        final direction = args.first.toString();
        final shouldMinimize = direction == 'down';
        if (shouldMinimize != _isDockMinimized) {
          setState(() => _isDockMinimized = shouldMinimize);
        }
        return null;
      },
    );

    // 3. External URL Bridge (used by Trello Boards & External Attachments)
    Future<dynamic> handleExternalBridge(List<dynamic> args) async {
      if (args.isEmpty) return false;
      final first = args.first;
      final rawUrl = first is Map
          ? (first['url'] ?? '').toString()
          : first.toString();
      final uri = Uri.tryParse(rawUrl.trim());
      if (uri == null || !KiuqUrlPolicy.isSafeExternalScheme(uri)) {
        return false;
      }
      if (KiuqUrlPolicy.isInternalUri(uri, baseUri: _appUri) &&
          KiuqUrlPolicy.isDownloadUri(uri)) {
        await _handleAuthenticatedDownload(uri);
        return true;
      }
      await _openSafeExternalUri(uri);
      return true;
    }

    webViewController.addJavaScriptHandler(
      handlerName: 'DgtOpenExternal',
      callback: handleExternalBridge,
    );
    webViewController.addJavaScriptHandler(
      handlerName: 'DgtOpenGoogleDoc',
      callback: handleExternalBridge,
    );

    // 4. Real-Time Pusher & Chat Native Notification Bridge
    webViewController.addJavaScriptHandler(
      handlerName: 'DgtNativeNotifications',
      callback: (args) async {
        if (!_prefs.notificationsEnabled || args.isEmpty) return false;
        final payload = args.first is Map
            ? Map<String, dynamic>.from(args.first as Map)
            : <String, dynamic>{'body': args.first.toString()};

        final action = (payload['action'] ?? 'notify').toString();
        if (action == 'requestPermission') {
          return _nativeBridge.requestNotificationPermission();
        }

        final title = (payload['title'] ?? 'KIUQ SYSTEM').toString();
        final body = (payload['body'] ?? payload['message'] ?? '').toString();
        final url = (payload['url'] ?? '').toString();
        if (body.trim().isEmpty && title.trim().isEmpty) return false;

        var status = await _nativeBridge.getNotificationStatus();
        if (status == 'notDetermined' && !_prefs.notificationPromptShown) {
          await _storage.markNotificationPromptShown();
          _prefs = _prefs.copyWith(notificationPromptShown: true);
          final granted = await _nativeBridge.requestNotificationPermission();
          status = granted ? 'authorized' : 'denied';
        }

        if (status == 'authorized') {
          return _nativeBridge.showLocalNotification(
            title: title,
            body: body,
            url: url.isNotEmpty ? url : null,
          );
        }
        return false;
      },
    );

    // 5. Native Blob / Authenticated File Download Bridge
    webViewController.addJavaScriptHandler(
      handlerName: 'DgtNativeDownload',
      callback: (args) async {
        if (args.isEmpty || args.first is! Map) return false;
        final map = Map<String, dynamic>.from(args.first as Map);
        final base64Data = (map['base64'] ?? '').toString();
        final filename = (map['filename'] ?? '').toString();
        final mimeType = (map['mimeType'] ?? '').toString();
        final directUrl = (map['url'] ?? '').toString();

        if (base64Data.isNotEmpty) {
          setState(() {
            _isDownloadingFile = true;
            _downloadingLabel = 'Preparing ${filename.isNotEmpty ? filename : 'file'}...';
          });
          final result = await _nativeBridge.saveBase64AndPresent(
            base64Data: base64Data,
            fileName: filename,
            mimeType: mimeType,
          );
          if (mounted) {
            setState(() {
              _isDownloadingFile = false;
              _downloadingLabel = null;
            });
          }
          return result.success;
        }

        final parsed = Uri.tryParse(directUrl);
        if (parsed != null) {
          await _handleAuthenticatedDownload(
            parsed,
            suggestedFilename: filename,
          );
          return true;
        }
        return false;
      },
    );
  }

  Future<void> _syncWebSurfaceMetrics(BuildContext context) async {
    final webView = _controller;
    if (webView == null || !mounted) return;

    final viewPadding = MediaQuery.viewPaddingOf(context);
    final mediaPadding = MediaQuery.paddingOf(context);
    final size = MediaQuery.sizeOf(context);
    final isIpad = size.shortestSide >= 600;

    final topSafe = math.max(viewPadding.top, mediaPadding.top);
    final bottomSafe = math.max(viewPadding.bottom, mediaPadding.bottom);
    final leftSafe = isIpad
        ? 0.0
        : math.max(viewPadding.left, mediaPadding.left);
    final rightSafe = math.max(viewPadding.right, mediaPadding.right);
    final dockClearance = isIpad
        ? math.max(bottomSafe, 16.0)
        : LiquidGlassBottomNav.reservedClearanceFor(
            context,
            minimized: _isDockMinimized,
          );

    await webView.evaluateJavascript(
      source: WebViewBridgeScripts.buildBootstrapScript(
        safeTopPx: topSafe,
        safeBottomPx: bottomSafe,
        safeLeftPx: leftSafe,
        safeRightPx: rightSafe,
        bottomDockClearancePx: dockClearance,
        isIpadLayout: isIpad,
      ),
    );
  }

  Future<void> _handleDestinationSelected(KiuqNavDestination dest) async {
    switch (dest.actionType) {
      case KiuqNavActionType.kiuqAi:
        await _openKiuqAi();
        return;
      case KiuqNavActionType.hub:
        await _openHubSheet();
        return;
      case KiuqNavActionType.notifications:
        await _openNotifications();
        return;
      case KiuqNavActionType.externalUrl:
        final uri = Uri.tryParse(dest.url);
        if (uri != null && KiuqUrlPolicy.isSafeExternalScheme(uri)) {
          await _openSafeExternalUri(uri);
        }
        return;
      case KiuqNavActionType.logout:
        await _confirmAndSignOut();
        return;
      case KiuqNavActionType.route:
        if (dest.url.trim().isEmpty) return;
        final targetUrl = KiuqUrlPolicy.resolveInternalUrl(dest.url);
        // Optimistic active tab highlight for instant fluid feedback
        setState(() {
          _shellState = _shellState.copyWith(
            currentUrl: targetUrl,
            primaryTabs: _shellState.primaryTabs
                .map((t) => t.copyWith(isActive: t.id == dest.id))
                .toList(),
            hubDestinations: _shellState.hubDestinations
                .map(
                  (h) => h.copyWith(
                    isActive: h.id == dest.id || h.url == dest.url,
                  ),
                )
                .toList(),
          );
        });
        await _navigateToUrl(targetUrl, useTurbo: dest.useTurbo);
    }
  }

  Future<void> _navigateToUrl(String targetUrl, {bool useTurbo = true}) async {
    final webView = _controller;
    if (webView == null) return;

    final current = await webView.getUrl();
    final currentUri = current != null ? Uri.tryParse(current.toString()) : null;
    if (currentUri != null &&
        !KiuqUrlPolicy.isInternalUri(currentUri, baseUri: _appUri)) {
      await webView.loadUrl(urlRequest: URLRequest(url: WebUri(targetUrl)));
      return;
    }

    await webView.evaluateJavascript(
      source: WebViewBridgeScripts.navigateToUrlScript(
        targetUrl,
        useTurbo: useTurbo,
      ),
    );
  }

  Future<void> _openKiuqAi() async {
    await _controller?.evaluateJavascript(
      source: WebViewBridgeScripts.openKiuqAiScript(),
    );
  }

  Future<void> _openNotifications() async {
    final clicked = await _controller?.evaluateJavascript(
      source: WebViewBridgeScripts.openNotificationsDropdownScript(),
    );
    if (clicked != true) {
      await _navigateToUrl(
        KiuqUrlPolicy.resolveInternalUrl('/dashboard'),
        useTurbo: true,
      );
    }
  }

  Future<void> _changeThemeMode(KiuqThemeMode mode) async {
    setState(() {
      _shellState = _shellState.copyWith(themeMode: mode);
      _prefs = _prefs.copyWith(themeMode: mode);
    });
    _applySystemOverlayStyle();
    await _storage.saveThemeMode(mode);
    await _controller?.evaluateJavascript(
      source: WebViewBridgeScripts.setWebThemeScript(mode),
    );
  }

  Future<void> _shareCurrentPage() async {
    final urlToShare = _shellState.currentUrl.isNotEmpty
        ? _shellState.currentUrl
        : appBaseUrl;
    final sharedNatively = await _nativeBridge.shareUrl(
      url: urlToShare,
      title: _shellState.pageTitle,
    );
    if (!sharedNatively) {
      await Clipboard.setData(ClipboardData(text: urlToShare));
    }
  }

  Future<void> _confirmAndSignOut() async {
    final palette = LiquidGlassPalette.of(_shellState.themeMode);
    final confirmed = await showLiquidGlassConfirmDialog(
      context: context,
      palette: palette,
      title: 'Sign Out of KIUQ SYSTEM?',
      message:
          'You will be signed out of your current workspace session on this device.',
      confirmLabel: 'Sign Out',
      icon: CupertinoIcons.square_arrow_right,
      isDestructive: true,
    );
    if (!confirmed) return;

    await _storage.clearLastRestorableUrl();
    setState(() {
      _shellState = _shellState.copyWith(isAuthenticated: false);
    });
    await _controller?.evaluateJavascript(
      source: WebViewBridgeScripts.triggerLogoutScript(
        KiuqUrlPolicy.resolveInternalUrl('/logout'),
      ),
    );
  }

  Future<void> _openSafeExternalUri(Uri uri) async {
    if (!KiuqUrlPolicy.isSafeExternalScheme(uri)) return;
    try {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    } catch (_) {}
  }

  Future<void> _handleAuthenticatedDownload(
    Uri uri, {
    String? suggestedFilename,
  }) async {
    if (_isDownloadingFile) return;

    if (!KiuqUrlPolicy.isInternalUri(uri, baseUri: _appUri)) {
      await _openSafeExternalUri(uri);
      return;
    }

    setState(() {
      _isDownloadingFile = true;
      _downloadingLabel = 'Downloading document...';
    });

    final result = await _nativeBridge.downloadAndPresentAuthenticatedFile(
      uri: uri,
      suggestedFilename: suggestedFilename,
    );

    if (!mounted) return;
    setState(() {
      _isDownloadingFile = false;
      _downloadingLabel = null;
    });

    if (!result.success) {
      // Fallback to external launcher if the file is publicly hosted
      await _openSafeExternalUri(uri);
    }
  }

  Future<PermissionResponse> _handleWebViewPermissionRequest(
    PermissionRequest permissionRequest,
  ) async {
    final originHost = permissionRequest.origin.host.toLowerCase();
    if (originHost != _appUri.host.toLowerCase()) {
      return PermissionResponse(
        resources: permissionRequest.resources,
        action: PermissionResponseAction.DENY,
      );
    }

    final needsCamera = permissionRequest.resources.contains(
      PermissionResourceType.CAMERA,
    );
    final needsMic = permissionRequest.resources.contains(
      PermissionResourceType.MICROPHONE,
    );
    final needsBoth = permissionRequest.resources.contains(
      PermissionResourceType.CAMERA_AND_MICROPHONE,
    );

    final resourceLabel = (needsBoth || (needsCamera && needsMic))
        ? 'Camera & Microphone'
        : (needsCamera ? 'Camera' : (needsMic ? 'Microphone' : 'Media Device'));
    final consentKey = '$originHost:$resourceLabel';

    if (!_storage.hasSessionMediaConsent(consentKey) && mounted) {
      final palette = LiquidGlassPalette.of(_shellState.themeMode);
      final userApproved = await showLiquidGlassConfirmDialog(
        context: context,
        palette: palette,
        title: 'Allow $resourceLabel Access?',
        message:
            'KIUQ SYSTEM is requesting temporary access to your $resourceLabel for this action.',
        confirmLabel: 'Allow',
        icon: needsMic && !needsCamera
            ? CupertinoIcons.mic_fill
            : CupertinoIcons.camera_fill,
      );
      if (!userApproved) {
        return PermissionResponse(
          resources: permissionRequest.resources,
          action: PermissionResponseAction.DENY,
        );
      }
      _storage.rememberSessionMediaConsent(consentKey);
    }

    if (needsCamera || needsBoth) {
      final granted = await _nativeBridge.requestMediaPermission('camera');
      if (!granted) {
        return PermissionResponse(
          resources: permissionRequest.resources,
          action: PermissionResponseAction.DENY,
        );
      }
    }

    if (needsMic || needsBoth) {
      final granted = await _nativeBridge.requestMediaPermission('microphone');
      if (!granted) {
        return PermissionResponse(
          resources: permissionRequest.resources,
          action: PermissionResponseAction.DENY,
        );
      }
    }

    return PermissionResponse(
      resources: permissionRequest.resources,
      action: PermissionResponseAction.GRANT,
    );
  }

  // ─── Sheets & Menus ───────────────────────────────────────────────────────

  Future<void> _openHubSheet() async {
    final palette = LiquidGlassPalette.of(_shellState.themeMode);
    await showLiquidGlassHubSheet(
      context: context,
      palette: palette,
      shellState: _shellState,
      enableHaptics: _prefs.hapticsEnabled,
      onSelectDestination: _handleDestinationSelected,
      onThemeChanged: _changeThemeMode,
      onShareCurrentPage: _shareCurrentPage,
      onReloadPage: _reloadWorkspace,
      onOpenAppSettings: _openAppSettingsSheet,
      onOpenNotifications: _openNotifications,
      onSignOut: _confirmAndSignOut,
    );
  }

  Future<void> _openQuickMenu() async {
    final palette = LiquidGlassPalette.of(_shellState.themeMode);
    await showLiquidGlassQuickMenu(
      context: context,
      palette: palette,
      canGoBack: _shellState.canGoBack,
      onGoBack: () async {
        if (await _controller?.canGoBack() == true) {
          await _controller?.goBack();
        }
      },
      onOpenKiuqAi: _openKiuqAi,
      onReload: _reloadWorkspace,
      onSharePage: _shareCurrentPage,
      onOpenAppSettings: _openAppSettingsSheet,
    );
  }

  Future<void> _openAppSettingsSheet() async {
    await showLiquidGlassAppSettingsSheet(
      context: context,
      preferences: _prefs,
      currentTheme: _shellState.themeMode,
      nativeBridge: _nativeBridge,
      onThemeChanged: _changeThemeMode,
      onScrollMinimizeChanged: (enabled) {
        setState(() {
          _prefs = _prefs.copyWith(scrollMinimizeEnabled: enabled);
          if (!enabled) _isDockMinimized = false;
        });
        _storage.saveScrollMinimize(enabled);
      },
      onHapticsChanged: (enabled) {
        setState(() => _prefs = _prefs.copyWith(hapticsEnabled: enabled));
        _storage.saveHaptics(enabled);
      },
      onNotificationsChanged: (enabled) {
        setState(
          () => _prefs = _prefs.copyWith(notificationsEnabled: enabled),
        );
        _storage.saveNotificationsEnabled(enabled);
      },
      onClearCacheKeepSession: () async {
        await _storage.clearWebCachePreservingSession();
      },
      onFullResetSession: () async {
        await _storage.performFullSessionReset();
        if (!mounted) return;
        setState(() {
          _shellState = _shellState.copyWith(isAuthenticated: false);
        });
        await _controller?.loadUrl(
          urlRequest: URLRequest(
            url: WebUri(KiuqUrlPolicy.resolveInternalUrl('/login')),
          ),
        );
      },
    );
  }

  // ─── Build ────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final palette = LiquidGlassPalette.of(_shellState.themeMode);
    final media = MediaQuery.of(context);
    final isIpadLayout = media.size.shortestSide >= 600;
    final isKeyboardVisible = media.viewInsets.bottom > 80;

    final currentUri = Uri.tryParse(_shellState.currentUrl);
    final isOnOAuthDomain = KiuqUrlPolicy.isInAppOAuthUri(currentUri);

    final showNavigationChrome =
        _shellState.isAuthenticated && !isOnOAuthDomain;
    final hideFloatingPhoneDock =
        !showNavigationChrome ||
        isKeyboardVisible ||
        _shellState.isFullScreenModalOpen ||
        _shellState.isChatThreadOpen;

    final showColdOfflineScreen =
        !_hasLoadedFirstPage && (_isOffline || _loadError != null);
    final showMidSessionOfflineBanner =
        _hasLoadedFirstPage && (_isOffline || _loadError != null);
    final showLaunchVeil =
        widget.enableWebView &&
        (!_preferencesLoaded || !_hasLoadedFirstPage || !_splashMinimumDone) &&
        !showColdOfflineScreen;

    final primaryTabs = _shellState.primaryTabs.isNotEmpty
        ? _shellState.primaryTabs
        : KiuqDefaultNavigation.primaryTabsForPath(_shellState.currentUrl);
    final ipadDestinations = _shellState.hubDestinations.isNotEmpty
        ? _shellState.hubDestinations
        : KiuqDefaultNavigation.defaultHubDestinations(_shellState.currentUrl);

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: palette.systemOverlayStyle,
      child: PopScope(
        canPop: false,
        onPopInvokedWithResult: (didPop, _) async {
          if (didPop) return;
          if (_controller != null && await _controller!.canGoBack()) {
            await _controller!.goBack();
          }
        },
        child: Scaffold(
          resizeToAvoidBottomInset: false,
          backgroundColor: palette.scaffoldBackground,
          body: Stack(
            children: <Widget>[
              // ── Adaptive Workspace Body (iPad Sidebar + WebView OR Edge-to-Edge iPhone WebView)
              Positioned.fill(
                child: Row(
                  children: <Widget>[
                    if (isIpadLayout && showNavigationChrome)
                      LiquidGlassIpadSidebar(
                        destinations: ipadDestinations,
                        palette: palette,
                        themeMode: _shellState.themeMode,
                        user: _shellState.user,
                        isExpanded: _prefs.ipadSidebarExpanded,
                        unreadNotifications: _shellState.unreadNotifications,
                        canGoBack: _shellState.canGoBack,
                        enableHaptics: _prefs.hapticsEnabled,
                        onToggleExpanded: () {
                          final next = !_prefs.ipadSidebarExpanded;
                          setState(() {
                            _prefs = _prefs.copyWith(ipadSidebarExpanded: next);
                          });
                          _storage.saveIpadSidebarExpanded(next);
                        },
                        onSelectDestination: _handleDestinationSelected,
                        onOpenSearchHub: _openHubSheet,
                        onOpenAppSettings: _openAppSettingsSheet,
                        onThemeChanged: _changeThemeMode,
                        onSignOut: _confirmAndSignOut,
                        onBackTap: () async {
                          if (await _controller?.canGoBack() == true) {
                            await _controller?.goBack();
                          }
                        },
                        onReloadTap: _reloadWorkspace,
                      ),
                    Expanded(
                      child: _buildWebViewContainer(context, palette),
                    ),
                  ],
                ),
              ),

              // ── iPhone Floating Apple Music iOS 26 Liquid Glass Capsule Navigation
              if (!isIpadLayout && showNavigationChrome)
                Positioned(
                  left: 0,
                  right: 0,
                  bottom: 0,
                  child: LiquidGlassBottomNav(
                    tabs: primaryTabs,
                    palette: palette,
                    isMinimized:
                        _isDockMinimized && _prefs.scrollMinimizeEnabled,
                    isHidden: hideFloatingPhoneDock,
                    hubBadgeCount: _shellState.totalAttentionCount,
                    canGoBack: _shellState.canGoBack,
                    enableHaptics: _prefs.hapticsEnabled,
                    onSelectTab: _handleDestinationSelected,
                    onOpenHub: _openHubSheet,
                    onLongPressHub: _openQuickMenu,
                    onExpandRequested: () {
                      if (_isDockMinimized) {
                        setState(() => _isDockMinimized = false);
                      }
                    },
                    onBackTap: () async {
                      if (await _controller?.canGoBack() == true) {
                        await _controller?.goBack();
                      }
                    },
                  ),
                ),

              // ── OAuth Return Pill (when completing Google Sign-In)
              if (isOnOAuthDomain)
                LiquidGlassOAuthReturnPill(
                  palette: palette,
                  onReturnToApp: () {
                    _controller?.loadUrl(
                      urlRequest: URLRequest(url: WebUri(appBaseUrl)),
                    );
                  },
                ),

              // ── Subtle Top Activity / File Download Pill
              LiquidGlassTopActivityPill(
                palette: palette,
                isVisible:
                    _isDownloadingFile ||
                    (_hasLoadedFirstPage &&
                        _loadingProgress > 8 &&
                        _loadingProgress < 95 &&
                        !showMidSessionOfflineBanner),
                label: _downloadingLabel,
                progress: _isDownloadingFile ? null : (_loadingProgress / 100),
              ),

              // ── Non-Destructive Mid-Session Offline Banner
              LiquidGlassOfflineBanner(
                palette: palette,
                isVisible: showMidSessionOfflineBanner,
                isRetrying: _isRetryingConnection,
                detailMessage: _loadError,
                onRetry: () =>
                    _attemptConnectionRecovery(reloadIfNeeded: _loadError != null),
              ),

              // ── Cold-Start Full Offline Recovery Screen
              if (showColdOfflineScreen)
                Positioned.fill(
                  child: LiquidGlassOfflineScreen(
                    palette: palette,
                    serverUrl: appBaseUrl,
                    errorMessage: _loadError ?? 'Internet connection unavailable',
                    isRetrying: _isRetryingConnection,
                    onRetry: () =>
                        _attemptConnectionRecovery(reloadIfNeeded: true),
                  ),
                ),

              // ── Smooth Launch Veil
              Positioned.fill(
                child: LiquidGlassLaunchVeil(isVisible: showLaunchVeil),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildWebViewContainer(
    BuildContext context,
    LiquidGlassPalette palette,
  ) {
    if (!widget.enableWebView) {
      return ColoredBox(
        key: const ValueKey<String>('kiuq_mock_webview_canvas'),
        color: palette.scaffoldBackground,
        child: Center(
          child: Text(
            _shellState.pageTitle,
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: palette.textSecondary,
            ),
          ),
        ),
      );
    }

    if (!_preferencesLoaded) {
      return ColoredBox(color: palette.scaffoldBackground);
    }

    return InAppWebView(
      initialUrlRequest: URLRequest(url: WebUri(_initialTargetUrl)),
      initialSettings: InAppWebViewSettings(
        applicationNameForUserAgent: _applicationUserAgentToken,
        javaScriptEnabled: true,
        javaScriptCanOpenWindowsAutomatically: false,
        supportMultipleWindows: true,
        transparentBackground: false,
        allowFileAccessFromFileURLs: false,
        allowUniversalAccessFromFileURLs: false,
        sharedCookiesEnabled: true,
        mediaPlaybackRequiresUserGesture: false,
        allowsInlineMediaPlayback: true,
        allowsAirPlayForMediaPlayback: true,
        disableContextMenu: false,
        verticalScrollBarEnabled: false,
        horizontalScrollBarEnabled: false,
        allowsBackForwardNavigationGestures: true,
        useShouldOverrideUrlLoading: true,
        useOnDownloadStart: true,
        cacheEnabled: true,
        supportZoom: false,
        preferredContentMode: UserPreferredContentMode.MOBILE,
        overScrollMode: OverScrollMode.NEVER,
        disallowOverScroll: false,
        allowsLinkPreview: false,
        isInspectable: kDebugMode,
      ),
      pullToRefreshController: _pullToRefreshController,
      onWebViewCreated: (webViewController) {
        _controller = webViewController;
        _registerJavaScriptBridges(webViewController);
      },
      onPermissionRequest: (controller, permissionRequest) {
        return _handleWebViewPermissionRequest(permissionRequest);
      },
      onCreateWindow: (controller, createWindowAction) async {
        final targetUri = createWindowAction.request.url;
        if (targetUri == null) return false;

        if (KiuqUrlPolicy.isDownloadUri(targetUri)) {
          await _handleAuthenticatedDownload(targetUri);
          return false;
        }

        if (KiuqUrlPolicy.isInternalUri(targetUri, baseUri: _appUri) ||
            KiuqUrlPolicy.isInAppOAuthUri(targetUri)) {
          await controller.loadUrl(urlRequest: createWindowAction.request);
          return false;
        }

        if (KiuqUrlPolicy.isSafeExternalScheme(targetUri)) {
          await _openSafeExternalUri(targetUri);
        }
        return false;
      },
      onProgressChanged: (controller, progress) {
        if (!mounted) return;
        setState(() {
          _loadingProgress = progress;
          if (progress >= 82 && !_hasLoadedFirstPage) {
            _hasLoadedFirstPage = true;
          }
        });
      },
      onLoadStart: (controller, url) {
        _startLoadWatchdog();
        if (!mounted) return;
        setState(() {
          _loadError = null;
          _loadingProgress = 10;
          if (url != null) {
            _shellState = _shellState.copyWith(currentUrl: url.toString());
          }
        });
      },
      onLoadStop: (controller, url) async {
        _loadWatchdogTimer?.cancel();
        _pullToRefreshController?.endRefreshing();
        final canBack = await controller.canGoBack();
        if (!mounted) return;
        setState(() {
          _loadingProgress = 100;
          _hasLoadedFirstPage = true;
          _isOffline = false;
          _loadError = null;
          _shellState = _shellState.copyWith(
            currentUrl: url?.toString() ?? _shellState.currentUrl,
            canGoBack: canBack,
          );
        });
        if (context.mounted) {
          await _syncWebSurfaceMetrics(context);
        }
      },
      onUpdateVisitedHistory: (controller, url, isReload) async {
        final canBack = await controller.canGoBack();
        if (!mounted) return;
        setState(() {
          _shellState = _shellState.copyWith(
            currentUrl: url?.toString() ?? _shellState.currentUrl,
            canGoBack: canBack,
          );
        });
        if (context.mounted) {
          await _syncWebSurfaceMetrics(context);
        }
      },
      onReceivedError: (controller, request, error) {
        if (request.isForMainFrame != true) return;
        final desc = error.description.toLowerCase();
        if (desc.contains('-999') ||
            desc.contains('cancelled') ||
            desc.contains('aborted') ||
            desc.contains('frame load interrupted')) {
          return;
        }
        _loadWatchdogTimer?.cancel();
        _pullToRefreshController?.endRefreshing();
        if (!mounted) return;
        setState(() {
          _loadingProgress = 100;
          _loadError = error.description;
          if (desc.contains('internet') ||
              desc.contains('offline') ||
              desc.contains('network') ||
              desc.contains('timed out') ||
              desc.contains('cannot find host')) {
            _isOffline = true;
          }
        });
      },
      onWebContentProcessDidTerminate: (controller) {
        // Recover gracefully if iOS reclaims WKWebView WebContent memory in background
        controller.reload();
      },
      shouldOverrideUrlLoading: (controller, navigationAction) async {
        final uri = navigationAction.request.url;
        if (uri == null) return NavigationActionPolicy.ALLOW;

        // Block unsafe schemes (`javascript:`, `file:`) on main-frame navigations
        final scheme = uri.scheme.toLowerCase();
        if (scheme == 'file' || scheme == 'javascript') {
          return NavigationActionPolicy.CANCEL;
        }

        if (KiuqUrlPolicy.isDownloadUri(uri)) {
          await _handleAuthenticatedDownload(uri);
          return NavigationActionPolicy.CANCEL;
        }

        if (KiuqUrlPolicy.isInternalUri(uri, baseUri: _appUri) ||
            KiuqUrlPolicy.isInAppOAuthUri(uri)) {
          return NavigationActionPolicy.ALLOW;
        }

        if (KiuqUrlPolicy.isSafeExternalScheme(uri)) {
          await _openSafeExternalUri(uri);
        }
        return NavigationActionPolicy.CANCEL;
      },
      onDownloadStartRequest: (controller, request) async {
        await _handleAuthenticatedDownload(
          request.url,
          suggestedFilename: request.suggestedFilename,
        );
      },
    );
  }
}
