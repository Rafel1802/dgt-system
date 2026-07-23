<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Enums\DealStage;
use App\Enums\WebsiteLeadStatus;
use App\Models\Customer;
use App\Models\CustomerInteraction;
use App\Models\EbayCustomerOrderItem;
use App\Models\LeadProduct;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CrmService
{
    /**
     * Search and filter the customer database.
     */
    public function searchCustomers(array $filters, User $user): LengthAwarePaginator
    {
        $query = Customer::with(['assignee:id,name,avatar', 'creator:id,name'])
            ->withCount('interactions');

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

            self::forgetDashboardStats();

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

            self::forgetDashboardStats();

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

            self::forgetDashboardStats();

            return $customer->fresh();
        });
    }

    /**
     * Permanently delete a customer and everything linked to them across
     * every CRM domain — interactions, leads, eBay records/orders, shipment
     * links, tech support cases, logistics, and their own children — via
     * the customer_id foreign keys, which are all ON DELETE CASCADE.
     *
     * The polymorphic attachment/call-request tables have no real foreign
     * key (attachable_type/id, source_type/id), so DB cascades can't reach
     * them — they're cleaned up explicitly here before the hard delete.
     */
    public function deleteCascading(Customer $customer): void
    {
        DB::transaction(function () use ($customer) {
            $customer->attachments()->delete();

            foreach ($customer->leads as $lead) {
                $lead->attachments()->delete();
            }

            foreach ($customer->ebayCustomerRecords as $record) {
                $record->attachments()->delete();
            }

            foreach ($customer->ebayOffers as $offer) {
                $offer->attachments()->delete();
            }

            foreach ($customer->logistics as $logistic) {
                $logistic->attachments()->delete();
            }

            foreach ($customer->techSupportCases as $case) {
                foreach ($case->logs as $log) {
                    $log->attachments()->delete();
                }
                $case->callRequests()->delete();
            }

            $customer->forceDelete();
            self::forgetDashboardStats();
        });
    }

    /**
     * Dashboard stats for the CRM module.
     *
     * Short-lived cache (60s): aggregate KPIs only — not customer PII.
     * Invalidated on write via forgetDashboardStats().
     */
    public function getDashboardStats(): array
    {
        return Cache::remember('crm.dashboard_stats', 60, function () {
            // One grouped scan instead of four separate COUNT queries.
            $counts = Customer::query()
                ->selectRaw('COUNT(*) as total')
                ->selectRaw("SUM(CASE WHEN status = 'lead' THEN 1 ELSE 0 END) as leads")
                ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active")
                ->selectRaw('SUM(CASE WHEN has_purchased = 1 THEN 1 ELSE 0 END) as purchased')
                ->first();

            return [
                'total'       => (int) ($counts->total ?? 0),
                'leads'       => (int) ($counts->leads ?? 0),
                'active'      => (int) ($counts->active ?? 0),
                'purchased'   => (int) ($counts->purchased ?? 0),
                'total_value' => $this->totalSales(),
            ];
        });
    }

    /** Drop cached CRM list KPI tiles + unified directory after mutations. */
    public static function forgetDashboardStats(): void
    {
        \App\Services\CrmCustomerMatchService::forgetUnifiedDirectoryCache();
        Cache::forget('crm.shipment_picker_customers');
        Cache::forget('crm.shipment_picker_customers.v2');
        \App\Support\CrmLookupCache::forgetAll();
    }

    /**
     * Real total revenue: eBay + Website sales (order-item prices), not the
     * unmaintained Customer.lifetime_value field — mirrors the "Total Sales"
     * calculation used on the Team Report page.
     */
    private function totalSales(): float
    {
        $ebaySales = (float) EbayCustomerOrderItem::sum('price');

        // SQL aggregate — never hydrate every LeadProduct row into PHP.
        $websiteSales = (float) LeadProduct::whereHas(
            'lead',
            fn ($q) => $q->where('status', WebsiteLeadStatus::Successful)
        )->sum(DB::raw('price * quantity'));

        return $ebaySales + $websiteSales;
    }
}
