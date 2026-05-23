<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ContextController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->as('api.v1.')
    ->group(function (): void {
        // Public routes
        Route::get('/health', HealthController::class)->name('health');
        Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');
        Route::post('/payments/webhook', [\App\Http\Controllers\Api\V1\PaymentWebhookController::class, 'handle'])->name('payments.webhook');

        // Authenticated routes
        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
            Route::get('/me', [AuthController::class, 'me'])->name('me');
            Route::get('/me/organizations', [AuthController::class, 'organizations'])->name('me.organizations');
            
            // Create a new organization (user automatically becomes owner)
            Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');
            
            // Switch context pre-flight verification
            Route::post('/context/switch-organization', [ContextController::class, 'switchOrganization'])->name('context.switch');
            
            // Accept invitation (doesn't have organization context yet, but user must be logged in)
            Route::post('/invitations/accept', [InvitationController::class, 'accept'])->name('invitations.accept');

            // Organization-context scoped routes
            Route::middleware(['resolve.organization', 'ensure.organization.member'])->group(function (): void {
                // Owner-only invitation action
                Route::post('/invitations', [InvitationController::class, 'invite'])
                    ->middleware(['ensure.organization.permission:organization.invite_user', 'throttle:invite'])
                    ->name('invitations.invite');

                // Menu Categories
                Route::get('menu-categories', [\App\Http\Controllers\Api\V1\MenuCategoryController::class, 'index'])
                    ->middleware('ensure.organization.permission:category.view')
                    ->name('menu-categories.index');
                Route::post('menu-categories', [\App\Http\Controllers\Api\V1\MenuCategoryController::class, 'store'])
                    ->middleware('ensure.organization.permission:category.create')
                    ->name('menu-categories.store');
                Route::get('menu-categories/{menuCategory}', [\App\Http\Controllers\Api\V1\MenuCategoryController::class, 'show'])
                    ->middleware('ensure.organization.permission:category.view')
                    ->name('menu-categories.show');
                Route::put('menu-categories/{menuCategory}', [\App\Http\Controllers\Api\V1\MenuCategoryController::class, 'update'])
                    ->middleware('ensure.organization.permission:category.update')
                    ->name('menu-categories.update');
                Route::delete('menu-categories/{menuCategory}', [\App\Http\Controllers\Api\V1\MenuCategoryController::class, 'destroy'])
                    ->middleware('ensure.organization.permission:category.delete')
                    ->name('menu-categories.destroy');

                // Menus
                Route::get('menus', [\App\Http\Controllers\Api\V1\MenuController::class, 'index'])
                    ->middleware('ensure.organization.permission:menu.view')
                    ->name('menus.index');
                Route::post('menus', [\App\Http\Controllers\Api\V1\MenuController::class, 'store'])
                    ->middleware('ensure.organization.permission:menu.create')
                    ->name('menus.store');
                Route::get('menus/{menu}', [\App\Http\Controllers\Api\V1\MenuController::class, 'show'])
                    ->middleware('ensure.organization.permission:menu.view')
                    ->name('menus.show');
                Route::put('menus/{menu}', [\App\Http\Controllers\Api\V1\MenuController::class, 'update'])
                    ->middleware('ensure.organization.permission:menu.update')
                    ->name('menus.update');
                Route::delete('menus/{menu}', [\App\Http\Controllers\Api\V1\MenuController::class, 'destroy'])
                    ->middleware('ensure.organization.permission:menu.delete')
                    ->name('menus.destroy');
                Route::post('menus/{menu}/image', [\App\Http\Controllers\Api\V1\MenuController::class, 'uploadImage'])
                    ->middleware('ensure.organization.permission:menu.update')
                    ->name('menus.image');

                // Dining Tables
                Route::get('dining-tables', [\App\Http\Controllers\Api\V1\DiningTableController::class, 'index'])
                    ->middleware('ensure.organization.permission:table.view')
                    ->name('dining-tables.index');
                Route::post('dining-tables', [\App\Http\Controllers\Api\V1\DiningTableController::class, 'store'])
                    ->middleware('ensure.organization.permission:table.create')
                    ->name('dining-tables.store');
                Route::get('dining-tables/{diningTable}', [\App\Http\Controllers\Api\V1\DiningTableController::class, 'show'])
                    ->middleware('ensure.organization.permission:table.view')
                    ->name('dining-tables.show');
                Route::put('dining-tables/{diningTable}', [\App\Http\Controllers\Api\V1\DiningTableController::class, 'update'])
                    ->middleware('ensure.organization.permission:table.update')
                    ->name('dining-tables.update');
                Route::delete('dining-tables/{diningTable}', [\App\Http\Controllers\Api\V1\DiningTableController::class, 'destroy'])
                    ->middleware('ensure.organization.permission:table.delete')
                    ->name('dining-tables.destroy');
                Route::post('dining-tables/{diningTable}/regenerate-qr', [\App\Http\Controllers\Api\V1\DiningTableController::class, 'regenerateQr'])
                    ->middleware('ensure.organization.permission:table.generate_qr')
                    ->name('dining-tables.regenerate-qr');
                // Kitchen
                Route::get('kitchen/orders', [\App\Http\Controllers\Api\V1\KitchenOrderController::class, 'index'])
                    ->middleware('ensure.organization.permission:kitchen.view')
                    ->name('kitchen.orders.index');
                Route::patch('kitchen/order-items/{id}/status', [\App\Http\Controllers\Api\V1\KitchenOrderController::class, 'updateItemStatus'])
                    ->middleware('ensure.organization.permission:kitchen.update_order_status')
                    ->name('kitchen.order-items.status');

                // Cashier / Payment
                Route::get('open-bills', [\App\Http\Controllers\Api\V1\CashierBillController::class, 'index'])
                    ->middleware('ensure.organization.permission:bill.view')
                    ->name('open-bills.index');
                Route::post('payments', [\App\Http\Controllers\Api\V1\PaymentController::class, 'store'])
                    ->middleware('ensure.organization.permission:payment.create')
                    ->name('payments.store');
                Route::post('payments/{payment}/check', [\App\Http\Controllers\Api\V1\PaymentController::class, 'checkStatus'])
                    ->middleware(['ensure.organization.permission:payment.create', 'throttle:qris-check'])
                    ->name('payments.check');
                Route::post('payments/{payment}/cancel', [\App\Http\Controllers\Api\V1\PaymentController::class, 'cancelPayment'])
                    ->middleware('ensure.organization.permission:payment.create')
                    ->name('payments.cancel');
                Route::post('open-bills/{id}/close', [\App\Http\Controllers\Api\V1\PaymentController::class, 'closeBill'])
                    ->middleware('ensure.organization.permission:bill.close')
                    ->name('open-bills.close');

                // Reports
                Route::get('reports/sales-summary', [\App\Http\Controllers\Api\V1\ReportController::class, 'salesSummary'])
                    ->middleware('ensure.organization.permission:report.view')
                    ->name('reports.sales-summary');
                Route::get('reports/daily-sales', [\App\Http\Controllers\Api\V1\ReportController::class, 'dailySales'])
                    ->middleware('ensure.organization.permission:report.view')
                    ->name('reports.daily-sales');
                Route::get('reports/menu-sales', [\App\Http\Controllers\Api\V1\ReportController::class, 'menuSales'])
                    ->middleware('ensure.organization.permission:report.view')
                    ->name('reports.menu-sales');
                Route::get('reports/payment-methods', [\App\Http\Controllers\Api\V1\ReportController::class, 'paymentMethods'])
                    ->middleware('ensure.organization.permission:report.view')
                    ->name('reports.payment-methods');
            });
        });

        // Pelanggan / Customer Web (Public / non-auth)
        Route::prefix('customer')->as('customer.')->group(function (): void {
            Route::post('sessions/start', [\App\Http\Controllers\Api\V1\CustomerSessionController::class, 'start'])->middleware('throttle:customer-session-start')->name('sessions.start');

            Route::middleware('ensure.customer.session')->group(function (): void {
                Route::get('sessions/current', [\App\Http\Controllers\Api\V1\CustomerSessionController::class, 'current'])->name('sessions.current');
                Route::get('menu', [\App\Http\Controllers\Api\V1\CustomerMenuController::class, 'index'])->name('menu.index');
                Route::get('open-bill', [\App\Http\Controllers\Api\V1\CustomerBillController::class, 'show'])->name('bill.show');
                Route::post('orders', [\App\Http\Controllers\Api\V1\CustomerOrderController::class, 'store'])->middleware('throttle:customer-order')->name('orders.store');
                Route::post('call-cashier', \App\Http\Controllers\Api\V1\CustomerCallCashierController::class)->name('call-cashier');
                Route::post('payments', [\App\Http\Controllers\Api\V1\CustomerPaymentController::class, 'store'])->name('payments.store');
                Route::post('payments/{payment}/check', [\App\Http\Controllers\Api\V1\CustomerPaymentController::class, 'checkStatus'])->middleware('throttle:qris-check')->name('payments.check');
                Route::post('payments/{payment}/cancel', [\App\Http\Controllers\Api\V1\CustomerPaymentController::class, 'cancelPayment'])->name('payments.cancel');
            });
        });
    });
