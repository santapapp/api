<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderType: string implements HasColor, HasLabel
{
    case TableOrder = 'table_order';
    case CashierOrder = 'cashier_order';
    case OpenBill = 'open_bill';

    public function getLabel(): string
    {
        return match ($this) {
            self::TableOrder   => 'Pesan di Meja',
            self::CashierOrder => 'Pesan di Kasir',
            self::OpenBill     => 'Open Bill',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::TableOrder   => 'info',
            self::CashierOrder => 'gray',
            self::OpenBill     => 'warning',
        };
    }
}
