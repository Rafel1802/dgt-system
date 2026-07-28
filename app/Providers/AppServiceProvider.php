<?php

namespace App\Providers;

use App\Models\Card;
use App\Models\Customer;
use App\Models\EbayCustomerRecord;
use App\Models\Lead;
use App\Models\ShipmentCustomer;
use App\Policies\CardPolicy;
use App\Policies\CustomerPolicy;
use App\Models\Product;
use App\Services\CrmCustomerMatchService;
use App\Support\CrmLookupCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ── Register Policies ──────────────────────────────────────────────
        Gate::policy(Card::class, CardPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);

        // ── Super Admin Gate Bypass ────────────────────────────────────────
        // Super admins bypass all Gate/Policy checks (Spatie handles this
        // via the HasRoles trait for permission checks; this covers Gates).
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('super-admin')) {
                return true;
            }
        });

        // Invalidate CRM directory / picker caches when source rows change.
        // Short-lived caches make list pages feel instant; these hooks keep them correct.
        $bustCrmDirectory = function () {
            CrmCustomerMatchService::forgetUnifiedDirectoryCache();
            Cache::forget('crm.shipment_picker_customers');
            Cache::forget('crm.shipment_picker_customers.v2');
            Cache::forget('crm.lookup.customers_combobox');
            Cache::forget('crm.lookup.pending_call_requests');
        };

        foreach ([Customer::class, Lead::class, EbayCustomerRecord::class, ShipmentCustomer::class] as $model) {
            $model::saved($bustCrmDirectory);
            $model::deleted($bustCrmDirectory);
        }

        // Product / store / trucking dropdowns
        Product::saved(fn () => CrmLookupCache::forgetAll());
        Product::deleted(fn () => CrmLookupCache::forgetAll());
    }
}
