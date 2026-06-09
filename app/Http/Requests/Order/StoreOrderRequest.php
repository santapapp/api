<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use App\Services\OrganizationContext;
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
            'order_type'          => ['required', 'string', Rule::in(['cashier_order', 'open_bill'])],
            'dining_table_id'     => ['nullable', 'integer', 'exists:dining_tables,id'],
            'customer_name'       => ['sometimes', 'nullable', 'string', 'max:100'],
            'customer_phone'      => ['sometimes', 'nullable', 'string', 'max:20'],
            'note'                => ['sometimes', 'nullable', 'string', 'max:500'],
            'order_marker_number' => $this->orderMarkerRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'order_marker_number.required' => 'Nomor Penanda Pesanan wajib diisi.',
            'order_marker_number.integer'  => 'Nomor Penanda Pesanan harus berupa angka.',
            'order_marker_number.min'      => 'Nomor Penanda Pesanan minimal 1.',
            'order_marker_number.max'      => 'Nomor Penanda Pesanan tidak boleh lebih dari :max.',
        ];
    }

    /**
     * Tentukan aturan validasi order_marker_number secara dinamis
     * berdasarkan konfigurasi organisasi aktif.
     *
     * Hanya berlaku untuk cashier_order dan open_bill.
     * Jika mode disabled: terima input apapun (akan diabaikan saat save).
     * Jika mode optional: boleh null, tapi jika diisi harus dalam rentang 1–max.
     * Jika mode required: wajib diisi dalam rentang 1–max.
     */
    private function orderMarkerRules(): array
    {
        $org = app(OrganizationContext::class)->get();

        // Fallback aman jika context belum tersedia (mis. saat testing tanpa middleware)
        if ($org === null) {
            return ['nullable'];
        }

        $mode = $org->order_marker_mode ?? 'disabled';
        $max  = $org->order_marker_max_number;

        return match ($mode) {
            'required' => array_filter([
                'required',
                'integer',
                'min:1',
                $max !== null ? "max:{$max}" : null,
            ]),
            'optional'  => array_filter([
                'nullable',
                'integer',
                'min:1',
                $max !== null ? "max:{$max}" : null,
            ]),
            default => ['nullable'], // 'disabled': terima apapun, diabaikan saat save
        };
    }
}
