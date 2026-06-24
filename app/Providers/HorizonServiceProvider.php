<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;
use Symfony\Component\HttpFoundation\IpUtils;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        Horizon::routeMailNotificationsTo('m@ttste.in');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * Access is granted only to clients whose IP falls within an allowed network
     * (horizon.allowed_networks) — by default the Tailscale CGNAT range, so the
     * dashboard is reachable over the tailnet rather than the public internet.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', static function ($user = null) {
            $networks = array_filter(array_map('trim', explode(',', (string) config('horizon.allowed_networks'))));

            return IpUtils::checkIp(request()->getClientIp() ?? '', $networks);
        });
    }
}
