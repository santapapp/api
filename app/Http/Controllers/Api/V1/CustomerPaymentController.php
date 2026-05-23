<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\BillStatus;
use App\Enums\PaymentStatus;
use App\Models\OpenBill;
use App\Models\Payment;
use App\Events\PaymentPaid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CustomerPaymentController extends Controller
{
    /**
     * Create a new QRIS payment for the customer's active open bill.
     */
    public function store(Request $request): JsonResponse
    {
        $session = $request->attributes->get('customer_session');
        $context = app(\App\Services\OrganizationContext::class);

        if (!$session->open_bill_id) {
            return response()->json(['message' => 'Tidak ada tagihan aktif untuk sesi Anda.'], 404);
        }

        $bill = OpenBill::where('id', $session->open_bill_id)
            ->where('status', BillStatus::Open)
            ->first();

        if (!$bill) {
            return response()->json(['message' => 'Tagihan tidak ditemukan atau sudah ditutup.'], 404);
        }

        // Cek jika sudah ada transaksi pending, tidak perlu buat baru, kembalikan yang sudah ada
        $existingPending = Payment::where('open_bill_id', $bill->id)
            ->where('status', PaymentStatus::Pending)
            ->where('method', 'qris')
            ->first();

        if ($existingPending) {
            return response()->json([
                'message' => 'Terdapat transaksi pending yang sudah aktif.',
                'data' => $existingPending,
            ]);
        }

        $paymentNumber = 'PAY-' . strtoupper(Str::random(8));

        // Call the third party create payment endpoint
        $response = Http::post('https://qris.sekeco.id/create', [
            'order_id' => $paymentNumber,
            'gross_amount' => (int) $bill->total_amount,
        ]);

        if ($response->failed()) {
            return response()->json([
                'message' => 'Gagal menghubungi server pembayaran pihak ketiga.',
                'error' => $response->body(),
            ], 502);
        }

        $responseData = $response->json();
        if (!($responseData['ok'] ?? false)) {
            return response()->json([
                'message' => 'Gagal membuat transaksi pembayaran QRIS.',
                'error' => $responseData['message'] ?? 'Unknown error',
            ], 400);
        }

        $payment = DB::transaction(function () use ($bill, $context, $paymentNumber, $responseData) {
            return Payment::create([
                'organization_id' => $context->getOrganizationId(),
                'open_bill_id' => $bill->id,
                'payment_number' => $paymentNumber,
                'method' => 'qris',
                'status' => PaymentStatus::Pending,
                'amount' => $bill->total_amount,
                'paid_amount' => 0.00,
                'change_amount' => 0.00,
                'reference_number' => $responseData['data']['order_id'],
                'metadata' => $responseData['data'],
            ]);
        });

        return response()->json([
            'message' => 'Pembayaran QRIS berhasil diinisiasi.',
            'data' => $payment,
        ], 201);
    }

    /**
     * Check the status of a pending customer payment.
     */
    public function checkStatus(Request $request, string $id): JsonResponse
    {
        $session = $request->attributes->get('customer_session');

        $payment = Payment::where('id', $id)
            ->where('open_bill_id', $session->open_bill_id)
            ->first();

        if (!$payment) {
            return response()->json(['message' => 'Pembayaran tidak ditemukan.'], 404);
        }

        if ($payment->status === PaymentStatus::Paid) {
            return response()->json([
                'message' => 'Pembayaran sudah lunas.',
                'data' => $payment,
            ]);
        }

        if (!$payment->reference_number) {
            return response()->json(['message' => 'Pembayaran tidak memiliki nomor referensi pihak ketiga.'], 400);
        }

        $response = Http::get('https://qris.sekeco.id/check', [
            'id' => $payment->reference_number,
        ]);

        if ($response->failed()) {
            return response()->json([
                'message' => 'Gagal memeriksa status pembayaran ke pihak ketiga.',
                'error' => $response->body(),
            ], 502);
        }

        $responseData = $response->json();
        if (!($responseData['ok'] ?? false)) {
            return response()->json([
                'message' => 'Gagal memeriksa status transaksi.',
                'error' => $responseData['message'] ?? 'Unknown error',
            ], 400);
        }

        $transactionStatus = $responseData['data']['transaction_status'] ?? 'pending';

        if ($transactionStatus === 'settlement' || $transactionStatus === 'capture') {
            DB::transaction(function () use ($payment, $responseData) {
                $payment->update([
                    'status' => PaymentStatus::Paid,
                    'paid_amount' => $payment->amount,
                    'paid_at' => now(),
                    'metadata' => array_merge($payment->metadata ?? [], $responseData['data']),
                ]);
            });

            broadcast(new PaymentPaid($payment));

            return response()->json([
                'message' => 'Pembayaran telah berhasil diverifikasi.',
                'data' => $payment,
            ]);
        } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
            $payment->update([
                'status' => PaymentStatus::Failed,
                'metadata' => array_merge($payment->metadata ?? [], $responseData['data']),
            ]);

            return response()->json([
                'message' => 'Pembayaran dibatalkan atau kedaluwarsa.',
                'data' => $payment,
            ]);
        }

        return response()->json([
            'message' => 'Pembayaran masih tertunda (pending).',
            'data' => $payment,
        ]);
    }

    /**
     * Cancel a pending customer payment.
     */
    public function cancelPayment(Request $request, string $id): JsonResponse
    {
        $session = $request->attributes->get('customer_session');

        $payment = Payment::where('id', $id)
            ->where('open_bill_id', $session->open_bill_id)
            ->first();

        if (!$payment) {
            return response()->json(['message' => 'Pembayaran tidak ditemukan.'], 404);
        }

        if ($payment->status !== PaymentStatus::Pending) {
            return response()->json(['message' => 'Hanya pembayaran tertunda yang bisa dibatalkan.'], 400);
        }

        if (!$payment->reference_number) {
            return response()->json(['message' => 'Pembayaran tidak memiliki nomor referensi pihak ketiga.'], 400);
        }

        $response = Http::delete('https://qris.sekeco.id/cancel', [
            'id' => $payment->reference_number,
        ]);

        if ($response->failed()) {
            return response()->json([
                'message' => 'Gagal membatalkan pembayaran di pihak ketiga.',
                'error' => $response->body(),
            ], 502);
        }

        $responseData = $response->json();
        if (!($responseData['ok'] ?? false)) {
            return response()->json([
                'message' => 'Gagal membatalkan transaksi.',
                'error' => $responseData['message'] ?? 'Unknown error',
            ], 400);
        }

        $payment->update([
            'status' => PaymentStatus::Failed,
            'void_reason' => 'Dibatalkan oleh pengguna',
            'metadata' => array_merge($payment->metadata ?? [], ['cancelled_response' => $responseData]),
        ]);

        return response()->json([
            'message' => 'Pembayaran berhasil dibatalkan.',
            'data' => $payment,
        ]);
    }
}
