<?php

declare(strict_types=1);

namespace App\Enums;

enum ItemStatus: string
{
    case Pending = 'pending';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Served = 'served';
    case Cancelled = 'cancelled';
}
