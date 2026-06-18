<?php

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Laravel\Sanctum\PersonalAccessToken;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('open-bill.{billId}', function (?User $user, int $billId): bool {
    $order = Order::query()
        ->whereKey($billId)
        ->where('order_type', OrderType::OpenBill)
        ->where('bill_status', BillStatus::Open)
        ->where('order_status', '!=', OrderStatus::Cancelled)
        ->whereNull('cancelled_at')
        ->whereNull('closed_at')
        ->first();

    if (! $order) {
        return false;
    }

    $request = request();
    $user ??= $request->user('sanctum') instanceof User
        ? $request->user('sanctum')
        : null;

    if (! $user && $request->bearerToken()) {
        $tokenable = PersonalAccessToken::findToken($request->bearerToken())?->tokenable;
        $user = $tokenable instanceof User ? $tokenable : null;
    }

    if (
        $user
        && $user->exists
        && $user->memberships()->where('organization_id', $order->organization_id)->exists()
    ) {
        return true;
    }

    $publicToken = $request->header('X-Public-Token');

    return is_string($publicToken)
        && is_string($order->public_token)
        && hash_equals($order->public_token, $publicToken);
}, ['guards' => ['sanctum', 'customer-token', 'web']]);

Broadcast::channel('organization.{orgId}', function (User $user, int $orgId): bool {
    return $user->memberships()->where('organization_id', $orgId)->exists();
}, ['guards' => ['sanctum', 'web']]);
