<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending   => 'Menunggu',
            self::Confirmed => 'Dikonfirmasi',
            self::Preparing => 'Disiapkan',
            self::Ready     => 'Siap',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending   => 'gray',
            self::Confirmed => 'info',
            self::Preparing => 'warning',
            self::Ready     => 'primary',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Pending   => 'heroicon-o-clock',
            self::Confirmed => 'heroicon-o-clipboard-document-check',
            self::Preparing => 'heroicon-o-fire',
            self::Ready     => 'heroicon-o-bell-alert',
            self::Completed => 'heroicon-o-check-circle',
            self::Cancelled => 'heroicon-o-x-circle',
        };
    }

    /**
     * Tahap order_status berikutnya dalam alur dapur (null bila sudah final).
     */
    public function next(): ?self
    {
        return match ($this) {
            self::Pending   => self::Confirmed,
            self::Confirmed => self::Preparing,
            self::Preparing => self::Ready,
            self::Ready     => self::Completed,
            default         => null,
        };
    }
}
