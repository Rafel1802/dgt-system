import 'dart:math' as math;

import 'package:flutter/cupertino.dart';

import '../models/shell_state.dart';
import '../theme/liquid_glass_theme.dart';
import 'liquid_glass_surface.dart';

/// Apple Music iOS 26-inspired Floating Liquid Glass Bottom Navigation Bar.
///
/// Visual & Behavioral Highlights:
/// - Floating translucent capsule (`LiquidGlassSurface`) with optical backdrop blur,
///   specular top rim reflection, refraction hairline border, and layered depth shadow.
/// - Fluid sliding & morphing active glass pill inside the track (`AnimatedPositioned`).
/// - Detached circular Liquid Glass **Hub** orb on the trailing edge (matching Apple Music's
///   detached trailing glass action orb in iOS 26) with combined attention badge and
///   long-press quick action support.
/// - Subtle scroll-down minimization: smoothly compacts from `62pt` to `46pt` when
///   scrolling down, and expands back to `62pt` when scrolling up or tapped—never
///   making navigation inaccessible.
/// - Respects iPhone safe areas, Dynamic Island/notch landscape insets, and Home Indicator.
class LiquidGlassBottomNav extends StatefulWidget {
  const LiquidGlassBottomNav({
    super.key,
    required this.tabs,
    required this.palette,
    required this.onSelectTab,
    required this.onOpenHub,
    this.onLongPressHub,
    this.onExpandRequested,
    this.isMinimized = false,
    this.isHidden = false,
    this.hubBadgeCount = 0,
    this.canGoBack = false,
    this.onBackTap,
    this.enableHaptics = true,
  });

  final List<KiuqNavDestination> tabs;
  final LiquidGlassPalette palette;
  final ValueChanged<KiuqNavDestination> onSelectTab;
  final VoidCallback onOpenHub;
  final VoidCallback? onLongPressHub;
  final VoidCallback? onExpandRequested;
  final bool isMinimized;
  final bool isHidden;
  final int hubBadgeCount;
  final bool canGoBack;
  final VoidCallback? onBackTap;
  final bool enableHaptics;

  /// Total vertical clearance from the bottom of the screen reserved for page content.
  static double reservedClearanceFor(BuildContext context, {bool minimized = false}) {
    final bottomSafe = math.max(
      MediaQuery.viewPaddingOf(context).bottom,
      MediaQuery.paddingOf(context).bottom,
    );
    final barHeight = minimized ? 46.0 : 62.0;
    final bottomMargin = bottomSafe > 0 ? bottomSafe + 4.0 : 12.0;
    return barHeight + bottomMargin + 10.0;
  }

  @override
  State<LiquidGlassBottomNav> createState() => _LiquidGlassBottomNavState();
}

class _LiquidGlassBottomNavState extends State<LiquidGlassBottomNav> {
  final ScrollController _scrollController = ScrollController();

