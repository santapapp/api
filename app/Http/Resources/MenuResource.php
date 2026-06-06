<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\NormalizesNumbers;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuResource extends JsonResource
{
    use NormalizesNumbers;

    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'type'         => $this->type,
            'parent_id'    => $this->parent_id,
            'name'         => $this->name,
            // URL gambar siap pakai (path tersimpan dikonversi ke URL penuh).
            'image'        => MediaService::toUrl($this->image),
            'sku'          => $this->sku,
            'description'  => $this->description,
            'price'        => self::num($this->price),
            'is_available' => $this->is_available,
            'is_required'  => $this->is_required,
            'min_select'   => $this->min_select,
            'max_select'   => $this->max_select,
            'sort_order'   => $this->sort_order,
            /**
             * Data fleksibel tambahan. Untuk produk, dapat menyimpan kategori dalam format {"category": "makanan"}.
             * @var array{category?: string}|null
             */
            'metadata'     => $this->metadata,
            'children'     => MenuResource::collection($this->whenLoaded('children')),
        ];
    }
}
