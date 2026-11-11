import 'dart:math' as math;
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:flutter_inappwebview/flutter_inappwebview.dart';

// ─── App URL Configuration ───────────────────────────────────────────────────
const configuredAppBaseUrl = String.fromEnvironment(
  'APP_BASE_URL',
  defaultValue: 'https://lightcyan-weasel-711536.hostingersite.com',
);

final String appBaseUrl = _resolveAppBaseUrl();

String _resolveAppBaseUrl() {
  final rawUrl = configuredAppBaseUrl;
  if (rawUrl.isEmpty) {
    return 'https://lightcyan-weasel-711536.hostingersite.com';
  }
  final uri = Uri.tryParse(rawUrl);
  if (uri == null || !uri.hasScheme) {
    return 'https://lightcyan-weasel-711536.hostingersite.com';
  }
  final normalizedPath = uri.path.replaceFirst(RegExp(r'/api/?$'), '');
  final appUri = uri.replace(path: normalizedPath, query: '', fragment: '');
  return appUri.toString().replaceFirst(RegExp(r'/$'), '');
}

// ─── Entry Point ─────────────────────────────────────────────────────────────
void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setSystemUIOverlayStyle(
    const SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      statusBarIconBrightness: Brightness.dark,
      statusBarBrightness: Brightness.light,
      systemNavigationBarColor: Colors.transparent,
      systemNavigationBarIconBrightness: Brightness.dark,
      systemNavigationBarDividerColor: Colors.transparent,
    ),
  );
  SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
  runApp(const DgtMobileApp());
}

// ─── App Root ─────────────────────────────────────────────────────────────────
class DgtMobileApp extends StatelessWidget {
  const DgtMobileApp({super.key});

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

// ─── Main Shell ──────────────────────────────────────────────────────────────
class DgtWebsiteShell extends StatefulWidget {
  const DgtWebsiteShell({super.key});

