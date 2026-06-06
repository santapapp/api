<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\Actions\OrderActions;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OrderActions::advanceStatus(),
            OrderActions::createQris(),
            OrderActions::paymentDetail(),
            OrderActions::syncFromSekeco(),
            OrderActions::markPaidCash(),
            OrderActions::cancelQris(),
            OrderActions::cancelOrder(),
        ];
    }
}