  @override
  void didUpdateWidget(covariant LiquidGlassBottomNav oldWidget) {
    super.didUpdateWidget(oldWidget);
    final oldActive = oldWidget.tabs.indexWhere((t) => t.isActive);
    final newActive = widget.tabs.indexWhere((t) => t.isActive);
    if (newActive != -1 && newActive != oldActive) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _scrollActiveIntoView(newActive);
      });
    }
  }

  void _scrollActiveIntoView(int activeIndex) {
    if (!mounted || !_scrollController.hasClients) return;
    final position = _scrollController.position;
    if (position.maxScrollExtent <= 0) return;

    final approxItemWidth = widget.isMinimized ? 54.0 : 66.0;
    final targetCenter = (activeIndex + 0.5) * approxItemWidth;
    final desiredOffset = (targetCenter - (position.viewportDimension / 2))
        .clamp(0.0, position.maxScrollExtent);

    _scrollController.animateTo(
      desiredOffset,
      duration: LiquidGlassMotion.lensGlide,
      curve: LiquidGlassMotion.iosFluidCurve,
    );
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (widget.tabs.isEmpty) return const SizedBox.shrink();

    final media = MediaQuery.of(context);
    final viewPadding = MediaQuery.viewPaddingOf(context);
    final bottomSafe = math.max(viewPadding.bottom, media.padding.bottom);
    final leftSafe = math.max(viewPadding.left, media.padding.left);
    final rightSafe = math.max(viewPadding.right, media.padding.right);
    final isCompactWidth = media.size.width <= 360;
    final isLandscape = media.orientation == Orientation.landscape;

    final minimized = widget.isMinimized || isLandscape;
    final capsuleHeight = minimized ? 46.0 : 62.0;
    final orbSize = minimized ? 46.0 : 58.0;
    final horizontalPad = isCompactWidth ? 10.0 : 14.0;
    final bottomPad = bottomSafe > 0
        ? math.max(bottomSafe - (isLandscape ? 4.0 : 2.0), 6.0)
        : 12.0;

    return IgnorePointer(
      ignoring: widget.isHidden,
      child: AnimatedSlide(
        offset: widget.isHidden ? const Offset(0, 1.35) : Offset.zero,
        duration: LiquidGlassMotion.standard,
        curve: LiquidGlassMotion.iosFluidCurve,
        child: AnimatedOpacity(
          opacity: widget.isHidden ? 0.0 : 1.0,
          duration: LiquidGlassMotion.fast,
          child: Padding(
            padding: EdgeInsets.fromLTRB(
              leftSafe + horizontalPad,
              0,
              rightSafe + horizontalPad,
              bottomPad,
            ),
            child: Align(
              alignment: Alignment.bottomCenter,
              heightFactor: 1.0,
              child: ConstrainedBox(
                constraints: BoxConstraints(
                  maxWidth: isLandscape ? 560 : 500,
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.max,
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: <Widget>[
                    // Optional compact Back orb when WebView has back history in landscape/deep views
                    if (widget.canGoBack && widget.onBackTap != null && minimized) ...<Widget>[
                      _DetachedGlassOrb(
                        size: orbSize,
                        palette: widget.palette,
                        icon: CupertinoIcons.chevron_back,
                        semanticLabel: 'Back',
                        enableHaptics: widget.enableHaptics,
                        onTap: widget.onBackTap!,
                      ),
                      const SizedBox(width: 8),
                    ],

                    // Main Floating Apple Music iOS 26 Liquid Glass Capsule
                    Expanded(
                      child: AnimatedContainer(
                        duration: LiquidGlassMotion.standard,
                        curve: LiquidGlassMotion.iosFluidCurve,
                        height: capsuleHeight,
                        child: LiquidGlassSurface(
                          key: const ValueKey<String>('kiuq_liquid_glass_capsule'),
                          palette: widget.palette,
                          borderRadius: BorderRadius.circular(999),
                          padding: EdgeInsets.symmetric(
                            horizontal: minimized ? 5 : 6,
                            vertical: minimized ? 4 : 5,
                          ),
                          child: LayoutBuilder(
                            builder: (context, constraints) {
                              final count = widget.tabs.length;
                              final minSlotWidth = minimized
                                  ? (isCompactWidth ? 46.0 : 52.0)
                                  : (isCompactWidth ? 58.0 : 64.0);
                              final fitsWithoutScroll =
                                  count * minSlotWidth <= constraints.maxWidth + 2;
                              final slotWidth = fitsWithoutScroll
                                  ? constraints.maxWidth / count
                                  : minSlotWidth;
                              final trackWidth = fitsWithoutScroll
                                  ? constraints.maxWidth
                                  : slotWidth * count;
                              final activeIndex = widget.tabs.indexWhere(
                                (t) => t.isActive,
                              );

                              final trackContent = SizedBox(
                                width: trackWidth,
                                height: double.infinity,
                                child: Stack(
                                  clipBehavior: Clip.none,
                                  children: <Widget>[
                                    // Fluid sliding Liquid Glass active lens pill
                                    if (activeIndex >= 0)
                                      AnimatedPositioned(
                                        duration: LiquidGlassMotion.lensGlide,
                                        curve: LiquidGlassMotion.iosSpringCurve,
                                        left: activeIndex * slotWidth + 1.5,
                                        top: 0,
                                        bottom: 0,
                                        width: math.max(slotWidth - 3.0, 36.0),
                                        child: IgnorePointer(
                                          child: DecoratedBox(
                                            decoration: BoxDecoration(
                                              borderRadius: BorderRadius.circular(999),
                                              gradient: LinearGradient(
                                                begin: Alignment.topCenter,
                                                end: Alignment.bottomCenter,
                                                colors: <Color>[
                                                  widget.palette.activePillTop,
                                                  widget.palette.activePillBottom,
                                                ],
                                              ),
                                              border: Border.all(
                                                color: widget.palette.activePillBorder,
                                                width: 0.95,
                                              ),
                                              boxShadow: <BoxShadow>[
                                                BoxShadow(
                                                  color: widget.palette.activePillGlow,
                                                  blurRadius: 14,
                                                  spreadRadius: -2,
                                                  offset: const Offset(0, 3),
                                                ),
                                              ],
                                            ),
                                          ),
                                        ),
                                      ),

                                    // Interactive SF-Symbol destination items
                                    Row(
                                      children: List<Widget>.generate(count, (index) {
                                        final item = widget.tabs[index];
                                        return SizedBox(
                                          width: slotWidth,
                                          height: double.infinity,
                                          child: _CapsuleTabButton(
                                            destination: item,
                                            palette: widget.palette,
                                            minimized: minimized,
                                            compactText: isCompactWidth,
                                            enableHaptics: widget.enableHaptics,
                                            onTap: () {
                                              if (widget.isMinimized) {
                                                widget.onExpandRequested?.call();
                                              }
                                              widget.onSelectTab(item);
                                            },
                                          ),
                                        );
                                      }),
                                    ),
                                  ],
                                ),
                              );

                              if (fitsWithoutScroll) {
                                return trackContent;
                              }

                              return SingleChildScrollView(
                                controller: _scrollController,
                                scrollDirection: Axis.horizontal,
                                physics: const BouncingScrollPhysics(),
                                child: trackContent,
                              );
                            },
                          ),
                        ),
                      ),
                    ),

                    SizedBox(width: isCompactWidth ? 8 : 10),

                    // Detached Circular Liquid Glass Hub Orb (Apple Music iOS 26 signature)
                    _DetachedGlassOrb(
                      key: const ValueKey<String>('kiuq_liquid_glass_hub_orb'),
                      size: orbSize,
                      palette: widget.palette,
                      icon: CupertinoIcons.square_grid_2x2_fill,
                      label: minimized ? null : 'Hub',
                      badgeCount: widget.hubBadgeCount,
                      semanticLabel: 'Open KIUQ App Hub and Search',
                      enableHaptics: widget.enableHaptics,
                      onTap: () {
                        if (widget.isMinimized) {
                          widget.onExpandRequested?.call();
                        }
                        widget.onOpenHub();
                      },
                      onLongPress: widget.onLongPressHub,
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

class _CapsuleTabButton extends StatelessWidget {
  const _CapsuleTabButton({
    required this.destination,
    required this.palette,
    required this.minimized,
    required this.compactText,
    required this.enableHaptics,
    required this.onTap,
  });

  final KiuqNavDestination destination;
  final LiquidGlassPalette palette;
  final bool minimized;
  final bool compactText;
  final bool enableHaptics;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final isActive = destination.isActive;
    final iconData = KiuqSfSymbols.resolve(
      destination.iconKey,
      active: isActive,
    );
    final fgColor = isActive ? palette.iconActive : palette.iconInactive;

    return LiquidGlassPressable(
      semanticLabel: destination.label,
      enableHaptics: enableHaptics,
      pressedScale: 0.91,
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 2),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Stack(
              clipBehavior: Clip.none,
              children: <Widget>[
                AnimatedScale(
                  scale: isActive ? 1.06 : 1.0,
                  duration: LiquidGlassMotion.fast,
                  curve: LiquidGlassMotion.iosSpringCurve,
                  child: Icon(
                    iconData,
                    size: minimized ? 19.5 : 21.0,
                    color: fgColor,
                  ),
                ),
                if (destination.hasBadge)
                  Positioned(
                    right: -9,
                    top: -5,
                    child: LiquidGlassBadge(
                      label: destination.effectiveBadgeLabel,
                      tone: destination.badgeTone,
                      compact: minimized,
                    ),
                  ),
              ],
            ),
            AnimatedSize(
              duration: LiquidGlassMotion.fast,
              curve: LiquidGlassMotion.iosFluidCurve,
              child: minimized
                  ? const SizedBox.shrink()
                  : Padding(
                      padding: const EdgeInsets.only(top: 3),
                      child: Text(
                        destination.label,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: compactText ? 9.5 : 10.2,
                          height: 1.05,
                          letterSpacing: -0.2,
                          fontWeight:
                              isActive ? FontWeight.w700 : FontWeight.w600,
                          color: isActive
                              ? palette.iconActive
                              : palette.textSecondary,
                        ),
                      ),
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DetachedGlassOrb extends StatelessWidget {
  const _DetachedGlassOrb({
    super.key,
    required this.size,
    required this.palette,
    required this.icon,
    required this.semanticLabel,
    required this.enableHaptics,
    required this.onTap,
    this.onLongPress,
    this.label,
    this.badgeCount = 0,
  });

  final double size;
  final LiquidGlassPalette palette;
  final IconData icon;
  final String? label;
  final int badgeCount;
  final String semanticLabel;
  final bool enableHaptics;
  final VoidCallback onTap;
  final VoidCallback? onLongPress;

  @override
  Widget build(BuildContext context) {
    final badgeText = badgeCount <= 0
        ? ''
        : (badgeCount > 99 ? '99+' : '$badgeCount');

    return LiquidGlassPressable(
      semanticLabel: semanticLabel,
      enableHaptics: enableHaptics,
      pressedScale: 0.90,
      onTap: onTap,
      onLongPress: onLongPress,
      child: AnimatedContainer(
        duration: LiquidGlassMotion.standard,
        curve: LiquidGlassMotion.iosFluidCurve,
        width: size,
        height: size,
        child: Stack(
          clipBehavior: Clip.none,
          children: <Widget>[
            Positioned.fill(
              child: LiquidGlassSurface(
                palette: palette,
                borderRadius: BorderRadius.circular(999),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: <Widget>[
                    Icon(
                      icon,
                      size: label == null ? 20 : 19.5,
                      color: palette.iconActive,
                    ),
                    if (label != null) ...<Widget>[
                      const SizedBox(height: 2),
                      Text(
                        label!,
                        style: TextStyle(
                          fontSize: 9.5,
                          height: 1.0,
                          fontWeight: FontWeight.w700,
                          letterSpacing: -0.15,
                          color: palette.textPrimary,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
            if (badgeText.isNotEmpty)
              Positioned(
                right: -2,
                top: -2,
                child: LiquidGlassBadge(label: badgeText),
              ),
          ],
        ),
      ),
    );
  }
}
