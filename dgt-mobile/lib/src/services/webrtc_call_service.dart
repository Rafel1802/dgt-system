import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter_inappwebview/flutter_inappwebview.dart';
import 'package:flutter_webrtc/flutter_webrtc.dart';

import '../config/app_config.dart';
import 'ios_native_bridge.dart';

enum KiuqCallPhase {
  idle,
  outgoingCalling,
  outgoingRinging,
  incomingRinging,
  connecting,
  connected,
  reconnecting,
  permissionDenied,
}

/// Real-time 1-to-1 WebRTC Voice & Video Call controller powered by `flutter_webrtc`,
/// synchronized with KIUQ SYSTEM's authenticated Laravel + Pusher signaling endpoints.
class WebRtcCallController extends ChangeNotifier {
  WebRtcCallController({
    required this.nativeBridge,
    this.enableNativeWebRtc = true,
    this.onNotifyWebCallEnded,
    this.webHttpExecutor,
  }) {
    nativeBridge.onCallKitAction = _handleCallKitAction;
  }

  final IosNativeBridge nativeBridge;
  final bool enableNativeWebRtc;
  final VoidCallback? onNotifyWebCallEnded;

  /// Optional authenticated HTTP executor running inside the live `InAppWebView`
  /// so first-party `HttpOnly` `laravel_session` cookies, CSRF tokens, and Pusher
  /// `X-Socket-ID` headers are guaranteed to match the active web session.
  final Future<Map<String, dynamic>?> Function(
    String method,
    String path,
    Map<String, dynamic>? body,
  )? webHttpExecutor;

  final RTCVideoRenderer localRenderer = RTCVideoRenderer();
  final RTCVideoRenderer remoteRenderer = RTCVideoRenderer();
  bool _renderersInitialized = false;

  RTCPeerConnection? _peerConnection;
  MediaStream? _localStream;
  MediaStream? _remoteStream;

  final List<RTCIceCandidate> _pendingCandidates = <RTCIceCandidate>[];
  final Set<String> _processedKeys = <String>{};

  KiuqCallPhase _phase = KiuqCallPhase.idle;
  bool _minimized = false;
  bool _isCaller = false;
  bool _isVideo = false;
  bool _isMuted = false;
  bool _isCameraOn = false;
  bool _isFrontCamera = true;
  bool _speakerOn = false;
  bool _remoteVideoActive = false;
  bool _remoteMuted = false;
  int _callId = 0;
  int _conversationId = 0;
  int _otherUserId = 0;
  int _authUserId = 0;
  int _lastSignalId = 0;
  int _durationSeconds = 0;
  String _contactName = 'Team Member';
  String _contactAvatarUrl = '';
  String _csrfToken = '';
  String _socketId = '';
  String? _errorMessage;
  bool _iceRestartAttempted = false;

  Timer? _durationTimer;
  Timer? _signalPollTimer;
  Timer? _ringTimeoutTimer;
  Timer? _reconnectTimer;

  List<Map<String, dynamic>> _iceServers = <Map<String, dynamic>>[
    <String, dynamic>{'urls': 'stun:stun.l.google.com:19302'},
    <String, dynamic>{'urls': 'stun:stun.cloudflare.com:3478'},
  ];

  // ─── Public Getters ───────────────────────────────────────────────────────

  KiuqCallPhase get phase => _phase;
  bool get isActive => _phase != KiuqCallPhase.idle;
  bool get isMinimized => _minimized;
  bool get isCaller => _isCaller;
  bool get isVideo => _isVideo;
  bool get isMuted => _isMuted;
  bool get isCameraOn => _isCameraOn;
  bool get isFrontCamera => _isFrontCamera;
  bool get speakerOn => _speakerOn;
  bool get remoteVideoActive => _remoteVideoActive;
  bool get remoteMuted => _remoteMuted;
  bool get renderersReady => _renderersInitialized;
  int get callId => _callId;
  int get conversationId => _conversationId;
  int get otherUserId => _otherUserId;
  int get durationSeconds => _durationSeconds;
  String get contactName => _contactName;
  String get contactAvatarUrl => _contactAvatarUrl;
  String? get errorMessage => _errorMessage;

  String get formattedDuration {
    final hrs = _durationSeconds ~/ 3600;
    final mins = (_durationSeconds % 3600) ~/ 60;
    final secs = _durationSeconds % 60;
    String two(int n) => n.toString().padLeft(2, '0');
    return hrs > 0
        ? '${two(hrs)}:${two(mins)}:${two(secs)}'
        : '${two(mins)}:${two(secs)}';
  }

  Future<void> ensureRenderersInitialized() async {
    if (_renderersInitialized || !enableNativeWebRtc || kIsWeb) return;
    try {
      await localRenderer.initialize();
      await remoteRenderer.initialize();
      _renderersInitialized = true;
    } catch (_) {}
  }

  void updateWebAuthMetadata({
    int? authUserId,
    String? csrfToken,
    String? socketId,
  }) {
    if (authUserId != null && authUserId > 0) _authUserId = authUserId;
    if (csrfToken != null && csrfToken.isNotEmpty) _csrfToken = csrfToken;
    if (socketId != null) _socketId = socketId;
  }

