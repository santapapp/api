<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @body array{
 *   items: array<int, array{
 *     menu_id: int,
 *     quantity: int,
 *     note?: string|null,
 *     notes?: string|null,
 *     selected_variants?: array<int, array{
 *       variant_group_id: int,
 *       variant_id: int
 *     }>
 *   }>
 * }
 */
class AddItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ── Root array ────────────────────────────────────────────────────
            'items'          => ['required', 'array', 'min:1'],

            // ── Per item ──────────────────────────────────────────────────────
            'items.*.menu_id'  => ['required', 'integer', 'exists:menus,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],

            // Support both 'note' and 'notes' temporarily
            'items.*.note'  => ['nullable', 'string', 'max:500'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],

            // ── Selected variants (new canonical payload) ─────────────────────
            'items.*.selected_variants'                          => ['nullable', 'array'],
            'items.*.selected_variants.*.variant_group_id'       => ['required', 'integer', 'exists:menus,id'],
            'items.*.selected_variants.*.variant_id'             => ['required', 'integer', 'exists:menus,id'],

            // ── Legacy children[] — backward compat only ──────────────────────
            // Tidak diproses oleh service baru. Masih divalidasi format-nya agar
            // tidak melempar error 500 jika dikirim, tapi bisnis logikanya di-skip.
            'items.*.children'           => ['nullable', 'array'],
            'items.*.children.*.menu_id' => ['required_with:items.*.children', 'integer', 'exists:menus,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'                  => 'Daftar item tidak boleh kosong.',
            'items.*.menu_id.required'        => 'Menu ID wajib diisi.',
            'items.*.menu_id.exists'          => 'Menu tidak ditemukan.',
            'items.*.quantity.required'       => 'Jumlah item wajib diisi.',
            'items.*.quantity.min'            => 'Jumlah item minimal 1.',
            'items.*.quantity.max'            => 'Jumlah item maksimal 99 per sekali order.',
            'items.*.selected_variants.*.variant_group_id.required' => 'Variant group wajib dipilih.',
            'items.*.selected_variants.*.variant_group_id.exists'   => 'Variant group tidak ditemukan.',
            'items.*.selected_variants.*.variant_id.required'       => 'Variant wajib dipilih.',
            'items.*.selected_variants.*.variant_id.exists'         => 'Variant tidak ditemukan.',
        ];
    }
}
