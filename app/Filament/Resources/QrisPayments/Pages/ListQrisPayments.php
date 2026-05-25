<?php

namespace App\Filament\Resources\QrisPayments\Pages;

use App\Filament\Resources\QrisPayments\QrisPaymentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQrisPayments extends ListRecords
{
    protected static string $resource = QrisPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