  /// Test helper for verifying Incoming / Active Liquid Glass UI states deterministically.
  @visibleForTesting
  void debugPreviewCallState({
    required KiuqCallPhase phase,
    required String contactName,
    bool isVideo = false,
    bool isCaller = false,
    bool minimized = false,
    int durationSeconds = 0,
  }) {
    _phase = phase;
    _contactName = contactName;
    _isVideo = isVideo;
    _isCaller = isCaller;
    _minimized = minimized;
    _durationSeconds = durationSeconds;
    notifyListeners();
  }

  // ─── WebView Bridge Dispatcher ────────────────────────────────────────────

  Future<void> handleWebViewBridgePayload(Map<String, dynamic> payload) async {
    final action = (payload['action'] ?? '').toString();
    updateWebAuthMetadata(
      authUserId: int.tryParse('${payload['authUserId'] ?? 0}'),
      csrfToken: (payload['csrfToken'] ?? '').toString(),
      socketId: (payload['socketId'] ?? '').toString(),
    );

    if (action == 'startCall') {
      final receiverId = int.tryParse('${payload['receiverId'] ?? 0}') ?? 0;
      final conversationId =
          int.tryParse('${payload['conversationId'] ?? 0}') ?? 0;
      final callType = (payload['callType'] ?? 'voice').toString();
      final name = (payload['contactName'] ?? 'Team Member').toString();
      final avatar = (payload['contactAvatar'] ?? '').toString();
      await startOutgoingCall(
        receiverId: receiverId,
        conversationId: conversationId,
        isVideo: callType == 'video',
        contactName: name,
        contactAvatarUrl: avatar,
      );
    } else if (action == 'pusherSignal') {
      final eventName = (payload['eventName'] ?? '').toString();
      final data = payload['data'] is Map
          ? Map<String, dynamic>.from(payload['data'] as Map)
          : <String, dynamic>{};
      await handleSignalingEvent(eventName, data);
    }
  }

  // ─── Outgoing Call Flow ───────────────────────────────────────────────────

  Future<void> startOutgoingCall({
    required int receiverId,
    int conversationId = 0,
    required bool isVideo,
    required String contactName,
    String contactAvatarUrl = '',
  }) async {
    if (receiverId <= 0) return;

    if (isActive) {
      _minimized = false;
      notifyListeners();
      return;
    }

    await ensureRenderersInitialized();
    _processedKeys.clear();
    _pendingCandidates.clear();
    _lastSignalId = 0;
    _iceRestartAttempted = false;

    _phase = KiuqCallPhase.outgoingCalling;
    _minimized = false;
    _isCaller = true;
    _isVideo = isVideo;
    _isCameraOn = isVideo;
    _isMuted = false;
    _speakerOn = isVideo;
    _remoteVideoActive = false;
    _remoteMuted = false;
    _otherUserId = receiverId;
    _conversationId = conversationId;
    _contactName = contactName;
    _contactAvatarUrl = contactAvatarUrl;
    _durationSeconds = 0;
    _errorMessage = null;
    notifyListeners();

    final hasMedia = await _acquireLocalMedia(wantVideo: isVideo);
    if (!hasMedia) return;

    await nativeBridge.configureCallAudio(
      isVideo: isVideo,
      speakerOn: _speakerOn,
    );

    try {
      final res = await _postJson('/chat/calls/initiate', <String, dynamic>{
        'receiver_id': receiverId,
        if (conversationId > 0) 'conversation_id': conversationId,
        'call_type': isVideo ? 'video' : 'voice',
      });

      if (_phase == KiuqCallPhase.idle) {
        final createdId = int.tryParse('${res['call']?['id'] ?? 0}') ?? 0;
        if (createdId > 0) {
          unawaited(
            _postJson('/chat/calls/$createdId/cancel', <String, dynamic>{
              'reason': 'cancelled',
            }),
          );
        }
        return;
      }

      final callMap = res['call'] is Map
          ? Map<String, dynamic>.from(res['call'] as Map)
          : <String, dynamic>{};
      _callId = int.tryParse('${callMap['id'] ?? 0}') ?? 0;
      _conversationId =
          int.tryParse('${callMap['conversation_id'] ?? _conversationId}') ?? 0;

      if (callMap['ice_servers'] is List) {
        _parseIceServers(callMap['ice_servers'] as List);
      } else {
        await _fetchIceConfig();
      }

      final otherUser = callMap['other_user'] is Map
          ? Map<String, dynamic>.from(callMap['other_user'] as Map)
          : null;
      if (otherUser != null) {
        final serverName = (otherUser['name'] ?? '').toString().trim();
        final serverAvatar = (otherUser['avatar_url'] ?? '').toString().trim();
        if (serverName.isNotEmpty) _contactName = serverName;
        if (serverAvatar.isNotEmpty) _contactAvatarUrl = serverAvatar;
      }
      notifyListeners();

      unawaited(
        nativeBridge.startOutgoingCallKit(
          callId: _callId,
          calleeName: _contactName,
          isVideo: isVideo,
        ),
      );

      await _createPeerConnection(_callId);
      await _createAndSendOffer(_callId, iceRestart: false);
      _startSignalPolling(_callId);
      _armRingTimeout(_callId);
    } catch (err) {
      await _cleanupCall();
      _errorMessage = err.toString().replaceFirst('Exception: ', '');
      _phase = KiuqCallPhase.permissionDenied;
      notifyListeners();
    }
  }

