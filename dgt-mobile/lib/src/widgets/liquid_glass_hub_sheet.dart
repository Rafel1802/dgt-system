import 'dart:math' as math;

import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

import '../models/shell_state.dart';
import '../theme/liquid_glass_theme.dart';
import 'liquid_glass_surface.dart';

/// Opens the Apple iOS 26 Liquid Glass App Hub & Instant Search Bottom Sheet.
Future<void> showLiquidGlassHubSheet({
  required BuildContext context,
  required LiquidGlassPalette palette,
  required KiuqShellState shellState,
  required ValueChanged<KiuqNavDestination> onSelectDestination,
  required ValueChanged<KiuqThemeMode> onThemeChanged,
  required VoidCallback onShareCurrentPage,
  required VoidCallback onReloadPage,
  required VoidCallback onOpenAppSettings,
  required VoidCallback onOpenNotifications,
  required VoidCallback onSignOut,
  bool enableHaptics = true,
}) async {
  await showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: false,
    backgroundColor: Colors.transparent,
    barrierColor: Colors.black.withValues(alpha: 0.42),
    builder: (sheetContext) {
      return LiquidGlassHubSheet(
        palette: palette,
        shellState: shellState,
        enableHaptics: enableHaptics,
        onSelectDestination: (dest) {
          Navigator.of(sheetContext).pop();
          onSelectDestination(dest);
        },
        onThemeChanged: onThemeChanged,
        onShareCurrentPage: () {
          Navigator.of(sheetContext).pop();
          onShareCurrentPage();
        },
        onReloadPage: () {
          Navigator.of(sheetContext).pop();
          onReloadPage();
        },
        onOpenAppSettings: () {
          Navigator.of(sheetContext).pop();
          onOpenAppSettings();
        },
        onOpenNotifications: () {
          Navigator.of(sheetContext).pop();
          onOpenNotifications();
        },
        onSignOut: () {
          Navigator.of(sheetContext).pop();
          onSignOut();
        },
      );
    },
  );
}

/// Interactive App Hub, Spotlight Module Filter, Quick Actions, and Workspace Switcher.
class LiquidGlassHubSheet extends StatefulWidget {
  const LiquidGlassHubSheet({
    super.key,
    required this.palette,
    required this.shellState,
    required this.onSelectDestination,
    required this.onThemeChanged,
    required this.onShareCurrentPage,
    required this.onReloadPage,
    required this.onOpenAppSettings,
    required this.onOpenNotifications,
    required this.onSignOut,
    this.enableHaptics = true,
  });

  final LiquidGlassPalette palette;
  final KiuqShellState shellState;
  final ValueChanged<KiuqNavDestination> onSelectDestination;
  final ValueChanged<KiuqThemeMode> onThemeChanged;
  final VoidCallback onShareCurrentPage;
  final VoidCallback onReloadPage;
  final VoidCallback onOpenAppSettings;
  final VoidCallback onOpenNotifications;
  final VoidCallback onSignOut;
  final bool enableHaptics;

  @override
  State<LiquidGlassHubSheet> createState() => _LiquidGlassHubSheetState();
}

class _LiquidGlassHubSheetState extends State<LiquidGlassHubSheet> {
  final TextEditingController _searchController = TextEditingController();
  late KiuqThemeMode _selectedTheme;
  String _query = '';

