<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'whatsapp',
        'avatar',
        'password',
        'is_active',
        'two_factor_secret',
        'two_factor_enabled',
        'two_factor_confirmed_at',
        'last_login_at',
        'last_login_ip',
        'failed_login_count',
        'locked_until',
        'dashboard_appearance',
        'can_edit_profile',
        'team_role',
        'crm_role',
        'notification_sound',
        'music_player_enabled',
        'lunch_alarm_enabled',
        'lunch_alarm_sound',
        'offwork_alarm_sound',
        'sat_alarm_sound',
        'board_backgrounds',
        'theme',
        'google_id',
        'google_email',
    ];

    /**
     * Check if user has linked their Google Account.
     */
    public function isGoogleLinked(): bool
    {
        return !empty($this->google_id);
    }

    /**
     * Check if user can safely unlink Google (must have a password set).
     */
    public function canUnlinkGoogle(): bool
    {
        return !empty($this->password);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'is_active' => 'boolean',
            'lunch_alarm_enabled' => 'boolean',
            'dashboard_appearance' => 'array',
            'password' => 'hashed',
            'board_backgrounds' => 'array',
        ];
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = \App\Support\PhoneNumberFormatter::format($value);
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function loginAttempts(): HasMany
    {
        return $this->hasMany(LoginAttempt::class, 'email', 'email');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function deviceLogs(): HasMany
    {
        return $this->hasMany(DeviceLog::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Check if the user account is currently locked out.
     */
    public function isLockedOut(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function isCrmSupervisor(): bool
    {
        return $this->crm_role === 'supervisor';
    }

    /**
     * Get the user's uploaded avatar URL or a generated initials avatar.
     */
    public function getAvatarUrlAttribute(): string
    {
        $avatar = trim((string) $this->avatar);

        if ($avatar === '') {
            return self::initialsAvatarDataUri($this->name ?: $this->email ?: 'User', $this->avatar_color);
        }

        static $avatarCache = [];

        if (isset($avatarCache[$avatar])) {
            return $avatarCache[$avatar];
        }

        if (Str::startsWith($avatar, ['http://', 'https://', 'data:image/'])) {
            return $avatarCache[$avatar] = $avatar;
        }

        $normalized = ltrim($avatar, '/');

        if (Str::startsWith($normalized, 'storage/')) {
            $relativePath = Str::after($normalized, 'storage/');

            if (Storage::disk('public')->exists($relativePath)) {
                return $avatarCache[$avatar] = asset($normalized);
            }
        }

        if (Storage::disk('public')->exists($normalized)) {
            return $avatarCache[$avatar] = asset('storage/' . $normalized);
        }

        if (is_file(public_path('storage/' . $normalized))) {
            return $avatarCache[$avatar] = asset('storage/' . $normalized);
        }

        if (is_file(base_path('storage/' . $normalized))) {
            return $avatarCache[$avatar] = asset('storage/' . $normalized);
        }

        if (is_file(public_path($normalized))) {
            return $avatarCache[$avatar] = asset($normalized);
        }

        return $avatarCache[$avatar] = self::initialsAvatarDataUri($this->name ?: $this->email ?: 'User', $this->avatar_color);
    }

    /**
     * Get two-letter initials for compact avatar fallbacks.
     */
    public function getAvatarInitialsAttribute(): string
    {
        return self::initialsFor($this->name ?: $this->email ?: 'User');
    }

    /**
     * Get a deterministic avatar color for this user.
     */
    public function getAvatarColorAttribute(): string
    {
        $palette = [
            '#4f46e5', '#0f766e', '#be123c', '#b45309',
            '#0369a1', '#7c3aed', '#15803d', '#334155',
        ];
        $seed = strtolower((string) ($this->email ?: $this->name ?: 'user'));

        return $palette[abs(crc32($seed)) % count($palette)];
    }

    /**
     * Build a local SVG data URI so missing avatars never show broken images.
     */
    public static function initialsAvatarDataUri(string $name, string $color = '#4f46e5'): string
    {
        $initials = htmlspecialchars(self::initialsFor($name), ENT_QUOTES, 'UTF-8');
        $safeColor = preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color) ? $color : '#4f46e5';
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128">
  <rect width="128" height="128" rx="64" fill="{$safeColor}"/>
  <text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" fill="#ffffff" font-family="Inter, Arial, sans-serif" font-size="44" font-weight="800">{$initials}</text>
</svg>
SVG;

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Convert a name or email into up to two uppercase initials.
     */
    public static function initialsFor(string $name): string
    {
        $cleanName = trim(preg_replace('/\s+/', ' ', $name));

        if ($cleanName === '') {
            return 'U';
        }

        if (str_contains($cleanName, '@')) {
            $cleanName = Str::before($cleanName, '@');
        }

        $parts = array_values(array_filter(explode(' ', $cleanName)));

        if (count($parts) >= 2) {
            return Str::upper(Str::substr($parts[0], 0, 1) . Str::substr(end($parts), 0, 1));
        }

        return Str::upper(Str::substr($parts[0], 0, 2));
    }

    /**
     * Get a clickable WhatsApp URL.
     * Uses the dedicated whatsapp field first, then falls back to phone.
     */
    public function getWhatsappUrlAttribute(): ?string
    {
        $number = preg_replace('/[^0-9]/', '', $this->whatsapp ?: $this->phone ?: '');
        return $number ? "https://wa.me/{$number}" : null;
    }

    /**
     * Get a human-readable primary role name.
     */
    public function getRoleDisplayAttribute(): string
    {
        if ($this->hasRole('digital-team')) {
            return 'Digital Team';
        }
        $role = $this->roles->first(fn($r) => strpos($r->name, 'social_') !== 0);
        return $role ? $role->display_name ?? ucwords(str_replace(['-', '_'], ' ', $role->name)) : 'No Role';
    }

    /**
     * Scope: only active users.
     */
    public function scopeActive($query): mixed
    {
        return $query->where('is_active', true);
    }

    // ─── Digital Team Helpers ─────────────────────────────────────────────────

    public function canCreateBoards(): bool
    {
        return $this->canManageBoards();
    }

    public function canManageBoards(): bool
    {
        if ($this->hasAnyRole(['super-admin', 'admin-digital', 'admin', 'supervisor'])) {
            return true;
        }

        $teamRole = strtolower($this->team_role ?? '');
        return str_contains($teamRole, 'head') || str_contains($teamRole, 'qc') || str_contains($teamRole, 'supervisor');
    }

    // ─── Website Permissions & Roles ──────────────────────────────────────────

    public function hasWebsiteAccess(): bool
    {
        if ($this->hasAnyRole(['super-admin', 'admin-digital'])) {
            return true;
        }
        return \App\Models\WebsiteMember::where('user_id', $this->id)->exists();
    }

    public function websiteRole(): ?string
    {
        $member = \App\Models\WebsiteMember::where('user_id', $this->id)->first();
        return $member ? $member->role : null;
    }

    public function isWebsiteViewer(): bool
    {
        return strtolower($this->websiteRole() ?? '') === 'viewer';
    }

    public function canApproveWebsiteQc(): bool
    {
        $role = $this->websiteRole();
        if ($role && strtolower($role) === 'qc') {
            return true;
        }
        if ($this->isQc()) {
            return true;
        }
        
        return $this->hasAnyRole(['super-admin', 'admin-digital']);
    }

    public function canApproveWebsiteSupervisor(): bool
    {
        // Explicitly block QC users from Supervisor approval even if they have blanket admin-digital roles
        $role = $this->websiteRole();
        if ($role && strtolower($role) === 'qc') {
            return false;
        }
        if ($this->isQc()) {
            return false;
        }

        if ($this->hasAnyRole(['super-admin', 'admin-digital'])) {
            return true;
        }
        
        return $role && strtolower($role) === 'supervisor';
    }

    public function canUpdateWebsiteProgress(): bool
    {
        if ($this->isWebsiteViewer()) {
            return false;
        }

        if ($this->hasAnyRole(['super-admin', 'admin-digital', 'digital-team', 'boss'])) {
            return true;
        }
        $role = $this->websiteRole();
        return $role && in_array(strtolower($role), ['developer', 'qc', 'supervisor']);
    }

    /**
     * TRUE only for QC team members (team_role contains 'QC').
     * Used to gate the Personal Report menu and QC-specific data filtering.
     */
    public function isQc(): bool
    {
        return str_contains(strtolower($this->team_role ?? ''), 'qc');
    }

    /**
     * TRUE only for Supervisors (role = admin-digital, or team_role contains 'Supervisor').
     * Super-admin is intentionally excluded — they have their own dashboards.
     */
    public function isSupervisorRole(): bool
    {
        if ($this->hasAnyRole(['admin-digital', 'supervisor'])) {
            return true;
        }
        return str_contains(strtolower($this->team_role ?? ''), 'supervisor');
    }

    /**
     * TRUE if the user is a QC member OR a Supervisor.
     * Deliberately excludes super-admin so the Personal Report menu stays focused.
     */
    public function isQcOrSupervisor(): bool
    {
        return $this->isQc() || $this->isSupervisorRole();
    }

    /**
     * Relationship to boards where the user is an explicit member.
     */
    public function boards(): BelongsToMany
    {
        return $this->belongsToMany(Board::class, 'board_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Popup ads seen or interacted with by this user.
     */
    public function popupAds(): BelongsToMany
    {
        return $this->belongsToMany(PopupAd::class, 'popup_ad_user')
            ->withPivot(['last_shown_at', 'is_clicked'])
            ->withTimestamps();
    }

    /**
     * TRUE if the user is Boss, Supervisor, or QC, or has Approval Queue permissions.
     * These roles already have the Approval Queue, so they do not see the member board menu.
     */
    public function isBossSupervisorOrQc(): bool
    {
        if ($this->hasAnyRole(['boss', 'supervisor', 'admin-digital', 'super-admin'])) {
            return true;
        }
        return $this->isQc() || $this->isSupervisorRole() || $this->can('kanban.approve');
    }

    /**
     * Get planning boards with active task counts for this member.
     * Excludes Workflow boards and excludes Boss/Supervisor/QC.
     */
    public function getPlanningBoardsWithTaskCounts()
    {
        if ($this->isBossSupervisorOrQc()) {
            return collect();
        }

        $tomorrowDate = \Carbon\Carbon::tomorrow()->toDateString();

        $boards = Board::query()
            ->with(['workspace', 'lists' => fn($q) => $q->orderBy('position')])
            ->where('is_archived', false)
            ->where('is_hidden', false)
            ->where('name', 'not like', '%Workflow%')
            ->where(function ($q) {
                $q->whereNull('type')->orWhere('type', '!=', 'smm');
            })
            ->where('name', 'not like', '%SMM%')
            ->whereDoesntHave('workspace', function ($wq) {
                $wq->where('name', 'like', '%Social Media%')
                   ->orWhere('name', 'like', '%SMM%');
            })
            ->where(function ($q) {
                $q->whereHas('members', fn($m) => $m->where('users.id', $this->id))
                  ->orWhere('created_by', $this->id)
                  ->orWhereHas('cards.assignees', fn($aq) => $aq->where('users.id', $this->id))
                  ->orWhereHas('workspace.members', fn($wm) => $wm->where('users.id', $this->id));
            })
            ->withCount([
                'cards as user_tasks_count' => function ($q) {
                    $q->where('is_archived', false)
                      ->whereHas('assignees', fn($aq) => $aq->where('users.id', $this->id));
                },
                'cards as due_tomorrow_count' => function ($q) use ($tomorrowDate) {
                    $q->where('is_archived', false)
                      ->where('deadline', $tomorrowDate)
                      ->whereNull('approved_at')
                      ->whereNull('block_completed_at')
                      ->whereNotIn('status', ['approved', 'done', 'completed'])
                      ->whereHas('assignees', fn($aq) => $aq->where('users.id', $this->id));
                },
                'cards as total_active_cards_count' => function ($q) {
                    $q->where('is_archived', false);
                },
                'cards as total_assigned_cards_count' => function ($q) {
                    $q->where('is_archived', false)
                      ->whereHas('assignees');
                }
            ])
            ->orderBy('position')
            ->orderBy('id', 'desc')
            ->get();

        foreach ($boards as $board) {
            if ($board->user_tasks_count == 0 && $board->total_assigned_cards_count == 0) {
                $board->user_tasks_count = $board->total_active_cards_count;
            }
        }

        return $boards->sortByDesc('user_tasks_count')->values();
    }

    /**
     * Get deadline warning tasks (due tomorrow, due today, overdue)
     * and task summary counts for active cards assigned to this member in planning boards.
     */
    public function getDeadlineWarningData(): array
    {
        $default = [
            'warningTasks'       => collect(),
            'dueTomorrowCount'   => 0,
            'dueTodayCount'      => 0,
            'overdueCount'       => 0,
            'totalWarningCount'  => 0,
            'totalTasksCount'    => 0,
            'userCards'          => collect(),
            'boards'             => collect(),
        ];

        if ($this->isBossSupervisorOrQc()) {
            return $default;
        }

        $boards = $this->getPlanningBoardsWithTaskCounts();
        $allBoardIds = $boards->pluck('id')->toArray();

        if (empty($allBoardIds)) {
            return $default;
        }

        $now = \Carbon\Carbon::now();
        $nowStr = $now->toDateTimeString();
        $todayDate = \Carbon\Carbon::today()->toDateString();
        $tomorrowDate = \Carbon\Carbon::tomorrow()->toDateString();

        // Query active cards assigned to this user in these planning boards
        $userCards = \App\Models\Card::with(['board.workspace', 'boardList', 'labels', 'checklists.items', 'assignees'])
            ->whereIn('board_id', $allBoardIds)
            ->where('is_archived', false)
            ->whereHas('assignees', fn($aq) => $aq->where('users.id', $this->id))
            ->orderByRaw("CASE WHEN deadline IS NOT NULL AND deadline < '{$nowStr}' THEN 0 ELSE 1 END")
            ->orderBy('deadline')
            ->orderBy('created_at', 'desc')
            ->get();

        // Batch pre-fetch approved sync group IDs to check twin workflow cards in 1 efficient query
        $syncGroupIds = $userCards->pluck('sync_group_id')->filter()->unique()->toArray();
        $approvedSyncGroupIds = [];
        if (!empty($syncGroupIds)) {
            $approvedSyncGroupIds = \App\Models\Card::whereIn('sync_group_id', $syncGroupIds)
                ->where(function ($q) {
                    $q->whereNotNull('approved_at')
                      ->orWhereNotNull('block_completed_at')
                      ->orWhereIn('status', ['approved', 'done', 'completed'])
                      ->orWhereHas('boardList', function ($lq) {
                          $lq->where('name', 'like', '%approved%')
                             ->orWhere('name', 'like', '%done%')
                             ->orWhere('name', 'like', '%completed%')
                             ->orWhere('name', 'like', '%complete%')
                             ->orWhere('name', 'like', '%finish%')
                             ->orWhere('name', 'like', '%published%');
                      });
                })
                ->pluck('sync_group_id')
                ->unique()
                ->toArray();
        }

        $warningTasks = collect();
        $dueTomorrowCount = 0;
        $dueTodayCount = 0;
        $overdueCount = 0;

        foreach ($userCards as $card) {
            $dueDate = $card->due_at ?? ($card->deadline ? \Carbon\Carbon::parse($card->deadline) : null);
            if (!$dueDate) {
                continue;
            }

            // Exclude already completed or approved tasks (approved_at, block_completed_at, status, list, twin cards, 100% checklists)
            if ($card->isCompletedOrApproved($approvedSyncGroupIds)) {
                continue;
            }

            $cardDateStr = $dueDate->toDateString();

            if ($cardDateStr === $tomorrowDate || (!$dueDate->isPast() && !$dueDate->isToday() && $now->diffInHours($dueDate, false) <= 36 && $now->diffInHours($dueDate, false) >= 0)) {
                $dueTomorrowCount++;
                $warningTasks->push([
                    'card'               => $card,
                    'title'              => $card->title,
                    'board_name'         => $card->board?->name ?? 'Board',
                    'workspace_name'     => $card->board?->workspace?->name ?? 'Team',
                    'list_name'          => $card->boardList?->name ?? 'List',
                    'deadline'           => $dueDate,
                    'due_type'           => 'tomorrow',
                    'badge_text'         => 'Due in 1 Day (Tomorrow)',
                    'badge_color'        => 'amber',
                    'due_formatted'      => $dueDate->format('M d, Y') . ($dueDate->format('H:i') !== '00:00' ? ' at ' . $dueDate->format('g:i A') : ''),
                    'open_url'           => $card->board ? route('boards.show', $card->board) . '?card=' . $card->id : '#',
                ]);
            } elseif ($cardDateStr === $todayDate) {
                $dueTodayCount++;
                $warningTasks->push([
                    'card'               => $card,
                    'title'              => $card->title,
                    'board_name'         => $card->board?->name ?? 'Board',
                    'workspace_name'     => $card->board?->workspace?->name ?? 'Team',
                    'list_name'          => $card->boardList?->name ?? 'List',
                    'deadline'           => $dueDate,
                    'due_type'           => 'today',
                    'badge_text'         => 'Due Today',
                    'badge_color'        => 'orange',
                    'due_formatted'      => $dueDate->format('M d, Y') . ($dueDate->format('H:i') !== '00:00' ? ' at ' . $dueDate->format('g:i A') : ''),
                    'open_url'           => $card->board ? route('boards.show', $card->board) . '?card=' . $card->id : '#',
                ]);
            } elseif ($dueDate->isPast()) {
                $overdueCount++;
                $warningTasks->push([
                    'card'               => $card,
                    'title'              => $card->title,
                    'board_name'         => $card->board?->name ?? 'Board',
                    'workspace_name'     => $card->board?->workspace?->name ?? 'Team',
                    'list_name'          => $card->boardList?->name ?? 'List',
                    'deadline'           => $dueDate,
                    'due_type'           => 'overdue',
                    'badge_text'         => 'Overdue (' . $dueDate->diffForHumans($now, true) . ')',
                    'badge_color'        => 'rose',
                    'due_formatted'      => $dueDate->format('M d, Y'),
                    'open_url'           => $card->board ? route('boards.show', $card->board) . '?card=' . $card->id : '#',
                ]);
            }
        }

        // Sort warning tasks: Overdue first, then Today, then Tomorrow
        $warningTasks = $warningTasks->sortBy(function ($item) {
            return match($item['due_type']) {
                'overdue' => 1,
                'today'   => 2,
                'tomorrow'=> 3,
                default   => 4,
            };
        })->values();

        return [
            'warningTasks'          => $warningTasks,
            'dueTomorrowCount'      => $dueTomorrowCount,
            'dueTodayCount'         => $dueTodayCount,
            'overdueCount'          => $overdueCount,
            'totalWarningCount'     => $warningTasks->count(),
            'totalTasksCount'       => $userCards->count(),
            'userCards'             => $userCards,
            'boards'                => $boards,
            'approvedSyncGroupIds'  => $approvedSyncGroupIds,
        ];
    }

    /**
     * TRUE if the user is a QC reviewer OR a Super-Admin / Admin.
     * Gates access to the System Health & Maintenance Diagnostics Center.
     */
    public function canAccessMaintenance(): bool
    {
        return $this->isQc() || $this->hasAnyRole(['super-admin', 'admin', 'admin-digital']);
    }

    /**
     * Check if user is allowed to pin/unpin notifications for all users.
     * Allowed for: super-admin, admin-digital, social_qc, boss, supervisor, and any QC role.
     */
    public function canPinNotifications(): bool
    {
        $allowedRoles = ['super-admin', 'admin-digital', 'social_qc', 'boss', 'supervisor', 'admin'];
        $userRole = strtolower($this->role ?? '');

        if (in_array($userRole, $allowedRoles, true) || str_contains($userRole, 'qc')) {
            return true;
        }

        if ($this->isQc() || str_contains(strtolower($this->team_role ?? ''), 'qc')) {
            return true;
        }

        try {
            if ($this->hasAnyRole($allowedRoles)) {
                return true;
            }
        } catch (\Throwable $e) {
            // fallback if roles relation is not loaded or db not connected
        }

        return false;
    }

    /**
     * Check if user is allowed to add and manage clock sounds.
     * Allowed for: super-admin, supervisor, and QC roles.
     */
    public function canManageClockSounds(): bool
    {
        try {
            if ($this->hasRole('super-admin')) {
                return true;
            }

            if ($this->isQcOrSupervisor()) {
                return true;
            }

            $supervisorRoles = ['supervisor', 'ebay-supervisor', 'logistic-supervisor', 'admin-digital', 'admin'];
            if ($this->hasAnyRole($supervisorRoles)) {
                return true;
            }
        } catch (\Throwable $e) {
            // fallback if roles relation is not loaded or db not connected
        }

        if (str_contains(strtolower($this->team_role ?? ''), 'supervisor')) {
            return true;
        }

        if ($this->isQc() || str_contains(strtolower($this->team_role ?? ''), 'qc') || str_contains(strtolower($this->name ?? ''), 'qc')) {
            return true;
        }

        return false;
    }

    /**
     * Check if user has supervisor or admin-digital role.
     * Shift clock alarms are turned off by default only for these roles.
     */
    public function isSupervisorOrAdminDigital(): bool
    {
        try {
            if ($this->hasAnyRole(['admin-digital', 'supervisor', 'ebay-supervisor', 'logistic-supervisor'])) {
                return true;
            }
        } catch (\Throwable $e) {}

        $teamRole = strtolower($this->team_role ?? '');
        if (
            str_contains($teamRole, 'supervisor') ||
            str_contains($teamRole, 'admin digital') ||
            str_contains($teamRole, 'admin-digital')
        ) {
            return true;
        }

        return false;
    }

    /**
     * Check if shift clock alarm (12 PM lunch, 4 PM off work & Saturday 11 AM) is enabled for this user.
     * Default: FALSE for all users. Users can turn it on individually from their profile settings.
     */
    public function isLunchAlarmEnabled(): bool
    {
        if ($this->lunch_alarm_enabled !== null) {
            return (bool) $this->lunch_alarm_enabled;
        }

        return false;
    }

    /**
     * Check if user can configure shift alarm duration in seconds.
     * Allowed for: Super Admin and QC.
     */
    public function canSetAlarmDuration(): bool
    {
        try {
            if ($this->hasRole('super-admin')) {
                return true;
            }
            if ($this->isQc() || $this->hasRole('social_qc')) {
                return true;
            }
        } catch (\Throwable $e) {}

        $teamRole = strtolower($this->team_role ?? '');
        if (str_contains($teamRole, 'super admin') || str_contains($teamRole, 'qc')) {
            return true;
        }

        $name = strtolower($this->name ?? '');
        if (str_contains($name, 'qc')) {
            return true;
        }

        return false;
    }

    /**
     * Get the user's lunch alarm sound (12:00 PM), defaulting to lunch.wav.
     */
    public function getLunchAlarmSoundAttribute($value): string
    {
        return $value ?: 'lunch.wav';
    }

    /**
     * Get the active lunch alarm sound URL (12:00 PM).
     */
    public function getLunchAlarmSoundUrlAttribute(): string
    {
        $sound = $this->lunch_alarm_sound ?: 'lunch.wav';
        $path = 'clocksound/' . $sound;
        if (file_exists(public_path($path))) {
            return asset($path);
        }
        return asset('clocksound/lunch.wav');
    }

    /**
     * Get the user's off work alarm sound (4:00 PM), defaulting to funny.wav.
     */
    public function getOffworkAlarmSoundAttribute($value): string
    {
        return $value ?: 'funny.wav';
    }

    /**
     * Get the active off work alarm sound URL (4:00 PM).
     */
    public function getOffworkAlarmSoundUrlAttribute(): string
    {
        $sound = $this->offwork_alarm_sound ?: 'funny.wav';
        $path = 'clocksound/' . $sound;
        if (file_exists(public_path($path))) {
            return asset($path);
        }
        return asset('clocksound/funny.wav');
    }

    /**
     * Get the user's Saturday half day alarm sound (11:00 AM), defaulting to funny.wav.
     */
    public function getSatAlarmSoundAttribute($value): string
    {
        return $value ?: 'funny.wav';
    }

    /**
     * Get the active Saturday half day alarm sound URL (11:00 AM).
     */
    public function getSatAlarmSoundUrlAttribute(): string
    {
        $sound = $this->sat_alarm_sound ?: 'funny.wav';
        $path = 'clocksound/' . $sound;
        if (file_exists(public_path($path))) {
            return asset($path);
        }
        return asset('clocksound/funny.wav');
    }


    /**
     * Which notification "modules" this user should see in their bell —
     * CRM board notifications shouldn't leak to digital/board staff and
     * vice versa, since the two are otherwise-disjoint teams sharing one
     * notifications table with no built-in module scoping. super-admin and
     * boss span both worlds and see everything. A user matching neither
     * bucket (e.g. a role added later that isn't listed here yet) also
     * sees everything, rather than silently going notification-blind.
     */
    public function notificationModules(): array
    {
        return ['digital'];
    }

    /**
     * Convert the model instance to an array for serialization.
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        $array['avatar'] = $this->avatar_url;
        $array['avatar_url'] = $this->avatar_url;
        $array['avatar_initials'] = $this->avatar_initials;
        $array['avatar_color'] = $this->avatar_color;
        return $array;
    }

    /**
     * Check if user can clear a board list (Super Admin, Mr Dara QC, Lyza, Sreypich).
     */
    public function canClearBoardList(): bool
    {
        if ($this->hasRole('super-admin')) {
            return true;
        }

        $allowedUsernames = ['dara', 'lyza', 'sreypich'];
        $allowedIds = [12, 22, 23];

        if (in_array($this->id, $allowedIds) || in_array(strtolower($this->username ?? ''), $allowedUsernames)) {
            return true;
        }

        $name = strtolower($this->name ?? '');
        if (str_contains($name, 'dara') && (str_contains($name, 'qc') || str_contains(strtolower($this->team_role ?? ''), 'qc'))) {
            return true;
        }

        return false;
    }
}

