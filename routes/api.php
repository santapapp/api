<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CashierOrderController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DiningTableController;
use App\Http\Controllers\Api\V1\KitchenOrderController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\OrganizationController;
use Illuminate\Support\Facades\Route;

// ============================================================
// Auth — Public
// ============================================================
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('auth.login');

// ============================================================
// Auth — Protected
// ============================================================
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::put('/auth/profile', [AuthController::class, 'updateProfile'])->name('auth.profile.update');

    // ============================================================
    // Organizations
    // ============================================================
    Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');

    // ============================================================
    // Org-scoped routes (header X-Org-ID)
    // ============================================================
    Route::middleware(['resolve.organization', 'ensure.organization.member'])->group(function (): void {

        // --- Organization detail & settings ---
        Route::get('/organizations/current', [OrganizationController::class, 'show'])->name('organizations.show');
        Route::put('/organizations/current', [OrganizationController::class, 'update'])->name('organizations.update');

        // --- Dining Tables ---
        Route::get('/dining-tables', [DiningTableController::class, 'index'])->name('dining-tables.index');
        Route::post('/dining-tables', [DiningTableController::class, 'store'])->name('dining-tables.store');
        Route::put('/dining-tables/{id}', [DiningTableController::class, 'update'])->name('dining-tables.update');
        Route::delete('/dining-tables/{id}', [DiningTableController::class, 'destroy'])->name('dining-tables.destroy');
        Route::post('/dining-tables/{id}/regenerate-qr', [DiningTableController::class, 'regenerateQr'])->name('dining-tables.regenerate-qr');

        // --- Menu ---
        Route::get('/menus', [MenuController::class, 'index'])->name('menus.index');
        Route::post('/menus', [MenuController::class, 'store'])->name('menus.store');
        Route::put('/menus/{id}', [MenuController::class, 'update'])->name('menus.update');
        Route::delete('/menus/{id}', [MenuController::class, 'destroy'])->name('menus.destroy');
        Route::patch('/menus/{id}/toggle', [MenuController::class, 'toggle'])->name('menus.toggle');

        // --- Cashier Orders ---
        Route::get('/cashier/orders', [CashierOrderController::class, 'index'])->name('cashier.orders.index');
        Route::post('/cashier/orders', [CashierOrderController::class, 'store'])->name('cashier.orders.store');
        Route::get('/cashier/orders/{id}', [CashierOrderController::class, 'show'])->name('cashier.orders.show');
        Route::post('/cashier/orders/{id}/items', [CashierOrderController::class, 'addItems'])->name('cashier.orders.add-items');
        Route::delete('/cashier/orders/{id}/items/{itemId}', [CashierOrderController::class, 'removeItem'])->name('cashier.orders.remove-item');
        Route::post('/cashier/orders/{id}/confirm', [CashierOrderController::class, 'confirm'])->name('cashier.orders.confirm');
        Route::post('/cashier/orders/{id}/pay-cash', [CashierOrderController::class, 'payCash'])->name('cashier.orders.pay-cash');
        Route::post('/cashier/orders/{id}/pay-qris', [CashierOrderController::class, 'payQris'])->name('cashier.orders.pay-qris');
        Route::get('/cashier/orders/{id}/qris-status', [CashierOrderController::class, 'qrisStatus'])->name('cashier.orders.qris-status');
        Route::delete('/cashier/orders/{id}/qris-cancel', [CashierOrderController::class, 'qrisCancel'])->name('cashier.orders.qris-cancel');
        Route::post('/cashier/orders/{id}/close', [CashierOrderController::class, 'close'])->name('cashier.orders.close');
        Route::post('/cashier/orders/{id}/cancel', [CashierOrderController::class, 'cancel'])->name('cashier.orders.cancel');

        // --- Kitchen ---
        Route::get('/kitchen/orders', [KitchenOrderController::class, 'index'])->name('kitchen.orders.index');
        Route::patch('/kitchen/order-items/{id}/status', [KitchenOrderController::class, 'updateItemStatus'])->name('kitchen.order-items.status');
    });
});

// ============================================================
// Customer (Public — no auth)
// ============================================================
Route::prefix('customer')->as('customer.')->group(function (): void {
    Route::get('/organization/{slug}', [CustomerController::class, 'organization'])->name('organization');
    Route::get('/table/{qrToken}', [CustomerController::class, 'scanTable'])->name('table.scan');
    Route::get('/menu', [CustomerController::class, 'menu'])->name('menu');

    Route::middleware('ensure.customer.token')->group(function (): void {
        Route::get('/order', [CustomerController::class, 'showOrder'])->name('order.show');
        Route::post('/order/items', [CustomerController::class, 'addItems'])
            ->middleware('throttle:customer-order')
            ->name('order.add-items');
        Route::post('/order/pay-qris', [CustomerController::class, 'payQris'])->name('order.pay-qris');
        Route::get('/order/qris-status', [CustomerController::class, 'qrisStatus'])
            ->middleware('throttle:qris-check')
            ->name('order.qris-status');
        Route::delete('/order/qris-cancel', [CustomerController::class, 'qrisCancel'])->name('order.qris-cancel');
    });
});
