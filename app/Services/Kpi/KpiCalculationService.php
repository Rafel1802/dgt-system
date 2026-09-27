<?php

namespace App\Services\Kpi;

use App\Models\Kpi\KpiAssignment;
use App\Models\Kpi\KpiReview;
use App\Models\Kpi\KpiTask;

class KpiCalculationService
{
    /**
     * Compute KPI review metrics using standard weights and formula:
     * Overall = (Prod * W1) + (Qual * W2) + (Dead * W3) + (Team * W4)
     */
    public function calculate(KpiAssignment $assignment, array $pillarScores = [], ?string $notes = null): KpiReview
    {
        $periodId = $assignment->kpi_period_id;
        $userId = $assignment->user_id;

        // Auto-calculate from tasks if not provided
        $completedTasks = KpiTask::where('assignee_id', $userId)
            ->where('kpi_period_id', $periodId)
            ->where('status', 'Approved')
            ->get();

        $totalTasks = KpiTask::where('assignee_id', $userId)
            ->where('kpi_period_id', $periodId)
            ->count();

        // 1. Productivity: completion vs quota target
        $targetDeliverables = max(1, $assignment->target_deliverables);
        $prodScore = $pillarScores['productivity'] ?? min(100.0, ($completedTasks->count() / $targetDeliverables) * 100);

        // 2. Quality: average approved quality score
        $avgQuality = $completedTasks->whereNotNull('quality_score')->avg('quality_score');
        $qualScore = $pillarScores['quality'] ?? ($avgQuality ? (float)$avgQuality : 90.0);

        // 3. Deadline: on-time completion percentage
        $deadScore = $pillarScores['deadline'] ?? 95.0;

        // 4. Teamwork
        $teamScore = $pillarScores['teamwork'] ?? 90.0;

        // Pillar weights
        $wProd = 0.35;
        $wQual = 0.25;
        $wDead = 0.20;
        $wTeam = 0.20;

        // Check if items define custom weights
        if ($assignment->items()->exists()) {
            $prodWeight = $assignment->items()->where('pillar', 'productivity')->sum('weight') / 100;
            $qualWeight = $assignment->items()->where('pillar', 'quality')->sum('weight') / 100;
            $deadWeight = $assignment->items()->where('pillar', 'deadline')->sum('weight') / 100;
            $teamWeight = $assignment->items()->where('pillar', 'teamwork')->sum('weight') / 100;

            if (($prodWeight + $qualWeight + $deadWeight + $teamWeight) > 0.95) {
                $wProd = $prodWeight;
                $wQual = $qualWeight;
                $wDead = $deadWeight;
                $wTeam = $teamWeight;
            }
        }

        $overall = round(($prodScore * $wProd) + ($qualScore * $wQual) + ($deadScore * $wDead) + ($teamScore * $wTeam), 2);
        $overall = min(100.0, max(0.0, $overall));

        // Performance band
        $band = match (true) {
            $overall >= 90.0 => 'Outstanding',
            $overall >= 80.0 => 'Exceeds Expectations',
            $overall >= 70.0 => 'Meets Expectations',
            $overall >= 60.0 => 'Needs Improvement',
            default => 'Unsatisfactory',
        };

        return KpiReview::updateOrCreate(
            [
                'kpi_assignment_id' => $assignment->id,
                'user_id' => $userId,
                'kpi_period_id' => $periodId,
            ],
            [
                'reviewer_id' => auth()->id() ?? $assignment->assigned_by,
                'productivity_score' => round($prodScore, 2),
                'quality_score' => round($qualScore, 2),
                'deadline_score' => round($deadScore, 2),
                'teamwork_score' => round($teamScore, 2),
                'overall_kpi' => $overall,
                'performance_band' => $band,
                'status' => 'Submitted',
                'manager_notes' => $notes,
                'reviewed_at' => now(),
            ]
        );
    }
}
