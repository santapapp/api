<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'parent_item_id' => $this->parent_item_id,
            'item_type'      => $this->item_type,
            'menu_id'        => $this->menu_id,
            'name'           => $this->name,

            // Harga breakdown
            'base_price'     => $this->base_price,
            'variant_total'  => $this->variant_total,
            'unit_price'     => $this->unit_price,
            'price'          => $this->price,    // legacy — sama dengan unit_price
            'quantity'       => $this->quantity,
            'subtotal'       => $this->subtotal,

            'item_status'    => $this->item_status,
            'note'           => $this->note,
            'metadata'       => $this->metadata,

            // New canonical: selected variants dengan snapshot lengkap
            'selected_variants' => OrderItemVariantResource::collection(
                $this->whenLoaded('selectedVariants')
            ),

            // Legacy: child items (backward compat)
            'children' => OrderItemResource::collection(
                $this->whenLoaded('children')
            ),
        ];
    }
}
