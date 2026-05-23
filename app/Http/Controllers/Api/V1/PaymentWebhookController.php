<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Events\PaymentPaid;
use App\Services\OrganizationContext;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentWebhookController extends Controller
{
    /**
     * Handle payment webhook notification from the third-party provider.
     */
    public function handle(Request $request): JsonResponse
    {
        $orderId = $request->input('order_id');
        $transactionStatus = $request->input('transaction_status');

        if (!$orderId || !$transactionStatus) {
            return response()->json(['message' => 'Payload tidak valid.'], 400);
        }

        // Cari pembayaran secara global
        $payment = Payment::where('reference_number', $orderId)->first();

        if (!$payment) {
            return response()->json(['message' => 'Pembayaran tidak ditemukan.'], 404);
        }

        // Set context tenant secara dinamis berdasarkan data pembayaran yang ditemukan
        $organization = $payment->organization;
        app(OrganizationContext::class)->set($organization);
        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);

        if ($payment->status === PaymentStatus::Paid) {
            return response()->json(['message' => 'Pembayaran sudah diproses sebelumnya.']);
        }

        if ($transactionStatus === 'settlement' || $transactionStatus === 'capture') {
            $fraudStatus = $request->input('fraud_status');
            if ($transactionStatus === 'capture' && $fraudStatus && $fraudStatus !== 'accept') {
                return response()->json(['message' => 'Transaksi ditolak karena status fraud.'], 400);
            }

            DB::transaction(function () use ($payment, $request) {
                $payment->update([
                    'status' => PaymentStatus::Paid,
                    'paid_amount' => $payment->amount,
                    'paid_at' => now(),
                    'metadata' => array_merge($payment->metadata ?? [], $request->all()),
                ]);
            });

            broadcast(new PaymentPaid($payment));

            return response()->json(['message' => 'Pembayaran berhasil dikonfirmasi lunas.']);
        } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
            DB::transaction(function () use ($payment, $request) {
                $payment->update([
                    'status' => PaymentStatus::Failed,
                    'metadata' => array_merge($payment->metadata ?? [], $request->all()),
                ]);
            });

            return response()->json(['message' => 'Pembayaran gagal atau dibatalkan.']);
        }

        return response()->json(['message' => 'Status transaksi tidak berubah: ' . $transactionStatus]);
    }
}
