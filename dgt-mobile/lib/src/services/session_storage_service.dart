import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter_inappwebview/flutter_inappwebview.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../config/app_config.dart';
import '../models/shell_state.dart';

/// Persisted user preferences and safe cache/session coordinator for KIUQ SYSTEM.
///
/// Security notes:
/// - Never stores plain-text credentials or tokens; authentication relies on
///   HttpOnly / SameSite session cookies managed natively by `WKHTTPCookieStore`.
/// - Provides non-destructive web cache clearing that preserves active login
///   cookies separately from full session reset.
@immutable
class KiuqAppPreferences {
  const KiuqAppPreferences({
    this.themeMode = KiuqThemeMode.light,
    this.lastRestorableUrl,
    this.scrollMinimizeEnabled = true,
    this.hapticsEnabled = true,
    this.ipadSidebarExpanded = true,
    this.notificationsEnabled = true,
    this.notificationPromptShown = false,
  });

  final KiuqThemeMode themeMode;
  final String? lastRestorableUrl;
  final bool scrollMinimizeEnabled;
  final bool hapticsEnabled;
  final bool ipadSidebarExpanded;
  final bool notificationsEnabled;
  final bool notificationPromptShown;

  KiuqAppPreferences copyWith({
    KiuqThemeMode? themeMode,
    String? lastRestorableUrl,
    bool clearLastUrl = false,
    bool? scrollMinimizeEnabled,
    bool? hapticsEnabled,
    bool? ipadSidebarExpanded,
    bool? notificationsEnabled,
    bool? notificationPromptShown,
  }) {
    return KiuqAppPreferences(
      themeMode: themeMode ?? this.themeMode,
      lastRestorableUrl: clearLastUrl
          ? null
          : (lastRestorableUrl ?? this.lastRestorableUrl),
      scrollMinimizeEnabled:
          scrollMinimizeEnabled ?? this.scrollMinimizeEnabled,
      hapticsEnabled: hapticsEnabled ?? this.hapticsEnabled,
      ipadSidebarExpanded: ipadSidebarExpanded ?? this.ipadSidebarExpanded,
      notificationsEnabled: notificationsEnabled ?? this.notificationsEnabled,
      notificationPromptShown:
          notificationPromptShown ?? this.notificationPromptShown,
    );
  }
}

class SessionStorageService {
  static const String _keyThemeMode = 'kiuq.pref.theme_mode';
  static const String _keyLastUrl = 'kiuq.session.last_restorable_url';
  static const String _keyScrollMinimize = 'kiuq.pref.scroll_minimize';
  static const String _keyHaptics = 'kiuq.pref.haptics';
  static const String _keyIpadSidebarExpanded = 'kiuq.pref.ipad_sidebar_expanded';
  static const String _keyNotificationsEnabled = 'kiuq.pref.notifications_enabled';
  static const String _keyNotificationPromptShown =
      'kiuq.pref.notification_prompt_shown';
  static const String _keyCachedShellSnapshot =
      'kiuq.cache.shell_snapshot_v1';

  SharedPreferences? _prefs;
  final Set<String> _sessionGrantedMediaOrigins = <String>{};

  Future<SharedPreferences> _instance() async {
    return _prefs ??= await SharedPreferences.getInstance();
  }

  Future<KiuqAppPreferences> loadPreferences() async {
    final prefs = await _instance();
    final savedUrl = prefs.getString(_keyLastUrl);
    final validUrl = KiuqUrlPolicy.isRestorableAppUrl(savedUrl) ? savedUrl : null;

    return KiuqAppPreferences(
      themeMode: KiuqThemeMode.fromString(prefs.getString(_keyThemeMode)),
      lastRestorableUrl: validUrl,
      scrollMinimizeEnabled: prefs.getBool(_keyScrollMinimize) ?? true,
      hapticsEnabled: prefs.getBool(_keyHaptics) ?? true,
      ipadSidebarExpanded: prefs.getBool(_keyIpadSidebarExpanded) ?? true,
      notificationsEnabled: prefs.getBool(_keyNotificationsEnabled) ?? true,
      notificationPromptShown:
          prefs.getBool(_keyNotificationPromptShown) ?? false,
    );
  }

  Future<void> saveThemeMode(KiuqThemeMode mode) async {
    final prefs = await _instance();
    await prefs.setString(_keyThemeMode, mode.wireValue);
  }

