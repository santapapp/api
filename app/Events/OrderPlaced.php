<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPlaced implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int $orderId,
        public int $organizationId,
        public ?int $tableId,
        public string $tableName,
        public string $orderNumber,
        public string $orderType,
        public float $totalAmount,
        public int $itemsCount,
        public ?string $customerName,
        public string $createdAt,
    ) {}

    /**
     * Create an event instance from an Order model.
     */
    public static function fromOrder(Order $order): self
    {
        return new self(
            orderId: (int) $order->id,
            organizationId: (int) $order->organization_id,
            tableId: $order->dining_table_id ? (int) $order->dining_table_id : null,
            tableName: $order->diningTable?->name ?? 'Meja',
            orderNumber: $order->order_number,
            orderType: $order->order_type->value ?? (string) $order->order_type,
            totalAmount: (float) $order->total_amount,
            itemsCount: (int) $order->allItems()->count(),
            customerName: $order->customer_name,
            createdAt: $order->created_at?->toIso8601String() ?? now()->toIso8601String(),
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
            new PrivateChannel("organization.{$this->organizationId}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order-placed';
    }
}