  // ─── Incoming Call & Signaling Flow ───────────────────────────────────────

  Future<void> handleSignalingEvent(
    String eventName,
    Map<String, dynamic> data,
  ) async {
    final incomingCallId =
        int.tryParse('${data['call_id'] ?? data['call']?['id'] ?? 0}') ?? 0;
    final senderId = int.tryParse('${data['sender_id'] ?? 0}') ?? 0;
    final signalId = int.tryParse('${data['signal_id'] ?? 0}') ?? 0;
    final payload = data['payload'] is Map
        ? Map<String, dynamic>.from(data['payload'] as Map)
        : <String, dynamic>{};
    final rawType =
        (payload['canonical_event'] ?? data['event'] ?? data['type'] ?? eventName)
            .toString();

    if (_authUserId > 0 &&
        senderId == _authUserId &&
        !rawType.contains('answered_elsewhere')) {
      return;
    }

    final key = signalId > 0
        ? 'sig:$incomingCallId:$signalId'
        : 'evt:$incomingCallId:$rawType:${data['status'] ?? ''}:${data['timestamp'] ?? ''}';
    if (_processedKeys.contains(key)) return;
    _processedKeys.add(key);
    if (signalId > _lastSignalId) _lastSignalId = signalId;

    if (rawType.contains('invited') || rawType == 'incoming_call') {
      await _onIncomingCallInvite(data, payload);
      return;
    }

    if (rawType.contains('answered_elsewhere')) {
      if (isActive &&
          _callId == incomingCallId &&
          !_isCaller &&
          _phase == KiuqCallPhase.incomingRinging) {
        await _cleanupCall();
      }
      return;
    }

    if (!isActive || (_callId > 0 && incomingCallId > 0 && _callId != incomingCallId)) {
      return;
    }

    if (rawType.contains('ringing')) {
      if (_isCaller && _phase == KiuqCallPhase.outgoingCalling) {
        _phase = KiuqCallPhase.outgoingRinging;
        notifyListeners();
      }
    } else if (rawType.contains('accepted')) {
      _ringTimeoutTimer?.cancel();
      if (_phase != KiuqCallPhase.connected) {
        _phase = KiuqCallPhase.connecting;
        notifyListeners();
      }
    } else if (rawType.contains('rejected') ||
        rawType == 'call_declined' ||
        rawType.contains('busy') ||
        rawType.contains('cancelled') ||
        rawType.contains('timeout') ||
        rawType.contains('ended') ||
        rawType == 'end') {
      onNotifyWebCallEnded?.call();
      await _cleanupCall();
    } else if (rawType.contains('offer') || rawType == 'renegotiate') {
      if (payload['sdp_in_db'] == true || payload['sdp'] == null) {
        await _pollSignalsOnce(_callId);
      } else {
        await _handleRemoteOffer(_callId, payload);
      }
    } else if (rawType.contains('answer')) {
      if (payload['sdp_in_db'] == true || payload['sdp'] == null) {
        await _pollSignalsOnce(_callId);
      } else {
        await _handleRemoteAnswer(payload);
      }
    } else if (rawType.contains('ice_candidate') || rawType == 'ice-candidate') {
      await _handleRemoteCandidate(payload);
    } else if (rawType.contains('video_on')) {
      _remoteVideoActive = true;
      _isVideo = true;
      notifyListeners();
    } else if (rawType.contains('video_off')) {
      _remoteVideoActive = false;
      notifyListeners();
    } else if (rawType.contains('unmute')) {
      _remoteMuted = false;
      notifyListeners();
    } else if (rawType.contains('mute')) {
      _remoteMuted = true;
      notifyListeners();
    }
  }

  Future<void> _onIncomingCallInvite(
    Map<String, dynamic> data,
    Map<String, dynamic> payload,
  ) async {
    final incomingId =
        int.tryParse('${data['call_id'] ?? data['call']?['id'] ?? 0}') ?? 0;
    final callerId =
        int.tryParse('${data['caller_id'] ?? data['caller']?['id'] ?? 0}') ?? 0;
    if (incomingId <= 0) return;

    if (isActive && _callId != incomingId) {
      unawaited(
        _postJson('/chat/calls/$incomingId/reject', <String, dynamic>{
          'reason': 'busy',
        }),
      );
      return;
    }

    await ensureRenderersInitialized();
    final callType =
        (data['call_type'] ?? payload['call_type'] ?? data['call']?['call_type'])
            .toString();
    final callerMap = data['caller'] is Map
        ? Map<String, dynamic>.from(data['caller'] as Map)
        : null;
    final callerName = (callerMap?['name'] ?? 'Team Member').toString();
    final callerAvatar = (callerMap?['avatar_url'] ?? '').toString();

    if (payload['ice_servers'] is List) {
      _parseIceServers(payload['ice_servers'] as List);
    }

    _pendingCandidates.clear();
    _lastSignalId = 0;
    _iceRestartAttempted = false;

    _callId = incomingId;
    _otherUserId = callerId;
    _conversationId =
        int.tryParse('${data['conversation_id'] ?? 0}') ?? 0;
    _isCaller = false;
    _minimized = false;
    _isVideo = callType == 'video';
    _isCameraOn = false;
    _isMuted = false;
    _speakerOn = _isVideo;
    _remoteVideoActive = false;
    _remoteMuted = false;
    _durationSeconds = 0;
    _contactName = callerName;
    _contactAvatarUrl = callerAvatar;
    _phase = KiuqCallPhase.incomingRinging;
    _errorMessage = null;
    notifyListeners();

    unawaited(_postJson('/chat/calls/$incomingId/ringing', const <String, dynamic>{}));
    unawaited(
      nativeBridge.reportIncomingCallKit(
        callId: incomingId,
        callerName: callerName,
        isVideo: _isVideo,
      ),
    );
    _startSignalPolling(incomingId);
    _armRingTimeout(incomingId);
  }

