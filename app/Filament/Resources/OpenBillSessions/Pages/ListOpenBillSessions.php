<?php

declare(strict_types=1);

namespace App\Filament\Resources\OpenBillSessions\Pages;

use App\Enums\OrderType;
use App\Filament\Resources\OpenBillSessions\OpenBillSessionResource;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListOpenBillSessions extends ListRecords
{
    protected static string $resource = OpenBillSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
