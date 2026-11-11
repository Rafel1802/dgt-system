/// Core URL, security, and routing configuration for the KIUQ SYSTEM mobile app.
///
/// Preserves the verified production host while supporting build-time overrides
/// via `--dart-define=APP_BASE_URL=...`.
library;

const String defaultProductionBaseUrl =
    'https://lightcyan-weasel-711536.hostingersite.com';

const String configuredAppBaseUrl = String.fromEnvironment(
  'APP_BASE_URL',
  defaultValue: defaultProductionBaseUrl,
);

/// Resolves and normalizes the KIUQ SYSTEM base URL.
String resolveAppBaseUrl([String rawUrl = configuredAppBaseUrl]) {
  final trimmed = rawUrl.trim();
  if (trimmed.isEmpty) {
    return defaultProductionBaseUrl;
  }

  final uri = Uri.tryParse(trimmed);
  if (uri == null || !uri.hasScheme || uri.host.isEmpty) {
    return defaultProductionBaseUrl;
  }

  if (uri.scheme != 'https' && uri.scheme != 'http') {
    return defaultProductionBaseUrl;
  }

  // Enforce HTTPS for non-local hosts to protect session cookies and tokens.
  final isLocalHost =
      uri.host == 'localhost' ||
      uri.host == '127.0.0.1' ||
      uri.host.endsWith('.local');
  final effectiveScheme = (!isLocalHost && uri.scheme == 'http')
      ? 'https'
      : uri.scheme;

  final normalizedPath = uri.path.replaceFirst(RegExp(r'/api/?$'), '');
  final appUri = Uri(
    scheme: effectiveScheme,
    userInfo: uri.userInfo.isNotEmpty ? uri.userInfo : null,
    host: uri.host,
    port: uri.hasPort ? uri.port : null,
    path: normalizedPath.isEmpty ? null : normalizedPath,
  );

  return appUri.toString().replaceFirst(RegExp(r'/$'), '');
}

/// Global resolved base URL (exported by `main.dart` for backwards compatibility).
final String appBaseUrl = resolveAppBaseUrl();

/// Helper utilities for inspecting and validating navigation targets.
class KiuqUrlPolicy {
  const KiuqUrlPolicy._();

  static const Set<String> _downloadExtensions = <String>{
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
  };

  static const Set<String> _previewableExtensions = <String>{
    '.pdf',
    '.csv',
    '.doc',
    '.docx',
    '.xls',
    '.xlsx',
    '.ppt',
    '.pptx',
    '.png',
    '.jpg',
    '.jpeg',
    '.heic',
    '.webp',
  };

  /// Returns true if [uri] belongs to the configured KIUQ SYSTEM origin or safe inline schemes.
  static bool isInternalUri(Uri? uri, {Uri? baseUri}) {
    if (uri == null) return true;
    final scheme = uri.scheme.toLowerCase();
    if (scheme == 'about' || scheme == 'blob' || scheme == 'data') {
      return true;
    }
    if (scheme != 'https' && scheme != 'http') {
      return false;
    }
    final resolvedBase = baseUri ?? Uri.parse(appBaseUrl);
    return uri.host.toLowerCase() == resolvedBase.host.toLowerCase();
  }

  /// Returns true if [uri] is a Google OAuth flow that must complete inside the WebView
  /// so session cookies and callbacks return to KIUQ SYSTEM seamlessly.
  static bool isInAppOAuthUri(Uri? uri) {
    if (uri == null) return false;
    final host = uri.host.toLowerCase();
    final path = uri.path.toLowerCase();
    if (host == 'accounts.google.com' || host.endsWith('.google.com')) {
      if (host == 'accounts.google.com' ||
          path.contains('/o/oauth2') ||
          path.contains('/signin/oauth')) {
        return true;
      }
    }
    return false;
  }

  /// Returns true only for safe `http`/`https`/`mailto`/`tel` external links.
  /// Blocks `javascript:`, `file:`, and unverified schemes.
  static bool isSafeExternalScheme(Uri? uri) {
    if (uri == null) return false;
    final scheme = uri.scheme.toLowerCase();
    return scheme == 'https' ||
        scheme == 'http' ||
        scheme == 'mailto' ||
        scheme == 'tel';
  }

  /// Detects file download / export URLs in KIUQ SYSTEM.
  static bool isDownloadUri(Uri? uri) {
    if (uri == null) return false;
    final path = uri.path.toLowerCase();
    final segments = uri.pathSegments.map((s) => s.toLowerCase()).toList();

    // Preview endpoints render HTML or inline media inside the app; only direct
    // export/download endpoints or file extensions should trigger download flow.
    if (segments.contains('preview') || segments.contains('view')) {
      return _downloadExtensions.any(path.endsWith);
    }

    if (segments.contains('download') ||
        segments.contains('export') ||
        path.endsWith('/template') ||
        path.endsWith('/template-xlsx') ||
        path.endsWith('/staff/pdf') ||
        path.endsWith('/pdf')) {
      return true;
    }

    return _downloadExtensions.any(path.endsWith);
  }

  /// Returns true if the filename or URL can be rendered via native iOS QuickLook (`QLPreviewController`).
  static bool isQuickLookPreviewable(String pathOrFileName) {
    final lower = pathOrFileName.toLowerCase();
    if (lower.endsWith('/pdf') || lower.contains('export/pdf')) {
      return true;
    }
    return _previewableExtensions.any(lower.endsWith);
  }

  /// Returns true if [url] is an authenticated KIUQ SYSTEM page suitable for
  /// session state restoration across app launches.
  static bool isRestorableAppUrl(String? url, {Uri? baseUri}) {
    if (url == null || url.trim().isEmpty) return false;
    final uri = Uri.tryParse(url.trim());
    if (uri == null) return false;
    final resolvedBase = baseUri ?? Uri.parse(appBaseUrl);
    if (uri.host.toLowerCase() != resolvedBase.host.toLowerCase()) {
      return false;
    }
    final path = uri.path.toLowerCase();
    if (path.isEmpty || path == '/') return false;
    const excludedPrefixes = <String>[
      '/login',
      '/logout',
      '/auth/',
      '/export/',
      '/api/',
      '/ping',
      '/attachments/',
      '/connectors/download',
      '/ios-app/download',
      '/mac-app/download',
    ];
    for (final prefix in excludedPrefixes) {
      if (path.startsWith(prefix)) return false;
    }
    if (KiuqUrlPolicy.isDownloadUri(uri)) return false;
    return true;
  }

  /// Normalizes a relative or absolute KIUQ path into a full URL on [appBaseUrl].
  static String resolveInternalUrl(String pathOrUrl, {String? base}) {
    final effectiveBase = (base ?? appBaseUrl).replaceFirst(RegExp(r'/$'), '');
    final trimmed = pathOrUrl.trim();
    if (trimmed.isEmpty) return '$effectiveBase/dashboard';
    final parsed = Uri.tryParse(trimmed);
    if (parsed != null && parsed.hasScheme) {
      return trimmed;
    }
    final cleanPath = trimmed.startsWith('/') ? trimmed : '/$trimmed';
    return '$effectiveBase$cleanPath';
  }
}
