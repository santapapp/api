<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'type'         => $this->type,
            'parent_id'    => $this->parent_id,
            'name'         => $this->name,
            'image'        => $this->image,
            'sku'          => $this->sku,
            'description'  => $this->description,
            'price'        => $this->price,
            'is_available' => $this->is_available,
            'is_required'  => $this->is_required,
            'min_select'   => $this->min_select,
            'max_select'   => $this->max_select,
            'sort_order'   => $this->sort_order,
            'metadata'     => $this->metadata,
            'children'     => MenuResource::collection($this->whenLoaded('children')),
        ];
    }
}
