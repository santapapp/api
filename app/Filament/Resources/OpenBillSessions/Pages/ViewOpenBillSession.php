<?php

declare(strict_types=1);

namespace App\Filament\Resources\OpenBillSessions\Pages;

use App\Filament\Resources\OpenBillSessions\OpenBillSessionResource;
use App\Filament\Resources\Orders\Actions\OrderActions;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewOpenBillSession extends ViewRecord
{
    protected static string $resource = OpenBillSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->viewSessionQrAction(),
            OrderActions::createQris(),
            OrderActions::paymentDetail(),
            OrderActions::syncFromSekeco(),
            OrderActions::cancelQris(),
            OrderActions::markPaidCash(),
            OrderActions::cancelOrder(),
        ];
    }

    private function viewSessionQrAction(): Action
    {
        return Action::make('view_session_qr')
            ->label('QR Session')
            ->icon('heroicon-o-qr-code')
            ->color('success')
            ->modalHeading(fn (Order $record): string => 'QR Open Bill - ' . $record->order_number)
            ->modalWidth('md')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->visible(fn (Order $record): bool => 
                $record->order_type === \App\Enums\OrderType::OpenBill &&
                $record->bill_status === \App\Enums\BillStatus::Open &&
                $record->order_status !== \App\Enums\OrderStatus::Cancelled &&
                $record->cancelled_at === null &&
                $record->closed_at === null &&
                filled($record->public_token)
            )
            ->modalContent(fn (Order $record) => view(
                'filament.open-bill-sessions.qr-modal',
                [
                    'record' => $record,
                    'qrUrl' => rtrim((string) config('services.santap.web_url'), '/')
                        . '/o/' . $record->organization->slug
                        . '/orders?bill=' . $record->public_token,
                ],
            ));
    }
}
