<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\OrderItem;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderItemStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int $billId,
        public int $organizationId,
        public int $itemId,
        public int $menuId,
        public string $name,
        public string $itemStatus,
        public string $orderStatus,
        public float $orderTotal,
    ) {}

    /**
     * Create an event instance from an OrderItem model.
     */
    public static function fromItem(OrderItem $item): self
    {
        $order = $item->order;

        return new self(
            billId: (int) $order->id,
            organizationId: (int) $order->organization_id,
            itemId: (int) $item->id,
            menuId: (int) $item->menu_id,
            name: $item->name ?? $item->menu_name_snapshot ?? '',
            itemStatus: $item->item_status?->value ?? (string) $item->item_status,
            orderStatus: $order->order_status?->value ?? (string) $order->order_status,
            orderTotal: (float) $order->total_amount,
        );
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("open-bill.{$this->billId}"),
            new PrivateChannel("organization.{$this->organizationId}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'item-status-updated';
    }
}
