<?php

namespace App\Providers;

use App\Models\Card;
use App\Policies\CardPolicy;
use App\Policies\WebsiteLeadPolicy;
use App\Models\Lead;
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
        if (config('app.env') === 'production' && !app()->runningInConsole()) {
            $host = request()->getHost();
            if ($host !== 'localhost' && $host !== '127.0.0.1' && !str_starts_with($host, '192.168.')) {
                \Illuminate\Support\Facades\URL::forceScheme('https');
            }
        }

        // ── Register Policies ──────────────────────────────────────────────
        Gate::policy(Card::class, CardPolicy::class);
        Gate::policy(Lead::class, WebsiteLeadPolicy::class);
        Gate::policy(\App\Models\ProductionReport\ProductionReport::class, \App\Policies\ProductionReportPolicy::class);

        // ── Super Admin Gate Bypass ────────────────────────────────────────
        // Super admins bypass all Gate/Policy checks EXCEPT ProductionReport,
        // which strictly enforces ProductionReportPolicy (Supervisor view-only,
        // Dara/Kim team isolation, and explicit team mapping).
        Gate::before(function ($user, $ability, $arguments = []) {
            $firstArg = $arguments[0] ?? null;
            if (
                $firstArg instanceof \App\Models\ProductionReport\ProductionReport
                || $firstArg === \App\Models\ProductionReport\ProductionReport::class
                || str_starts_with((string) $ability, 'production-report')
            ) {
                return null;
            }

            if ($user->hasRole('super-admin')) {
                return true;
            }
        });
    }
}
