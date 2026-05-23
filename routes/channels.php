<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Organization General Channel (For Owners/Admins)
Broadcast::channel('org.{organizationId}', function (User $user, int $organizationId) {
    return $user->organizations()->where('organization_id', $organizationId)->exists();
});

// Kitchen Channel
Broadcast::channel('kitchen.{organizationId}', function (User $user, int $organizationId) {
    return $user->organizations()->where('organization_id', $organizationId)->exists() 
        && $user->hasPermissionTo('order.view', 'web'); // Or similar permission logic
});

// Cashier Channel
Broadcast::channel('cashier.{organizationId}', function (User $user, int $organizationId) {
    return $user->organizations()->where('organization_id', $organizationId)->exists();
});

// Table / Customer Channel (For now, allow any user or customer to subscribe)
// In a real production app, this would use a custom guard for CustomerSession.
Broadcast::channel('table.{organizationId}.{tableId}', function () {
    return true; 
});

Broadcast::channel('customer-session.{sessionId}', function () {
    return true;
});
