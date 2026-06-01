<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Enums\PaymentStatus;
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
    protected $description = 'Automatically expire pending orders that have passed their payment_expires_at time';

    /**
     * Execute the console command.
     */
    public function handle(QrisService $qris)
    {
        $expiredOrders = Order::where('payment_status', PaymentStatus::Pending)
            ->whereNotNull('payment_expires_at')
            ->where('payment_expires_at', '<=', now())
            ->get();

        if ($expiredOrders->isEmpty()) {
            $this->info('No expired pending orders found.');
            return;
        }

        $count = 0;
        foreach ($expiredOrders as $order) {
            // Konsisten dengan tracking/payment-status publik:
            // payment_status=cancelled, order_status=cancelled, alasan timeout.
            $order->markPaymentExpired();

            if ($order->payment_reference) {
                try {
                    $qris->cancel($order->payment_reference);
                } catch (\Exception $e) {
                    Log::error("Failed to cancel QRIS for expired order {$order->id}: " . $e->getMessage());
                }
            }
            $count++;
        }

        $this->info("Successfully expired {$count} pending orders.");
    }
}
