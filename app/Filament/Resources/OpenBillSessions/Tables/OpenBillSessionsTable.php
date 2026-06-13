<?php

declare(strict_types=1);

namespace App\Filament\Resources\OpenBillSessions\Tables;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Support\Orders\OrderItemBatchSummary;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OpenBillSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Eager load relasi untuk hindari N+1. Base filter open_bill+open
            // sudah diterapkan di OpenBillSessionResource::getEloquentQuery().
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['organization', 'diningTable', 'createdBy', 'items'])
                ->withCount('items'))
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->columns([
                TextColumn::make('order_number')
                    ->label('No. Open Bill')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('organization.name')
                    ->label('Restoran')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('diningTable.name')
                    ->label('Meja')
                    ->placeholder('—')
                    ->description(fn (Order $record): ?string => $record->diningTable?->code
                        ? "Kode: {$record->diningTable->code}"
                        : null)
                    ->searchable(),

                TextColumn::make('createdBy.name')
                    ->label('Dibuat oleh')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('bill_status')
                    ->label('Sesi')
                    ->badge()
                    ->formatStateUsing(fn (Order $record): string => $record->getDerivedSessionStatus())
                    ->color(fn (Order $record): string => $record->getDerivedSessionStatusColor()),

                TextColumn::make('order_status')
                    ->label('Status Order')
                    ->badge(),

                TextColumn::make('payment_status')
                    ->label('Bayar')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('items_count')
                    ->label('Item')
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('batch_count')
                    ->label('Pesanan')
                    ->state(fn (Order $record): int => OrderItemBatchSummary::count($record->items))
                    ->formatStateUsing(fn (int $state): string => $state.' Pesanan')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('latest_batch')
                    ->label('Pesanan Terbaru')
                    ->state(function (Order $record): string {
                        $latest = OrderItemBatchSummary::latest($record->items);

                        if ($latest === null) {
                            return '-';
                        }

                        return sprintf(
                            '%s - %s item - Rp %s',
                            $latest['label'],
                            $latest['items_count'],
                            number_format((float) $latest['total_amount'], 0, ',', '.')
                        );
                    })
                    ->description(function (Order $record): ?string {
                        $latest = OrderItemBatchSummary::latest($record->items);

                        return $latest ? $latest['submitted_at'] : null;
                    })
                    ->wrap(),

                TextColumn::make('total_amount')
                    ->label('Total Sementara')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('opened_at')
                    ->label('Dibuka')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('updated_at')
                    ->label('Update Terakhir')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('organization_id')
                    ->relationship('organization', 'name')
                    ->label('Restoran')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('dining_table_id')
                    ->relationship('diningTable', 'name')
                    ->label('Meja')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Detail'),
                Action::make('view_qr')
                    ->label('Lihat QR')
                    ->icon('heroicon-o-qr-code')
                    ->color('success')
                    ->modalHeading(fn (Order $record): string => 'QR Open Bill — '.$record->order_number)
                    ->modalWidth('md')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->visible(fn (Order $record): bool => $record->order_type === OrderType::OpenBill &&
                        $record->bill_status === BillStatus::Open &&
                        $record->order_status !== OrderStatus::Cancelled &&
                        $record->cancelled_at === null &&
                        $record->closed_at === null &&
                        filled($record->public_token)
                    )
                    ->modalContent(fn (Order $record) => view(
                        'filament.open-bill-sessions.qr-modal',
                        [
                            'record' => $record,
                            // URL join open bill customer. Format ?bill={public_token}
                            // konsisten dengan jalur 'bill' di frontend santap_web.
                            'qrUrl' => rtrim(config('services.santap.web_url'), '/')
                                .'/o/'.$record->organization->slug
                                .'/orders?bill='.$record->public_token,
                        ]
                    )),
            ])
            ->toolbarActions([]);
    }
}
