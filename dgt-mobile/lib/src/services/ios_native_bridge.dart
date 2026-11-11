import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_inappwebview/flutter_inappwebview.dart';

import '../config/app_config.dart';

/// Connectivity snapshot reported by native `NWPathMonitor` or `/ping` check.
@immutable
class KiuqConnectivityStatus {
  const KiuqConnectivityStatus({
    required this.isOnline,
    this.connectionType = 'wifi',
    this.latencyMs,
  });

  final bool isOnline;
  final String connectionType;
  final int? latencyMs;
}

/// Result of an authenticated file download handled natively on iOS.
@immutable
class KiuqDownloadResult {
  const KiuqDownloadResult({
    required this.success,
    this.filePath,
    this.fileName,
    this.errorMessage,
  });

  final bool success;
  final String? filePath;
  final String? fileName;
  final String? errorMessage;
}

/// Typed Dart interface to the native Swift `kiuq_system/ios_bridge` channel.
class IosNativeBridge {
  IosNativeBridge({
    this.onConnectivityChanged,
    this.onNotificationTap,
  }) {
    _channel.setMethodCallHandler(_handleNativeCallback);
  }

  static const MethodChannel _channel = MethodChannel('kiuq_system/ios_bridge');

  final ValueChanged<KiuqConnectivityStatus>? onConnectivityChanged;
  final ValueChanged<String>? onNotificationTap;

  bool get _isNativeIos =>
      !kIsWeb && defaultTargetPlatform == TargetPlatform.iOS;

  Future<dynamic> _handleNativeCallback(MethodCall call) async {
    final args = call.arguments;
    if (call.method == 'onConnectivityChanged' && args is Map) {
      final online = args['online'] == true;
      final type = (args['type'] ?? 'wifi').toString();
      onConnectivityChanged?.call(
        KiuqConnectivityStatus(isOnline: online, connectionType: type),
      );
    } else if (call.method == 'onNotificationTap' && args is Map) {
      final url = (args['url'] ?? '').toString().trim();
      if (url.isNotEmpty) {
        onNotificationTap?.call(url);
      }
    }
    return null;
  }

  /// Queries `NWPathMonitor` for immediate OS-level network status.
  Future<KiuqConnectivityStatus> checkConnectivity() async {
    if (!_isNativeIos) {
      return const KiuqConnectivityStatus(isOnline: true);
    }
    try {
      final res = await _channel.invokeMapMethod<String, dynamic>(
        'checkConnectivity',
      );
      if (res == null) {
        return const KiuqConnectivityStatus(isOnline: true);
      }
      return KiuqConnectivityStatus(
        isOnline: res['online'] != false,
        connectionType: (res['type'] ?? 'wifi').toString(),
      );
    } catch (_) {
      return const KiuqConnectivityStatus(isOnline: true);
    }
  }

  /// Verifies end-to-end reachability against KIUQ SYSTEM `/ping` endpoint.
  Future<KiuqConnectivityStatus> pingServer({String? baseUrl}) async {
    final resolvedBase = (baseUrl ?? appBaseUrl).replaceFirst(RegExp(r'/$'), '');
    final pingUri = Uri.parse('$resolvedBase/ping');
    final stopwatch = Stopwatch()..start();
    final client = HttpClient()..connectionTimeout = const Duration(seconds: 6);
    try {
      final request = await client.getUrl(pingUri);
      request.headers.set(HttpHeaders.acceptHeader, 'application/json,text/plain,*/*');
      final response = await request.close().timeout(const Duration(seconds: 6));
      await response.drain<void>();
      stopwatch.stop();
      final reachable = response.statusCode >= 200 && response.statusCode < 500;
      return KiuqConnectivityStatus(
        isOnline: reachable,
        latencyMs: stopwatch.elapsedMilliseconds,
      );
    } catch (_) {
      stopwatch.stop();
      return const KiuqConnectivityStatus(
        isOnline: false,
        connectionType: 'none',
      );
    } finally {
      client.close(force: true);
    }
  }

  /// Opens the native iOS Share Sheet (`UIActivityViewController`) with iPad popover safety.
  Future<bool> shareUrl({
    required String url,
    String? title,
  }) async {
    if (!_isNativeIos) return false;
    try {
      final shared = await _channel.invokeMethod<bool>('share', <String, dynamic>{
        'url': url,
        if (title != null && title.trim().isNotEmpty) 'title': title.trim(),
      });
      return shared == true;
    } catch (_) {
      return false;
    }
  }

  /// Shares a local file via `UIActivityViewController` (Save to Files, AirDrop, Mail, etc.).
  Future<bool> shareFile({
    required String filePath,
    String? title,
  }) async {
    if (!_isNativeIos) return false;
    try {
      final shared = await _channel.invokeMethod<bool>('share', <String, dynamic>{
        'filePath': filePath,
        if (title != null && title.trim().isNotEmpty) 'title': title.trim(),
      });
      return shared == true;
    } catch (_) {
      return false;
    }
  }

