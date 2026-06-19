<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Enums\DealStage;
use App\Models\Customer;
use App\Models\CustomerInteraction;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CrmService
{
    /**
     * Search and filter the customer database.
     */
    public function searchCustomers(array $filters, User $user): LengthAwarePaginator
    {
        $query = Customer::with(['assignee:id,name,avatar', 'creator:id,name'])
            ->withCount(['interactions', 'deals']);

        // Role-based visibility: sales-crm only sees their assigned customers
        if ($user->hasRole('sales-crm') && ! $user->hasAnyRole(['admin', 'supervisor', 'super-admin'])) {
            $query->assignedTo($user->id);
        }

        // Search
        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Status filter
        if (! empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        // Source filter
        if (! empty($filters['source'])) {
            $query->bySource($filters['source']);
        }

        // Purchased filter
        if (isset($filters['purchased']) && $filters['purchased'] !== '') {
            $query->where('has_purchased', (bool) $filters['purchased']);
        }

        // Assignee filter
        if (! empty($filters['assignee'])) {
            $query->assignedTo($filters['assignee']);
        }

        // Sort
        $sort = $filters['sort'] ?? 'created_at';
        $dir  = $filters['dir'] ?? 'desc';
        $allowedSorts = ['name', 'email', 'company', 'status', 'created_at', 'lifetime_value', 'last_purchase_date'];

        if (in_array($sort, $allowedSorts)) {
            $query->orderBy($sort, $dir === 'asc' ? 'asc' : 'desc');
        }

        return $query->paginate(20)->withQueryString();
    }

    /**
     * Create a new customer.
     */
    public function createCustomer(array $data, User $creator): Customer
    {
        return DB::transaction(function () use ($data, $creator) {
            $customer = Customer::create(array_merge($data, [
                'created_by' => $creator->id,
            ]));

            // Auto-log creation interaction
            $customer->interactions()->create([
                'user_id'       => $creator->id,
                'type'          => 'note',
                'subject'       => 'Customer created',
                'content'       => "Customer record created by {$creator->name}.",
                'outcome'       => 'positive',
                'interacted_at' => now(),
            ]);

            return $customer;
        });
    }

    /**
     * Update customer details and track changes.
     */
    public function updateCustomer(Customer $customer, array $data, User $updater): Customer
    {
        return DB::transaction(function () use ($customer, $data, $updater) {
            // Track pipeline stage change
            $oldStage = $customer->pipeline_stage?->value;
            $newStage = $data['pipeline_stage'] ?? $oldStage;

            $customer->update($data);

            if ($oldStage !== $newStage) {
                $stageLabel = DealStage::tryFrom($newStage)?->label() ?? $newStage;
                $customer->interactions()->create([
                    'user_id'       => $updater->id,
                    'type'          => 'note',
                    'subject'       => 'Pipeline stage changed',
                    'content'       => "Pipeline stage updated to: {$stageLabel} by {$updater->name}.",
                    'outcome'       => 'neutral',
                    'interacted_at' => now(),
                ]);
            }

            return $customer->fresh();
        });
    }

    /**
     * Log an interaction with a customer.
     */
    public function logInteraction(Customer $customer, array $data, User $user): CustomerInteraction
    {
        return $customer->interactions()->create([
            'user_id'          => $user->id,
            'type'             => $data['type'],
            'subject'          => $data['subject'] ?? null,
            'content'          => $data['content'],
            'outcome'          => $data['outcome'] ?? 'neutral',
            'interacted_at'    => $data['interacted_at'] ?? now(),
            'duration_minutes' => $data['duration_minutes'] ?? null,
        ]);
    }

    /**
     * Mark customer as bought and update financial stats.
     */
    public function recordPurchase(Customer $customer, float $value, User $user): Customer
    {
        return DB::transaction(function () use ($customer, $value, $user) {
            $customer->update([
                'has_purchased'      => true,
                'status'             => CustomerStatus::Active->value,
                'lifetime_value'     => $customer->lifetime_value + $value,
                'total_orders'       => $customer->total_orders + 1,
                'first_purchase_date' => $customer->first_purchase_date ?? today(),
                'last_purchase_date'  => today(),
            ]);

            $customer->interactions()->create([
                'user_id'       => $user->id,
                'type'          => 'note',
                'subject'       => 'Purchase recorded',
                'content'       => "Purchase of {$customer->currency} " . number_format($value, 2) . " recorded by {$user->name}.",
                'outcome'       => 'positive',
                'interacted_at' => now(),
            ]);

            return $customer->fresh();
        });
    }

    /**
     * Get pipeline data grouped by deal stage.
     */
    public function getPipelineData(User $user): array
    {
        $query = Deal::with([
            'customer:id,name,email,company,avatar',
            'assignee:id,name,avatar',
        ])->whereNotNull('id')->orderBy('position');

        if ($user->hasRole('sales-crm') && ! $user->hasAnyRole(['admin', 'supervisor', 'super-admin'])) {
            $query->where('assigned_to', $user->id);
        }

        $deals = $query->get();

        $pipeline = [];
        foreach (DealStage::pipelineColumns() as $stage) {
            $stageDeals = $deals->where('stage', $stage);
            $pipeline[$stage->value] = [
                'stage'        => $stage,
                'deals'        => $stageDeals->values(),
                'total_value'  => $stageDeals->sum('value'),
                'count'        => $stageDeals->count(),
            ];
        }

        return $pipeline;
    }

    /**
     * Dashboard stats for the CRM module.
     */
    public function getDashboardStats(): array
    {
        return [
            'total'         => Customer::count(),
            'leads'         => Customer::byStatus('lead')->count(),
            'active'        => Customer::byStatus('active')->count(),
            'purchased'     => Customer::purchased()->count(),
            'total_value'   => Customer::sum('lifetime_value'),
            'pipeline_value' => Deal::where('stage', '!=', DealStage::Lost->value)->sum('value'),
            'deals_won'     => Deal::where('stage', DealStage::Won->value)->count(),
        ];
    }
}
