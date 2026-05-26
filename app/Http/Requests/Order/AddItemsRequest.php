<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class AddItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'                       => ['required', 'array', 'min:1'],
            'items.*.menu_id'             => ['required', 'integer', 'exists:menus,id'],
            'items.*.quantity'            => ['required', 'integer', 'min:1'],
            'items.*.note'                => ['nullable', 'string', 'max:500'],
            'items.*.children'            => ['nullable', 'array'],
            'items.*.children.*.menu_id'  => ['required', 'integer', 'exists:menus,id'],
        ];
    }
}
