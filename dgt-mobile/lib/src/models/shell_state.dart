import 'package:flutter/foundation.dart';

/// Supported visual themes synchronized with KIUQ SYSTEM (`light`, `dark`, `neon`).
enum KiuqThemeMode {
  light,
  dark,
  neon;

  static KiuqThemeMode fromString(String? raw) {
    switch (raw?.trim().toLowerCase()) {
      case 'neon':
        return KiuqThemeMode.neon;
      case 'dark':
        return KiuqThemeMode.dark;
      default:
        return KiuqThemeMode.light;
    }
  }

  String get wireValue {
    switch (this) {
      case KiuqThemeMode.light:
        return 'light';
      case KiuqThemeMode.dark:
        return 'dark';
      case KiuqThemeMode.neon:
        return 'neon';
    }
  }

  String get displayLabel {
    switch (this) {
      case KiuqThemeMode.light:
        return 'Light';
      case KiuqThemeMode.dark:
        return 'Dark';
      case KiuqThemeMode.neon:
        return 'Neon';
    }
  }

  bool get isDarkSurface => this != KiuqThemeMode.light;
}

/// Action triggered when a navigation item is selected.
enum KiuqNavActionType {
  route,
  kiuqAi,
  hub,
  notifications,
  externalUrl,
  logout,
}

/// Badge accent tone matching KIUQ SYSTEM status badges.
enum KiuqBadgeTone {
  alert,
  warning,
  info,
  success,
}

/// Represents an existing KIUQ SYSTEM menu destination synchronized from the live DOM
/// (preserving role-based permissions, active state, and live unread/task counters).
@immutable
class KiuqNavDestination {
  const KiuqNavDestination({
    required this.id,
    required this.label,
    required this.iconKey,
    this.url = '',
    this.subtitle = '',
    this.section = 'Main',
    this.actionType = KiuqNavActionType.route,
    this.useTurbo = true,
    this.isActive = false,
    this.badgeCount = 0,
    this.badgeText = '',
    this.badgeTone = KiuqBadgeTone.alert,
  });

  final String id;
  final String label;
  final String iconKey;
  final String url;
  final String subtitle;
  final String section;
  final KiuqNavActionType actionType;
  final bool useTurbo;
  final bool isActive;
  final int badgeCount;
  final String badgeText;
  final KiuqBadgeTone badgeTone;

  String get effectiveBadgeLabel {
    if (badgeText.isNotEmpty) return badgeText;
    if (badgeCount <= 0) return '';
    return badgeCount > 99 ? '99+' : '$badgeCount';
  }

  bool get hasBadge => effectiveBadgeLabel.isNotEmpty;

  KiuqNavDestination copyWith({
    String? id,
    String? label,
    String? iconKey,
    String? url,
    String? subtitle,
    String? section,
    KiuqNavActionType? actionType,
    bool? useTurbo,
    bool? isActive,
    int? badgeCount,
    String? badgeText,
    KiuqBadgeTone? badgeTone,
  }) {
    return KiuqNavDestination(
      id: id ?? this.id,
      label: label ?? this.label,
      iconKey: iconKey ?? this.iconKey,
      url: url ?? this.url,
      subtitle: subtitle ?? this.subtitle,
      section: section ?? this.section,
      actionType: actionType ?? this.actionType,
      useTurbo: useTurbo ?? this.useTurbo,
      isActive: isActive ?? this.isActive,
      badgeCount: badgeCount ?? this.badgeCount,
      badgeText: badgeText ?? this.badgeText,
      badgeTone: badgeTone ?? this.badgeTone,
    );
  }

  factory KiuqNavDestination.fromJson(Map<String, dynamic> json) {
    final rawAction = (json['actionType'] ?? 'route').toString();
    final actionType = switch (rawAction) {
      'kiuqAi' => KiuqNavActionType.kiuqAi,
      'hub' => KiuqNavActionType.hub,
      'notifications' => KiuqNavActionType.notifications,
      'externalUrl' => KiuqNavActionType.externalUrl,
      'logout' => KiuqNavActionType.logout,
      _ => KiuqNavActionType.route,
    };

    final rawTone = (json['badgeTone'] ?? 'alert').toString();
    final badgeTone = switch (rawTone) {
      'warning' => KiuqBadgeTone.warning,
      'info' => KiuqBadgeTone.info,
      'success' => KiuqBadgeTone.success,
      _ => KiuqBadgeTone.alert,
    };

    return KiuqNavDestination(
      id: (json['id'] ?? '').toString(),
      label: (json['label'] ?? '').toString(),
      iconKey: (json['iconKey'] ?? 'circle_grid').toString(),
      url: (json['url'] ?? '').toString(),
      subtitle: (json['subtitle'] ?? '').toString(),
      section: (json['section'] ?? 'Main').toString(),
      actionType: actionType,
      useTurbo: json['useTurbo'] != false,
      isActive: json['isActive'] == true,
      badgeCount: int.tryParse('${json['badgeCount'] ?? 0}') ?? 0,
      badgeText: (json['badgeText'] ?? '').toString(),
      badgeTone: badgeTone,
    );
  }
}

