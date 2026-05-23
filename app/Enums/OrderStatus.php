<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Cooking = 'cooking';
    case Ready = 'ready';
    case Served = 'served';
    case Cancelled = 'cancelled';
}
