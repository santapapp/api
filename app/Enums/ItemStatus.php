<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ItemStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Served = 'served';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending   => 'Menunggu',
            self::Preparing => 'Disiapkan',
            self::Ready     => 'Siap',
            self::Served    => 'Disajikan',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending   => 'gray',
            self::Preparing => 'warning',
            self::Ready     => 'primary',
            self::Served    => 'success',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Urutan progres dapur (semakin besar = semakin maju). Cancelled = -1.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Pending   => 0,
            self::Preparing => 1,
            self::Ready     => 2,
            self::Served    => 3,
            self::Cancelled => -1,
        };
    }

    /**
     * Tahap item berikutnya (pending→preparing→ready→served). Null bila sudah final.
     */
    public function next(): ?self
    {
        return match ($this) {
            self::Pending   => self::Preparing,
            self::Preparing => self::Ready,
            self::Ready     => self::Served,
            default         => null,
        };
    }
}
