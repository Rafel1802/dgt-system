import 'dart:ui';

import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_webrtc/flutter_webrtc.dart';

import '../models/shell_state.dart';
import '../services/webrtc_call_service.dart';
import '../theme/liquid_glass_theme.dart';
import 'liquid_glass_surface.dart';

/// Native Apple iOS 26 Liquid Glass Voice & Video Call Overlay:
/// - Incoming Call Sheet (Accept Voice, Accept Video, Decline)
/// - Full-Screen Voice & HD Video Call View (with draggable Local PiP preview)
/// - Minimized Floating Liquid Glass Call Pill
class LiquidGlassCallOverlay extends StatefulWidget {
  const LiquidGlassCallOverlay({
    super.key,
    required this.controller,
    required this.palette,
  });

  final WebRtcCallController controller;
  final LiquidGlassPalette palette;

  @override
  State<LiquidGlassCallOverlay> createState() => _LiquidGlassCallOverlayState();
}

class _LiquidGlassCallOverlayState extends State<LiquidGlassCallOverlay> {
  Offset _pipOffset = const Offset(16, 128);

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: widget.controller,
      builder: (context, _) {
        final c = widget.controller;
        if (!c.isActive) {
          return const SizedBox.shrink();
        }

        if (c.isMinimized) {
          return _buildMinimizedPill(context, c);
        }

        if (c.phase == KiuqCallPhase.incomingRinging && !c.isCaller) {
          return _buildIncomingCallScreen(context, c);
        }

        return _buildActiveOrOutgoingCallScreen(context, c);
      },
    );
  }

  Widget _buildIncomingCallScreen(
    BuildContext context,
    WebRtcCallController c,
  ) {
    final safePadding = MediaQuery.paddingOf(context);

    return Positioned.fill(
      key: const ValueKey<String>('kiuq_native_incoming_call_screen'),
      child: Material(
        color: Colors.black.withValues(alpha: 0.72),
        child: BackdropFilter(
          filter: ImageFilter.blur(sigmaX: 28, sigmaY: 28),
          child: Padding(
            padding: EdgeInsets.fromLTRB(
              24,
              safePadding.top + 24,
              24,
              safePadding.bottom + 32,
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: <Widget>[
                LiquidGlassSurface(
                  palette: LiquidGlassPalette.of(KiuqThemeMode.dark),
                  borderRadius: BorderRadius.circular(999),
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 8,
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: <Widget>[
                      Container(
                        width: 8,
                        height: 8,
                        decoration: const BoxDecoration(
                          color: Color(0xFF34D399),
                          shape: BoxShape.circle,
                        ),
                      ),
                      const SizedBox(width: 8),
                      Text(
                        c.isVideo
                            ? 'Incoming Video Call'
                            : 'Incoming Voice Call',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
                Column(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    _buildContactAvatar(c, radius: 54),
                    const SizedBox(height: 20),
                    Text(
                      c.contactName,
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 28,
                        fontWeight: FontWeight.w700,
                        letterSpacing: -0.5,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      c.isVideo ? 'KIUQ HD Video' : 'KIUQ HD Audio',
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.72),
                        fontSize: 15,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                  children: <Widget>[
                    _CallRoundActionButton(
                      key: const ValueKey<String>('kiuq_call_decline_btn'),
                      icon: CupertinoIcons.phone_down_fill,
                      label: 'Decline',
                      backgroundColor: const Color(0xFFE11D48),
                      onTap: c.rejectIncomingCall,
                    ),
                    _CallRoundActionButton(
                      key: const ValueKey<String>('kiuq_call_accept_voice_btn'),
                      icon: CupertinoIcons.phone_fill,
                      label: c.isVideo ? 'Audio Only' : 'Accept',
                      backgroundColor: const Color(0xFF10B981),
                      onTap: () => c.acceptIncomingCall(withVideo: false),
                    ),
                    if (c.isVideo)
                      _CallRoundActionButton(
                        key: const ValueKey<String>(
                          'kiuq_call_accept_video_btn',
                        ),
                        icon: CupertinoIcons.videocam_fill,
                        label: 'Video',
                        backgroundColor: const Color(0xFF4F46E5),
                        onTap: () => c.acceptIncomingCall(withVideo: true),
                      ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildActiveOrOutgoingCallScreen(
    BuildContext context,
    WebRtcCallController c,
  ) {
    final safePadding = MediaQuery.paddingOf(context);
    final size = MediaQuery.sizeOf(context);
    final showRemoteVideo = c.remoteVideoActive && c.renderersReady;
    final showLocalFull =
        !showRemoteVideo && c.isCameraOn && c.renderersReady;

    final statusLabel = switch (c.phase) {
      KiuqCallPhase.outgoingCalling =>
        c.isVideo ? 'Video Calling...' : 'Calling...',
      KiuqCallPhase.outgoingRinging => 'Ringing...',
      KiuqCallPhase.connecting => 'Connecting...',
      KiuqCallPhase.connected => c.formattedDuration,
      KiuqCallPhase.reconnecting => 'Reconnecting...',
      KiuqCallPhase.permissionDenied => 'Permission Required',
      _ => 'Call',
    };

    return Positioned.fill(
      key: const ValueKey<String>('kiuq_native_active_call_screen'),
      child: Material(
        color: const Color(0xFF060B16),
        child: Stack(
          children: <Widget>[
            // 1. Remote HD Video Stream or Ambient Glass Backdrop
            if (showRemoteVideo)
              Positioned.fill(
                child: RTCVideoView(
                  c.remoteRenderer,
                  objectFit: RTCVideoViewObjectFit.RTCVideoViewObjectFitCover,
                ),
              )
            else if (showLocalFull)
              Positioned.fill(
                child: RTCVideoView(
                  c.localRenderer,
                  mirror: c.isFrontCamera,
                  objectFit: RTCVideoViewObjectFit.RTCVideoViewObjectFitCover,
                ),
              )
            else
              Positioned.fill(
                child: DecoratedBox(
                  decoration: const BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: <Color>[
                        Color(0xFF0F172A),
                        Color(0xFF1E1B4B),
                        Color(0xFF020617),
                      ],
                    ),
                  ),
                  child: BackdropFilter(
                    filter: ImageFilter.blur(sigmaX: 24, sigmaY: 24),
                    child: const SizedBox.expand(),
                  ),
                ),
              ),

            // Subtle readability gradient over video
            if (showRemoteVideo || showLocalFull)
              const Positioned.fill(
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: <Color>[
                        Color(0xAA020617),
                        Colors.transparent,
                        Color(0xCC020617),
                      ],
                    ),
                  ),
                ),
              ),

            // 2. Top Safe-Area Header (Minimize + Status Pill)
            Positioned(
              top: safePadding.top + 10,
              left: 16,
              right: 16,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: <Widget>[
                  IconButton(
                    key: const ValueKey<String>('kiuq_call_minimize_btn'),
                    onPressed: c.toggleMinimized,
                    style: IconButton.styleFrom(
                      backgroundColor: Colors.white.withValues(alpha: 0.14),
                    ),
                    icon: const Icon(
                      CupertinoIcons.chevron_down,
                      color: Colors.white,
                      size: 20,
                    ),
                  ),
                  LiquidGlassSurface(
                    palette: LiquidGlassPalette.of(KiuqThemeMode.dark),
                    borderRadius: BorderRadius.circular(999),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 14,
                      vertical: 7,
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        Icon(
                          c.isVideo
                              ? CupertinoIcons.videocam_fill
                              : CupertinoIcons.lock_shield_fill,
                          size: 14,
                          color: const Color(0xFF34D399),
                        ),
                        const SizedBox(width: 6),
                        Text(
                          statusLabel,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 40),
                ],
              ),
            ),

            // 3. Center Identity or Permission Guidance
            if (!showRemoteVideo && !showLocalFull)
              Center(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 28),
                  child: c.phase == KiuqCallPhase.permissionDenied
                      ? LiquidGlassSurface(
                          palette: LiquidGlassPalette.of(KiuqThemeMode.dark),
                          borderRadius: BorderRadius.circular(28),
                          padding: const EdgeInsets.all(24),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: <Widget>[
                              const Icon(
                                CupertinoIcons.mic_slash_fill,
                                color: Color(0xFFFB7185),
                                size: 36,
                              ),
                              const SizedBox(height: 12),
                              const Text(
                                'Microphone / Camera Access Needed',
                                textAlign: TextAlign.center,
                                style: TextStyle(
                                  color: Colors.white,
                                  fontSize: 17,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: 8),
                              Text(
                                c.errorMessage ??
                                    'Please enable Microphone and Camera permissions in Settings.',
                                textAlign: TextAlign.center,
                                style: TextStyle(
                                  color: Colors.white.withValues(alpha: 0.78),
                                  fontSize: 13,
                                ),
                              ),
                              const SizedBox(height: 18),
                              FilledButton(
                                onPressed: c.endCall,
                                style: FilledButton.styleFrom(
                                  backgroundColor: Colors.white.withValues(
                                    alpha: 0.18,
                                  ),
                                ),
                                child: const Text('Close'),
                              ),
                            ],
                          ),
                        )
                      : Column(
                          mainAxisSize: MainAxisSize.min,
                          children: <Widget>[
                            _buildContactAvatar(c, radius: 52),
                            const SizedBox(height: 18),
                            Text(
                              c.contactName,
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 26,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              statusLabel,
                              style: TextStyle(
                                color: Colors.white.withValues(alpha: 0.75),
                                fontSize: 15,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            if (c.remoteMuted) ...<Widget>[
                              const SizedBox(height: 10),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 10,
                                  vertical: 4,
                                ),
                                decoration: BoxDecoration(
                                  color: Colors.amber.withValues(alpha: 0.22),
                                  borderRadius: BorderRadius.circular(999),
                                ),
                                child: const Text(
                                  'Contact muted microphone',
                                  style: TextStyle(
                                    color: Color(0xFFFDE68A),
                                    fontSize: 11,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ),
                            ],
                          ],
                        ),
                ),
              ),

            // 4. Draggable Local Camera Picture-in-Picture (PiP)
            if (showRemoteVideo && c.isCameraOn && c.renderersReady)
              Positioned(
                right: _pipOffset.dx.clamp(12.0, size.width - 124.0),
                bottom: _pipOffset.dy.clamp(
                  safePadding.bottom + 110.0,
                  size.height - safePadding.top - 220.0,
                ),
                child: GestureDetector(
                  onPanUpdate: (details) {
                    setState(() {
                      _pipOffset = Offset(
                        _pipOffset.dx - details.delta.dx,
                        _pipOffset.dy - details.delta.dy,
                      );
                    });
                  },
                  child: Container(
                    width: 108,
                    height: 152,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(
                        color: Colors.white.withValues(alpha: 0.38),
                        width: 1.5,
                      ),
                      boxShadow: const <BoxShadow>[
                        BoxShadow(
                          color: Color(0x66000000),
                          blurRadius: 20,
                          offset: Offset(0, 10),
                        ),
                      ],
                    ),
                    clipBehavior: Clip.antiAlias,
                    child: RTCVideoView(
                      c.localRenderer,
                      mirror: c.isFrontCamera,
                      objectFit:
                          RTCVideoViewObjectFit.RTCVideoViewObjectFitCover,
                    ),
                  ),
                ),
              ),

            // 5. Bottom Floating Apple iOS 26 Liquid Glass Call Control Capsule
            if (c.phase != KiuqCallPhase.permissionDenied)
              Positioned(
                left: 20,
                right: 20,
                bottom: safePadding.bottom + 20,
                child: Center(
                  child: LiquidGlassSurface(
                    palette: LiquidGlassPalette.of(KiuqThemeMode.dark),
                    borderRadius: BorderRadius.circular(999),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 12,
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        _CapsuleControlIcon(
                          key: const ValueKey<String>('kiuq_call_mute_btn'),
                          icon: c.isMuted
                              ? CupertinoIcons.mic_slash_fill
                              : CupertinoIcons.mic_fill,
                          isActive: c.isMuted,
                          activeColor: const Color(0xFFF59E0B),
                          onTap: c.toggleMute,
                        ),
                        const SizedBox(width: 12),
                        _CapsuleControlIcon(
                          key: const ValueKey<String>('kiuq_call_camera_btn'),
                          icon: c.isCameraOn
                              ? CupertinoIcons.videocam_fill
                              : CupertinoIcons.videocam,
                          isActive: c.isCameraOn,
                          activeColor: const Color(0xFF4F46E5),
                          onTap: c.toggleCamera,
                        ),
                        if (c.isCameraOn) ...<Widget>[
                          const SizedBox(width: 12),
                          _CapsuleControlIcon(
                            key: const ValueKey<String>(
                              'kiuq_call_switch_cam_btn',
                            ),
                            icon: CupertinoIcons.switch_camera_solid,
                            isActive: false,
                            onTap: c.switchCamera,
                          ),
                        ],
                        const SizedBox(width: 12),
                        _CapsuleControlIcon(
                          key: const ValueKey<String>('kiuq_call_speaker_btn'),
                          icon: c.speakerOn
                              ? CupertinoIcons.speaker_3_fill
                              : CupertinoIcons.speaker_1_fill,
                          isActive: c.speakerOn,
                          activeColor: Colors.white.withValues(alpha: 0.32),
                          onTap: c.toggleSpeaker,
                        ),
                        const SizedBox(width: 14),
                        GestureDetector(
                          key: const ValueKey<String>('kiuq_call_end_btn'),
                          onTap: c.endCall,
                          child: Container(
                            width: 56,
                            height: 48,
                            decoration: BoxDecoration(
                              color: const Color(0xFFE11D48),
                              borderRadius: BorderRadius.circular(999),
                              boxShadow: const <BoxShadow>[
                                BoxShadow(
                                  color: Color(0x66E11D48),
                                  blurRadius: 14,
                                  offset: Offset(0, 4),
                                ),
                              ],
                            ),
                            child: const Icon(
                              CupertinoIcons.phone_down_fill,
                              color: Colors.white,
                              size: 22,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildMinimizedPill(BuildContext context, WebRtcCallController c) {
    final safeTop = MediaQuery.paddingOf(context).top;

    return Positioned(
      key: const ValueKey<String>('kiuq_native_minimized_call_pill'),
      top: safeTop + 10,
      right: 14,
      child: Material(
        color: Colors.transparent,
        child: LiquidGlassSurface(
          palette: LiquidGlassPalette.of(KiuqThemeMode.dark),
          borderRadius: BorderRadius.circular(999),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              GestureDetector(
                onTap: c.toggleMinimized,
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    Container(
                      width: 8,
                      height: 8,
                      decoration: const BoxDecoration(
                        color: Color(0xFF34D399),
                        shape: BoxShape.circle,
                      ),
                    ),
                    const SizedBox(width: 8),
                    ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 110),
                      child: Text(
                        c.contactName,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      c.phase == KiuqCallPhase.connected
                          ? c.formattedDuration
                          : 'Calling',
                      style: const TextStyle(
                        color: Color(0xFF6EE7B7),
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              GestureDetector(
                onTap: c.endCall,
                child: Container(
                  width: 28,
                  height: 28,
                  decoration: const BoxDecoration(
                    color: Color(0xFFE11D48),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    CupertinoIcons.phone_down_fill,
                    color: Colors.white,
                    size: 14,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildContactAvatar(WebRtcCallController c, {required double radius}) {
    final initials = c.contactName.trim().isNotEmpty
        ? c.contactName
              .trim()
              .split(RegExp(r'\s+'))
              .take(2)
              .map((w) => w.isNotEmpty ? w[0].toUpperCase() : '')
              .join()
        : 'U';

    return Container(
      width: radius * 2,
      height: radius * 2,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: const LinearGradient(
          colors: <Color>[Color(0xFF4F46E5), Color(0xFF0EA5E9)],
        ),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.3),
          width: 3,
        ),
        boxShadow: const <BoxShadow>[
          BoxShadow(
            color: Color(0x55000000),
            blurRadius: 24,
            offset: Offset(0, 10),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: c.contactAvatarUrl.startsWith('http')
          ? Image.network(
              c.contactAvatarUrl,
              fit: BoxFit.cover,
              errorBuilder: (_, _, _) => Center(
                child: Text(
                  initials,
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: radius * 0.65,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            )
          : Center(
              child: Text(
                initials,
                style: TextStyle(
                  color: Colors.white,
                  fontSize: radius * 0.65,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
    );
  }
}

class _CallRoundActionButton extends StatelessWidget {
  const _CallRoundActionButton({
    super.key,
    required this.icon,
    required this.label,
    required this.backgroundColor,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final Color backgroundColor;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        GestureDetector(
          onTap: onTap,
          child: Container(
            width: 64,
            height: 64,
            decoration: BoxDecoration(
              color: backgroundColor,
              shape: BoxShape.circle,
              boxShadow: <BoxShadow>[
                BoxShadow(
                  color: backgroundColor.withValues(alpha: 0.45),
                  blurRadius: 18,
                  offset: const Offset(0, 6),
                ),
              ],
            ),
            child: Icon(icon, color: Colors.white, size: 28),
          ),
        ),
        const SizedBox(height: 8),
        Text(
          label,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 12,
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }
}

class _CapsuleControlIcon extends StatelessWidget {
  const _CapsuleControlIcon({
    super.key,
    required this.icon,
    required this.isActive,
    this.activeColor,
    required this.onTap,
  });

  final IconData icon;
  final bool isActive;
  final Color? activeColor;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 46,
        height: 46,
        decoration: BoxDecoration(
          color: isActive
              ? (activeColor ?? const Color(0xFF2563EB))
              : Colors.white.withValues(alpha: 0.14),
          shape: BoxShape.circle,
        ),
        child: Icon(icon, color: Colors.white, size: 21),
      ),
    );
  }
}
