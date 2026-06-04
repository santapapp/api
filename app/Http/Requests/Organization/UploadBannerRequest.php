<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;

class UploadBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /**
             * File banner organisasi. Dikirim sebagai multipart/form-data.
             * Tipe diizinkan: jpeg, jpg, png, webp. Maks 2 MB.
             */
            'banner' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'banner.required' => 'File banner wajib diunggah.',
            'banner.image'    => 'File harus berupa gambar.',
            'banner.mimes'    => 'Format banner harus jpeg, jpg, png, atau webp.',
            'banner.max'      => 'Ukuran banner maksimal 2 MB.',
        ];
    }
}
