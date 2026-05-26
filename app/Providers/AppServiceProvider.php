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
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;

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

        // ── Scramble: Mobile / Staff API ─────────────────────────────
        // Routes: /v1/* MINUS /v1/customer/*
        Scramble::registerApi('mobile', [
            'ui' => [
                'title' => 'Santap Mobile POS & Staff API',
            ],
            'info' => [
                'description' => 'API untuk aplikasi staff kasir, dapur, dan owner.',
                'version'     => '1.0.0',
            ],
        ])->routes(function (Route $route) {
            return Str::startsWith($route->uri(), 'v1')
                && ! Str::startsWith($route->uri(), 'v1/customer');
        })->withDocumentTransformers(function (OpenApi $openApi) {
            $openApi->secure(
                SecurityScheme::http('bearer')
                    ->as('Sanctum')
                    ->setDescription('Gunakan token Bearer untuk otentikasi. Dapatkan dari endpoint login.')
            );
        })->withOperationTransformers(function (Operation $operation, RouteInfo $routeInfo) {
            $middleware = $routeInfo->route->gatherMiddleware();
            $hasSanctum = collect($middleware)->contains(function ($m) {
                return $m === 'auth:sanctum' || (is_string($m) && str_starts_with($m, 'auth:'));
            });

            if (! $hasSanctum) {
                $operation->security = [];
            }
        });

        // ── Scramble: Customer Web API ────────────────────────────────
        // Routes: /v1/customer/*
        Scramble::registerApi('web-customer', [
            'ui' => [
                'title' => 'Santap Customer Web API',
            ],
            'info' => [
                'description' => 'API publik tanpa login untuk pelanggan di meja.',
                'version'     => '1.0.0',
            ],
        ])->routes(function (Route $route) {
            return Str::startsWith($route->uri(), 'v1/customer');
        })->withDocumentTransformers(function (OpenApi $openApi) {
            $openApi->secure(
                SecurityScheme::apiKey('header', 'X-Public-Token')
                    ->as('X-Public-Token')
                    ->setDescription('Gunakan public_token yang didapat dari scan QR meja (X-Public-Token header).')
            );
        })->withOperationTransformers(function (Operation $operation, RouteInfo $routeInfo) {
            $middleware = $routeInfo->route->gatherMiddleware();
            $hasCustomerToken = collect($middleware)->contains(function ($m) {
                return $m === 'ensure.customer.token' || (is_string($m) && str_contains($m, 'EnsureCustomerToken'));
            });

            if (! $hasCustomerToken) {
                $operation->security = [];
            }
        });


        // ── Scramble UI & JSON Spec Routes ────────────────────────────
        Scramble::registerUiRoute('docs/api/mobile', api: 'mobile');
        Scramble::registerJsonSpecificationRoute('docs/api/mobile/api.json', api: 'mobile');

        Scramble::registerUiRoute('docs/api/web-customer', api: 'web-customer');
        Scramble::registerJsonSpecificationRoute('docs/api/web-customer/api.json', api: 'web-customer');

        // ── Rate Limiters ─────────────────────────────────────────────
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
