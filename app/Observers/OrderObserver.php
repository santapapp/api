<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;

class OrderObserver
{
    /**
     * Handle the Order "saved" event.
     */
    public function saved(Order $order): void
    {
        $this->clearCache($order->organization_id);
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        $this->clearCache($order->organization_id);
    }

    /**
     * Clear the cache for the given organization.
     */
    private function clearCache(int $organizationId): void
    {
        Cache::forget("org_{$organizationId}_orders_version");
    }
}
