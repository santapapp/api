<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @body array{
 *   items: array<int, array{
 *     menu_id: int,
 *     quantity: int,
 *     note?: string|null,
 *     notes?: string|null,
 *     selected_options?: array<int, array{
 *       group_id: int,
 *       option_id: int
 *     }>,
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_id' => ['required', 'integer', 'exists:menus,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],

            'items.*.selected_options' => ['nullable', 'array'],
            'items.*.selected_options.*.group_id' => ['required', 'integer', 'exists:menus,id'],
            'items.*.selected_options.*.option_id' => ['required', 'integer', 'exists:menus,id'],

            'items.*.selected_variants' => ['nullable', 'array'],
            'items.*.selected_variants.*.variant_group_id' => ['required', 'integer', 'exists:menus,id'],
            'items.*.selected_variants.*.variant_id' => ['required', 'integer', 'exists:menus,id'],
        ];
    }
}
