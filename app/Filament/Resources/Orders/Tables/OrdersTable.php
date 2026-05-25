<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Enums\BillStatus;
use App\Enums\PaymentStatus;


class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->searchable(),
                TextColumn::make('organization.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('diningTable.name')
                    ->label('Table')
                    ->searchable(),
                TextColumn::make('order_type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('bill_status')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        BillStatus::Open => 'warning',
                        BillStatus::Closed => 'success',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('payment_status')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        PaymentStatus::Paid => 'success',
                        PaymentStatus::Unpaid => 'danger',
                        PaymentStatus::Pending => 'warning',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('total_amount')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('organization_id')
                    ->relationship('organization', 'name')
                    ->label('Organization'),
                \Filament\Tables\Filters\SelectFilter::make('bill_status')
                    ->options([
                        'open' => 'Open',
                        'closed' => 'Closed',
                    ]),
                \Filament\Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        'unpaid' => 'Unpaid',
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                    ]),
                \Filament\Tables\Filters\SelectFilter::make('order_status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'preparing' => 'Preparing',
                        'ready' => 'Ready',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('view_items')
                    ->label('View Items')
                    ->icon('heroicon-o-list-bullet')
                    ->modalContent(fn ($record) => view('filament.orders.items', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->toolbarActions([]);
    }
}
