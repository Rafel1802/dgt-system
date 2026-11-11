import 'dart:math' as math;

import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

import '../models/shell_state.dart';
import '../theme/liquid_glass_theme.dart';
import 'liquid_glass_surface.dart';

/// Adaptive iPadOS 26 Liquid Glass Sidebar Navigation (`shortestSide >= 600`).
///
/// Designed specifically for iPad Mini, iPad Air, and iPad Pro in both portrait
/// and landscape orientations—never stretching the iPhone bottom tab bar:
/// - Expanded mode (`246pt`): Full grouped KIUQ SYSTEM workspace sidebar with
///   Spotlight search button, unread/task badges, theme switcher, and user card.
/// - Compact rail mode (`78pt`): Sleek floating SF-Symbol icon rail for maximum
///   canvas area in portrait or split-view, expandable with a single tap.
class LiquidGlassIpadSidebar extends StatelessWidget {
  const LiquidGlassIpadSidebar({
    super.key,
    required this.destinations,
    required this.palette,
    required this.themeMode,
    required this.user,
    required this.isExpanded,
    required this.onToggleExpanded,
    required this.onSelectDestination,
    required this.onOpenSearchHub,
    required this.onOpenAppSettings,
    required this.onThemeChanged,
    required this.onSignOut,
    this.unreadNotifications = 0,
    this.canGoBack = false,
    this.onBackTap,
    this.onReloadTap,
    this.enableHaptics = true,
  });

  final List<KiuqNavDestination> destinations;
  final LiquidGlassPalette palette;
  final KiuqThemeMode themeMode;
  final KiuqUserProfile user;
  final bool isExpanded;
  final VoidCallback onToggleExpanded;
  final ValueChanged<KiuqNavDestination> onSelectDestination;
  final VoidCallback onOpenSearchHub;
  final VoidCallback onOpenAppSettings;
  final ValueChanged<KiuqThemeMode> onThemeChanged;
  final VoidCallback onSignOut;
  final int unreadNotifications;
  final bool canGoBack;
  final VoidCallback? onBackTap;
  final VoidCallback? onReloadTap;
  final bool enableHaptics;

