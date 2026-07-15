<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    ];

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
            'dashboard_appearance' => 'array',
            'password' => 'hashed',
        ];
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

    /**
     * Get the user's uploaded avatar URL or a generated initials avatar.
     */
    public function getAvatarUrlAttribute(): string
    {
        $avatar = trim((string) $this->avatar);

        if ($avatar !== '') {
            if (Str::startsWith($avatar, ['http://', 'https://', 'data:image/'])) {
                return $avatar;
            }

            $normalized = ltrim($avatar, '/');

            if (Str::startsWith($normalized, 'storage/')) {
                $relativePath = Str::after($normalized, 'storage/');

                if (Storage::disk('public')->exists($relativePath)) {
                    return asset($normalized);
                }
            }

            if (Storage::disk('public')->exists($normalized)) {
                return asset('storage/' . $normalized);
            }

            if (is_file(public_path('storage/' . $normalized))) {
                return asset('storage/' . $normalized);
            }

            if (is_file(base_path('storage/' . $normalized))) {
                return asset('storage/' . $normalized);
            }

            if (is_file(public_path($normalized))) {
                return asset($normalized);
            }
        }

        return self::initialsAvatarDataUri($this->name ?: $this->email ?: 'User', $this->avatar_color);
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
        $role = $this->roles->first();
        return $role ? $role->display_name ?? ucfirst($role->name) : 'No Role';
    }

    /**
     * Scope: only active users.
     */
    public function scopeActive($query): mixed
    {
        return $query->where('is_active', true);
    }

    /**
     * Every CRM-facing role — anyone staffing the Website/eBay/Logistic/Tech
     * Support pipelines, not just the general sales-crm tier. Kept as one
     * constant so this scope, isCrmMember(), and the crm.* route middleware
     * (routes/web.php) can't drift apart on who counts as "CRM staff".
     */
    public const CRM_ROLES = ['super-admin', 'admin-crm', 'sales-crm', 'boss', 'tech-support', 'ebay-supervisor', 'logistic-supervisor', 'ebay-team', 'logistic-team'];

    /**
     * Scope: only users who belong to the CRM team.
     * Excludes digital-team, admin-digital, social_admin, social_qc.
     */
    public function scopeCrmMembers($query): mixed
    {
        return $query->whereHas('roles', function ($q) {
            $q->whereIn('name', self::CRM_ROLES);
        })->where('is_active', true);
    }

    /**
     * Check whether this user is a CRM team member.
     */
    public function isCrmMember(): bool
    {
        return $this->hasAnyRole(self::CRM_ROLES);
    }

    /**
     * Get a CRM-specific role display label.
     * Returns: Boss | CRM Supervisor | CRM Member
     */
    public function getCrmRoleDisplayAttribute(): string
    {
        if ($this->hasAnyRole(['super-admin', 'boss'])) {
            return 'Boss';
        }
        if ($this->isCrmSupervisor()) {
            return 'CRM Supervisor';
        }
        if ($this->hasRole('sales-crm')) {
            return 'CRM Member';
        }
        return $this->role_display;
    }

    /**
     * "CRM Supervisor" tier: either the admin-crm role, or a sales-crm user
     * whose crm_role sub-flag is set to 'supervisor'. This is the same rule
     * getCrmRoleDisplayAttribute() surfaces as a label — kept here as the
     * single source of truth so authorization checks and the display label
     * can never drift apart.
     */
    public function isCrmSupervisor(): bool
    {
        return $this->hasRole('admin-crm') || ($this->hasRole('sales-crm') && $this->crm_role === 'supervisor');
    }

    /**
     * Whether this user may delete entity-level CRM records (Leads, Customers,
     * Products, eBay records/stores/offers, Shipments, Trucking Companies) in
     * the given domain. super-admin, boss, a CRM Supervisor, ebay-supervisor,
     * and logistic-supervisor can all delete anywhere — same tier as the CRM
     * admin, not scoped to their own domain. Routine in-page removals (e.g.
     * removing one customer from a shipment) are NOT gated by this — only
     * whole-record deletes.
     */
    public function canDeleteCrmRecords(string $domain = 'website'): bool
    {
        return $this->hasAnyRole(['super-admin', 'boss', 'ebay-supervisor', 'logistic-supervisor']) || $this->isCrmSupervisor();
    }

    public function canCreateBoards(): bool
    {
        // All active members can create boards
        return true;
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
        if ($this->hasAnyRole(['super-admin', 'admin-digital'])) {
            return true;
        }
        $role = $this->websiteRole();
        return $role && strtolower($role) === 'qc';
    }

    public function canApproveWebsiteSupervisor(): bool
    {
        if ($this->hasAnyRole(['super-admin', 'admin-digital'])) {
            return true;
        }
        $role = $this->websiteRole();
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
        if ($this->hasAnyRole(['super-admin', 'boss'])) {
            return ['crm', 'digital'];
        }

        $modules = [];
        if ($this->hasAnyRole(['admin-crm', 'sales-crm', 'tech-support', 'ebay-supervisor', 'logistic-supervisor', 'ebay-team', 'logistic-team'])) {
            $modules[] = 'crm';
        }
        if ($this->hasAnyRole(['admin-digital', 'digital-team', 'social_admin', 'social_qc'])) {
            $modules[] = 'digital';
        }

        return $modules ?: ['crm', 'digital'];
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
        $array['crm_role_display'] = $this->crm_role_display;
        return $array;
    }
}
