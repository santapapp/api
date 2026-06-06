<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Actions;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\OrderQrisPaymentService;
use App\Services\QrisService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;

class OrderActions
{
    public static function advanceStatus(): Action
    {
        return Action::make('advance_status')
            ->label(fn (Order $record): string => match ($record->order_status) {
                OrderStatus::Confirmed => 'Mulai Masak',
                OrderStatus::Preparing => 'Tandai Semua Siap',
                OrderStatus::Ready => 'Tandai Tersaji (Selesai)',
                default => 'Majukan',
            })
            ->icon(fn (Order $record): string => match ($record->order_status) {
                OrderStatus::Confirmed => 'heroicon-o-fire',
                OrderStatus::Preparing => 'heroicon-o-bell-alert',
                OrderStatus::Ready => 'heroicon-o-check-circle',
                default => 'heroicon-o-arrow-right',
            })
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Majukan Tahap Dapur')
            ->modalDescription('Semua item aktif akan dimajukan satu tahap, dan status pesanan menyesuaikan otomatis.')
            ->visible(fn (Order $record): bool => in_array(
                $record->order_status,
                [OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Ready],
                true,
            ))
            ->action(function (Order $record): void {
                $record->advanceItems();

                Notification::make()
                    ->title('Status diperbarui')
                    ->body("Pesanan {$record->order_number} -> {$record->refresh()->order_status->getLabel()}.")
                    ->success()
                    ->send();
            });
    }

    public static function createQris(): Action
    {
        return Action::make('create_qris')
            ->label(fn (Order $record): string => app(OrderQrisPaymentService::class)->isLocallyExpiredPending($record)
                ? 'Regenerate QRIS'
                : 'Buat QRIS Pembayaran')
            ->icon('heroicon-o-qr-code')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Buat QRIS Pembayaran')
            ->modalDescription('QRIS dibuat untuk order ini saja. Order lain tidak terpengaruh walau ada QRIS pending.')
            ->visible(function (Order $record): bool {
                $payments = app(OrderQrisPaymentService::class);

                if ($record->payment_status === PaymentStatus::Paid || (float) $record->total_amount <= 0) {
                    return false;
                }

                return $record->payment_status !== PaymentStatus::Pending
                    || $payments->isLocallyExpiredPending($record);
            })
            ->action(function (Order $record): void {
                try {
                    $result = app(OrderQrisPaymentService::class)->create($record, app(QrisService::class));
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Gagal membuat QRIS')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title($result['reused'] ? 'QRIS pending masih aktif' : 'QRIS payment dibuat')
                    ->body($result['reused']
                        ? 'Order ini sudah punya QRIS pending aktif. Gunakan QRIS existing.'
                        : 'QRIS baru berhasil dibuat untuk order ini.')
                    ->success()
                    ->send();
            });
    }

    public static function cancelOrder(): Action
    {
        return Action::make('cancel_order')
            ->label('Batalkan Pesanan')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Order $record): bool => ! in_array(
                $record->order_status,
                [OrderStatus::Completed, OrderStatus::Cancelled],
                true,
            ))
            ->schema([
                Textarea::make('cancel_reason')
                    ->label('Alasan Pembatalan')
                    ->required()
                    ->maxLength(255),
            ])
            ->requiresConfirmation()
            ->action(function (array $data, Order $record): void {
                if ($record->payment_status === PaymentStatus::Pending && $record->payment_reference) {
                    try {
                        app(OrderQrisPaymentService::class)->cancel($record, app(QrisService::class));
                        $record->refresh();
                    } catch (\Throwable) {
                        // Pembatalan order tetap lanjut.
                    }
                }

                $payment = in_array($record->payment_status, [PaymentStatus::Pending, PaymentStatus::Unpaid], true)
                    ? PaymentStatus::Cancelled
                    : $record->payment_status;

                $record->update([
                    'order_status' => OrderStatus::Cancelled,
                    'payment_status' => $payment,
                    'cancel_reason' => $data['cancel_reason'],
                    'cancelled_at' => now(),
                ]);

                $record->cancelItems();

                Notification::make()
                    ->title('Pesanan dibatalkan')
                    ->body("Pesanan {$record->order_number} dibatalkan.")
                    ->success()
                    ->send();
            });
    }

    public static function markPaidCash(): Action
    {
        return Action::make('mark_paid_cash')
            ->label('Tandai Lunas (Tunai)')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Order $record): bool => $record->payment_status !== PaymentStatus::Paid
                && $record->order_status !== OrderStatus::Cancelled)
            ->action(function (Order $record): void {
                if (! $record->payment_method) {
                    $record->update(['payment_method' => 'cash']);
                }

                $record->markPaid(closeBill: $record->order_type === OrderType::OpenBill);

                Notification::make()
                    ->title('Ditandai lunas')
                    ->body("Pesanan {$record->order_number} ditandai lunas (tunai).")
                    ->success()
                    ->send();
            });
    }

    public static function syncFromSekeco(): Action
    {
        return Action::make('sync_sekeco')
            ->label('Sinkron dari Sekeco')
            ->icon('heroicon-o-arrow-path')
            ->color('info')
            ->visible(fn (Order $record): bool => $record->payment_method === 'qris'
                && $record->payment_status === PaymentStatus::Pending
                && ! empty($record->payment_reference))
            ->action(function (Order $record): void {
                $sync = app(OrderQrisPaymentService::class)->sync($record, app(QrisService::class));
                $result = $sync['result'];

                if ($result['paid']) {
                    Notification::make()
                        ->title('Pembayaran LUNAS')
                        ->body("Order {$record->order_number} sudah settlement di provider.")
                        ->success()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Status provider: ' . $result['status'])
                    ->body($result['status'] === 'pending'
                        ? 'Pembayaran masih menunggu.'
                        : 'Attempt QRIS sudah diperbarui sesuai status provider.')
                    ->info()
                    ->send();
            });
    }

    public static function cancelQris(): Action
    {
        return Action::make('cancel_qris')
            ->label('Batalkan QRIS')
            ->icon('heroicon-o-x-mark')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (Order $record): bool => $record->payment_method === 'qris'
                && $record->payment_status === PaymentStatus::Pending
                && ! empty($record->payment_reference))
            ->action(function (Order $record): void {
                try {
                    app(OrderQrisPaymentService::class)->cancel($record, app(QrisService::class));
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Gagal membatalkan QRIS')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('QRIS dibatalkan')
                    ->body("QRIS pesanan {$record->order_number} dibatalkan.")
                    ->success()
                    ->send();
            });
    }

    public static function paymentDetail(): Action
    {
        return Action::make('payment_detail')
            ->label('Detail Pembayaran')
            ->icon('heroicon-o-credit-card')
            ->color('gray')
            ->visible(fn (Order $record): bool => ! empty($record->payment_reference))
            ->modalHeading('Detail Pembayaran (Sekeco / Midtrans)')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->modalContent(function (Order $record): View {
                $result = app(QrisService::class)->check($record->payment_reference);

                return view('filament.orders.payment-detail', [
                    'order' => $record,
                    'result' => $result,
                ]);
            });
    }
}
