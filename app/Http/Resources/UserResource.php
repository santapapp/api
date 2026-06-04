<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'email'         => $this->email,
            'phone'         => $this->phone,
            // URL avatar siap pakai (path tersimpan dikonversi ke URL penuh).
            'avatar'        => MediaService::toUrl($this->avatar),
            'status'        => $this->status,
            'is_superadmin' => $this->is_superadmin,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'organizations' => $this->whenLoaded('organizations', fn () =>
                $this->organizations->map(fn ($org) => [
                    'id'   => $org->id,
                    'name' => $org->name,
                    'slug' => $org->slug,
                    'role' => $org->pivot->role,
                ])
            ),
        ];
    }
}
