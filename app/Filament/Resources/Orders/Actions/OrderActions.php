<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Actions;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\QrisService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;

/**
 * Kumpulan action order yang dipakai bersama oleh OrdersTable (per-baris) dan
 * ViewOrder (header). Definisi tunggal agar perilaku konsisten di kedua tempat.
 *
 * Prinsip: gateway = source of truth. Tidak ada override payment bebas — hanya
 * "Tandai Lunas (Tunai)", "Sinkron dari Sekeco", dan "Batalkan QRIS".
 */
class OrderActions
{
    /**
     * Majukan tahap dapur. MODEL ITEM-DRIVEN: aksi ini memajukan SEMUA item aktif
     * satu tahap (pending→preparing→ready→served), lalu order_status diturunkan
     * ulang dari agregat item. Label mengikuti tahap order berikutnya.
     *
     * Hanya tampil setelah order dikonfirmasi (confirmed/preparing/ready) — order
     * pending harus dibayar/dikonfirmasi dulu.
     */
    public static function advanceStatus(): Action
    {
        return Action::make('advance_status')
            ->label(fn (Order $record): string => match ($record->order_status) {
                OrderStatus::Confirmed => 'Mulai Masak',
                OrderStatus::Preparing => 'Tandai Semua Siap',
                OrderStatus::Ready     => 'Tandai Tersaji (Selesai)',
                default                => 'Majukan',
            })
            ->icon(fn (Order $record): string => match ($record->order_status) {
                OrderStatus::Confirmed => 'heroicon-o-fire',
                OrderStatus::Preparing => 'heroicon-o-bell-alert',
                OrderStatus::Ready     => 'heroicon-o-check-circle',
                default                => 'heroicon-o-arrow-right',
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
                    ->body("Pesanan {$record->order_number} → {$record->refresh()->order_status->getLabel()}.")
                    ->success()
                    ->send();
            });
    }

    /**
     * Batalkan pesanan dengan alasan. Item aktif ikut dibatalkan (cascade). Bila
     * QRIS masih pending, batalkan juga di provider dan tandai payment cancelled.
     * Order yang sudah paid tidak diubah pembayarannya.
     */
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
                // Batalkan QRIS bila masih pending (best-effort).
                if ($record->payment_status === PaymentStatus::Pending && $record->payment_reference) {
                    try {
                        app(QrisService::class)->cancel($record->payment_reference);
                    } catch (\Throwable $e) {
                        // Diabaikan — pembatalan order tetap lanjut.
                    }
                }

                // Hanya turunkan payment ke cancelled jika belum lunas.
                $payment = in_array($record->payment_status, [PaymentStatus::Pending, PaymentStatus::Unpaid], true)
                    ? PaymentStatus::Cancelled
                    : $record->payment_status;

                $record->update([
                    'order_status'   => OrderStatus::Cancelled,
                    'payment_status' => $payment,
                    'cancel_reason'  => $data['cancel_reason'],
                    'cancelled_at'   => now(),
                ]);

                // Cascade: batalkan seluruh item aktif.
                $record->cancelItems();

                Notification::make()
                    ->title('Pesanan dibatalkan')
                    ->body("Pesanan {$record->order_number} dibatalkan.")
                    ->success()
                    ->send();
            });
    }

    /**
     * Tandai lunas secara manual (mis. pembayaran tunai). Idempotent via Order::markPaid().
     */
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

    /**
     * Sinkronkan status pembayaran dari Sekeco/Midtrans (source of truth).
     */
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
                $result = app(QrisService::class)->check($record->payment_reference);

                if ($result['paid']) {
                    $record->markPaid(closeBill: $record->order_type === OrderType::OpenBill);

                    Notification::make()
                        ->title('Pembayaran LUNAS')
                        ->body("Order {$record->order_number} sudah settlement di provider.")
                        ->success()
                        ->send();

                    return;
                }

                if (in_array($result['status'], ['expired', 'cancelled', 'denied'], true)) {
                    Notification::make()
                        ->title('Provider: ' . $result['status'])
                        ->body('Pembayaran tidak berhasil di provider.')
                        ->warning()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Masih menunggu')
                    ->body('Status provider: ' . $result['status'])
                    ->info()
                    ->send();
            });
    }

    /**
     * Batalkan transaksi QRIS yang masih pending di provider.
     */
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
                    app(QrisService::class)->cancel($record->payment_reference);
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Gagal membatalkan QRIS')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                $record->update(['payment_status' => PaymentStatus::Cancelled]);

                Notification::make()
                    ->title('QRIS dibatalkan')
                    ->body("QRIS pesanan {$record->order_number} dibatalkan.")
                    ->success()
                    ->send();
            });
    }

    /**
     * Tampilkan detail pembayaran dari Sekeco/Midtrans — di-fetch LAZY saat modal
     * dibuka (on click), bukan saat halaman dimuat.
     */
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
                // Konsumsi data Sekeco hanya di sini (saat modal dibuka).
                $result = app(QrisService::class)->check($record->payment_reference);

                return view('filament.orders.payment-detail', [
                    'order'  => $record,
                    'result' => $result,
                ]);
            });
    }
}
