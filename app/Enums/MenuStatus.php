<?php

declare(strict_types=1);

namespace App\Enums;

enum MenuStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case OutOfStock = 'out_of_stock';
}
