import 'dart:math' as math;

import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

import '../theme/liquid_glass_theme.dart';
import 'liquid_glass_surface.dart';

/// Subtle Dynamic-Island-inspired top Liquid Glass progress & activity pill.
class LiquidGlassTopActivityPill extends StatelessWidget {
  const LiquidGlassTopActivityPill({
    super.key,
    required this.palette,
    required this.isVisible,
    this.label,
    this.progress,
  });

  final LiquidGlassPalette palette;
  final bool isVisible;
  final String? label;
  final double? progress;

  @override
  Widget build(BuildContext context) {
    final topSafe = math.max(
      MediaQuery.viewPaddingOf(context).top,
      MediaQuery.paddingOf(context).top,
    );

    return IgnorePointer(
      child: AnimatedSlide(
        offset: isVisible ? Offset.zero : const Offset(0, -1.8),
        duration: LiquidGlassMotion.standard,
        curve: LiquidGlassMotion.iosFluidCurve,
        child: AnimatedOpacity(
          opacity: isVisible ? 1.0 : 0.0,
          duration: LiquidGlassMotion.fast,
          child: Align(
            alignment: Alignment.topCenter,
            child: Padding(
              padding: EdgeInsets.only(top: math.max(topSafe + 6, 12)),
              child: LiquidGlassSurface(
                palette: palette,
                borderRadius: BorderRadius.circular(999),
                padding: const EdgeInsets.symmetric(
                  horizontal: 14,
                  vertical: 7,
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    SizedBox(
                      width: 14,
                      height: 14,
                      child: CupertinoActivityIndicator(
                        radius: 7,
                        animating: isVisible,
                        color: palette.primaryAccent,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      label ??
                          (progress != null && progress! > 0 && progress! < 1
                              ? 'Loading ${(progress! * 100).round()}%'
                              : 'Syncing...'),
                      style: TextStyle(
                        fontSize: 11.5,
                        fontWeight: FontWeight.w700,
                        color: palette.textPrimary,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Non-destructive floating Liquid Glass offline banner displayed when connectivity
/// drops mid-session—preserving the current WebView page and unsaved form input.
class LiquidGlassOfflineBanner extends StatelessWidget {
  const LiquidGlassOfflineBanner({
    super.key,
    required this.palette,
    required this.isVisible,
    required this.isRetrying,
    required this.onRetry,
    this.detailMessage,
  });

  final LiquidGlassPalette palette;
  final bool isVisible;
  final bool isRetrying;
  final VoidCallback onRetry;
  final String? detailMessage;

  @override
  Widget build(BuildContext context) {
    final topSafe = math.max(
      MediaQuery.viewPaddingOf(context).top,
      MediaQuery.paddingOf(context).top,
    );

    return IgnorePointer(
      ignoring: !isVisible,
      child: AnimatedSlide(
        offset: isVisible ? Offset.zero : const Offset(0, -1.6),
        duration: LiquidGlassMotion.standard,
        curve: LiquidGlassMotion.iosFluidCurve,
        child: AnimatedOpacity(
          opacity: isVisible ? 1.0 : 0.0,
          duration: LiquidGlassMotion.fast,
          child: Align(
            alignment: Alignment.topCenter,
            child: Padding(
              padding: EdgeInsets.fromLTRB(
                14,
                math.max(topSafe + 8, 14),
                14,
                0,
              ),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 460),
                child: LiquidGlassSurface(
                  key: const ValueKey<String>('kiuq_offline_banner'),
                  palette: palette,
                  borderRadius: BorderRadius.circular(22),
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 11,
                  ),
                  child: Row(
                    children: <Widget>[
                      Container(
                        width: 34,
                        height: 34,
                        decoration: BoxDecoration(
                          color: const Color(0xFFF59E0B).withValues(alpha: 0.16),
                          shape: BoxShape.circle,
                        ),
                        alignment: Alignment.center,
                        child: const Icon(
                          CupertinoIcons.wifi_slash,
                          size: 17,
                          color: Color(0xFFF59E0B),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Text(
                              'Connection Unavailable',
                              style: TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w800,
                                color: palette.textPrimary,
                              ),
                            ),
                            Text(
                              detailMessage ??
                                  'Current page preserved. Will reconnect automatically.',
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w500,
                                color: palette.textSecondary,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 8),
                      LiquidGlassPressable(
                        semanticLabel: 'Retry connection',
                        onTap: isRetrying ? null : onRetry,
                        child: Container(
                          height: 34,
                          padding: const EdgeInsets.symmetric(horizontal: 13),
                          decoration: BoxDecoration(
                            color: palette.primaryAccent,
                            borderRadius: BorderRadius.circular(999),
                          ),
                          alignment: Alignment.center,
                          child: isRetrying
                              ? const CupertinoActivityIndicator(
                                  radius: 7,
                                  color: Colors.white,
                                )
                              : const Text(
                                  'Retry',
                                  style: TextStyle(
                                    color: Colors.white,
                                    fontSize: 12,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Full-screen Liquid Glass offline recovery experience shown only when cold-start
/// connection fails before any KIUQ SYSTEM page has rendered.
class LiquidGlassOfflineScreen extends StatelessWidget {
  const LiquidGlassOfflineScreen({
    super.key,
    required this.palette,
    required this.serverUrl,
    required this.errorMessage,
    required this.isRetrying,
    required this.onRetry,
  });

  final LiquidGlassPalette palette;
  final String serverUrl;
  final String errorMessage;
  final bool isRetrying;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Container(
      key: const ValueKey<String>('kiuq_offline_screen'),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: palette.mode.isDarkSurface
              ? const <Color>[
                  Color(0xFF070E1E),
                  Color(0xFF0F172A),
                  Color(0xFF172554),
                ]
              : const <Color>[
                  Color(0xFFEFF6FF),
                  Color(0xFFF8FAFC),
                  Color(0xFFE0E7FF),
                ],
        ),
      ),
      alignment: Alignment.center,
      padding: const EdgeInsets.all(24),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 400),
        child: LiquidGlassSurface(
          palette: palette,
          borderRadius: BorderRadius.circular(32),
          padding: const EdgeInsets.fromLTRB(26, 30, 26, 26),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Container(
                width: 68,
                height: 68,
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: <Color>[Color(0xFF2563EB), Color(0xFF4F46E5)],
                  ),
                  borderRadius: BorderRadius.circular(22),
                  boxShadow: <BoxShadow>[
                    BoxShadow(
                      color: const Color(0xFF2563EB).withValues(alpha: 0.32),
                      blurRadius: 18,
                      offset: const Offset(0, 8),
                    ),
                  ],
                ),
                alignment: Alignment.center,
                child: const Icon(
                  CupertinoIcons.wifi_slash,
                  color: Colors.white,
                  size: 30,
                ),
              ),
              const SizedBox(height: 20),
              Text(
                'You’re Offline',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 21,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.4,
                  color: palette.textPrimary,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'KIUQ SYSTEM could not reach the workspace server. Check your Wi-Fi or cellular data—the app will reconnect automatically as soon as internet returns.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 13.5,
                  height: 1.45,
                  fontWeight: FontWeight.w500,
                  color: palette.textSecondary,
                ),
              ),
              const SizedBox(height: 14),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 8,
                ),
                decoration: BoxDecoration(
                  color: palette.mode.isDarkSurface
                      ? Colors.white.withValues(alpha: 0.06)
                      : Colors.black.withValues(alpha: 0.045),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  errorMessage.isNotEmpty ? errorMessage : serverUrl,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 11.5,
                    fontWeight: FontWeight.w600,
                    color: palette.textMuted,
                  ),
                ),
              ),
              const SizedBox(height: 22),
              LiquidGlassPressable(
                semanticLabel: 'Retry Connection',
                onTap: isRetrying ? null : onRetry,
                child: Container(
                  height: 48,
                  width: double.infinity,
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: <Color>[Color(0xFF2563EB), Color(0xFF4F46E5)],
                    ),
                    borderRadius: BorderRadius.circular(16),
                    boxShadow: <BoxShadow>[
                      BoxShadow(
                        color: const Color(0xFF2563EB).withValues(alpha: 0.34),
                        blurRadius: 14,
                        offset: const Offset(0, 5),
                      ),
                    ],
                  ),
                  alignment: Alignment.center,
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: <Widget>[
                      if (isRetrying)
                        const CupertinoActivityIndicator(
                          radius: 8,
                          color: Colors.white,
                        )
                      else
                        const Icon(
                          CupertinoIcons.arrow_clockwise,
                          size: 17,
                          color: Colors.white,
                        ),
                      const SizedBox(width: 8),
                      Text(
                        isRetrying ? 'Reconnecting...' : 'Retry Connection',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 14.5,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Floating glass return pill displayed when an in-app Google OAuth sign-in flow is active.
class LiquidGlassOAuthReturnPill extends StatelessWidget {
  const LiquidGlassOAuthReturnPill({
    super.key,
    required this.palette,
    required this.onReturnToApp,
  });

  final LiquidGlassPalette palette;
  final VoidCallback onReturnToApp;

  @override
  Widget build(BuildContext context) {
    final topSafe = math.max(
      MediaQuery.viewPaddingOf(context).top,
      MediaQuery.paddingOf(context).top,
    );

    return Align(
      alignment: Alignment.topLeft,
      child: Padding(
        padding: EdgeInsets.only(left: 14, top: math.max(topSafe + 8, 14)),
        child: LiquidGlassPressable(
          semanticLabel: 'Return to KIUQ SYSTEM',
          onTap: onReturnToApp,
          child: LiquidGlassSurface(
            palette: palette,
            borderRadius: BorderRadius.circular(999),
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Icon(
                  CupertinoIcons.chevron_back,
                  size: 15,
                  color: palette.primaryAccent,
                ),
                const SizedBox(width: 5),
                Text(
                  'KIUQ SYSTEM',
                  style: TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w700,
                    color: palette.textPrimary,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Smooth Liquid Glass branded launch veil that fades out seamlessly once initial load completes.
class LiquidGlassLaunchVeil extends StatelessWidget {
  const LiquidGlassLaunchVeil({
    super.key,
    required this.isVisible,
  });

  final bool isVisible;

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      ignoring: !isVisible,
      child: AnimatedOpacity(
        opacity: isVisible ? 1.0 : 0.0,
        duration: const Duration(milliseconds: 340),
        curve: Curves.easeOutCubic,
        child: DecoratedBox(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: <Color>[
                Color(0xFF1D4ED8),
                Color(0xFF2563EB),
                Color(0xFF3730A3),
              ],
            ),
          ),
          child: Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Container(
                  width: 88,
                  height: 88,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.96),
                    borderRadius: BorderRadius.circular(24),
                    boxShadow: <BoxShadow>[
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.22),
                        blurRadius: 28,
                        offset: const Offset(0, 12),
                      ),
                    ],
                  ),
                  alignment: Alignment.center,
                  child: const Text(
                    'KQ',
                    style: TextStyle(
                      color: Color(0xFF2563EB),
                      fontWeight: FontWeight.w900,
                      fontSize: 34,
                      letterSpacing: -1.0,
                    ),
                  ),
                ),
                const SizedBox(height: 22),
                const Text(
                  'KIUQ SYSTEM',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 22,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 1.4,
                  ),
                ),
                const SizedBox(height: 16),
                CupertinoActivityIndicator(
                  radius: 10,
                  animating: isVisible,
                  color: Colors.white70,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