  @override
  void initState() {
    super.initState();
    _selectedTheme = widget.shellState.themeMode;
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  List<KiuqNavDestination> get _allDestinations {
    final raw = widget.shellState.hubDestinations.isNotEmpty
        ? widget.shellState.hubDestinations
        : KiuqDefaultNavigation.defaultHubDestinations(
            widget.shellState.currentUrl,
          );
    return raw;
  }

  List<KiuqNavDestination> get _filteredDestinations {
    final trimmed = _query.trim().toLowerCase();
    if (trimmed.isEmpty) return _allDestinations;
    return _allDestinations.where((item) {
      return item.label.toLowerCase().contains(trimmed) ||
          item.section.toLowerCase().contains(trimmed) ||
          item.subtitle.toLowerCase().contains(trimmed) ||
          item.url.toLowerCase().contains(trimmed);
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    final palette = LiquidGlassPalette.of(_selectedTheme);
    final media = MediaQuery.of(context);
    final bottomSafe = math.max(
      MediaQuery.viewPaddingOf(context).bottom,
      media.padding.bottom,
    );
    final keyboardBottom = media.viewInsets.bottom;
    final maxHeight = media.size.height * (media.size.shortestSide >= 600 ? 0.84 : 0.88);
    final filtered = _filteredDestinations;
    final grouped = _groupBySection(filtered);

    return Padding(
      padding: EdgeInsets.only(bottom: keyboardBottom),
      child: Align(
        alignment: Alignment.bottomCenter,
        child: ConstrainedBox(
          constraints: BoxConstraints(
            maxWidth: 620,
            maxHeight: maxHeight,
          ),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 10),
            child: LiquidGlassSurface(
              key: const ValueKey<String>('kiuq_liquid_glass_hub_sheet'),
              palette: palette,
              borderRadius: const BorderRadius.vertical(
                top: Radius.circular(34),
                bottom: Radius.circular(28),
              ),
              padding: EdgeInsets.fromLTRB(
                18,
                10,
                18,
                math.max(bottomSafe, 14) + 4,
              ),
              child: Material(
                color: Colors.transparent,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    // Drag Grabber Handle
                    Container(
                      width: 40,
                      height: 4.5,
                      decoration: BoxDecoration(
                        color: palette.textMuted.withValues(alpha: 0.36),
                        borderRadius: BorderRadius.circular(999),
                      ),
                    ),
                    const SizedBox(height: 14),

                    // User Header & Quick Utility Pills
                    _buildUserHeader(palette),
                    const SizedBox(height: 14),

                    // Instant Spotlight Search Bar
                    _buildSearchField(palette),
                    const SizedBox(height: 12),

                    // Scrollable Content
                    Flexible(
                      child: ListView(
                        shrinkWrap: true,
                        physics: const BouncingScrollPhysics(),
                        padding: EdgeInsets.zero,
                        children: <Widget>[
                          if (_query.trim().isEmpty) ...<Widget>[
                            _buildQuickActionsRow(palette),
                            const SizedBox(height: 16),
                            _buildQuickGrid(palette),
                            const SizedBox(height: 16),
                          ],

                          if (filtered.isEmpty)
                            Padding(
                              padding: const EdgeInsets.symmetric(vertical: 28),
                              child: Column(
                                children: <Widget>[
                                  Icon(
                                    CupertinoIcons.search,
                                    size: 30,
                                    color: palette.textMuted,
                                  ),
                                  const SizedBox(height: 8),
                                  Text(
                                    'No matching KIUQ module for "$_query"',
                                    textAlign: TextAlign.center,
                                    style: TextStyle(
                                      fontSize: 13.5,
                                      fontWeight: FontWeight.w600,
                                      color: palette.textSecondary,
                                    ),
                                  ),
                                ],
                              ),
                            )
                          else
                            for (final entry in grouped.entries) ...<Widget>[
                              Padding(
                                padding: const EdgeInsets.fromLTRB(4, 4, 4, 7),
                                child: Text(
                                  entry.key.toUpperCase(),
                                  style: TextStyle(
                                    fontSize: 10.5,
                                    fontWeight: FontWeight.w800,
                                    letterSpacing: 0.85,
                                    color: palette.textMuted,
                                  ),
                                ),
                              ),
                              _buildSectionCard(palette, entry.value),
                              const SizedBox(height: 14),
                            ],

                          // Appearance Switcher
                          Padding(
                            padding: const EdgeInsets.fromLTRB(4, 2, 4, 7),
                            child: Text(
                              'APPEARANCE',
                              style: TextStyle(
                                fontSize: 10.5,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 0.85,
                                color: palette.textMuted,
                              ),
                            ),
                          ),
                          LiquidGlassThemeSegment(
                            currentMode: _selectedTheme,
                            palette: palette,
                            enableHaptics: widget.enableHaptics,
                            onChanged: (mode) {
                              setState(() => _selectedTheme = mode);
                              widget.onThemeChanged(mode);
                            },
                          ),
                          const SizedBox(height: 14),

                          // Sign Out Button
                          LiquidGlassPressable(
                            enableHaptics: widget.enableHaptics,
                            pressedScale: 0.98,
                            onTap: widget.onSignOut,
                            child: Container(
                              height: 46,
                              decoration: BoxDecoration(
                                color: const Color(
                                  0xFFF43F5E,
                                ).withValues(alpha: 0.12),
                                borderRadius: BorderRadius.circular(16),
                                border: Border.all(
                                  color: const Color(
                                    0xFFF43F5E,
                                  ).withValues(alpha: 0.28),
                                  width: 0.85,
                                ),
                              ),
                              alignment: Alignment.center,
                              child: const Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: <Widget>[
                                  Icon(
                                    CupertinoIcons.square_arrow_right,
                                    size: 17,
                                    color: Color(0xFFF43F5E),
                                  ),
                                  SizedBox(width: 8),
                                  Text(
                                    'Sign Out of KIUQ SYSTEM',
                                    style: TextStyle(
                                      fontSize: 13.5,
                                      fontWeight: FontWeight.w700,
                                      color: Color(0xFFF43F5E),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                          const SizedBox(height: 6),
                        ],
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

  Widget _buildUserHeader(LiquidGlassPalette palette) {
    final user = widget.shellState.user;
    return Row(
      children: <Widget>[
        Container(
          width: 42,
          height: 42,
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: <Color>[Color(0xFF2563EB), Color(0xFF4F46E5)],
            ),
            borderRadius: BorderRadius.circular(14),
            boxShadow: <BoxShadow>[
              BoxShadow(
                color: const Color(0xFF2563EB).withValues(alpha: 0.30),
                blurRadius: 10,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          alignment: Alignment.center,
          child: Text(
            user.initials,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 14,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        const SizedBox(width: 11),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                user.isPopulated ? user.name : 'KIUQ SYSTEM Hub',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 15.5,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.3,
                  color: palette.textPrimary,
                ),
              ),
              Text(
                user.role.isNotEmpty
                    ? user.role
                    : widget.shellState.pageTitle,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: palette.textSecondary,
                ),
              ),
            ],
          ),
        ),
        LiquidGlassPressable(
          semanticLabel: 'Close Hub',
          enableHaptics: widget.enableHaptics,
          onTap: () => Navigator.of(context).pop(),
          child: Container(
            width: 32,
            height: 32,
            decoration: BoxDecoration(
              color: palette.mode.isDarkSurface
                  ? Colors.white.withValues(alpha: 0.10)
                  : Colors.black.withValues(alpha: 0.06),
              shape: BoxShape.circle,
            ),
            alignment: Alignment.center,
            child: Icon(
              CupertinoIcons.xmark,
              size: 14,
              color: palette.textSecondary,
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildSearchField(LiquidGlassPalette palette) {
    return Container(
      height: 42,
      decoration: BoxDecoration(
        color: palette.mode.isDarkSurface
            ? Colors.white.withValues(alpha: 0.08)
            : Colors.black.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: palette.cardBorder, width: 0.8),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 12),
      child: Row(
        children: <Widget>[
          Icon(CupertinoIcons.search, size: 17, color: palette.textMuted),
          const SizedBox(width: 8),
          Expanded(
            child: TextField(
              key: const ValueKey<String>('kiuq_hub_search_input'),
              controller: _searchController,
              onChanged: (value) => setState(() => _query = value),
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w500,
                color: palette.textPrimary,
              ),
              decoration: InputDecoration(
                isDense: true,
                border: InputBorder.none,
                hintText: 'Search boards, websites, notes, tools...',
                hintStyle: TextStyle(
                  fontSize: 13.5,
                  fontWeight: FontWeight.w500,
                  color: palette.textMuted,
                ),
              ),
            ),
          ),
          if (_query.isNotEmpty)
            GestureDetector(
              onTap: () {
                _searchController.clear();
                setState(() => _query = '');
              },
              child: Icon(
                CupertinoIcons.clear_circled_solid,
                size: 17,
                color: palette.textMuted,
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildQuickActionsRow(LiquidGlassPalette palette) {
    final notifCount = widget.shellState.unreadNotifications;
    return Row(
      children: <Widget>[
        Expanded(
          child: _QuickActionChip(
            palette: palette,
            icon: CupertinoIcons.bell_fill,
            label: notifCount > 0 ? 'Alerts ($notifCount)' : 'Alerts',
            onTap: widget.onOpenNotifications,
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: _QuickActionChip(
            palette: palette,
            icon: CupertinoIcons.share,
            label: 'Share',
            onTap: widget.onShareCurrentPage,
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: _QuickActionChip(
            palette: palette,
            icon: CupertinoIcons.arrow_clockwise,
            label: 'Reload',
            onTap: widget.onReloadPage,
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: _QuickActionChip(
            palette: palette,
            icon: CupertinoIcons.slider_horizontal_3,
            label: 'iOS App',
            onTap: widget.onOpenAppSettings,
          ),
        ),
      ],
    );
  }

  Widget _buildQuickGrid(LiquidGlassPalette palette) {
    final highlights = _allDestinations.take(8).toList();
    if (highlights.isEmpty) return const SizedBox.shrink();

    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      padding: EdgeInsets.zero,
      itemCount: highlights.length,
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 4,
        mainAxisSpacing: 10,
        crossAxisSpacing: 8,
        childAspectRatio: 0.94,
      ),
      itemBuilder: (context, index) {
        final item = highlights[index];
        final gradient = KiuqSfSymbols.hubTileGradient(item.iconKey);

        return LiquidGlassPressable(
          semanticLabel: item.label,
          enableHaptics: widget.enableHaptics,
          onTap: () => widget.onSelectDestination(item),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 6),
            decoration: BoxDecoration(
              color: item.isActive
                  ? palette.primaryAccent.withValues(alpha: 0.12)
                  : (palette.mode.isDarkSurface
                        ? Colors.white.withValues(alpha: 0.04)
                        : Colors.white.withValues(alpha: 0.65)),
              borderRadius: BorderRadius.circular(18),
              border: Border.all(
                color: item.isActive
                    ? palette.activePillBorder
                    : palette.cardBorder,
                width: 0.85,
              ),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: <Widget>[
                Stack(
                  clipBehavior: Clip.none,
                  children: <Widget>[
                    Container(
                      width: 38,
                      height: 38,
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                          colors: gradient,
                        ),
                        borderRadius: BorderRadius.circular(12),
                        boxShadow: <BoxShadow>[
                          BoxShadow(
                            color: gradient.first.withValues(alpha: 0.28),
                            blurRadius: 8,
                            offset: const Offset(0, 3),
                          ),
                        ],
                      ),
                      alignment: Alignment.center,
                      child: Icon(
                        KiuqSfSymbols.resolve(item.iconKey, active: true),
                        color: Colors.white,
                        size: 19,
                      ),
                    ),
                    if (item.hasBadge)
                      Positioned(
                        right: -6,
                        top: -5,
                        child: LiquidGlassBadge(
                          label: item.effectiveBadgeLabel,
                          tone: item.badgeTone,
                          compact: true,
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  item.label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 10.5,
                    fontWeight: FontWeight.w700,
                    letterSpacing: -0.2,
                    color: palette.textPrimary,
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _buildSectionCard(
    LiquidGlassPalette palette,
    List<KiuqNavDestination> items,
  ) {
    return Container(
      decoration: BoxDecoration(
        color: palette.cardFill,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: palette.cardBorder, width: 0.8),
      ),
      child: Column(
        children: List<Widget>.generate(items.length, (index) {
          final item = items[index];
          final isLast = index == items.length - 1;
          final gradient = KiuqSfSymbols.hubTileGradient(item.iconKey);

          return Column(
            children: <Widget>[
              LiquidGlassPressable(
                enableHaptics: widget.enableHaptics,
                pressedScale: 0.985,
                onTap: () => widget.onSelectDestination(item),
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 13,
                    vertical: 11,
                  ),
                  child: Row(
                    children: <Widget>[
                      Container(
                        width: 32,
                        height: 32,
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                            colors: gradient,
                          ),
                          borderRadius: BorderRadius.circular(9),
                        ),
                        alignment: Alignment.center,
                        child: Icon(
                          KiuqSfSymbols.resolve(item.iconKey, active: true),
                          color: Colors.white,
                          size: 16,
                        ),
                      ),
                      const SizedBox(width: 11),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Text(
                              item.label,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                fontSize: 13.5,
                                fontWeight: item.isActive
                                    ? FontWeight.w800
                                    : FontWeight.w600,
                                color: item.isActive
                                    ? palette.primaryAccent
                                    : palette.textPrimary,
                              ),
                            ),
                            if (item.subtitle.isNotEmpty)
                              Text(
                                item.subtitle,
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
                      if (item.hasBadge) ...<Widget>[
                        LiquidGlassBadge(
                          label: item.effectiveBadgeLabel,
                          tone: item.badgeTone,
                        ),
                        const SizedBox(width: 6),
                      ],
                      Icon(
                        item.actionType == KiuqNavActionType.externalUrl
                            ? CupertinoIcons.arrow_up_right
                            : CupertinoIcons.chevron_right,
                        size: 14,
                        color: palette.textMuted,
                      ),
                    ],
                  ),
                ),
              ),
              if (!isLast)
                Padding(
                  padding: const EdgeInsets.only(left: 56),
                  child: Divider(
                    height: 1,
                    thickness: 0.65,
                    color: palette.cardBorder,
                  ),
                ),
            ],
          );
        }),
      ),
    );
  }

  Map<String, List<KiuqNavDestination>> _groupBySection(
    List<KiuqNavDestination> items,
  ) {
    final map = <String, List<KiuqNavDestination>>{};
    for (final item in items) {
      final section = item.section.trim().isEmpty
          ? 'Workspace'
          : item.section.trim();
      map.putIfAbsent(section, () => <KiuqNavDestination>[]).add(item);
    }
    return map;
  }
}

class _QuickActionChip extends StatelessWidget {
  const _QuickActionChip({
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
      semanticLabel: label,
      onTap: onTap,
      child: Container(
        height: 40,
        padding: const EdgeInsets.symmetric(horizontal: 6),
        decoration: BoxDecoration(
          color: palette.mode.isDarkSurface
              ? Colors.white.withValues(alpha: 0.065)
              : Colors.black.withValues(alpha: 0.045),
          borderRadius: BorderRadius.circular(13),
          border: Border.all(color: palette.cardBorder, width: 0.8),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: <Widget>[
            Icon(icon, size: 14, color: palette.primaryAccent),
            const SizedBox(width: 5),
            Flexible(
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 11.5,
                  fontWeight: FontWeight.w700,
                  color: palette.textPrimary,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
