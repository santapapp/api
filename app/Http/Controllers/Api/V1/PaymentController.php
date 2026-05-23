<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\BillStatus;
use App\Enums\CustomerSessionStatus;
use App\Enums\PaymentStatus;
use App\Enums\TableStatus;
use App\Models\OpenBill;
use App\Models\Payment;
use App\Events\PaymentPaid;
use App\Events\TableStatusChanged;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $context = app(\App\Services\OrganizationContext::class);
        $validator = Validator::make($request->all(), [
            'open_bill_id' => 'required|uuid',
            'method' => 'required|string|in:cash,qris,bank_transfer,card,other',
            'paid_amount' => 'required_unless:method,qris|numeric|min:0',
            'reference_number' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors()
            ], 422);
        }

        $bill = OpenBill::where('id', $request->open_bill_id)
            ->where('organization_id', $context->getOrganizationId())
            ->first();

        if (!$bill || $bill->status !== BillStatus::Open) {
            return response()->json(['message' => 'Tagihan tidak ditemukan atau sudah tidak aktif.'], 404);
        }

        $method = $request->method;
        $paymentNumber = 'PAY-' . strtoupper(Str::random(8));

        if ($method === 'qris') {
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

        $changeAmount = $request->paid_amount - $bill->total_amount;
        if ($changeAmount < 0) {
            return response()->json(['message' => 'Jumlah bayar kurang dari total tagihan.'], 400);
        }

        $payment = DB::transaction(function () use ($request, $bill, $context, $changeAmount, $paymentNumber) {
            $payment = Payment::create([
                'organization_id' => $context->getOrganizationId(),
                'open_bill_id' => $bill->id,
                'payment_number' => $paymentNumber,
                'method' => $request->method,
                'status' => PaymentStatus::Paid,
                'amount' => $bill->total_amount,
                'paid_amount' => $request->paid_amount,
                'change_amount' => $changeAmount,
                'reference_number' => $request->reference_number,
                'paid_by' => $request->user()?->id,
                'paid_at' => now(),
            ]);

            return $payment;
        });

        broadcast(new PaymentPaid($payment));

        return response()->json([
            'message' => 'Pembayaran berhasil dicatat.',
            'data' => $payment,
        ], 201);
    }

    public function checkStatus(Request $request, string $id): JsonResponse
    {
        $context = app(\App\Services\OrganizationContext::class);
        $payment = Payment::where('id', $id)
            ->where('organization_id', $context->getOrganizationId())
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

    public function cancelPayment(Request $request, string $id): JsonResponse
    {
        $context = app(\App\Services\OrganizationContext::class);
        $payment = Payment::where('id', $id)
            ->where('organization_id', $context->getOrganizationId())
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

    public function closeBill(Request $request, string $id): JsonResponse
    {
        $context = app(\App\Services\OrganizationContext::class);
        $bill = OpenBill::where('id', $id)
            ->where('organization_id', $context->getOrganizationId())
            ->with(['table', 'sessions', 'payments'])
            ->first();

        if (!$bill || $bill->status !== BillStatus::Open) {
            return response()->json(['message' => 'Tagihan tidak ditemukan atau sudah tidak aktif.'], 404);
        }

        // Pastikan sudah lunas
        $isPaid = $bill->payments()->where('status', PaymentStatus::Paid->value)->exists();
        if (!$isPaid) {
            return response()->json(['message' => 'Tagihan belum dibayar lunas.'], 400);
        }

        DB::transaction(function () use ($bill, $request) {
            // Close Bill
            $bill->update([
                'status' => BillStatus::Closed,
                'closed_by' => $request->user()->id,
                'closed_at' => now(),
            ]);

            // Close Sessions
            foreach ($bill->sessions as $session) {
                if ($session->status === CustomerSessionStatus::Active) {
                    $session->update([
                        'status' => CustomerSessionStatus::Closed,
                        'closed_at' => now(),
                    ]);
                }
            }

            // Update Table Status
            $table = $bill->table;
            $table->update([
                'status' => TableStatus::Available,
            ]);

            broadcast(new TableStatusChanged($table));
        });

        return response()->json([
            'message' => 'Tagihan berhasil ditutup. Meja sekarang tersedia.',
        ]);
    }
}
