<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\OrganizationContext::class);
        Scramble::ignoreDefaultRoutes();
    }

    public function boot(): void
    {
        Gate::define('viewApiDocs', function ($user = null) {
            return (bool) config('app.scramble_docs_enabled', false);
        });

        // Register custom API groups for Scramble
        Scramble::registerApi('mobile', [
            'info' => [
                'title' => 'Santap Mobile POS & Staff API',
                'description' => 'API untuk aplikasi staff kasir, dapur, dan owner.',
                'version' => '1.0.0',
            ]
        ])->routes(function (Route $route) {
            return Str::startsWith($route->uri(), 'api/v1') && !Str::startsWith($route->uri(), 'api/v1/customer');
        });

        Scramble::registerApi('customer-web', [
            'info' => [
                'title' => 'Santap Customer Web API',
                'description' => 'API publik tanpa login untuk pelanggan di meja.',
                'version' => '1.0.0',
            ]
        ])->routes(function (Route $route) {
            return Str::startsWith($route->uri(), 'api/v1/customer');
        });

        // Register UI & JSON routes
        Scramble::registerUiRoute('docs/api/mobile', api: 'mobile');
        Scramble::registerJsonSpecificationRoute('docs/api/mobile/api.json', api: 'mobile');

        Scramble::registerUiRoute('docs/api/customer-web', api: 'customer-web');
        Scramble::registerJsonSpecificationRoute('docs/api/customer-web/api.json', api: 'customer-web');

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('customer-order', function (Request $request) {
            $token = $request->header('X-Public-Token') ?: $request->ip();
            return Limit::perMinute(10)->by($token);
        });

        RateLimiter::for('qris-check', function (Request $request) {
            $token = $request->header('X-Public-Token') ?: $request->ip();
            return Limit::perMinute(20)->by($token);
        });
    }
}
