<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderLifecycleService
{
    public function __construct(
        protected OrderQrisPaymentService $paymentService,
        protected QrisService $qrisService,
    ) {}

    /**
     * Membatalkan pesanan secara keseluruhan dengan database transaction.
     */
    public function cancelOrder(Order $order, ?User $user, string $reason): Order
    {
        return DB::transaction(function () use ($order, $user, $reason): Order {
            $locked = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->order_status === OrderStatus::Cancelled) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'order' => 'Pesanan sudah dibatalkan.',
                ]);
            }

            if (in_array($locked->order_status, [OrderStatus::Completed], true)) {
                return $locked;
            }

            if ($locked->payment_status === PaymentStatus::Paid) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'order' => 'Pesanan yang sudah dibayar tidak dapat dibatalkan.',
                ]);
            }

            // Batalkan QRIS payment attempt jika berstatus Pending
            if ($locked->payment_reference && $locked->payment_status === PaymentStatus::Pending) {
                try {
                    $this->paymentService->cancel($locked, $this->qrisService);
                    $locked->refresh();
                } catch (\Throwable) {
                    // Lanjutkan pembatalan order walaupun cancel di provider gagal/error
                }
            }

            $payment = in_array($locked->payment_status, [PaymentStatus::Pending, PaymentStatus::Unpaid], true)
                ? PaymentStatus::Cancelled
                : $locked->payment_status;

            $updates = [
                'order_status'   => OrderStatus::Cancelled,
                'payment_status' => $payment,
                'cancel_reason'  => $reason,
                'cancelled_by'   => $user?->id,
                'cancelled_at'   => now(),
            ];

            if ($locked->order_type === OrderType::OpenBill) {
                $updates['bill_status'] = BillStatus::Closed;
                $updates['closed_at']   = now();
            }

            $locked->update($updates);
            $locked->cancelItems();

            return $locked->fresh();
        });
    }
}
