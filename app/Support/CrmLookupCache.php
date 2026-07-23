<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\EbayStore;
use App\Models\Product;
use App\Models\TruckingCompany;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Short-lived, non-PII-heavy lookup lists for CRM dropdowns.
 * Always returns plain arrays (never cached Eloquent models) so Hostinger
 * file/database cache cannot corrupt them on unserialize.
 */
class CrmLookupCache
{
    public const TTL = 120;

    public static function forgetAll(): void
    {
        Cache::forget('crm.lookup.members');
        Cache::forget('crm.lookup.members.v2');
        Cache::forget('crm.lookup.products_active');
        Cache::forget('crm.lookup.products_active_full');
        Cache::forget('crm.lookup.ebay_stores');
        Cache::forget('crm.lookup.trucking_companies');
        Cache::forget('crm.lookup.customers_combobox');
        Cache::forget('crm.lookup.pending_call_requests');
        Cache::forget('crm.members.dropdown');
        Cache::forget('crm.shipment_picker_customers');
        Cache::forget('crm.shipment_picker_customers.v2');
        Cache::forget('tech_support.technicians.v2');
        Cache::forget('tech_support.index_stats');
    }

    /**
     * CRM staff for assignment dropdowns. Includes crm_role_display for
     * member-searchable-select labels (e.g. "Name — CRM Supervisor").
     *
     * @return \Illuminate\Support\Collection<int, object{id:int,name:string,crm_role_display:string}>
     */
    public static function crmMembers(): \Illuminate\Support\Collection
    {
        $rows = Cache::remember('crm.lookup.members.v2', self::TTL, function () {
            // Load full models so role accessors (Spatie) resolve correctly,
            // then store plain arrays only — never cache Eloquent instances.
            return User::crmMembers()
                ->orderBy('name')
                ->get()
                ->map(fn (User $u) => [
                    'id'               => $u->id,
                    'name'             => $u->name,
                    'crm_role_display' => $u->crm_role_display,
                ])
                ->values()
                ->all();
        });

        return collect($rows)->map(fn (array $r) => (object) $r);
    }

    /**
     * Lightweight product picker rows (property access like Eloquent for Blade).
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public static function activeProducts(bool $withPrice = true): \Illuminate\Support\Collection
    {
        $key = $withPrice ? 'crm.lookup.products_active_full' : 'crm.lookup.products_active';

        $rows = Cache::remember($key, self::TTL, function () use ($withPrice) {
            $cols = $withPrice ? ['id', 'name', 'sku', 'price'] : ['id', 'name', 'sku'];

            return Product::active()
                ->orderBy('name')
                ->get($cols)
                ->map(function (Product $p) use ($withPrice) {
                    $row = [
                        'id'   => $p->id,
                        'name' => $p->name,
                        'sku'  => $p->sku,
                    ];
                    if ($withPrice) {
                        $row['price'] = $p->price;
                    }

                    return $row;
                })
                ->values()
                ->all();
        });

        return collect($rows)->map(fn (array $r) => (object) $r);
    }

    /**
     * @return \Illuminate\Support\Collection<int, object{id:int,store_name:string}>
     */
    public static function activeEbayStores(): \Illuminate\Support\Collection
    {
        $rows = Cache::remember('crm.lookup.ebay_stores', self::TTL, function () {
            return EbayStore::active()
                ->orderBy('store_name')
                ->get(['id', 'store_name'])
                ->map(fn (EbayStore $s) => ['id' => $s->id, 'store_name' => $s->store_name])
                ->values()
                ->all();
        });

        return collect($rows)->map(fn (array $r) => (object) $r);
    }

    /**
     * Customer combobox options (shared across Website/eBay/Shipment forms).
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public static function customersCombobox(): \Illuminate\Support\Collection
    {
        $rows = Cache::remember('crm.lookup.customers_combobox', 60, function () {
            return Customer::orderBy('name')
                ->get(['id', 'name', 'email', 'phone', 'company', 'address'])
                ->map(fn (Customer $c) => [
                    'id'      => $c->id,
                    'name'    => $c->name,
                    'email'   => $c->email,
                    'phone'   => $c->phone,
                    'company' => $c->company,
                    'address' => $c->address,
                ])
                ->values()
                ->all();
        });

        return collect($rows)->map(fn (array $r) => (object) $r);
    }

    public static function pendingCallRequestsCount(): int
    {
        return (int) Cache::remember('crm.lookup.pending_call_requests', 30, function () {
            return \App\Models\CallRequest::pending()->count();
        });
    }
}
