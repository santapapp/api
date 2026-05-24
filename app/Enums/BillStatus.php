<?php

declare(strict_types=1);

namespace App\Enums;

enum BillStatus: string
{
    case None = 'none';
    case Open = 'open';
    case Closed = 'closed';
}
