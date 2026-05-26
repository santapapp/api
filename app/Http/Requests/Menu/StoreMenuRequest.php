<?php

declare(strict_types=1);

namespace App\Http\Requests\Menu;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'       => ['required', 'string', Rule::in(['product', 'variant_group', 'variant', 'addon_group', 'addon'])],
            'parent_id'  => ['nullable', 'integer', 'exists:menus,id'],
            'name'       => ['required', 'string', 'max:255'],
            'price'      => ['sometimes', 'numeric', 'min:0'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            // Kolom baru (product only)
            'image'       => ['sometimes', 'nullable', 'string', 'max:500'],
            'sku'         => ['sometimes', 'nullable', 'string', 'max:50'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            // Selection rules (variant_group / addon_group only)
            'is_required' => ['sometimes', 'boolean'],
            'min_select'  => ['sometimes', 'integer', 'min:0', 'max:255'],
            'max_select'  => ['sometimes', 'integer', 'min:1', 'max:255'],
            'metadata'    => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $minSelect = $this->input('min_select', 0);
            $maxSelect = $this->input('max_select', 1);

            if ($minSelect > $maxSelect) {
                $validator->errors()->add('min_select', 'min_select tidak boleh lebih besar dari max_select.');
            }
        });
    }
}
