<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\NormalizesNumbers;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    use NormalizesNumbers;

    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'name'                   => $this->name,
            'slug'                   => $this->slug,
            'is_active'              => $this->is_active,
            // Visual — URL siap pakai (path tersimpan dikonversi ke URL penuh).
            'logo'                   => MediaService::toUrl($this->logo),
            'banner'                 => MediaService::toUrl($this->banner),
            // Kontak
            'phone'                  => $this->phone,
            'email'                  => $this->email,
            // Alamat
            'address'                => $this->address,
            'city'                   => $this->city,
            'province'               => $this->province,
            'postal_code'            => $this->postal_code,
            // GPS
            'latitude'               => self::num($this->latitude),
            'longitude'              => self::num($this->longitude),
            // Lokalisasi
            'timezone'               => $this->timezone,
            'currency'               => $this->currency,
            // Pajak
            'tax_enabled'            => $this->tax_enabled,
            'tax_rate'               => self::num($this->tax_rate),
            // Service charge
            'service_charge_enabled' => $this->service_charge_enabled,
            'service_charge_rate'    => self::num($this->service_charge_rate),
            // JSON
            'opening_hours'          => $this->opening_hours,
            'settings'               => $this->settings,
            // Nomor Penanda Pesanan — config untuk mobile app
            'order_marker'           => [
                'mode'       => $this->order_marker_mode ?? 'disabled',
                'max_number' => $this->order_marker_max_number,
                'label'      => 'Nomor Penanda Pesanan',
            ],
            // Role saat diload via pivot
            'role'                   => $this->whenPivotLoaded('organization_members', fn () => $this->pivot->role),
        ];
    }
}
