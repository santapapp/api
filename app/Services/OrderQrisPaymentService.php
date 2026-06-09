<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BillStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderQrisPaymentService
{
    public function create(Order $order, QrisService $qris): array
    {
        return DB::transaction(function () use ($order, $qris): array {
            $locked = $this->lockOrder($order);

            $this->ensurePayable($locked);
            $this->syncExpiredPendingAttempt($locked, $qris);
            $locked->refresh();
            $this->ensurePayable($locked);

            if ($this->hasActivePendingAttempt($locked)) {
                return [
                    'order'  => $locked,
                    'qris'   => $this->activeAttempt($locked) ?? $this->attemptFromOrder($locked),
                    'reused' => true,
                ];
            }

            $reference = $this->nextReference($locked);
            $expiresAt = now()->addMinutes((int) config('santap.qris.expiry_minutes', 15));
            $result = $qris->create($reference, (float) $locked->total_amount);

            $active = $this->normalizeCreateResult(
                order: $locked,
                reference: $reference,
                expiresAt: $expiresAt,
                providerResult: $result,
            );

            $metadata = $this->metadata($locked);
            $metadata['qris_active'] = $active;

            $locked->update([
                'payment_status'     => PaymentStatus::Pending,
                'payment_method'     => 'qris',
                'payment_reference'  => $reference,
                'payment_expires_at' => $expiresAt,
                'metadata'           => $metadata,
            ]);

            return [
                'order'  => $locked->fresh(),
                'qris'   => $active,
                'reused' => false,
            ];
        });
    }

    public function sync(Order $order, QrisService $qris): array
    {
        return DB::transaction(function () use ($order, $qris): array {
            $locked = $this->lockOrder($order);

            if (! $locked->payment_reference) {
                throw ValidationException::withMessages([
                    'payment' => 'Tidak ada QRIS payment aktif.',
                ]);
            }

            $result = $qris->check($locked->payment_reference);
            $this->applyProviderResult($locked, $result);

            return [
                'order'  => $locked->fresh(),
                'result' => $result,
                'qris'   => $this->activeAttempt($locked->fresh()),
            ];
        });
    }

    public function cancel(Order $order, QrisService $qris): Order
    {
        return DB::transaction(function () use ($order, $qris): Order {
            $locked = $this->lockOrder($order);

            if (! $locked->payment_reference) {
                throw ValidationException::withMessages([
                    'payment' => 'Tidak ada QRIS payment aktif.',
                ]);
            }

            $reference = $locked->payment_reference;
            $qris->cancel($reference);

            $this->archiveActiveAttempt($locked, [
                'status'          => 'cancelled',
                'provider_status' => 'cancelled',
                'cancelled_at'    => now()->toIso8601String(),
            ]);

            $locked->update([
                'payment_status'     => PaymentStatus::Cancelled,
                'payment_reference'  => null,
                'payment_expires_at' => null,
            ]);

            return $locked->fresh();
        });
    }

    public function releaseExpiredPending(Order $order, QrisService $qris): Order
    {
        return DB::transaction(function () use ($order, $qris): Order {
            $locked = $this->lockOrder($order);
            $this->syncExpiredPendingAttempt($locked, $qris);

            return $locked->fresh();
        });
    }

    public function ensureItemsMutable(Order $order, QrisService $qris): Order
    {
        $order = $this->releaseExpiredPending($order, $qris);

        if ($order->payment_status === PaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'items' => 'Item tidak bisa diubah karena order sudah dibayar.',
            ]);
        }

        $isCancelled = $order->order_status === \App\Enums\OrderStatus::Cancelled || $order->cancelled_at !== null;
        $isClosed = $order->bill_status === BillStatus::Closed || $order->closed_at !== null;

        if ($isCancelled || $isClosed) {
            throw ValidationException::withMessages([
                'items' => 'Item tidak bisa diubah karena bill sudah ditutup atau order dibatalkan.',
            ]);
        }

        if ($this->hasActivePendingAttempt($order)) {
            throw ValidationException::withMessages([
                'items' => 'Item tidak bisa diubah saat QRIS masih pending. Batalkan QRIS atau tunggu expired lalu regenerate.',
            ]);
        }

        return $order;
    }

    public function hasActivePendingAttempt(Order $order): bool
    {
        if ($order->payment_status !== PaymentStatus::Pending || blank($order->payment_reference)) {
            return false;
        }

        $active = $this->activeAttempt($order);

        if ($active !== null && ($active['status'] ?? null) !== 'pending') {
            return false;
        }

        return $order->payment_expires_at === null || $order->payment_expires_at->isFuture();
    }

    public function isLocallyExpiredPending(Order $order): bool
    {
        return $order->payment_status === PaymentStatus::Pending
            && filled($order->payment_reference)
            && $order->payment_expires_at !== null
            && $order->payment_expires_at->isPast();
    }

    public function activeAttempt(Order $order): ?array
    {
        $active = $order->metadata['qris_active'] ?? null;

        return is_array($active) ? $active : null;
    }

    public function responsePayload(Order $order, ?array $qris = null): array
    {
        $active = $qris ?? $this->activeAttempt($order) ?? [];

        return [
            'qr_url'             => $active['qr_url'] ?? null,
            'qr_string'          => $active['qr_string'] ?? null,
            'payment_reference'  => $order->payment_reference ?? ($active['reference'] ?? null),
            'payment_status'     => $order->payment_status->value,
            'payment_expires_at' => $order->payment_expires_at?->toIso8601String(),
            'server_time'        => now()->toIso8601String(),
            'amount'             => (float) $order->total_amount,
            'qris_status'        => $active['status'] ?? null,
            'provider_status'    => $active['provider_status'] ?? null,
        ];
    }

    private function ensurePayable(Order $order): void
    {
        if ($order->payment_status === PaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'payment' => 'Order sudah dibayar.',
            ]);
        }

        if ($order->order_status === \App\Enums\OrderStatus::Cancelled || $order->cancelled_at !== null) {
            throw ValidationException::withMessages([
                'payment' => 'QRIS tidak bisa dibuat karena order sudah dibatalkan.',
            ]);
        }

        if ((float) $order->total_amount <= 0) {
            throw ValidationException::withMessages([
                'payment' => 'QRIS tidak bisa dibuat karena total tagihan masih 0.',
            ]);
        }

        if ($order->allItems()->where('item_status', '!=', 'cancelled')->count() === 0) {
            throw ValidationException::withMessages([
                'payment' => 'QRIS tidak bisa dibuat karena order belum memiliki item aktif.',
            ]);
        }

        if ($order->bill_status === BillStatus::Closed || $order->closed_at !== null) {
            throw ValidationException::withMessages([
                'payment' => 'QRIS tidak bisa dibuat karena bill sudah ditutup.',
            ]);
        }
    }

    private function syncExpiredPendingAttempt(Order $order, QrisService $qris): void
    {
        if (! $this->isLocallyExpiredPending($order)) {
            return;
        }

        $result = $qris->check($order->payment_reference);
        $this->applyProviderResult($order, $result, forceExpireWhenPending: true);
    }

    private function applyProviderResult(Order $order, array $result, bool $forceExpireWhenPending = false): void
    {
        $providerStatus = (string) ($result['status'] ?? 'pending');

        if (($result['paid'] ?? false) === true) {
            $metadata = $this->metadata($order);
            $active = $this->activeAttempt($order) ?? $this->attemptFromOrder($order);
            $active['status'] = 'paid';
            $active['provider_status'] = $providerStatus;
            $active['paid_at'] = now()->toIso8601String();
            $metadata['qris_active'] = $active;
            $order->update(['metadata' => $metadata]);
            $order->markPaid(closeBill: $order->order_type === OrderType::OpenBill);

            return;
        }

        $shouldCloseAttempt = in_array($providerStatus, ['expired', 'cancelled', 'denied', 'refunded', 'not_found'], true)
            || ($forceExpireWhenPending && in_array($providerStatus, ['pending', 'error'], true));

        if (! $shouldCloseAttempt) {
            $this->updateActiveProviderStatus($order, $providerStatus);

            return;
        }

        $status = match ($providerStatus) {
            'cancelled' => 'cancelled',
            'denied', 'refunded', 'error' => 'failed',
            default => 'expired',
        };

        $timestampKey = match ($status) {
            'cancelled' => 'cancelled_at',
            'failed' => 'failed_at',
            default => 'expired_at',
        };

        $this->archiveActiveAttempt($order, [
            'status'          => $status,
            'provider_status' => $providerStatus,
            $timestampKey     => now()->toIso8601String(),
        ]);

        $updates = [
            'payment_status'     => $status === 'failed' ? PaymentStatus::Failed : PaymentStatus::Cancelled,
            'payment_reference'  => null,
            'payment_expires_at' => null,
        ];

        if ($order->order_type !== OrderType::OpenBill) {
            $updates['order_status'] = 'cancelled';
            $updates['cancel_reason'] = 'Payment ' . $status;
            $updates['cancelled_at'] = now();
        }

        $order->update($updates);
    }

    private function updateActiveProviderStatus(Order $order, string $providerStatus): void
    {
        $metadata = $this->metadata($order);
        $active = $this->activeAttempt($order) ?? $this->attemptFromOrder($order);
        $active['provider_status'] = $providerStatus;
        $metadata['qris_active'] = $active;
        $order->update(['metadata' => $metadata]);
    }

    private function archiveActiveAttempt(Order $order, array $overrides): void
    {
        $metadata = $this->metadata($order);
        $attempts = $metadata['qris_attempts'] ?? [];
        $active = $this->activeAttempt($order) ?? $this->attemptFromOrder($order);

        $attempts[] = array_merge($active, $overrides);

        $metadata['qris_attempts'] = $attempts;
        unset($metadata['qris_active']);

        $order->update(['metadata' => $metadata]);
    }

    private function normalizeCreateResult(Order $order, string $reference, \Illuminate\Support\Carbon $expiresAt, array $providerResult): array
    {
        $data = is_array($providerResult['data'] ?? null) ? $providerResult['data'] : [];

        return [
            'reference'         => $reference,
            'amount'            => (float) $order->total_amount,
            'status'            => 'pending',
            'provider_status'   => (string) ($data['transaction_status'] ?? $data['status_code'] ?? 'pending'),
            'qr_url'            => $data['actions'][0]['url'] ?? $data['qr_url'] ?? null,
            'qr_string'         => $data['qr_string'] ?? null,
            'provider_order_id' => $data['order_id'] ?? null,
            'transaction_id'    => $data['transaction_id'] ?? null,
            'created_at'        => now()->toIso8601String(),
            'expired_at'        => $expiresAt->toIso8601String(),
        ];
    }

    private function attemptFromOrder(Order $order): array
    {
        return [
            'reference'       => $order->payment_reference,
            'amount'          => (float) $order->total_amount,
            'status'          => $order->payment_status->value,
            'provider_status' => null,
            'created_at'      => $order->created_at?->toIso8601String(),
            'expired_at'      => $order->payment_expires_at?->toIso8601String(),
        ];
    }

    private function nextReference(Order $order): string
    {
        $attempts = $this->metadata($order)['qris_attempts'] ?? [];
        $sequence = count(is_array($attempts) ? $attempts : []) + 1;

        return 'santap-' . $order->id . '-' . $sequence . '-' . Str::lower(Str::random(6));
    }

    private function lockOrder(Order $order): Order
    {
        return Order::query()
            ->whereKey($order->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function metadata(Order $order): array
    {
        return is_array($order->metadata) ? $order->metadata : [];
    }
}
