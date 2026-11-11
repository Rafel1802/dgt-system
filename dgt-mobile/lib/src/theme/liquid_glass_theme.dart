import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../models/shell_state.dart';

/// Apple iOS 26 Liquid Glass visual tokens and SF-Symbol icon resolver.
///
/// Designed around Apple Human Interface Guidelines:
/// - Translucent glass surfaces with optical blur (`BackdropFilter`)
/// - Specular top-edge reflection & subtle light refraction border
/// - Layered ambient shadows for floating elevation
/// - High-readability foreground contrast across Light, Dark, and Neon modes
@immutable
class LiquidGlassPalette {
  const LiquidGlassPalette({
    required this.mode,
    required this.scaffoldBackground,
    required this.glassTopFill,
    required this.glassBottomFill,
    required this.sheetFill,
    required this.cardFill,
    required this.cardBorder,
    required this.outerBorder,
    required this.specularRimTop,
    required this.specularRimBottom,
    required this.activePillTop,
    required this.activePillBottom,
    required this.activePillBorder,
    required this.activePillGlow,
    required this.primaryAccent,
    required this.secondaryAccent,
    required this.textPrimary,
    required this.textSecondary,
    required this.textMuted,
    required this.iconInactive,
    required this.iconActive,
    required this.ambientShadow,
    required this.keyShadow,
    required this.blurSigma,
  });

  final KiuqThemeMode mode;
  final Color scaffoldBackground;
  final Color glassTopFill;
  final Color glassBottomFill;
  final Color sheetFill;
  final Color cardFill;
  final Color cardBorder;
  final Color outerBorder;
  final Color specularRimTop;
  final Color specularRimBottom;
  final Color activePillTop;
  final Color activePillBottom;
  final Color activePillBorder;
  final Color activePillGlow;
  final Color primaryAccent;
  final Color secondaryAccent;
  final Color textPrimary;
  final Color textSecondary;
  final Color textMuted;
  final Color iconInactive;
  final Color iconActive;
  final Color ambientShadow;
  final Color keyShadow;
  final double blurSigma;

  static LiquidGlassPalette of(KiuqThemeMode mode) {
    switch (mode) {
      case KiuqThemeMode.light:
        return const LiquidGlassPalette(
          mode: KiuqThemeMode.light,
          scaffoldBackground: Color(0xFFF4F7FB),
          glassTopFill: Color(0xCCFFFFFF), // 80% pure crystal white
          glassBottomFill: Color(0xB3EEF3FC), // 70% cool sapphire tint
          sheetFill: Color(0xE0F6F9FE),
          cardFill: Color(0xD9FFFFFF),
          cardBorder: Color(0x1A0F172A),
          outerBorder: Color(0xCCFFFFFF),
          specularRimTop: Color(0xF2FFFFFF),
          specularRimBottom: Color(0x4DFFFFFF),
          activePillTop: Color(0xFAFFFFFF),
          activePillBottom: Color(0xE6E8EEFF),
          activePillBorder: Color(0x403B82F6),
          activePillGlow: Color(0x382563EB),
          primaryAccent: Color(0xFF2563EB),
          secondaryAccent: Color(0xFF4F46E5),
          textPrimary: Color(0xFF0F172A),
          textSecondary: Color(0xFF475569),
          textMuted: Color(0xFF64748B),
          iconInactive: Color(0xFF52627A),
          iconActive: Color(0xFF1D4ED8),
          ambientShadow: Color(0x2E0F172A),
          keyShadow: Color(0x140F172A),
          blurSigma: 28.0,
        );

      case KiuqThemeMode.dark:
        return const LiquidGlassPalette(
          mode: KiuqThemeMode.dark,
          scaffoldBackground: Color(0xFF0B1120),
          glassTopFill: Color(0xBF1E293B),
          glassBottomFill: Color(0xD60F172A),
          sheetFill: Color(0xEB0F172A),
          cardFill: Color(0xCC1E293B),
          cardBorder: Color(0x24FFFFFF),
          outerBorder: Color(0x33FFFFFF),
          specularRimTop: Color(0x47FFFFFF),
          specularRimBottom: Color(0x12FFFFFF),
          activePillTop: Color(0x5238BDF8),
          activePillBottom: Color(0x383B82F6),
          activePillBorder: Color(0x667DD3FC),
          activePillGlow: Color(0x420EA5E9),
          primaryAccent: Color(0xFF38BDF8),
          secondaryAccent: Color(0xFF818CF8),
          textPrimary: Color(0xFFF8FAFC),
          textSecondary: Color(0xFFCBD5E1),
          textMuted: Color(0xFF94A3B8),
          iconInactive: Color(0xFF94A3B8),
          iconActive: Color(0xFFFFFFFF),
          ambientShadow: Color(0x99000000),
          keyShadow: Color(0x59000000),
          blurSigma: 30.0,
        );

      case KiuqThemeMode.neon:
        return const LiquidGlassPalette(
          mode: KiuqThemeMode.neon,
          scaffoldBackground: Color(0xFF020819),
          glassTopFill: Color(0xC7061C48),
          glassBottomFill: Color(0xE0020B22),
          sheetFill: Color(0xF0030F2E),
          cardFill: Color(0xCC071A42),
          cardBorder: Color(0x4D00E1FF),
          outerBorder: Color(0x6B00E1FF),
          specularRimTop: Color(0x7300F0FF),
          specularRimBottom: Color(0x240096FF),
          activePillTop: Color(0x5900D2FF),
          activePillBottom: Color(0x420066FF),
          activePillBorder: Color(0xAD00F0FF),
          activePillGlow: Color(0x6600E1FF),
          primaryAccent: Color(0xFF00F0FF),
          secondaryAccent: Color(0xFF38BDF8),
          textPrimary: Color(0xFFF0F9FF),
          textSecondary: Color(0xFFBAE6FD),
          textMuted: Color(0xFF7DD3FC),
          iconInactive: Color(0xFF7DD3FC),
          iconActive: Color(0xFFFFFFFF),
          ambientShadow: Color(0xBF000414),
          keyShadow: Color(0x4D00C3FF),
          blurSigma: 30.0,
        );
    }
  }

