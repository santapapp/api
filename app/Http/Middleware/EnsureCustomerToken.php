<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\BillStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\QrisService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Public-Token');

        if (empty($token)) {
            return response()->json(['message' => 'Token tidak ditemukan.'], 401);
        }

        // Middleware ini KHUSUS untuk open bill aktif. Table order bersifat
        // stateless dan TIDAK pernah valid lewat jalur ini, meskipun punya
        // public_token — table order memakai endpoint publik berbasis
        // order_number/public_token (tracking & payment-status).
        $order = Order::where('public_token', $token)
            ->where('order_type', OrderType::OpenBill)
            ->where('bill_status', BillStatus::Open)
            ->first();

        if (! $order) {
            return response()->json([
                'message' => 'Sesi tidak valid atau sudah berakhir.',
            ], 403);
        }

        // Cek payment timeout — TAPI gateway = source of truth: final sync ke
        // provider lebih dulu sebelum memutuskan expired.
        if (
            $order->payment_status === PaymentStatus::Pending &&
            $order->payment_expires_at !== null &&
            $order->payment_expires_at->isPast()
        ) {
            // Final sync: jika provider ternyata lunas, angkat menjadi paid & lanjut.
            if ($order->payment_reference) {
                $result = app(QrisService::class)->check($order->payment_reference);

                Log::info('EnsureCustomerToken: QRIS sync', [
                    'order_no'              => $order->order_number,
                    'payment_reference'     => $order->payment_reference,
                    'payment_status_before' => $order->payment_status->value,
                    'provider_status'       => $result['status'],
                    'provider_tx_status'    => $result['transaction_status'],
                ]);

                if ($result['paid']) {
                    $order->markPaid(closeBill: true);
                    $request->attributes->set('customer_order', $order->fresh());

                    return $next($request);
                }
            }

            // Benar-benar belum lunas saat deadline → expire (konsisten: cancelled).
            $order->markPaymentExpired('Payment Timeout');

            Log::info('EnsureCustomerToken: order di-expire', [
                'order_no' => $order->order_number,
                'reason'   => 'Payment Timeout (provider belum settlement saat deadline)',
            ]);

            if ($order->payment_reference) {
                try {
                    app(QrisService::class)->cancel($order->payment_reference);
                } catch (\Throwable $e) {
                    Log::warning("EnsureCustomerToken: gagal cancel QRIS order {$order->id}: " . $e->getMessage());
                }
            }

            return response()->json([
                'status'    => 'expired',
                'message'   => 'Waktu pembayaran sudah habis. Silakan buat pesanan ulang.',
                'can_retry' => true,
            ], 403);
        }

        // Simpan order ke request attributes untuk dipakai controller
        $request->attributes->set('customer_order', $order);

        return $next($request);
    }
}