  /// Opens a local PDF, Office document, CSV, or image in native iOS QuickLook (`QLPreviewController`).
  Future<bool> previewFile({
    required String filePath,
    String? title,
  }) async {
    if (!_isNativeIos) return false;
    try {
      final shown = await _channel.invokeMethod<bool>(
        'previewFile',
        <String, dynamic>{
          'filePath': filePath,
          if (title != null && title.trim().isNotEmpty) 'title': title.trim(),
        },
      );
      return shown == true;
    } catch (_) {
      return false;
    }
  }

  /// Returns `'authorized'`, `'denied'`, or `'notDetermined'`.
  Future<String> getNotificationStatus() async {
    if (!_isNativeIos) return 'notDetermined';
    try {
      return await _channel.invokeMethod<String>('getNotificationStatus') ??
          'notDetermined';
    } catch (_) {
      return 'notDetermined';
    }
  }

  /// Requests native iOS notification authorization only when explicitly initiated.
  Future<bool> requestNotificationPermission() async {
    if (!_isNativeIos) return false;
    try {
      final granted = await _channel.invokeMethod<bool>(
        'requestNotificationPermission',
      );
      return granted == true;
    } catch (_) {
      return false;
    }
  }

  /// Delivers a local notification banner for real-time Pusher / Chat events.
  Future<bool> showLocalNotification({
    required String title,
    required String body,
    String? url,
  }) async {
    if (!_isNativeIos) return false;
    try {
      final delivered = await _channel.invokeMethod<bool>(
        'showLocalNotification',
        <String, dynamic>{
          'title': title,
          'body': body,
          if (url != null && url.isNotEmpty) 'url': url,
        },
      );
      return delivered == true;
    } catch (_) {
      return false;
    }
  }

  /// Verifies/requests native iOS Camera or Microphone access (`AVCaptureDevice`).
  Future<bool> requestMediaPermission(String kind) async {
    if (!_isNativeIos) return true;
    try {
      final granted = await _channel.invokeMethod<bool>(
        'requestMediaPermission',
        <String, dynamic>{'kind': kind},
      );
      return granted == true;
    } catch (_) {
      return false;
    }
  }

  /// Downloads an authenticated KIUQ SYSTEM file using active `WKWebView` session cookies
  /// and presents it via QuickLook (for PDFs/docs/images) or the iOS Share Sheet.
  Future<KiuqDownloadResult> downloadAndPresentAuthenticatedFile({
    required Uri uri,
    String? suggestedFilename,
    String? userAgent,
  }) async {
    final client = HttpClient()..connectionTimeout = const Duration(seconds: 20);
    try {
      final cookies = await CookieManager.instance().getCookies(
        url: WebUri(uri.toString()),
      );
      final cookieHeader = cookies
          .map((c) => '${c.name}=${c.value}')
          .where((pair) => pair.length > 1)
          .join('; ');

      final request = await client.getUrl(uri);
      if (cookieHeader.isNotEmpty) {
        request.headers.set(HttpHeaders.cookieHeader, cookieHeader);
      }
      if (userAgent != null && userAgent.isNotEmpty) {
        request.headers.set(HttpHeaders.userAgentHeader, userAgent);
      }
      request.headers.set(HttpHeaders.acceptHeader, '*/*');

      final response = await request.close().timeout(const Duration(seconds: 45));
      if (response.statusCode < 200 || response.statusCode >= 400) {
        await response.drain<void>();
        return KiuqDownloadResult(
          success: false,
          errorMessage: 'Download returned HTTP ${response.statusCode}',
        );
      }

      final contentType = response.headers.contentType?.mimeType.toLowerCase() ?? '';
      // Guard against session-expired HTML login page masquerading as a download.
      if (contentType == 'text/html' && !uri.path.toLowerCase().endsWith('.html')) {
        await response.drain<void>();
        return const KiuqDownloadResult(
          success: false,
          errorMessage: 'Session authentication required for this file.',
        );
      }

      final disposition = response.headers.value('content-disposition');
      final resolvedName = _resolveSafeFileName(
        uri: uri,
        suggestedFilename: suggestedFilename,
        contentDisposition: disposition,
        mimeType: contentType,
      );

      final downloadsDir = Directory(
        '${Directory.systemTemp.path}/kiuq_downloads',
      );
      if (!await downloadsDir.exists()) {
        await downloadsDir.create(recursive: true);
      }

      final targetFile = File('${downloadsDir.path}/$resolvedName');
      final sink = targetFile.openWrite();
      await response.pipe(sink);
      await sink.close();

      await _presentDownloadedFile(targetFile.path, resolvedName);
      return KiuqDownloadResult(
        success: true,
        filePath: targetFile.path,
        fileName: resolvedName,
      );
    } catch (error) {
      return KiuqDownloadResult(
        success: false,
        errorMessage: error.toString(),
      );
    } finally {
      client.close(force: true);
    }
  }