  Future<void> acceptIncomingCall({bool? withVideo}) async {
    if (_callId <= 0) return;
    final wantVideo = withVideo ?? _isVideo;
    _ringTimeoutTimer?.cancel();

    _phase = KiuqCallPhase.connecting;
    _isCameraOn = wantVideo;
    _isVideo = wantVideo || _isVideo;
    _speakerOn = _isVideo;
    notifyListeners();

    final hasMedia = await _acquireLocalMedia(wantVideo: wantVideo);
    if (!hasMedia) return;

    await nativeBridge.configureCallAudio(
      isVideo: _isVideo,
      speakerOn: _speakerOn,
    );

    try {
      final res = await _postJson(
        '/chat/calls/$_callId/accept',
        const <String, dynamic>{},
      );
      if (res['call'] is Map && res['call']['ice_servers'] is List) {
        _parseIceServers(res['call']['ice_servers'] as List);
      } else {
        await _fetchIceConfig();
      }

      await _createPeerConnection(_callId);
      await _pollSignalsOnce(_callId, resetCursor: true);
    } catch (_) {
      await _cleanupCall();
    }
  }

  Future<void> rejectIncomingCall() async {
    final id = _callId;
    await _cleanupCall();
    if (id > 0) {
      unawaited(
        _postJson('/chat/calls/$id/reject', <String, dynamic>{
          'reason': 'declined',
        }),
      );
      onNotifyWebCallEnded?.call();
    }
  }

  Future<void> endCall({String reason = 'normal'}) async {
    final id = _callId;
    final wasConnected =
        _phase == KiuqCallPhase.connected ||
        _phase == KiuqCallPhase.reconnecting;
    final wasCaller = _isCaller;

    await _cleanupCall();

    if (id <= 0) return;
    if (!wasConnected && wasCaller) {
      unawaited(
        _postJson('/chat/calls/$id/cancel', <String, dynamic>{
          'reason': 'cancelled',
        }),
      );
    } else {
      unawaited(
        _postJson('/chat/calls/$id/end', <String, dynamic>{'reason': reason}),
      );
    }
    onNotifyWebCallEnded?.call();
  }

  // ─── Media & PeerConnection Lifecycle ─────────────────────────────────────

  Future<bool> _acquireLocalMedia({required bool wantVideo}) async {
    final micAllowed = await nativeBridge.requestMediaPermission('microphone');
    if (!micAllowed) {
      _phase = KiuqCallPhase.permissionDenied;
      _errorMessage =
          'Microphone permission is required for voice and video calls. Please enable Microphone access in Settings.';
      notifyListeners();
      return false;
    }

    var effectiveVideo = wantVideo;
    if (wantVideo) {
      final camAllowed = await nativeBridge.requestMediaPermission('camera');
      if (!camAllowed) {
        effectiveVideo = false;
        _isCameraOn = false;
      }
    }

    if (!enableNativeWebRtc || kIsWeb) {
      return true;
    }

    try {
      final constraints = <String, dynamic>{
        'audio': <String, dynamic>{
          'echoCancellation': true,
          'noiseSuppression': true,
          'autoGainControl': true,
        },
        'video': effectiveVideo
            ? <String, dynamic>{
                'facingMode': _isFrontCamera ? 'user' : 'environment',
                'width': <String, dynamic>{'ideal': 1280},
                'height': <String, dynamic>{'ideal': 720},
                'frameRate': <String, dynamic>{'ideal': 30},
              }
            : false,
      };

      try {
        _localStream = await navigator.mediaDevices.getUserMedia(constraints);
      } catch (_) {
        if (effectiveVideo) {
          // Graceful fallback to audio-only (e.g., iOS Simulator with no hardware camera)
          _localStream = await navigator.mediaDevices.getUserMedia(
            <String, dynamic>{'audio': true, 'video': false},
          );
          _isCameraOn = false;
        } else {
          rethrow;
        }
      }

      if (_renderersInitialized && _localStream != null) {
        localRenderer.srcObject = _localStream;
      }
      notifyListeners();
      return true;
    } catch (err) {
      _phase = KiuqCallPhase.permissionDenied;
      _errorMessage = 'Unable to open audio/video stream: $err';
      notifyListeners();
      return false;
    }
  }

