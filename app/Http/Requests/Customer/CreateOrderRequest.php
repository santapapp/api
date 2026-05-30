<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qr_token'       => ['required', 'string', 'exists:dining_tables,qr_token'],
            'items'          => ['required', 'array', 'min:1'],
            'items.*.menu_id'  => ['required', 'integer', 'exists:menus,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.note'  => ['nullable', 'string', 'max:500'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'items.*.selected_variants'                          => ['nullable', 'array'],
            'items.*.selected_variants.*.variant_group_id'       => ['required', 'integer', 'exists:menus,id'],
            'items.*.selected_variants.*.variant_id'             => ['required', 'integer', 'exists:menus,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'qr_token.required'               => 'Token QR meja wajib diisi.',
            'qr_token.exists'                 => 'Meja tidak ditemukan atau token tidak valid.',
            'items.required'                  => 'Daftar item tidak boleh kosong.',
            'items.*.menu_id.required'        => 'Menu ID wajib diisi.',
            'items.*.menu_id.exists'          => 'Menu tidak ditemukan.',
            'items.*.quantity.required'       => 'Jumlah item wajib diisi.',
            'items.*.quantity.min'            => 'Jumlah item minimal 1.',
            'items.*.quantity.max'            => 'Jumlah item maksimal 99 per sekali order.',
        ];
    }
}
