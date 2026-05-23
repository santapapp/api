<?php

declare(strict_types=1);

namespace App\Enums;

enum CustomerSessionStatus: string
{
    case Active = 'active';
    case Closed = 'closed';
    case Expired = 'expired';
}
