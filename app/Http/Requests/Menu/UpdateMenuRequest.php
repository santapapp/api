<?php

declare(strict_types=1);

namespace App\Http\Requests\Menu;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['sometimes', 'string', 'max:255'],
            'price'       => ['sometimes', 'numeric', 'min:0'],
            'sort_order'  => ['sometimes', 'integer', 'min:0'],
            'is_available' => ['sometimes', 'boolean'],
            // URL gambar produk yang SUDAH di-host. API TIDAK menerima upload file —
            // kirim URL string, bukan multipart. Kirim null untuk menghapus gambar.
            'image'       => ['sometimes', 'nullable', 'string', 'max:500'],
            'sku'         => ['sometimes', 'nullable', 'string', 'max:50'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_required' => ['sometimes', 'boolean'],
            'min_select'  => ['sometimes', 'integer', 'min:0', 'max:255'],
            'max_select'  => ['sometimes', 'integer', 'min:1', 'max:255'],
            // Objek JSON bebas untuk data tambahan, mis. {"category": "makanan"}.
            'metadata'    => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('min_select') && $this->has('max_select')) {
                if ($this->input('min_select') > $this->input('max_select')) {
                    $validator->errors()->add('min_select', 'min_select tidak boleh lebih besar dari max_select.');
                }
            }
        });
    }
}