  @override
  Widget build(BuildContext context) {
    final viewPadding = MediaQuery.viewPaddingOf(context);
    final mediaPadding = MediaQuery.paddingOf(context);
    final topSafe = math.max(viewPadding.top, mediaPadding.top);
    final bottomSafe = math.max(viewPadding.bottom, mediaPadding.bottom);
    final leftSafe = math.max(viewPadding.left, mediaPadding.left);

    final targetWidth = isExpanded ? 252.0 : 80.0;
    final grouped = _groupDestinations(destinations);

    return AnimatedContainer(
      duration: LiquidGlassMotion.standard,
      curve: LiquidGlassMotion.iosFluidCurve,
      width: targetWidth + leftSafe + 16,
      padding: EdgeInsets.fromLTRB(
        leftSafe + 10,
        math.max(topSafe, 12),
        6,
        math.max(bottomSafe, 12),
      ),
      child: LiquidGlassSurface(
        key: const ValueKey<String>('kiuq_liquid_glass_ipad_sidebar'),
        palette: palette,
        borderRadius: BorderRadius.circular(28),
        padding: EdgeInsets.symmetric(
          horizontal: isExpanded ? 12 : 8,
          vertical: 12,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            // Brand + Collapse/Expand + Quick Controls Header
            _buildHeader(),
            const SizedBox(height: 10),

            // Instant Spotlight / App Hub Trigger
            _buildSearchTrigger(),
            const SizedBox(height: 10),

            // Grouped Scrollable KIUQ SYSTEM Navigation
            Expanded(
              child: ListView(
                padding: EdgeInsets.zero,
                physics: const BouncingScrollPhysics(),
                children: <Widget>[
                  for (final entry in grouped.entries) ...<Widget>[
                    if (isExpanded)
                      Padding(
                        padding: const EdgeInsets.fromLTRB(10, 10, 8, 5),
                        child: Text(
                          entry.key.toUpperCase(),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 0.8,
                            color: palette.textMuted,
                          ),
                        ),
                      )
                    else
                      Padding(
                        padding: const EdgeInsets.symmetric(
                          vertical: 6,
                          horizontal: 14,
                        ),
                        child: Divider(
                          height: 1,
                          thickness: 0.7,
                          color: palette.cardBorder,
                        ),
                      ),
                    for (final item in entry.value)
                      _IpadSidebarItem(
                        destination: item,
                        palette: palette,
                        expanded: isExpanded,
                        enableHaptics: enableHaptics,
                        onTap: () => onSelectDestination(item),
                      ),
                  ],
                ],
              ),
            ),

            const SizedBox(height: 8),

            // Theme Switcher (Expanded) or Cycle Button (Collapsed)
            if (isExpanded) ...<Widget>[
              LiquidGlassThemeSegment(
                currentMode: themeMode,
                palette: palette,
                enableHaptics: enableHaptics,
                onChanged: onThemeChanged,
              ),
              const SizedBox(height: 10),
            ],

            // User Profile & App Settings Footer
            _buildFooter(),
          ],
        ),
      ),
    );
  }

  Widget _buildHeader() {
    if (!isExpanded) {
      return Column(
        children: <Widget>[
          LiquidGlassPressable(
            semanticLabel: 'Expand sidebar',
            enableHaptics: enableHaptics,
            onTap: onToggleExpanded,
            child: Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: <Color>[Color(0xFF2563EB), Color(0xFF4F46E5)],
                ),
                borderRadius: BorderRadius.circular(14),
                boxShadow: <BoxShadow>[
                  BoxShadow(
                    color: const Color(0xFF2563EB).withValues(alpha: 0.32),
                    blurRadius: 10,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              alignment: Alignment.center,
              child: const Icon(
                CupertinoIcons.sidebar_left,
                color: Colors.white,
                size: 20,
              ),
            ),
          ),
          if (canGoBack && onBackTap != null) ...<Widget>[
            const SizedBox(height: 8),
            _IconMiniPill(
              icon: CupertinoIcons.chevron_back,
              palette: palette,
              tooltip: 'Back',
              onTap: onBackTap!,
            ),
          ],
        ],
      );
    }

    return Row(
      children: <Widget>[
        Container(
          width: 36,
          height: 36,
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: <Color>[Color(0xFF2563EB), Color(0xFF4F46E5)],
            ),
            borderRadius: BorderRadius.circular(11),
          ),
          alignment: Alignment.center,
          child: const Text(
            'KQ',
            style: TextStyle(
              color: Colors.white,
              fontSize: 13.5,
              fontWeight: FontWeight.w900,
              letterSpacing: -0.4,
            ),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                'KIUQ SYSTEM',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 13.5,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.2,
                  color: palette.textPrimary,
                ),
              ),
              Text(
                'iPadOS Workspace',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 10.5,
                  fontWeight: FontWeight.w600,
                  color: palette.textMuted,
                ),
              ),
            ],
          ),
        ),
        if (canGoBack && onBackTap != null) ...<Widget>[
          _IconMiniPill(
            icon: CupertinoIcons.chevron_back,
            palette: palette,
            tooltip: 'Back',
            onTap: onBackTap!,
          ),
          const SizedBox(width: 4),
        ],
        if (onReloadTap != null) ...<Widget>[
          _IconMiniPill(
            icon: CupertinoIcons.arrow_clockwise,
            palette: palette,
            tooltip: 'Reload',
            onTap: onReloadTap!,
          ),
          const SizedBox(width: 4),
        ],
        _IconMiniPill(
          icon: CupertinoIcons.sidebar_left,
          palette: palette,
          tooltip: 'Collapse sidebar',
          onTap: onToggleExpanded,
        ),
      ],
    );
  }

  Widget _buildSearchTrigger() {
    final badgeText = unreadNotifications > 99
        ? '99+'
        : (unreadNotifications > 0 ? '$unreadNotifications' : '');

    return LiquidGlassPressable(
      semanticLabel: 'Search modules and open Hub',
      enableHaptics: enableHaptics,
      pressedScale: 0.97,
      onTap: onOpenSearchHub,
      child: Container(
        height: 40,
        padding: EdgeInsets.symmetric(horizontal: isExpanded ? 12 : 0),
        decoration: BoxDecoration(
          color: palette.mode.isDarkSurface
              ? Colors.white.withValues(alpha: 0.07)
              : Colors.black.withValues(alpha: 0.045),
          borderRadius: BorderRadius.circular(13),
          border: Border.all(color: palette.cardBorder, width: 0.8),
        ),
        child: isExpanded
            ? Row(
                children: <Widget>[
                  Icon(
                    CupertinoIcons.search,
                    size: 15,
                    color: palette.textMuted,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Quick Search & Hub',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w600,
                        color: palette.textSecondary,
                      ),
                    ),
                  ),
                  if (badgeText.isNotEmpty)
                    LiquidGlassBadge(label: badgeText, compact: true),
                ],
              )
            : Center(
                child: Stack(
                  clipBehavior: Clip.none,
                  children: <Widget>[
                    Icon(
                      CupertinoIcons.search,
                      size: 18,
                      color: palette.textSecondary,
                    ),
                    if (badgeText.isNotEmpty)
                      Positioned(
                        right: -7,
                        top: -6,
                        child: LiquidGlassBadge(label: badgeText, compact: true),
                      ),
                  ],
                ),
              ),
      ),
    );
  }

  Widget _buildFooter() {
    if (!isExpanded) {
      return Column(
        children: <Widget>[
          _IconMiniPill(
            icon: CupertinoIcons.slider_horizontal_3,
            palette: palette,
            tooltip: 'App Settings',
            onTap: onOpenAppSettings,
          ),
          const SizedBox(height: 8),
          LiquidGlassPressable(
            semanticLabel: 'Account & Sign Out',
            enableHaptics: enableHaptics,
            onTap: onOpenSearchHub,
            child: CircleAvatar(
              radius: 18,
              backgroundColor: palette.primaryAccent.withValues(alpha: 0.18),
              child: Text(
                user.initials,
                style: TextStyle(
                  fontSize: 11.5,
                  fontWeight: FontWeight.w800,
                  color: palette.primaryAccent,
                ),
              ),
            ),
          ),
        ],
      );
    }

    return Container(
      padding: const EdgeInsets.all(8),
      decoration: BoxDecoration(
        color: palette.mode.isDarkSurface
            ? Colors.white.withValues(alpha: 0.05)
            : Colors.black.withValues(alpha: 0.035),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: palette.cardBorder, width: 0.75),
      ),
      child: Row(
        children: <Widget>[
          CircleAvatar(
            radius: 17,
            backgroundColor: palette.primaryAccent.withValues(alpha: 0.18),
            child: Text(
              user.initials,
              style: TextStyle(
                fontSize: 11.5,
                fontWeight: FontWeight.w800,
                color: palette.primaryAccent,
              ),
            ),
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Text(
                  user.isPopulated ? user.name : 'KIUQ Member',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: palette.textPrimary,
                  ),
                ),
                Text(
                  user.role.isNotEmpty ? user.role : 'Workspace Active',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 10.5,
                    fontWeight: FontWeight.w500,
                    color: palette.textMuted,
                  ),
                ),
              ],
            ),
          ),
          _IconMiniPill(
            icon: CupertinoIcons.slider_horizontal_3,
            palette: palette,
            tooltip: 'iOS App Settings',
            onTap: onOpenAppSettings,
          ),
          const SizedBox(width: 4),
          _IconMiniPill(
            icon: CupertinoIcons.square_arrow_right,
            palette: palette,
            tooltip: 'Sign Out',
            isDestructive: true,
            onTap: onSignOut,
          ),
        ],
      ),
    );
  }

  Map<String, List<KiuqNavDestination>> _groupDestinations(
    List<KiuqNavDestination> items,
  ) {
    final map = <String, List<KiuqNavDestination>>{};
    for (final item in items) {
      final key = item.section.trim().isEmpty ? 'Workspace' : item.section.trim();
      map.putIfAbsent(key, () => <KiuqNavDestination>[]).add(item);
    }
    return map;
  }
}

