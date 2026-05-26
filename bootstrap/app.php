<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // API routes dengan prefix /v1 (tanpa /api — sudah subdomain api.santap.app)
            Route::middleware('api')
                ->prefix('v1')
                ->name('api.v1.')
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'resolve.organization'       => \App\Http\Middleware\ResolveOrganization::class,
            'ensure.organization.member' => \App\Http\Middleware\EnsureOrganizationMember::class,
            'ensure.customer.token'      => \App\Http\Middleware\EnsureCustomerToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('v1/*') || $request->is('health') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Validasi gagal.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->is('v1/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Resource tidak ditemukan.',
                ], 404);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, $request) {
            if ($request->is('v1/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Akses ditolak.',
                ], 403);
            }
        });
    })->create();
