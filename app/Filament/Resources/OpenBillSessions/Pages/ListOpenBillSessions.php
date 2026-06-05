<?php

declare(strict_types=1);

namespace App\Filament\Resources\OpenBillSessions\Pages;

use App\Filament\Resources\OpenBillSessions\OpenBillSessionResource;
use Filament\Resources\Pages\ListRecords;

class ListOpenBillSessions extends ListRecords
{
    protected static string $resource = OpenBillSessionResource::class;

    protected function getHeaderActions(): array
    {
        // Read-only: open bill dibuat lewat API cashier, bukan dari dashboard.
        return [];
    }
}
