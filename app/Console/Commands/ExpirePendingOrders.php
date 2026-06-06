<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Services\OrderQrisPaymentService;
use App\Services\QrisService;
use Illuminate\Support\Facades\Log;

class ExpirePendingOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:expire-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Final-sync pending orders to QRIS provider, then expire only those still unpaid';

    /**
     * Execute the console command.
     *
     * Fallback sync periodik (pengganti webhook): untuk setiap order pending yang
     * sudah lewat deadline, WAJIB cek provider lebih dulu. Jika provider settlement
     * → rekonsiliasi menjadi paid. Hanya yang benar-benar belum lunas yang di-expire.
     * Order TIDAK PERNAH dibatalkan hanya berdasarkan deadline lokal.
     */
    public function handle(QrisService $qris, OrderQrisPaymentService $payments)
    {
        $expiredOrders = Order::where('payment_status', PaymentStatus::Pending)
            ->whereNotNull('payment_expires_at')
            ->where('payment_expires_at', '<=', now())
            ->get();

        if ($expiredOrders->isEmpty()) {
            $this->info('No expired pending orders found.');
            return;
        }

        $expired    = 0;
        $reconciled = 0;

        foreach ($expiredOrders as $order) {
            if ($order->order_type === OrderType::OpenBill) {
                $payments->releaseExpiredPending($order, $qris);
                $expired++;

                Log::info('ExpirePendingOrders: open bill QRIS attempt released', [
                    'order_no' => $order->order_number,
                ]);

                continue;
            }

            // (1) Final sync ke provider sebelum keputusan apa pun.
            if ($order->payment_reference) {
                $result = $qris->check($order->payment_reference);

                Log::info('ExpirePendingOrders: QRIS sync', [
                    'order_no'              => $order->order_number,
                    'payment_reference'     => $order->payment_reference,
                    'payment_status_before' => $order->payment_status->value,
                    'provider_status'       => $result['status'],
                    'provider_tx_status'    => $result['transaction_status'],
                ]);

                if ($result['paid']) {
                    $order->markPaid(closeBill: $order->order_type === OrderType::OpenBill);

                    Log::info('ExpirePendingOrders: order DIREKONSILIASI menjadi PAID', [
                        'order_no'             => $order->order_number,
                        'payment_status_after' => $order->payment_status->value,
                    ]);

                    $reconciled++;
                    continue;
                }
            }

            // (2) Belum lunas saat deadline → expire.
            $order->markPaymentExpired('Payment Timeout');

            Log::info('ExpirePendingOrders: order di-expire', [
                'order_no' => $order->order_number,
                'reason'   => 'Payment Timeout (provider belum settlement saat deadline)',
            ]);

            if ($order->payment_reference) {
                try {
                    $qris->cancel($order->payment_reference);
                } catch (\Throwable $e) {
                    Log::error("Failed to cancel QRIS for expired order {$order->id}: " . $e->getMessage());
                }
            }

            $expired++;
        }

        $this->info("Expired {$expired} orders, reconciled {$reconciled} as paid.");
    }
}
