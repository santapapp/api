<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\Actions\OrderActions;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['organization', 'diningTable'])
                ->withCount('items'))
            ->defaultSort('created_at', 'desc')
            ->poll('15s')
            ->columns([
                TextColumn::make('order_number')
                    ->label('No. Pesanan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),
                TextColumn::make('organization.name')
                    ->label('Organisasi')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('diningTable.name')
                    ->label('Meja')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->label('Pelanggan')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('order_type')
                    ->label('Tipe')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('order_status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('payment_status')
                    ->label('Bayar')
                    ->badge(),
                TextColumn::make('bill_status')
                    ->label('Bill')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_method')
                    ->label('Metode')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('items_count')
                    ->label('Item')
                    ->alignCenter()
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('organization_id')
                    ->relationship('organization', 'name')
                    ->label('Organisasi')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('order_type')
                    ->label('Tipe')
                    ->options(OrderType::class),
                SelectFilter::make('order_status')
                    ->label('Status Pesanan')
                    ->options(OrderStatus::class),
                SelectFilter::make('payment_status')
                    ->label('Status Bayar')
                    ->options(PaymentStatus::class),
                SelectFilter::make('bill_status')
                    ->label('Status Bill')
                    ->options(BillStatus::class),
                SelectFilter::make('payment_method')
                    ->label('Metode')
                    ->options([
                        'qris' => 'QRIS',
                        'cash' => 'Cash',
                    ]),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('created_from')->label('Dari'),
                        DatePicker::make('created_until')->label('Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    OrderActions::advanceStatus(),
                    OrderActions::paymentDetail(),
                    OrderActions::syncFromSekeco(),
                    OrderActions::markPaidCash(),
                    OrderActions::cancelQris(),
                    OrderActions::cancelOrder(),
                ]),
            ])
            ->toolbarActions([]);
    }
}
