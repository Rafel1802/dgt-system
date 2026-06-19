<?php

namespace App\Enums;

enum DealStage: string
{
    case NewLead       = 'new_lead';
    case Contacted     = 'contacted';
    case Qualified     = 'qualified';
    case ProposalSent  = 'proposal_sent';
    case Negotiating   = 'negotiating';
    case Won           = 'won';
    case Lost          = 'lost';

    public function label(): string
    {
        return match($this) {
            self::NewLead      => 'New Lead',
            self::Contacted    => 'Contacted',
            self::Qualified    => 'Qualified',
            self::ProposalSent => 'Proposal Sent',
            self::Negotiating  => 'Negotiating',
            self::Won          => 'Won',
            self::Lost         => 'Lost',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::NewLead      => '#94a3b8',
            self::Contacted    => '#3b82f6',
            self::Qualified    => '#8b5cf6',
            self::ProposalSent => '#f59e0b',
            self::Negotiating  => '#f97316',
            self::Won          => '#10b981',
            self::Lost         => '#ef4444',
        };
    }

    public function bgClass(): string
    {
        return match($this) {
            self::NewLead      => 'bg-slate-50 border-slate-200',
            self::Contacted    => 'bg-blue-50 border-blue-200',
            self::Qualified    => 'bg-violet-50 border-violet-200',
            self::ProposalSent => 'bg-amber-50 border-amber-200',
            self::Negotiating  => 'bg-orange-50 border-orange-200',
            self::Won          => 'bg-emerald-50 border-emerald-200',
            self::Lost         => 'bg-rose-50 border-rose-200',
        };
    }

    public function defaultProbability(): int
    {
        return match($this) {
            self::NewLead      => 5,
            self::Contacted    => 15,
            self::Qualified    => 30,
            self::ProposalSent => 50,
            self::Negotiating  => 75,
            self::Won          => 100,
            self::Lost         => 0,
        };
    }

    public static function pipelineColumns(): array
    {
        return [
            self::NewLead,
            self::Contacted,
            self::Qualified,
            self::ProposalSent,
            self::Negotiating,
            self::Won,
            self::Lost,
        ];
    }
}
