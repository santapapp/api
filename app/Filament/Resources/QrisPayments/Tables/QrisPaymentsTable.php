<?php

namespace App\Filament\Resources\QrisPayments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\Action;
use Filament\Tables\Table;
use App\Services\QrisService;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\PaymentStatus;


class QrisPaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')->searchable(),
                TextColumn::make('organization.name')->searchable(),
                TextColumn::make('payment_reference')->searchable(),
                TextColumn::make('payment_status')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        PaymentStatus::Paid => 'success',
                        PaymentStatus::Pending => 'warning',
                        PaymentStatus::Failed, PaymentStatus::Cancelled => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('total_amount')->money('IDR'),
                TextColumn::make('created_at')->dateTime()->sortable()->label('Time'),
            ])
            ->filters([
                SelectFilter::make('payment_status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
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
                Action::make('check_status')
                    ->label('Check Status')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function ($record) {
                        // Dummy check status logic for now
                        Notification::make()
                            ->title('Status Checked')
                            ->body('Status from Sekeco: ' . $record->payment_status)
                            ->success()
                            ->send();
                    })
                    ->visible(fn ($record) => $record->payment_status === 'pending'),
                Action::make('cancel_payment')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['payment_status' => 'cancelled']);
                        Notification::make()
                            ->title('Payment Cancelled')
                            ->success()
                            ->send();
                    })
                    ->visible(fn ($record) => $record->payment_status === 'pending'),
            ])
            ->toolbarActions([]);
    }
}