  Future<void> _createPeerConnection(int activeCallId) async {
    if (!enableNativeWebRtc || kIsWeb) return;

    await _peerConnection?.close();
    final pc = await createPeerConnection(<String, dynamic>{
      'iceServers': _iceServers,
      'sdpSemantics': 'unified-plan',
    });
    _peerConnection = pc;

    final local = _localStream;
    if (local != null) {
      for (final track in local.getTracks()) {
        await pc.addTrack(track, local);
      }
    }

    pc.onIceCandidate = (RTCIceCandidate candidate) {
      if (candidate.candidate == null || !isActive || _callId != activeCallId) {
        return;
      }
      unawaited(
        _postJson('/chat/calls/$activeCallId/signal', <String, dynamic>{
          'type': 'ice_candidate',
          'payload': <String, dynamic>{
            'candidate': <String, dynamic>{
              'candidate': candidate.candidate,
              'sdpMid': candidate.sdpMid,
              'sdpMLineIndex': candidate.sdpMLineIndex,
            },
          },
        }),
      );
    };

    pc.onTrack = (RTCTrackEvent event) {
      if (event.streams.isNotEmpty) {
        _remoteStream = event.streams.first;
        if (_renderersInitialized) {
          remoteRenderer.srcObject = _remoteStream;
        }
      }
      if (event.track.kind == 'video') {
        _remoteVideoActive = true;
        _isVideo = true;
      }
      notifyListeners();
    };

    pc.onIceConnectionState = (RTCIceConnectionState state) {
      if (_peerConnection != pc) return;
      switch (state) {
        case RTCIceConnectionState.RTCIceConnectionStateChecking:
          if (_phase != KiuqCallPhase.connected) {
            _phase = KiuqCallPhase.connecting;
            notifyListeners();
          }
          break;
        case RTCIceConnectionState.RTCIceConnectionStateConnected:
        case RTCIceConnectionState.RTCIceConnectionStateCompleted:
          _markCallConnected();
          break;
        case RTCIceConnectionState.RTCIceConnectionStateDisconnected:
          if (_phase == KiuqCallPhase.connected) {
            _phase = KiuqCallPhase.reconnecting;
            notifyListeners();
            _reconnectTimer?.cancel();
            _reconnectTimer = Timer(const Duration(seconds: 4), () {
              if (_peerConnection == pc && isActive) {
                _attemptIceRestart(activeCallId);
              }
            });
          }
          break;
        case RTCIceConnectionState.RTCIceConnectionStateFailed:
          _attemptIceRestart(activeCallId);
          break;
        default:
          break;
      }
    };

    pc.onConnectionState = (RTCPeerConnectionState state) {
      if (_peerConnection != pc) return;
      if (state == RTCPeerConnectionState.RTCPeerConnectionStateConnected) {
        _markCallConnected();
      }
    };
  }

  Future<void> _createAndSendOffer(
    int activeCallId, {
    required bool iceRestart,
  }) async {
    final pc = _peerConnection;
    if (pc == null) return;

    final offer = await pc.createOffer(<String, dynamic>{
      'offerToReceiveAudio': 1,
      'offerToReceiveVideo': 1,
      if (iceRestart) 'iceRestart': true,
    });
    await pc.setLocalDescription(offer);
    await _postJson('/chat/calls/$activeCallId/signal', <String, dynamic>{
      'type': 'offer',
      'payload': <String, dynamic>{
        'sdp': offer.sdp,
        'type': offer.type ?? 'offer',
        'video_enabled': _isCameraOn,
      },
    });
  }

  Future<void> _handleRemoteOffer(
    int activeCallId,
    Map<String, dynamic> payload,
  ) async {
    final sdp = (payload['sdp'] ?? '').toString();
    if (sdp.isEmpty) return;

    if (payload['video_enabled'] == true) {
      _isVideo = true;
    }

    // Wait until user taps Accept before setting remote description & answering
    if (!_isCaller && _phase == KiuqCallPhase.incomingRinging) {
      return;
    }

    if (_peerConnection == null) {
      await _createPeerConnection(activeCallId);
    }
    final pc = _peerConnection;
    if (pc == null) return;

    await pc.setRemoteDescription(
      RTCSessionDescription(sdp, (payload['type'] ?? 'offer').toString()),
    );
    await _flushPendingCandidates();

    final answer = await pc.createAnswer();
    await pc.setLocalDescription(answer);
    await _postJson('/chat/calls/$activeCallId/signal', <String, dynamic>{
      'type': 'answer',
      'payload': <String, dynamic>{
        'sdp': answer.sdp,
        'type': answer.type ?? 'answer',
        'video_enabled': _isCameraOn,
      },
    });
  }

  Future<void> _handleRemoteAnswer(Map<String, dynamic> payload) async {
    final pc = _peerConnection;
    final sdp = (payload['sdp'] ?? '').toString();
    if (pc == null || sdp.isEmpty) return;

    if (payload['video_enabled'] == true) {
      _remoteVideoActive = true;
      _isVideo = true;
    }

    await pc.setRemoteDescription(
      RTCSessionDescription(sdp, (payload['type'] ?? 'answer').toString()),
    );
    await _flushPendingCandidates();
  }

