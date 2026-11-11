import 'dart:ui';

import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../models/shell_state.dart';
import '../theme/liquid_glass_theme.dart';

/// Core Apple iOS 26 Liquid Glass surface with translucent backdrop blur,
/// specular top reflection rim, hairline refraction border, and floating depth shadow.
class LiquidGlassSurface extends StatelessWidget {
  const LiquidGlassSurface({
    super.key,
    required this.palette,
    required this.child,
    this.borderRadius = const BorderRadius.all(Radius.circular(999)),
    this.padding = EdgeInsets.zero,
    this.blurSigma,
    this.showShadow = true,
    this.showSpecularRim = true,
    this.customGradient,
    this.borderColor,
  });

  final LiquidGlassPalette palette;
  final Widget child;
  final BorderRadius borderRadius;
  final EdgeInsetsGeometry padding;
  final double? blurSigma;
  final bool showShadow;
  final bool showSpecularRim;
  final Gradient? customGradient;
  final Color? borderColor;

  @override
  Widget build(BuildContext context) {
    final sigma = blurSigma ?? palette.blurSigma;

    return Container(
      decoration: BoxDecoration(
        borderRadius: borderRadius,
        boxShadow: showShadow
            ? <BoxShadow>[
                BoxShadow(
                  color: palette.ambientShadow,
                  blurRadius: 34,
                  spreadRadius: -4,
                  offset: const Offset(0, 14),
                ),
                BoxShadow(
                  color: palette.keyShadow,
                  blurRadius: 12,
                  spreadRadius: -1,
                  offset: const Offset(0, 4),
                ),
              ]
            : null,
      ),
      child: ClipRRect(
        borderRadius: borderRadius,
        child: BackdropFilter(
          filter: ImageFilter.blur(sigmaX: sigma, sigmaY: sigma),
          child: DecoratedBox(
            decoration: BoxDecoration(
              borderRadius: borderRadius,
              gradient:
                  customGradient ??
                  LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: <Color>[
                      palette.glassTopFill,
                      palette.glassBottomFill,
                    ],
                  ),
              border: Border.all(
                color: borderColor ?? palette.outerBorder,
                width: 0.9,
              ),
            ),
            child: Stack(
              children: <Widget>[
                if (showSpecularRim)
                  Positioned.fill(
                    child: IgnorePointer(
                      child: DecoratedBox(
                        decoration: BoxDecoration(
                          borderRadius: borderRadius,
                          gradient: RadialGradient(
                            center: const Alignment(0, -1.25),
                            radius: 1.35,
                            colors: <Color>[
                              palette.specularRimTop,
                              palette.specularRimBottom.withValues(alpha: 0.0),
                            ],
                            stops: const <double>[0.0, 0.62],
                          ),
                        ),
                      ),
                    ),
                  ),
                Padding(padding: padding, child: child),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Tactile iOS 26 pressable wrapper with smooth scale feedback and optional haptics.
class LiquidGlassPressable extends StatefulWidget {
  const LiquidGlassPressable({
    super.key,
    required this.child,
    this.onTap,
    this.onLongPress,
    this.pressedScale = 0.93,
    this.enableHaptics = true,
    this.semanticLabel,
    this.behavior = HitTestBehavior.opaque,
  });

  final Widget child;
  final VoidCallback? onTap;
  final VoidCallback? onLongPress;
  final double pressedScale;
  final bool enableHaptics;
  final String? semanticLabel;
  final HitTestBehavior behavior;

  @override
  State<LiquidGlassPressable> createState() => _LiquidGlassPressableState();
}

class _LiquidGlassPressableState extends State<LiquidGlassPressable> {
  bool _pressed = false;

  void _setPressed(bool value) {
    if (_pressed == value || !mounted) return;
    setState(() => _pressed = value);
  }

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: widget.semanticLabel,
      child: GestureDetector(
        behavior: widget.behavior,
        onTapDown: widget.onTap != null ? (_) => _setPressed(true) : null,
        onTapUp: widget.onTap != null ? (_) => _setPressed(false) : null,
        onTapCancel: widget.onTap != null ? () => _setPressed(false) : null,
        onTap: widget.onTap == null
            ? null
            : () {
                if (widget.enableHaptics) {
                  HapticFeedback.selectionClick();
                }
                widget.onTap?.call();
              },
        onLongPress: widget.onLongPress == null
            ? null
            : () {
                _setPressed(false);
                if (widget.enableHaptics) {
                  HapticFeedback.mediumImpact();
                }
                widget.onLongPress?.call();
              },
        child: AnimatedScale(
          scale: _pressed ? widget.pressedScale : 1.0,
          duration: _pressed
              ? const Duration(milliseconds: 90)
              : const Duration(milliseconds: 200),
          curve: _pressed ? Curves.easeOut : LiquidGlassMotion.iosSpringCurve,
          child: widget.child,
        ),
      ),
    );
  }
}

/// High-contrast iOS notification / counter badge pill.
class LiquidGlassBadge extends StatelessWidget {
  const LiquidGlassBadge({
    super.key,
    required this.label,
    this.tone = KiuqBadgeTone.alert,
    this.compact = false,
  });

  final String label;
  final KiuqBadgeTone tone;
  final bool compact;

  Color get _backgroundColor {
    switch (tone) {
      case KiuqBadgeTone.warning:
        return const Color(0xFFF59E0B);
      case KiuqBadgeTone.info:
        return const Color(0xFF2563EB);
      case KiuqBadgeTone.success:
        return const Color(0xFF10B981);
      case KiuqBadgeTone.alert:
        return const Color(0xFFF43F5E);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (label.isEmpty) return const SizedBox.shrink();

    return Container(
      constraints: BoxConstraints(
        minWidth: compact ? 15 : 17,
        minHeight: compact ? 15 : 17,
      ),
      padding: EdgeInsets.symmetric(
        horizontal: label.length > 1 ? 4.5 : 3.5,
        vertical: 1,
      ),
      decoration: BoxDecoration(
        color: _backgroundColor,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white.withValues(alpha: 0.9), width: 1.2),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: _backgroundColor.withValues(alpha: 0.42),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      alignment: Alignment.center,
      child: Text(
        label,
        style: TextStyle(
          color: Colors.white,
          fontSize: compact ? 8.5 : 9.5,
          fontWeight: FontWeight.w800,
          height: 1.05,
          letterSpacing: -0.2,
        ),
      ),
    );
  }
}

/// Segmented Liquid Glass Theme Switcher (`Light` / `Dark` / `Neon`).
class LiquidGlassThemeSegment extends StatelessWidget {
  const LiquidGlassThemeSegment({
    super.key,
    required this.currentMode,
    required this.palette,
    required this.onChanged,
    this.enableHaptics = true,
  });

  final KiuqThemeMode currentMode;
  final LiquidGlassPalette palette;
  final ValueChanged<KiuqThemeMode> onChanged;
  final bool enableHaptics;

  @override
  Widget build(BuildContext context) {
    const modes = KiuqThemeMode.values;
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: palette.mode.isDarkSurface
            ? Colors.white.withValues(alpha: 0.06)
            : Colors.black.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: palette.cardBorder, width: 0.75),
      ),
      child: Row(
        children: modes.map((mode) {
          final selected = mode == currentMode;
          final icon = switch (mode) {
            KiuqThemeMode.light => CupertinoIcons.sun_max_fill,
            KiuqThemeMode.dark => CupertinoIcons.moon_stars_fill,
            KiuqThemeMode.neon => CupertinoIcons.bolt_fill,
          };
          final iconColor = switch (mode) {
            KiuqThemeMode.light => const Color(0xFFF59E0B),
            KiuqThemeMode.dark => const Color(0xFF818CF8),
            KiuqThemeMode.neon => const Color(0xFF00F0FF),
          };

          return Expanded(
            child: LiquidGlassPressable(
              enableHaptics: enableHaptics,
              pressedScale: 0.96,
              semanticLabel: 'Switch to ${mode.displayLabel} theme',
              onTap: () => onChanged(mode),
              child: AnimatedContainer(
                duration: LiquidGlassMotion.fast,
                curve: LiquidGlassMotion.iosFluidCurve,
                padding: const EdgeInsets.symmetric(vertical: 9, horizontal: 6),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(12),
                  gradient: selected
                      ? LinearGradient(
                          begin: Alignment.topCenter,
                          end: Alignment.bottomCenter,
                          colors: <Color>[
                            palette.activePillTop,
                            palette.activePillBottom,
                          ],
                        )
                      : null,
                  border: selected
                      ? Border.all(color: palette.activePillBorder, width: 0.9)
                      : null,
                  boxShadow: selected
                      ? <BoxShadow>[
                          BoxShadow(
                            color: palette.activePillGlow,
                            blurRadius: 10,
                            offset: const Offset(0, 2),
                          ),
                        ]
                      : null,
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: <Widget>[
                    Icon(
                      icon,
                      size: 14,
                      color: selected ? iconColor : palette.textMuted,
                    ),
                    const SizedBox(width: 5),
                    Flexible(
                      child: Text(
                        mode.displayLabel,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight:
                              selected ? FontWeight.w700 : FontWeight.w600,
                          color: selected
                              ? palette.textPrimary
                              : palette.textSecondary,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          );
        }).toList(),
      ),
    );
  }
}

/// Shows an Apple iOS 26 Liquid Glass confirmation dialog.
Future<bool> showLiquidGlassConfirmDialog({
  required BuildContext context,
  required LiquidGlassPalette palette,
  required String title,
  required String message,
  required String confirmLabel,
  String cancelLabel = 'Cancel',
  IconData icon = CupertinoIcons.exclamationmark_shield_fill,
  bool isDestructive = false,
}) async {
  final accent = isDestructive ? const Color(0xFFF43F5E) : palette.primaryAccent;
  final result = await showGeneralDialog<bool>(
    context: context,
    barrierDismissible: true,
    barrierLabel: 'Dismiss dialog',
    barrierColor: Colors.black.withValues(alpha: 0.45),
    transitionDuration: const Duration(milliseconds: 220),
    pageBuilder: (dialogContext, _, _) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 370),
            child: Material(
              color: Colors.transparent,
              child: LiquidGlassSurface(
                palette: palette,
                borderRadius: BorderRadius.circular(28),
                padding: const EdgeInsets.fromLTRB(22, 24, 22, 18),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    Container(
                      width: 50,
                      height: 50,
                      decoration: BoxDecoration(
                        color: accent.withValues(alpha: 0.14),
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: accent.withValues(alpha: 0.32),
                          width: 1,
                        ),
                      ),
                      alignment: Alignment.center,
                      child: Icon(icon, color: accent, size: 24),
                    ),
                    const SizedBox(height: 14),
                    Text(
                      title,
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w800,
                        letterSpacing: -0.3,
                        color: palette.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      message,
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 13.5,
                        height: 1.42,
                        fontWeight: FontWeight.w500,
                        color: palette.textSecondary,
                      ),
                    ),
                    const SizedBox(height: 20),
                    Row(
                      children: <Widget>[
                        Expanded(
                          child: LiquidGlassPressable(
                            onTap: () => Navigator.of(dialogContext).pop(false),
                            child: Container(
                              height: 44,
                              decoration: BoxDecoration(
                                color: palette.mode.isDarkSurface
                                    ? Colors.white.withValues(alpha: 0.08)
                                    : Colors.black.withValues(alpha: 0.05),
                                borderRadius: BorderRadius.circular(14),
                                border: Border.all(
                                  color: palette.cardBorder,
                                  width: 0.8,
                                ),
                              ),
                              alignment: Alignment.center,
                              child: Text(
                                cancelLabel,
                                style: TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w700,
                                  color: palette.textPrimary,
                                ),
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: LiquidGlassPressable(
                            onTap: () => Navigator.of(dialogContext).pop(true),
                            child: Container(
                              height: 44,
                              decoration: BoxDecoration(
                                color: isDestructive
                                    ? const Color(0xFFE11D48)
                                    : const Color(0xFF2563EB),
                                borderRadius: BorderRadius.circular(14),
                                boxShadow: <BoxShadow>[
                                  BoxShadow(
                                    color: accent.withValues(alpha: 0.32),
                                    blurRadius: 12,
                                    offset: const Offset(0, 4),
                                  ),
                                ],
                              ),
                              alignment: Alignment.center,
                              child: Text(
                                confirmLabel,
                                style: const TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w700,
                                  color: Colors.white,
                                ),
                              ),
                            ),
                          ),
                        ),
                      ],
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
          scale: Tween<double>(begin: 0.92, end: 1.0).animate(curved),
          child: child,
        ),
      );
    },
  );
  return result ?? false;
}
