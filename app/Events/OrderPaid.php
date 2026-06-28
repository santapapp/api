<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPaid implements ShouldBroadcastNow
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
        public string $paymentMethod,
        public float $totalAmount,
        public string $paidAt,
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
            paymentMethod: $order->payment_method ?? 'qris',
            totalAmount: (float) $order->total_amount,
            paidAt: $order->paid_at?->toIso8601String() ?? now()->toIso8601String(),
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
            new PrivateChannel("open-bill.{$this->orderId}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order-paid';
    }
}