  Future<void> _handleRemoteCandidate(Map<String, dynamic> payload) async {
    final raw = payload['candidate'] is Map
        ? Map<String, dynamic>.from(payload['candidate'] as Map)
        : payload;
    final candStr = (raw['candidate'] ?? '').toString();
    if (candStr.isEmpty) return;

    final candidate = RTCIceCandidate(
      candStr,
      raw['sdpMid']?.toString(),
      int.tryParse('${raw['sdpMLineIndex'] ?? 0}'),
    );

    final pc = _peerConnection;
    final remoteDesc = await pc?.getRemoteDescription();
    if (pc == null || remoteDesc == null) {
      _pendingCandidates.add(candidate);
      return;
    }

    try {
      await pc.addCandidate(candidate);
    } catch (_) {}
  }

  Future<void> _flushPendingCandidates() async {
    final pc = _peerConnection;
    if (pc == null) return;
    while (_pendingCandidates.isNotEmpty) {
      final next = _pendingCandidates.removeAt(0);
      try {
        await pc.addCandidate(next);
      } catch (_) {}
    }
  }

  void _markCallConnected() {
    _ringTimeoutTimer?.cancel();
    _reconnectTimer?.cancel();
    if (_phase != KiuqCallPhase.connected) {
      _phase = KiuqCallPhase.connected;
      unawaited(nativeBridge.reportCallConnected(_callId));
      _durationTimer?.cancel();
      _durationTimer = Timer.periodic(const Duration(seconds: 1), (_) {
        _durationSeconds += 1;
        notifyListeners();
      });
      notifyListeners();
    }
  }

  Future<void> _attemptIceRestart(int activeCallId) async {
    if (!_iceRestartAttempted && _isCaller && _peerConnection != null) {
      _iceRestartAttempted = true;
      _phase = KiuqCallPhase.reconnecting;
      notifyListeners();
      try {
        await _fetchIceConfig();
        await _createAndSendOffer(activeCallId, iceRestart: true);
        return;
      } catch (_) {}
    }
    await endCall(reason: 'failed');
  }

  // ─── In-Call Controls ─────────────────────────────────────────────────────

  void toggleMute({bool? forceMuted}) {
    _isMuted = forceMuted ?? !_isMuted;
    final stream = _localStream;
    if (stream != null) {
      for (final t in stream.getAudioTracks()) {
        t.enabled = !_isMuted;
      }
    }
    if (_callId > 0) {
      unawaited(
        _postJson('/chat/calls/$_callId/signal', <String, dynamic>{
          'type': _isMuted ? 'mute' : 'unmute',
          'payload': <String, dynamic>{'muted': _isMuted},
        }),
      );
    }
    notifyListeners();
  }

  Future<void> toggleCamera() async {
    final stream = _localStream;
    if (stream != null && stream.getVideoTracks().isNotEmpty) {
      _isCameraOn = !_isCameraOn;
      for (final t in stream.getVideoTracks()) {
        t.enabled = _isCameraOn;
      }
      if (_isCameraOn) _isVideo = true;
      if (_callId > 0) {
        unawaited(
          _postJson('/chat/calls/$_callId/signal', <String, dynamic>{
            'type': _isCameraOn ? 'video_on' : 'video_off',
            'payload': <String, dynamic>{'video': _isCameraOn},
          }),
        );
      }
      notifyListeners();
      return;
    }

    if (!enableNativeWebRtc || kIsWeb) return;
    final camAllowed = await nativeBridge.requestMediaPermission('camera');
    if (!camAllowed) return;

    try {
      final camStream = await navigator.mediaDevices.getUserMedia(
        <String, dynamic>{
          'audio': false,
          'video': <String, dynamic>{
            'facingMode': _isFrontCamera ? 'user' : 'environment',
            'width': <String, dynamic>{'ideal': 1280},
            'height': <String, dynamic>{'ideal': 720},
          },
        },
      );
      if (camStream.getVideoTracks().isEmpty) return;
      final track = camStream.getVideoTracks().first;
      await _localStream?.addTrack(track);
      if (_peerConnection != null && _localStream != null) {
        await _peerConnection!.addTrack(track, _localStream!);
        if (_callId > 0 && _phase == KiuqCallPhase.connected) {
          await _createAndSendOffer(_callId, iceRestart: false);
        }
      }
      if (_renderersInitialized) {
        localRenderer.srcObject = _localStream;
      }
      _isCameraOn = true;
      _isVideo = true;
      if (_callId > 0) {
        unawaited(
          _postJson('/chat/calls/$_callId/signal', <String, dynamic>{
            'type': 'video_on',
            'payload': <String, dynamic>{'video': true},
          }),
        );
      }
      notifyListeners();
    } catch (_) {}
  }

  Future<void> switchCamera() async {
    final stream = _localStream;
    if (stream == null || stream.getVideoTracks().isEmpty) return;
    try {
      await Helper.switchCamera(stream.getVideoTracks().first);
      _isFrontCamera = !_isFrontCamera;
      notifyListeners();
    } catch (_) {}
  }

