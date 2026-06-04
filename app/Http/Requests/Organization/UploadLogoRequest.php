<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;

class UploadLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /**
             * File logo organisasi. Dikirim sebagai multipart/form-data.
             * Tipe diizinkan: jpeg, jpg, png, webp. Maks 2 MB.
             */
            'logo' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'logo.required' => 'File logo wajib diunggah.',
            'logo.image'    => 'File harus berupa gambar.',
            'logo.mimes'    => 'Format logo harus jpeg, jpg, png, atau webp.',
            'logo.max'      => 'Ukuran logo maksimal 2 MB.',
        ];
    }
}
