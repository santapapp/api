<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\OrderItem;
use Illuminate\Support\Facades\Cache;

class OrderItemObserver
{
    /**
     * Handle the OrderItem "saved" event.
     */
    public function saved(OrderItem $item): void
    {
        $this->clearCache($item);
    }

    /**
     * Handle the OrderItem "deleted" event.
     */
    public function deleted(OrderItem $item): void
    {
        $this->clearCache($item);
    }

    /**
     * Clear the cache for the associated organization.
     */
    private function clearCache(OrderItem $item): void
    {
        if ($item->order_id && $item->order) {
            Cache::forget("org_{$item->order->organization_id}_orders_version");
        }
    }
}
