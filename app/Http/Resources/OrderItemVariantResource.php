<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Snapshot satu variant yang dipilih customer.
 *
 * @property int         $variant_group_id
 * @property string      $variant_group_name
 * @property int         $variant_id
 * @property string      $variant_name
 * @property string      $price
 */
class OrderItemVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'variant_group_id'   => $this->variant_group_id,
            'variant_group_name' => $this->variant_group_name,
            'variant_id'         => $this->variant_id,
            'variant_name'       => $this->variant_name,
            'price'              => $this->price,
        ];
    }
}