  /// Dynamic iOS status bar & home-indicator overlay style matching the active theme.
  SystemUiOverlayStyle get systemOverlayStyle {
    final isDark = mode.isDarkSurface;
    return SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      statusBarIconBrightness: isDark ? Brightness.light : Brightness.dark,
      statusBarBrightness: isDark ? Brightness.dark : Brightness.light,
      systemNavigationBarColor: Colors.transparent,
      systemNavigationBarIconBrightness:
          isDark ? Brightness.light : Brightness.dark,
      systemNavigationBarDividerColor: Colors.transparent,
    );
  }
}

/// Shared motion & geometry constants aligned with iOS 26 / Apple Music fluid dynamics.
class LiquidGlassMotion {
  const LiquidGlassMotion._();

  static const Duration fast = Duration(milliseconds: 160);
  static const Duration standard = Duration(milliseconds: 280);
  static const Duration lensGlide = Duration(milliseconds: 340);
  static const Duration sheetTransition = Duration(milliseconds: 320);

  /// iOS fluid spring-like deceleration curve (`cubic-bezier(0.22, 1, 0.36, 1)`).
  static const Curve iosFluidCurve = Cubic(0.22, 1.0, 0.36, 1.0);

  /// Subtle overshoot spring for active icon selection & pill morphing.
  static const Curve iosSpringCurve = Cubic(0.28, 1.12, 0.38, 1.0);
}

/// Maps KIUQ SYSTEM destination icon keys to crisp SF-Symbols-inspired [CupertinoIcons].
class KiuqSfSymbols {
  const KiuqSfSymbols._();

