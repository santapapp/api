<?php

declare(strict_types=1);

namespace App\Http\Requests\DiningTable;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDiningTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['sometimes', 'string', 'max:100'],
            'code'      => ['sometimes', 'nullable', 'string', 'max:20'],
            'capacity'  => ['sometimes', 'nullable', 'integer', 'min:1', 'max:255'],
            'location'  => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata'  => ['sometimes', 'nullable', 'array'],
        ];
    }
}
