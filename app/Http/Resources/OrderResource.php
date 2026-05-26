<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Order summary — untuk list (index).
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                           => $this->id,
            'order_number'                 => $this->order_number,
            'order_type'                   => $this->order_type,
            'bill_status'                  => $this->bill_status,
            'order_status'                 => $this->order_status,
            'payment_status'               => $this->payment_status,
            'payment_method'               => $this->payment_method,
            'payment_reference'            => $this->payment_reference,
            // Customer
            'customer_name'                => $this->customer_name,
            'customer_phone'               => $this->customer_phone,
            // Financial
            'subtotal_amount'              => $this->subtotal_amount,
            'discount_amount'              => $this->discount_amount,
            'tax_amount'                   => $this->tax_amount,
            'service_charge_amount'        => $this->service_charge_amount,
            'total_amount'                 => $this->total_amount,
            'payment_amount'               => $this->payment_amount,
            'change_amount'                => $this->change_amount,
            // Snapshot rates
            'tax_rate_snapshot'            => $this->tax_rate_snapshot,
            'service_charge_rate_snapshot' => $this->service_charge_rate_snapshot,
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
