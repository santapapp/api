<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasColor, HasIcon, HasLabel
{
    case Unpaid = 'unpaid';
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unpaid    => 'Belum Bayar',
            self::Pending   => 'Menunggu',
            self::Paid      => 'Lunas',
            self::Failed    => 'Gagal',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Unpaid              => 'gray',
            self::Pending             => 'warning',
            self::Paid                => 'success',
            self::Failed, self::Cancelled => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Unpaid              => 'heroicon-o-banknotes',
            self::Pending             => 'heroicon-o-clock',
            self::Paid                => 'heroicon-o-check-circle',
            self::Failed, self::Cancelled => 'heroicon-o-x-circle',
        };
    }
}
