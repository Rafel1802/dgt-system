import 'dart:math' as math;

import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

import '../config/app_config.dart';
import '../models/shell_state.dart';
import '../services/ios_native_bridge.dart';
import '../services/session_storage_service.dart';
import '../theme/liquid_glass_theme.dart';
import 'liquid_glass_surface.dart';

/// Opens a compact Apple iOS 26 Liquid Glass Quick Action Popover Menu
/// (triggered on Hub orb long-press).
Future<void> showLiquidGlassQuickMenu({
  required BuildContext context,
  required LiquidGlassPalette palette,
  required VoidCallback onOpenKiuqAi,
  required VoidCallback onReload,
  required VoidCallback onSharePage,
  required VoidCallback onOpenAppSettings,
  bool canGoBack = false,
  VoidCallback? onGoBack,
}) async {
  await showGeneralDialog<void>(
    context: context,
    barrierDismissible: true,
    barrierLabel: 'Dismiss quick menu',
    barrierColor: Colors.black.withValues(alpha: 0.28),
    transitionDuration: const Duration(milliseconds: 210),
    pageBuilder: (dialogContext, _, _) {
      final bottomSafe = math.max(
        MediaQuery.viewPaddingOf(dialogContext).bottom,
        MediaQuery.paddingOf(dialogContext).bottom,
      );
      return Align(
        alignment: Alignment.bottomRight,
        child: Padding(
          padding: EdgeInsets.fromLTRB(20, 0, 16, bottomSafe + 78),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 255),
            child: Material(
              color: Colors.transparent,
              child: LiquidGlassSurface(
                palette: palette,
                borderRadius: BorderRadius.circular(24),
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    if (canGoBack && onGoBack != null) ...<Widget>[
                      _QuickPopoverRow(
                        palette: palette,
                        icon: CupertinoIcons.chevron_back,
                        label: 'Navigate Back',
                        onTap: () {
                          Navigator.of(dialogContext).pop();
                          onGoBack();
                        },
                      ),
                      Divider(height: 1, color: palette.cardBorder),
                    ],
                    _QuickPopoverRow(
                      palette: palette,
                      icon: CupertinoIcons.sparkles,
                      label: 'Open KIUQ AI',
                      onTap: () {
                        Navigator.of(dialogContext).pop();
                        onOpenKiuqAi();
                      },
                    ),
                    Divider(height: 1, color: palette.cardBorder),
                    _QuickPopoverRow(
                      palette: palette,
                      icon: CupertinoIcons.share,
                      label: 'Share Current Page',
                      onTap: () {
                        Navigator.of(dialogContext).pop();
                        onSharePage();
                      },
                    ),
                    Divider(height: 1, color: palette.cardBorder),
                    _QuickPopoverRow(
                      palette: palette,
                      icon: CupertinoIcons.arrow_clockwise,
                      label: 'Reload Workspace',
                      onTap: () {
                        Navigator.of(dialogContext).pop();
                        onReload();
                      },
                    ),
                    Divider(height: 1, color: palette.cardBorder),
                    _QuickPopoverRow(
                      palette: palette,
                      icon: CupertinoIcons.slider_horizontal_3,
                      label: 'iOS App & Cache Settings',
                      onTap: () {
                        Navigator.of(dialogContext).pop();
                        onOpenAppSettings();
                      },
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      );
    },
    transitionBuilder: (_, animation, _, child) {
      final curved = CurvedAnimation(
        parent: animation,
        curve: LiquidGlassMotion.iosSpringCurve,
      );
      return FadeTransition(
        opacity: animation,
        child: ScaleTransition(
          alignment: Alignment.bottomRight,
          scale: Tween<double>(begin: 0.85, end: 1.0).animate(curved),
          child: child,
        ),
      );
    },
  );
}

class _QuickPopoverRow extends StatelessWidget {
  const _QuickPopoverRow({
    required this.palette,
    required this.icon,
    required this.label,
    required this.onTap,
  });

  final LiquidGlassPalette palette;
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return LiquidGlassPressable(
      pressedScale: 0.98,
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        child: Row(
          children: <Widget>[
            Expanded(
              child: Text(
                label,
                style: TextStyle(
                  fontSize: 13.5,
                  fontWeight: FontWeight.w600,
                  color: palette.textPrimary,
                ),
              ),
            ),
            Icon(icon, size: 17, color: palette.primaryAccent),
          ],
        ),
      ),
    );
  }
}

/// Opens the native iOS App Settings, Connection Diagnostics, & Cache Management Sheet.
Future<void> showLiquidGlassAppSettingsSheet({
  required BuildContext context,
  required KiuqAppPreferences preferences,
  required KiuqThemeMode currentTheme,
  required IosNativeBridge nativeBridge,
  required ValueChanged<KiuqThemeMode> onThemeChanged,
  required ValueChanged<bool> onScrollMinimizeChanged,
  required ValueChanged<bool> onHapticsChanged,
  required ValueChanged<bool> onNotificationsChanged,
  required Future<void> Function() onClearCacheKeepSession,
  required Future<void> Function() onFullResetSession,
}) async {
  await showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: false,
    backgroundColor: Colors.transparent,
    barrierColor: Colors.black.withValues(alpha: 0.45),
    builder: (sheetContext) {
      return _LiquidGlassAppSettingsSheet(
        initialPreferences: preferences,
        initialTheme: currentTheme,
        nativeBridge: nativeBridge,
        onThemeChanged: onThemeChanged,
        onScrollMinimizeChanged: onScrollMinimizeChanged,
        onHapticsChanged: onHapticsChanged,
        onNotificationsChanged: onNotificationsChanged,
        onClearCacheKeepSession: onClearCacheKeepSession,
        onFullResetSession: onFullResetSession,
      );
    },
  );
}

