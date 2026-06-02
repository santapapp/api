<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Schemas;

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
                        TextEntry::make('order_status')
                            ->label('Status Pesanan')
                            ->badge(),
                        TextEntry::make('payment_status')
                            ->label('Status Bayar')
                            ->badge(),
                        TextEntry::make('bill_status')
                            ->label('Status Bill')
                            ->badge(),
                        TextEntry::make('created_at')
                            ->label('Dibuat')
                            ->dateTime('d M Y H:i'),
                        TextEntry::make('cancel_reason')
                            ->label('Alasan Batal')
                            ->placeholder('—')
                            ->visible(fn ($record): bool => filled($record->cancel_reason)),
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
                            ->label('Total')
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
