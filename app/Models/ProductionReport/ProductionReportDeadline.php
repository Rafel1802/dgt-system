<?php

namespace App\Models\ProductionReport;

use Illuminate\Database\Eloquent\Model;

class ProductionReportDeadline extends Model
{
    protected $table = 'production_report_deadlines';

    protected $fillable = [
        'scope_key',
        'timezone',
        'daily_deadline_time',
        'daily_reminder_time',
        'weekly_deadline_day',
        'weekly_deadline_time',
        'weekly_reminder_day',
        'weekly_reminder_time',
        'weekly_prep_day',
        'weekly_prep_time',
        'weekly_period_start_day',
        'weekly_period_end_day',
        'monthly_deadline_day',
        'monthly_deadline_mode',
        'monthly_deadline_time',
        'allow_head_daily_submission',
        'allow_head_monthly_submission',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'monthly_deadline_day' => 'integer',
            'allow_head_daily_submission' => 'boolean',
            'allow_head_monthly_submission' => 'boolean',
        ];
    }

    public static function current(): self
    {
        try {
            return static::firstOrCreate(
                ['scope_key' => 'global'],
                [
                    'timezone' => 'Asia/Phnom_Penh',
                    'daily_deadline_time' => '17:00',
                    'daily_reminder_time' => '16:45',
                    'weekly_deadline_day' => 'Saturday',
                    'weekly_deadline_time' => '08:00',
                    'weekly_reminder_day' => 'Saturday',
                    'weekly_reminder_time' => '07:00',
                    'weekly_prep_day' => 'Friday',
                    'weekly_prep_time' => '16:00',
                    'weekly_period_start_day' => 'Monday',
                    'weekly_period_end_day' => 'Friday',
                    'monthly_deadline_day' => 28,
                    'monthly_deadline_mode' => 'last_day_of_month',
                    'monthly_deadline_time' => '17:00',
                    'allow_head_daily_submission' => false,
                    'allow_head_monthly_submission' => true,
                ]
            );
        } catch (\Throwable) {
            return new self([
                'scope_key' => 'global',
                'timezone' => 'Asia/Phnom_Penh',
                'daily_deadline_time' => '17:00',
                'daily_reminder_time' => '16:45',
                'weekly_deadline_day' => 'Saturday',
                'weekly_deadline_time' => '08:00',
                'weekly_reminder_day' => 'Saturday',
                'weekly_reminder_time' => '07:00',
                'weekly_prep_day' => 'Friday',
                'weekly_prep_time' => '16:00',
                'weekly_period_start_day' => 'Monday',
                'weekly_period_end_day' => 'Friday',
                'monthly_deadline_day' => 28,
                'monthly_deadline_mode' => 'last_day_of_month',
                'monthly_deadline_time' => '17:00',
                'allow_head_daily_submission' => false,
                'allow_head_monthly_submission' => true,
            ]);
        }
    }
}

