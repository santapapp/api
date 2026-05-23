<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            if (!$user) {
                return false;
            }
            $originalTeamId = app(\Spatie\Permission\PermissionRegistrar::class)->getPermissionsTeamId();
            app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId(null);
            $hasRole = $user->hasRole('administrator');
            app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($originalTeamId);
            return $hasRole || $user->email === 'test@example.com';
        });
    }
}
