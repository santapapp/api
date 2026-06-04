<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'   => ['sometimes', 'string', 'max:255'],
            'phone'  => ['sometimes', 'nullable', 'string', 'max:20'],
            // URL avatar yang SUDAH di-host. API TIDAK menerima upload file —
            // kirim URL string, bukan multipart. Kirim null untuk menghapus.
            'avatar' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
