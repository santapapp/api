<?php

declare(strict_types=1);

namespace App\Http\Requests\Kitchen;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateItemStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_status' => ['required', 'string', Rule::in(['preparing', 'ready', 'served', 'cancelled'])],
        ];
    }
}