  /// Decodes a `data:` or `blob:` base64 payload sent via `DgtNativeDownload` JS bridge
  /// and opens it natively via QuickLook or the Share Sheet.
  Future<KiuqDownloadResult> saveBase64AndPresent({
    required String base64Data,
    String? fileName,
    String? mimeType,
  }) async {
    try {
      final cleanBase64 = base64Data.contains(',')
          ? base64Data.split(',').last
          : base64Data;
      final bytes = base64Decode(cleanBase64);
      final safeName = _sanitizeFileName(
        fileName?.trim().isNotEmpty == true
            ? fileName!.trim()
            : 'kiuq-export${_extensionForMime(mimeType ?? '')}',
      );

      final downloadsDir = Directory(
        '${Directory.systemTemp.path}/kiuq_downloads',
      );
      if (!await downloadsDir.exists()) {
        await downloadsDir.create(recursive: true);
      }

      final file = File('${downloadsDir.path}/$safeName');
      await file.writeAsBytes(bytes, flush: true);
      await _presentDownloadedFile(file.path, safeName);

      return KiuqDownloadResult(
        success: true,
        filePath: file.path,
        fileName: safeName,
      );
    } catch (error) {
      return KiuqDownloadResult(
        success: false,
        errorMessage: error.toString(),
      );
    }
  }

  Future<void> _presentDownloadedFile(String filePath, String fileName) async {
    if (KiuqUrlPolicy.isQuickLookPreviewable(fileName)) {
      final opened = await previewFile(filePath: filePath, title: fileName);
      if (opened) return;
    }
    await shareFile(filePath: filePath, title: fileName);
  }

  String _resolveSafeFileName({
    required Uri uri,
    String? suggestedFilename,
    String? contentDisposition,
    required String mimeType,
  }) {
    String? candidate;

    if (contentDisposition != null && contentDisposition.isNotEmpty) {
      final utf8Match = RegExp(
        r"filename\*=UTF-8''([^;\r\n]+)",
        caseSensitive: false,
      ).firstMatch(contentDisposition);
      if (utf8Match != null) {
        candidate = Uri.decodeFull(utf8Match.group(1)!.trim());
      } else {
        final quotedMatch = RegExp(
          r'filename="?([^";\r\n]+)"?',
          caseSensitive: false,
        ).firstMatch(contentDisposition);
        if (quotedMatch != null) {
          candidate = quotedMatch.group(1)!.trim();
        }
      }
    }

    if ((candidate == null || candidate.isEmpty) &&
        suggestedFilename != null &&
        suggestedFilename.trim().isNotEmpty) {
      candidate = suggestedFilename.trim();
    }

    if (candidate == null || candidate.isEmpty) {
      final lastSegment = uri.pathSegments.isNotEmpty ? uri.pathSegments.last : '';
      candidate = lastSegment.isNotEmpty ? lastSegment : 'kiuq-document';
    }

    var sanitized = _sanitizeFileName(candidate);
    if (!sanitized.contains('.')) {
      final ext = _extensionForMime(mimeType, fallbackPath: uri.path);
      sanitized = '$sanitized$ext';
    }
    return sanitized;
  }

  String _sanitizeFileName(String raw) {
    final cleaned = raw
        .replaceAll(RegExp(r'[\\/:*?"<>|]'), '_')
        .replaceAll(RegExp(r'\s+'), ' ')
        .trim();
    return cleaned.isEmpty ? 'kiuq-file.pdf' : cleaned;
  }

  String _extensionForMime(String mimeType, {String fallbackPath = ''}) {
    final lowerPath = fallbackPath.toLowerCase();
    if (mimeType.contains('pdf') || lowerPath.endsWith('/pdf')) return '.pdf';
    if (mimeType.contains('csv') || lowerPath.contains('csv')) return '.csv';
    if (mimeType.contains('spreadsheetml') || lowerPath.contains('xlsx')) {
      return '.xlsx';
    }
    if (mimeType.contains('ms-excel')) return '.xls';
    if (mimeType.contains('wordprocessingml')) return '.docx';
    if (mimeType.contains('png')) return '.png';
    if (mimeType.contains('jpeg') || mimeType.contains('jpg')) return '.jpg';
    if (mimeType.contains('webp')) return '.webp';
    if (mimeType.contains('zip')) return '.zip';
    return '.pdf';
  }
}