  Future<void> toggleSpeaker() async {
    _speakerOn = !_speakerOn;
    notifyListeners();
    try {
      await Helper.setSpeakerphoneOn(_speakerOn);
    } catch (_) {}
    await nativeBridge.setSpeakerphone(_speakerOn);
  }

  void toggleMinimized() {
    _minimized = !_minimized;
    notifyListeners();
  }

  void _handleCallKitAction(KiuqCallKitActionEvent event) {
    if (!isActive) return;
    if (event.action == 'accept' && _phase == KiuqCallPhase.incomingRinging) {
      unawaited(acceptIncomingCall(withVideo: _isVideo));
    } else if (event.action == 'end') {
      if (_phase == KiuqCallPhase.incomingRinging && !_isCaller) {
        unawaited(rejectIncomingCall());
      } else {
        unawaited(endCall());
      }
    } else if (event.action == 'mute' && event.muted != null) {
      toggleMute(forceMuted: event.muted);
    }
  }

  // ─── Hybrid Signal Polling & Cleanup ──────────────────────────────────────

  void _startSignalPolling(int activeCallId) {
    _signalPollTimer?.cancel();
    _signalPollTimer = Timer.periodic(const Duration(milliseconds: 950), (_) {
      if (!isActive || _callId != activeCallId) {
        _signalPollTimer?.cancel();
        return;
      }
      unawaited(_pollSignalsOnce(activeCallId));
    });
  }

  Future<void> _pollSignalsOnce(
    int activeCallId, {
    bool resetCursor = false,
  }) async {
    try {
      final after = resetCursor ? 0 : _lastSignalId;
      final res = await _getJson(
        '/chat/calls/$activeCallId/signals?after_id=$after',
      );
      if (res['success'] != true) return;

      final status = (res['call_status'] ?? '').toString();
      if (const <String>[
        'ended',
        'declined',
        'missed',
        'cancelled',
        'busy',
      ].contains(status)) {
        onNotifyWebCallEnded?.call();
        await _cleanupCall();
        return;
      }

      if (status == 'ringing' &&
          _isCaller &&
          _phase == KiuqCallPhase.outgoingCalling) {
        _phase = KiuqCallPhase.outgoingRinging;
        notifyListeners();
      }

      if (res['signals'] is List) {
        for (final item in res['signals'] as List) {
          if (item is! Map) continue;
          final sig = Map<String, dynamic>.from(item);
          final id = int.tryParse('${sig['id'] ?? 0}') ?? 0;
          if (id > _lastSignalId) _lastSignalId = id;
          final key = 'sig:$activeCallId:$id';
          if (_processedKeys.contains(key) && !resetCursor) continue;
          _processedKeys.add(key);

          final type = (sig['type'] ?? '').toString();
          final sigPayload = sig['payload'] is Map
              ? Map<String, dynamic>.from(sig['payload'] as Map)
              : <String, dynamic>{};

          if (type == 'offer' || type == 'renegotiate') {
            await _handleRemoteOffer(activeCallId, sigPayload);
          } else if (type == 'answer') {
            await _handleRemoteAnswer(sigPayload);
          } else if (type == 'ice_candidate' || type == 'ice-candidate') {
            await _handleRemoteCandidate(sigPayload);
          } else if (type == 'video_on') {
            _remoteVideoActive = true;
            _isVideo = true;
            notifyListeners();
          } else if (type == 'video_off') {
            _remoteVideoActive = false;
            notifyListeners();
          }
        }
      }
    } catch (_) {}
  }

  void _armRingTimeout(int activeCallId) {
    _ringTimeoutTimer?.cancel();
    _ringTimeoutTimer = Timer(const Duration(seconds: 45), () async {
      if (isActive &&
          _callId == activeCallId &&
          (_phase == KiuqCallPhase.outgoingCalling ||
              _phase == KiuqCallPhase.outgoingRinging ||
              _phase == KiuqCallPhase.incomingRinging)) {
        unawaited(
          _postJson('/chat/calls/$activeCallId/timeout', const <String, dynamic>{}),
        );
        await _cleanupCall();
      }
    });
  }

  Future<void> _fetchIceConfig() async {
    try {
      final data = await _getJson('/chat/calls/ice-config');
      if (data['iceServers'] is List) {
        _parseIceServers(data['iceServers'] as List);
      }
    } catch (_) {}
  }

  void _parseIceServers(List<dynamic> rawList) {
    final parsed = <Map<String, dynamic>>[];
    for (final entry in rawList) {
      if (entry is Map) {
        parsed.add(Map<String, dynamic>.from(entry));
      }
    }
    if (parsed.isNotEmpty) {
      _iceServers = parsed;
    }
  }

