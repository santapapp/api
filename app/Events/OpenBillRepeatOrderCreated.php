<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OpenBillRepeatOrderCreated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $billId,
        public int $organizationId,
        public ?int $tableId,
        public string $orderNumber,
        public array $batch,
        public array $items,
        public float $orderTotal,
        public string $billStatus,
    ) {}

    public static function fromOrder(Order $order, array $batch): self
    {
        $batchUuid = $batch['batch_uuid'] ?? null;

        $items = $order->items
            ->when($batchUuid, fn ($items) => $items->where('batch_uuid', $batchUuid))
            ->values()
            ->map(fn ($item) => [
                'id' => $item->id,
                'menu_id' => $item->menu_id,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'subtotal' => (float) $item->subtotal,
                'item_status' => $item->item_status?->value ?? (string) $item->item_status,
            ])
            ->all();

        return new self(
            billId: (int) $order->id,
            organizationId: (int) $order->organization_id,
            tableId: $order->dining_table_id ? (int) $order->dining_table_id : null,
            orderNumber: $order->order_number,
            batch: $batch,
            items: $items,
            orderTotal: (float) $order->total_amount,
            billStatus: $order->bill_status?->value ?? (string) $order->bill_status,
        );
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("open-bill.{$this->billId}"),
            new PrivateChannel("organization.{$this->organizationId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'repeat-order-created';
    }
}
