<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\NormalizesNumbers;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Order summary — untuk list (index).
 */
class OrderResource extends JsonResource
{
    use NormalizesNumbers;

    public function toArray(Request $request): array
    {
        return [
            'id'                           => $this->id,
            'order_number'                 => $this->order_number,
            // Enum fields di-serialize sebagai string value secara eksplisit.
            'order_type'                   => $this->order_type?->value,
            'bill_status'                  => $this->bill_status?->value,
            'order_status'                 => $this->order_status?->value,
            'payment_status'               => $this->payment_status?->value,
            'payment_method'               => $this->payment_method,
            'payment_reference'            => $this->payment_reference,
            'qris'                         => [
                'active'         => $this->metadata['qris_active'] ?? null,
                'attempts_count' => is_array($this->metadata['qris_attempts'] ?? null)
                    ? count($this->metadata['qris_attempts'])
                    : 0,
                'is_expired'     => $this->payment_status?->value === 'pending'
                    && $this->payment_expires_at !== null
                    && $this->payment_expires_at->isPast(),
            ],
            // Customer
            'customer_name'                => $this->customer_name,
            'customer_phone'               => $this->customer_phone,
            // Nomor Penanda Pesanan
            'order_marker_number'          => $this->order_marker_number,
            // Financial
            'subtotal_amount'              => self::num($this->subtotal_amount),
            'discount_amount'              => self::num($this->discount_amount),
            'tax_amount'                   => self::num($this->tax_amount),
            'service_charge_amount'        => self::num($this->service_charge_amount),
            'total_amount'                 => self::num($this->total_amount),
            'payment_amount'               => self::num($this->payment_amount),
            'change_amount'                => self::num($this->change_amount),
            // Snapshot rates
            'tax_rate_snapshot'            => self::num($this->tax_rate_snapshot),
            'service_charge_rate_snapshot' => self::num($this->service_charge_rate_snapshot),
            // Timestamps
            'note'         => $this->note,
            'opened_at'    => $this->opened_at?->toIso8601String(),
            'closed_at'    => $this->closed_at?->toIso8601String(),
            'paid_at'      => $this->paid_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            // Cancel info
            'cancel_reason'  => $this->cancel_reason,
            'cancelled_by'   => $this->whenLoaded('cancelledBy', fn () => [
                'id'   => $this->cancelledBy->id,
                'name' => $this->cancelledBy->name,
            ]),
            // Relations
            'dining_table' => $this->whenLoaded('diningTable', fn () => [
                'id'   => $this->diningTable->id,
                'name' => $this->diningTable->name,
                'code' => $this->diningTable->code,
            ]),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id'   => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
