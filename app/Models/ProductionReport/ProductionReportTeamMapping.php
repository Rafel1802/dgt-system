<?php

namespace App\Models\ProductionReport;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionReportTeamMapping extends Model
{
    protected $table = 'production_report_team_mappings';

    public const ROLE_SUPERVISOR = 'supervisor';
    public const ROLE_HEAD = 'head';
    public const ROLE_MEMBER = 'member';

    protected $fillable = [
        'user_id',
        'team_code',
        'team_name',
        'role_in_reporting',
        'position_title',
        'can_submit_daily',
        'can_submit_weekly',
        'can_submit_monthly',
        'is_active',
        'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'can_submit_daily' => 'boolean',
            'can_submit_weekly' => 'boolean',
            'can_submit_monthly' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}

