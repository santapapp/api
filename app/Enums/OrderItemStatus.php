<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderItemStatus: string
{
    case Pending = 'pending';
    case Cooking = 'cooking';
    case Ready = 'ready';
    case Served = 'served';
    case Cancelled = 'cancelled';
}
