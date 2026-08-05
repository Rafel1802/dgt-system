import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:flutter_inappwebview/flutter_inappwebview.dart';

// ─── App URL Configuration ───────────────────────────────────────────────────
const configuredAppBaseUrl = String.fromEnvironment(
  'APP_BASE_URL',
  defaultValue: 'https://rosybrown-baboon-228003.hostingersite.com',
);

final String appBaseUrl = _resolveAppBaseUrl();

String _resolveAppBaseUrl() {
  final rawUrl = configuredAppBaseUrl;
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

// ─── Entry Point ─────────────────────────────────────────────────────────────
void main() async {
  WidgetsFlutterBinding.ensureInitialized();
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
    Future.delayed(const Duration(seconds: 2), () {
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

  Future<void> _prepareAppSurface() async {
    await controller?.evaluateJavascript(
      source: '''
      (() => {
        if (window.__dgtMobileReady) return;
        window.__dgtMobileReady = true;
        document.documentElement.classList.add('dgt-mobile-app');

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
    return Scaffold(
      backgroundColor: const Color(0xFFF4F7FB),
      body: Stack(
        children: [
          // ── WebView ──────────────────────────────────────────────
          Positioned.fill(
            child: InAppWebView(
              initialUrlRequest: URLRequest(url: WebUri(appBaseUrl)),
              initialSettings: InAppWebViewSettings(
                userAgent: 'DGTSystemiOSApp/1.0',
                javaScriptEnabled: true,
                transparentBackground: false,
                allowFileAccessFromFileURLs: true,
                allowUniversalAccessFromFileURLs: true,
                mediaPlaybackRequiresUserGesture: false,
                disableContextMenu: true,
                verticalScrollBarEnabled: false,
                horizontalScrollBarEnabled: false,
                allowsBackForwardNavigationGestures: true,
                useShouldOverrideUrlLoading: true,
                useOnDownloadStart: true,
                cacheEnabled: true,
              ),
              onWebViewCreated: (webViewController) {
                controller = webViewController;
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
                setState(() {
                  loadingProgress = 100;
                  hasLoadedFirstPage = true;
                });
                await _prepareAppSurface();
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