  @override
  State<DgtWebsiteShell> createState() => _DgtWebsiteShellState();
}

class _DgtWebsiteShellState extends State<DgtWebsiteShell>
    with WidgetsBindingObserver {
  InAppWebViewController? controller;
  PullToRefreshController? pullToRefreshController;
  int loadingProgress = 0;
  bool hasLoadedFirstPage = false;
  bool splashScreenDone = false;
  String? loadError;

  Uri get appUri => Uri.parse(appBaseUrl);

  bool _isDownloadUrl(Uri uri) {
    final path = uri.path.toLowerCase();
    final segments = uri.pathSegments.map((segment) => segment.toLowerCase());

    if (segments.contains('download') ||
        path.endsWith('/template') ||
        path.endsWith('/template-xlsx')) {
      return true;
    }

    return const <String>{
      '.csv',
      '.doc',
      '.docx',
      '.dmg',
      '.heic',
      '.ipa',
      '.jpg',
      '.jpeg',
      '.numbers',
      '.pdf',
      '.png',
      '.ppt',
      '.pptx',
      '.rar',
      '.webp',
      '.xls',
      '.xlsx',
      '.zip',
    }.any(path.endsWith);
  }

  Future<void> _openExternalUrl(Uri uri) async {
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  bool _isInternalUrl(Uri? uri) {
    if (uri == null) return true;
    if (uri.scheme == 'about' || uri.scheme == 'data') return true;
    return uri.host == appUri.host;
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);

    pullToRefreshController = kIsWeb
        ? null
        : PullToRefreshController(
            settings: PullToRefreshSettings(
              color: const Color(0xFF2F68ED),
            ),
            onRefresh: () async {
              if (defaultTargetPlatform == TargetPlatform.android) {
                await controller?.reload();
              } else if (defaultTargetPlatform == TargetPlatform.iOS) {
                await controller?.reload();
              }
            },
          );

    Future.delayed(const Duration(milliseconds: 250), () {
      if (mounted) {
        setState(() => splashScreenDone = true);
      }
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  Future<void> _reload() async {
    setState(() => loadError = null);
    await controller?.reload();
  }

  Future<void> _prepareAppSurface({double topInset = 56.0, double bottomInset = 28.0}) async {
    final isIos = defaultTargetPlatform == TargetPlatform.iOS;
    final safeTopPx = isIos ? math.max(topInset, 56.0).round() : math.max(topInset, 24.0).round();
    final safeBottomPx = isIos ? math.max(bottomInset, 28.0).round() : math.max(bottomInset, 12.0).round();

    await controller?.evaluateJavascript(
      source: '''
      (() => {
        const applySafeMetrics = () => {
          const root = document.documentElement;
          root.classList.add('dgt-mobile-app');
          if (navigator.userAgent.includes('Android') || navigator.userAgent.includes('DGTSystemAndroidApp')) {
            root.classList.add('dgt-android-app');
          } else {
            root.classList.add('dgt-ios-app');
          }
          root.style.setProperty('--dgt-safe-top', '${safeTopPx}px');
          root.style.setProperty('--dgt-safe-bottom', '${safeBottomPx}px');
        };

        applySafeMetrics();

        if (!document.getElementById('dgt-ios-native-app-styles')) {
          const style = document.createElement('style');
          style.id = 'dgt-ios-native-app-styles';
          style.innerHTML = `
            html.dgt-ios-app {
              --dgt-safe-top: ${safeTopPx}px !important;
              --dgt-safe-bottom: ${safeBottomPx}px !important;
              -webkit-user-select: none;
              -webkit-touch-callout: none;
              -webkit-tap-highlight-color: transparent !important;
              touch-action: manipulation;
            }
            html.dgt-ios-app input, 
            html.dgt-ios-app textarea, 
            html.dgt-ios-app [contenteditable="true"] {
              -webkit-user-select: text !important;
              user-select: text !important;
              font-size: 16px !important;
            }
            html.dgt-ios-app .page-content,
            html.dgt-ios-app .custom-scrollbar {
              -webkit-overflow-scrolling: touch !important;
            }
          `;
          document.head.appendChild(style);
        }

        if (window.__dgtMobileReady) return;
        window.__dgtMobileReady = true;

        document.addEventListener('turbo:load', applySafeMetrics);
        document.addEventListener('turbo:render', applySafeMetrics);

        const blurAccidentalColorPicker = () => {
          const active = document.activeElement;
          if (active && active.matches && active.matches('input[type="color"]')) {
            active.blur();
          }
        };

        window.addEventListener('pageshow', blurAccidentalColorPicker, true);
        window.addEventListener('load', blurAccidentalColorPicker, true);
        window.setTimeout(blurAccidentalColorPicker, 80);
      })();
    ''',
    );
  }

  @override
  Widget build(BuildContext context) {
    final viewPadding = MediaQuery.viewPaddingOf(context);
    final mediaPadding = MediaQuery.paddingOf(context);
    final topSafe = math.max(viewPadding.top, mediaPadding.top);
    final bottomSafe = math.max(viewPadding.bottom, mediaPadding.bottom);

    return Scaffold(
      backgroundColor: const Color(0xFFF4F7FB),
      body: Stack(
        children: [
          // ── WebView ──────────────────────────────────────────────
          Positioned.fill(
            child: InAppWebView(
              initialUrlRequest: URLRequest(url: WebUri(appBaseUrl)),
              initialSettings: InAppWebViewSettings(
                userAgent: defaultTargetPlatform == TargetPlatform.android
                    ? 'DGTSystemAndroidApp/1.0'
                    : 'DGTSystemiOSApp/1.0',
                javaScriptEnabled: true,
                transparentBackground: false,
                allowFileAccessFromFileURLs: true,
                allowUniversalAccessFromFileURLs: true,
                mediaPlaybackRequiresUserGesture: false,
                allowsInlineMediaPlayback: true,
                allowsAirPlayForMediaPlayback: true,
                disableContextMenu: true,
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
              pullToRefreshController: pullToRefreshController,
              onWebViewCreated: (webViewController) {
                controller = webViewController;
              },
              onPermissionRequest: (controller, permissionRequest) async {
                return PermissionResponse(
                  resources: permissionRequest.resources,
                  action: PermissionResponseAction.GRANT,
                );
              },
              onProgressChanged: (controller, progress) {
                if (!hasLoadedFirstPage) {
                  setState(() {
                    loadingProgress = progress;
                    if (progress >= 80) {
                      hasLoadedFirstPage = true;
                    }
                  });
                }
              },
              onLoadStart: (controller, url) {
                setState(() {
                  loadError = null;
                  if (!hasLoadedFirstPage) loadingProgress = 0;
                });
              },
              onLoadStop: (controller, url) async {
                pullToRefreshController?.endRefreshing();
                setState(() {
                  loadingProgress = 100;
                  hasLoadedFirstPage = true;
                });
                await _prepareAppSurface(
                  topInset: topSafe,
                  bottomInset: bottomSafe,
                );
              },
              onReceivedError: (controller, request, error) {
                if (request.isForMainFrame ?? false) {
                  final desc = error.description.toLowerCase();
                  if (desc.contains('-999') ||
                      desc.contains('cancelled') ||
                      desc.contains('aborted')) {
                    return;
                  }
                  setState(() => loadError = error.description);
                }
              },
              shouldOverrideUrlLoading: (controller, navigationAction) async {
                final uri = navigationAction.request.url;
                if (uri != null && _isDownloadUrl(uri)) {
                  await _openExternalUrl(uri);
                  return NavigationActionPolicy.CANCEL;
                }
                if (_isInternalUrl(uri)) {
                  return NavigationActionPolicy.ALLOW;
                }
                if (uri != null) {
                  await _openExternalUrl(uri);
                }
                return NavigationActionPolicy.CANCEL;
              },
              onDownloadStartRequest: (controller, request) async {
                await _openExternalUrl(request.url);
              },
            ),
          ),

          // ── Loading Bar Removed for Instant Feel ─────────────────────────

          // ── Splash Screen Overlay ────────────────────────────────────────
          if (!hasLoadedFirstPage || !splashScreenDone)
            Container(
              color: const Color(0xFF2F68ED),
              alignment: Alignment.center,
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Container(
                    width: 90,
                    height: 90,
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(22),
                      boxShadow: const [
                        BoxShadow(
                          color: Colors.black12,
                          blurRadius: 20,
                          offset: Offset(0, 8),
                        ),
                      ],
                    ),
                    alignment: Alignment.center,
                    child: const Text(
                      'KQ',
                      style: TextStyle(
                        color: Color(0xFF2F68ED),
                        fontWeight: FontWeight.w900,
                        fontSize: 36,
                      ),
                    ),
                  ),
                  const SizedBox(height: 24),
                  const Text(
                    'KIUQ SYSTEM',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 24,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 1.2,
                    ),
                  ),
                ],
              ),
            ),

          // ── Error Panel ──────────────────────────────────────────
          if (loadError != null)
            _LoadErrorPanel(
              message: loadError!,
              url: appBaseUrl,
              onRetry: _reload,
            ),
        ],
      ),
    );
  }
}

// ─── Error Panel ─────────────────────────────────────────────────────────────
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
                fontSize: 22,
              ),
            ),
          ),
          const SizedBox(height: 18),
          const Text(
            'Cannot open KIUQ SYSTEM',
            style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 8),
          Text(
            url,
            style: const TextStyle(color: Color(0xFF64748B), fontSize: 13),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 12),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Color(0xFFDC2626), fontSize: 13),
          ),
          const SizedBox(height: 22),
          FilledButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Try Again'),
          ),
        ],
      ),
    );
  }
}