/// Summary of the currently authenticated KIUQ SYSTEM user.
@immutable
class KiuqUserProfile {
  const KiuqUserProfile({
    this.name = '',
    this.role = '',
    this.email = '',
    this.avatarUrl = '',
  });

  final String name;
  final String role;
  final String email;
  final String avatarUrl;

  bool get isPopulated => name.trim().isNotEmpty;

  String get initials {
    final cleaned = name.replaceAll(RegExp(r'\s*\([^)]*\)'), '').trim();
    if (cleaned.isEmpty) return 'KQ';
    final parts = cleaned.split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
    if (parts.length >= 2) {
      return '${parts.first[0]}${parts.last[0]}'.toUpperCase();
    }
    return parts.first.substring(0, parts.first.length.clamp(1, 2)).toUpperCase();
  }

  factory KiuqUserProfile.fromJson(Map<String, dynamic>? json) {
    if (json == null) return const KiuqUserProfile();
    return KiuqUserProfile(
      name: (json['name'] ?? '').toString().trim(),
      role: (json['role'] ?? '').toString().trim(),
      email: (json['email'] ?? '').toString().trim(),
      avatarUrl: (json['avatarUrl'] ?? '').toString().trim(),
    );
  }
}

/// Default fallback destinations matching KIUQ SYSTEM's real routes before/during DOM sync.
class KiuqDefaultNavigation {
  const KiuqDefaultNavigation._();

  static List<KiuqNavDestination> primaryTabsForPath(String currentPath) {
    final path = _cleanPath(currentPath);
    return <KiuqNavDestination>[
      KiuqNavDestination(
        id: 'home',
        label: 'Home',
        iconKey: 'home',
        url: '/dashboard',
        subtitle: 'System overview & live activity',
        section: 'Main',
        isActive: path == '/dashboard' || path == '/',
      ),
      KiuqNavDestination(
        id: 'boards',
        label: 'Boards',
        iconKey: 'boards',
        url: '/boards',
        subtitle: 'Workspaces, lists & planning cards',
        section: 'Digital Team',
        isActive:
            (path == '/boards' || path.startsWith('/boards/')) &&
            !path.startsWith('/boards/personal-report'),
      ),
      KiuqNavDestination(
        id: 'tasks',
        label: 'Tasks',
        iconKey: 'tasks',
        url: '/tasks-count',
        subtitle: 'Assigned planning tasks & deadlines',
        section: 'Digital Team',
        isActive: path == '/tasks-count',
      ),
      KiuqNavDestination(
        id: 'websites',
        label: 'Websites',
        iconKey: 'websites',
        url: '/websites',
        subtitle: 'Website portfolio, QC & status',
        section: 'Websites & SEO',
        isActive: path == '/websites',
      ),
      KiuqNavDestination(
        id: 'blog_cms',
        label: 'Blog CMS',
        iconKey: 'blog_cms',
        url: '/websites/posts',
        subtitle: 'Multi-website blog publishing',
        section: 'Websites & SEO',
        isActive:
            path.startsWith('/websites/posts') ||
            path.startsWith('/websites/blogs'),
      ),
      KiuqNavDestination(
        id: 'chat',
        label: 'Chat',
        iconKey: 'chat',
        url: '/chat',
        subtitle: 'Real-time team messaging',
        section: 'Main',
        useTurbo: false,
        isActive: path == '/chat' || path.startsWith('/chat/'),
      ),
      KiuqNavDestination(
        id: 'notes',
        label: 'Notes',
        iconKey: 'notes',
        url: '/notes/private',
        subtitle: 'Private & shared team notes',
        section: 'Notes',
        isActive: path.startsWith('/notes'),
      ),
      const KiuqNavDestination(
        id: 'kiuq_ai',
        label: 'KIUQ AI',
        iconKey: 'sparkles',
        subtitle: 'AI workspace & content studios',
        section: 'Main',
        actionType: KiuqNavActionType.kiuqAi,
      ),
    ];
  }

