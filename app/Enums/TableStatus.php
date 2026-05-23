<?php

declare(strict_types=1);

namespace App\Enums;

enum TableStatus: string
{
    case Available = 'available';
    case Occupied = 'occupied';
    case Reserved = 'reserved';
    case Inactive = 'inactive';
}