class _IpadSidebarItem extends StatelessWidget {
  const _IpadSidebarItem({
    required this.destination,
    required this.palette,
    required this.expanded,
    required this.enableHaptics,
    required this.onTap,
  });

  final KiuqNavDestination destination;
  final LiquidGlassPalette palette;
  final bool expanded;
  final bool enableHaptics;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final active = destination.isActive;
    final icon = KiuqSfSymbols.resolve(destination.iconKey, active: active);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: LiquidGlassPressable(
        semanticLabel: destination.label,
        enableHaptics: enableHaptics,
        pressedScale: 0.97,
        onTap: onTap,
        child: AnimatedContainer(
          duration: LiquidGlassMotion.fast,
          curve: LiquidGlassMotion.iosFluidCurve,
          height: 42,
          padding: EdgeInsets.symmetric(horizontal: expanded ? 11 : 0),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(13),
            gradient: active
                ? LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: <Color>[
                      palette.activePillTop,
                      palette.activePillBottom,
                    ],
                  )
                : null,
            border: active
                ? Border.all(color: palette.activePillBorder, width: 0.9)
                : null,
            boxShadow: active
                ? <BoxShadow>[
                    BoxShadow(
                      color: palette.activePillGlow,
                      blurRadius: 12,
                      offset: const Offset(0, 2),
                    ),
                  ]
                : null,
          ),
          child: expanded
              ? Row(
                  children: <Widget>[
                    Icon(
                      icon,
                      size: 18,
                      color: active ? palette.iconActive : palette.iconInactive,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        destination.label,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight:
                              active ? FontWeight.w700 : FontWeight.w600,
                          color: active
                              ? palette.textPrimary
                              : palette.textSecondary,
                        ),
                      ),
                    ),
                    if (destination.hasBadge)
                      LiquidGlassBadge(
                        label: destination.effectiveBadgeLabel,
                        tone: destination.badgeTone,
                        compact: true,
                      ),
                    if (destination.actionType == KiuqNavActionType.externalUrl)
                      Padding(
                        padding: const EdgeInsets.only(left: 4),
                        child: Icon(
                          CupertinoIcons.arrow_up_right,
                          size: 12,
                          color: palette.textMuted,
                        ),
                      ),
                  ],
                )
              : Center(
                  child: Tooltip(
                    message: destination.label,
                    child: Stack(
                      clipBehavior: Clip.none,
                      children: <Widget>[
                        Icon(
                          icon,
                          size: 20,
                          color: active
                              ? palette.iconActive
                              : palette.iconInactive,
                        ),
                        if (destination.hasBadge)
                          Positioned(
                            right: -8,
                            top: -6,
                            child: LiquidGlassBadge(
                              label: destination.effectiveBadgeLabel,
                              tone: destination.badgeTone,
                              compact: true,
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
        ),
      ),
    );
  }
}

class _IconMiniPill extends StatelessWidget {
  const _IconMiniPill({
    required this.icon,
    required this.palette,
    required this.tooltip,
    required this.onTap,
    this.isDestructive = false,
  });

  final IconData icon;
  final LiquidGlassPalette palette;
  final String tooltip;
  final VoidCallback onTap;
  final bool isDestructive;

  @override
  Widget build(BuildContext context) {
    final color = isDestructive
        ? const Color(0xFFF43F5E)
        : palette.textSecondary;

    return Tooltip(
      message: tooltip,
      child: LiquidGlassPressable(
        semanticLabel: tooltip,
        onTap: onTap,
        child: Container(
          width: 30,
          height: 30,
          decoration: BoxDecoration(
            color: isDestructive
                ? const Color(0xFFF43F5E).withValues(alpha: 0.12)
                : (palette.mode.isDarkSurface
                      ? Colors.white.withValues(alpha: 0.07)
                      : Colors.black.withValues(alpha: 0.05)),
            borderRadius: BorderRadius.circular(9),
          ),
          alignment: Alignment.center,
          child: Icon(icon, size: 15, color: color),
        ),
      ),
    );
  }
}
