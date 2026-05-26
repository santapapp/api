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
            'price'          => $this->price,
            'quantity'       => $this->quantity,
            'subtotal'       => $this->subtotal,
            'item_status'    => $this->item_status,
            'note'           => $this->note,
            'metadata'       => $this->metadata,
            'children'       => OrderItemResource::collection($this->whenLoaded('children')),
        ];
    }
}