  static IconData resolve(String iconKey, {bool active = false}) {
    switch (iconKey) {
      case 'home':
      case 'dashboard':
        return active ? CupertinoIcons.house_fill : CupertinoIcons.house;
      case 'boards':
      case 'kanban':
        return active
            ? CupertinoIcons.square_split_2x1_fill
            : CupertinoIcons.square_split_2x1;
      case 'tasks':
        return active
            ? CupertinoIcons.checkmark_rectangle_fill
            : CupertinoIcons.checkmark_rectangle;
      case 'websites':
        return CupertinoIcons.globe;
      case 'blog_cms':
      case 'posts':
        return active
            ? CupertinoIcons.pencil_ellipsis_rectangle
            : CupertinoIcons.square_pencil;
      case 'chat':
        return active
            ? CupertinoIcons.chat_bubble_2_fill
            : CupertinoIcons.chat_bubble_2;
      case 'notes':
      case 'private_notes':
        return active ? CupertinoIcons.lock_fill : CupertinoIcons.doc_text;
      case 'team_notes':
        return active ? CupertinoIcons.doc_text_fill : CupertinoIcons.doc_text;
      case 'sparkles':
      case 'kiuq_ai':
      case 'ai':
        return CupertinoIcons.sparkles;
      case 'hub':
      case 'circle_grid':
        return active
            ? CupertinoIcons.square_grid_2x2_fill
            : CupertinoIcons.square_grid_2x2;
      case 'members':
      case 'users':
        return active ? CupertinoIcons.person_2_fill : CupertinoIcons.person_2;
      case 'social_media':
      case 'reels':
        return active
            ? CupertinoIcons.play_rectangle_fill
            : CupertinoIcons.play_rectangle;
      case 'smm_boards':
        return active ? CupertinoIcons.calendar_today : CupertinoIcons.calendar;
      case 'gsc':
      case 'blog_analytics':
      case 'kpi':
      case 'reports':
        return active
            ? CupertinoIcons.chart_bar_alt_fill
            : CupertinoIcons.chart_bar;
      case 'connectors':
        return CupertinoIcons.link;
      case 'followup':
        return active
            ? CupertinoIcons.checkmark_seal_fill
            : CupertinoIcons.checkmark_seal;
      case 'labels':
        return active ? CupertinoIcons.tag_fill : CupertinoIcons.tag;
      case 'security':
        return active ? CupertinoIcons.shield_fill : CupertinoIcons.shield;
      case 'alarms':
      case 'notifications':
        return active ? CupertinoIcons.bell_fill : CupertinoIcons.bell;
      case 'maintenance':
        return active ? CupertinoIcons.wrench_fill : CupertinoIcons.wrench;
      case 'profile':
        return active
            ? CupertinoIcons.person_crop_circle_fill
            : CupertinoIcons.person_crop_circle;
      case 'settings':
        return active ? CupertinoIcons.gear_solid : CupertinoIcons.gear;
      case 'external':
        return CupertinoIcons.arrow_up_right_square;
      case 'logout':
        return CupertinoIcons.square_arrow_right;
      default:
        return active ? CupertinoIcons.circle_grid_hex_fill : CupertinoIcons.app;
    }
  }

  /// Vibrant Apple-style icon tile gradient used inside the App Hub launcher grid.
  static List<Color> hubTileGradient(String iconKey) {
    switch (iconKey) {
      case 'home':
      case 'dashboard':
        return const [Color(0xFF2563EB), Color(0xFF4F46E5)];
      case 'boards':
        return const [Color(0xFF7C3AED), Color(0xFF4F46E5)];
      case 'tasks':
        return const [Color(0xFFF59E0B), Color(0xFFEA580C)];
      case 'websites':
        return const [Color(0xFF10B981), Color(0xFF0D9488)];
      case 'blog_cms':
        return const [Color(0xFF6366F1), Color(0xFF8B5CF6)];
      case 'chat':
        return const [Color(0xFF06B6D4), Color(0xFF2563EB)];
      case 'notes':
      case 'team_notes':
        return const [Color(0xFFF59E0B), Color(0xFFD97706)];
      case 'private_notes':
        return const [Color(0xFF475569), Color(0xFF1E293B)];
      case 'sparkles':
      case 'kiuq_ai':
        return const [Color(0xFF8B5CF6), Color(0xFFEC4899)];
      case 'social_media':
        return const [Color(0xFFEC4899), Color(0xFFE11D48)];
      case 'smm_boards':
        return const [Color(0xFF3B82F6), Color(0xFF1D4ED8)];
      case 'gsc':
      case 'blog_analytics':
      case 'kpi':
        return const [Color(0xFF0EA5E9), Color(0xFF2563EB)];
      case 'security':
        return const [Color(0xFF059669), Color(0xFF047857)];
      default:
        return const [Color(0xFF334155), Color(0xFF0F172A)];
    }
  }
}
