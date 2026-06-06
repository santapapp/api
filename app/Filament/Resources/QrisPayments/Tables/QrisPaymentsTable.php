<?php

namespace App\Filament\Resources\QrisPayments\Tables;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Services\OrderQrisPaymentService;
use App\Services\QrisService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QrisPaymentsTable
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
                TextColumn::make('payment_reference')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('payment_amount')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('payment_status')
                    ->badge(),
                TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Created At'),
            ])
            ->filters([
                SelectFilter::make('organization_id')
                    ->relationship('organization', 'name')
                    ->label('Organization')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('payment_status')
                    ->options(PaymentStatus::class),
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
                Action::make('check_status')
                    ->label('Check Status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn ($record) => 
                        $record->payment_status === PaymentStatus::Pending &&
                        !empty($record->payment_reference)
                    )
                    ->action(function ($record, QrisService $qris) {
                        try {
                            $sync = app(OrderQrisPaymentService::class)->sync($record, $qris);
                            $result = $sync['result'];

                            if ($result['paid']) {
                                Notification::make()
                                    ->title('Payment Paid')
                                    ->body("Payment for order {$record->order_number} has been paid.")
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Payment Pending')
                                    ->body("Payment status: " . ($result['status'] ?? 'pending'))
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

                Action::make('cancel_payment')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => 
                        $record->payment_status === PaymentStatus::Pending &&
                        !empty($record->payment_reference)
                    )
                    ->action(function ($record, QrisService $qris) {
                        try {
                            app(OrderQrisPaymentService::class)->cancel($record, $qris);
                            
                            Notification::make()
                                ->title('Payment Cancelled')
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
            ])
            ->toolbarActions([]);
    }
}
