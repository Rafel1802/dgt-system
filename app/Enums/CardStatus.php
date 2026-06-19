<?php

namespace App\Enums;

enum CardStatus: string
{
    case Todo       = 'todo';
    case InProgress = 'in_progress';
    case Review     = 'review';
    case Approved   = 'approved';
    case Rejected   = 'rejected';
    case Done       = 'done';

    public function label(): string
    {
        return match($this) {
            self::Todo       => 'To Do',
            self::InProgress => 'In Progress',
            self::Review     => 'Under Review',
            self::Approved   => 'Approved',
            self::Rejected   => 'Rejected',
            self::Done       => 'Done',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Todo       => 'slate',
            self::InProgress => 'blue',
            self::Review     => 'amber',
            self::Approved   => 'emerald',
            self::Rejected   => 'rose',
            self::Done       => 'violet',
        };
    }

    public function bgClass(): string
    {
        return match($this) {
            self::Todo       => 'bg-slate-100 border-slate-300',
            self::InProgress => 'bg-blue-50 border-blue-200',
            self::Review     => 'bg-amber-50 border-amber-200',
            self::Approved   => 'bg-emerald-50 border-emerald-200',
            self::Rejected   => 'bg-rose-50 border-rose-200',
            self::Done       => 'bg-violet-50 border-violet-200',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Todo       => 'badge-slate',
            self::InProgress => 'badge-sky',
            self::Review     => 'badge-amber',
            self::Approved   => 'badge-emerald',
            self::Rejected   => 'badge-rose',
            self::Done       => 'badge-violet',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::Todo       => '○',
            self::InProgress => '◑',
            self::Review     => '◷',
            self::Approved   => '✓',
            self::Rejected   => '✗',
            self::Done       => '●',
        };
    }

    /** Transitions allowed per role (what statuses this status can move TO) */
    public function allowedTransitions(string $roleName): array
    {
        return match($roleName) {
            'super-admin', 'admin' => CardStatus::cases(), // can move anywhere
            'supervisor' => match($this) {
                self::Review    => [self::Approved, self::Rejected],
                self::Approved  => [self::Done],
                default         => [self::Todo, self::InProgress, self::Review],
            },
            'staff', 'digital-team', 'sales-crm' => match($this) {
                self::Todo       => [self::InProgress],
                self::InProgress => [self::Review],
                self::Rejected   => [self::InProgress], // re-submit after rejection
                default          => [],
            },
            default => [],
        };
    }

    public static function columns(): array
    {
        return [
            self::Todo,
            self::InProgress,
            self::Review,
            self::Approved,
            self::Rejected,
            self::Done,
        ];
    }
}
