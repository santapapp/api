<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiningTableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'code'             => $this->code,
            'capacity'         => $this->capacity,
            'location'         => $this->location,
            'qr_token'         => $this->qr_token,
            'is_active'        => $this->is_active,
            'metadata'         => $this->metadata,
            'has_active_order' => $this->whenLoaded('orders', fn () => $this->activeOrder()->exists()),
        ];
    }
}
