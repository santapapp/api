<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\NormalizesNumbers;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    use NormalizesNumbers;

    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'item_type'   => $this->item_type,
            'menu_id'     => $this->menu_id,
            'name'        => $this->name,

            // Harga breakdown
            'base_price'    => self::num($this->base_price),
            'variant_total' => self::num($this->variant_total),
            'unit_price'    => self::num($this->unit_price),
            'price'         => self::num($this->price),    // legacy — sama dengan unit_price
            'quantity'      => $this->quantity,
            'subtotal'      => self::num($this->subtotal),

            'item_status' => $this->item_status,
            'note'        => $this->note,

            // Snapshot pilihan variant/addon — diambil dari metadata
            'selected_options' => $this->metadata['selected_options'] ?? [],
        ];
    }
}