class _LiquidGlassAppSettingsSheet extends StatefulWidget {
  const _LiquidGlassAppSettingsSheet({
    required this.initialPreferences,
    required this.initialTheme,
    required this.nativeBridge,
    required this.onThemeChanged,
    required this.onScrollMinimizeChanged,
    required this.onHapticsChanged,
    required this.onNotificationsChanged,
    required this.onClearCacheKeepSession,
    required this.onFullResetSession,
  });

  final KiuqAppPreferences initialPreferences;
  final KiuqThemeMode initialTheme;
  final IosNativeBridge nativeBridge;
  final ValueChanged<KiuqThemeMode> onThemeChanged;
  final ValueChanged<bool> onScrollMinimizeChanged;
  final ValueChanged<bool> onHapticsChanged;
  final ValueChanged<bool> onNotificationsChanged;
  final Future<void> Function() onClearCacheKeepSession;
  final Future<void> Function() onFullResetSession;

  @override
  State<_LiquidGlassAppSettingsSheet> createState() =>
      _LiquidGlassAppSettingsSheetState();
}

class _LiquidGlassAppSettingsSheetState
    extends State<_LiquidGlassAppSettingsSheet> {
  late KiuqThemeMode _theme;
  late bool _scrollMinimize;
  late bool _haptics;
  late bool _notifications;
  KiuqConnectivityStatus? _pingStatus;
  bool _clearingCache = false;
  String? _statusBanner;

  @override
  void initState() {
    super.initState();
    _theme = widget.initialTheme;
    _scrollMinimize = widget.initialPreferences.scrollMinimizeEnabled;
    _haptics = widget.initialPreferences.hapticsEnabled;
    _notifications = widget.initialPreferences.notificationsEnabled;
    _runPingCheck();
  }

  Future<void> _runPingCheck() async {
    final status = await widget.nativeBridge.pingServer();
    if (!mounted) return;
    setState(() => _pingStatus = status);
  }

  @override
  Widget build(BuildContext context) {
    final palette = LiquidGlassPalette.of(_theme);
    final bottomSafe = math.max(
      MediaQuery.viewPaddingOf(context).bottom,
      MediaQuery.paddingOf(context).bottom,
    );

    return Align(
      alignment: Alignment.bottomCenter,
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 560),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 10),
          child: LiquidGlassSurface(
            key: const ValueKey<String>('kiuq_ios_app_settings_sheet'),
            palette: palette,
            borderRadius: const BorderRadius.vertical(
              top: Radius.circular(32),
              bottom: Radius.circular(26),
            ),
            padding: EdgeInsets.fromLTRB(
              18,
              10,
              18,
              math.max(bottomSafe, 14) + 6,
            ),
            child: Material(
              color: Colors.transparent,
              child: SingleChildScrollView(
                physics: const BouncingScrollPhysics(),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    Center(
                      child: Container(
                        width: 38,
                        height: 4.5,
                        decoration: BoxDecoration(
                          color: palette.textMuted.withValues(alpha: 0.36),
                          borderRadius: BorderRadius.circular(999),
                        ),
                      ),
                    ),
                    const SizedBox(height: 14),
                    Row(
                      children: <Widget>[
                        Icon(
                          CupertinoIcons.slider_horizontal_3,
                          color: palette.primaryAccent,
                          size: 20,
                        ),
                        const SizedBox(width: 9),
                        Expanded(
                          child: Text(
                            'iOS App & Storage Settings',
                            style: TextStyle(
                              fontSize: 16.5,
                              fontWeight: FontWeight.w800,
                              letterSpacing: -0.3,
                              color: palette.textPrimary,
                            ),
                          ),
                        ),
                        LiquidGlassPressable(
                          onTap: () => Navigator.of(context).pop(),
                          child: Container(
                            width: 30,
                            height: 30,
                            decoration: BoxDecoration(
                              color: palette.mode.isDarkSurface
                                  ? Colors.white.withValues(alpha: 0.1)
                                  : Colors.black.withValues(alpha: 0.06),
                              shape: BoxShape.circle,
                            ),
                            alignment: Alignment.center,
                            child: Icon(
                              CupertinoIcons.xmark,
                              size: 13,
                              color: palette.textSecondary,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 14),

                    // Server Connection Card
                    Container(
                      padding: const EdgeInsets.all(13),
                      decoration: BoxDecoration(
                        color: palette.cardFill,
                        borderRadius: BorderRadius.circular(18),
                        border: Border.all(
                          color: palette.cardBorder,
                          width: 0.8,
                        ),
                      ),
                      child: Row(
                        children: <Widget>[
                          Container(
                            width: 10,
                            height: 10,
                            decoration: BoxDecoration(
                              color: _pingStatus == null
                                  ? const Color(0xFFF59E0B)
                                  : (_pingStatus!.isOnline
                                        ? const Color(0xFF10B981)
                                        : const Color(0xFFF43F5E)),
                              shape: BoxShape.circle,
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: <Widget>[
                                Text(
                                  _pingStatus == null
                                      ? 'Checking KIUQ Server...'
                                      : (_pingStatus!.isOnline
                                            ? 'Connected (${_pingStatus!.latencyMs ?? 0} ms)'
                                            : 'Server Unreachable'),
                                  style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w700,
                                    color: palette.textPrimary,
                                  ),
                                ),
                                Text(
                                  Uri.parse(appBaseUrl).host,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: TextStyle(
                                    fontSize: 11,
                                    color: palette.textMuted,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          LiquidGlassPressable(
                            onTap: _runPingCheck,
                            child: Icon(
                              CupertinoIcons.arrow_clockwise,
                              size: 16,
                              color: palette.primaryAccent,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 14),

                    // Theme Selector
                    LiquidGlassThemeSegment(
                      currentMode: _theme,
                      palette: palette,
                      enableHaptics: _haptics,
                      onChanged: (mode) {
                        setState(() => _theme = mode);
                        widget.onThemeChanged(mode);
                      },
                    ),
                    const SizedBox(height: 14),

                    // Interactive Experience Toggles
                    Container(
                      decoration: BoxDecoration(
                        color: palette.cardFill,
                        borderRadius: BorderRadius.circular(18),
                        border: Border.all(
                          color: palette.cardBorder,
                          width: 0.8,
                        ),
                      ),
                      child: Column(
                        children: <Widget>[
                          _ToggleSettingRow(
                            palette: palette,
                            icon: CupertinoIcons.chevron_compact_down,
                            title: 'Minimize Dock on Scroll',
                            subtitle:
                                'Subtly compacts the Liquid Glass capsule when scrolling down',
                            value: _scrollMinimize,
                            onChanged: (value) {
                              setState(() => _scrollMinimize = value);
                              widget.onScrollMinimizeChanged(value);
                            },
                          ),
                          Divider(height: 1, color: palette.cardBorder),
                          _ToggleSettingRow(
                            palette: palette,
                            icon: CupertinoIcons.hand_draw_fill,
                            title: 'iOS Haptic Feedback',
                            subtitle:
                                'Subtle tactile response on navigation & controls',
                            value: _haptics,
                            onChanged: (value) {
                              setState(() => _haptics = value);
                              widget.onHapticsChanged(value);
                            },
                          ),
                          Divider(height: 1, color: palette.cardBorder),
                          _ToggleSettingRow(
                            palette: palette,
                            icon: CupertinoIcons.bell_fill,
                            title: 'Real-Time Local Alerts',
                            subtitle:
                                'Deliver iOS banners for Pusher & Chat updates',
                            value: _notifications,
                            onChanged: (value) async {
                              if (value) {
                                final granted = await widget.nativeBridge
                                    .requestNotificationPermission();
                                if (!mounted) return;
                                setState(() => _notifications = granted);
                                widget.onNotificationsChanged(granted);
                              } else {
                                setState(() => _notifications = false);
                                widget.onNotificationsChanged(false);
                              }
                            },
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 14),

                    if (_statusBanner != null) ...<Widget>[
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 9,
                        ),
                        decoration: BoxDecoration(
                          color: const Color(
                            0xFF10B981,
                          ).withValues(alpha: 0.14),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Text(
                          _statusBanner!,
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            fontSize: 12.5,
                            fontWeight: FontWeight.w700,
                            color: Color(0xFF10B981),
                          ),
                        ),
                      ),
                      const SizedBox(height: 10),
                    ],

                    // Safe Cache Clear (Keeps Active Session)
                    LiquidGlassPressable(
                      onTap: _clearingCache
                          ? null
                          : () async {
                              setState(() => _clearingCache = true);
                              await widget.onClearCacheKeepSession();
                              if (!mounted) return;
                              setState(() {
                                _clearingCache = false;
                                _statusBanner =
                                    'Cached web files cleared. Your login session was preserved.';
                              });
                            },
                      child: Container(
                        height: 45,
                        decoration: BoxDecoration(
                          color: palette.primaryAccent.withValues(alpha: 0.13),
                          borderRadius: BorderRadius.circular(15),
                          border: Border.all(
                            color: palette.primaryAccent.withValues(alpha: 0.3),
                            width: 0.85,
                          ),
                        ),
                        alignment: Alignment.center,
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: <Widget>[
                            Icon(
                              CupertinoIcons.trash,
                              size: 16,
                              color: palette.primaryAccent,
                            ),
                            const SizedBox(width: 8),
                            Text(
                              _clearingCache
                                  ? 'Clearing Web Cache...'
                                  : 'Clear Web Cache (Keep Login)',
                              style: TextStyle(
                                fontSize: 13.5,
                                fontWeight: FontWeight.w700,
                                color: palette.primaryAccent,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 9),

                    // Full Session & Storage Reset
                    LiquidGlassPressable(
                      onTap: () async {
                        final confirmed = await showLiquidGlassConfirmDialog(
                          context: context,
                          palette: palette,
                          title: 'Reset Session & Sign Out?',
                          message:
                              'This clears cached resources, local storage, and session cookies, returning you to the KIUQ SYSTEM sign-in screen.',
                          confirmLabel: 'Reset & Sign Out',
                          icon: CupertinoIcons.trash_fill,
                          isDestructive: true,
                        );
                        if (!confirmed || !context.mounted) return;
                        Navigator.of(context).pop();
                        await widget.onFullResetSession();
                      },
                      child: Container(
                        height: 44,
                        decoration: BoxDecoration(
                          color: const Color(0xFFF43F5E).withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(15),
                          border: Border.all(
                            color: const Color(
                              0xFFF43F5E,
                            ).withValues(alpha: 0.25),
                            width: 0.85,
                          ),
                        ),
                        alignment: Alignment.center,
                        child: const Text(
                          'Reset All Storage & Sign Out',
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                            color: Color(0xFFF43F5E),
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
    );
  }
}

class _ToggleSettingRow extends StatelessWidget {
  const _ToggleSettingRow({
    required this.palette,
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.value,
    required this.onChanged,
  });

  final LiquidGlassPalette palette;
  final IconData icon;
  final String title;
  final String subtitle;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 10),
      child: Row(
        children: <Widget>[
          Icon(icon, size: 18, color: palette.primaryAccent),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  title,
                  style: TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w700,
                    color: palette.textPrimary,
                  ),
                ),
                Text(
                  subtitle,
                  style: TextStyle(
                    fontSize: 11,
                    color: palette.textMuted,
                  ),
                ),
              ],
            ),
          ),
          CupertinoSwitch(
            value: value,
            activeTrackColor: palette.primaryAccent,
            onChanged: onChanged,
          ),
        ],
      ),
    );
  }
}
