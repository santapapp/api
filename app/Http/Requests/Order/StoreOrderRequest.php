<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_type'      => ['required', 'string', Rule::in(['cashier_order', 'open_bill'])],
            'dining_table_id' => ['nullable', 'integer', 'exists:dining_tables,id'],
            'customer_name'   => ['sometimes', 'nullable', 'string', 'max:100'],
            'customer_phone'  => ['sometimes', 'nullable', 'string', 'max:20'],
            'note'            => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
