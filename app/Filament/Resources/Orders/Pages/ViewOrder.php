<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Services\QrisService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('check_qris_status')
                ->label('Check QRIS Status')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->visible(fn () => 
                    $this->record->payment_method === 'qris' &&
                    $this->record->payment_status === PaymentStatus::Pending &&
                    !empty($this->record->payment_reference)
                )
                ->action(function (QrisService $qris) {
                    try {
                        $result = $qris->check($this->record->payment_reference);
                        
                        if (($result['status'] ?? '') === 'paid') {
                            $this->record->update([
                                'payment_status' => PaymentStatus::Paid,
                                'payment_amount' => $this->record->total_amount,
                                'bill_status' => BillStatus::Closed,
                                'order_status' => OrderStatus::Completed,
                                'paid_at' => now(),
                                'closed_at' => now(),
                            ]);
                            
                            Notification::make()
                                ->title('Payment Paid')
                                ->body('QRIS payment has been paid successfully.')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Payment Pending')
                                ->body('QRIS payment is still pending. Status: ' . ($result['status'] ?? 'pending'))
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
                    $this->fillForm();
                }),

            Action::make('cancel_qris')
                ->label('Cancel QRIS')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => 
                    $this->record->payment_method === 'qris' &&
                    $this->record->payment_status === PaymentStatus::Pending &&
                    !empty($this->record->payment_reference)
                )
                ->action(function (QrisService $qris) {
                    try {
                        $qris->cancel($this->record->payment_reference);
                        
                        $this->record->update([
                            'payment_status' => PaymentStatus::Cancelled,
                            'payment_reference' => null,
                        ]);
                        
                        Notification::make()
                            ->title('QRIS Cancelled')
                            ->body('QRIS payment has been cancelled.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Failed to Cancel')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                    $this->fillForm();
                }),

            Action::make('mark_paid_manual')
                ->label('Mark Paid Manual')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->payment_status !== PaymentStatus::Paid)
                ->action(function () {
                    $this->record->update([
                        'payment_status' => PaymentStatus::Paid,
                        'payment_method' => $this->record->payment_method ?? 'cash',
                        'payment_amount' => $this->record->total_amount,
                        'bill_status' => BillStatus::Closed,
                        'order_status' => OrderStatus::Completed,
                        'paid_at' => now(),
                        'closed_at' => now(),
                    ]);
                    
                    Notification::make()
                        ->title('Marked as Paid')
                        ->body('Order has been manually marked as paid.')
                        ->success()
                        ->send();
                        
                    $this->fillForm();
                }),

            Action::make('close_bill')
                ->label('Close Bill')
                ->icon('heroicon-o-lock-closed')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->bill_status === BillStatus::Open)
                ->action(function () {
                    $this->record->update([
                        'bill_status' => BillStatus::Closed,
                        'order_status' => $this->record->order_status === OrderStatus::Cancelled
                            ? OrderStatus::Cancelled
                            : OrderStatus::Completed,
                        'closed_at' => now(),
                    ]);
                    
                    Notification::make()
                        ->title('Bill Closed')
                        ->body('Bill has been successfully closed.')
                        ->success()
                        ->send();
                        
                    $this->fillForm();
                }),
        ];
    }
}
