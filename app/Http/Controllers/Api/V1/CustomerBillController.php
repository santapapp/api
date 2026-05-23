<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OpenBill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerBillController extends Controller
{
    /**
     * Show the active open bill for the current customer session table.
     */
    public function show(Request $request): JsonResponse
    {
        $session = $request->attributes->get('customer_session');

        if (!$session->open_bill_id) {
            return response()->json([
                'message' => 'Tidak ada tagihan aktif untuk sesi Anda.',
            ], 404);
        }

        // Ambil open bill (scoping otomatis oleh BelongsToOrganization)
        $bill = OpenBill::with(['table', 'payments'])->find($session->open_bill_id);

        if (!$bill || $bill->status->value !== 'open') {
            return response()->json([
                'message' => 'Tagihan tidak ditemukan atau telah ditutup.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $bill->id,
                'bill_number' => $bill->bill_number,
                'status' => $bill->status,
                'subtotal_amount' => $bill->subtotal_amount,
                'discount_amount' => $bill->discount_amount,
                'service_amount' => $bill->service_amount,
                'tax_amount' => $bill->tax_amount,
                'total_amount' => $bill->total_amount,
                'opened_at' => $bill->opened_at->toIso8601String(),
                'table' => [
                    'id' => $bill->table->id,
                    'name' => $bill->table->name,
                    'code' => $bill->table->code,
                ],
                'payments' => $bill->payments->map(fn ($p) => [
                    'id' => $p->id,
                    'payment_number' => $p->payment_number,
                    'method' => $p->method,
                    'status' => $p->status,
                    'amount' => $p->amount,
                    'paid_at' => $p->paid_at?->toIso8601String(),
                    'qr_string' => $p->metadata['qr_string'] ?? null,
                    'expiry_time' => $p->metadata['expiry_time'] ?? null,
                ]),
            ],
        ]);
    }
}