  static List<KiuqNavDestination> defaultHubDestinations(String currentPath) {
    final path = _cleanPath(currentPath);
    return <KiuqNavDestination>[
      ...primaryTabsForPath(currentPath),
      KiuqNavDestination(
        id: 'members',
        label: 'All Members',
        iconKey: 'members',
        url: '/members',
        subtitle: 'Team directory & roles',
        section: 'Main',
        isActive: path.startsWith('/members'),
      ),
      KiuqNavDestination(
        id: 'team_notes',
        label: 'Team Notes',
        iconKey: 'team_notes',
        url: '/notes/team',
        subtitle: 'Shared team documentation',
        section: 'Notes',
        isActive: path.startsWith('/notes/team'),
      ),
      KiuqNavDestination(
        id: 'private_notes',
        label: 'Private Notes',
        iconKey: 'private_notes',
        url: '/notes/private',
        subtitle: 'Encrypted personal notes',
        section: 'Notes',
        isActive: path.startsWith('/notes/private'),
      ),
      KiuqNavDestination(
        id: 'social_media',
        label: 'Reels & SMM Hub',
        iconKey: 'social_media',
        url: '/social-media',
        subtitle: 'Social media classes & analytics',
        section: 'Digital Operations',
        isActive: path.startsWith('/social-media'),
      ),
      KiuqNavDestination(
        id: 'smm_boards',
        label: 'SMM Planning',
        iconKey: 'smm_boards',
        url: '/smm-boards',
        subtitle: 'Content calendar & schedules',
        section: 'Digital Operations',
        isActive: path.startsWith('/smm-boards'),
      ),
      KiuqNavDestination(
        id: 'gsc',
        label: 'Google Search Console',
        iconKey: 'gsc',
        url: '/websites/google-search-console',
        subtitle: 'Clicks, impressions & sitemaps',
        section: 'Websites & SEO',
        isActive: path.startsWith('/websites/google-search-console'),
      ),
      KiuqNavDestination(
        id: 'blog_analytics',
        label: 'Blog Analytics',
        iconKey: 'blog_analytics',
        url: '/websites/blogs',
        subtitle: 'Fleet blog performance & sync',
        section: 'Websites & SEO',
        isActive: path.startsWith('/websites/blogs'),
      ),
      KiuqNavDestination(
        id: 'followup',
        label: 'Follow Up',
        iconKey: 'followup',
        url: '/websites?tab=follow-up',
        subtitle: 'Website follow-up tracking',
        section: 'Websites & SEO',
        isActive: false,
      ),
      KiuqNavDestination(
        id: 'profile',
        label: 'Account Profile',
        iconKey: 'profile',
        url: '/profile',
        subtitle: 'Profile, sounds, Google & 2FA',
        section: 'Account & System',
        isActive: path.startsWith('/profile'),
      ),
      KiuqNavDestination(
        id: 'settings',
        label: 'Account Settings',
        iconKey: 'settings',
        url: '/settings',
        subtitle: 'Security & password settings',
        section: 'Account & System',
        isActive: path == '/settings',
      ),
    ];
  }

  static String _cleanPath(String rawUrlOrPath) {
    final uri = Uri.tryParse(rawUrlOrPath);
    final path = (uri?.path ?? rawUrlOrPath).trim();
    if (path.isEmpty) return '/';
    final stripped = path.replaceFirst(RegExp(r'/+$'), '');
    return stripped.isEmpty ? '/' : stripped;
  }
}

/// Complete live state of the KIUQ SYSTEM WebView + Native Liquid Glass shell.
@immutable
class KiuqShellState {
  const KiuqShellState({
    this.isAuthenticated = false,
    this.currentUrl = '',
    this.pageTitle = 'KIUQ SYSTEM',
    this.canGoBack = false,
    this.themeMode = KiuqThemeMode.light,
    this.unreadNotifications = 0,
    this.unreadChat = 0,
    this.tasksCount = 0,
    this.primaryTabs = const <KiuqNavDestination>[],
    this.hubDestinations = const <KiuqNavDestination>[],
    this.user = const KiuqUserProfile(),
    this.isFullScreenModalOpen = false,
    this.isChatThreadOpen = false,
  });

  final bool isAuthenticated;
  final String currentUrl;
  final String pageTitle;
  final bool canGoBack;
  final KiuqThemeMode themeMode;
  final int unreadNotifications;
  final int unreadChat;
  final int tasksCount;
  final List<KiuqNavDestination> primaryTabs;
  final List<KiuqNavDestination> hubDestinations;
  final KiuqUserProfile user;
  final bool isFullScreenModalOpen;
  final bool isChatThreadOpen;

  /// Combined badge count shown on the Hub orb when notifications or secondary items require attention.
  int get totalAttentionCount => unreadNotifications;

  KiuqShellState copyWith({
    bool? isAuthenticated,
    String? currentUrl,
    String? pageTitle,
    bool? canGoBack,
    KiuqThemeMode? themeMode,
    int? unreadNotifications,
    int? unreadChat,
    int? tasksCount,
    List<KiuqNavDestination>? primaryTabs,
    List<KiuqNavDestination>? hubDestinations,
    KiuqUserProfile? user,
    bool? isFullScreenModalOpen,
    bool? isChatThreadOpen,
  }) {
    return KiuqShellState(
      isAuthenticated: isAuthenticated ?? this.isAuthenticated,
      currentUrl: currentUrl ?? this.currentUrl,
      pageTitle: pageTitle ?? this.pageTitle,
      canGoBack: canGoBack ?? this.canGoBack,
      themeMode: themeMode ?? this.themeMode,
      unreadNotifications: unreadNotifications ?? this.unreadNotifications,
      unreadChat: unreadChat ?? this.unreadChat,
      tasksCount: tasksCount ?? this.tasksCount,
      primaryTabs: primaryTabs ?? this.primaryTabs,
      hubDestinations: hubDestinations ?? this.hubDestinations,
      user: user ?? this.user,
      isFullScreenModalOpen:
          isFullScreenModalOpen ?? this.isFullScreenModalOpen,
      isChatThreadOpen: isChatThreadOpen ?? this.isChatThreadOpen,
    );
  }
}
