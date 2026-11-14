import 'package:dgt_mobile_app/main.dart';
import 'package:dgt_mobile_app/src/config/app_config.dart';
import 'package:dgt_mobile_app/src/models/shell_state.dart';
import 'package:dgt_mobile_app/src/services/ios_native_bridge.dart';
import 'package:dgt_mobile_app/src/services/webrtc_call_service.dart';
import 'package:dgt_mobile_app/src/services/webview_bridge_scripts.dart';
import 'package:dgt_mobile_app/src/theme/liquid_glass_theme.dart';
import 'package:dgt_mobile_app/src/widgets/liquid_glass_bottom_nav.dart';
import 'package:dgt_mobile_app/src/widgets/liquid_glass_call_screen.dart';
import 'package:dgt_mobile_app/src/widgets/liquid_glass_hub_sheet.dart';
import 'package:dgt_mobile_app/src/widgets/liquid_glass_overlays.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues(<String, Object>{});
  });

  group('URL & Security Policy (KiuqUrlPolicy)', () {
    test('KIUQ mobile shell uses the verified HTTPS hosted app URL', () {
      expect(appBaseUrl, startsWith('https://'));
      expect(appBaseUrl, equals('https://lightcyan-weasel-711536.hostingersite.com'));
    });

    test('resolveAppBaseUrl enforces HTTPS on non-local domains and strips /api suffix', () {
      expect(
        resolveAppBaseUrl('http://lightcyan-weasel-711536.hostingersite.com/api/'),
        equals('https://lightcyan-weasel-711536.hostingersite.com'),
      );
      expect(
        resolveAppBaseUrl('http://localhost:8000/api'),
        equals('http://localhost:8000'),
      );
      expect(
        resolveAppBaseUrl('javascript:alert(1)'),
        equals(defaultProductionBaseUrl),
      );
    });

    test('validates internal, Google OAuth, external, and unsafe schemes accurately', () {
      final base = Uri.parse('https://lightcyan-weasel-711536.hostingersite.com');

      expect(
        KiuqUrlPolicy.isInternalUri(
          Uri.parse('https://lightcyan-weasel-711536.hostingersite.com/boards'),
          baseUri: base,
        ),
        isTrue,
      );
      expect(
        KiuqUrlPolicy.isInternalUri(
          Uri.parse('https://evil.example.com/boards'),
          baseUri: base,
        ),
        isFalse,
      );
      expect(
        KiuqUrlPolicy.isInAppOAuthUri(
          Uri.parse('https://accounts.google.com/o/oauth2/v2/auth?client_id=123'),
        ),
        isTrue,
      );
      expect(
        KiuqUrlPolicy.isSafeExternalScheme(Uri.parse('https://docs.google.com')),
        isTrue,
      );
      expect(
        KiuqUrlPolicy.isSafeExternalScheme(Uri.parse('file:///etc/passwd')),
        isFalse,
      );
      expect(
        KiuqUrlPolicy.isSafeExternalScheme(Uri.parse('javascript:void(0)')),
        isFalse,
      );
    });

    test('detects download/export routes and restorable authenticated URLs', () {
      expect(
        KiuqUrlPolicy.isDownloadUri(
          Uri.parse('https://lightcyan-weasel-711536.hostingersite.com/attachments/42/download'),
        ),
        isTrue,
      );
      expect(
        KiuqUrlPolicy.isDownloadUri(
          Uri.parse('https://lightcyan-weasel-711536.hostingersite.com/kpi/staff/pdf'),
        ),
        isTrue,
      );
      expect(
        KiuqUrlPolicy.isQuickLookPreviewable('monthly-report.pdf'),
        isTrue,
      );
      expect(
        KiuqUrlPolicy.isRestorableAppUrl(
          'https://lightcyan-weasel-711536.hostingersite.com/boards/12',
        ),
        isTrue,
      );
      expect(
        KiuqUrlPolicy.isRestorableAppUrl(
          'https://lightcyan-weasel-711536.hostingersite.com/login',
        ),
        isFalse,
      );
      expect(
        KiuqUrlPolicy.isRestorableAppUrl(
          'https://lightcyan-weasel-711536.hostingersite.com/attachments/42/download',
        ),
        isFalse,
      );
    });
  });

  group('Liquid Glass Theme & WebView Bridge Scripts', () {
    test('provides distinct status bar & glass tokens for Light, Dark, and Neon modes', () {
      final light = LiquidGlassPalette.of(KiuqThemeMode.light);
      final dark = LiquidGlassPalette.of(KiuqThemeMode.dark);
      final neon = LiquidGlassPalette.of(KiuqThemeMode.neon);

      expect(light.systemOverlayStyle.statusBarIconBrightness, Brightness.dark);
      expect(dark.systemOverlayStyle.statusBarIconBrightness, Brightness.light);
      expect(neon.systemOverlayStyle.statusBarIconBrightness, Brightness.light);
      expect(neon.primaryAccent, const Color(0xFF00F0FF));
    });

    test('bootstrap script scopes duplicate nav suppression cleanly without touching guest pages', () {
      final script = WebViewBridgeScripts.buildBootstrapScript(
        safeTopPx: 59,
        safeBottomPx: 34,
        safeLeftPx: 0,
        safeRightPx: 0,
        bottomDockClearancePx: 96,
        isIpadLayout: false,
      );

      expect(script, contains('html.dgt-flutter-nav-managed #mobile-bottom-nav'));
      expect(script, contains('DgtShellState'));
      expect(script, contains('DgtScrollDirection'));
    });
  });

  group('Apple Music iOS 26 Floating Liquid Glass Bottom Navigation', () {
    testWidgets('renders cleanly across compact iPhone SE (320pt) and Pro Max without overflow', (
      tester,
    ) async {
      KiuqNavDestination? selected;
      var hubOpened = false;

      final tabs = KiuqDefaultNavigation.primaryTabsForPath('/dashboard')
          .map(
            (t) => t.id == 'chat'
                ? t.copyWith(badgeCount: 5)
                : (t.id == 'tasks' ? t.copyWith(badgeCount: 12) : t),
          )
          .toList();

      // Test compact iPhone SE 1st-gen width (320x568)
      tester.view.physicalSize = const Size(320, 568);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.reset);

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Stack(
              children: <Widget>[
                Positioned(
                  left: 0,
                  right: 0,
                  bottom: 0,
                  child: LiquidGlassBottomNav(
                    tabs: tabs,
                    palette: LiquidGlassPalette.of(KiuqThemeMode.light),
                    hubBadgeCount: 3,
                    onSelectTab: (tab) => selected = tab,
                    onOpenHub: () => hubOpened = true,
                  ),
                ),
              ],
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.byKey(const ValueKey<String>('kiuq_liquid_glass_capsule')), findsOneWidget);
      expect(find.byKey(const ValueKey<String>('kiuq_liquid_glass_hub_orb')), findsOneWidget);
      expect(find.text('Home'), findsOneWidget);
      expect(find.text('12'), findsOneWidget);
      expect(find.text('3'), findsOneWidget);

      await tester.tap(find.text('Boards'));
      await tester.pumpAndSettle();
      expect(selected?.url, equals('/boards'));

      await tester.tap(find.byKey(const ValueKey<String>('kiuq_liquid_glass_hub_orb')));
      await tester.pumpAndSettle();
      expect(hubOpened, isTrue);
    });

    testWidgets('subtly minimizes on scroll down and expands on tap', (tester) async {
      var expandedRequested = false;
      final tabs = KiuqDefaultNavigation.primaryTabsForPath('/boards').take(4).toList();

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Align(
              alignment: Alignment.bottomCenter,
              child: LiquidGlassBottomNav(
                tabs: tabs,
                palette: LiquidGlassPalette.of(KiuqThemeMode.dark),
                isMinimized: true,
                onExpandRequested: () => expandedRequested = true,
                onSelectTab: (_) {},
                onOpenHub: () {},
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      final capsuleBox = tester.renderObject<RenderBox>(
        find.byKey(const ValueKey<String>('kiuq_liquid_glass_capsule')),
      );
      expect(capsuleBox.size.height, equals(46.0));

      // Icons remain accessible when minimized; tapping requests expansion
      await tester.tap(find.byIcon(CupertinoIcons.house));
      await tester.pumpAndSettle();
      expect(expandedRequested, isTrue);
    });
  });

  group('Responsive iPhone vs iPad Adaptive Layout', () {
    final authedState = KiuqShellState(
      isAuthenticated: true,
      currentUrl: 'https://lightcyan-weasel-711536.hostingersite.com/dashboard',
      pageTitle: 'Dashboard',
      themeMode: KiuqThemeMode.light,
      unreadNotifications: 4,
      primaryTabs: KiuqDefaultNavigation.primaryTabsForPath('/dashboard'),
      hubDestinations: KiuqDefaultNavigation.defaultHubDestinations('/dashboard'),
      user: const KiuqUserProfile(name: 'Rafel Admin', role: 'Administrator'),
    );

    testWidgets('iPhone viewport shows floating bottom capsule and hides iPad sidebar', (
      tester,
    ) async {
      tester.view.physicalSize = const Size(393, 852);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.reset);

      await tester.pumpWidget(
        DgtMobileApp(
          enableWebView: false,
          initialShellState: authedState,
        ),
      );
      await tester.pump(const Duration(milliseconds: 350));
      await tester.pumpAndSettle();

      expect(find.byKey(const ValueKey<String>('kiuq_liquid_glass_capsule')), findsOneWidget);
      expect(find.byKey(const ValueKey<String>('kiuq_liquid_glass_ipad_sidebar')), findsNothing);
    });

    testWidgets('iPad Pro viewport shows adaptive Liquid Glass sidebar and hides phone bottom bar', (
      tester,
    ) async {
      tester.view.physicalSize = const Size(1024, 1366);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.reset);

      await tester.pumpWidget(
        DgtMobileApp(
          enableWebView: false,
          initialShellState: authedState,
        ),
      );
      await tester.pump(const Duration(milliseconds: 350));
      await tester.pumpAndSettle();

      expect(find.byKey(const ValueKey<String>('kiuq_liquid_glass_ipad_sidebar')), findsOneWidget);
      expect(find.byKey(const ValueKey<String>('kiuq_liquid_glass_capsule')), findsNothing);
      expect(find.text('Rafel Admin'), findsOneWidget);
      expect(find.text('Google Search Console'), findsOneWidget);
    });

    testWidgets('unauthenticated login screen hides navigation chrome cleanly', (
      tester,
    ) async {
      tester.view.physicalSize = const Size(393, 852);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.reset);

      await tester.pumpWidget(
        const DgtMobileApp(
          enableWebView: false,
          initialShellState: KiuqShellState(
            isAuthenticated: false,
            currentUrl: 'https://lightcyan-weasel-711536.hostingersite.com/login',
            pageTitle: 'Sign In',
          ),
        ),
      );
      await tester.pump(const Duration(milliseconds: 350));
      await tester.pumpAndSettle();

      expect(find.byKey(const ValueKey<String>('kiuq_liquid_glass_capsule')), findsNothing);
      expect(find.byKey(const ValueKey<String>('kiuq_liquid_glass_ipad_sidebar')), findsNothing);
    });
  });

  group('App Hub Spotlight Search & Offline Recovery UI', () {
    testWidgets('Hub sheet filters KIUQ modules in real time and switches themes', (
      tester,
    ) async {
      KiuqNavDestination? chosen;
      KiuqThemeMode? switchedTheme;

      final state = KiuqShellState(
        isAuthenticated: true,
        currentUrl: 'https://lightcyan-weasel-711536.hostingersite.com/dashboard',
        primaryTabs: KiuqDefaultNavigation.primaryTabsForPath('/dashboard'),
        hubDestinations: KiuqDefaultNavigation.defaultHubDestinations('/dashboard'),
        user: const KiuqUserProfile(name: 'DGT Lead', role: 'Supervisor'),
      );

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: LiquidGlassHubSheet(
              palette: LiquidGlassPalette.of(KiuqThemeMode.light),
              shellState: state,
              onSelectDestination: (d) => chosen = d,
              onThemeChanged: (m) => switchedTheme = m,
              onShareCurrentPage: () {},
              onReloadPage: () {},
              onOpenAppSettings: () {},
              onOpenNotifications: () {},
              onSignOut: () {},
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      await tester.enterText(
        find.byKey(const ValueKey<String>('kiuq_hub_search_input')),
        'Search Console',
      );
      await tester.pumpAndSettle();

      expect(find.text('Google Search Console'), findsOneWidget);
      await tester.tap(find.text('Google Search Console'));
      await tester.pumpAndSettle();
      expect(chosen?.url, equals('/websites/google-search-console'));

      await tester.tap(find.text('Neon'));
      await tester.pumpAndSettle();
      expect(switchedTheme, equals(KiuqThemeMode.neon));
    });

    testWidgets('Offline recovery screen & mid-session banner invoke retry cleanly', (
      tester,
    ) async {
      var retried = 0;

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Stack(
              children: <Widget>[
                LiquidGlassOfflineScreen(
                  palette: LiquidGlassPalette.of(KiuqThemeMode.dark),
                  serverUrl: appBaseUrl,
                  errorMessage: 'The Internet connection appears to be offline.',
                  isRetrying: false,
                  onRetry: () => retried++,
                ),
                LiquidGlassOfflineBanner(
                  palette: LiquidGlassPalette.of(KiuqThemeMode.dark),
                  isVisible: true,
                  isRetrying: false,
                  onRetry: () => retried++,
                ),
              ],
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('You’re Offline'), findsOneWidget);
      expect(find.text('Connection Unavailable'), findsOneWidget);

      await tester.tap(find.text('Retry Connection'));
      await tester.pump();
      await tester.tap(find.text('Retry'));
      await tester.pump();
      expect(retried, equals(2));
    });
  });

  group('Native WebRTC Voice & Video Call Liquid Glass Overlay', () {
    testWidgets('renders Incoming Video Call screen with Audio Only, Video, and Decline actions', (
      tester,
    ) async {
      final bridge = IosNativeBridge();
      final callController = WebRtcCallController(
        nativeBridge: bridge,
        enableNativeWebRtc: false,
      );
      addTearDown(callController.dispose);

      callController.debugPreviewCallState(
        phase: KiuqCallPhase.incomingRinging,
        contactName: 'Dara Sok',
        isVideo: true,
        isCaller: false,
      );

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Stack(
              children: <Widget>[
                LiquidGlassCallOverlay(
                  controller: callController,
                  palette: LiquidGlassPalette.of(KiuqThemeMode.dark),
                ),
              ],
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(
        find.byKey(const ValueKey<String>('kiuq_native_incoming_call_screen')),
        findsOneWidget,
      );
      expect(find.text('Incoming Video Call'), findsOneWidget);
      expect(find.text('Dara Sok'), findsOneWidget);
      expect(
        find.byKey(const ValueKey<String>('kiuq_call_decline_btn')),
        findsOneWidget,
      );
      expect(
        find.byKey(const ValueKey<String>('kiuq_call_accept_voice_btn')),
        findsOneWidget,
      );
      expect(
        find.byKey(const ValueKey<String>('kiuq_call_accept_video_btn')),
        findsOneWidget,
      );
    });

    testWidgets('switches smoothly between Active Call screen and Minimized Floating Call Pill', (
      tester,
    ) async {
      final bridge = IosNativeBridge();
      final callController = WebRtcCallController(
        nativeBridge: bridge,
        enableNativeWebRtc: false,
      );
      addTearDown(callController.dispose);

      callController.debugPreviewCallState(
        phase: KiuqCallPhase.connected,
        contactName: 'Sopora Admin',
        isVideo: false,
        isCaller: true,
        durationSeconds: 125,
      );

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Stack(
              children: <Widget>[
                LiquidGlassCallOverlay(
                  controller: callController,
                  palette: LiquidGlassPalette.of(KiuqThemeMode.dark),
                ),
              ],
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(
        find.byKey(const ValueKey<String>('kiuq_native_active_call_screen')),
        findsOneWidget,
      );
      expect(find.text('02:05'), findsWidgets);
      expect(
        find.byKey(const ValueKey<String>('kiuq_call_mute_btn')),
        findsOneWidget,
      );
      expect(
        find.byKey(const ValueKey<String>('kiuq_call_end_btn')),
        findsOneWidget,
      );

      // Minimize to floating Liquid Glass pill
      await tester.tap(
        find.byKey(const ValueKey<String>('kiuq_call_minimize_btn')),
      );
      await tester.pumpAndSettle();

      expect(
        find.byKey(const ValueKey<String>('kiuq_native_minimized_call_pill')),
        findsOneWidget,
      );
      expect(find.text('Sopora Admin'), findsOneWidget);

      // Tap floating pill to restore full call screen
      await tester.tap(find.text('Sopora Admin'));
      await tester.pumpAndSettle();

      expect(
        find.byKey(const ValueKey<String>('kiuq_native_active_call_screen')),
        findsOneWidget,
      );
    });
  });
}
