<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Services\QrisService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('organization.name')
                    ->label('Organization')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order_number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('diningTable.name')
                    ->label('Table')
                    ->placeholder('None')
                    ->searchable()
                    ->sortable(),
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
                TextColumn::make('order_status')
                    ->badge()
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
                TextColumn::make('payment_method')
                    ->badge()
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('total_amount')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('organization_id')
                    ->relationship('organization', 'name')
                    ->label('Organization')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('order_type')
                    ->options(OrderType::class),
                SelectFilter::make('bill_status')
                    ->options(BillStatus::class),
                SelectFilter::make('order_status')
                    ->options(OrderStatus::class),
                SelectFilter::make('payment_status')
                    ->options(PaymentStatus::class),
                SelectFilter::make('payment_method')
                    ->options([
                        'qris' => 'QRIS',
                        'cash' => 'Cash',
                    ]),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from'),
                        DatePicker::make('created_until'),
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
                    })
            ])
            ->recordActions([
                ViewAction::make(),
                
                Action::make('check_qris_status')
                    ->label('Check QRIS')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn ($record) => 
                        $record->payment_method === 'qris' &&
                        $record->payment_status === PaymentStatus::Pending &&
                        !empty($record->payment_reference)
                    )
                    ->action(function ($record, QrisService $qris) {
                        try {
                            $result = $qris->check($record->payment_reference);
                            
                            if (($result['status'] ?? '') === 'paid') {
                                $record->update([
                                    'payment_status' => PaymentStatus::Paid,
                                    'payment_amount' => $record->total_amount,
                                    'bill_status' => BillStatus::Closed,
                                    'order_status' => OrderStatus::Completed,
                                    'paid_at' => now(),
                                    'closed_at' => now(),
                                ]);
                                
                                Notification::make()
                                    ->title('Payment Paid')
                                    ->body("Order {$record->order_number} has been paid.")
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Payment Pending')
                                    ->body("QRIS payment is still pending. Status: " . ($result['status'] ?? 'pending'))
                                    ->warning()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Failed to Check Status')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('cancel_qris')
                    ->label('Cancel QRIS')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => 
                        $record->payment_method === 'qris' &&
                        $record->payment_status === PaymentStatus::Pending &&
                        !empty($record->payment_reference)
                    )
                    ->action(function ($record, QrisService $qris) {
                        try {
                            $qris->cancel($record->payment_reference);
                            
                            $record->update([
                                'payment_status' => PaymentStatus::Cancelled,
                                'payment_reference' => null,
                            ]);
                            
                            Notification::make()
                                ->title('QRIS Cancelled')
                                ->body("QRIS payment for order {$record->order_number} has been cancelled.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Failed to Cancel')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('mark_paid_manual')
                    ->label('Mark Paid')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->payment_status !== PaymentStatus::Paid)
                    ->action(function ($record) {
                        $record->update([
                            'payment_status' => PaymentStatus::Paid,
                            'payment_method' => $record->payment_method ?? 'cash',
                            'payment_amount' => $record->total_amount,
                            'bill_status' => BillStatus::Closed,
                            'order_status' => OrderStatus::Completed,
                            'paid_at' => now(),
                            'closed_at' => now(),
                        ]);
                        
                        Notification::make()
                            ->title('Marked as Paid')
                            ->body("Order {$record->order_number} has been manually marked as paid.")
                            ->success()
                            ->send();
                    }),

                Action::make('close_bill')
                    ->label('Close Bill')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->bill_status === BillStatus::Open)
                    ->action(function ($record) {
                        $record->update([
                            'bill_status' => BillStatus::Closed,
                            'order_status' => $record->order_status === OrderStatus::Cancelled
                                ? OrderStatus::Cancelled
                                : OrderStatus::Completed,
                            'closed_at' => now(),
                        ]);
                        
                        Notification::make()
                            ->title('Bill Closed')
                            ->body("Bill for order {$record->order_number} has been closed.")
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([]);
    }
}
