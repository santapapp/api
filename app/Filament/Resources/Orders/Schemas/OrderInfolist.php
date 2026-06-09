<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Banner Sesi Dibatalkan (untuk open_bill yang dibatalkan)
                Section::make('⚠️ Sesi Open Bill Dibatalkan')
                    ->visible(fn ($record): bool => $record !== null && $record->order_type === \App\Enums\OrderType::OpenBill && ($record->isCancelled() || $record->cancelled_at !== null))
                    ->schema([
                        TextEntry::make('cancelled_session_note')
                            ->label('')
                            ->default('Open bill ini sudah dibatalkan dan QR session tidak dapat digunakan lagi.')
                            ->weight('bold')
                            ->color('danger'),
                        TextEntry::make('cancel_reason')
                            ->label('Alasan Batal')
                            ->placeholder('—'),
                        TextEntry::make('cancelled_at')
                            ->label('Waktu Batal')
                            ->dateTime('d M Y H:i')
                            ->placeholder('—'),
                    ])
                    ->columns(3),

                // Banner Sesi Ditutup (untuk open_bill yang sudah closed/paid)
                Section::make('✅ Sesi Open Bill Sudah Ditutup')
                    ->visible(fn ($record): bool => $record !== null && $record->order_type === \App\Enums\OrderType::OpenBill && ! ($record->isCancelled() || $record->cancelled_at !== null) && ($record->bill_status === \App\Enums\BillStatus::Closed || $record->closed_at !== null))
                    ->schema([
                        TextEntry::make('closed_session_note')
                            ->label('')
                            ->default('Customer tidak dapat menambahkan item baru melalui QR session.')
                            ->weight('bold')
                            ->color('gray'),
                    ]),

                // Banner Sesi Aktif (untuk open_bill yang masih aktif)
                Section::make('⚡ Sesi Open Bill Aktif')
                    ->visible(fn ($record): bool => $record !== null && $record->order_type === \App\Enums\OrderType::OpenBill && ! ($record->isCancelled() || $record->cancelled_at !== null) && $record->bill_status === \App\Enums\BillStatus::Open && $record->closed_at === null)
                    ->schema([
                        TextEntry::make('active_session_note')
                            ->label('')
                            ->default('Customer masih dapat menambahkan item melalui QR session.')
                            ->weight('bold')
                            ->color('success'),
                    ]),

                // Banner Order Biasa Dibatalkan
                Section::make('⚠️ Pesanan Dibatalkan')
                    ->visible(fn ($record): bool => $record !== null && $record->order_type !== \App\Enums\OrderType::OpenBill && $record->isCancelled())
                    ->schema([
                        TextEntry::make('cancel_reason')
                            ->label('Pesanan ini telah dibatalkan karena:')
                            ->weight('bold')
                            ->color('danger'),
                    ]),

                Section::make('Ringkasan Pesanan')
                    ->icon('heroicon-o-rectangle-stack')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('order_number')
                            ->label('No. Pesanan')
                            ->weight('bold')
                            ->copyable(),
                        TextEntry::make('organization.name')
                            ->label('Organisasi'),
                        TextEntry::make('diningTable.name')
                            ->label('Meja')
                            ->placeholder('—'),
                        TextEntry::make('order_type')
                            ->label('Tipe')
                            ->badge(),
                        TextEntry::make('bill_status')
                            ->label('Status Sesi')
                            ->badge()
                            ->formatStateUsing(fn (Order $record): string => $record->getDerivedSessionStatus())
                            ->color(fn (Order $record): string => $record->getDerivedSessionStatusColor()),
                        TextEntry::make('order_status')
                            ->label('Status Order')
                            ->badge(),
                        TextEntry::make('payment_status')
                            ->label('Status Bayar')
                            ->badge(),
                        TextEntry::make('opened_at')
                            ->label('Dibuka')
                            ->dateTime('d M Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('closed_at')
                            ->label('Ditutup')
                            ->dateTime('d M Y H:i')
                            ->placeholder('—')
                            ->visible(fn ($record): bool => $record !== null && $record->closed_at !== null),
                        TextEntry::make('cancelled_at')
                            ->label('Dibatalkan')
                            ->dateTime('d M Y H:i')
                            ->placeholder('—')
                            ->visible(fn ($record): bool => $record !== null && $record->cancelled_at !== null),
                        TextEntry::make('cancel_reason')
                            ->label('Alasan Batal')
                            ->placeholder('—')
                            ->visible(fn ($record): bool => $record !== null && filled($record->cancel_reason)),
                    ]),

                Section::make('Pelanggan')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('customer_name')
                            ->label('Nama')
                            ->placeholder('—'),
                        TextEntry::make('customer_phone')
                            ->label('Telepon')
                            ->placeholder('—'),
                    ]),

                Section::make('Pembayaran')
                    ->icon('heroicon-o-credit-card')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('payment_method')
                            ->label('Metode')
                            ->badge()
                            ->placeholder('—'),
                        TextEntry::make('payment_reference')
                            ->label('Referensi')
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('paid_at')
                            ->label('Dibayar')
                            ->dateTime('d M Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('payment_expires_at')
                            ->label('Kedaluwarsa')
                            ->dateTime('d M Y H:i')
                            ->placeholder('—'),
                    ]),

                Section::make('Rincian Biaya')
                    ->icon('heroicon-o-calculator')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('subtotal_amount')
                            ->label('Subtotal')
                            ->money('IDR'),
                        TextEntry::make('discount_amount')
                            ->label('Diskon')
                            ->money('IDR'),
                        TextEntry::make('tax_amount')
                            ->label(fn ($record): string => 'Pajak (' . rtrim(rtrim((string) $record->tax_rate_snapshot, '0'), '.') . '%)')
                            ->money('IDR'),
                        TextEntry::make('service_charge_amount')
                            ->label(fn ($record): string => 'Service (' . rtrim(rtrim((string) $record->service_charge_rate_snapshot, '0'), '.') . '%)')
                            ->money('IDR'),
                        TextEntry::make('total_amount')
                            ->label(fn (Order $record): string => 
                                $record->order_type === \App\Enums\OrderType::OpenBill && $record->isOpen() && ! $record->isCancelled()
                                    ? 'Total Sementara'
                                    : 'Total'
                            )
                            ->money('IDR')
                            ->weight('bold')
                            ->size(TextSize::Large),
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('payment_amount')
                                    ->label('Dibayar')
                                    ->money('IDR'),
                                TextEntry::make('change_amount')
                                    ->label('Kembalian')
                                    ->money('IDR'),
                            ]),
                    ]),
            ]);
    }
}
