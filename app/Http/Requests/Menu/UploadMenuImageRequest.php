<?php

declare(strict_types=1);

namespace App\Http\Requests\Menu;

use Illuminate\Foundation\Http\FormRequest;

class UploadMenuImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /**
             * File gambar produk. Dikirim sebagai multipart/form-data.
             * Tipe diizinkan: jpeg, jpg, png, webp. Maks 2 MB.
             */
            'image' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'File gambar wajib diunggah.',
            'image.image'    => 'File harus berupa gambar.',
            'image.mimes'    => 'Format gambar harus jpeg, jpg, png, atau webp.',
            'image.max'      => 'Ukuran gambar maksimal 2 MB.',
        ];
    }
}
