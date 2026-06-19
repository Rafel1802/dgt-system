<?php

namespace App\Providers;

use App\Models\Card;
use App\Models\Customer;
use App\Policies\CardPolicy;
use App\Policies\CustomerPolicy;
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
    }
}
