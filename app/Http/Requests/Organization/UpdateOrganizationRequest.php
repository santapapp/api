<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                    => ['sometimes', 'string', 'max:255'],
            // URL logo yang SUDAH di-host. API TIDAK menerima upload file —
            // kirim URL string, bukan multipart. Kirim null untuk menghapus.
            'logo'                    => ['sometimes', 'nullable', 'string', 'max:500'],
            // URL banner yang SUDAH di-host (string URL, bukan upload file).
            'banner'                  => ['sometimes', 'nullable', 'string', 'max:500'],
            'phone'                   => ['sometimes', 'nullable', 'string', 'max:20'],
            'email'                   => ['sometimes', 'nullable', 'email', 'max:255'],
            'address'                 => ['sometimes', 'nullable', 'string', 'max:1000'],
            'city'                    => ['sometimes', 'nullable', 'string', 'max:100'],
            'province'                => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code'             => ['sometimes', 'nullable', 'string', 'max:10'],
            'latitude'                => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude'               => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'timezone'                => ['sometimes', 'string', 'max:50'],
            'currency'                => ['sometimes', 'string', 'size:3'],
            'tax_enabled'             => ['sometimes', 'boolean'],
            'tax_rate'                => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'service_charge_enabled'  => ['sometimes', 'boolean'],
            'service_charge_rate'     => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'opening_hours'           => ['sometimes', 'nullable', 'array'],
            'opening_hours.*.day'     => ['required_with:opening_hours', 'integer', 'between:0,6'],
            'opening_hours.*.is_open' => ['required_with:opening_hours', 'boolean'],
            'opening_hours.*.open'    => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'opening_hours.*.close'   => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'settings'                => ['sometimes', 'nullable', 'array'],
        ];
    }
}
