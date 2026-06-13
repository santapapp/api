<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\NormalizesNumbers;
use App\Support\Orders\OrderItemBatchSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Order detail — untuk show (dengan items nested).
 */
class OrderDetailResource extends JsonResource
{
    use NormalizesNumbers;

    public function toArray(Request $request): array
    {
        $items = $this->relationLoaded('items') ? $this->items : collect();
        $itemBatches = collect(OrderItemBatchSummary::fromItems($items, includeItems: true))
            ->map(function (array $batch): array {
                $batch['items'] = OrderItemResource::collection($batch['_items']);
                unset($batch['_items']);

                return $batch;
            })
            ->values();

        // Resolve organization: dari dining_table.organization jika ada meja,
        // fallback ke order.organization langsung (open bill tanpa meja).
        $resolvedOrg = $this->relationLoaded('diningTable') && $this->diningTable?->relationLoaded('organization')
            ? ($this->diningTable->organization ?? ($this->relationLoaded('organization') ? $this->organization : null))
            : ($this->relationLoaded('organization') ? $this->organization : null);

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'public_token' => $this->public_token,
            // Enum fields di-serialize sebagai string value secara eksplisit
            // agar frontend bisa membandingkan dengan string biasa (mis. 'open_bill').
            'order_type' => $this->order_type?->value,
            'bill_status' => $this->bill_status?->value,
            'order_status' => $this->order_status?->value,
            'payment_status' => $this->payment_status?->value,
            'payment_method' => $this->payment_method,
            'payment_reference' => $this->payment_reference,
            'summary' => [
                'items_count' => $items->count(),
                'batch_count' => $itemBatches->count(),
                'subtotal' => self::num($this->subtotal_amount),
                'discount_total' => self::num($this->discount_amount),
                'service_charge' => self::num($this->service_charge_amount),
                'tax_total' => self::num($this->tax_amount),
                'grand_total' => self::num($this->total_amount),
            ],
            // Organization di root level — selalu tersedia untuk open bill (dengan/tanpa meja)
            'organization' => $resolvedOrg ? [
                'id' => $resolvedOrg->id,
                'name' => $resolvedOrg->name,
                'slug' => $resolvedOrg->slug,
            ] : null,

            'payment_expires_at' => $this->payment_expires_at?->toIso8601String(),
            'qris' => [
                'active' => $this->metadata['qris_active'] ?? null,
                'attempts_count' => is_array($this->metadata['qris_attempts'] ?? null)
                    ? count($this->metadata['qris_attempts'])
                    : 0,
                'is_expired' => $this->payment_status?->value === 'pending'
                    && $this->payment_expires_at !== null
                    && $this->payment_expires_at->isPast(),
            ],
            // Customer
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            // Nomor Penanda Pesanan
            'order_marker_number' => $this->order_marker_number,
            // Financial
            'subtotal_amount' => self::num($this->subtotal_amount),
            'discount_amount' => self::num($this->discount_amount),
            'tax_amount' => self::num($this->tax_amount),
            'service_charge_amount' => self::num($this->service_charge_amount),
            'total_amount' => self::num($this->total_amount),
            'payment_amount' => self::num($this->payment_amount),
            'change_amount' => self::num($this->change_amount),
            // Snapshot rates
            'tax_rate_snapshot' => self::num($this->tax_rate_snapshot),
            'service_charge_rate_snapshot' => self::num($this->service_charge_rate_snapshot),
            // Timestamps
            'note' => $this->note,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            'cancelled_by' => $this->whenLoaded('cancelledBy', fn () => [
                'id' => $this->cancelledBy->id,
                'name' => $this->cancelledBy->name,
            ]),
            // Relations
            'dining_table' => $this->whenLoaded('diningTable', fn () => [
                'id' => $this->diningTable->id,
                'name' => $this->diningTable->name,
                'code' => $this->diningTable->code,
                'location' => $this->diningTable->location,
                'organization' => $this->diningTable->relationLoaded('organization') ? [
                    'id' => $this->diningTable->organization->id,
                    'name' => $this->diningTable->organization->name,
                    'slug' => $this->diningTable->organization->slug,
                ] : null,
            ]),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            // Items nested (root items + children)
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'item_batches' => $itemBatches,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