  Future<void> saveLastRestorableUrl(String? url) async {
    final prefs = await _instance();
    if (url == null || !KiuqUrlPolicy.isRestorableAppUrl(url)) {
      return;
    }
    await prefs.setString(_keyLastUrl, url);
  }

  Future<void> clearLastRestorableUrl() async {
    final prefs = await _instance();
    await prefs.remove(_keyLastUrl);
  }

  Future<void> saveScrollMinimize(bool enabled) async {
    final prefs = await _instance();
    await prefs.setBool(_keyScrollMinimize, enabled);
  }

  Future<void> saveHaptics(bool enabled) async {
    final prefs = await _instance();
    await prefs.setBool(_keyHaptics, enabled);
  }

  Future<void> saveIpadSidebarExpanded(bool expanded) async {
    final prefs = await _instance();
    await prefs.setBool(_keyIpadSidebarExpanded, expanded);
  }

  Future<void> saveNotificationsEnabled(bool enabled) async {
    final prefs = await _instance();
    await prefs.setBool(_keyNotificationsEnabled, enabled);
  }

  Future<void> markNotificationPromptShown() async {
    final prefs = await _instance();
    await prefs.setBool(_keyNotificationPromptShown, true);
  }

  /// Saves a lightweight JSON snapshot of the authenticated shell state in device
  /// storage (`SharedPreferences`) so cold starts hydrate navigation tabs,
  /// user profile, and badge counts in 0ms.
  Future<void> saveShellSnapshot(Map<String, dynamic> rawState) async {
    try {
      final prefs = await _instance();
      await prefs.setString(_keyCachedShellSnapshot, jsonEncode(rawState));
    } catch (_) {}
  }

  /// Loads the cached shell snapshot from device storage.
  Future<Map<String, dynamic>?> loadShellSnapshot() async {
    try {
      final prefs = await _instance();
      final encoded = prefs.getString(_keyCachedShellSnapshot);
      if (encoded == null || encoded.isEmpty) return null;
      final decoded = jsonDecode(encoded);
      if (decoded is Map) {
        return Map<String, dynamic>.from(decoded);
      }
    } catch (_) {}
    return null;
  }

  Future<void> clearShellSnapshot() async {
    try {
      final prefs = await _instance();
      await prefs.remove(_keyCachedShellSnapshot);
    } catch (_) {}
  }

  bool hasSessionMediaConsent(String originAndResourceKey) {
    return _sessionGrantedMediaOrigins.contains(originAndResourceKey);
  }

  void rememberSessionMediaConsent(String originAndResourceKey) {
    _sessionGrantedMediaOrigins.add(originAndResourceKey);
  }

  /// Ensures `WKHTTPCookieStore` flushes session cookies when entering background.
  Future<void> flushSessionCookies() async {
    if (kIsWeb) return;
    try {
      await CookieManager.instance().getCookies(url: WebUri(appBaseUrl));
    } catch (_) {
      // Ignore on unsupported test platforms.
    }
  }

  /// Clears cached web assets (images, scripts, styles, temp downloads) while
  /// preserving authentication cookies, LocalStorage, and IndexedDB.
  Future<void> clearWebCachePreservingSession() async {
    if (!kIsWeb) {
      try {
        await InAppWebViewController.clearAllCache(includeDiskFiles: true);
      } catch (_) {}
      try {
        final tempDir = Directory('${Directory.systemTemp.path}/kiuq_downloads');
        if (await tempDir.exists()) {
          await tempDir.delete(recursive: true);
        }
      } catch (_) {}
    }
  }

  /// Clears all WebView caches, web storage records, temp files, and cookies.
  Future<void> performFullSessionReset() async {
    _sessionGrantedMediaOrigins.clear();
    await clearLastRestorableUrl();
    await clearShellSnapshot();
    await clearWebCachePreservingSession();
    if (!kIsWeb) {
      try {
        await CookieManager.instance().deleteAllCookies();
      } catch (_) {}
      try {
        if (defaultTargetPlatform == TargetPlatform.iOS) {
          final storage = WebStorageManager.instance();
          final records = await storage.fetchDataRecords(
            dataTypes: WebsiteDataType.ALL,
          );
          await storage.removeDataFor(
            dataTypes: WebsiteDataType.ALL,
            dataRecords: records,
          );
        }
      } catch (_) {}
    }
  }
}
