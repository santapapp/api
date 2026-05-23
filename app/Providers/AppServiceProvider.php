<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\OrganizationContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 1. Authorize Laravel Pulse access
        Gate::define('viewPulse', function ($user) {
            if (!$user) {
                return false;
            }
            $originalTeamId = app(\Spatie\Permission\PermissionRegistrar::class)->getPermissionsTeamId();
            app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId(null);
            $hasRole = $user->hasRole('administrator');
            app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($originalTeamId);
            return $hasRole || $user->email === 'test@example.com';
        });

        // 2. Define Rate Limiters
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('invite', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('customer-session-start', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('customer-order', function (Request $request) {
            $sessionToken = $request->header('X-Customer-Session') ?: $request->ip();
            return Limit::perMinute(15)->by($sessionToken);
        });

        RateLimiter::for('qris-check', function (Request $request) {
            $sessionToken = $request->header('X-Customer-Session') ?: $request->ip();
            return Limit::perMinute(30)->by($sessionToken);
        });
    }
}
