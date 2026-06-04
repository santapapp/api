<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /**
             * File avatar pengguna. Dikirim sebagai multipart/form-data.
             * Tipe diizinkan: jpeg, jpg, png, webp. Maks 2 MB.
             */
            'avatar' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.required' => 'File avatar wajib diunggah.',
            'avatar.image'    => 'File harus berupa gambar.',
            'avatar.mimes'    => 'Format avatar harus jpeg, jpg, png, atau webp.',
            'avatar.max'      => 'Ukuran avatar maksimal 2 MB.',
        ];
    }
}
