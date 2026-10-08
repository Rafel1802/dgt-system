<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SocialMediaClass extends Model
{
    protected $fillable = ['name', 'description', 'icon', 'color', 'status', 'created_by', 'external_link', 'position'];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SocialMediaItem::class, 'social_media_class_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function activeItems(): HasMany
    {
        return $this->hasMany(SocialMediaItem::class, 'social_media_class_id')
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(SocialMediaPost::class, 'social_media_class_id');
    }

    public function analytics(): BelongsToMany
    {
        return $this->belongsToMany(
            SocialMediaAnalytic::class,
            'social_media_analytic_class',
            'social_media_class_id',
            'social_media_analytic_id'
        )->withTimestamps()->orderByDesc('date_from');
    }

    /** Users assigned to this class */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'social_media_class_user')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    /**
     * Map common aliases/variations of class names to their canonical SocialMediaClass name.
     * Supports single or multi-select delimited cluster strings (e.g. "ImpossibleMachinery, MachineryAsia.Online").
     */
    public static function canonicalName(?string $name): string
    {
        $raw = trim((string)$name);
        if ($raw === '') {
            return '';
        }

        if (preg_match('/[,&+\/\n]/', $raw)) {
            $parts = preg_split('/[,&+\/\n]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
            $canonicalParts = [];
            foreach ($parts as $part) {
                $c = self::canonicalSingleName($part);
                if (!empty($c) && !in_array($c, $canonicalParts)) {
                    $canonicalParts[] = $c;
                }
            }
            return !empty($canonicalParts) ? implode(', ', $canonicalParts) : $raw;
        }

        return self::canonicalSingleName($raw);
    }

    /**
     * Canonical name for a single class/cluster name.
     */
    public static function canonicalSingleName(?string $name): string
    {
        $raw = trim((string)$name);
        if ($raw === '') {
            return '';
        }

        $clean = preg_replace('/[^a-z0-9]/', '', strtolower($raw));

        // 1. MachineryBargains (aliases: Machinery.Bargains, Machinery Bargains, machinerybargains, etc.)
        if ($clean === 'machinerybargains' || str_contains($clean, 'machinerybargain')) {
            return 'MachineryBargains';
        }

        // 2. SkidSteers (aliases: SkidSteer, Skid Steer, SkidSteers, skidsteer, american skidsteer, etc.)
        if (str_starts_with($clean, 'skidsteer') || str_contains($clean, 'skidsteer')) {
            return 'SkidSteers';
        }

        // 3. MiniExca (aliases: Mini Exca, MiniExcavator, miniexca, etc.)
        if (str_starts_with($clean, 'miniexca')) {
            return 'MiniExca';
        }

        // 4. MachineryAsia.Online (aliases: MachineryAsia Online, Machinery Asia Online, etc.)
        if (str_contains($clean, 'machineryasia') && str_contains($clean, 'online')) {
            return 'MachineryAsia.Online';
        }

        // 5. MachineryAsia (FB)
        if (str_contains($clean, 'machineryasia') && (str_contains($clean, 'fb') || str_contains($clean, 'facebook'))) {
            return 'MachineryAsia (FB)';
        }

        // 6. ImpossibleMachinery
        if ($clean === 'impossiblemachinery' || str_contains($clean, 'impossiblemachin')) {
            return 'ImpossibleMachinery';
        }

        // 7. Machinery.Org
        if ($clean === 'machineryorg') {
            return 'Machinery.Org';
        }

        return $raw;
    }

    /**
     * Resolve a raw cluster string (single or multi-select) into an array of canonical cluster names
     * and a canonical string.
     */
    public static function resolveClusters(?string $raw): array
    {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return ['names' => [], 'canonical_string' => ''];
        }

        $parts = preg_split('/[,&+\/\n]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $names = [];
        foreach ($parts as $p) {
            $c = self::canonicalSingleName(trim($p));
            if (!empty($c) && !in_array($c, $names)) {
                $names[] = $c;
            }
        }

        return [
            'names' => $names,
            'canonical_string' => implode(', ', $names),
        ];
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isAssignedTo(User $user): bool
    {
        return $this->assignedUsers()->where('user_id', $user->id)->exists();
    }

    /**
     * Can the given user view this class?
     * Admins/QC see all; social_user sees only assigned ones.
     */
    public function isVisibleTo(User $user): bool
    {
        if ($user->hasAnyRole(['super-admin', 'admin-digital', 'boss', 'digital-team', 'social_admin', 'social_qc'])) {
            return true;
        }
        return $this->isAssignedTo($user);
    }

    // ─── Summary Stats ────────────────────────────────────────────────────────

    public function summaryFor(?User $user = null): array
    {
        $query = $this->posts();

        if ($user && !$user->hasAnyRole(['super-admin', 'admin-digital', 'social_admin', 'social_qc', 'boss'])) {
            $query = $query->where('user_id', $user->id);
        }

        $posts = $query->get();

        return [
            'total_items'  => $this->activeItems()->count(),
            'total_posts'  => $posts->count(),
            'completed'    => $posts->where('is_completed', true)->count(),
            'pending'      => $posts->where('is_completed', false)->count(),
            'qc_checked'   => $posts->where('is_checked', true)->count(),
            'qc_pending'   => $posts->where('is_completed', true)->where('is_checked', false)->count(),
        ];
    }
}