  Future<void> _cleanupCall() async {
    final prevId = _callId;
    _durationTimer?.cancel();
    _durationTimer = null;
    _signalPollTimer?.cancel();
    _signalPollTimer = null;
    _ringTimeoutTimer?.cancel();
    _ringTimeoutTimer = null;
    _reconnectTimer?.cancel();
    _reconnectTimer = null;

    if (prevId > 0) {
      unawaited(nativeBridge.endCallKit(prevId));
    }
    unawaited(nativeBridge.deactivateCallAudio());

    if (_renderersInitialized) {
      localRenderer.srcObject = null;
      remoteRenderer.srcObject = null;
    }

    final local = _localStream;
    _localStream = null;
    if (local != null) {
      for (final t in local.getTracks()) {
        try {
          await t.stop();
        } catch (_) {}
      }
      try {
        await local.dispose();
      } catch (_) {}
    }

    final remote = _remoteStream;
    _remoteStream = null;
    if (remote != null) {
      for (final t in remote.getTracks()) {
        try {
          await t.stop();
        } catch (_) {}
      }
      try {
        await remote.dispose();
      } catch (_) {}
    }

    final pc = _peerConnection;
    _peerConnection = null;
    if (pc != null) {
      try {
        await pc.close();
      } catch (_) {}
    }

    _pendingCandidates.clear();
    _phase = KiuqCallPhase.idle;
    _minimized = false;
    _callId = 0;
    _durationSeconds = 0;
    _remoteVideoActive = false;
    _remoteMuted = false;
    notifyListeners();
  }

  // ─── Authenticated HTTP Helpers (reuses WKWebView / Android WebView cookies) ─

  Future<Map<String, String>> _buildAuthHeaders({required bool jsonBody}) async {
    final headers = <String, String>{
      HttpHeaders.acceptHeader: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    };
    if (jsonBody) {
      headers[HttpHeaders.contentTypeHeader] = 'application/json';
    }
    if (_socketId.isNotEmpty) {
      headers['X-Socket-ID'] = _socketId;
    }

    var xsrfFromCookie = '';
    if (!kIsWeb) {
      try {
        final cookies = await CookieManager.instance().getCookies(
          url: WebUri(appBaseUrl),
        );
        final cookiePairs = <String>[];
        for (final c in cookies) {
          cookiePairs.add('${c.name}=${c.value}');
          if (c.name == 'XSRF-TOKEN' && c.value.toString().isNotEmpty) {
            xsrfFromCookie = Uri.decodeComponent(c.value.toString());
          }
        }
        if (cookiePairs.isNotEmpty) {
          headers[HttpHeaders.cookieHeader] = cookiePairs.join('; ');
        }
      } catch (_) {}
    }

    if (_csrfToken.isNotEmpty) {
      headers['X-CSRF-TOKEN'] = _csrfToken;
    } else if (xsrfFromCookie.isNotEmpty) {
      headers['X-XSRF-TOKEN'] = xsrfFromCookie;
    }
    return headers;
  }

  Future<Map<String, dynamic>> _postJson(
    String path,
    Map<String, dynamic> body,
  ) async {
    if (webHttpExecutor != null) {
      final webRes = await webHttpExecutor!('POST', path, body);
      if (webRes != null) {
        final status = int.tryParse('${webRes['__httpStatus'] ?? 200}') ?? 200;
        if (status >= 400 || webRes['success'] == false) {
          throw Exception(
            webRes['error'] ?? webRes['message'] ?? 'HTTP $status',
          );
        }
        return webRes;
      }
    }

    final uri = Uri.parse(KiuqUrlPolicy.resolveInternalUrl(path));
    final client = HttpClient()..connectionTimeout = const Duration(seconds: 12);
    try {
      final headers = await _buildAuthHeaders(jsonBody: true);
      final req = await client.postUrl(uri);
      headers.forEach(req.headers.set);
      req.add(utf8.encode(jsonEncode(body)));
      final res = await req.close().timeout(const Duration(seconds: 15));
      final raw = await utf8.decoder.bind(res).join();
      final decoded = jsonDecode(raw);
      final map = decoded is Map
          ? Map<String, dynamic>.from(decoded)
          : <String, dynamic>{};
      if (res.statusCode >= 400 || map['success'] == false) {
        throw Exception(map['error'] ?? map['message'] ?? 'HTTP ${res.statusCode}');
      }
      return map;
    } finally {
      client.close(force: true);
    }
  }

  Future<Map<String, dynamic>> _getJson(String path) async {
    if (webHttpExecutor != null) {
      final webRes = await webHttpExecutor!('GET', path, null);
      if (webRes != null) {
        return webRes;
      }
    }

    final uri = Uri.parse(KiuqUrlPolicy.resolveInternalUrl(path));
    final client = HttpClient()..connectionTimeout = const Duration(seconds: 12);
    try {
      final headers = await _buildAuthHeaders(jsonBody: false);
      final req = await client.getUrl(uri);
      headers.forEach(req.headers.set);
      final res = await req.close().timeout(const Duration(seconds: 15));
      final raw = await utf8.decoder.bind(res).join();
      final decoded = jsonDecode(raw);
      return decoded is Map
          ? Map<String, dynamic>.from(decoded)
          : <String, dynamic>{};
    } finally {
      client.close(force: true);
    }
  }

  @override
  void dispose() {
    _durationTimer?.cancel();
    _signalPollTimer?.cancel();
    _ringTimeoutTimer?.cancel();
    _reconnectTimer?.cancel();
    if (_renderersInitialized) {
      localRenderer.dispose();
      remoteRenderer.dispose();
    }
    super.dispose();
  }
}
